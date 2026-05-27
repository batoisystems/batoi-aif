<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

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
$vectors = new InMemoryVectorStore();

$documentEmbedding = $gateway->embed(new EmbeddingRequest('Document text.'));
$vectors->upsert(new VectorRecord('docs', 'doc_1', $documentEmbedding->embedding, 'Document text.'));

$queryEmbedding = $gateway->embed(new EmbeddingRequest('Question text.'));
$matches = $vectors->search(new VectorSearchRequest('docs', $queryEmbedding->embedding));

echo 'Matches: ' . count($matches) . PHP_EOL;

