<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OriginIpRule implements ValidationRule
{
    /**
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $input = trim((string) $value);

        if ($input === '') {
            $fail('Origin / Server IP is required.');

            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $fail('Enter a valid public IPv4 address (for example: 203.0.113.10). Domains are not allowed.');

            return;
        }

        if ($this->isPrivateOrReserved($input)) {
            $fail('Origin / Server IP must be a public IPv4 address. Private or reserved ranges are not allowed.');
        }
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
