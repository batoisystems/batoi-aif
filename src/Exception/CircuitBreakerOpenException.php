<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(public readonly string $circuit)
    {
        parent::__construct(sprintf('Circuit breaker is open: %s', $circuit));
    }
}
