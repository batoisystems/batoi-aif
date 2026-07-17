<?php

declare(strict_types=1);

namespace Batoi\Aif\Rag;

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Value\Citation;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\RagGenerationResult;

final readonly class GovernedRagService
{
    public function __construct(
        private AifGateway $gateway,
        private GovernedRetrievalService $retrieval,
    ) {
    }

    public function answer(
        string $collection,
        string $query,
        ExecutionContext $context,
        int $topK = 5,
    ): RagGenerationResult {
        $citations = $this->retrieval->search($collection, $query, $context, $topK);
        $evidence = array_map(
            static fn (Citation $citation): array => [
                'source_uid' => $citation->sourceUid,
                'chunk_uid' => $citation->chunkUid,
                'score' => $citation->score,
            ],
            $citations,
        );
        $contextText = implode("\n\n", array_map(
            static fn (Citation $citation): string => sprintf(
                '[%s:%s] %s',
                $citation->sourceUid,
                $citation->chunkUid,
                $citation->content,
            ),
            $citations,
        ));
        $request = new InferenceRequest(
            input: sprintf("Use only the governed context below.\n\n%s\n\nQuestion: %s", $contextText, $query),
            metadata: ['retrieval_evidence' => $evidence, 'retrieval_collection' => $collection],
        );

        return new RagGenerationResult($this->gateway->infer($request, $context), $citations);
    }
}
