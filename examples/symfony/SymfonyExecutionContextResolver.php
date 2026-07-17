<?php

declare(strict_types=1);

namespace Batoi\Aif\Symfony;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
use Batoi\Aif\Value\ExecutionContext;
use Symfony\Component\HttpFoundation\Request;

final readonly class SymfonyExecutionContextResolver implements ExecutionContextResolverInterface
{
    public function resolve(mixed $request): ExecutionContext
    {
        $roles = [];
        $userId = '0';

        if ($request instanceof Request) {
            $userId = (string) ($request->attributes->get('user_id') ?? '0');
            $roles = array_values(array_map('strval', (array) $request->attributes->get('roles', [])));
        }

        return new ExecutionContext(
            userId: $userId,
            workspaceId: $request instanceof Request ? (string) ($request->headers->get('X-Space-Id') ?? '0') : '0',
            roles: $roles,
            traceUid: $request instanceof Request ? $request->headers->get('X-Request-Id') : null,
            metadata: [
                'framework' => 'symfony',
            ],
        );
    }
}
