<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Providers;

use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Tests\Fixtures\FakeHttpTransport;
use Batoi\Aif\Value\HttpResponse;
use Batoi\Aif\Value\InferenceRequest;
use PHPUnit\Framework\TestCase;

final class OpenAICompatibleProviderTest extends TestCase
{
    public function testSafeRateLimitAndRequestHeadersAreExposedAsMetadata(): void
    {
        $transport = new FakeHttpTransport([
            '/chat/completions' => new HttpResponse(
                200,
                '{"model":"model-1","choices":[{"message":{"content":"Done"}}]}',
                [
                    'x-request-id' => 'request_1',
                    'x-ratelimit-remaining-requests' => '12',
                    'authorization' => 'must-not-be-exposed',
                ],
            ),
        ]);
        $provider = new OpenAICompatibleProvider('secret', transport: $transport);

        $response = $provider->generateText(new InferenceRequest('Hello'));

        self::assertSame('request_1', $response->requestUid);
        self::assertSame('12', $response->metadata['transport']['ratelimit_remaining_requests']);
        self::assertArrayNotHasKey('authorization', $response->metadata['transport']);
    }
}
