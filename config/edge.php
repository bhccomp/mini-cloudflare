<?php

return [
    'default_provider' => env('EDGE_PROVIDER', 'aws'),

    'bunny' => [
        'base_url' => env('BUNNY_API_BASE_URL', 'https://api.bunny.net'),
    ],
];
