<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class PromptNotApprovedException extends RuntimeException
{
    public static function forPrompt(string $promptCode, string $version): self
    {
        return new self(sprintf('Prompt is not approved: %s@%s', $promptCode, $version));
    }
}
