<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\OperationAwarePolicyEngineInterface;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\PolicySubject;

final class ConfigurablePolicyEngine implements OperationAwarePolicyEngineInterface
{
    /** @var list<PolicySubject> */
    public array $subjects = [];

    public function __construct(private PolicyDecision $decision)
    {
    }

    public function decide(ExecutionContext $context, InferenceRequest $request): PolicyDecision
    {
        return $this->decideForOperation($context, new PolicySubject(ExecutionOperation::Infer, $request));
    }

    public function decideForOperation(ExecutionContext $context, PolicySubject $subject): PolicyDecision
    {
        $this->subjects[] = $subject;

        return $this->decision;
    }
}
