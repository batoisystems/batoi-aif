# Batoi AIF

Standalone PHP framework for governed AI execution.

Batoi AIF (Artificial Intelligence Framework) is a standalone PHP framework for governed AI execution, provider abstraction, prompt governance, policy enforcement, audit logging, evaluation, and application integration.

Batoi RAD is a first-class integration target, but AIF core is framework-independent and can be used in RAD apps, Laravel/Symfony/Slim applications, vanilla PHP projects, SaaS products, CLI workers, and internal enterprise tools.

## What It Provides

- Provider abstraction for AI text, embedding, moderation, and streaming workflows.
- Governed gateway execution with policy checks before provider calls.
- Prompt registry contracts with versioning and approval-state support.
- Audit log contracts for immutable execution evidence.
- REST-style response envelopes for host applications and API controllers.
- In-memory implementations for local development and tests.
- OpenAI-compatible provider support.
- First-class Batoi RAD integration profile without making RAD a core dependency.

## Core Stack

Batoi AIF core is PHP-first and adapter-oriented.

Core requirements:

- PHP 8.3+
- Composer autoloading or the bundled `autoload.php` for GitHub ZIP installs
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

Preferred Composer install:

```bash
composer require batoi/aif
```

Manual GitHub ZIP install:

1. Download the Batoi AIF GitHub ZIP archive.
2. Extract it into a target repository, for example `vendor/batoi/aif/` or `rad/vendor/batoi/aif/` for RAD apps.
3. Include the bundled autoloader when Composer is not managing the package:

```php
require_once __DIR__ . '/rad/vendor/batoi/aif/autoload.php';
```

See [docs/zip-install.md](docs/zip-install.md) for the RAD fallback autoload pattern.

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
- RAD implementation handoff in `specs/rad-core-aif-implementation-instructions.md`

RAD deployments may install AIF with Composer or by placing a GitHub ZIP extraction at:

```text
rad/vendor/batoi/aif/
```

## Development

Run the smoke test:

```bash
php tests/smoke.php
```

Run syntax checks:

```bash
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
php -l autoload.php
```
