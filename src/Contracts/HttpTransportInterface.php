<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\HttpResponse;

interface HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse;
}
