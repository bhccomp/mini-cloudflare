<?php

return [
    'default_provider' => env('DEFAULT_EDGE_PROVIDER', 'bunny'),

    'feature_aws_onboarding' => env('FEATURE_AWS_ONBOARDING', false),

    'bunny' => [
        'base_url' => env('BUNNY_API_BASE_URL', 'https://api.bunny.net'),
    ],
];
