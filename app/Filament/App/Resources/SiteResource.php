<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Resources\SiteResource\Pages;
use App\Jobs\ApplySiteControlSettingJob;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\InvalidateCloudFrontCacheJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\Site;
use App\Rules\ApexDomainRule;
use App\Rules\SafeOriginUrlRule;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                    \Filament\Schemas\Components\Wizard::make([
                        \Filament\Schemas\Components\Wizard\Step::make('Domain')
                            ->description('Choose the domain you want to protect with Bunny edge by default.')
                            ->schema([
                                Forms\Components\Hidden::make('organization_id')
                                    ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
                                Forms\Components\Hidden::make('www_enabled')
                                    ->default(true),
                                Forms\Components\TextInput::make('apex_domain')
                                    ->label('Domain')
                                    ->placeholder('example.com or https://example.com')
                                    ->required()
                                    ->helperText('Enter your root domain. You can paste a URL and we will normalize it.')
                                    ->rule(function (): \Closure {
                                        return function (string $attribute, mixed $value, \Closure $fail): void {
                                            (new ApexDomainRule)->validate(
                                                $attribute,
                                                static::normalizeDomainInput((string) $value),
                                                $fail
                                            );
                                        };
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): string => static::normalizeDomainInput($state))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                        $domain = static::normalizeDomainInput($state);
                                        $currentOrigin = trim((string) $get('origin_url'));

                                        if ($domain !== '' && $currentOrigin === '') {
                                            $set('origin_url', 'https://'.$domain);
                                        }
                                    }),
                            ])->columns(1),
                        \Filament\Schemas\Components\Wizard\Step::make('Origin')
                            ->description('Tell us where requests should be forwarded after inspection.')
                            ->schema([
                                Forms\Components\TextInput::make('origin_url')
                                    ->label('Origin URL')
                                    ->required()
                                    ->placeholder('origin.example.com or https://origin.example.com')
                                    ->helperText('Host-only values are accepted and normalized to https://. Private and local addresses are blocked for safety.')
                                    ->rule(function (): \Closure {
                                        return function (string $attribute, mixed $value, \Closure $fail): void {
                                            (new SafeOriginUrlRule)->validate(
                                                $attribute,
                                                static::normalizeOriginInput((string) $value),
                                                $fail
                                            );
                                        };
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): string => static::normalizeOriginInput((string) $state))
                                    ->suffixAction(
                                        Actions\Action::make('testOrigin')
                                            ->label('Test')
                                            ->icon('heroicon-m-signal')
                                            ->action(function (Get $get, Set $set): void {
                                                $originUrl = static::normalizeOriginInput((string) $get('origin_url'));

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
                        \Filament\Schemas\Components\Wizard\Step::make('Advanced')
                            ->description('Optional controls for provider selection and rollout behavior.')
                            ->schema([
                                Forms\Components\Toggle::make('show_advanced_provider')
                                    ->label('I want to choose provider manually')
                                    ->dehydrated(false)
                                    ->default(false),
                                Forms\Components\Select::make('provider')
                                    ->label('Edge provider')
                                    ->options([
                                        Site::PROVIDER_BUNNY => 'Bunny.net (Recommended)',
                                        Site::PROVIDER_AWS => 'AWS (Advanced)',
                                    ])
                                    ->default((string) config('edge.default_provider', Site::PROVIDER_BUNNY))
                                    ->visible(fn (Get $get): bool => (bool) config('edge.feature_aws_onboarding', false) || (bool) $get('show_advanced_provider')),
                                Forms\Components\Placeholder::make('advanced_note')
                                    ->label('Onboarding mode')
                                    ->content('Bunny flow skips pre-validation and activates SSL after DNS cutover. AWS flow remains available for advanced users.'),
                            ]),
                        \Filament\Schemas\Components\Wizard\Step::make('Review & Create')
                            ->description('Confirm details and create your protection layer.')
                            ->schema([
                                Forms\Components\Placeholder::make('review_domain')
                                    ->label('Domain')
                                    ->content(function (Get $get): string {
                                        $apex = (string) ($get('apex_domain') ?: 'Not set');

                                        if ($apex === 'Not set') {
                                            return $apex;
                                        }

                                        return $apex.' and www.'.$apex;
                                    }),
                                Forms\Components\Placeholder::make('review_origin')
                                    ->label('Origin URL')
                                    ->content(fn (Get $get): string => (string) ($get('origin_url') ?: 'Not set')),
                                Forms\Components\Placeholder::make('review_note')
                                    ->label('Next action after creation')
                                    ->content('Open the Site Status Hub, provision edge, update DNS, and verify cutover until protection is live.'),
                            ])->columns(1),
                    ])->columnSpanFull(),
                ])
                ->visible(fn (string $operation): bool => $operation === 'create'),

            Section::make('SSL')
                ->icon('heroicon-o-lock-closed')
                ->description('Certificate and secure transport controls for your protected domain.')
                ->schema([
                    Forms\Components\Placeholder::make('ssl_certificate_status')
                        ->label('Certificate status')
                        ->content(fn (?Site $record): string => $record?->acm_certificate_arn ? 'Issued or pending validation' : 'Not requested yet'),
                    Forms\Components\Toggle::make('https_enforced_control')
                        ->label('Force HTTPS for visitors')
                        ->helperText('Keeps visitor traffic on encrypted connections only.')
                        ->dehydrated(false)
                        ->default(fn (?Site $record): bool => (bool) static::controlValue($record, 'https_enforced', true))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record) {
                                return;
                            }

                            static::queuePlaceholderControl(
                                $record,
                                'https_enforced',
                                (bool) $state,
                                'HTTPS enforcement update queued.'
                            );
                        }),
                    Forms\Components\Placeholder::make('ssl_expiry')
                        ->label('Certificate expiration')
                        ->content('Coming soon'),
                    Forms\Components\Placeholder::make('ssl_explanation')
                        ->label('How this works')
                        ->content('We handle certificate issuance and guide you through any DNS records needed. No certificate management is required on your side.'),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'edit'),

            Section::make('CDN')
                ->icon('heroicon-o-cloud')
                ->description('Traffic delivery and edge routing status.')
                ->headerActions([
                    Actions\Action::make('purgeCacheFromCdn')
                        ->label('Purge cache')
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (?Site $record): void {
                            if (! $record) {
                                return;
                            }

                            static::throttle($record, 'purge-cache-cdn');
                            InvalidateCloudFrontCacheJob::dispatch($record->id, ['/*'], auth()->id());
                            Notification::make()->title('Cache purge queued')->success()->send();
                        }),
                ])
                ->schema([
                    Forms\Components\Placeholder::make('cdn_distribution_status')
                        ->label('Distribution status')
                        ->content(fn (?Site $record): string => $record?->cloudfront_distribution_id ? 'Provisioned' : 'Not provisioned yet'),
                    Forms\Components\Placeholder::make('cdn_domain')
                        ->label('Distribution domain')
                        ->content(fn (?Site $record): string => $record?->cloudfront_domain_name ?: 'Waiting for provisioning'),
                    Forms\Components\Placeholder::make('cdn_health')
                        ->label('Health')
                        ->content(function (?Site $record): string {
                            if (! $record?->cloudfront_distribution_id) {
                                return 'Needs setup';
                            }

                            return $record->status === Site::STATUS_ACTIVE ? 'Healthy' : 'Provisioning';
                        }),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'edit'),

            Section::make('Cache')
                ->icon('heroicon-o-bolt')
                ->description('Control response caching behavior at the edge.')
                ->headerActions([
                    Actions\Action::make('purgeCacheFromCacheCard')
                        ->label('Purge cache')
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (?Site $record): void {
                            if (! $record) {
                                return;
                            }

                            static::throttle($record, 'purge-cache-card');
                            InvalidateCloudFrontCacheJob::dispatch($record->id, ['/*'], auth()->id());
                            Notification::make()->title('Cache purge queued')->success()->send();
                        }),
                ])
                ->schema([
                    Forms\Components\Toggle::make('cache_enabled_control')
                        ->label('Cache enabled')
                        ->dehydrated(false)
                        ->default(fn (?Site $record): bool => (bool) static::controlValue($record, 'cache_enabled', true))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record) {
                                return;
                            }

                            static::queuePlaceholderControl(
                                $record,
                                'cache_enabled',
                                (bool) $state,
                                'Cache toggle queued.'
                            );
                        }),
                    Forms\Components\Select::make('cache_mode_control')
                        ->label('Cache mode')
                        ->options([
                            'standard' => 'Standard',
                            'aggressive' => 'Aggressive',
                        ])
                        ->dehydrated(false)
                        ->default(fn (?Site $record): string => (string) static::controlValue($record, 'cache_mode', 'standard'))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record || ! is_string($state)) {
                                return;
                            }

                            static::queuePlaceholderControl(
                                $record,
                                'cache_mode',
                                $state,
                                'Cache mode update queued.'
                            );
                        }),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'edit'),

            Section::make('WAF')
                ->icon('heroicon-o-shield-check')
                ->description('Application-layer protection controls and traffic filtering.')
                ->schema([
                    Forms\Components\Placeholder::make('waf_status')
                        ->label('Protection status')
                        ->content(fn (?Site $record): string => $record?->waf_web_acl_arn ? 'Protection active' : 'Protection pending'),
                    Forms\Components\Toggle::make('under_attack_control')
                        ->label('Under attack mode')
                        ->helperText('Tightens traffic filtering during spikes or abuse events.')
                        ->dehydrated(false)
                        ->default(fn (?Site $record): bool => (bool) ($record?->under_attack ?? false))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record) {
                                return;
                            }

                            static::throttle($record, 'under-attack-control');
                            ToggleUnderAttackModeJob::dispatch($record->id, (bool) $state, auth()->id());
                            Notification::make()->title('Under attack update queued')->success()->send();
                        }),
                    Forms\Components\Select::make('waf_ruleset_preset_control')
                        ->label('Ruleset preset')
                        ->options([
                            'baseline' => 'Baseline',
                            'strict' => 'Strict',
                        ])
                        ->dehydrated(false)
                        ->default(fn (?Site $record): string => (string) static::controlValue($record, 'waf_preset', 'baseline'))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record || ! is_string($state)) {
                                return;
                            }

                            static::queuePlaceholderControl(
                                $record,
                                'waf_preset',
                                $state,
                                'Ruleset preset update queued.'
                            );
                        }),
                    Forms\Components\Placeholder::make('waf_blocked_metric')
                        ->label('Blocked traffic (24h)')
                        ->content('Coming soon'),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'edit'),

            Section::make('Origin')
                ->icon('heroicon-o-server-stack')
                ->description('Origin endpoint details and access hardening guidance.')
                ->schema([
                    Forms\Components\Placeholder::make('origin_host_display')
                        ->label('Origin host')
                        ->content(function (?Site $record): string {
                            if (! $record?->origin_url) {
                                return 'Not configured';
                            }

                            return parse_url($record->origin_url, PHP_URL_HOST) ?: $record->origin_url;
                        }),
                    Forms\Components\Placeholder::make('origin_warning')
                        ->label('Direct access warning')
                        ->content('Keep direct origin access restricted where possible so visitors pass through the protection layer.'),
                    Forms\Components\Toggle::make('origin_protection_control')
                        ->label('Origin access lock (placeholder)')
                        ->dehydrated(false)
                        ->default(fn (?Site $record): bool => (bool) static::controlValue($record, 'origin_lockdown', false))
                        ->live()
                        ->afterStateUpdated(function (?Site $record, mixed $state): void {
                            if (! $record) {
                                return;
                            }

                            static::queuePlaceholderControl(
                                $record,
                                'origin_lockdown',
                                (bool) $state,
                                'Origin protection update queued.'
                            );
                        }),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Site $record): string => SiteStatusHubPage::getUrl(['site_id' => $record->id]))
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
                Tables\Columns\TextColumn::make('step')
                    ->label('Step')
                    ->state(fn (Site $record) => static::nextStepLabel($record))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\Action::make('openDashboard')
                    ->label('Open status hub')
                    ->color('primary')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->action(function (Site $record): void {
                        session(['selected_site_id' => $record->id]);
                        Notification::make()->title('Site selected for status hub context.')->success()->send();
                    })
                    ->url(fn (Site $record): string => SiteStatusHubPage::getUrl(['site_id' => $record->id])),
                Actions\Action::make('provision')
                    ->label('Provision')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'provision');
                        RequestAcmCertificateJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('Provision request queued')->success()->send();
                    })
                    ->visible(fn (Site $record): bool => in_array($record->status, [Site::STATUS_DRAFT, Site::STATUS_FAILED], true)),
                Actions\Action::make('checkDns')
                    ->label('Check DNS (validation)')
                    ->color('info')
                    ->action(function (Site $record): void {
                        static::throttle($record, 'check-dns');
                        CheckAcmDnsValidationJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('DNS check queued')->success()->send();
                    })
                    ->visible(fn (Site $record): bool => $record->status === Site::STATUS_PENDING_DNS_VALIDATION),
                Actions\Action::make('checkCutover')
                    ->label('Check cutover')
                    ->color('info')
                    ->action(function (Site $record): void {
                        static::throttle($record, 'check-cutover');
                        CheckAcmDnsValidationJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('Cutover check queued')->success()->send();
                    })
                    ->visible(fn (Site $record): bool => $record->status === Site::STATUS_READY_FOR_CUTOVER),
                Actions\Action::make('underAttack')
                    ->label(fn (Site $record): string => $record->under_attack ? 'Disable Under Attack' : 'Enable Under Attack')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'under-attack');
                        ToggleUnderAttackModeJob::dispatch($record->id, ! $record->under_attack, auth()->id());
                        Notification::make()->title('Under attack update queued')->success()->send();
                    })
                    ->visible(fn (Site $record): bool => filled($record->waf_web_acl_arn)),
                Actions\Action::make('purgeCache')
                    ->label('Purge cache')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'purge-cache');
                        InvalidateCloudFrontCacheJob::dispatch($record->id, ['/*'], auth()->id());
                        Notification::make()->title('Invalidation queued')->success()->send();
                    })
                    ->visible(fn (Site $record): bool => filled($record->cloudfront_distribution_id)),
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

    protected static function queuePlaceholderControl(Site $site, string $setting, mixed $value, string $message): void
    {
        static::throttle($site, 'control-'.$setting);

        ApplySiteControlSettingJob::dispatch($site->id, $setting, $value, auth()->id());

        Notification::make()->title($message)->success()->send();
    }

    protected static function controlValue(?Site $site, string $setting, mixed $default): mixed
    {
        if (! $site) {
            return $default;
        }

        return data_get($site->required_dns_records, 'control_panel.'.$setting, $default);
    }

    protected static function nextStep(Site $site): string
    {
        return match ($site->status) {
            Site::STATUS_DRAFT => 'Click Provision to generate SSL validation DNS records.',
            Site::STATUS_PENDING_DNS_VALIDATION => 'Add validation DNS records, then click Check DNS.',
            Site::STATUS_DEPLOYING => 'Deploying WAF and CloudFront. Wait until ready for cutover.',
            Site::STATUS_READY_FOR_CUTOVER => 'Update apex/www traffic DNS to CloudFront, then click Check cutover.',
            Site::STATUS_ACTIVE => 'Site is active and protected.',
            Site::STATUS_FAILED => 'Review last error and retry Provision.',
            default => 'Review site status.',
        };
    }

    protected static function nextStepLabel(Site $site): string
    {
        return match ($site->status) {
            Site::STATUS_DRAFT => 'Provision',
            Site::STATUS_PENDING_DNS_VALIDATION => 'Validate DNS',
            Site::STATUS_DEPLOYING => 'Deploying',
            Site::STATUS_READY_FOR_CUTOVER => 'Cutover DNS',
            Site::STATUS_ACTIVE => 'Active',
            Site::STATUS_FAILED => 'Retry',
            default => 'Review',
        };
    }

    protected static function normalizeDomainInput(?string $value): string
    {
        $input = strtolower(trim((string) $value));

        if ($input === '') {
            return '';
        }

        $candidate = str_contains($input, '://') ? $input : 'https://'.ltrim($input, '/');
        $host = parse_url($candidate, PHP_URL_HOST) ?: $input;
        $host = explode(':', $host)[0];
        $host = trim($host, '.');

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    protected static function normalizeOriginInput(?string $value): string
    {
        $input = trim((string) $value);

        if ($input === '') {
            return '';
        }

        if (! str_contains($input, '://')) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return $input;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf('%s://%s%s%s%s%s', $scheme, $host, $port, $path, $query, $fragment);
    }
}
