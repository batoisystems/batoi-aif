<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class StreamingNotSupportedException extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self(sprintf('Provider does not support incremental streaming: %s', $provider));
    }
}
