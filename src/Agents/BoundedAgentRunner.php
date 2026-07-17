<?php

declare(strict_types=1);

namespace Batoi\Aif\Agents;

use Batoi\Aif\Exception\AgentBudgetExceededException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Tools\ToolRegistry;
use Batoi\Aif\Value\AgentBudget;
use Batoi\Aif\Value\AgentRunResult;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\ToolCall;

final readonly class BoundedAgentRunner
{
    public function __construct(private AifGateway $gateway, private ToolRegistry $tools)
    {
    }

    /** @param list<ToolCall> $calls */
    public function run(array $calls, ExecutionContext $context, AgentBudget $budget = new AgentBudget()): AgentRunResult
    {
        if (count($calls) > $budget->maxSteps) {
            throw new AgentBudgetExceededException('Agent step budget exceeded before execution.');
        }

        $startedAt = hrtime(true);
        $results = [];
        foreach ($calls as $call) {
            $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            if ($elapsedMs >= $budget->maxDurationMs) {
                throw new AgentBudgetExceededException('Agent duration budget exceeded.');
            }

            $results[] = $this->gateway->executeTool(
                $this->tools->get($call->toolCode),
                $call->arguments,
                $context,
            );
        }

        return new AgentRunResult(
            steps: $results,
            durationMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
        );
    }
}
