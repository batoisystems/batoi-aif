<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(public string $toolCode, public array $arguments = [])
    {
    }
}
