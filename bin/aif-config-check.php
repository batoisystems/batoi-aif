<?php

declare(strict_types=1);

$json = in_array('--json', $argv, true);
$configPath = null;

foreach ($argv as $index => $arg) {
    if ($arg === '--config' && isset($argv[$index + 1])) {
        $configPath = $argv[$index + 1];
    }

    if (str_starts_with($arg, '--config=')) {
        $configPath = substr($arg, strlen('--config='));
    }
}

$errors = [];
$warnings = [];
$config = [];

if ($configPath === null) {
    $warnings[] = 'No --config path supplied; checked environment defaults only.';
} elseif (!is_file($configPath)) {
    $errors[] = 'Config file not found: ' . $configPath;
} else {
    $loaded = require $configPath;

    if (!is_array($loaded)) {
        $errors[] = 'Config file must return an array.';
    } else {
        $config = $loaded;
    }
}

$providers = array_keys(array_filter($config['providers'] ?? [], 'is_array'));
$defaultProvider = (string) ($config['default_provider'] ?? getenv('AIF_PROVIDER') ?: 'mock');
$allowedProviders = $config['policy']['allowed_providers'] ?? [];

if ($config !== [] && $providers === []) {
    $errors[] = 'Config should define at least one provider.';
}

if ($providers !== [] && !in_array($defaultProvider, $providers, true)) {
    $errors[] = sprintf('Default provider "%s" is not defined in providers.', $defaultProvider);
}

if (is_array($allowedProviders) && $allowedProviders !== [] && !in_array($defaultProvider, $allowedProviders, true)) {
    $errors[] = sprintf('Default provider "%s" is not allowed by policy.allowed_providers.', $defaultProvider);
}

foreach (($config['providers'] ?? []) as $providerCode => $providerConfig) {
    if (!is_array($providerConfig)) {
        $errors[] = sprintf('Provider "%s" config must be an array.', (string) $providerCode);
        continue;
    }

    foreach ($providerConfig as $key => $value) {
        $keyName = (string) $key;

        if (str_ends_with($keyName, '_env')) {
            continue;
        }

        if (is_string($value) && $value !== '' && preg_match('/(sk-|api[_-]?key|secret|token)/i', $value) === 1) {
            $warnings[] = sprintf('Provider "%s" field "%s" looks like it may contain a secret value.', (string) $providerCode, (string) $key);
        }
    }
}

$result = [
    'ok' => $errors === [],
    'errors' => $errors,
    'warnings' => $warnings,
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($errors === [] ? 0 : 1);
}

echo "Batoi AIF config check\n";
echo "======================\n";

foreach ($errors as $error) {
    echo '[fail] ' . $error . PHP_EOL;
}

foreach ($warnings as $warning) {
    echo '[warn] ' . $warning . PHP_EOL;
}

if ($errors === []) {
    echo "[ok] Config check passed.\n";
}

exit($errors === [] ? 0 : 1);
