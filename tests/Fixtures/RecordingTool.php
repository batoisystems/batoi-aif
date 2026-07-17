<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\ToolInterface;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\ToolDefinition;
use Batoi\Aif\Value\ToolResult;

final class RecordingTool implements ToolInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private ToolDefinition $definition)
    {
    }

    public function definition(): ToolDefinition
    {
        return $this->definition;
    }

    public function execute(array $arguments, ExecutionContext $context): ToolResult
    {
        $this->calls[] = $arguments;

        return new ToolResult(['tool' => $this->definition->code, 'arguments' => $arguments]);
    }
}
