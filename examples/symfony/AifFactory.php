<?php

declare(strict_types=1);

namespace App\Aif;

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;

final readonly class AifFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function gateway(array $config): AifGateway
    {
        $providers = [
            'mock' => new MockProvider(),
        ];
        $openAi = is_array($config['providers']['openai'] ?? null) ? $config['providers']['openai'] : [];

        if (!empty($openAi['api_key'])) {
            $providers['openai'] = new OpenAICompatibleProvider(
                apiKey: (string) $openAi['api_key'],
                baseUrl: (string) ($openAi['base_url'] ?? 'https://api.openai.com/v1'),
            );
        }

        return new AifGateway(
            providers: new InMemoryProviderRegistry($providers),
            defaultProvider: (string) ($config['default_provider'] ?? 'mock'),
            policyEngine: new StaticPolicyEngine(
                allowedProviders: array_values($config['policy']['allowed_providers'] ?? []),
                allowedRoles: array_values($config['policy']['allowed_roles'] ?? []),
            ),
        );
    }
}

