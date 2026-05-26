<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
use Batoi\Aif\Value\ExecutionContext;

final readonly class RadRunDataContextResolver implements ExecutionContextResolverInterface
{
    /**
     * @param mixed $request RAD controller runData, session array, or flattened context.
     */
    public function resolve(mixed $request): ExecutionContext
    {
        $runData = is_array($request) ? $request : [];
        $session = $this->arrayValue($runData['session'] ?? []);
        $user = $this->arrayValue($runData['user'] ?? $runData['entity'] ?? []);
        $workspace = $this->arrayValue($runData['workspace'] ?? $runData['space'] ?? []);
        $route = $this->arrayValue($runData['route'] ?? []);
        $roles = $this->mergeRoles(
            $runData['roles'] ?? null,
            $session['roles'] ?? null,
            $user['roles'] ?? null,
            $runData['role_id'] ?? null,
            $session['role_id'] ?? null,
        );

        $entityId = $runData['entity_id']
            ?? $session['entity_id']
            ?? $user['entity_id']
            ?? $user['id']
            ?? $runData['user_id']
            ?? $session['user_id']
            ?? '0';
        $spaceId = $runData['space_id']
            ?? $session['space_id']
            ?? $workspace['space_id']
            ?? $workspace['id']
            ?? '0';

        return new ExecutionContext(
            userId: (string) $entityId,
            workspaceId: (string) $spaceId,
            roles: $roles,
            traceUid: isset($runData['trace_uid']) ? (string) $runData['trace_uid'] : null,
            metadata: [
                'rad' => [
                    'entity_id' => $entityId,
                    'space_id' => $spaceId,
                    'role_id' => $runData['role_id'] ?? $session['role_id'] ?? null,
                    'ms_id' => $runData['ms_id'] ?? $route['ms_id'] ?? null,
                    'route_id' => $runData['route_id'] ?? $route['id'] ?? null,
                    'route_uri' => $route['uri'] ?? $route['url'] ?? null,
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function mergeRoles(mixed ...$values): array
    {
        $roles = [];

        foreach ($values as $value) {
            foreach ($this->stringList($value) as $role) {
                if ($role !== '' && !in_array($role, $roles, true)) {
                    $roles[] = $role;
                }
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value) || is_int($value)) {
            return [(string) $value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $role): string => (string) $role, $value));
    }
}
