<?php

return [
    'early_access_enabled' => filter_var(env('EARLY_ACCESS_ENABLED', true), FILTER_VALIDATE_BOOL),
    'google_analytics_measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID'),
    'crisp_website_id' => env('CRISP_WEBSITE_ID'),

    'early_access_bypass_ips' => array_values(array_filter(array_map(
        static fn (string $ip): string => trim($ip),
        explode(',', (string) env('EARLY_ACCESS_BYPASS_IPS', '87.116.135.34'))
    ))),
];
