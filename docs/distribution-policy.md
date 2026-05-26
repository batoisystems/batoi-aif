# Distribution Policy

Batoi AIF must always be installable in two ways:

- Composer package installation for PHP projects that use Composer.
- GitHub ZIP/drop-in installation for projects that manually place the framework in a target repository.

Composer is the native package manager for the PHP core framework. npm is not a required distribution channel for AIF core. If future JavaScript, TypeScript, or UI tooling is added, npm packages may be introduced for those assets separately, but the PHP framework must remain downloadable and usable from a GitHub ZIP archive.

## Required ZIP Compatibility

Every public release must include:

- `autoload.php`
- `composer.json`
- `src/`
- `docs/`
- `examples/`
- `database/migrations/` when migrations exist
- `LICENSE`
- `README.md`

The GitHub ZIP archive must work after extraction without running Composer:

```php
require_once __DIR__ . '/vendor/batoi/aif/autoload.php';
```

For RAD deployments, the supported drop-in path is:

```text
rad/vendor/batoi/aif/
```

For non-RAD PHP projects, the recommended drop-in path is:

```text
vendor/batoi/aif/
```

## Release Checklist

Before tagging a release:

- Run `php tests/smoke.php`.
- Run PHP syntax checks across `autoload.php`, `src/`, `tests/`, and `examples/`.
- Run `php examples/standalone-queue-vector.php`.
- Verify `autoload.php` can load `Batoi\Aif\Aif` without Composer.
- Verify README includes both Composer and GitHub ZIP installation instructions.
- Verify ZIP install docs remain accurate.
- Do not add core `require` dependencies for Laravel, Symfony, Redis, RabbitMQ, vector stores, or JavaScript tooling.

## npm Position

Do not publish AIF core as an npm package unless there is a clear JavaScript runtime or UI package to distribute.

Potential future npm packages, if needed:

- AIF admin UI components.
- browser-side schema helpers.
- JavaScript/TypeScript client SDK.
- documentation tooling.

Those packages must not replace the Composer and GitHub ZIP distribution paths for the PHP core.
