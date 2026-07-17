# Batoi AIF

[![CI](https://github.com/batoisystems/batoi-aif/actions/workflows/ci.yml/badge.svg)](https://github.com/batoisystems/batoi-aif/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg)](https://www.php.net/)
[![Release](https://img.shields.io/badge/release-1.0.0-2F855A.svg)](https://github.com/batoisystems/batoi-aif/releases/tag/v1.0.0)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)

Batoi AIF is a PHP-first framework for governed AI execution in enterprise applications. It provides a provider-neutral gateway for inference, embeddings, moderation, retrieval, and tool execution while keeping policy enforcement, approvals, evaluations, evidence, and operational controls inside one explicit runtime boundary.

The framework is independent of any application stack. Batoi RAD is a first-class integration target, and the same core can be embedded in Laravel, Symfony, other PHP frameworks, command-line workers, SaaS platforms, and standalone services.

## Why Batoi AIF

Calling an AI provider is straightforward. Operating AI reliably inside a production application is not. Enterprise systems must determine who may execute a request, which provider and model may receive it, whether sensitive content must be transformed, when a human must approve an action, how output is validated, and what evidence remains afterward.

Batoi AIF makes those concerns part of the execution model:

- **Governance by construction** — governed mode fails closed when trusted caller context, policy, or audit persistence is unavailable.
- **Provider independence** — application code targets stable contracts rather than provider-specific response shapes.
- **Auditable decisions** — success, denial, review, provider failure, and evaluation failure produce correlated evidence.
- **Controlled automation** — tools declare permissions, side effects, idempotency, required arguments, and review requirements.
- **Tenant-safe retrieval** — workspace and ACL filters are pushed into governed vector-store adapters before scoring and `topK` selection.
- **Operational resilience** — bounded HTTP responses, timeouts, retries, rate-limit metadata, circuit breaking, and cooperative cancellation are available as composable transports.
- **Framework portability** — core has no required dependency on RAD, Laravel, Symfony, a particular queue, or a particular vector database.

## Capabilities

| Area | Included capabilities |
| --- | --- |
| Gateway | Text inference, streaming contracts, embeddings, moderation, retrieval, and tool execution |
| Governance | Development and governed runtime modes, operation-aware policy, denial, redaction, and review obligations |
| Review | Persistent review requests, audited decisions, request-hash binding, single-use approval consumption, and replay protection |
| Providers | Capability discovery, deterministic routing, health-aware fallback, normalized model metadata, and OpenAI-compatible HTTP integration |
| Prompts | Immutable versions, approval status, semantic-version resolution, variable rendering, and input-schema validation |
| Audit | Canonical hashes, actor/workspace/trace correlation, latency, policy and evaluation evidence, sensitivity labels, JSON Lines export, and RAD/MySQL persistence |
| Evaluation | Pre- and post-execution evaluators with block, warn, and annotate outcomes |
| RAG | Document chunking, governed ingestion, workspace/ACL retrieval, citations, and retrieval evidence attached to generation audits |
| Queues | Dispatch, idempotency, reserve/lease, retry, delay, acknowledgement, cancellation, dead-letter, and crash recovery |
| Agents and tools | Tool registry, permissions, side-effect classification, approval pauses, and bounded step/duration execution |
| Observability | Vendor-neutral metrics for execution, latency, tokens, cost, denials, reviews, retries, health, and audit failures |
| Integrations | Batoi RAD persistence and context adapters plus isolated Laravel and Symfony packages |

## Requirements

- PHP 8.3 or newer
- Composer for normal package installation
- `ext-curl` for the bundled HTTP transport

Optional integrations may require:

- `ext-pdo_mysql` for RAD/MySQL persistence
- a framework package for Laravel or Symfony integration
- an external queue or vector-store client supplied by the host application

## Installation

Install from Packagist when the package is available there:

```bash
composer require batoi/aif:^1.0
```

For a GitHub-hosted installation, declare the repository in the consuming
project:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/batoisystems/batoi-aif.git"
        }
    ]
}
```

Then run the same Composer command.

The repository also contains a lightweight PSR-4-compatible autoloader for controlled ZIP or drop-in deployments:

```php
require_once __DIR__ . '/vendor/batoi/aif/autoload.php';
```

No JavaScript runtime or npm dependency is required by the PHP core.

## Quick Start

The shortest development-mode example uses the deterministic mock provider:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
);

$response = $gateway->infer(new InferenceRequest(
    input: 'Summarize the latest support case.',
));

echo $response->output;
```

Development mode is intended for evaluation and local tests. Production applications should use governed mode.

## Governed Execution

Governed mode requires caller context, policy enforcement, and audit persistence before execution can reach a provider:

```php
<?php

declare(strict_types=1);

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;

$audit = new InMemoryAuditLog();

$gateway = new AifGateway(
    providers: new InMemoryProviderRegistry([
        'mock' => new MockProvider(),
    ]),
    defaultProvider: 'mock',
    policyEngine: new StaticPolicyEngine(
        allowedProviders: ['mock'],
        allowedRoles: ['ai-user'],
        maxInputChars: 20_000,
    ),
    auditLog: $audit,
    runtimeMode: RuntimeMode::Governed,
);

$context = new ExecutionContext(
    userId: 'user-42',
    workspaceId: 'workspace-7',
    roles: ['ai-user'],
    traceUid: 'trace-01J...',
);

$response = $gateway->infer(
    new InferenceRequest(
        input: 'Prepare a concise account summary.',
        provider: 'mock',
    ),
    $context,
);
```

In a production deployment, replace the in-memory audit log and static policy engine with persistent adapters appropriate to the host environment.

## Execution Lifecycle

Every governed operation follows the same control path:

1. Validate runtime dependencies and trusted execution context.
2. Resolve an immutable approved prompt version when applicable.
3. Evaluate operation-aware policy.
4. Apply obligations such as redaction or persist a review request.
5. Select a healthy provider by normalized capability.
6. Execute through bounded resilience controls.
7. Run pre- and post-execution evaluators.
8. Persist one correlated terminal audit record.
9. Emit vendor-neutral operational metrics.

Denied and review-required operations stop before provider or tool side effects.

## Provider Integration

Providers implement `AIProviderInterface`; capability-aware providers additionally expose normalized operations and model metadata. The bundled implementations include:

- `MockProvider` for deterministic development and tests
- `OpenAICompatibleProvider` for text, embedding, and moderation endpoints compatible with OpenAI-style APIs

Provider routing is deterministic and records why a provider was selected. Explicit provider requests do not silently fall back to another provider. A provider must advertise real streaming support to be selected for a streaming operation.

Credentials remain host-owned. Load them from environment or secret-management infrastructure and inject them at application composition time.

## Prompt Governance

Prompt definitions support:

- stable prompt codes
- immutable semantic versions
- approval lifecycle state
- deterministic latest-approved selection
- variable rendering
- JSON-schema-style input validation
- workspace-specific RAD persistence

This allows application code to request an approved prompt without embedding mutable prompt text throughout the codebase.

## Review and Tool Safety

Policy or tool definitions may require human review. A review pause:

- persists the operation and canonical request hash
- prevents provider or tool execution
- records the pending outcome in audit evidence
- accepts an audited approve or reject decision
- binds resumption to the original workspace, operation, and request hash
- consumes approval once, preventing replay

Tool definitions also declare required arguments, permissions, side-effect class, and idempotency behavior. Non-idempotent side-effecting tools require an idempotency key.

## Governed Retrieval

The RAG services provide document chunking, embedding, retrieval, and citation DTOs. Governed retrieval requires an access-controlled vector adapter. Workspace and user/role ACL checks occur before ranking so unauthorized records cannot consume the result window.

`GovernedRagService` returns the generation response and its citations, while the generation audit stores source UID, chunk UID, score, and collection evidence without copying credentials or other classified values.

## Durable Work

`DurableQueueAdapterInterface` defines explicit worker semantics:

- idempotent dispatch
- delayed availability
- reserve and lease ownership
- expired-lease recovery
- retry limits
- acknowledgement and release
- cancellation
- failure and dead-letter handling

The RAD PDO adapter persists these states in MySQL and uses transactional row locking for competing workers.

## Batoi RAD Integration

The RAD profile includes:

- execution-context and permission adapters under `src/Rad/`
- MySQL persistence for audit, prompts, policy, provider catalogs, reviews, and durable queue jobs
- forward and rollback migrations under `database/migrations/rad/`
- immutable call-log database triggers
- schema validation and MySQL integration coverage

RAD installations may load AIF through Composer or place a verified release at `rad/vendor/batoi/aif`.

## Laravel and Symfony

The Laravel and Symfony integrations are isolated packages under `examples/laravel` and `examples/symfony`. Each has its own Composer manifest and tests. Neither framework is a core dependency.

Host controllers, jobs, and message handlers should call `AifGateway` or `AifApi`; they should not bypass governance by calling provider adapters directly.

## API Envelope

`AifApi` returns a stable transport-neutral envelope:

```json
{
  "ok": true,
  "data": {
    "request_uid": "request-id",
    "output": "..."
  },
  "error": null
}
```

Errors use stable public codes and safe messages:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "policy_denied",
    "message": "The request was denied by policy.",
    "http_status": 403
  }
}
```

Internal exception details remain in protected audit evidence rather than being exposed to callers.

## Documentation

- [Adapter architecture](docs/adapters.md)
- [Framework independence](docs/framework-independence.md)
- [RAD persistence profile](docs/rad-persistence-profile.md)
- [REST API contract](docs/rest-api-contract.md)
- [Queue adapters](docs/queue-adapters.md)
- [Vector-store adapters](docs/vector-store-adapters.md)
- [Laravel integration](docs/laravel-adapter.md)
- [Symfony integration](docs/symfony-adapter.md)
- [Distribution policy](docs/distribution-policy.md)
- [ZIP installation](docs/zip-install.md)

## Quality and Compatibility

The release pipeline validates:

- PHPUnit unit, contract, persistence, RAG, review, tool, and HTTP tests
- an end-to-end smoke suite
- PHPStan static analysis
- PSR-12 coding style
- Composer package metadata
- PHP 8.3 and PHP 8.4
- MySQL 8 forward migration, persistence contracts, immutability triggers, and rollback
- isolated Laravel and Symfony packages
- ZIP/drop-in autoloading

Run the local checks:

```bash
composer install
composer validate --strict --no-check-publish
composer test
composer analyse
composer lint
php bin/aif-rad-schema-check.php
```

## Security

Do not commit provider credentials, authorization headers, customer prompts, or private audit payloads. Use the host application's secret manager and retention controls.

Security reports should follow [SECURITY.md](SECURITY.md). Contribution guidance is available in [CONTRIBUTING.md](CONTRIBUTING.md).

## Versioning

Batoi AIF follows semantic versioning. The public runtime version is available as `Batoi\Aif\Aif::VERSION`.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

Copyright © Batoi Systems.

Licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE).
