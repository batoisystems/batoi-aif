# Laravel Adapter

The Laravel adapter should make Batoi AIF feel native in Laravel while preserving AIF's governance boundary.

This adapter is optional. AIF core must work without Laravel installed.

## Proposed Namespace

```text
Batoi\Aif\Laravel
```

## Proposed Structure

```text
src/Laravel/
├── AifServiceProvider.php
├── AifManager.php
├── LaravelExecutionContextResolver.php
├── LaravelPermissionChecker.php
├── Commands/
│   ├── AifInstallCommand.php
│   ├── AifHealthCommand.php
│   └── AifPromptSyncCommand.php
├── Http/
│   ├── Controllers/
│   └── Middleware/
├── Queue/
│   └── LaravelQueueAdapter.php
└── config/aif.php
```

## Responsibilities

- Register `AifGateway` in Laravel's service container.
- Publish `config/aif.php`.
- Load provider credentials from `.env` or Laravel config.
- Resolve `ExecutionContext` from `Illuminate\Http\Request` and `Auth::user()`.
- Map Laravel gates/policies to `PermissionCheckerInterface`.
- Provide optional `/aif/*` routes that return the standard AIF envelope.
- Bridge Laravel Queue to `QueueAdapterInterface`.
- Add Artisan commands for install, health checks, and prompt sync.

## Config Sketch

```php
return [
    'default_provider' => env('AIF_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'type' => 'openai_compatible',
            'base_url' => env('AIF_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('AIF_OPENAI_API_KEY'),
        ],
    ],
    'audit' => [
        'driver' => env('AIF_AUDIT_DRIVER', 'database'),
    ],
    'queue' => [
        'connection' => env('AIF_QUEUE_CONNECTION', 'default'),
    ],
];
```

## Governance Rule

Laravel controllers, jobs, commands, and services must call `AifGateway` or `AifApi`. They must not call provider adapters directly.

## Acceptance Criteria

- Laravel can resolve `AifGateway` from the service container.
- A Laravel controller can call `AifApi::infer()` and return `{ok,data,error}`.
- Authenticated Laravel users map to `ExecutionContext`.
- Laravel Queue can dispatch an AIF job through `QueueAdapterInterface`.
- The adapter can be omitted without affecting AIF core.
