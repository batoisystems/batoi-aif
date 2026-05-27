<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Value\InferenceRequest;

$apiKey = getenv('AIF_OPENAI_API_KEY') ?: '';
$baseUrl = getenv('AIF_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1';

if ($apiKey === '') {
    fwrite(STDERR, "Set AIF_OPENAI_API_KEY before running this example.\n");
    exit(1);
}

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'openai' => new OpenAICompatibleProvider(apiKey: $apiKey, baseUrl: $baseUrl),
    ]),
    defaultProvider: 'openai',
);

$response = $gateway->infer(new InferenceRequest(
    input: 'Write a one-sentence description of governed AI execution.',
));

echo $response->output . PHP_EOL;

