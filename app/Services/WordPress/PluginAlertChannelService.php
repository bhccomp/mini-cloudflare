<?php

namespace App\Services\WordPress;

use App\Models\AlertChannel;
use App\Models\PluginSiteConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PluginAlertChannelService
{
    /**
     * @param array<string, mixed> $settings
     */
    public function syncChannels(PluginSiteConnection $connection, array $settings): array
    {
        $site = $connection->site()->with('organization')->firstOrFail();
        $organizationId = (int) $site->organization_id;
        $siteId = (int) $site->id;

        $alertsEnabled = ! empty($settings['notifications_enabled']);
        $malwareEnabled = ! empty($settings['notifications_alert_malware']);
        $coreEnabled = ! empty($settings['notifications_alert_core_edits']);
        $email = trim((string) ($settings['notification_email'] ?? ''));
        $webhookUrl = trim((string) ($settings['notifications_webhook_url'] ?? ''));
        $slackValue = trim((string) ($settings['notifications_slack_channel'] ?? ''));

        $this->upsertChannel(
            organizationId: $organizationId,
            siteId: $siteId,
            type: 'email',
            name: 'Email Alerts',
            enabled: $alertsEnabled && $email !== '',
            config: [
                'recipients' => $email !== '' ? [$email] : [],
                'from_name' => 'FirePhage Alerts',
                'source' => 'wordpress-plugin',
                'alert_types' => [
                    'malware' => $malwareEnabled,
                    'core_edits' => $coreEnabled,
                ],
            ],
        );

        $this->upsertChannel(
            organizationId: $organizationId,
            siteId: $siteId,
            type: 'webhook',
            name: 'Webhook Alerts',
            enabled: $alertsEnabled && $webhookUrl !== '',
            config: [
                'url' => $webhookUrl,
                'secret' => (string) ($settings['notifications_webhook_secret'] ?? ''),
                'source' => 'wordpress-plugin',
                'alert_types' => [
                    'malware' => $malwareEnabled,
                    'core_edits' => $coreEnabled,
                ],
            ],
        );

        $this->upsertChannel(
            organizationId: $organizationId,
            siteId: $siteId,
            type: 'slack',
            name: 'Slack Alerts',
            enabled: $alertsEnabled && $slackValue !== '',
            config: [
                'webhook_url' => Str::startsWith($slackValue, 'https://') ? $slackValue : '',
                'channel' => Str::startsWith($slackValue, '#') || Str::startsWith($slackValue, '@') ? $slackValue : '',
                'mention' => '',
                'source' => 'wordpress-plugin',
                'alert_types' => [
                    'malware' => $malwareEnabled,
                    'core_edits' => $coreEnabled,
                ],
            ],
        );

        return [
            'status' => 'ok',
        ];
    }

    /**
     * @param array<string, mixed> $previousReport
     * @param array<string, mixed> $report
     */
    public function dispatchAlertsForReport(PluginSiteConnection $connection, array $previousReport, array $report, bool $proEnabled): void
    {
        if (! $proEnabled) {
            return;
        }

        $site = $connection->site()->with('organization')->first();

        if (! $site) {
            return;
        }

        $currentScanId = (string) data_get($report, 'malware_scan.scan_id', '');
        $previousScanId = (string) data_get($previousReport, 'malware_scan.scan_id', '');

        if ($currentScanId === '' || $currentScanId === $previousScanId) {
            return;
        }

        if ((int) data_get($report, 'malware_scan.suspicious_files', 0) > 0) {
            $this->dispatchToChannels($site->organization_id, $site->id, 'malware', $this->malwarePayload($site->apex_domain ?: $site->name, $site->id, $report));
        }

        if ($this->hasCoreIntegrityFinding($report)) {
            $this->dispatchToChannels($site->organization_id, $site->id, 'core_edits', $this->corePayload($site->apex_domain ?: $site->name, $site->id, $report));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchToChannels(int $organizationId, int $siteId, string $alertType, array $payload): void
    {
        $channels = AlertChannel::query()
            ->where('organization_id', $organizationId)
            ->where('site_id', $siteId)
            ->whereIn('type', ['slack', 'webhook'])
            ->where('is_active', true)
            ->get();

        foreach ($channels as $channel) {
            $config = is_array($channel->config) ? $channel->config : [];
            if (! data_get($config, "alert_types.{$alertType}", true)) {
                continue;
            }

            if ($channel->type === 'webhook') {
                $this->sendWebhook($config, $payload);
                continue;
            }

            if ($channel->type === 'slack') {
                $this->sendSlack($config, $payload);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     */
    private function sendWebhook(array $config, array $payload): void
    {
        $url = trim((string) ($config['url'] ?? ''));

        if ($url === '') {
            return;
        }

        $body = [
            'source' => 'firephage-wordpress-plugin',
            'event' => $payload['event'] ?? 'wordpress_alert',
            'payload' => $payload,
        ];

        $request = Http::timeout(10)->acceptJson();
        $secret = (string) ($config['secret'] ?? '');

        if ($secret !== '') {
            $request = $request->withHeaders([
                'X-FirePhage-Signature' => hash_hmac('sha256', json_encode($body, JSON_UNESCAPED_SLASHES) ?: '', $secret),
            ]);
        }

        try {
            $request->post($url, $body)->throw();
        } catch (\Throwable $exception) {
            Log::warning('Failed to deliver FirePhage webhook alert.', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     */
    private function sendSlack(array $config, array $payload): void
    {
        $webhookUrl = trim((string) ($config['webhook_url'] ?? ''));

        if ($webhookUrl === '') {
            return;
        }

        $title = (string) ($payload['title'] ?? 'WordPress alert');
        $summary = (string) ($payload['summary'] ?? '');
        $site = (string) ($payload['site'] ?? 'Site');
        $mention = trim((string) ($config['mention'] ?? ''));
        $channel = trim((string) ($config['channel'] ?? ''));

        $body = [
            'text' => trim(($mention !== '' ? $mention . ' ' : '') . $title . ' for ' . $site),
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*' . $title . '*',
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Site:* {$site}\n*Summary:* {$summary}",
                    ],
                ],
            ],
        ];

        if ($channel !== '') {
            $body['channel'] = $channel;
        }

        try {
            Http::timeout(10)->acceptJson()->post($webhookUrl, $body)->throw();
        } catch (\Throwable $exception) {
            Log::warning('Failed to deliver FirePhage Slack alert.', [
                'webhook_url' => $webhookUrl,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function malwarePayload(string $siteDomain, int $siteId, array $report): array
    {
        $findings = collect((array) data_get($report, 'malware_scan.findings', []))
            ->filter(fn ($finding) => is_array($finding) && data_get($finding, 'type') === 'malware')
            ->take(5)
            ->map(fn ($finding) => (string) data_get($finding, 'file', ''))
            ->filter()
            ->values()
            ->all();

        return [
            'event' => 'wordpress_malware_detected',
            'title' => 'Malware detected',
            'site' => $siteDomain,
            'site_id' => $siteId,
            'scan_id' => (string) data_get($report, 'malware_scan.scan_id', ''),
            'summary' => sprintf(
                '%d malicious file(s) reported.',
                (int) data_get($report, 'malware_scan.suspicious_files', 0)
            ),
            'malicious_files' => (int) data_get($report, 'malware_scan.suspicious_files', 0),
            'integrity_issues' => (int) data_get($report, 'malware_scan.integrity_issues', 0),
            'findings' => $findings,
            'dashboard_url' => rtrim((string) config('app.url'), '/') . '/app/wordpress?site_id=' . $siteId,
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function corePayload(string $siteDomain, int $siteId, array $report): array
    {
        $findings = collect((array) data_get($report, 'malware_scan.findings', []))
            ->filter(fn ($finding) => is_array($finding) && data_get($finding, 'type') === 'integrity' && data_get($finding, 'source') === 'core_checksum')
            ->take(5)
            ->map(fn ($finding) => (string) data_get($finding, 'file', ''))
            ->filter()
            ->values()
            ->all();

        return [
            'event' => 'wordpress_core_edits_detected',
            'title' => 'WordPress core edits detected',
            'site' => $siteDomain,
            'site_id' => $siteId,
            'scan_id' => (string) data_get($report, 'malware_scan.scan_id', ''),
            'summary' => sprintf(
                '%d modified core file(s) need review.',
                count($findings)
            ),
            'findings' => $findings,
            'dashboard_url' => rtrim((string) config('app.url'), '/') . '/app/wordpress?site_id=' . $siteId,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function hasCoreIntegrityFinding(array $report): bool
    {
        foreach ((array) data_get($report, 'malware_scan.findings', []) as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            if (data_get($finding, 'type') === 'integrity' && data_get($finding, 'source') === 'core_checksum') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function upsertChannel(int $organizationId, int $siteId, string $type, string $name, bool $enabled, array $config): void
    {
        $record = AlertChannel::query()
            ->where('organization_id', $organizationId)
            ->where('site_id', $siteId)
            ->where('type', $type)
            ->first();

        if (! $record) {
            AlertChannel::query()->create([
                'organization_id' => $organizationId,
                'site_id' => $siteId,
                'name' => $name,
                'type' => $type,
                'is_active' => $enabled,
                'config' => $config,
            ]);

            return;
        }

        $record->update([
            'name' => $name,
            'is_active' => $enabled,
            'config' => $config,
        ]);
    }
}
