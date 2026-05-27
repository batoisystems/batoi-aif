<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\ModerationRequest;

$provider = 'mock';
$mode = 'infer';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--provider=')) {
        $provider = substr($arg, strlen('--provider='));
    }

    if (in_array($arg, ['--infer', '--embed', '--moderate', '--stream'], true)) {
        $mode = substr($arg, 2);
    }
}

$providers = [
    'mock' => new MockProvider(),
];

if ($provider === 'openai-compatible') {
    $apiKey = getenv('AIF_OPENAI_API_KEY') ?: '';

    if ($apiKey === '') {
        fwrite(STDERR, "Set AIF_OPENAI_API_KEY before using --provider=openai-compatible.\n");
        exit(1);
    }

    $providers[$provider] = new OpenAICompatibleProvider(
        apiKey: $apiKey,
        baseUrl: getenv('AIF_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
        providerCode: $provider,
    );
}

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry($providers),
    defaultProvider: $provider,
);

switch ($mode) {
    case 'embed':
        $response = $gateway->embed(new EmbeddingRequest(input: 'Batoi AIF smoke test.'));
        echo json_encode([
            'ok' => true,
            'mode' => $mode,
            'provider' => $response->provider,
            'model' => $response->model,
            'dimensions' => count($response->embedding),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        break;

    case 'moderate':
        $response = $gateway->moderate(new ModerationRequest(input: 'Batoi AIF smoke test.'));
        echo json_encode([
            'ok' => true,
            'mode' => $mode,
            'flagged' => $response->flagged,
            'categories' => $response->categories,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        break;

    case 'stream':
        foreach ($gateway->stream(new InferenceRequest(input: 'Batoi AIF smoke test.')) as $event) {
            echo json_encode([
                'type' => $event->type,
                'content' => $event->content,
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
        break;

    default:
        $response = $gateway->infer(new InferenceRequest(input: 'Batoi AIF smoke test.'));
        echo json_encode([
            'ok' => true,
            'mode' => $mode,
            'provider' => $response->provider,
            'model' => $response->model,
            'output' => $response->output,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

