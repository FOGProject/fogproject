<?php
require __DIR__ . '/fog-schema-collector.php';
$database = $argv[1] ?? '';
if (!preg_match('/\Afog_mj1(?:_[A-Za-z0-9_]+)?\z/', $database)) {
    fwrite(STDERR, "FAIL: pass the validated fog_mj1 scratch database name\n");
    exit(1);
}
$steps = fogCollectSchemaSteps(dirname(__DIR__, 2) . '/packages/web/commons/schema.php', $database, 447);
$step447 = $steps[446] ?? null;
if (!is_array($step447) || array_keys($step447) !== [0, 1, 2]
    || count(array_filter($step447, 'is_string')) !== 3) {
    fwrite(STDERR, "FAIL: schema index 446 (official schema 447) must contain three DDL strings\n");
    exit(1);
}
echo json_encode($step447), "\n";
