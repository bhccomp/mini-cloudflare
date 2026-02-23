<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeOriginUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $url = trim((string) $value);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $fail('Origin URL must be a valid URL.');

            return;
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Only HTTP/HTTPS origins are allowed.');

            return;
        }

        if (! $host || $host === 'localhost' || str_ends_with($host, '.local')) {
            $fail('Local/private origins are not allowed.');

            return;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = gethostbynamel($host) ?: [];
            $ips = array_merge($ips, $resolved);
        }

        foreach (array_unique($ips) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('Origin resolves to private or reserved IP ranges; this is blocked.');

                return;
            }
        }
    }
}
