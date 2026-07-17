<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\PolicySubject;

interface OperationAwarePolicyEngineInterface extends PolicyEngineInterface
{
    public function decideForOperation(ExecutionContext $context, PolicySubject $subject): PolicyDecision;
}
