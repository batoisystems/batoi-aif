<?php

declare(strict_types=1);

namespace Batoi\Aif\Providers;

use Batoi\Aif\Contracts\AIProviderInterface;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\StreamEvent;

final readonly class MockProvider implements AIProviderInterface
{
    public function __construct(
        private string $providerCode = 'mock',
        private string $modelCode = 'mock-text',
    ) {
    }

    public function generateText(InferenceRequest $request): InferenceResponse
    {
        return new InferenceResponse(
            output: sprintf('Mock response: %s', $request->input),
            provider: $this->providerCode,
            model: $request->model ?? $this->modelCode,
            requestUid: $this->requestUid(),
            usage: [
                'input_chars' => strlen($request->input),
                'output_chars' => strlen($request->input) + 15,
            ],
        );
    }

    public function stream(InferenceRequest $request): iterable
    {
        yield new StreamEvent('start', metadata: ['provider' => $this->providerCode]);
        yield new StreamEvent('delta', sprintf('Mock response: %s', $request->input));
        yield new StreamEvent('done');
    }

    public function generateEmbedding(EmbeddingRequest $request): EmbeddingResponse
    {
        return new EmbeddingResponse(
            embedding: [0.1, 0.2, 0.3],
            provider: $this->providerCode,
            model: $request->model ?? 'mock-embedding',
            usage: ['input_chars' => strlen($request->input)],
        );
    }

    public function moderate(ModerationRequest $request): ModerationResponse
    {
        return new ModerationResponse(flagged: false);
    }

    public function healthCheck(): bool
    {
        return true;
    }

    private function requestUid(): string
    {
        return sprintf('mock_%s', bin2hex(random_bytes(8)));
    }
}
