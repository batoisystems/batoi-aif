<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ExecutionCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Execution was cancelled.');
    }
}
