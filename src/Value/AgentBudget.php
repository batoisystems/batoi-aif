<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use InvalidArgumentException;

final readonly class AgentBudget
{
    public function __construct(public int $maxSteps = 5, public int $maxDurationMs = 30_000)
    {
        if ($this->maxSteps < 1 || $this->maxDurationMs < 1) {
            throw new InvalidArgumentException('Agent step and duration budgets must be positive.');
        }
    }
}
