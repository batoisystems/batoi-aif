<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ProviderCapability;

interface CapabilityAwareProviderInterface extends AIProviderInterface
{
    /** @return list<ProviderCapability> */
    public function capabilities(): array;
}
