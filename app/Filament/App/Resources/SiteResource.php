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
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            Section::make('Site Onboarding Wizard')
                ->schema([
                    Forms\Components\Wizard::make([
                        Forms\Components\Wizard\Step::make('Domain')
                            ->schema([
                                Forms\Components\Hidden::make('organization_id')
                                    ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
                                Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                                Forms\Components\TextInput::make('apex_domain')->required()->rule(new ApexDomainRule),
                                Forms\Components\Toggle::make('www_enabled')->label('Also protect www')->default(false),
                            ]),
                        Forms\Components\Wizard\Step::make('Origin')
                            ->schema([
                                Forms\Components\TextInput::make('origin_url')
                                    ->label('Origin URL')
                                    ->required()
                                    ->placeholder('https://origin.example.com')
                                    ->rule(new SafeOriginUrlRule),
                            ]),
                        Forms\Components\Wizard\Step::make('Request SSL')
                            ->schema([
                                Forms\Components\Placeholder::make('ssl_step')
                                    ->content('After saving, click Provision to request ACM certificate and get DNS validation records.'),
                            ]),
                        Forms\Components\Wizard\Step::make('Provision Protection')
                            ->schema([
                                Forms\Components\Placeholder::make('protect_step')
                                    ->content('After certificate validation, click Check DNS to provision CloudFront + AWS WAF.'),
                            ]),
                        Forms\Components\Wizard\Step::make('Activate')
                            ->schema([
                                Forms\Components\Placeholder::make('activate_step')
                                    ->content('Final step: point your domain to CloudFront and use Check DNS to mark site active.'),
                            ]),
                    ])->columnSpanFull(),
                ]),
            Section::make('Status Hub')
                ->schema([
                    Forms\Components\Placeholder::make('status_badge')
                        ->label('Status')
                        ->content(fn (?Site $record) => $record?->status ?? 'draft'),
                    Forms\Components\Placeholder::make('next_step')
                        ->label('Next step')
                        ->content(fn (?Site $record) => $record ? static::nextStep($record) : 'Complete wizard and create the site.'),
                    Forms\Components\Textarea::make('required_dns_records')
                        ->label('DNS Instructions')
                        ->rows(12)
                        ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'Coming soon')
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
                        Notification::make()->title('ACM request queued')->success()->send();
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
            'draft' => 'Click Provision to request ACM certificate.',
            'pending_dns' => 'Add ACM validation DNS records, then click Check DNS.',
            'provisioning' => 'Provisioning resources. After DNS points to CloudFront, click Check DNS again.',
            'active' => 'Site is active and protected.',
            'failed' => 'Review last error and retry Provision.',
            default => 'Review site status.',
        };
    }
}
