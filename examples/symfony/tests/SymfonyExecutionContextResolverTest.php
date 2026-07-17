<?php

declare(strict_types=1);

namespace Batoi\Aif\Symfony\Tests;

use Batoi\Aif\Symfony\SymfonyExecutionContextResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SymfonyExecutionContextResolverTest extends TestCase
{
    public function testRequestAttributesMapToExecutionContext(): void
    {
        $request = Request::create('/', 'POST', server: [
            'HTTP_X_SPACE_ID' => '20',
            'HTTP_X_REQUEST_ID' => 'trace_1',
        ]);
        $request->attributes->set('user_id', 10);
        $request->attributes->set('roles', ['ROLE_ADMIN']);

        $context = (new SymfonyExecutionContextResolver())->resolve($request);

        self::assertSame('10', $context->userId);
        self::assertSame('20', $context->workspaceId);
        self::assertSame(['ROLE_ADMIN'], $context->roles);
        self::assertSame('trace_1', $context->traceUid);
    }
}
