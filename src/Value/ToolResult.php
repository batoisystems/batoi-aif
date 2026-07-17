<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ToolResult
{
    /**
     * @param array<string, mixed> $output
     * @param array<string, mixed> $metadata
     */
    public function __construct(public array $output, public array $metadata = [])
    {
    }
}
