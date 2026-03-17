<?php

return [
    'enabled' => env('DEMO_ENABLED', true),
    'host' => env('DEMO_HOST', 'demo.firephage.com'),
    'refresh_cron' => env('DEMO_REFRESH_CRON', '0 */6 * * *'),
    'cleanup_cron' => env('DEMO_CLEANUP_CRON', '30 3 * * *'),
    'blocked_paths' => [
        'app/my-profile',
        'app/organization-settings-page',
        'app/billing',
        'app/alert-channels',
        'app/alert-rules',
        'app/alert-rules/*',
        'app/sites/create',
        'app/sites/*/edit',
        'app/passkeys/*',
        'app/two-factor-authentication',
    ],
    'account' => [
        'name' => env('DEMO_ACCOUNT_NAME', 'FirePhage Demo'),
        'email' => env('DEMO_ACCOUNT_EMAIL', 'demo@firephage.com'),
        'password' => env('DEMO_ACCOUNT_PASSWORD', 'FirePhageDemo!2026'),
    ],
    'organization' => [
        'name' => 'FirePhage Demo',
        'slug' => 'firephage-demo',
    ],
    'site' => [
        'apex_domain' => 'demo-store.firephage.test',
        'display_name' => 'Demo WooCommerce Store',
    ],
];
