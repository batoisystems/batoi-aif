<?php

declare(strict_types=1);

namespace Batoi\Aif\Prompts;

use Batoi\Aif\Exception\PromptNotApprovedException;
use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Value\PromptVersion;

final readonly class PromptRenderer
{
    /**
     * @param array<string, mixed> $variables
     */
    public function render(PromptVersion $prompt, array $variables): string
    {
        if (!$prompt->isApproved()) {
            throw PromptNotApprovedException::forPrompt($prompt->code, $prompt->version);
        }

        return preg_replace_callback(
            '/{{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*}}/',
            static function (array $matches) use ($variables): string {
                $name = $matches[1];

                if (!array_key_exists($name, $variables)) {
                    throw PromptRenderException::missingVariable($name);
                }

                return (string) $variables[$name];
            },
            $prompt->template,
        ) ?? $prompt->template;
    }
}
