<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ProviderDefinition;

interface ProviderCatalogInterface
{
    public function get(string $providerCode): ProviderDefinition;

    /** @return list<ProviderDefinition> */
    public function all(): array;
}
