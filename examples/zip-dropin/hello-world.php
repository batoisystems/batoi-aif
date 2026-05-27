<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

$response = $gateway->infer(new InferenceRequest(
    input: 'This example works from a GitHub ZIP/drop-in install.',
));

echo $response->output . PHP_EOL;

