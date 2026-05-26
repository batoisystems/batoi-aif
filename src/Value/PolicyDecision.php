<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Policy\PolicyAction;

final readonly class PolicyDecision
{
    /**
     * @param list<string> $reasons
     * @param array<string, mixed> $evidence
     */
    public function __construct(
        public PolicyAction $action,
        public array $reasons = [],
        public array $evidence = [],
    ) {
    }

    public function allowsExecution(): bool
    {
        return $this->action === PolicyAction::Allow || $this->action === PolicyAction::RedactAndContinue;
    }
}
