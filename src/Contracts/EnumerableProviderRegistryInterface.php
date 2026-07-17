<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

interface EnumerableProviderRegistryInterface extends ProviderRegistryInterface
{
    /** @return array<string, AIProviderInterface> */
    public function all(): array;
}
