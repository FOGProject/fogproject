<?php
/**
 * Re-export flat `FOG\X` test stubs under the bucket namespace the real tree
 * uses.
 *
 * Harness tests declare cut-down stand-ins -- `FOG\FOGBase`, `FOG\Route`,
 * `FOG\StorageNode` -- and then require one real class file so its logic runs
 * against them. Since Move 2 that real file lives in `namespace FOG\Boot;`
 * (or Service, Items, ...) and imports its collaborators by their bucketed
 * names, so a stub declared as `FOG\FOGBase` no longer satisfies
 * `use FOG\Base\FOGBase;` and the require dies with "class not found".
 *
 * Rather than make every harness hardcode which bucket each collaborator
 * happens to live in -- a second copy of the taxonomy, guaranteed to drift --
 * this reads the buckets off the tree and aliases whatever the harness has
 * already declared. Moving a class between buckets therefore needs no test
 * edit at all.
 *
 * Include it AFTER the stubs are declared and BEFORE the real file is
 * required. `class_exists($flat, false)` is deliberately autoload-free: it
 * must see only what the harness declared, never trigger a load of the real
 * class it is standing in for.
 */
$srcDir = dirname(__DIR__, 2) . '/packages/web/src';
foreach (glob($srcDir . '/*/*.php') as $stubBucketFile) {
    $stubShort  = basename($stubBucketFile, '.php');
    $stubBucket = basename(dirname($stubBucketFile));
    $stubFlat   = 'FOG\\' . $stubShort;
    $stubReal   = 'FOG\\' . $stubBucket . '\\' . $stubShort;
    if (class_exists($stubFlat, false)
        && !class_exists($stubReal, false)
        && !interface_exists($stubReal, false)
    ) {
        class_alias($stubFlat, $stubReal);
    }
}
unset($stubBucketFile, $stubShort, $stubBucket, $stubFlat, $stubReal);
