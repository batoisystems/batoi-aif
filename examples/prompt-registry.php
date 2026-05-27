<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Prompts\InMemoryPromptRegistry;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PromptVersion;

$promptRegistry = new InMemoryPromptRegistry([
    new PromptVersion(
        code: 'ticket_summary',
        version: '1.0.0',
        template: 'Summarize ticket {{ticket_id}}: {{ticket_body}}',
    ),
]);

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    promptRegistry: $promptRegistry,
);

$response = $gateway->infer(new InferenceRequest(
    input: '',
    promptCode: 'ticket_summary',
    promptVersion: '1.0.0',
    variables: [
        'ticket_id' => 'T-1001',
        'ticket_body' => 'Customer cannot print invoices after updating the browser.',
    ],
));

echo $response->output . PHP_EOL;

