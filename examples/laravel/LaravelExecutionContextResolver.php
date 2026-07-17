<?php

declare(strict_types=1);

namespace Batoi\Aif\Laravel;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
use Batoi\Aif\Value\ExecutionContext;

final readonly class LaravelExecutionContextResolver implements ExecutionContextResolverInterface
{
    public function resolve(mixed $request): ExecutionContext
    {
        $user = is_object($request) && method_exists($request, 'user') ? $request->user() : null;
        $roles = [];

        if (is_object($user) && method_exists($user, 'getRoleNames')) {
            $roles = array_values($user->getRoleNames()->toArray());
        } elseif (is_object($user) && isset($user->role)) {
            $roles = [(string) $user->role];
        }

        return new ExecutionContext(
            userId: is_object($user) && isset($user->id) ? (string) $user->id : '0',
            workspaceId: is_object($request) && method_exists($request, 'header') ? (string) ($request->header('X-Space-Id') ?? '0') : '0',
            roles: $roles,
            traceUid: is_object($request) && method_exists($request, 'header') ? $request->header('X-Request-Id') : null,
            metadata: [
                'framework' => 'laravel',
            ],
        );
    }
}
