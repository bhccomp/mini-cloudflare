<?php

namespace App\Filament\App\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Bunny\BunnyLogsService;

class LogsPage extends BaseProtectionPage
{
    protected static ?string $slug = 'logs';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?string $title = 'Logs';

    protected string $view = 'filament.app.pages.protection.logs';

    public function logEntries(): array
    {
        if (! $this->site) {
            return [];
        }

        if ($this->site->provider === Site::PROVIDER_BUNNY) {
            return app(BunnyLogsService::class)->recentLogs($this->site, 120);
        }

        return AuditLog::query()
            ->where('site_id', $this->site->id)
            ->latest('id')
            ->limit(120)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'timestamp' => $log->created_at,
                'action' => strtoupper((string) $log->status),
                'ip' => '-',
                'country' => '??',
                'method' => 'SYS',
                'uri' => $log->action,
                'rule' => (string) $log->message,
                'status_code' => $log->status === 'failed' ? 500 : 200,
            ])
            ->all();
    }

    public function refreshLogs(): void
    {
        $this->refreshSite();
        $this->notify('Log stream refreshed');
    }
}
