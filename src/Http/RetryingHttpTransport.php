<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Contracts\SleeperInterface;
use Batoi\Aif\Contracts\MetricsCollectorInterface;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Value\HttpResponse;
use Batoi\Aif\Value\MetricEvent;

final readonly class RetryingHttpTransport implements HttpTransportInterface
{
    public function __construct(
        private HttpTransportInterface $transport,
        private RetryPolicy $policy = new RetryPolicy(),
        private SleeperInterface $sleeper = new SystemSleeper(),
        private ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        $attempt = 0;

        do {
            $attempt++;
            try {
                $response = $this->transport->postJson($url, $headers, $payload, $timeoutSeconds);
            } catch (ProviderRequestException $exception) {
                if (!$exception->retryable || $attempt >= $this->policy->maxAttempts) {
                    throw $exception;
                }

                $this->sleeper->sleepMilliseconds($this->policy->delayMilliseconds($attempt));
                $this->recordRetry($attempt, $exception->statusCode);
                continue;
            }

            if (!$this->retryableStatus($response->statusCode) || $attempt >= $this->policy->maxAttempts) {
                return $response;
            }

            $this->sleeper->sleepMilliseconds($this->policy->delayMilliseconds(
                $attempt,
                $response->headers['retry-after'] ?? null,
            ));
            $this->recordRetry($attempt, $response->statusCode);
        } while (true);
    }

    private function retryableStatus(int $statusCode): bool
    {
        return $statusCode === 408 || $statusCode === 429 || $statusCode >= 500;
    }

    private function recordRetry(int $attempt, int $statusCode): void
    {
        $this->metrics?->record(new MetricEvent(
            name: 'aif.http.retry',
            tags: [
                'attempt' => (string) $attempt,
                'status_code' => (string) $statusCode,
            ],
            occurredAt: gmdate(DATE_ATOM),
        ));
    }
}
