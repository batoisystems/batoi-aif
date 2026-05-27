<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Queue\InMemoryQueueAdapter;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Vector\InMemoryVectorStore;

$queue = new InMemoryQueueAdapter();
$vectorStore = new InMemoryVectorStore();
$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

$jobId = $queue->dispatch('aif.embedding.create', [
    'collection' => 'support_tickets',
    'record_id' => 'ticket_1001_chunk_1',
    'content' => 'Customer cannot print invoices after updating the browser.',
    'metadata' => [
        'space_id' => '10',
        'source' => 'ticket',
    ],
]);

$job = $queue->get($jobId);
$payload = $job['payload'];

try {
    $embedding = $gateway->embed(new EmbeddingRequest(input: (string) $payload['content']));
    $vectorStore->upsert(new VectorRecord(
        collection: (string) $payload['collection'],
        id: (string) $payload['record_id'],
        vector: $embedding->embedding,
        content: (string) $payload['content'],
        metadata: is_array($payload['metadata']) ? $payload['metadata'] : [],
    ));
    $queue->acknowledge($jobId);
} catch (Throwable $exception) {
    $queue->fail($jobId, $exception->getMessage(), ['class' => $exception::class]);
}

echo json_encode([
    'job' => $queue->get($jobId),
    'stored_vectors' => count($vectorStore->all('support_tickets')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

