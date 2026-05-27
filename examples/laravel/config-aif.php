<?php

declare(strict_types=1);

return [
    'default_provider' => env('AIF_PROVIDER', 'mock'),
    'providers' => [
        'mock' => [
            'type' => 'mock',
        ],
        'openai' => [
            'type' => 'openai-compatible',
            'base_url' => env('AIF_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('AIF_OPENAI_API_KEY'),
        ],
    ],
    'policy' => [
        'allowed_roles' => ['admin'],
        'allowed_providers' => ['mock', 'openai'],
    ],
];

