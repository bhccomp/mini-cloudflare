<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\SiteResource\Pages;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\InvalidateCloudFrontCacheJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\Site;
use App\Rules\ApexDomainRule;
use App\Rules\SafeOriginUrlRule;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereIn('organization_id', $user?->organizations()->select('organizations.id') ?? []);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Site onboarding')
                ->description('Set up your site in a few guided steps.')
                ->schema([
                    Forms\Components\Wizard::make([
                        Forms\Components\Wizard\Step::make('Domain')
                            ->description('Choose the domain you want to protect.')
                            ->schema([
                                Forms\Components\Hidden::make('organization_id')
                                    ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
                                Forms\Components\TextInput::make('display_name')
                                    ->label('Site name')
                                    ->placeholder('Main store')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('apex_domain')
                                    ->label('Primary domain')
                                    ->placeholder('example.com')
                                    ->required()
                                    ->helperText('You will point this domain in DNS in the final step.')
                                    ->rule(new ApexDomainRule),
                                Forms\Components\Toggle::make('www_enabled')
                                    ->label('Also protect www')
                                    ->helperText('Enable if you want to protect both example.com and www.example.com.')
                                    ->default(false)
                                    ->live(),
                            ])->columns(1),
                        Forms\Components\Wizard\Step::make('Origin')
                            ->description('Tell us where requests should be forwarded after inspection.')
                            ->schema([
                                Forms\Components\TextInput::make('origin_url')
                                    ->label('Origin URL')
                                    ->required()
                                    ->placeholder('https://origin.example.com')
                                    ->helperText('This should be your website origin URL. Private and local addresses are blocked for safety.')
                                    ->rule(new SafeOriginUrlRule)
                                    ->suffixAction(
                                        Actions\Action::make('testOrigin')
                                            ->label('Test')
                                            ->icon('heroicon-m-signal')
                                            ->action(function (Get $get, Set $set): void {
                                                $originUrl = trim((string) $get('origin_url'));

                                                if ($originUrl === '') {
                                                    $set('origin_test_feedback', 'Enter an origin URL before running a test.');

                                                    return;
                                                }

                                                try {
                                                    $response = Http::timeout(8)
                                                        ->withoutRedirecting()
                                                        ->get($originUrl);

                                                    if ($response->successful() || in_array($response->status(), [301, 302, 307, 308, 401, 403], true)) {
                                                        $set('origin_test_feedback', 'Origin reachable. Connection check succeeded.');

                                                        return;
                                                    }

                                                    $set('origin_test_feedback', "Origin responded with status {$response->status()}. Please verify the URL.");
                                                } catch (\Throwable $exception) {
                                                    $set('origin_test_feedback', 'Could not connect to origin. Check DNS, SSL, and firewall rules.');
                                                }
                                            })
                                    ),
                                Forms\Components\Hidden::make('origin_test_feedback')
                                    ->dehydrated(false),
                                Forms\Components\Placeholder::make('origin_test_result')
                                    ->label('Connectivity test')
                                    ->content(fn (Get $get): string => (string) ($get('origin_test_feedback') ?: 'Run a quick connectivity test before continuing.')),
                            ])->columns(1),
                        Forms\Components\Wizard\Step::make('SSL')
                            ->description('Your domain needs certificate validation before traffic can be routed.')
                            ->schema([
                                Forms\Components\Placeholder::make('ssl_info')
                                    ->label('What happens next')
                                    ->content('After you create the site, click Provision in the status hub. We will generate DNS records for certificate validation and guide you through the exact DNS updates needed.'),
                            ]),
                        Forms\Components\Wizard\Step::make('Review & Create')
                            ->description('Confirm details and create your protection layer.')
                            ->schema([
                                Forms\Components\Placeholder::make('review_domain')
                                    ->label('Domain')
                                    ->content(function (Get $get): string {
                                        $apex = (string) ($get('apex_domain') ?: 'Not set');

                                        if (! $get('www_enabled')) {
                                            return $apex;
                                        }

                                        return $apex.' and www.'.$apex;
                                    }),
                                Forms\Components\Placeholder::make('review_origin')
                                    ->label('Origin URL')
                                    ->content(fn (Get $get): string => (string) ($get('origin_url') ?: 'Not set')),
                                Forms\Components\Placeholder::make('review_note')
                                    ->label('Next action after creation')
                                    ->content('Open the site status hub, click Provision, and follow DNS instructions until the site becomes active.'),
                            ])->columns(1),
                    ])->columnSpanFull(),
                ]),
            Section::make('Status hub')
                ->description('Track provisioning state and follow the next required action.')
                ->schema([
                    Forms\Components\Placeholder::make('status_badge')
                        ->label('Status')
                        ->content(fn (?Site $record) => $record?->status ?? 'draft'),
                    Forms\Components\Placeholder::make('next_step')
                        ->label('Next step')
                        ->content(fn (?Site $record) => $record ? static::nextStep($record) : 'Complete the onboarding wizard first.'),
                    Forms\Components\Textarea::make('required_dns_records')
                        ->label('DNS instructions')
                        ->rows(12)
                        ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No DNS records yet. Click Provision to generate them.')
                        ->dehydrated(false)
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('last_error')
                        ->label('Last error')
                        ->dehydrated(false)
                        ->disabled()
                        ->visible(fn (?Site $record) => filled($record?->last_error)),
                ])
                ->visible(fn (?Site $record) => $record !== null)
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Actions\Action::make('addSiteSecondary')
                    ->label('Add Site')
                    ->icon('heroicon-m-plus')
                    ->color('gray')
                    ->url(static::getUrl('create')),
            ])
            ->emptyStateHeading('Protect your first site')
            ->emptyStateDescription('Add your first protected site to start guided onboarding, DNS setup, and traffic protection.')
            ->emptyStateActions([
                Actions\Action::make('addFirstSite')
                    ->label('Add your first protected site')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(static::getUrl('create')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('apex_domain')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('under_attack')->label('Under Attack')->boolean(),
                Tables\Columns\TextColumn::make('cloudfront_domain_name')->label('CloudFront')->toggleable(),
                Tables\Columns\TextColumn::make('next_step')->state(fn (Site $record) => static::nextStep($record))->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\Action::make('provision')
                    ->label('Provision')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'provision');
                        RequestAcmCertificateJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('Provision request queued')->success()->send();
                    }),
                Actions\Action::make('checkDns')
                    ->label('Check DNS')
                    ->color('info')
                    ->action(function (Site $record): void {
                        static::throttle($record, 'check-dns');
                        CheckAcmDnsValidationJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('DNS check queued')->success()->send();
                    }),
                Actions\Action::make('underAttack')
                    ->label(fn (Site $record): string => $record->under_attack ? 'Disable Under Attack' : 'Enable Under Attack')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'under-attack');
                        ToggleUnderAttackModeJob::dispatch($record->id, ! $record->under_attack, auth()->id());
                        Notification::make()->title('Under attack update queued')->success()->send();
                    }),
                Actions\Action::make('purgeCache')
                    ->label('Purge cache')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'purge-cache');
                        InvalidateCloudFrontCacheJob::dispatch($record->id, ['/*'], auth()->id());
                        Notification::make()->title('Invalidation queued')->success()->send();
                    }),
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    protected static function throttle(Site $site, string $action): void
    {
        $key = sprintf('site-action:%s:%s:%s', auth()->id(), $site->id, $action);

        if (! RateLimiter::attempt($key, maxAttempts: 3, callback: static fn () => true, decaySeconds: 60)) {
            abort(429, 'Too many sensitive requests. Please retry in a minute.');
        }
    }

    protected static function nextStep(Site $site): string
    {
        return match ($site->status) {
            'draft' => 'Click Provision to generate SSL validation DNS records.',
            'pending_dns' => 'Add validation DNS records, then click Check DNS.',
            'provisioning' => 'Provisioning in progress. Follow DNS target instructions and check again.',
            'active' => 'Site is active and protected.',
            'failed' => 'Review last error and retry Provision.',
            default => 'Review site status.',
        };
    }
}
