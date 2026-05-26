<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\StreamEvent;

interface AIProviderInterface
{
    public function generateText(InferenceRequest $request): InferenceResponse;

    /**
     * @return iterable<StreamEvent>
     */
    public function stream(InferenceRequest $request): iterable;

    public function generateEmbedding(EmbeddingRequest $request): EmbeddingResponse;

    public function moderate(ModerationRequest $request): ModerationResponse;

    public function healthCheck(): bool;
}
