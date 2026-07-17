<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use Batoi\Aif\Value\EvaluationResult;
use RuntimeException;

final class EvaluationBlockedException extends RuntimeException
{
    /** @param list<EvaluationResult> $results */
    public function __construct(public readonly array $results)
    {
        parent::__construct('Execution output failed evaluation.');
    }
}
