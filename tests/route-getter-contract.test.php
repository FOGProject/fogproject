<?php
/**
 * What Route::getter() serializes, pinned as a golden file.
 *
 * getter() is the single-entity serializer: `GET /host/1` and every nested
 * object embedded in another entity come through it. Fifteen of its arms add
 * per-class keys on top of the entity's own fields, and those keys ARE the
 * API's single-entity contract -- `imagename` beside `image`, `macs`,
 * `hostcount`, `isCapture`. Nothing pinned them, so an arm could gain or
 * lose a key with the whole suite green.
 *
 * WHAT IS PINNED, one line per key, in class then key order:
 *
 *     class  key  rendering
 *
 * Objects are built UNLOADED, deliberately. The fields an entity carries are
 * its own concern and are pinned elsewhere; what is this file's business is
 * the extra keys the serializer contributes, and against an unloaded object
 * `$class->get()` is empty, so every line here is one of them. An arm that
 * throws on an empty object is recorded as its exception -- that is a
 * behavior too, and a refactor that turns a throw into a silent empty is
 * exactly the kind of change this exists to show.
 *
 * The ordering trap this was written for, because it is invisible any other
 * way: each arm used to end in
 * `$data = FOGCore::fastmerge($class->get(), [...])`, and PHP evaluates that
 * first argument BEFORE the extras -- so the entity was read before the
 * arm's own accessors ran. Several of those accessors populate the object as
 * a side effect (Group::getHostCount() leaves `hosts`, snapin's arm leaves
 * `storagegroups`). Hoisting the read to after the switch, which is the
 * obvious way to write the merge once, silently added two keys to two
 * entities. Nothing else in the tree noticed; this file names both.
 *
 * WHEN THIS FAILS. Either a serialized key changed and should not have, or
 * it changed and should have. If the change is intended:
 *
 *     php tests/route-getter-contract.test.php --update
 *
 * and commit the fixture diff alongside the code -- it is the readable
 * record of what the change did to the API surface.
 *
 * DB-free.
 *
 * Usage: php tests/route-getter-contract.test.php [--update]
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$fixture = __DIR__ . '/fixtures/route-getter-contract.txt';
$update = in_array('--update', array_slice(isset($argv) ? $argv : [], 1), true);

FogTestHarness::boot('getter-contract');
$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

$admin = FOGCore::getClass('User')->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * One value, rendered so a diff is readable and stable.
 *
 * Arrays are reduced to their SHAPE, never their contents: `list` for a
 * positional one, `map[a,b,c]` for an associative one. What is under test is
 * which keys the serializer produces and what shape each one has -- an
 * embedded object's own contents are that object's contract, not this one's.
 *
 * A list's LENGTH is deliberately not rendered, and this is not tidiness.
 * The first version of this file recorded `array(N)[keys]`, and
 * `storagegroup.enablednodes` then came out as array(1) on PHP 8.3 and
 * array(0) on 7.4: StorageGroup::loadEnablednodes() skips a node whose
 * `maxClients < 1`, and against the harness's synthesized non-numeric value
 * PHP 7.4 casts the string to 0 while PHP 8 compares it as a string. The
 * length was a property of the fake row and of the PHP version, not of the
 * serializer, and the suite is run on both. (Production is unaffected --
 * maxClients is an integer column, and a numeric string compares the same
 * on either version.)
 *
 * @param mixed $v the value
 *
 * @return string
 */
function renderValue($v)
{
    if (is_scalar($v) || null === $v) {
        return var_export($v, true);
    }
    if (is_array($v)) {
        $keys = array_keys($v);
        // Empty counts as a list, because in PHP an empty array HAS no
        // shape -- and because an arm whose value is empty on one PHP
        // version and one-element on another must render identically or
        // the fixture is version-dependent again. See the note above.
        if (!count($keys) || $keys === range(0, count($keys) - 1)) {
            return 'list';
        }
        return 'map[' . implode(',', array_map('strval', $keys)) . ']';
    }
    return 'object(' . get_class($v) . ')';
}

$lines = [];
$classes = Route::$validClasses;
sort($classes);
foreach ($classes as $classname) {
    try {
        $obj = FOGCore::getClass($classname);
    } catch (\Throwable $e) {
        $lines[] = $classname . "\tNO-CLASS\t" . $e->getMessage();
        continue;
    }
    try {
        $out = Route::getter($classname, $obj);
    } catch (\Throwable $e) {
        $lines[] = $classname . "\tTHREW\t" . get_class($e) . ': '
            . str_replace("\n", ' ', $e->getMessage());
        continue;
    }
    if (!is_array($out)) {
        $lines[] = $classname . "\tNOT-AN-ARRAY\t" . renderValue($out);
        continue;
    }
    ksort($out);
    foreach ($out as $k => $v) {
        $lines[] = $classname . "\t" . $k . "\t"
            . substr(str_replace("\n", ' ', renderValue($v)), 0, 160);
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
    echo 'ok  ' . count($lines) . " serialized keys across "
        . count(array_unique(array_map(
            function ($l) {
                return strstr($l, "\t", true);
            },
            $lines
        ))) . " classes\n";
    exit(0);
}

$oldLines = explode("\n", trim($was, "\n"));
$newLines = explode("\n", trim($now, "\n"));
$diffs = 0;
foreach (array_unique(array_merge($oldLines, $newLines)) as $l) {
    $inOld = in_array($l, $oldLines, true);
    $inNew = in_array($l, $newLines, true);
    if ($inOld && $inNew) {
        continue;
    }
    $diffs++;
    fwrite(STDERR, ($inOld ? '  was:  ' : '  now:  ') . $l . "\n");
}
fwrite(
    STDERR,
    "FAIL: getter()'s serialized keys changed ($diffs difference(s), "
    . count($oldLines) . ' expected, ' . count($newLines) . " found).\n"
    . "If that was intended, re-run with --update and commit the fixture.\n"
);
exit(1);
