# Symfony Adapter

The Symfony adapter makes Batoi AIF available as a separately installable/testable example package for dependency injection, HTTP, and routing integration.

This adapter is optional. AIF core must work without Symfony installed.

## Namespace

```text
Batoi\Aif\Symfony
```

## Current Package

The optional package is under `examples/symfony/` with its own `composer.json`, PHPUnit configuration, and context-resolver test. Its Symfony dependencies are not part of the root package.

```bash
composer install --working-dir=examples/symfony
composer test --working-dir=examples/symfony
```

## Target Expansion

```text
src/Symfony/
├── BatoiAifBundle.php
├── DependencyInjection/
│   ├── BatoiAifExtension.php
│   └── Configuration.php
├── AifManager.php
├── SymfonyExecutionContextResolver.php
├── SymfonyPermissionChecker.php
├── Command/
│   ├── AifInstallCommand.php
│   └── AifHealthCommand.php
├── Controller/
├── Messenger/
│   └── AifMessageHandler.php
└── Resources/config/services.yaml
```

## Responsibilities

- Register AIF services in Symfony DI.
- Support `config/packages/batoi_aif.yaml`.
- Resolve `ExecutionContext` from `RequestStack`, `Security`, and authenticated users.
- Map Symfony roles/voters to `PermissionCheckerInterface`.
- Provide optional controllers/routes returning the standard AIF envelope.
- Bridge Symfony Messenger to `QueueAdapterInterface`.
- Add console commands for install and health checks.

## Config Sketch

```yaml
batoi_aif:
  default_provider: openai
  providers:
    openai:
      type: openai_compatible
      base_url: '%env(default:https://api.openai.com/v1:AIF_OPENAI_BASE_URL)%'
      api_key: '%env(AIF_OPENAI_API_KEY)%'
  audit:
    driver: doctrine
  messenger:
    bus: messenger.bus.default
```

## Governance Rule

Symfony controllers, message handlers, commands, and services must call `AifGateway` or `AifApi`. They must not call provider adapters directly.

## Acceptance Criteria

- Symfony can autowire `AifGateway`.
- A Symfony controller can call `AifApi::infer()` and return `{ok,data,error}`.
- Symfony security token data maps to `ExecutionContext`.
- Symfony Messenger can dispatch an AIF job through `QueueAdapterInterface`.
- The adapter can be omitted without affecting AIF core.
