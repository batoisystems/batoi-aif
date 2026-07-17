<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Tools\ToolSideEffect;

final readonly class ToolDefinition
{
    /** @param list<string> $requiredArguments */
    public function __construct(
        public string $code,
        public ToolSideEffect $sideEffect = ToolSideEffect::None,
        public ?string $permission = null,
        public bool $requiresReview = false,
        public bool $idempotent = true,
        public array $requiredArguments = [],
    ) {
    }
}
