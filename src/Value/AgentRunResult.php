<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class AgentRunResult
{
    /** @param list<ToolResult> $steps */
    public function __construct(public array $steps, public int $durationMs)
    {
    }
}
