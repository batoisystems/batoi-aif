<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use InvalidArgumentException;

final readonly class VectorSearchRequest
{
    /**
     * @param list<float|int> $vector
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public string $collection,
        public array $vector,
        public int $topK = 5,
        public float $minScore = -1.0,
        public array $filters = [],
    ) {
        if (trim($this->collection) === '') {
            throw new InvalidArgumentException('Vector search collection is required.');
        }

        if ($this->vector === []) {
            throw new InvalidArgumentException('Vector search requires at least one dimension.');
        }

        if ($this->topK < 1) {
            throw new InvalidArgumentException('Vector search topK must be greater than zero.');
        }
    }

    /**
     * @return list<float>
     */
    public function normalizedVector(): array
    {
        return array_values(array_map(static fn (float|int $value): float => (float) $value, $this->vector));
    }
}
