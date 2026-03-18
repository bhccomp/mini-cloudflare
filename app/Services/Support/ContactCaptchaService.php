<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;

class ContactCaptchaService
{
    public function shouldUseTurnstile(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $ipAddress = null): bool
    {
        if (! $this->shouldUseTurnstile()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ipAddress,
            ]);

        return (bool) $response->json('success', false);
    }
}
