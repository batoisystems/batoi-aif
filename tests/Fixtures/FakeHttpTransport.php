<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Value\HttpResponse;

final class FakeHttpTransport implements HttpTransportInterface
{
    /**
     * @param array<string, HttpResponse> $responses
     */
    public function __construct(
        private array $responses,
    ) {
    }

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        foreach ($this->responses as $path => $response) {
            if (str_ends_with($url, $path)) {
                return $response;
            }
        }

        return new HttpResponse(404, '{"error":{"message":"No fake response configured."}}');
    }
}
