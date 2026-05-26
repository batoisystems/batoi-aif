<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class PromptNotFoundException extends RuntimeException
{
    public static function forPrompt(string $promptCode, ?string $version = null): self
    {
        $suffix = $version === null ? '' : sprintf('@%s', $version);

        return new self(sprintf('Prompt not found: %s%s', $promptCode, $suffix));
    }
}
