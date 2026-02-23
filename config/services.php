<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'aws_edge' => [
        'access_key_id' => env('AWS_EDGE_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_EDGE_SECRET_ACCESS_KEY'),
        'region' => env('AWS_EDGE_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'dry_run' => env('AWS_EDGE_DRY_RUN', true),
        'manage_dns' => env('AWS_EDGE_MANAGE_DNS', false),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_basic_monthly' => env('STRIPE_PRICE_BASIC_MONTHLY'),
        'price_pro_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
        'price_business_monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
    ],
];
