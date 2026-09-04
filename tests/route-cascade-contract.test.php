<?php
/**
 * What a delete takes with it, pinned as a golden file.
 *
 * Route::deletemass() decides, per class, which OTHER tables have to be
 * cleared when rows of that class go. The map used to be 200 lines inline in
 * a 435-line method; it is Route::_removeItemsFor() plus
 * Route::_withSiteCleanup() now, and either way nothing in the tree asserted
 * a single entry of it. A table could be dropped from an arm, or an arm's
 * where-column renamed, with the whole suite green.
 *
 * That is not a cosmetic risk. Three entries in this map exist because they
 * were MISSING and the rows they name outlived their owners:
 *
 *   - `apitoken` under `user`. A token whose owner is gone is a working
 *     credential belonging to an account that no longer exists.
 *   - `snapintask` under `snapin`, which the REST path orphaned while the UI
 *     path (Snapin::destroy()) cleaned up -- issue #885.
 *   - `siterolegrant` / `siteusergroupgrant`. A grant naming a deleted role
 *     can later put every holder of an unrelated NEW role into the site the
 *     old one granted, because ids get reused.
 *
 * WHAT IS PINNED, one line per cascade entry, in class then table order:
 *
 *     class  table  where-columns
 *
 * The COLUMNS, never the ids. Which rows a delete matches is a property of
 * the request; which columns it matches them BY is the contract, and a
 * renamed column silently matches nothing rather than failing.
 *
 * Ids are passed as [1, 2] so an arm that derives one map from another --
 * host's `snapintask` keys off jobIDs looked up from the hosts, not off the
 * host ids -- still produces its real shape.
 *
 * WHEN THIS FAILS. Either the cascade changed and should not have, or it
 * changed and should have. If the change is intended:
 *
 *     php tests/route-cascade-contract.test.php --update
 *
 * and commit the fixture diff alongside the code -- it is the readable
 * record of what the change did to what a delete destroys.
 *
 * ADR 0031 is gradually replacing this map with declared foreign keys. This
 * file is what shows an entry LEAVING the map as a deliberate line in a diff
 * rather than as an orphan discovered later.
 *
 * DB-free.
 *
 * Usage: php tests/route-cascade-contract.test.php [--update]
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$fixture = __DIR__ . '/fixtures/route-cascade-contract.txt';
$update = in_array('--update', array_slice(isset($argv) ? $argv : [], 1), true);

FogTestHarness::boot('cascade-contract');
$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

$ref = new \ReflectionClass(Route::class);
$removeItemsFor = $ref->getMethod('_removeItemsFor');
$removeItemsFor->setAccessible(true);
$withSiteCleanup = $ref->getMethod('_withSiteCleanup');
$withSiteCleanup->setAccessible(true);

$ids = [1, 2];
$lines = [];
$classes = Route::$validClasses;
sort($classes);
foreach ($classes as $classname) {
    try {
        $map = $removeItemsFor->invoke(null, $classname, $ids);
        $map = $withSiteCleanup->invoke(null, $map, $classname, $ids);
    } catch (\Throwable $e) {
        $lines[] = $classname . "\tTHREW\t" . get_class($e) . ': '
            . str_replace("\n", ' ', $e->getMessage());
        continue;
    }
    if (!is_array($map)) {
        $lines[] = $classname . "\tNOT-AN-ARRAY\t" . gettype($map);
        continue;
    }
    if (!count($map)) {
        $lines[] = $classname . "\t(nothing)\t";
        continue;
    }
    ksort($map);
    foreach ($map as $table => $where) {
        $cols = is_array($where) ? array_keys($where) : ['NOT-AN-ARRAY'];
        sort($cols);
        $lines[] = $classname . "\t" . $table . "\t"
            . implode(',', array_map('strval', $cols));
    }
}

$now = implode("\n", $lines) . "\n";

if ($update) {
    file_put_contents($fixture, $now);
    echo 'updated ' . $fixture . ' (' . count($lines) . " entries)\n";
    exit(0);
}

if (!is_file($fixture)) {
    fwrite(STDERR, "FAIL: no fixture at $fixture. Run with --update.\n");
    exit(1);
}

$was = (string)file_get_contents($fixture);
if ($was === $now) {
    echo 'ok  ' . count($lines) . " cascade entries across "
        . count(array_unique(array_map(
            function ($l) {
                return strstr($l, "\t", true);
            },
            $lines
        ))) . " classes\n";
    exit(0);
}

fwrite(STDERR, "FAIL: the cascade map changed.\n\n");
$wasLines = explode("\n", trim($was));
$nowLines = explode("\n", trim($now));
foreach (array_diff($wasLines, $nowLines) as $l) {
    fwrite(STDERR, "  - $l\n");
}
foreach (array_diff($nowLines, $wasLines) as $l) {
    fwrite(STDERR, "  + $l\n");
}
fwrite(
    STDERR,
    "\nIf this is intended: php tests/route-cascade-contract.test.php --update\n"
);
exit(1);
