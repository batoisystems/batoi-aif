<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ProviderNotFoundException extends RuntimeException
{
    public static function forCode(string $providerCode): self
    {
        return new self(sprintf('AI provider not found: %s', $providerCode));
    }
}
