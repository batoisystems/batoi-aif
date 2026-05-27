<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
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
    $vectorStore->upsert(new VectorRecord('support_knowledge', $id, $embedding->embedding, $content));
}

$queryEmbedding = $gateway->embed(new EmbeddingRequest(input: 'invoice export problem'));
$matches = $vectorStore->search(new VectorSearchRequest(
    collection: 'support_knowledge',
    vector: $queryEmbedding->embedding,
    topK: 2,
));

echo json_encode(array_map(static fn ($match): array => [
    'id' => $match->record->id,
    'score' => $match->score,
    'content' => $match->record->content,
], $matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

