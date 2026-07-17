<?php

declare(strict_types=1);

namespace Batoi\Aif\Rag;

use Batoi\Aif\Contracts\VectorStoreInterface;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Value\Document;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\VectorRecord;
use InvalidArgumentException;

final readonly class RagIngestionService
{
    public function __construct(
        private AifGateway $gateway,
        private VectorStoreInterface $vectorStore,
        private TextChunker $chunker = new TextChunker(),
    ) {
    }

    /** @return list<string> chunk UIDs */
    public function ingest(string $collection, Document $document, ExecutionContext $context): array
    {
        if ($document->workspaceId !== $context->workspaceId) {
            throw new InvalidArgumentException('Document workspace must match execution context.');
        }

        $chunkUids = [];
        foreach ($this->chunker->chunk($document->content) as $index => $chunk) {
            $chunkUid = sprintf('%s_chunk_%d', $document->uid, $index);
            $embedding = $this->gateway->embed(
                new EmbeddingRequest($chunk, metadata: ['source_uid' => $document->uid]),
                context: $context,
            );
            $this->vectorStore->upsert(new VectorRecord(
                collection: $collection,
                id: $chunkUid,
                vector: $embedding->embedding,
                content: $chunk,
                metadata: [
                    'space_id' => $context->workspaceId,
                    'source_uid' => $document->uid,
                    'chunk_index' => $index,
                    'acl_visibility' => $document->metadata['acl_visibility'] ?? 'public',
                    'acl_roles' => $document->metadata['acl_roles'] ?? [],
                    'acl_user_ids' => $document->metadata['acl_user_ids'] ?? [],
                ] + $document->metadata,
            ));
            $chunkUids[] = $chunkUid;
        }

        return $chunkUids;
    }
}
