<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ModerationResponse
{
    /**
     * @param list<string> $categories
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $flagged,
        public array $categories = [],
        public array $metadata = [],
    ) {
    }
}
