<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ProviderDefinition
{
    /**
     * @param list<ProviderCapability> $models
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $type,
        public string $status,
        public array $models = [],
        public array $metadata = [],
    ) {
    }
}
