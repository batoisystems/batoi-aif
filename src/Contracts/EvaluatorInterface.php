<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\EvaluationResult;
use Batoi\Aif\Value\EvaluationSubject;

interface EvaluatorInterface
{
    public function evaluate(EvaluationSubject $subject): EvaluationResult;
}
