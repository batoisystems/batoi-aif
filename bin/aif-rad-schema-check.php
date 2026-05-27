<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaPath = $root . '/database/migrations/rad/001_aif_foundation.sql';
$sql = is_file($schemaPath) ? (string) file_get_contents($schemaPath) : '';
$json = in_array('--json', $argv, true);
$requiredTables = [
    's_aif_provider_catalog',
    's_aif_model_catalog',
    's_aif_capability',
    's_aif_policy_template',
    's_aif_eval_template',
    'a_aif_prompt',
    'a_aif_prompt_version',
    'a_aif_policy',
    'a_aif_policy_rule',
    'a_aif_call_log',
    'a_aif_eval',
];
$optionalTables = [
    'a_aif_embedding',
    'a_aif_review',
    'a_aif_memory',
    'a_aif_workflow',
];
$requiredColumns = [
    'id',
    'uid',
    'livestatus',
    'versioncode',
    'wf_status',
    'space_id',
    'createdby',
    'createstamp',
    'updatedby',
    'updatestamp',
];
$checks = [];

foreach ($requiredTables as $table) {
    $tablePattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?\s*\((.*?)\)\s*ENGINE=/is';
    $exists = preg_match($tablePattern, $sql, $match) === 1;
    $checks[] = [
        'name' => 'table:' . $table,
        'ok' => $exists,
        'message' => $exists ? 'Table is defined.' : 'Table is missing from RAD migration.',
    ];

    if (!$exists) {
        continue;
    }

    $definition = $match[1];

    foreach ($requiredColumns as $column) {
        $checks[] = [
            'name' => $table . '.column:' . $column,
            'ok' => preg_match('/`?' . preg_quote($column, '/') . '`?\s+/i', $definition) === 1,
            'message' => 'RAD standard column check.',
        ];
    }

    $checks[] = [
        'name' => $table . '.unique_uid',
        'ok' => preg_match('/UNIQUE\s+KEY\s+`?uid`?\s*\(\s*`?uid`?\s*\)/i', $definition) === 1,
        'message' => 'RAD tables should normally include UNIQUE KEY uid (uid).',
    ];
}

$warnings = [];

foreach ($optionalTables as $table) {
    if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?\s*\(/is', $sql)) {
        $warnings[] = 'Optional future table is not present: ' . $table;
    }
}

$failed = array_values(array_filter($checks, static fn (array $check): bool => !$check['ok']));

if ($json) {
    echo json_encode([
        'ok' => $failed === [],
        'checks' => $checks,
        'warnings' => $warnings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($failed === [] ? 0 : 1);
}

echo "Batoi AIF RAD schema check\n";
echo "==========================\n";

foreach ($checks as $check) {
    echo sprintf("[%s] %s - %s\n", $check['ok'] ? 'ok' : 'fail', $check['name'], $check['message']);
}

foreach ($warnings as $warning) {
    echo '[warn] ' . $warning . PHP_EOL;
}

exit($failed === [] ? 0 : 1);
