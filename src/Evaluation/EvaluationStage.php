<?php

declare(strict_types=1);

namespace Batoi\Aif\Evaluation;

enum EvaluationStage: string
{
    case PreExecution = 'pre_execution';
    case PostExecution = 'post_execution';
}
