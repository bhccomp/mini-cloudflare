<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\Firewall\FirewallAccessRulesTable;
use App\Models\SiteFirewallRule;
use App\Services\Firewall\FirewallAccessControlService;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
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

class FirewallAccessControlPage extends BaseProtectionPage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'firewall-access-control';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Access Control';

    protected static ?string $navigationParentItem = 'Firewall';

    protected static ?string $title = 'Firewall Access Control';

    protected string $view = 'filament.app.pages.protection.firewall-access-control';

    public ?array $data = [];

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

        $policy = $this->policyFlags();
        $this->form->fill([
            'rule_action' => SiteFirewallRule::ACTION_BLOCK,
            'country_codes' => [],
            'continent_code' => null,
            'ip_value' => null,
            'ip_type' => SiteFirewallRule::TYPE_IP,
            'bulk_values' => null,
            'expires_at' => null,
            'note' => null,
            'staging_mode' => (bool) ($policy['staging_mode'] ?? false),
            'allowlist_priority' => (bool) ($policy['allowlist_priority'] ?? true),
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
                                Select::make('continent_code')
                                    ->label('Continent')
                                    ->options($this->continentOptions)
                                    ->searchable()
                                    ->helperText('Creates country-level rules for all countries in the selected continent.'),
                                Placeholder::make('continent_help')
                                    ->content('Continent blocks are expanded into country blocks to keep edge behavior explicit and auditable.'),
                            ]),
                        Tab::make('IP / CIDR')
                            ->icon('heroicon-o-server')
                            ->schema([
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
                        Tab::make('Bulk Import')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
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
        $expiresAt = Arr::get($state, 'expires_at');
        $note = Arr::get($state, 'note');
        $mode = $stagingMode ? SiteFirewallRule::MODE_STAGED : SiteFirewallRule::MODE_ENFORCED;

        $createdCount = 0;
        $failedCount = 0;

        foreach ($targets as $target) {
            $created = app(FirewallAccessControlService::class)->createRules(
                site: $this->site,
                actorId: (int) auth()->id(),
                ruleType: $target['type'],
                targets: [$target['value']],
                action: $action,
                mode: $mode,
                expiresAt: $expiresAt ? Carbon::parse((string) $expiresAt) : null,
                note: is_string($note) ? $note : null,
            );

            $createdCount += count($created);
            $failedCount += collect($created)->where('status', SiteFirewallRule::STATUS_FAILED)->count();
        }

        $this->dispatch('firewall-access-rules-updated');
        $this->syncOptions();

        if ($failedCount > 0) {
            Notification::make()
                ->title("Saved {$createdCount} rule(s); {$failedCount} could not be enforced yet.")
                ->body('Verify edge provisioning for this site and try deploying staged rules again.')
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

        $bulk = preg_split('/\r\n|\r|\n/', (string) Arr::get($state, 'bulk_values', '')) ?: [];
        foreach ($bulk as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $type = str_contains($line, '/') ? SiteFirewallRule::TYPE_CIDR : SiteFirewallRule::TYPE_IP;
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
}
