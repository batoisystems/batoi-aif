<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class InferenceResponse
{
    /**
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $output,
        public string $provider,
        public string $model,
        public string $requestUid,
        public array $usage = [],
        public array $metadata = [],
    ) {
    }
}
