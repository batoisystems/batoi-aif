<?php

declare(strict_types=1);

namespace Batoi\Aif\Prompts;

use Batoi\Aif\Contracts\PromptRegistryInterface;
use Batoi\Aif\Exception\PromptNotFoundException;
use Batoi\Aif\Value\PromptVersion;

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
        krsort($versions, SORT_NATURAL);

        return reset($versions);
    }
}
