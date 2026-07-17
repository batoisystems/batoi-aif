<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\ToolDefinition;
use Batoi\Aif\Value\ToolResult;

interface ToolInterface
{
    public function definition(): ToolDefinition;

    /** @param array<string, mixed> $arguments */
    public function execute(array $arguments, ExecutionContext $context): ToolResult;
}
