<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Queue\InMemoryQueueAdapter;
use Batoi\Aif\Value\InferenceRequest;

$queue = new InMemoryQueueAdapter();
$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

$jobId = $queue->dispatch('aif.eval.run', [
    'input' => 'Summarize the unpaid invoice risk for this account.',
    'expected_terms' => ['invoice', 'risk'],
]);

$job = $queue->get($jobId);
$payload = $job['payload'];

try {
    $response = $gateway->infer(new InferenceRequest(input: (string) $payload['input']));
    $missing = [];

    foreach ((array) $payload['expected_terms'] as $term) {
        if (!str_contains(strtolower($response->output), strtolower((string) $term))) {
            $missing[] = (string) $term;
        }
    }

    $queue->acknowledge($jobId);

    echo json_encode([
        'job' => $queue->get($jobId),
        'output' => $response->output,
        'passed' => $missing === [],
        'missing_terms' => $missing,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    $queue->fail($jobId, $exception->getMessage(), ['class' => $exception::class]);

    echo json_encode([
        'job' => $queue->get($jobId),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

