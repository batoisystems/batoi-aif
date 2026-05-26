<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ProviderRequestException extends RuntimeException
{
    public static function failed(string $provider, int $statusCode, string $message): self
    {
        return new self(sprintf('%s request failed with HTTP %d: %s', $provider, $statusCode, $message));
    }
}
