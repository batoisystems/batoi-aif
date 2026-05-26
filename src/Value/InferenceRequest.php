<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class InferenceRequest
{
    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $input,
        public ?string $promptCode = null,
        public ?string $promptVersion = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $variables = [],
        public array $metadata = [],
    ) {
    }
}
