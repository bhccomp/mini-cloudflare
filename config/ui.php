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
];
