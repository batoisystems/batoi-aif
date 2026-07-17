<?php

declare(strict_types=1);

namespace Batoi\Aif\Laravel;

use Batoi\Aif\Api\AifApi;
use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Illuminate\Support\ServiceProvider;

final class AifServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AifGateway::class, function (): AifGateway {
            $config = config('aif');
            $providers = [
                'mock' => new MockProvider(),
            ];

            if (!empty($config['providers']['openai']['api_key'])) {
                $providers['openai'] = new OpenAICompatibleProvider(
                    apiKey: (string) $config['providers']['openai']['api_key'],
                    baseUrl: (string) ($config['providers']['openai']['base_url'] ?? 'https://api.openai.com/v1'),
                );
            }

            return new AifGateway(
                providers: new InMemoryProviderRegistry($providers),
                defaultProvider: (string) ($config['default_provider'] ?? 'mock'),
                policyEngine: new StaticPolicyEngine(
                    allowedProviders: array_values($config['policy']['allowed_providers'] ?? []),
                    allowedRoles: array_values($config['policy']['allowed_roles'] ?? []),
                ),
                auditLog: new InMemoryAuditLog(),
                runtimeMode: RuntimeMode::Governed,
            );
        });

        $this->app->singleton(AifApi::class, fn (): AifApi => new AifApi(
            $this->app->make(AifGateway::class),
            new LaravelExecutionContextResolver(),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config-aif.php' => config_path('aif.php'),
        ], 'batoi-aif-config');
    }
}
