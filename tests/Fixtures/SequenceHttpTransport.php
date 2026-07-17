<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Value\HttpResponse;
use RuntimeException;

final class SequenceHttpTransport implements HttpTransportInterface
{
    public int $calls = 0;

    /** @param list<HttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        $response = $this->responses[$this->calls] ?? null;
        $this->calls++;

        return $response ?? throw new RuntimeException('No response remains in the test sequence.');
    }
}
