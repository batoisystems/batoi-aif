<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

final readonly class RadSuggestionClient
{
    public function __construct(
        private RadAifService $service,
        private ?int $maxTokens = null,
    ) {
    }

    public function getSuggestion(string $prompt): string
    {
        $options = $this->maxTokens === null ? [] : ['max_tokens' => $this->maxTokens];
        return $this->service->completion($prompt, $options);
    }
}
