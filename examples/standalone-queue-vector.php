<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use Batoi\Aif\Queue\InMemoryQueueAdapter;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Vector\InMemoryVectorStore;

$queue = new InMemoryQueueAdapter();
$jobId = $queue->dispatch('aif.embedding.create', [
    'request_uid' => 'req_example_1',
    'idempotency_key' => 'embed_req_example_1',
    'space_id' => 'demo',
]);
$queue->acknowledge($jobId);

$store = new InMemoryVectorStore();
$store->upsert(new VectorRecord(
    collection: 'support',
    id: 'ticket_1_chunk_1',
    vector: [1.0, 0.0],
    content: 'Printer is offline.',
    metadata: ['space_id' => 'demo', 'source_uid' => 'ticket_1'],
));
$store->upsert(new VectorRecord(
    collection: 'support',
    id: 'ticket_2_chunk_1',
    vector: [0.0, 1.0],
    content: 'Billing account needs review.',
    metadata: ['space_id' => 'demo', 'source_uid' => 'ticket_2'],
));

$results = $store->search(new VectorSearchRequest(
    collection: 'support',
    vector: [0.9, 0.1],
    topK: 1,
    filters: ['space_id' => 'demo'],
));

echo json_encode([
    'queued_job' => $queue->get($jobId),
    'top_vector_match' => [
        'id' => $results[0]->record->id ?? null,
        'score' => $results[0]->score ?? null,
        'content' => $results[0]->record->content ?? null,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
