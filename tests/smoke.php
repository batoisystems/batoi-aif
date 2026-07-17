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
use Batoi\Aif\Exception\GovernanceConfigurationException;
use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Exception\ReviewRequiredException;
use Batoi\Aif\Exception\StreamingNotSupportedException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Policy\PolicyAction;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Prompts\InMemoryPromptRegistry;
use Batoi\Aif\Prompts\PromptRenderer;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Queue\InMemoryQueueAdapter;
use Batoi\Aif\Review\InMemoryReviewRepository;
use Batoi\Aif\Rad\RadArrayContextResolver;
use Batoi\Aif\Rad\RadRunDataContextResolver;
use Batoi\Aif\Rad\RadRolePermissionChecker;
use Batoi\Aif\Tests\Fixtures\FakeHttpTransport;
use Batoi\Aif\Tests\Fixtures\ConfigurablePolicyEngine;
use Batoi\Aif\Tests\Fixtures\RecordingProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\HttpResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\ProviderCapability;
use Batoi\Aif\Value\PromptVersion;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Vector\InMemoryVectorStore;

if (Aif::name() !== 'Batoi AIF') {
    fwrite(STDERR, "Unexpected framework name.\n");
    exit(1);
}

if (Aif::VERSION !== '1.0.1') {
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

$providerPolicyGateway = new AifGateway(
    providers: $registry,
    policyEngine: new StaticPolicyEngine(allowedProviders: ['openai']),
);

try {
    $providerPolicyGateway->infer($request, new ExecutionContext(userId: 'u_1', workspaceId: 'w_1', roles: ['admin']));
    fwrite(STDERR, "Expected default provider policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

try {
    $policyGateway->embed(new EmbeddingRequest(input: 'Denied embedding'), context: $context);
    fwrite(STDERR, "Expected embedding policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

try {
    $policyGateway->moderate(new ModerationRequest(input: 'Denied moderation'), context: $context);
    fwrite(STDERR, "Expected moderation policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

try {
    foreach ($policyGateway->stream(new InferenceRequest(input: 'Denied stream'), $context) as $event) {
    }

    fwrite(STDERR, "Expected stream policy denial.\n");
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

$operationAuditLog = new InMemoryAuditLog();
$operationAuditGateway = new AifGateway(
    providers: $registry,
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
    auditLog: $operationAuditLog,
);
$adminContext = new ExecutionContext(userId: 'u_1', workspaceId: 'w_1', roles: ['admin']);
$operationAuditGateway->embed(new EmbeddingRequest(input: 'Allowed embedding'), context: $adminContext);

try {
    $operationAuditGateway->moderate(new ModerationRequest(input: 'Denied moderation'), context: $context);
    fwrite(STDERR, "Expected audited moderation policy denial.\n");
    exit(1);
} catch (PolicyDeniedException) {
}

$operationAuditRecords = $operationAuditLog->all();

if (count($operationAuditRecords) !== 2 || $operationAuditRecords[1]->status !== 'denied') {
    fwrite(STDERR, "Unexpected operation audit behavior.\n");
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

$queue = new InMemoryQueueAdapter();
$jobId = $queue->dispatch('aif.embedding.create', [
    'request_uid' => 'req_1',
    'idempotency_key' => 'idem_1',
]);

if (($queue->get($jobId)['status'] ?? '') !== 'queued') {
    fwrite(STDERR, "Unexpected in-memory queue dispatch behavior.\n");
    exit(1);
}

$queue->acknowledge($jobId);

if (($queue->get($jobId)['status'] ?? '') !== 'acknowledged') {
    fwrite(STDERR, "Unexpected in-memory queue acknowledge behavior.\n");
    exit(1);
}

$failedJobId = $queue->dispatch('aif.eval.run', ['request_uid' => 'req_2']);
$queue->fail($failedJobId, 'Evaluator unavailable.', ['retryable' => true]);

if (($queue->get($failedJobId)['failure_metadata']['retryable'] ?? false) !== true) {
    fwrite(STDERR, "Unexpected in-memory queue failure behavior.\n");
    exit(1);
}

$vectorStore = new InMemoryVectorStore();
$vectorStore->upsert(new VectorRecord(
    collection: 'tickets',
    id: 'doc_1',
    vector: [1.0, 0.0],
    content: 'Printer is offline.',
    metadata: ['space_id' => '10', 'source_uid' => 'ticket_1'],
));
$vectorStore->upsert(new VectorRecord(
    collection: 'tickets',
    id: 'doc_2',
    vector: [0.0, 1.0],
    content: 'Billing account needs review.',
    metadata: ['space_id' => '20', 'source_uid' => 'ticket_2'],
));

$vectorResults = $vectorStore->search(new VectorSearchRequest(
    collection: 'tickets',
    vector: [0.9, 0.1],
    topK: 1,
    minScore: 0.0,
    filters: ['space_id' => '10'],
));

if (count($vectorResults) !== 1 || $vectorResults[0]->record->id !== 'doc_1') {
    fwrite(STDERR, "Unexpected in-memory vector search behavior.\n");
    exit(1);
}

$vectorStore->delete('tickets', 'doc_1');
$deletedVectorResults = $vectorStore->search(new VectorSearchRequest(
    collection: 'tickets',
    vector: [1.0, 0.0],
    filters: ['space_id' => '10'],
));

if ($deletedVectorResults !== []) {
    fwrite(STDERR, "Unexpected in-memory vector delete behavior.\n");
    exit(1);
}

$governedContext = new ExecutionContext('governed_user', 'governed_space', ['admin'], 'trace_1');
$recordingProvider = new RecordingProvider();
$recordingRegistry = new InMemoryProviderRegistry(['recording' => $recordingProvider]);
$missingContextAudit = new InMemoryAuditLog();
$missingContextGateway = new AifGateway(
    providers: $recordingRegistry,
    defaultProvider: 'recording',
    policyEngine: new StaticPolicyEngine(),
    auditLog: $missingContextAudit,
    runtimeMode: RuntimeMode::Governed,
);

try {
    $missingContextGateway->infer(new InferenceRequest('Must not execute'));
    fwrite(STDERR, "Expected governed mode to require context.\n");
    exit(1);
} catch (GovernanceConfigurationException) {
}

if ($recordingProvider->calls !== [] || count($missingContextAudit->all()) !== 1) {
    fwrite(STDERR, "Governed dependency failure was not fail-closed and audited.\n");
    exit(1);
}

$promptFailureAudit = new InMemoryAuditLog();
$promptFailureGateway = new AifGateway(
    providers: $recordingRegistry,
    defaultProvider: 'recording',
    policyEngine: new StaticPolicyEngine(),
    promptRegistry: new InMemoryPromptRegistry([
        new PromptVersion('needs_value', '1.0.0', 'Value: {{value}}'),
    ]),
    auditLog: $promptFailureAudit,
    runtimeMode: RuntimeMode::Governed,
);

try {
    $promptFailureGateway->infer(new InferenceRequest('', promptCode: 'needs_value'), $governedContext);
    fwrite(STDERR, "Expected prompt rendering failure.\n");
    exit(1);
} catch (PromptRenderException) {
}

if (count($promptFailureAudit->all()) !== 1 || $recordingProvider->calls !== []) {
    fwrite(STDERR, "Prompt failure did not create exactly one pre-provider audit record.\n");
    exit(1);
}

$redactionPolicy = new ConfigurablePolicyEngine(new PolicyDecision(
    PolicyAction::RedactAndContinue,
    ['pii_redacted'],
    obligations: ['redacted_input' => 'Customer: [REDACTED]'],
));
$redactionAudit = new InMemoryAuditLog();
$redactionGateway = new AifGateway(
    providers: $recordingRegistry,
    defaultProvider: 'recording',
    policyEngine: $redactionPolicy,
    auditLog: $redactionAudit,
    runtimeMode: RuntimeMode::Governed,
);
$redactionGateway->infer(new InferenceRequest('Customer: secret@example.test'), $governedContext);

if (($recordingProvider->calls[0]['input'] ?? '') !== 'Customer: [REDACTED]'
    || ($redactionAudit->all()[0]->metadata['policy_redacted'] ?? false) !== true
    || $redactionAudit->all()[0]->traceUid !== 'trace_1') {
    fwrite(STDERR, "Redaction obligation or audit correlation failed.\n");
    exit(1);
}

$callsBeforeReview = count($recordingProvider->calls);
$reviewRepository = new InMemoryReviewRepository();
$reviewAudit = new InMemoryAuditLog();
$reviewPolicy = new ConfigurablePolicyEngine(new PolicyDecision(PolicyAction::RequiresReview, ['high_risk']));
$reviewGateway = new AifGateway(
    providers: $recordingRegistry,
    defaultProvider: 'recording',
    policyEngine: $reviewPolicy,
    auditLog: $reviewAudit,
    runtimeMode: RuntimeMode::Governed,
    reviewRepository: $reviewRepository,
);

try {
    $reviewGateway->embed(new EmbeddingRequest('Review this'), context: $governedContext);
    fwrite(STDERR, "Expected review-required execution to pause.\n");
    exit(1);
} catch (ReviewRequiredException $exception) {
    if ($exception->reviewUid === '') {
        fwrite(STDERR, "Review UID was not returned.\n");
        exit(1);
    }
}

if (count($recordingProvider->calls) !== $callsBeforeReview
    || count($reviewRepository->all()) !== 1
    || count($reviewAudit->all()) !== 1
    || $reviewAudit->all()[0]->status !== 'review_required'
    || ($reviewPolicy->subjects[0]->operation ?? null) !== ExecutionOperation::Embed) {
    fwrite(STDERR, "Review pause, operation-aware policy, or audit behavior failed.\n");
    exit(1);
}

$invalidApiResponse = (new AifApi(new AifGateway($recordingRegistry, defaultProvider: 'recording')))->infer([]);

if (($invalidApiResponse['error']['code'] ?? '') !== 'invalid_request'
    || ($invalidApiResponse['error']['http_status'] ?? 0) !== 422
    || str_contains((string) ($invalidApiResponse['error']['message'] ?? ''), 'Exception')) {
    fwrite(STDERR, "Stable API validation error mapping failed.\n");
    exit(1);
}

$versionedPrompts = new InMemoryPromptRegistry([
    new PromptVersion('semantic_order', '1.9.0', 'old'),
    new PromptVersion('semantic_order', '1.10.0', 'new'),
]);

if ($versionedPrompts->get('semantic_order')->version !== '1.10.0') {
    fwrite(STDERR, "Semantic prompt version selection failed.\n");
    exit(1);
}

$schemaPrompt = new PromptVersion(
    code: 'schema_prompt',
    version: '1.0.0',
    template: 'Count: {{count}}',
    inputSchema: [
        'required' => ['count'],
        'properties' => ['count' => ['type' => 'integer']],
        'additionalProperties' => false,
    ],
);

try {
    (new PromptRenderer())->render($schemaPrompt, ['count' => 'not-an-integer']);
    fwrite(STDERR, "Expected prompt schema validation failure.\n");
    exit(1);
} catch (PromptRenderException) {
}

if (!$openAiProvider->capabilities()[0]->supports('text') || $openAiProvider->capabilities()[0]->supports('stream')) {
    fwrite(STDERR, "Provider capability reporting failed.\n");
    exit(1);
}

try {
    foreach ($openAiProvider->stream(new InferenceRequest('Do not buffer')) as $event) {
    }
    fwrite(STDERR, "Expected unsupported incremental streaming failure.\n");
    exit(1);
} catch (StreamingNotSupportedException) {
}

echo "Smoke test passed.\n";
