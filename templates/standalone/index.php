<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

echo $gateway->infer(new InferenceRequest('Hello from Batoi AIF.'))->output . PHP_EOL;

