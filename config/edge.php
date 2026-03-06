<?php

return [
    'default_provider' => env('DEFAULT_EDGE_PROVIDER', 'bunny'),

    'feature_aws_onboarding' => env('FEATURE_AWS_ONBOARDING', false),

    'bunny' => [
        'base_url' => env('BUNNY_API_BASE_URL', 'https://api.bunny.net'),
        'shield_auto_upgrade_to_advanced' => env('BUNNY_SHIELD_AUTO_UPGRADE_TO_ADVANCED', false),
        'shield_advanced_plan_type' => (int) env('BUNNY_SHIELD_ADVANCED_PLAN_TYPE', 0),
        'logging_base_url' => env('BUNNY_LOGGING_BASE_URL', 'https://logging.bunnycdn.com'),
        'logging_storage_zone_id' => (int) env('BUNNY_LOGGING_STORAGE_ZONE_ID', 0),
        'log_forwarding_enabled' => env('BUNNY_LOG_FORWARDING_ENABLED', false),
        'log_forwarding_hostname' => env('BUNNY_LOG_FORWARDING_HOSTNAME', ''),
        'log_forwarding_port' => (int) env('BUNNY_LOG_FORWARDING_PORT', 514),
        'log_forwarding_token' => env('BUNNY_LOG_FORWARDING_TOKEN', ''),
        'log_forwarding_protocol' => env('BUNNY_LOG_FORWARDING_PROTOCOL', ''),
        'log_forwarding_format' => env('BUNNY_LOG_FORWARDING_FORMAT', ''),
    ],
];
