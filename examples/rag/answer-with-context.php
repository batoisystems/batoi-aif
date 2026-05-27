<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Vector\InMemoryVectorStore;

$auditLog = new InMemoryAuditLog();
$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
    auditLog: $auditLog,
);
$vectorStore = new InMemoryVectorStore();
$context = new ExecutionContext(userId: '1001', workspaceId: '10', roles: ['admin']);
$documents = [
    'ticket_1' => 'Printer is offline after browser update.',
    'ticket_2' => 'Customer needs invoice export in CSV format.',
    'ticket_3' => 'Workspace admin wants role permissions reviewed.',
];

foreach ($documents as $id => $content) {
    $embedding = $gateway->embed(new EmbeddingRequest(input: $content), context: $context);
    $vectorStore->upsert(new VectorRecord('support_knowledge', $id, $embedding->embedding, $content));
}

$question = 'How should we help with invoice export?';
$queryEmbedding = $gateway->embed(new EmbeddingRequest(input: $question), context: $context);
$matches = $vectorStore->search(new VectorSearchRequest('support_knowledge', $queryEmbedding->embedding, topK: 2));
$contextText = implode("\n", array_map(static fn ($match): string => '- ' . $match->record->content, $matches));

$answer = $gateway->infer(new InferenceRequest(
    input: "Use this context to answer:\n" . $contextText . "\n\nQuestion: " . $question,
    metadata: ['operation' => 'rag_answer'],
), $context);

echo json_encode([
    'answer' => $answer->output,
    'matches' => array_map(static fn ($match): array => [
        'id' => $match->record->id,
        'score' => $match->score,
    ], $matches),
    'audit_records' => count($auditLog->all()),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

