<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class PromptRenderException extends RuntimeException
{
    public static function missingVariable(string $variable): self
    {
        return new self(sprintf('Missing prompt variable: %s', $variable));
    }
}
