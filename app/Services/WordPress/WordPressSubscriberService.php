<?php

namespace App\Services\WordPress;

use App\Models\WordPressSubscriber;
use App\Notifications\WordPressFreeTokenNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
        $verificationToken = 'fpv_' . Str::random(48);
        $statusToken = 'fps_' . Str::random(48);

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
                'token_encrypted' => Crypt::encryptString($plainToken),
                'status_token_hash' => hash('sha256', $statusToken),
                'verification_token_hash' => hash('sha256', $verificationToken),
                'status' => 'pending',
                'last_token_issued_at' => now(),
                'verified_at' => null,
            ],
        );

        $verifyUrl = $this->buildWordPressVerifyUrl($siteUrl !== '' ? $siteUrl : $homeUrl, $verificationToken);

        Notification::route('mail', $email)
            ->notify(new WordPressFreeTokenNotification($siteHost, $verifyUrl));

        return [
            'email' => $subscriber->email,
            'site_host' => $subscriber->site_host,
            'status' => 'pending',
            'status_token' => $statusToken,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function status(string $statusToken): array
    {
        $subscriber = WordPressSubscriber::query()
            ->where('status_token_hash', hash('sha256', $statusToken))
            ->first();

        if (! $subscriber) {
            throw new RuntimeException('The FirePhage signature verification request is invalid.');
        }

        if ($subscriber->status === 'active' && $subscriber->verified_at !== null) {
            return [
                'status' => 'verified',
                'email' => (string) $subscriber->email,
                'site_host' => (string) $subscriber->site_host,
                'token' => Crypt::decryptString((string) $subscriber->token_encrypted),
            ];
        }

        return [
            'status' => 'pending',
            'email' => (string) $subscriber->email,
            'site_host' => (string) $subscriber->site_host,
        ];
    }

    public function verify(string $verificationToken): WordPressSubscriber
    {
        $subscriber = WordPressSubscriber::query()
            ->where('verification_token_hash', hash('sha256', $verificationToken))
            ->first();

        if (! $subscriber) {
            throw new RuntimeException('This FirePhage verification link is invalid or has already been used.');
        }

        $subscriber->forceFill([
            'status' => 'active',
            'verified_at' => now(),
            'verification_token_hash' => null,
        ])->save();

        return $subscriber;
    }

    /**
     * @return array<string, string>
     */
    public function verifyForPlugin(string $verificationToken): array
    {
        $subscriber = $this->verify($verificationToken);

        return [
            'status' => 'verified',
            'email' => (string) $subscriber->email,
            'site_host' => (string) $subscriber->site_host,
            'token' => Crypt::decryptString((string) $subscriber->token_encrypted),
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

    private function buildWordPressVerifyUrl(string $siteUrl, string $verificationToken): string
    {
        $baseUrl = rtrim($siteUrl, '/');

        if ($baseUrl === '') {
            return route('wordpress.free-token.verify', ['token' => $verificationToken]);
        }

        return $baseUrl . '/wp-admin/admin.php?page=firephage-security&firephage_verify=' . rawurlencode($verificationToken);
    }
}
