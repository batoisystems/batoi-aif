<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicyDecision;

interface PolicyEngineInterface
{
    public function decide(ExecutionContext $context, InferenceRequest $request): PolicyDecision;
}
