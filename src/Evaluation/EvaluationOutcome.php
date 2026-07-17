<?php

declare(strict_types=1);

namespace Batoi\Aif\Evaluation;

enum EvaluationOutcome: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
