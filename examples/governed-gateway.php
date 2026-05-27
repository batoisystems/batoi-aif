<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Exception\PolicyDeniedException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;

$auditLog = new InMemoryAuditLog();
$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
    auditLog: $auditLog,
);

$request = new InferenceRequest(input: 'Draft a brief project status summary.');
$adminContext = new ExecutionContext(userId: '1001', workspaceId: '10', roles: ['admin']);
$memberContext = new ExecutionContext(userId: '1002', workspaceId: '10', roles: ['member']);

$allowed = $gateway->infer($request, $adminContext);
echo 'Allowed output: ' . $allowed->output . PHP_EOL;

try {
    $gateway->infer($request, $memberContext);
} catch (PolicyDeniedException $exception) {
    echo 'Denied output: ' . $exception->getMessage() . PHP_EOL;
}

foreach ($auditLog->all() as $record) {
    echo sprintf(
        "Audit %s status=%s provider=%s\n",
        $record->uid,
        $record->status,
        $record->provider ?? 'none',
    );
}

