<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

interface ProviderRegistryInterface
{
    public function get(string $providerCode): AIProviderInterface;

    public function has(string $providerCode): bool;
}
