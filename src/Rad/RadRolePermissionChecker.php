<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\PermissionCheckerInterface;
use Batoi\Aif\Value\ExecutionContext;

final readonly class RadRolePermissionChecker implements PermissionCheckerInterface
{
    /**
     * @param array<string, list<string>> $abilityRoles
     */
    public function __construct(
        private array $abilityRoles,
    ) {
    }

    public function can(ExecutionContext $context, string $ability, array $resource = []): bool
    {
        $allowedRoles = $this->abilityRoles[$ability] ?? [];

        if ($allowedRoles === []) {
            return false;
        }

        foreach ($context->roles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
