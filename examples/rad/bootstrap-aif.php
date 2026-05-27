<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../../vendor/batoi/aif/autoload.php',
    __DIR__ . '/../../../rad/vendor/batoi/aif/autoload.php',
    dirname(__DIR__, 2) . '/autoload.php',
];

foreach ($autoloadCandidates as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

if (!class_exists(Batoi\Aif\Gateway\AifGateway::class)) {
    fwrite(STDERR, "Batoi AIF autoload file was not found.\n");
    exit(1);
}

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

$api = new AifApi($gateway, new RadRunDataContextResolver());

$radRunData = [
    'session' => [
        'entity_id' => 1001,
        'space_id' => 10,
        'roles' => ['admin'],
    ],
    'route' => [
        'ms_id' => 20,
        'uri' => '/aif/infer',
    ],
];

echo json_encode($api->infer([
    'input' => 'Summarize the current RAD workspace activity.',
], $radRunData), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

