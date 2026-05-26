<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class EmbeddingResponse
{
    /**
     * @param list<float> $embedding
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $embedding,
        public string $provider,
        public string $model,
        public array $usage = [],
        public array $metadata = [],
    ) {
    }
}
