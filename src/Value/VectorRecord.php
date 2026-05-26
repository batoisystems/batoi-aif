<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use InvalidArgumentException;

final readonly class VectorRecord
{
    /**
     * @param list<float|int> $vector
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $collection,
        public string $id,
        public array $vector,
        public string $content = '',
        public array $metadata = [],
    ) {
        if (trim($this->collection) === '') {
            throw new InvalidArgumentException('Vector collection is required.');
        }

        if (trim($this->id) === '') {
            throw new InvalidArgumentException('Vector record ID is required.');
        }

        if ($this->vector === []) {
            throw new InvalidArgumentException('Vector record requires at least one dimension.');
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
