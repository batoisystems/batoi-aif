<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/batoi/aif/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

echo $gateway->infer(new InferenceRequest('Hello from a ZIP/drop-in install.'))->output . PHP_EOL;

