# GitHub ZIP Installation

Batoi AIF can be installed without Composer by downloading the GitHub ZIP archive and placing the extracted folder inside any target PHP repository.

Composer installation is still preferred when the target project already manages dependencies through Composer. ZIP installation is for RAD deployments or other application repositories that need a checked-in or manually placed framework copy.

## Recommended Folder

For a RAD repository, place AIF under:

```text
rad/vendor/batoi/aif/
```

For a non-RAD PHP repository, place it under a vendor-like path:

```text
vendor/batoi/aif/
```

Do not place AIF source files directly under `rad/core`. RAD bridge classes should live in `rad/core/sys`; the AIF package should remain a separate folder.

## Required Include

When Composer autoload is not available, include AIF's bundled autoloader:

```php
require_once __DIR__ . '/rad/vendor/batoi/aif/autoload.php';
```

Use the path that matches the target repository layout.

After this include, AIF classes are available under the `Batoi\Aif\` namespace:

```php
use Batoi\Aif\Aif;

echo Aif::name();
```

## RAD Autoloader Fallback

RAD's normal autoloader includes:

```php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
```

For ZIP installations, RAD should keep that Composer include when present and add a fallback for the embedded AIF autoloader:

```php
$composerAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

$aifAutoload = dirname(__DIR__, 2) . '/vendor/batoi/aif/autoload.php';
if (is_file($aifAutoload)) {
    require_once $aifAutoload;
}
```

This allows both installation modes:

- Composer package: `rad/vendor/autoload.php`
- GitHub ZIP: `rad/vendor/batoi/aif/autoload.php`

## Updates

For a manual ZIP installation, update by replacing the `rad/vendor/batoi/aif/` folder with the newer extracted release. Preserve target-repository files such as `rad/config/aif-config.php`, RAD bridge classes, and applied database migrations.

## Verification

Run this from the target repository after placing the folder:

```bash
php -r "require_once 'rad/vendor/batoi/aif/autoload.php'; echo Batoi\\Aif\\Aif::name(), PHP_EOL;"
```

Expected output:

```text
Batoi AIF
```
