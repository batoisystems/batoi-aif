<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use InvalidArgumentException;

final readonly class CircuitBreakerPolicy
{
    public function __construct(
        public int $failureThreshold = 5,
        public int $cooldownSeconds = 30,
    ) {
        if ($this->failureThreshold < 1 || $this->cooldownSeconds < 1) {
            throw new InvalidArgumentException('Circuit breaker threshold and cooldown must be positive.');
        }
    }
}
