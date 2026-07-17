<?php

declare(strict_types=1);

namespace Batoi\Aif\Prompts;

use Batoi\Aif\Exception\PromptNotApprovedException;
use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Value\PromptVersion;
use Stringable;

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

        $this->validateInputSchema($prompt, $variables);

        return preg_replace_callback(
            '/{{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*}}/',
            static function (array $matches) use ($variables): string {
                $name = $matches[1];

                if (!array_key_exists($name, $variables)) {
                    throw PromptRenderException::missingVariable($name);
                }

                $value = $variables[$name];

                if (!is_scalar($value) && !$value instanceof Stringable && $value !== null) {
                    throw new PromptRenderException(sprintf('Prompt variable must be scalar: %s', $name));
                }

                return (string) $value;
            },
            $prompt->template,
        ) ?? $prompt->template;
    }

    /** @param array<string, mixed> $variables */
    private function validateInputSchema(PromptVersion $prompt, array $variables): void
    {
        if ($prompt->inputSchema === []) {
            return;
        }

        $required = $prompt->inputSchema['required'] ?? [];
        if (!is_array($required)) {
            throw new PromptRenderException('Prompt input schema field "required" must be an array.');
        }

        foreach ($required as $name) {
            if (!is_string($name) || !array_key_exists($name, $variables)) {
                throw PromptRenderException::missingVariable((string) $name);
            }
        }

        $properties = $prompt->inputSchema['properties'] ?? [];
        if (!is_array($properties)) {
            throw new PromptRenderException('Prompt input schema field "properties" must be an object.');
        }

        foreach ($properties as $name => $definition) {
            if (!array_key_exists($name, $variables) || !is_array($definition) || !isset($definition['type'])) {
                continue;
            }

            if (!$this->matchesType($variables[$name], (string) $definition['type'])) {
                throw new PromptRenderException(sprintf('Prompt variable "%s" must be %s.', $name, $definition['type']));
            }
        }

        if (($prompt->inputSchema['additionalProperties'] ?? true) === false) {
            $unknown = array_diff(array_keys($variables), array_keys($properties));

            if ($unknown !== []) {
                throw new PromptRenderException(sprintf('Unknown prompt variable: %s', (string) reset($unknown)));
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            'null' => $value === null,
            default => throw new PromptRenderException(sprintf('Unsupported prompt schema type: %s', $type)),
        };
    }
}
