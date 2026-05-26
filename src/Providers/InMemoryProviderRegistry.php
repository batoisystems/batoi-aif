<?php

declare(strict_types=1);

namespace Batoi\Aif\Providers;

use Batoi\Aif\Contracts\AIProviderInterface;
use Batoi\Aif\Contracts\ProviderRegistryInterface;
use Batoi\Aif\Exception\ProviderNotFoundException;

final class InMemoryProviderRegistry implements ProviderRegistryInterface
{
    /**
     * @param array<string, AIProviderInterface> $providers
     */
    public function __construct(
        private array $providers = [],
    ) {
    }

    public function register(string $providerCode, AIProviderInterface $provider): void
    {
        $this->providers[$providerCode] = $provider;
    }

    public function get(string $providerCode): AIProviderInterface
    {
        if (!$this->has($providerCode)) {
            throw ProviderNotFoundException::forCode($providerCode);
        }

        return $this->providers[$providerCode];
    }

    public function has(string $providerCode): bool
    {
        return isset($this->providers[$providerCode]);
    }
}
