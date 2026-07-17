<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class RagGenerationResult
{
    /** @param list<Citation> $citations */
    public function __construct(
        public InferenceResponse $response,
        public array $citations,
    ) {
    }
}
