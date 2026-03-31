<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\Firewall\FirewallAccessRulesTable;
use App\Models\AuditLog;
use App\Models\SiteFirewallRule;
use App\Services\Bunny\Waf\BunnyShieldWafService;
use App\Services\Firewall\FirewallAccessControlService;
use App\Services\Firewall\FirewallPresetService;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

class FirewallAccessControlPage extends BaseProtectionPage implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'firewall-access-control';

    protected static ?int $navigationSort = -39;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'WAF';

    protected static ?string $title = 'WAF';

    protected string $view = 'filament.app.pages.protection.firewall-access-control';

    public ?array $data = [];

    public array $botData = [];

    /** @var array<int, array<string, mixed>> */
    public array $wafEventLogs = [];

    /** @var array<int, array<string, mixed>> */
    public array $wafTriggeredRules = [];

    /** @var array<int, array<string, mixed>> */
    public array $customWafRules = [];

    /** @var array<string, mixed> */
    public array $wafMetrics = [];

    /** @var array<string, mixed> */
    public array $botMetrics = [];

    public bool $advancedWafAvailable = false;

    public bool $wafStateLoaded = false;

    /**
     * @var array<string, string>
     */
    public array $countryOptions = [];

    /**
     * @var array<string, string>
     */
    public array $continentOptions = [];

    public function mount(Request $request, SiteContext $siteContext, UiModeManager $uiMode): void
    {
        parent::mount($request, $siteContext, $uiMode);

        $this->syncOptions();
        $this->advancedWafAvailable = $this->hasAdvancedWafSupport();
        $this->botData = $this->initialBotData();

        $policy = $this->policyFlags();
        $this->form->fill([
            'rule_action' => SiteFirewallRule::ACTION_BLOCK,
            'rule_name' => null,
            'country_codes' => [],
            'continent_code' => null,
            'ip_value' => null,
            'ip_type' => SiteFirewallRule::TYPE_IP,
            'bulk_values' => null,
            'expires_at' => null,
            'note' => null,
            'staging_mode' => (bool) ($policy['staging_mode'] ?? false),
            'allowlist_priority' => (bool) ($policy['allowlist_priority'] ?? true),
            'advanced_rule_name' => 'Header anomaly block',
            'advanced_rule_description' => 'Block a suspicious request pattern before it reaches the origin.',
            'advanced_rule_action' => 'block',
            'advanced_rule_variable' => 'REQUEST_URI',
            'advanced_rule_operator' => 'contains',
            'advanced_rule_transformation' => 'lower',
            'advanced_rule_value' => '/wp-admin',
            'advanced_rule_conditions' => [],
        ]);

        if ($this->site) {
            app(FirewallAccessControlService::class)->expireTemporaryRules($this->site, (int) auth()->id());
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('access_control_tabs')
                    ->tabs([
                        Tab::make('Country')
                            ->icon('heroicon-o-flag')
                            ->schema([
                                Select::make('rule_action')
                                    ->label('Action')
                                    ->options(SiteFirewallRule::actionOptions())
                                    ->required(),
                                TextInput::make('rule_name')
                                    ->label('Rule name (optional)')
                                    ->maxLength(120)
                                    ->helperText('Use a friendly name if you do not want FirePhage to generate one.'),
                                Select::make('country_codes')
                                    ->label('Countries')
                                    ->options($this->countryOptions)
                                    ->multiple()
                                    ->searchable()
                                    ->helperText('Select one or more countries to apply this rule.'),
                                DateTimePicker::make('expires_at')
                                    ->label('Temporary until')
                                    ->native(false)
                                    ->seconds(false)
                                    ->helperText('Optional auto-expiration for temporary blocks.'),
                                TextInput::make('note')
                                    ->label('Reason (optional)')
                                    ->maxLength(255),
                            ]),
                        Tab::make('Continent')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Select::make('rule_action')
                                    ->label('Action')
                                    ->options(SiteFirewallRule::actionOptions())
                                    ->required(),
                                TextInput::make('rule_name')
                                    ->label('Rule name (optional)')
                                    ->maxLength(120)
                                    ->helperText('Use a friendly name if you do not want FirePhage to generate one.'),
                                Select::make('continent_code')
                                    ->label('Continent')
                                    ->options($this->continentOptions)
                                    ->searchable()
                                    ->helperText('Creates country-level rules for all countries in the selected continent.'),
                                Placeholder::make('continent_help')
                                    ->content('Continent blocks are expanded into country blocks to keep edge behavior explicit and auditable.'),
                            ]),
                        Tab::make('Advanced Rules')
                            ->icon('heroicon-o-command-line')
                            ->disabled(fn (): bool => ! $this->advancedWafAvailable)
                            ->schema([
                                TextInput::make('advanced_rule_name')
                                    ->label('Rule name')
                                    ->maxLength(120)
                                    ->helperText('Short label for this advanced rule.'),
                                Select::make('advanced_rule_variable')
                                    ->label('Request field')
                                    ->options($this->customRuleVariableOptions())
                                    ->searchable()
                                    ->helperText('Choose what part of the request FirePhage should inspect.'),
                                Select::make('advanced_rule_operator')
                                    ->label('Comparison')
                                    ->options($this->customRuleOperatorOptions())
                                    ->searchable()
                                    ->helperText('Choose how the request field should be matched.'),
                                TextInput::make('advanced_rule_value')
                                    ->label('Match value')
                                    ->placeholder('/wp-admin')
                                    ->helperText('The exact value or pattern FirePhage should look for.')
                                    ->columnSpanFull(),
                                Select::make('advanced_rule_transformation')
                                    ->label('Normalization')
                                    ->options($this->customRuleTransformationOptions())
                                    ->searchable()
                                    ->helperText('Normalize values before FirePhage compares them.'),
                                Select::make('advanced_rule_action')
                                    ->label('Response')
                                    ->options($this->customRuleActionOptions())
                                    ->searchable()
                                    ->helperText('Choose what should happen when the request matches.'),
                                TextInput::make('advanced_rule_description')
                                    ->label('Why this rule exists')
                                    ->maxLength(255)
                                    ->helperText('Short note to make the rule easier to understand later.')
                                    ->columnSpanFull(),
                                Repeater::make('advanced_rule_conditions')
                                    ->label('Additional conditions')
                                    ->helperText('Add extra conditions that must also match. These are combined with AND.')
                                    ->default([])
                                    ->collapsed()
                                    ->addActionLabel('Add AND condition')
                                    ->schema([
                                        Select::make('variable')
                                            ->label('Request field')
                                            ->options($this->customRuleVariableOptions())
                                            ->searchable()
                                            ->required(),
                                        Select::make('operator')
                                            ->label('Comparison')
                                            ->options($this->customRuleOperatorOptions())
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('value')
                                            ->label('Match value')
                                            ->required()
                                            ->columnSpanFull(),
                                        Select::make('transformation')
                                            ->label('Normalization')
                                            ->options($this->customRuleTransformationOptions())
                                            ->searchable()
                                            ->default('lower'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('IP / CIDR')
                            ->icon('heroicon-o-server')
                            ->schema([
                                Select::make('rule_action')
                                    ->label('Action')
                                    ->options(SiteFirewallRule::actionOptions())
                                    ->required(),
                                TextInput::make('rule_name')
                                    ->label('Rule name (optional)')
                                    ->maxLength(120)
                                    ->helperText('Use a friendly name if you do not want FirePhage to generate one.'),
                                Select::make('ip_type')
                                    ->label('Type')
                                    ->options([
                                        SiteFirewallRule::TYPE_IP => 'Single IP',
                                        SiteFirewallRule::TYPE_CIDR => 'CIDR range',
                                    ])
                                    ->required(),
                                TextInput::make('ip_value')
                                    ->label('IP or CIDR')
                                    ->placeholder('203.0.113.10 or 203.0.113.0/24')
                                    ->helperText('Add a single address or CIDR network.'),
                            ]),
                        Tab::make('ASN')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Select::make('rule_action')
                                    ->label('Action')
                                    ->options(SiteFirewallRule::actionOptions())
                                    ->required(),
                                TextInput::make('rule_name')
                                    ->label('Rule name (optional)')
                                    ->maxLength(120)
                                    ->helperText('Use a friendly name if you do not want FirePhage to generate one.'),
                                TextInput::make('asn_value')
                                    ->label('ASN')
                                    ->placeholder('AS13335 or 13335')
                                    ->helperText('Block, allow, challenge, or log traffic by autonomous system number.'),
                            ]),
                        Tab::make('Bulk Import')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
                                Select::make('rule_action')
                                    ->label('Action')
                                    ->options(SiteFirewallRule::actionOptions())
                                    ->required(),
                                TextInput::make('rule_name')
                                    ->label('Rule name (optional)')
                                    ->maxLength(120)
                                    ->helperText('Use a friendly name if you do not want FirePhage to generate one.'),
                                Textarea::make('bulk_values')
                                    ->label('Bulk IP/CIDR list')
                                    ->rows(8)
                                    ->placeholder("203.0.113.10\n198.51.100.0/24\n2001:db8::/48")
                                    ->helperText('One IP or CIDR per line.'),
                            ]),
                        Tab::make('Policy')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Toggle::make('staging_mode')
                                    ->label('Stage new rules before enforcement')
                                    ->helperText('When enabled, newly created rules are saved as staged and require explicit deployment.'),
                                Toggle::make('allowlist_priority')
                                    ->label('Allowlist has priority over blocklist')
                                    ->helperText('Recommended: ensures trusted IPs/countries are evaluated first.'),
                                Placeholder::make('policy_info')
                                    ->content('You can deploy staged rules at any time using the Deploy staged rules action.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->site) {
            return [];
        }

        return [
            FirewallAccessRulesTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }


    public function createRules(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('WAF rule changes')) {
            return;
        }

        if (! app(FirewallAccessControlService::class)->supportsManagedRules($this->site)) {
            Notification::make()
                ->title('Access control is not available for this edge mode yet.')
                ->warning()
                ->send();

            return;
        }

        $state = $this->form->getState();

        $stagingMode = (bool) Arr::get($state, 'staging_mode', false);
        $allowlistPriority = (bool) Arr::get($state, 'allowlist_priority', true);
        app(FirewallAccessControlService::class)->setPolicyFlags($this->site, $stagingMode, $allowlistPriority, (int) auth()->id());

        $targets = $this->collectTargets($state);
        if ($targets === []) {
            Notification::make()->title('Add at least one target before saving.')->warning()->send();

            return;
        }

        $action = (string) Arr::get($state, 'rule_action', SiteFirewallRule::ACTION_BLOCK);
        $ruleName = trim((string) Arr::get($state, 'rule_name', ''));
        $expiresAt = Arr::get($state, 'expires_at');
        $note = Arr::get($state, 'note');
        $mode = $stagingMode ? SiteFirewallRule::MODE_STAGED : SiteFirewallRule::MODE_ENFORCED;

        $createdCount = 0;
        $failedCount = 0;
        $firstFailure = null;
        $service = app(FirewallAccessControlService::class);

        $countryTargets = collect($targets)
            ->where('type', SiteFirewallRule::TYPE_COUNTRY)
            ->pluck('value')
            ->values()
            ->all();

        if ($countryTargets !== []) {
            $created = $service->createRuleSet(
                site: $this->site,
                actorId: (int) auth()->id(),
                ruleType: SiteFirewallRule::TYPE_COUNTRY,
                targets: $countryTargets,
                action: $action,
                mode: $mode,
                expiresAt: $expiresAt ? Carbon::parse((string) $expiresAt) : null,
                note: is_string($note) ? $note : null,
                displayName: $ruleName !== '' ? $ruleName : null,
            );

            $createdCount += count($created);
            $failedCount += collect($created)->where('status', SiteFirewallRule::STATUS_FAILED)->count();
            if ($firstFailure === null) {
                $firstFailure = collect($created)
                    ->first(fn (SiteFirewallRule $rule): bool => $rule->status === SiteFirewallRule::STATUS_FAILED);
            }
        }

        foreach ($targets as $target) {
            if ($target['type'] === SiteFirewallRule::TYPE_COUNTRY) {
                continue;
            }

            $created = $service->createRules(
                site: $this->site,
                actorId: (int) auth()->id(),
                ruleType: $target['type'],
                targets: [$target['value']],
                action: $action,
                mode: $mode,
                expiresAt: $expiresAt ? Carbon::parse((string) $expiresAt) : null,
                note: is_string($note) ? $note : null,
                displayName: $ruleName !== '' ? $ruleName : null,
            );

            $createdCount += count($created);
            $failedCount += collect($created)->where('status', SiteFirewallRule::STATUS_FAILED)->count();
            if ($firstFailure === null) {
                $firstFailure = collect($created)
                    ->first(fn (SiteFirewallRule $rule): bool => $rule->status === SiteFirewallRule::STATUS_FAILED);
            }
        }

        $this->dispatch('firewall-access-rules-updated');
        $this->syncOptions();

        if ($failedCount > 0) {
            $failureReason = (string) data_get($firstFailure?->meta, 'error', '');
            Notification::make()
                ->title("Saved {$createdCount} rule(s); {$failedCount} could not be enforced yet.")
                ->body($failureReason !== '' ? $failureReason : 'Verify edge provisioning for this site and try deploying staged rules again.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("Saved {$createdCount} firewall access rule(s).")
            ->success()
            ->send();
    }

    public function deployStagedRules(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Deploy staged WAF rules')) {
            return;
        }

        $count = app(FirewallAccessControlService::class)->deployStagedRules($this->site, (int) auth()->id());
        $this->dispatch('firewall-access-rules-updated');

        Notification::make()
            ->title($count > 0 ? "Deployed {$count} staged rule(s)." : 'No staged rules to deploy.')
            ->success()
            ->send();
    }

    public function expireRulesNow(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Expire WAF rules')) {
            return;
        }

        $count = app(FirewallAccessControlService::class)->expireTemporaryRules($this->site, (int) auth()->id());
        $this->dispatch('firewall-access-rules-updated');

        Notification::make()
            ->title($count > 0 ? "Expired {$count} temporary rule(s)." : 'No temporary rules to expire.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array{type: string, value: string}>
     */
    protected function collectTargets(array $state): array
    {
        $targets = [];

        foreach ((array) Arr::get($state, 'country_codes', []) as $countryCode) {
            $code = strtoupper(trim((string) $countryCode));
            if (strlen($code) === 2) {
                $targets[] = ['type' => SiteFirewallRule::TYPE_COUNTRY, 'value' => $code];
            }
        }

        $continent = strtoupper(trim((string) Arr::get($state, 'continent_code', '')));
        if ($continent !== '') {
            $continentMap = app(\App\Services\Bunny\BunnyShieldAccessListService::class)->continentCountries();
            foreach ((array) ($continentMap[$continent] ?? []) as $countryCode) {
                $targets[] = ['type' => SiteFirewallRule::TYPE_COUNTRY, 'value' => strtoupper((string) $countryCode)];
            }
        }

        $ipValue = trim((string) Arr::get($state, 'ip_value', ''));
        $ipType = (string) Arr::get($state, 'ip_type', SiteFirewallRule::TYPE_IP);
        if ($ipValue !== '') {
            $targets[] = ['type' => $ipType, 'value' => $ipValue];
        }

        $asnValue = strtoupper(trim((string) Arr::get($state, 'asn_value', '')));
        if ($asnValue !== '') {
            $asnValue = str_starts_with($asnValue, 'AS') ? substr($asnValue, 2) : $asnValue;
            if (ctype_digit($asnValue)) {
                $targets[] = ['type' => SiteFirewallRule::TYPE_ASN, 'value' => $asnValue];
            }
        }

        $bulk = preg_split('/\r\n|\r|\n/', (string) Arr::get($state, 'bulk_values', '')) ?: [];
        foreach ($bulk as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $normalizedLine = strtoupper($line);
            $type = match (true) {
                str_starts_with($normalizedLine, 'AS') || ctype_digit($normalizedLine) => SiteFirewallRule::TYPE_ASN,
                str_contains($line, '/') => SiteFirewallRule::TYPE_CIDR,
                default => SiteFirewallRule::TYPE_IP,
            };

            if ($type === SiteFirewallRule::TYPE_ASN) {
                $line = str_starts_with($normalizedLine, 'AS') ? substr($normalizedLine, 2) : $normalizedLine;
            }

            $targets[] = ['type' => $type, 'value' => $line];
        }

        return collect($targets)
            ->unique(fn (array $target): string => "{$target['type']}|".strtoupper($target['value']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    protected function policyFlags(): array
    {
        return (array) data_get($this->site?->provider_meta, 'firewall_policy', [
            'staging_mode' => false,
            'allowlist_priority' => true,
        ]);
    }

    protected function syncOptions(): void
    {
        if (! $this->site || ! app(FirewallAccessControlService::class)->supportsManagedRules($this->site)) {
            $this->countryOptions = [];
            $this->continentOptions = [];

            return;
        }

        $this->countryOptions = app(FirewallAccessControlService::class)->countryOptions($this->site);
        $this->continentOptions = app(FirewallAccessControlService::class)->continentOptions($this->site);
    }

    public function reloadWafState(): void
    {
        if (! $this->site || $this->site->provider !== \App\Models\Site::PROVIDER_BUNNY) {
            return;
        }

        try {
            $service = app(BunnyShieldWafService::class);
            $bot = $service->botDetectionSettings($this->site);

            $this->botData = [
                'enabled' => (bool) ($bot['enabled'] ?? false),
                'request_integrity_sensitivity' => (int) ($bot['request_integrity_sensitivity'] ?? 1),
                'ip_reputation_sensitivity' => (int) ($bot['ip_reputation_sensitivity'] ?? 1),
                'browser_fingerprint_sensitivity' => (int) ($bot['browser_fingerprint_sensitivity'] ?? 1),
                'browser_fingerprint_aggression' => (int) ($bot['browser_fingerprint_aggression'] ?? 1),
                'complex_fingerprinting' => (bool) ($bot['complex_fingerprinting'] ?? false),
            ];

            $this->wafMetrics = $service->overviewMetrics($this->site);
            $this->botMetrics = $service->botDetectionMetrics($this->site);
            $this->customWafRules = $service->customRules($this->site);
            $this->syncAdvancedRuleRecords();
            $this->advancedWafAvailable = $this->hasAdvancedWafSupport();
            $this->wafStateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Unable to load WAF details.')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function loadDeferredWafState(): void
    {
        if ($this->wafStateLoaded) {
            return;
        }

        $this->reloadWafState();
    }

    public function botSensitivityOptions(): array
    {
        return [
            0 => 'Off',
            1 => 'Balanced',
            2 => 'Strict',
            3 => 'Aggressive',
        ];
    }

    protected function hasAdvancedWafSupport(): bool
    {
        $meta = is_array($this->site?->provider_meta) ? $this->site->provider_meta : [];

        if (((int) ($meta['shield_plan_type'] ?? 0)) > 0) {
            return true;
        }

        if (($meta['shield_plan_status'] ?? null) === 'active') {
            return true;
        }

        if (($meta['shield_plan'] ?? null) === 'advanced') {
            return true;
        }

        return (bool) ($meta['shield_premium_plan'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialBotData(): array
    {
        $saved = is_array($this->site?->provider_meta) ? (array) data_get($this->site?->provider_meta, 'bot_detection', []) : [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'request_integrity_sensitivity' => (int) ($saved['request_integrity_sensitivity'] ?? 1),
            'ip_reputation_sensitivity' => (int) ($saved['ip_reputation_sensitivity'] ?? 1),
            'browser_fingerprint_sensitivity' => (int) ($saved['browser_fingerprint_sensitivity'] ?? 1),
            'browser_fingerprint_aggression' => (int) ($saved['browser_fingerprint_aggression'] ?? 1),
            'complex_fingerprinting' => (bool) ($saved['complex_fingerprinting'] ?? false),
        ];
    }

    protected function syncAdvancedRuleRecords(): void
    {
        if (! $this->site) {
            return;
        }

        $seenProviderIds = [];

        foreach ($this->customWafRules as $rule) {
            $providerRuleId = (string) data_get($rule, 'wafRuleId', data_get($rule, 'id', ''));

            if ($providerRuleId === '') {
                continue;
            }

            $seenProviderIds[] = $providerRuleId;

            $target = (string) data_get($rule, 'ruleConfiguration.value', data_get($rule, 'value', 'Targeted condition'));
            $existing = SiteFirewallRule::query()
                ->where('site_id', $this->site->id)
                ->where('provider_rule_id', $providerRuleId)
                ->where('rule_type', SiteFirewallRule::TYPE_ADVANCED)
                ->first();

            $displayAction = $this->normalizeAdvancedRuleAction(
                (string) ($existing?->meta['display_action'] ?? data_get($rule, 'meta.display_action', data_get($rule, 'actionTypeLabel', data_get($rule, 'actionLabel', data_get($rule, 'ruleConfiguration.actionType', data_get($rule, 'actionType', 'Configured'))))))
            );
            $displayName = (string) ($existing?->meta['display_name'] ?? data_get($rule, 'ruleName', data_get($rule, 'name', 'Advanced rule')));

            SiteFirewallRule::query()->updateOrCreate(
                [
                    'site_id' => $this->site->id,
                    'provider_rule_id' => $providerRuleId,
                    'rule_type' => SiteFirewallRule::TYPE_ADVANCED,
                ],
                [
                    'created_by_user_id' => auth()->id(),
                    'provider' => $this->site->provider,
                    'target' => $target,
                    'action' => SiteFirewallRule::ACTION_BLOCK,
                    'mode' => SiteFirewallRule::MODE_ENFORCED,
                    'status' => SiteFirewallRule::STATUS_ACTIVE,
                    'note' => (string) data_get($rule, 'ruleDescription', data_get($rule, 'description', '')),
                    'meta' => [
                        'advanced_rule' => true,
                        'display_name' => $displayName,
                        'display_action' => $displayAction,
                        'display_match' => $target,
                        'variable' => (string) data_get($rule, 'variableLabel', 'REQUEST_URI'),
                        'operator' => (string) data_get($rule, 'operatorLabel', 'contains'),
                        'transformation' => (string) data_get($rule, 'transformationLabel', 'lower'),
                        'conditions' => (array) data_get($rule, 'conditions', []),
                    ],
                ],
            );
        }

        SiteFirewallRule::query()
            ->where('site_id', $this->site->id)
            ->where('rule_type', SiteFirewallRule::TYPE_ADVANCED)
            ->when($seenProviderIds !== [], fn ($query) => $query->whereNotIn('provider_rule_id', $seenProviderIds))
            ->delete();
    }

    public function saveBotDetectionState(string $field, mixed $value): void
    {
        if (! $this->site || $this->site->provider !== \App\Models\Site::PROVIDER_BUNNY) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Bot detection settings')) {
            return;
        }

        $allowed = [
            'enabled',
            'request_integrity_sensitivity',
            'ip_reputation_sensitivity',
            'browser_fingerprint_sensitivity',
            'browser_fingerprint_aggression',
            'complex_fingerprinting',
        ];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $state = $this->botData;
        $state[$field] = in_array($field, ['enabled', 'complex_fingerprinting'], true)
            ? (bool) $value
            : (int) $value;

        try {
            app(BunnyShieldWafService::class)->updateBotDetectionSettings($this->site, $state);
            $this->refreshSite();
            $this->reloadWafState();
            Notification::make()->title('Bot detection saved.')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Unable to save bot detection settings.')->body($exception->getMessage())->danger()->send();
        }
    }

    public function createCustomWafRule(): void
    {
        if (! $this->site || $this->site->provider !== \App\Models\Site::PROVIDER_BUNNY) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Custom WAF rules')) {
            return;
        }

        try {
            $state = $this->form->getState();

            $created = app(BunnyShieldWafService::class)->createCustomRule($this->site, [
                'name' => (string) ($state['advanced_rule_name'] ?? ''),
                'description' => (string) ($state['advanced_rule_description'] ?? ''),
                'action' => (string) ($state['advanced_rule_action'] ?? 'block'),
                'variable' => (string) ($state['advanced_rule_variable'] ?? 'REQUEST_URI'),
                'operator' => (string) ($state['advanced_rule_operator'] ?? 'contains'),
                'transformation' => (string) ($state['advanced_rule_transformation'] ?? 'lower'),
                'value' => (string) ($state['advanced_rule_value'] ?? ''),
                'conditions' => array_values(array_filter((array) ($state['advanced_rule_conditions'] ?? []), fn (mixed $condition): bool => is_array($condition))),
            ]);

            $providerRuleId = (string) data_get($created, 'wafRuleId', data_get($created, 'id', ''));

            if ($providerRuleId !== '') {
                SiteFirewallRule::query()->updateOrCreate(
                    [
                        'site_id' => $this->site->id,
                        'provider_rule_id' => $providerRuleId,
                        'rule_type' => SiteFirewallRule::TYPE_ADVANCED,
                    ],
                    [
                        'created_by_user_id' => auth()->id(),
                        'provider' => $this->site->provider,
                        'target' => (string) ($state['advanced_rule_value'] ?? 'Targeted condition'),
                        'action' => SiteFirewallRule::ACTION_BLOCK,
                        'mode' => SiteFirewallRule::MODE_ENFORCED,
                        'status' => SiteFirewallRule::STATUS_ACTIVE,
                        'note' => (string) ($state['advanced_rule_description'] ?? ''),
                        'meta' => [
                            'advanced_rule' => true,
                            'display_name' => (string) ($state['advanced_rule_name'] ?? 'Advanced rule'),
                            'display_action' => $this->normalizeAdvancedRuleAction((string) ($state['advanced_rule_action'] ?? 'block')),
                            'display_match' => (string) ($state['advanced_rule_value'] ?? 'Targeted condition'),
                            'variable' => (string) ($state['advanced_rule_variable'] ?? 'REQUEST_URI'),
                            'operator' => (string) ($state['advanced_rule_operator'] ?? 'contains'),
                            'transformation' => (string) ($state['advanced_rule_transformation'] ?? 'lower'),
                            'conditions' => array_values(array_filter((array) ($state['advanced_rule_conditions'] ?? []), fn (mixed $condition): bool => is_array($condition))),
                        ],
                    ],
                );
            }

            $this->reloadWafState();
            $this->dispatch('firewall-access-rules-updated');
            Notification::make()->title('Custom WAF rule created.')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Unable to create custom WAF rule.')->body($exception->getMessage())->danger()->send();
        }
    }

    public function deleteCustomWafRule(string $ruleId): void
    {
        if (! $this->site || $this->site->provider !== \App\Models\Site::PROVIDER_BUNNY) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Custom WAF rules')) {
            return;
        }

        try {
            app(BunnyShieldWafService::class)->deleteCustomRule($this->site, $ruleId);
            SiteFirewallRule::query()
                ->where('site_id', $this->site->id)
                ->where('rule_type', SiteFirewallRule::TYPE_ADVANCED)
                ->where('provider_rule_id', $ruleId)
                ->delete();
            $this->reloadWafState();
            $this->dispatch('firewall-access-rules-updated');
            Notification::make()->title('Custom WAF rule removed.')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Unable to remove custom WAF rule.')->body($exception->getMessage())->danger()->send();
        }
    }

    public function refreshWafTelemetry(): void
    {
        $this->reloadWafState();
        Notification::make()->title('WAF telemetry refreshed.')->success()->send();
    }

    public function applyProtectionPreset(string $presetId): void
    {
        if (! $this->site || $this->site->provider !== \App\Models\Site::PROVIDER_BUNNY) {
            Notification::make()->title('Protection presets are available only for Standard Edge sites.')->warning()->send();

            return;
        }

        if (! $this->ensureNotDemoReadOnly('Protection presets')) {
            return;
        }

        try {
            $result = app(FirewallPresetService::class)->applyPreset($this->site, $presetId);

            $this->refreshSite();
            $this->reloadWafState();
            $this->dispatch('firewall-access-rules-updated');

            AuditLog::create([
                'actor_id' => auth()->id(),
                'organization_id' => $this->site->organization_id,
                'site_id' => $this->site->id,
                'action' => 'site.firewall_preset.'.$presetId,
                'status' => 'success',
                'message' => (string) ($result['message'] ?? 'Protection preset applied.'),
                'meta' => $result,
            ]);

            Notification::make()
                ->title((string) ($result['message'] ?? 'Protection preset applied.'))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Unable to apply the protection preset.')->body($exception->getMessage())->danger()->send();
        }
    }

    /**
     * @return array<int, array{label:string,value:string,support:string,color:string}>
     */
    public function wafSnapshot(): array
    {
        $totalBlocked = (int) ($this->wafMetrics['blockedRequests'] ?? 0);
        $totalLogged = (int) ($this->wafMetrics['loggedRequests'] ?? 0);
        $botChallenged = (int) ($this->botMetrics['totalChallengedRequests'] ?? 0);

        return [
            [
                'label' => 'Blocked Requests',
                'value' => number_format($totalBlocked),
                'support' => 'Requests denied by edge security controls across the recent reporting window.',
                'color' => $totalBlocked > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Logged-only Hits',
                'value' => number_format($totalLogged),
                'support' => 'Requests observed by log-only access or WAF actions without being denied.',
                'color' => $totalLogged > 0 ? 'primary' : 'gray',
            ],
            [
                'label' => 'Bot Challenges',
                'value' => number_format($botChallenged),
                'support' => 'Challenges issued by bot detection during the recent reporting window.',
                'color' => $botChallenged > 0 ? 'danger' : 'gray',
            ],
        ];
    }

    public function customRuleActionOptions(): array
    {
        return [
            'block' => 'Block',
            'challenge' => 'Challenge',
            'log' => 'Log only',
        ];
    }

    public function customRuleVariableOptions(): array
    {
        return [
            'REQUEST_URI' => 'Request path',
            'REMOTE_ADDR' => 'Visitor IP',
            'GEO' => 'Country code',
            'REQUEST_METHOD' => 'HTTP method',
            'REQUEST_HEADERS' => 'Request headers',
            'REQUEST_COOKIES' => 'Cookies',
            'QUERY_STRING' => 'Query string',
            'REQUEST_BODY' => 'Request body',
        ];
    }

    public function customRuleOperatorOptions(): array
    {
        return [
            'contains' => 'Contains',
            'equals' => 'Equals',
            'startswith' => 'Starts with',
            'endswith' => 'Ends with',
            'regex' => 'Regex',
        ];
    }

    public function customRuleTransformationOptions(): array
    {
        return [
            'none' => 'No transform',
            'lower' => 'Lowercase',
            'trim' => 'Trim whitespace',
        ];
    }

    protected function normalizeAdvancedRuleAction(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'block' => 'Block',
            '2', 'challenge' => 'Challenge',
            '3', 'log', 'log only' => 'Log',
            default => str($value)->headline()->toString(),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function protectionPresets(): array
    {
        return app(FirewallPresetService::class)->presets();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastAppliedProtectionPreset(): ?array
    {
        $state = data_get($this->site?->provider_meta, 'firewall_presets.last_applied', []);

        return is_array($state) && ($state['id'] ?? null) !== null ? $state : null;
    }
}
