<?php

declare(strict_types=1);

namespace Batoi\Aif\Laravel\Tests;

use Batoi\Aif\Laravel\LaravelExecutionContextResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class LaravelExecutionContextResolverTest extends TestCase
{
    public function testAuthenticatedRequestMapsToExecutionContext(): void
    {
        $request = Request::create('/', 'POST', server: [
            'HTTP_X_SPACE_ID' => '20',
            'HTTP_X_REQUEST_ID' => 'trace_1',
        ]);
        $request->setUserResolver(static fn (): object => (object) ['id' => 10, 'role' => 'admin']);

        $context = (new LaravelExecutionContextResolver())->resolve($request);

        self::assertSame('10', $context->userId);
        self::assertSame('20', $context->workspaceId);
        self::assertSame(['admin'], $context->roles);
        self::assertSame('trace_1', $context->traceUid);
    }
}
