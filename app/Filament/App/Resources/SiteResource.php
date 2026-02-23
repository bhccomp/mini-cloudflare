<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\SiteResource\Pages;
use App\Jobs\CheckSiteDnsAndFinalizeProvisioningJob;
use App\Jobs\InvalidateCacheJob;
use App\Jobs\StartSiteProvisioningJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\Site;
use App\Rules\ApexDomainRule;
use App\Rules\SafeOriginUrlRule;
use Filament\Actions;
use Filament\Forms;
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
            Forms\Components\Hidden::make('organization_id')
                ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
            Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('apex_domain')->required()->rule(new ApexDomainRule),
            Forms\Components\TextInput::make('www_domain')->rule(new ApexDomainRule),
            Forms\Components\Select::make('origin_type')
                ->required()
                ->default('url')
                ->options([
                    'url' => 'URL',
                    'ip' => 'Host/IP',
                ])
                ->live(),
            Forms\Components\TextInput::make('origin_url')
                ->label('Origin URL')
                ->required(fn (callable $get) => $get('origin_type') === 'url')
                ->visible(fn (callable $get) => $get('origin_type') === 'url')
                ->rule(new SafeOriginUrlRule),
            Forms\Components\TextInput::make('origin_host')
                ->label('Origin Host or IP')
                ->required(fn (callable $get) => $get('origin_type') === 'ip')
                ->visible(fn (callable $get) => $get('origin_type') === 'ip')
                ->helperText('Public hostname or public IP only.'),
            Forms\Components\Placeholder::make('status_info')
                ->content(fn (?Site $record): string => $record
                    ? "Status: {$record->status}".($record->last_error ? " | Last error: {$record->last_error}" : '')
                    : 'Status will appear after creating the site.'),
            Forms\Components\Textarea::make('required_dns_records')
                ->label('DNS Instructions (JSON)')
                ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null)
                ->rows(12)
                ->columnSpanFull()
                ->dehydrated(false)
                ->disabled()
                ->visible(fn (?Site $record) => $record !== null),
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
                Tables\Columns\IconColumn::make('under_attack_mode_enabled')->label('Under Attack')->boolean(),
                Tables\Columns\TextColumn::make('cloudfront_domain_name')->label('CloudFront Target')->toggleable(),
                Tables\Columns\TextColumn::make('next_step')
                    ->state(function (Site $record): string {
                        return match ($record->status) {
                            'draft' => 'Click Provision',
                            'pending_dns' => 'Add ACM CNAME(s), then click Check DNS',
                            'provisioning' => 'Provisioning in progress',
                            'active' => 'Point domain to CloudFront target',
                            'failed' => 'Fix issue and retry Provision',
                            default => 'Review site status',
                        };
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\Action::make('provision')
                    ->label('Provision')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'provision');
                        StartSiteProvisioningJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('Provisioning started')->success()->send();
                    }),
                Actions\Action::make('checkDns')
                    ->label('Check DNS')
                    ->color('info')
                    ->visible(fn (Site $record): bool => in_array($record->status, ['pending_dns', 'provisioning'], true))
                    ->action(function (Site $record): void {
                        static::throttle($record, 'check-dns');
                        CheckSiteDnsAndFinalizeProvisioningJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('DNS check queued')->success()->send();
                    }),
                Actions\Action::make('underAttack')
                    ->label(fn (Site $record): string => $record->under_attack_mode_enabled ? 'Disable Under Attack' : 'Enable Under Attack')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'under-attack');
                        ToggleUnderAttackModeJob::dispatch($record->id, ! $record->under_attack_mode_enabled, auth()->id());
                        Notification::make()->title('Under-attack mode update queued')->success()->send();
                    }),
                Actions\Action::make('purgeCache')
                    ->label('Purge Cache')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'purge');
                        InvalidateCacheJob::dispatch($record->id, ['/*'], auth()->id());
                        Notification::make()->title('Cache purge queued')->success()->send();
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
}
