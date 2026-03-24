<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Filament\App\Widgets\WordPress\WordPressConnectionStats;
use App\Filament\App\Widgets\WordPress\WordPressFirewallEventsTable;
use App\Filament\App\Widgets\WordPress\WordPressHealthStats;
use App\Filament\App\Widgets\WordPress\WordPressMalwareFindingsTable;
use App\Filament\App\Widgets\WordPress\WordPressMalwareScanStats;
use App\Services\WordPress\PluginSiteService;
use Filament\Actions\Action;

class WordPressPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'General';

    protected static ?string $slug = 'wordpress';

    protected static ?int $navigationSort = -45;

    protected static string|\BackedEnum|null $navigationIcon = 'icon-wordpress';

    protected static ?string $navigationLabel = 'WordPress';

    protected static ?string $title = 'WordPress';

    protected string $view = 'filament.app.pages.wordpress';

    public ?string $pluginConnectionToken = null;

    public ?string $pluginConnectionTokenExpiresAt = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generatePluginToken')
                ->label($this->isPluginConnected() ? 'Generate new token' : 'Generate token')
                ->icon('heroicon-m-key')
                ->color('gray')
                ->action('generatePluginToken')
                ->disabled(fn (): bool => ! $this->site),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->site || ! $this->isPluginConnected()) {
            return [];
        }

        return [
            WordPressConnectionStats::class,
            WordPressHealthStats::class,
            WordPressMalwareScanStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->site || ! $this->isPluginConnected()) {
            return [];
        }

        return [
            WordPressMalwareFindingsTable::class,
            WordPressFirewallEventsTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public function generatePluginToken(): void
    {
        if (! $this->site) {
            return;
        }

        $issued = app(PluginSiteService::class)->issueConnectionToken($this->site, auth()->id());

        $this->pluginConnectionToken = (string) $issued['token'];
        $this->pluginConnectionTokenExpiresAt = (string) $issued['expires_at'];
        $this->notify('Plugin connection token generated');
    }

    public function copyToClipboard(string $encodedValue, string $label = 'Value', ?string $key = null): void
    {
        $value = base64_decode($encodedValue, true);

        $this->dispatch(
            'firephage-copy-to-clipboard',
            text: is_string($value) ? $value : '',
            label: $label,
            key: $key,
        );
    }

    public function shouldPollForPluginConnection(): bool
    {
        return $this->site !== null
            && ! $this->isPluginConnected()
            && filled($this->pluginConnectionToken)
            && filled($this->pluginConnectionTokenExpiresAt)
            && now()->lt(\Illuminate\Support\Carbon::parse($this->pluginConnectionTokenExpiresAt));
    }

    public function pollForPluginConnection(): void
    {
        if (! $this->site || ! $this->shouldPollForPluginConnection()) {
            return;
        }

        $this->site->refresh();

        if (! $this->isPluginConnected()) {
            return;
        }

        $this->pluginConnectionToken = null;
        $this->pluginConnectionTokenExpiresAt = null;
        $this->notify('WordPress plugin connected');
    }

    public function isPluginConnected(): bool
    {
        return $this->site?->pluginConnection !== null;
    }

    public function pluginConnectionStatus(): string
    {
        if (! $this->isPluginConnected()) {
            return 'Not connected';
        }

        return ucfirst((string) ($this->site->pluginConnection->status ?: 'connected'));
    }

    public function pluginConnectionStatusColor(): string
    {
        return $this->isPluginConnected() ? 'success' : 'gray';
    }

    public function pluginConnectionLastSeen(): string
    {
        return $this->site?->pluginConnection?->last_seen_at?->diffForHumans() ?? 'Never';
    }

    public function pluginConnectionLastReported(): string
    {
        return $this->site?->pluginConnection?->last_reported_at?->diffForHumans() ?? 'Never';
    }

    /**
     * @return array<string, mixed>
     */
    public function latestPluginReport(): array
    {
        return $this->site ? app(PluginSiteService::class)->latestReportForSite($this->site) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressHealthSummary(): array
    {
        return $this->site ? app(PluginSiteService::class)->wordpressHealthSummaryForSite($this->site) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressScanSummary(): array
    {
        return $this->site ? app(PluginSiteService::class)->wordpressScanSummaryForSite($this->site) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressSiteMeta(): array
    {
        return $this->site ? app(PluginSiteService::class)->wordpressSiteMetaForSite($this->site) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function pluginBillingSummary(): array
    {
        return $this->site ? app(PluginSiteService::class)->billingAccessSummaryForSite($this->site) : [];
    }

    public function pluginRouteUrl(string $path): string
    {
        return untrailingslashit(url('/')) . $path . '?site_id=' . $this->site?->id;
    }
}
