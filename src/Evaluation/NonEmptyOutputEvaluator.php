<?php

declare(strict_types=1);

namespace Batoi\Aif\Evaluation;

use Batoi\Aif\Contracts\EvaluatorInterface;
use Batoi\Aif\Value\EvaluationResult;
use Batoi\Aif\Value\EvaluationSubject;

final readonly class NonEmptyOutputEvaluator implements EvaluatorInterface
{
    public function evaluate(EvaluationSubject $subject): EvaluationResult
    {
        if ($subject->stage === EvaluationStage::PreExecution) {
            return new EvaluationResult('non_empty_output', EvaluationOutcome::Pass);
        }

        $output = $subject->output['text'] ?? null;
        $passes = !is_string($output) || trim($output) !== '';

        return new EvaluationResult(
            evaluator: 'non_empty_output',
            outcome: $passes ? EvaluationOutcome::Pass : EvaluationOutcome::Fail,
            evidence: ['text_output_present' => $passes],
        );
    }
}
