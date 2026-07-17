# Contributing to Batoi AIF

## Development Setup

Requirements:

- PHP 8.3 or 8.4;
- Composer 2;
- the cURL extension.

Install and verify:

```bash
composer install
composer validate --strict --no-check-publish
composer test
composer analyse
composer lint
php bin/aif-rad-schema-check.php
```

`composer.lock` is committed to keep CI and contributor tooling reproducible. The published library continues to expose dependency ranges from `composer.json`; consumers do not install this repository's lock file.

## Change Rules

- Preserve provider-neutral public contracts.
- Route execution through `AifGateway`.
- Add policy and audit coverage for every new execution operation.
- Prove denied and review-required paths do not call providers or tools.
- Keep RAD, Laravel, Symfony, queue, and vector dependencies optional.
- Never add credentials, production prompts, customer data, or raw audit evidence to fixtures.

Public-interface changes require migration notes and contract tests. Governance behavior must fail closed in governed mode.
