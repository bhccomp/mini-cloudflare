<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ApexDomainRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(trim((string) $value));

        if ($domain === '' || str_starts_with($domain, '*.') || str_contains($domain, '/')) {
            $fail('Provide a valid domain without wildcards or paths.');

            return;
        }

        if (! preg_match('/^(?=.{3,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            $fail('Provide a valid domain (example.com).');

            return;
        }

        if (str_ends_with($domain, '.local') || str_ends_with($domain, '.internal')) {
            $fail('Internal domains are not allowed.');
        }
    }
}
