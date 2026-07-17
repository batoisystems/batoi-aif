<?php

declare(strict_types=1);

namespace Batoi\Aif\Evaluation;

use Batoi\Aif\Contracts\EvaluatorInterface;
use Batoi\Aif\Exception\EvaluationBlockedException;
use Batoi\Aif\Value\EvaluationResult;
use Batoi\Aif\Value\EvaluationSubject;

final readonly class EvaluationPipeline
{
    /** @param list<EvaluatorInterface> $evaluators */
    public function __construct(private array $evaluators)
    {
    }

    /** @return list<EvaluationResult> */
    public function evaluate(EvaluationSubject $subject): array
    {
        $results = [];
        $blocked = false;

        foreach ($this->evaluators as $evaluator) {
            $result = $evaluator->evaluate($subject);
            $results[] = $result;
            $blocked = $blocked || $result->outcome === EvaluationOutcome::Fail;
        }

        if ($blocked) {
            throw new EvaluationBlockedException($results);
        }

        return $results;
    }
}
