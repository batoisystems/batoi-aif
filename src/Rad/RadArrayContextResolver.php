<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
use Batoi\Aif\Value\ExecutionContext;

final readonly class RadArrayContextResolver implements ExecutionContextResolverInterface
{
    /**
     * @param array<string, mixed> $request
     */
    public function resolve(mixed $request): ExecutionContext
    {
        $data = is_array($request) ? $request : [];

        return new ExecutionContext(
            userId: (string) ($data['entity_id'] ?? $data['user_id'] ?? '0'),
            workspaceId: (string) ($data['space_id'] ?? '0'),
            roles: $this->stringList($data['roles'] ?? []),
            traceUid: isset($data['trace_uid']) ? (string) $data['trace_uid'] : null,
            metadata: [
                'rad' => [
                    'entity_id' => $data['entity_id'] ?? null,
                    'space_id' => $data['space_id'] ?? null,
                    'role_id' => $data['role_id'] ?? null,
                    'ms_id' => $data['ms_id'] ?? null,
                    'route_id' => $data['route_id'] ?? null,
                ],
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $role): string => (string) $role, $value));
    }
}
