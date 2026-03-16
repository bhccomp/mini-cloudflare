<?php

return [
    'early_access_enabled' => (bool) env('EARLY_ACCESS_ENABLED', true),

    'early_access_bypass_ips' => array_values(array_filter(array_map(
        static fn (string $ip): string => trim($ip),
        explode(',', (string) env('EARLY_ACCESS_BYPASS_IPS', '87.116.135.34'))
    ))),
];
