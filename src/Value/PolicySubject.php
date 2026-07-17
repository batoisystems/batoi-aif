<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Policy\ExecutionOperation;

final readonly class PolicySubject
{
    public function __construct(
        public ExecutionOperation $operation,
        public InferenceRequest $request,
    ) {
    }
}
