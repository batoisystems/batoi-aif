<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\HttpResponse;

interface CancellableHttpTransportInterface extends HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    public function postJsonCancellable(
        string $url,
        array $headers,
        array $payload,
        CancellationTokenInterface $cancellation,
        int $timeoutSeconds = 30,
    ): HttpResponse;
}
