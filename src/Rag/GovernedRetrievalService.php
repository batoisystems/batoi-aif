<?php

declare(strict_types=1);

namespace Batoi\Aif\Rag;

use Batoi\Aif\Contracts\VectorStoreInterface;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Value\Citation;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Value\VectorSearchResult;

final readonly class GovernedRetrievalService
{
    public function __construct(private AifGateway $gateway, private VectorStoreInterface $vectorStore)
    {
    }

    /** @return list<Citation> */
    public function search(
        string $collection,
        string $query,
        ExecutionContext $context,
        int $topK = 5,
        float $minScore = -1.0,
    ): array {
        $embedding = $this->gateway->embed(new EmbeddingRequest($query), context: $context);
        $results = $this->gateway->retrieve(
            new VectorSearchRequest($collection, $embedding->embedding, $topK, $minScore),
            $this->vectorStore,
            $context,
        );

        return array_map(
            static fn (VectorSearchResult $result): Citation => new Citation(
                sourceUid: (string) ($result->record->metadata['source_uid'] ?? $result->record->id),
                chunkUid: $result->record->id,
                content: $result->record->content,
                score: $result->score,
                metadata: $result->record->metadata,
            ),
            $results,
        );
    }
}
