<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Api;

use Batoi\Aif\Api\AifApi;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Tests\Fixtures\RecordingProvider;
use PHPUnit\Framework\TestCase;

final class AifApiTest extends TestCase
{
    public function testInvalidRequestReturnsStableSafeError(): void
    {
        $provider = new RecordingProvider();
        $api = new AifApi(new AifGateway(
            new InMemoryProviderRegistry(['recording' => $provider]),
            defaultProvider: 'recording',
        ));

        $response = $api->infer([]);

        self::assertFalse($response['ok']);
        self::assertSame('invalid_request', $response['error']['code']);
        self::assertSame(422, $response['error']['http_status']);
        self::assertSame([], $provider->calls);
    }
}
