<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Forms\Components\Select;
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
                    ->label('WAF Sensitivity')
                    ->options($service->sensitivityOptions())
                    ->required(),
                Select::make('ddos_sensitivity')
                    ->label('DDoS Sensitivity')
                    ->options($service->sensitivityOptions())
                    ->required(),
                Select::make('bot_sensitivity')
                    ->label('Bot Detection Sensitivity')
                    ->options($service->sensitivityOptions())
                    ->required(),
                Select::make('challenge_window_minutes')
                    ->label('Valid challenge window')
                    ->helperText('The duration a visitor can access your website after passing a challenge.')
                    ->options($service->challengeWindowOptions())
                    ->required(),
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
                'bot_sensitivity' => 'medium',
                'challenge_window_minutes' => 30,
            ]);

            return;
        }

        try {
            $settings = app(BunnyShieldSecurityService::class)->currentSettings($this->site);

            $this->form->fill([
                'waf_sensitivity' => (string) ($settings['waf_sensitivity'] ?? 'medium'),
                'ddos_sensitivity' => (string) ($settings['ddos_sensitivity'] ?? 'medium'),
                'bot_sensitivity' => (string) ($settings['bot_sensitivity'] ?? 'medium'),
                'challenge_window_minutes' => (int) ($settings['challenge_window_minutes'] ?? 30),
            ]);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Unable to load Shield settings.')->body($e->getMessage())->warning()->send();
        }
    }

}
