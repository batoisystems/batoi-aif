<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;

interface PermissionCheckerInterface
{
    /**
     * @param array<string, mixed> $resource
     */
    public function can(ExecutionContext $context, string $ability, array $resource = []): bool;
}
