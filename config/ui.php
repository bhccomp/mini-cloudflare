<?php

return [
    'default_mode' => env('APP_UI_MODE_DEFAULT', 'simple'),

    'session_key' => 'app.ui_mode',

    // Monthly included bandwidth (GB) used by dashboard usage cards.
    'bandwidth_limits' => [
        'default' => (int) env('PLAN_BANDWIDTH_DEFAULT_GB', 500),
        'basic' => (int) env('PLAN_BANDWIDTH_BASIC_GB', 500),
        'pro' => (int) env('PLAN_BANDWIDTH_PRO_GB', 2000),
        'business' => (int) env('PLAN_BANDWIDTH_BUSINESS_GB', 10000),
    ],

    // Availability monitor cadence (seconds).
    'availability_monitor_intervals' => [
        'basic' => (int) env('AVAILABILITY_MONITOR_BASIC_SECONDS', 300),
        'paid' => (int) env('AVAILABILITY_MONITOR_PAID_SECONDS', 60),
    ],

    // Plans treated as paid for monitoring cadence.
    'availability_monitor_paid_plans' => array_values(array_filter(array_map(
        fn (string $value): string => trim(strtolower($value)),
        explode(',', (string) env('AVAILABILITY_MONITOR_PAID_PLANS', 'pro,business,enterprise'))
    ))),
];
