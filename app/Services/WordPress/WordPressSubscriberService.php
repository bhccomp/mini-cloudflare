<?php

namespace App\Services\WordPress;

use App\Models\WordPressSubscriber;
use App\Notifications\WordPressFreeTokenNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class WordPressSubscriberService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function register(array $payload): array
    {
        $email = trim((string) ($payload['email'] ?? ''));
        $homeUrl = trim((string) ($payload['home_url'] ?? ''));
        $siteUrl = trim((string) ($payload['site_url'] ?? ''));
        $adminEmail = trim((string) ($payload['admin_email'] ?? ''));
        $pluginVersion = trim((string) ($payload['plugin_version'] ?? ''));
        $marketingOptIn = (bool) ($payload['marketing_opt_in'] ?? false);
        $siteHost = $this->resolveSiteHost($siteUrl !== '' ? $siteUrl : $homeUrl);

        if ($email === '' || $siteHost === '') {
            throw new RuntimeException('A valid email address and WordPress site URL are required.');
        }

        $plainToken = 'fpf_' . Str::random(48);

        $subscriber = WordPressSubscriber::query()->updateOrCreate(
            ['site_host' => $siteHost],
            [
                'email' => $email,
                'home_url' => $homeUrl !== '' ? $homeUrl : null,
                'site_url' => $siteUrl !== '' ? $siteUrl : null,
                'admin_email' => $adminEmail !== '' ? $adminEmail : null,
                'plugin_version' => $pluginVersion !== '' ? $pluginVersion : null,
                'marketing_opt_in' => $marketingOptIn,
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'active',
                'last_token_issued_at' => now(),
            ],
        );

        Notification::route('mail', $email)
            ->notify(new WordPressFreeTokenNotification($siteHost, $plainToken));

        return [
            'token' => $plainToken,
            'email' => $subscriber->email,
            'site_host' => $subscriber->site_host,
            'status' => 'registered',
        ];
    }

    public function authenticate(Request $request): WordPressSubscriber
    {
        $token = (string) $request->bearerToken();

        if ($token === '') {
            throw new RuntimeException('A valid FirePhage signature token is required.');
        }

        $subscriber = WordPressSubscriber::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->first();

        if (! $subscriber) {
            throw new RuntimeException('The FirePhage signature token is invalid.');
        }

        $subscriber->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return $subscriber;
    }

    private function resolveSiteHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }
}
