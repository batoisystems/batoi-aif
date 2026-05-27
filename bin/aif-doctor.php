<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$checks = [];

$add = static function (string $name, bool $ok, string $message) use (&$checks): void {
    $checks[] = [
        'name' => $name,
        'ok' => $ok,
        'message' => $message,
    ];
};

$add('php_version', version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION . ' detected; Batoi AIF requires PHP 8.3+.');
$add('bundled_autoload', is_file($root . '/autoload.php'), 'Bundled autoload.php should exist for GitHub ZIP/drop-in installs.');
$add('composer_manifest', is_file($root . '/composer.json'), 'composer.json should exist for Composer installs.');
$add('curl_extension', extension_loaded('curl'), 'ext-curl is required for bundled HTTP provider transport.');
$add('json_extension', extension_loaded('json'), 'ext-json is required for API envelopes and provider responses.');
$add('src_directory', is_dir($root . '/src'), 'src/ should exist.');
$add('examples_directory', is_dir($root . '/examples'), 'examples/ should exist.');
$add('rad_migration', is_file($root . '/database/migrations/rad/001_aif_foundation.sql'), 'RAD migration profile should exist.');

require_once $root . '/autoload.php';

$add('aif_class', class_exists(Batoi\Aif\Aif::class), 'Batoi\\Aif\\Aif should autoload.');
$add('gateway_class', class_exists(Batoi\Aif\Gateway\AifGateway::class), 'AifGateway should autoload.');

$envProvider = getenv('AIF_PROVIDER') ?: 'mock';
$add('provider_env', $envProvider !== '', 'AIF_PROVIDER is optional; defaulting to mock when not set.');

$failed = array_values(array_filter($checks, static fn (array $check): bool => !$check['ok']));

if ($json) {
    echo json_encode([
        'ok' => $failed === [],
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    exit($failed === [] ? 0 : 1);
}

echo "Batoi AIF doctor\n";
echo "================\n";

foreach ($checks as $check) {
    echo sprintf("[%s] %s - %s\n", $check['ok'] ? 'ok' : 'fail', $check['name'], $check['message']);
}

exit($failed === [] ? 0 : 1);

