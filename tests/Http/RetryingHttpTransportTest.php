<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Http;

use Batoi\Aif\Http\RetryingHttpTransport;
use Batoi\Aif\Http\RetryPolicy;
use Batoi\Aif\Tests\Fixtures\RecordingSleeper;
use Batoi\Aif\Tests\Fixtures\SequenceHttpTransport;
use Batoi\Aif\Observability\InMemoryMetricsCollector;
use Batoi\Aif\Value\HttpResponse;
use PHPUnit\Framework\TestCase;

final class RetryingHttpTransportTest extends TestCase
{
    public function testRateLimitResponseIsRetriedUsingRetryAfter(): void
    {
        $inner = new SequenceHttpTransport([
            new HttpResponse(429, '{}', ['retry-after' => '1']),
            new HttpResponse(200, '{"ok":true}'),
        ]);
        $sleeper = new RecordingSleeper();
        $metrics = new InMemoryMetricsCollector();
        $transport = new RetryingHttpTransport(
            $inner,
            new RetryPolicy(maxAttempts: 3, baseDelayMs: 10, maxDelayMs: 1500),
            $sleeper,
            $metrics,
        );

        $response = $transport->postJson('https://example.test', [], []);

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $inner->calls);
        self::assertSame([1000], $sleeper->delays);
        self::assertSame('aif.http.retry', $metrics->all()[0]->name);
        self::assertSame('429', $metrics->all()[0]->tags['status_code']);
    }

    public function testNonRetryableClientErrorIsReturnedImmediately(): void
    {
        $inner = new SequenceHttpTransport([new HttpResponse(400, '{}')]);
        $sleeper = new RecordingSleeper();
        $transport = new RetryingHttpTransport($inner, sleeper: $sleeper);

        self::assertSame(400, $transport->postJson('https://example.test', [], [])->statusCode);
        self::assertSame(1, $inner->calls);
        self::assertSame([], $sleeper->delays);
    }
}
