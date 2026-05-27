<?php

declare(strict_types=1);

return [
    'default_provider' => getenv('AIF_PROVIDER') ?: 'mock',
    'providers' => [
        'mock' => [
            'type' => 'mock',
        ],
        'openai' => [
            'type' => 'openai-compatible',
            'base_url' => getenv('AIF_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
            'api_key_env' => 'AIF_OPENAI_API_KEY',
        ],
    ],
    'policy' => [
        'allowed_providers' => ['mock', 'openai'],
        'allowed_roles' => ['admin'],
    ],
];

