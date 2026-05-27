<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';

use Batoi\Aif\Api\AifApi;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Rad\RadArrayContextResolver;

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
);
$api = new AifApi($gateway, new RadArrayContextResolver());

$envelope = $api->infer(
    payload: [
        'input' => 'Create a one-line account note.',
    ],
    contextSource: [
        'entity_id' => 1001,
        'space_id' => 10,
        'roles' => ['admin'],
    ],
);

echo json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

