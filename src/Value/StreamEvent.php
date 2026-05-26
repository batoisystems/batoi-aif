<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class StreamEvent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $type,
        public string $content = '',
        public array $metadata = [],
    ) {
    }
}
