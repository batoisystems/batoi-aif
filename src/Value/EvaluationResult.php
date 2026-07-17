<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Evaluation\EvaluationOutcome;

final readonly class EvaluationResult
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $evaluator,
        public EvaluationOutcome $outcome,
        public ?float $score = null,
        public array $evidence = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evaluator' => $this->evaluator,
            'outcome' => $this->outcome->value,
            'score' => $this->score,
            'evidence' => $this->evidence,
        ];
    }
}
