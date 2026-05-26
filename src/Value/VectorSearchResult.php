<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class VectorSearchResult
{
    public function __construct(
        public VectorRecord $record,
        public float $score,
    ) {
    }
}
