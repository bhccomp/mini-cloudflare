<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Services\WordPress\PluginSiteService;

class WordPressPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'General';

    protected static ?string $slug = 'wordpress';

    protected static ?int $navigationSort = -45;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'WordPress';

    protected static ?string $title = 'WordPress';

    protected string $view = 'filament.app.pages.wordpress';

    public ?string $pluginConnectionToken = null;

    public ?string $pluginConnectionTokenExpiresAt = null;

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
        $payload = $this->site?->pluginConnection?->last_report_payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressHealthSummary(): array
    {
        $report = $this->latestPluginReport();
        $summary = data_get($report, 'health.summary', []);
        $updates = data_get($report, 'health.updates', []);
        $checksum = data_get($report, 'health.core_checksum', []);

        return [
            'good' => (int) ($summary['good'] ?? 0),
            'warning' => (int) ($summary['warning'] ?? 0),
            'critical' => (int) ($summary['critical'] ?? 0),
            'core_updates' => (int) ($updates['core_updates'] ?? 0),
            'plugin_updates' => (int) ($updates['plugin_updates'] ?? 0),
            'theme_updates' => (int) ($updates['theme_updates'] ?? 0),
            'inactive_plugins' => (int) ($updates['inactive_plugins'] ?? 0),
            'checksum_status' => (string) ($checksum['status'] ?? 'unknown'),
            'checksum_summary' => (string) ($checksum['summary'] ?? 'No core checksum report yet.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressScanSummary(): array
    {
        $scan = data_get($this->latestPluginReport(), 'malware_scan', []);

        return [
            'status' => (string) ($scan['status'] ?? 'idle'),
            'scanned_files' => (int) ($scan['scanned_files'] ?? 0),
            'discovered_files' => (int) ($scan['discovered_files'] ?? 0),
            'suspicious_files' => (int) ($scan['suspicious_files'] ?? 0),
            'skipped_files' => (int) ($scan['skipped_files'] ?? 0),
            'findings' => is_array($scan['findings'] ?? null) ? $scan['findings'] : [],
            'updated_at' => (string) ($scan['updated_at'] ?? ''),
            'finished_at' => (string) ($scan['finished_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressSiteMeta(): array
    {
        $site = data_get($this->latestPluginReport(), 'site', []);

        return [
            'home_url' => (string) ($site['home_url'] ?? ''),
            'site_url' => (string) ($site['site_url'] ?? ''),
            'wp_version' => (string) ($site['wp_version'] ?? ''),
            'php_version' => (string) ($site['php_version'] ?? ''),
            'plugin_version' => (string) ($site['plugin_version'] ?? ''),
            'generated_at' => (string) data_get($this->latestPluginReport(), 'generated_at', ''),
        ];
    }

    public function pluginRouteUrl(string $path): string
    {
        return untrailingslashit(url('/')) . $path . '?site_id=' . $this->site?->id;
    }
}
