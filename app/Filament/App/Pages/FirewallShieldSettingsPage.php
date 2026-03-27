<?php

namespace App\Filament\App\Pages;

use App\Models\EdgeRequestLog;
use App\Models\Site;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\Firewall\FirewallInsightsPresenter;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;
use Throwable;

class FirewallShieldSettingsPage extends BaseProtectionPage implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'firewall-shield-settings';

    protected static ?int $navigationSort = -38;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'DDoS';

    protected static ?string $title = 'DDoS';

    protected string $view = 'filament.app.pages.protection.firewall-shield-settings';

    public ?array $data = [];

    public function mount(Request $request, SiteContext $siteContext, UiModeManager $uiMode): void
    {
        parent::mount($request, $siteContext, $uiMode);

        $this->reloadState();
    }

    public function form(Schema $schema): Schema
    {
        $service = app(BunnyShieldSecurityService::class);

        return $schema
            ->schema([
                Select::make('waf_sensitivity')
                    ->label('Edge filtering sensitivity')
                    ->options($service->sensitivityOptions())
                    ->helperText('Controls how aggressively suspicious requests are filtered before they reach your origin.')
                    ->required(),
                Select::make('ddos_sensitivity')
                    ->label('Traffic surge sensitivity')
                    ->options($service->sensitivityOptions())
                    ->helperText('Controls how strongly the edge reacts when traffic pressure starts to look hostile.')
                    ->required(),
                Select::make('challenge_window_minutes')
                    ->label('Trusted visitor window')
                    ->helperText('The duration a visitor can access your website after passing a challenge.')
                    ->options($service->challengeWindowOptions())
                    ->required(),
                Toggle::make('waf_enabled')
                    ->label('Edge filtering enabled'),
                Toggle::make('learning_mode')
                    ->label('Adaptive learning'),
                Toggle::make('block_vpn')
                    ->label('Block privacy relay traffic'),
                Toggle::make('block_tor')
                    ->label('Block anonymized exit traffic'),
                Toggle::make('block_datacentre')
                    ->label('Block datacenter egress'),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function saveSettings(): void
    {
        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            Notification::make()->title('Shield settings are available only for standard edge sites.')->warning()->send();

            return;
        }

        if (! $this->ensureNotDemoReadOnly('Shield setting changes')) {
            return;
        }

        $state = $this->form->getState();

        try {
            app(BunnyShieldSecurityService::class)->updateSettings($this->site, $state);
            $this->reloadState();

            Notification::make()->title('Shield settings updated.')->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Failed to update Shield settings.')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function reloadState(): void
    {
        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            $this->form->fill([
                'waf_sensitivity' => 'medium',
                'ddos_sensitivity' => 'medium',
                'challenge_window_minutes' => 30,
                'waf_enabled' => true,
                'learning_mode' => false,
                'block_vpn' => false,
                'block_tor' => false,
                'block_datacentre' => false,
            ]);

            return;
        }

        try {
            $settings = app(BunnyShieldSecurityService::class)->currentSettings($this->site);

            $this->form->fill([
                'waf_sensitivity' => (string) ($settings['waf_sensitivity'] ?? 'medium'),
                'ddos_sensitivity' => (string) ($settings['ddos_sensitivity'] ?? 'medium'),
                'challenge_window_minutes' => (int) ($settings['challenge_window_minutes'] ?? 30),
                'waf_enabled' => (bool) ($settings['waf_enabled'] ?? true),
                'learning_mode' => (bool) ($settings['learning_mode'] ?? false),
                'block_vpn' => (bool) ($settings['block_vpn'] ?? false),
                'block_tor' => (bool) ($settings['block_tor'] ?? false),
                'block_datacentre' => (bool) ($settings['block_datacentre'] ?? false),
            ]);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Unable to load Shield settings.')->body($e->getMessage())->warning()->send();
        }
    }

    /**
     * @return array<int, array{label:string,value:string,support:string,color:string}>
     */
    public function protectionSnapshot(): array
    {
        if (! $this->site) {
            return [];
        }

        $this->site->loadMissing('analyticsMetric');
        $presenter = app(FirewallInsightsPresenter::class);
        $insights = $presenter->insights($this->site);
        $summary = (array) data_get($insights, 'summary', []);
        $total = (int) ($summary['total'] ?? ($this->site->analyticsMetric?->total_requests_24h ?? 0));
        $blocked = (int) ($summary['blocked'] ?? ($this->site->analyticsMetric?->blocked_requests_24h ?? 0));
        $ratio = $total > 0 ? round(($blocked / max(1, $total)) * 100, 2) : 0.0;
        $threat = $presenter->threatLevel($insights);

        return [
            [
                'label' => 'Protection posture',
                'value' => $threat,
                'support' => match ($threat) {
                    'Under Attack' => 'Traffic pressure is elevated and protections are working hard.',
                    'Active Mitigation' => 'The edge is actively filtering noisy traffic.',
                    default => 'Traffic looks stable right now.',
                },
                'color' => match ($threat) {
                    'Under Attack' => 'danger',
                    'Active Mitigation' => 'warning',
                    default => 'success',
                },
            ],
            [
                'label' => 'Blocked or challenged',
                'value' => number_format($blocked),
                'support' => $total > 0 ? number_format($ratio, 2).'% of recent traffic was denied or challenged.' : 'No significant hostile traffic in the last 24 hours.',
                'color' => $blocked > 0 ? 'warning' : 'gray',
            ],
            [
                'label' => 'Traffic seen',
                'value' => number_format($total),
                'support' => 'Requests observed across the last 24 hours.',
                'color' => 'primary',
            ],
        ];
    }

    /**
     * @return array<int, array{path:string,requests:int,blocked:int}>
     */
    public function hottestPaths(): array
    {
        if (! $this->site) {
            return [];
        }

        return EdgeRequestLog::query()
            ->selectRaw("path, COUNT(*) as requests, SUM(CASE WHEN action IN ('BLOCK','DENY','CHALLENGE','CAPTCHA') OR status_code = 403 THEN 1 ELSE 0 END) as blocked")
            ->where('site_id', $this->site->id)
            ->where('event_at', '>=', now()->subDay())
            ->groupBy('path')
            ->orderByDesc('requests')
            ->limit(6)
            ->get()
            ->map(fn (EdgeRequestLog $row): array => [
                'path' => (string) ($row->path ?: '/'),
                'requests' => (int) ($row->requests ?? 0),
                'blocked' => (int) ($row->blocked ?? 0),
            ])
            ->all();
    }

}
