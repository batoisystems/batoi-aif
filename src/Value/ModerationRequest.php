<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ModerationRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $input,
        public ?string $model = null,
        public array $metadata = [],
    ) {
    }
}
