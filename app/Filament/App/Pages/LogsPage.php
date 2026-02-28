<?php

namespace App\Filament\App\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Bunny\BunnyLogsService;

class LogsPage extends BaseProtectionPage
{
    protected static bool $shouldRegisterNavigation = false;

    public string $timeRange = '24h';

    public string $statusGroup = '';

    public string $country = '';

    public bool $suspiciousOnly = false;

    public int $logsPage = 1;

    public int $logsPerPage = 25;

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
                'rule' => str_ireplace(['bunny', 'aws', 'cloudfront'], 'edge network', (string) $log->message),
                'status_code' => $log->status === 'failed' ? 500 : 200,
            ])
            ->all();
    }

    public function filteredLogEntries(): array
    {
        $rows = collect($this->logEntries());
        $from = match ($this->timeRange) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };

        $rows = $rows->filter(fn (array $row): bool => \Illuminate\Support\Carbon::parse($row['timestamp'])->greaterThanOrEqualTo($from));

        if ($this->statusGroup !== '') {
            $rows = $rows->filter(function (array $row): bool {
                $status = (int) ($row['status_code'] ?? 200);

                return match ($this->statusGroup) {
                    '2xx' => $status >= 200 && $status < 300,
                    '3xx' => $status >= 300 && $status < 400,
                    '4xx' => $status >= 400 && $status < 500,
                    '5xx' => $status >= 500,
                    default => true,
                };
            });
        }

        if ($this->country !== '') {
            $rows = $rows->where('country', strtoupper($this->country));
        }

        if ($this->suspiciousOnly) {
            $rows = $rows->filter(fn (array $row): bool => in_array(strtoupper((string) ($row['action'] ?? 'ALLOW')), ['BLOCK', 'DENY', 'CHALLENGE', 'CAPTCHA'], true) || (int) ($row['status_code'] ?? 200) >= 400);
        }

        return $rows
            ->slice(($this->logsPage - 1) * $this->logsPerPage, $this->logsPerPage)
            ->values()
            ->all();
    }

    public function countries(): array
    {
        return collect($this->logEntries())
            ->pluck('country')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function nextLogsPage(): void
    {
        $this->logsPage++;
    }

    public function prevLogsPage(): void
    {
        $this->logsPage = max(1, $this->logsPage - 1);
    }

    public function refreshLogs(): void
    {
        $this->refreshSite();
        $this->notify('Log stream refreshed');
    }
}
