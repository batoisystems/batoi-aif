<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\EmbeddingRequest;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

$embedding = $gateway->embed(new EmbeddingRequest('Worker payload text.'));

echo 'Embedding dimensions: ' . count($embedding->embedding) . PHP_EOL;

