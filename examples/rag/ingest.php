<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Vector\InMemoryVectorStore;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));
$vectorStore = new InMemoryVectorStore();
$documents = [
    'ticket_1' => 'Printer is offline after browser update.',
    'ticket_2' => 'Customer needs invoice export in CSV format.',
    'ticket_3' => 'Workspace admin wants role permissions reviewed.',
];

foreach ($documents as $id => $content) {
    $embedding = $gateway->embed(new EmbeddingRequest(input: $content));
    $vectorStore->upsert(new VectorRecord(
        collection: 'support_knowledge',
        id: $id,
        vector: $embedding->embedding,
        content: $content,
        metadata: ['source' => 'example'],
    ));
}

echo json_encode([
    'collection' => 'support_knowledge',
    'stored_records' => count($vectorStore->all('support_knowledge')),
    'note' => 'This example uses an in-memory vector store. Use a persistent adapter in production.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

