<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/batoi/aif/autoload.php';

use Batoi\Aif\Api\AifApi;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Rad\RadRunDataContextResolver;

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    policyEngine: new StaticPolicyEngine(allowedRoles: ['admin']),
);

return new AifApi($gateway, new RadRunDataContextResolver());

