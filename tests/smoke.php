<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $testPrefix = 'Batoi\\Aif\\Tests\\';

    if (str_starts_with($class, $testPrefix)) {
        $relative = substr($class, strlen($testPrefix));
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
        }

        return;
    }

    $prefix = 'Batoi\\Aif\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

use Batoi\Aif\Aif;
use Batoi\Aif\Api\AifApi;
use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Exception\PolicyDeniedException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Prompts\InMemoryPromptRegistry;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Rad\RadArrayContextResolver;
use Batoi\Aif\Rad\RadRunDataContextResolver;
use Batoi\Aif\Rad\RadRolePermissionChecker;
use Batoi\Aif\Tests\Fixtures\FakeHttpTransport;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\HttpResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ProviderCapability;
use Batoi\Aif\Value\PromptVersion;

if (Aif::name() !== 'Batoi AIF') {
    fwrite(STDERR, "Unexpected framework name.\n");
    exit(1);
}

if (Aif::VERSION !== '0.1.0') {
    fwrite(STDERR, "Unexpected framework version.\n");
    exit(1);
}

$autoloadCheck = trim((string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg(
    'require_once ' . var_export(dirname(__DIR__) . '/autoload.php', true) . '; echo Batoi\\Aif\\Aif::name();'
)));

if ($autoloadCheck !== 'Batoi AIF') {
    fwrite(STDERR, "Bundled autoloader check failed.\n");
    exit(1);
}

$request = new InferenceRequest(input: 'Hello');

if ($request->input !== 'Hello') {
    fwrite(STDERR, "Unexpected inference request input.\n");
    exit(1);
}

$capability = new ProviderCapability('mock', 'mock-text', ['text']);

if (!$capability->supports('text')) {
    fwrite(STDERR, "Unexpected provider capability result.\n");
    exit(1);
}

$registry = new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]);
$gateway = new AifGateway($registry);
$response = $gateway->infer($request);

if ($response->output !== 'Mock response: Hello') {
    fwrite(STDERR, "Unexpected gateway response.\n");
    exit(1);
}

$policyGateway = new AifGateway(
    providers: $registry,
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
);
$context = new ExecutionContext(userId: 'u_1', workspaceId: 'w_1', roles: ['member']);

try {
    $policyGateway->infer($request, $context);
    fwrite(STDERR, "Expected policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

$promptRegistry = new InMemoryPromptRegistry([
    new PromptVersion(
        code: 'summarize_ticket',
        version: '1.0.0',
        template: 'Summarize: {{ticket_body}}',
    ),
]);
$promptGateway = new AifGateway(
    providers: $registry,
    promptRegistry: $promptRegistry,
);
$promptResponse = $promptGateway->infer(new InferenceRequest(
    input: '',
    promptCode: 'summarize_ticket',
    promptVersion: '1.0.0',
    variables: ['ticket_body' => 'The printer is offline.'],
));

if ($promptResponse->output !== 'Mock response: Summarize: The printer is offline.') {
    fwrite(STDERR, "Unexpected prompt gateway response.\n");
    exit(1);
}

$auditLog = new InMemoryAuditLog();
$auditGateway = new AifGateway(
    providers: $registry,
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
    auditLog: $auditLog,
);
$auditGateway->infer($request, new ExecutionContext(userId: 'u_1', workspaceId: 'w_1', roles: ['admin']));

try {
    $auditGateway->infer($request, $context);
    fwrite(STDERR, "Expected audited policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

if (count($auditLog->all()) !== 2) {
    fwrite(STDERR, "Unexpected audit record count.\n");
    exit(1);
}

$openAiTransport = new FakeHttpTransport([
    '/chat/completions' => new HttpResponse(200, json_encode([
        'id' => 'chatcmpl_test',
        'model' => 'gpt-test',
        'choices' => [
            [
                'message' => ['content' => 'OpenAI-compatible response'],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 4],
    ], JSON_UNESCAPED_SLASHES)),
    '/embeddings' => new HttpResponse(200, json_encode([
        'model' => 'embed-test',
        'data' => [
            ['embedding' => [0.1, 0.2]],
        ],
        'usage' => ['prompt_tokens' => 2],
    ], JSON_UNESCAPED_SLASHES)),
    '/moderations' => new HttpResponse(200, json_encode([
        'results' => [
            [
                'flagged' => true,
                'categories' => ['violence' => true, 'hate' => false],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES)),
]);
$openAiProvider = new OpenAICompatibleProvider(
    apiKey: 'test-key',
    baseUrl: 'https://example.test/v1',
    transport: $openAiTransport,
);
$openAiResponse = $openAiProvider->generateText(new InferenceRequest(input: 'Hello provider'));

if ($openAiResponse->output !== 'OpenAI-compatible response') {
    fwrite(STDERR, "Unexpected OpenAI-compatible text response.\n");
    exit(1);
}

$embeddingResponse = $openAiProvider->generateEmbedding(new EmbeddingRequest(input: 'Hello embedding'));

if ($embeddingResponse->embedding !== [0.1, 0.2]) {
    fwrite(STDERR, "Unexpected OpenAI-compatible embedding response.\n");
    exit(1);
}

$moderationResponse = $openAiProvider->moderate(new ModerationRequest(input: 'Check this'));

if (!$moderationResponse->flagged || $moderationResponse->categories !== ['violence']) {
    fwrite(STDERR, "Unexpected OpenAI-compatible moderation response.\n");
    exit(1);
}

$radContext = (new RadArrayContextResolver())->resolve([
    'entity_id' => 10,
    'space_id' => 20,
    'roles' => ['workspace_admin'],
]);
$radPermissions = new RadRolePermissionChecker([
    'aif.infer' => ['workspace_admin'],
]);

if ($radContext->userId !== '10' || $radContext->workspaceId !== '20' || !$radPermissions->can($radContext, 'aif.infer')) {
    fwrite(STDERR, "Unexpected RAD context adapter behavior.\n");
    exit(1);
}

$api = new AifApi(new AifGateway($registry));
$apiResponse = $api->infer(['input' => 'Hello API']);

if (($apiResponse['ok'] ?? false) !== true || ($apiResponse['data']['output'] ?? '') !== 'Mock response: Hello API') {
    fwrite(STDERR, "Unexpected API facade response.\n");
    exit(1);
}

$apiEmbeddingResponse = $api->embed(['input' => 'Hello embedding API']);

if (($apiEmbeddingResponse['ok'] ?? false) !== true || ($apiEmbeddingResponse['data']['embedding'] ?? []) !== [0.1, 0.2, 0.3]) {
    fwrite(STDERR, "Unexpected API embedding response.\n");
    exit(1);
}

$apiModerationResponse = $api->moderate(['input' => 'Hello moderation API']);

if (($apiModerationResponse['ok'] ?? false) !== true || ($apiModerationResponse['data']['flagged'] ?? true) !== false) {
    fwrite(STDERR, "Unexpected API moderation response.\n");
    exit(1);
}

$radRunDataContext = (new RadRunDataContextResolver())->resolve([
    'session' => [
        'entity_id' => 30,
        'space_id' => 40,
        'roles' => ['owner'],
    ],
    'route' => [
        'id' => 50,
        'uri' => '/aif/infer',
    ],
]);

if ($radRunDataContext->userId !== '30' || $radRunDataContext->workspaceId !== '40' || $radRunDataContext->roles !== ['owner']) {
    fwrite(STDERR, "Unexpected RAD runData context adapter behavior.\n");
    exit(1);
}

echo "Smoke test passed.\n";
