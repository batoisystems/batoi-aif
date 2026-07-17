<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\EvaluatorInterface;
use Batoi\Aif\Evaluation\EvaluationOutcome;
use Batoi\Aif\Evaluation\EvaluationStage;
use Batoi\Aif\Value\EvaluationResult;
use Batoi\Aif\Value\EvaluationSubject;

final readonly class FailingPostEvaluator implements EvaluatorInterface
{
    public function evaluate(EvaluationSubject $subject): EvaluationResult
    {
        return new EvaluationResult(
            evaluator: 'test_safety',
            outcome: $subject->stage === EvaluationStage::PostExecution
                ? EvaluationOutcome::Fail
                : EvaluationOutcome::Pass,
            evidence: ['stage' => $subject->stage->value],
        );
    }
}
