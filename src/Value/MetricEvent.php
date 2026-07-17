<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class MetricEvent
{
    /** @param array<string, string> $tags */
    public function __construct(
        public string $name,
        public float $value = 1.0,
        public array $tags = [],
        public ?string $occurredAt = null,
    ) {
    }
}
