<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class Citation
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $sourceUid,
        public string $chunkUid,
        public string $content,
        public float $score,
        public array $metadata = [],
    ) {
    }
}
