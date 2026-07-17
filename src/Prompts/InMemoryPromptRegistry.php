<?php

declare(strict_types=1);

namespace Batoi\Aif\Prompts;

use Batoi\Aif\Contracts\PromptRegistryInterface;
use Batoi\Aif\Exception\PromptNotFoundException;
use Batoi\Aif\Value\PromptVersion;
use LogicException;

final class InMemoryPromptRegistry implements PromptRegistryInterface
{
    /**
     * @var array<string, array<string, PromptVersion>>
     */
    private array $prompts = [];

    /**
     * @param list<PromptVersion> $prompts
     */
    public function __construct(array $prompts = [])
    {
        foreach ($prompts as $prompt) {
            $this->register($prompt);
        }
    }

    public function register(PromptVersion $prompt): void
    {
        $existing = $this->prompts[$prompt->code][$prompt->version] ?? null;

        if ($existing !== null && $existing != $prompt) {
            throw new LogicException(sprintf('Prompt versions are immutable: %s@%s', $prompt->code, $prompt->version));
        }

        $this->prompts[$prompt->code][$prompt->version] = $prompt;
    }

    public function get(string $promptCode, ?string $version = null): PromptVersion
    {
        if (!isset($this->prompts[$promptCode])) {
            throw PromptNotFoundException::forPrompt($promptCode, $version);
        }

        if ($version !== null) {
            return $this->prompts[$promptCode][$version]
                ?? throw PromptNotFoundException::forPrompt($promptCode, $version);
        }

        $versions = $this->prompts[$promptCode];
        uksort($versions, static fn (string $left, string $right): int => version_compare($right, $left));

        return reset($versions);
    }
}
