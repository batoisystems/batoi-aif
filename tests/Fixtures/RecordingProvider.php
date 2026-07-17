<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\CapabilityAwareProviderInterface;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\ProviderCapability;
use Batoi\Aif\Value\StreamEvent;

final class RecordingProvider implements CapabilityAwareProviderInterface
{
    /** @var list<array{operation: string, input: string}> */
    public array $calls = [];

    public function generateText(InferenceRequest $request): InferenceResponse
    {
        $this->calls[] = ['operation' => 'infer', 'input' => $request->input];

        return new InferenceResponse($request->input, 'recording', $request->model ?? 'recording-text', 'recording_request');
    }

    public function stream(InferenceRequest $request): iterable
    {
        $this->calls[] = ['operation' => 'stream', 'input' => $request->input];
        yield new StreamEvent('delta', $request->input);
    }

    public function generateEmbedding(EmbeddingRequest $request): EmbeddingResponse
    {
        $this->calls[] = ['operation' => 'embed', 'input' => $request->input];

        return new EmbeddingResponse([0.1], 'recording', $request->model ?? 'recording-embedding');
    }

    public function moderate(ModerationRequest $request): ModerationResponse
    {
        $this->calls[] = ['operation' => 'moderate', 'input' => $request->input];

        return new ModerationResponse(false);
    }

    public function healthCheck(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return [new ProviderCapability('recording', 'recording-all', ['text', 'stream', 'embedding', 'moderation'])];
    }
}
