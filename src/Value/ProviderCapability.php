<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ProviderCapability
{
    /**
     * @param list<string> $capabilities
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $provider,
        public string $model,
        public array $capabilities,
        public array $metadata = [],
    ) {
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
