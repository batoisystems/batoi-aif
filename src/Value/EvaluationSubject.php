<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Evaluation\EvaluationStage;
use Batoi\Aif\Policy\ExecutionOperation;

final readonly class EvaluationSubject
{
    /** @param array<string, mixed> $output */
    public function __construct(
        public EvaluationStage $stage,
        public ExecutionOperation $operation,
        public string $input,
        public array $output = [],
        public ?ExecutionContext $context = null,
    ) {
    }
}
