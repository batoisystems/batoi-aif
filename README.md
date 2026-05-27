# Batoi AIF

Standalone PHP framework for governed AI execution.

Batoi AIF (Artificial Intelligence Framework) is a standalone PHP framework for governed AI execution, provider abstraction, prompt governance, policy enforcement, audit logging, evaluation, and application integration.

Batoi RAD is a first-class integration target, but AIF core is framework-independent and can be used in RAD apps, Laravel/Symfony/Slim applications, vanilla PHP projects, SaaS products, CLI workers, and internal enterprise tools.

## Status

This repository is in early foundation work. The current package includes the core gateway, provider contracts, mock and OpenAI-compatible providers, prompt rendering, static policy checks, in-memory audit logging, RAD context adapters, queue/vector contracts, in-memory queue/vector implementations, and RAD persistence DDL.

## What It Provides

- Provider abstraction for AI text, embedding, moderation, and streaming workflows.
- Governed gateway execution with policy checks before provider calls.
- Prompt registry contracts with versioning and approval-state support.
- Audit log contracts for immutable execution evidence.
- REST-style response envelopes for host applications and API controllers.
- In-memory implementations for local development and tests.
- OpenAI-compatible provider support.
- First-class Batoi RAD integration profile without making RAD a core dependency.

## Requirements

Batoi AIF core is PHP-first and adapter-oriented.

- PHP 8.3+
- `ext-curl` for the bundled cURL HTTP transport
- Composer autoloading or the bundled `autoload.php` for GitHub ZIP/drop-in installs
- cURL-based HTTP provider transport
- REST-style integration contracts

Integration profiles and optional adapters may add requirements such as MySQL/MariaDB, RAD-compatible `s_` and `a_` table conventions, Redis, queues, PostgreSQL/pgvector, Qdrant, Pinecone, Weaviate, or external object storage. These are not required by the core package.

## Architecture Boundary

AIF core owns:

- provider contracts and provider adapters
- gateway execution
- policy decisions
- prompt rendering and prompt registry contracts
- audit log contracts
- DTOs and REST-style API envelopes
- generic in-memory implementations for tests and simple embedding

Batoi RAD integration owns:

- RAD context and permission adapters
- RAD `s_aif_*` and `a_aif_*` persistence profile
- RAD API endpoint registration
- RAD admin/UI modules
- RAD upgrade and migration flow

See [docs/framework-independence.md](docs/framework-independence.md) for the dependency boundary and integration-profile rules.

## Installation

Batoi AIF must remain installable through both Composer and GitHub ZIP/drop-in placement.

Composer install for PHP projects using Composer:

```bash
composer require batoi/aif
```

If the package has not yet been published to Packagist, use a Composer path or VCS repository entry from your application until publication.

Manual GitHub ZIP/drop-in install:

1. Download the Batoi AIF GitHub ZIP archive.
2. Extract it into a target repository, for example `vendor/batoi/aif/` or `rad/vendor/batoi/aif/` for RAD apps.
3. Include the bundled autoloader when Composer is not managing the package:

```php
require_once __DIR__ . '/vendor/batoi/aif/autoload.php';
```

For RAD apps, use the RAD path:

```php
require_once __DIR__ . '/rad/vendor/batoi/aif/autoload.php';
```

Composer is the native package manager for the PHP framework. npm is not required for AIF core; future npm packages may be added only for JavaScript/UI assets if needed.

See [docs/zip-install.md](docs/zip-install.md) for the RAD fallback autoload pattern and [docs/distribution-policy.md](docs/distribution-policy.md) for the release distribution policy.

## Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Value\InferenceRequest;

$gateway = new AifGateway(new InMemoryProviderRegistry([
    'mock' => new MockProvider(),
]));

$response = $gateway->infer(new InferenceRequest(
    input: 'Summarize this support ticket.'
));

echo $response->output;
```

For a GitHub ZIP/drop-in install, replace Composer autoload with:

```php
require_once __DIR__ . '/vendor/batoi/aif/autoload.php';
```

## Adoption Paths

Start with the path closest to your project:

| Use case | Start here |
| --- | --- |
| Standalone PHP evaluation | [examples/hello-world.php](examples/hello-world.php) |
| GitHub ZIP/drop-in install | [examples/zip-dropin/hello-world.php](examples/zip-dropin/hello-world.php) |
| Governed execution | [examples/governed-gateway.php](examples/governed-gateway.php) |
| Prompt registry/versioning | [examples/prompt-registry.php](examples/prompt-registry.php) |
| Batoi RAD integration | [examples/rad/bootstrap-aif.php](examples/rad/bootstrap-aif.php) |
| REST/API controller integration | [examples/api-envelope.php](examples/api-envelope.php) |
| Laravel integration | [examples/laravel](examples/laravel) |
| Symfony integration | [examples/symfony](examples/symfony) |
| Queue/worker workflows | [docs/queue-adapters.md](docs/queue-adapters.md) |
| Queue/worker examples | [examples/worker/embedding-worker.php](examples/worker/embedding-worker.php) |
| Vector/RAG workflows | [examples/rag/answer-with-context.php](examples/rag/answer-with-context.php) |
| Starter templates | [templates/README.md](templates/README.md) |

Adoption tooling:

```bash
php bin/aif-doctor.php
php bin/aif-config-check.php --config examples/config/aif.php
php bin/aif-smoke.php --provider=mock --infer
php bin/aif-rad-schema-check.php
```

## API Envelope

AIF facades return a stable envelope:

```json
{
  "ok": true,
  "data": {},
  "error": null
}
```

Errors use the same shape:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "error_code",
    "message": "Human-readable message"
  }
}
```

See [docs/rest-api-contract.md](docs/rest-api-contract.md).

## RAD Integration

RAD support is delivered as an integration profile:

- adapters under `src/Rad/`
- migrations under `database/migrations/rad/`
- RAD persistence profile in [docs/rad-persistence-profile.md](docs/rad-persistence-profile.md)
- ZIP/drop-in guidance in [docs/zip-install.md](docs/zip-install.md)

RAD deployments may install AIF with Composer or by placing a GitHub ZIP extraction at:

```text
rad/vendor/batoi/aif/
```

## Optional Adapters

Queue and vector-store contracts are available for async work and governed RAG integrations:

- [docs/adapters.md](docs/adapters.md)
- [docs/laravel-adapter.md](docs/laravel-adapter.md)
- [docs/symfony-adapter.md](docs/symfony-adapter.md)
- [docs/queue-adapters.md](docs/queue-adapters.md)
- [docs/vector-store-adapters.md](docs/vector-store-adapters.md)
- [examples/standalone-queue-vector.php](examples/standalone-queue-vector.php)

## Development

Run the smoke test:

```bash
php tests/smoke.php
```

Run the standalone queue/vector example:

```bash
php examples/standalone-queue-vector.php
```

Run adoption examples:

```bash
php examples/hello-world.php
php examples/governed-gateway.php
php examples/prompt-registry.php
php examples/api-envelope.php
php examples/zip-dropin/hello-world.php
php examples/rad/bootstrap-aif.php
php examples/worker/embedding-worker.php
php examples/worker/eval-runner.php
php examples/rag/ingest.php
php examples/rag/search.php
php examples/rag/answer-with-context.php
```

Laravel and Symfony examples are skeletons intended to be copied into host applications:

- [examples/laravel](examples/laravel)
- [examples/symfony](examples/symfony)

Run syntax checks:

```bash
php -l autoload.php
find src tests examples bin -name '*.php' -print0 | xargs -0 -n1 php -l
```

Composer validation is recommended where Composer is installed:

```bash
composer validate --no-check-publish
```

## License

Apache-2.0. See [LICENSE](LICENSE).
