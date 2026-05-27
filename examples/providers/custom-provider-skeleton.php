<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Contracts\AIProviderInterface;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\StreamEvent;

final readonly class CustomProviderSkeleton implements AIProviderInterface
{
    public function generateText(InferenceRequest $request): InferenceResponse
    {
        return new InferenceResponse(
            output: 'Custom provider response: ' . $request->input,
            provider: 'custom',
            model: $request->model ?? 'custom-text',
            requestUid: 'custom_' . bin2hex(random_bytes(8)),
        );
    }

    public function stream(InferenceRequest $request): iterable
    {
        yield new StreamEvent('start');
        yield new StreamEvent('delta', $this->generateText($request)->output);
        yield new StreamEvent('done');
    }

    public function generateEmbedding(EmbeddingRequest $request): EmbeddingResponse
    {
        return new EmbeddingResponse([0.1, 0.2, 0.3], 'custom', $request->model ?? 'custom-embedding');
    }

    public function moderate(ModerationRequest $request): ModerationResponse
    {
        return new ModerationResponse(flagged: false);
    }

    public function healthCheck(): bool
    {
        return true;
    }
}

echo "CustomProviderSkeleton implements AIProviderInterface.\n";

