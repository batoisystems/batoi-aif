<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ProviderRequestException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly int $statusCode,
        public readonly bool $retryable,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function failed(string $provider, int $statusCode, string $message): self
    {
        $message = preg_replace('/\bsk-[A-Za-z0-9_-]+\b/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $message) ?? $message;
        $message = substr(trim($message), 0, 400);

        return new self(
            provider: $provider,
            statusCode: $statusCode,
            retryable: $statusCode === 0 || $statusCode === 408 || $statusCode === 429 || $statusCode >= 500,
            message: sprintf('%s request failed with HTTP %d: %s', $provider, $statusCode, $message),
        );
    }
}
