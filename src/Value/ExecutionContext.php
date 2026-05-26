<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ExecutionContext
{
    /**
     * @param list<string> $roles
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $userId,
        public string $workspaceId,
        public array $roles = [],
        public ?string $traceUid = null,
        public array $metadata = [],
    ) {
    }
}
