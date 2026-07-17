<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use InvalidArgumentException;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMs = 100,
        public int $maxDelayMs = 2000,
    ) {
        if ($this->maxAttempts < 1 || $this->baseDelayMs < 0 || $this->maxDelayMs < $this->baseDelayMs) {
            throw new InvalidArgumentException('Retry attempts and delay bounds are invalid.');
        }
    }

    public function delayMilliseconds(int $attempt, ?string $retryAfter = null): int
    {
        if ($retryAfter !== null && ctype_digit($retryAfter)) {
            return min($this->maxDelayMs, (int) $retryAfter * 1000);
        }

        return min($this->maxDelayMs, $this->baseDelayMs * (2 ** max(0, $attempt - 1)));
    }
}
