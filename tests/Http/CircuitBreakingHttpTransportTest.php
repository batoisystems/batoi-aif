<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Http;

use Batoi\Aif\Exception\CircuitBreakerOpenException;
use Batoi\Aif\Exception\ExecutionCancelledException;
use Batoi\Aif\Http\CancellationToken;
use Batoi\Aif\Http\CircuitBreakerPolicy;
use Batoi\Aif\Http\CircuitBreakingHttpTransport;
use Batoi\Aif\Tests\Fixtures\SequenceHttpTransport;
use Batoi\Aif\Value\HttpResponse;
use PHPUnit\Framework\TestCase;

final class CircuitBreakingHttpTransportTest extends TestCase
{
    public function testCircuitOpensAfterConfiguredTransientFailures(): void
    {
        $inner = new SequenceHttpTransport([
            new HttpResponse(500, '{}'),
            new HttpResponse(429, '{}'),
        ]);
        $transport = new CircuitBreakingHttpTransport(
            $inner,
            'provider:openai',
            new CircuitBreakerPolicy(failureThreshold: 2, cooldownSeconds: 30),
        );

        self::assertSame(500, $transport->postJson('https://example.test', [], [])->statusCode);
        self::assertSame(429, $transport->postJson('https://example.test', [], [])->statusCode);

        $this->expectException(CircuitBreakerOpenException::class);
        $transport->postJson('https://example.test', [], []);
    }

    public function testCancellationTokenFailsCooperatively(): void
    {
        $token = new CancellationToken();
        $token->cancel();

        $this->expectException(ExecutionCancelledException::class);
        $token->throwIfCancellationRequested();
    }
}
