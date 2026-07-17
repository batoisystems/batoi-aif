<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Exception\CircuitBreakerOpenException;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Value\HttpResponse;

final class CircuitBreakingHttpTransport implements HttpTransportInterface
{
    private int $failures = 0;
    private float $openUntil = 0.0;

    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly string $circuit,
        private readonly CircuitBreakerPolicy $policy = new CircuitBreakerPolicy(),
    ) {
    }

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        if ($this->openUntil > microtime(true)) {
            throw new CircuitBreakerOpenException($this->circuit);
        }

        try {
            $response = $this->transport->postJson($url, $headers, $payload, $timeoutSeconds);
        } catch (ProviderRequestException $exception) {
            if ($exception->retryable) {
                $this->recordFailure();
            }
            throw $exception;
        }

        if ($response->statusCode === 408 || $response->statusCode === 429 || $response->statusCode >= 500) {
            $this->recordFailure();
        } else {
            $this->failures = 0;
            $this->openUntil = 0.0;
        }

        return $response;
    }

    private function recordFailure(): void
    {
        $this->failures++;
        if ($this->failures >= $this->policy->failureThreshold) {
            $this->openUntil = microtime(true) + $this->policy->cooldownSeconds;
        }
    }
}
