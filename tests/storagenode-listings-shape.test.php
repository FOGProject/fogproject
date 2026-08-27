<?php
/**
 * A node's file listing must be a LIST, and must name each file once.
 *
 * GH-1312 was about `images` and `snapinfiles` coming back empty for a caller
 * with no browser session. That is fixed -- status/getfiles.php now accepts a
 * node signature -- and fixing it exposed what those fields answer when they
 * DO answer, which had never been looked at because nothing without a session
 * could get a non-empty one.
 *
 * Two defects, both of them a 200 carrying wrong data, which is the same
 * failure GH-1312 itself was:
 *
 * 1. EVERY FILE, ONCE PER NODE. getfiles.php builds its allow-list from
 *    Route::getIds('storagenode', [], 'path') and the same for 'snapinpath'.
 *    Those return one row per storage node, and every node in a group is
 *    normally configured with the same paths -- so a request for
 *    /opt/fog/snapins matched four identical entries on a four-node install,
 *    globbed the same directory four times, and answered with every file
 *    repeated four times. Measured on a live four-node server: 16 entries for
 *    4 files. It scales with node count and is invisible on the single-node
 *    installs most testing happens on.
 *
 * 2. AN OBJECT WHERE A LIST BELONGS. StorageNode::_getData() returned
 *    preg_grep()'s result directly, and preg_grep PRESERVES KEYS. Every entry
 *    it filtered out -- the `dev|postdownloadscripts|ssl` exclusions -- left a
 *    gap, and json_encode renders a gapped array as an OBJECT. So a node
 *    holding an `ssl` directory served `{"0":"a","2":"b"}` where the API
 *    documents a list. `images` looked fine throughout only because it is
 *    mapped through Route::getIds() and rebuilt on the way out, so the gaps
 *    never survived to the payload.
 *
 * Both are executed here rather than read. A source check passes on code that
 * calls array_unique on the wrong variable, and on an array_values() applied
 * where there was never a gap.
 *
 * Usage: php tests/storagenode-listings-shape.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$fails = [];

// ---------------------------------------------------------------------------
// 1. getfiles.php: one entry per directory, not per node that names it.
//
// The block only needs $decodePath, $validPaths, FOGCore::fastmerge() and the
// filesystem, so it lifts out of a file that otherwise requires a full FOG
// boot and a database.
// ---------------------------------------------------------------------------
$getfiles = (string)file_get_contents($web . '/status/getfiles.php');
$from = strpos($getfiles, "\$paths = explode(':', \$decodePath);");
$to = strpos($getfiles, '$files = [];');
if (false === $from || false === $to || $to <= $from) {
    fwrite(
        STDERR,
        "FAIL: could not locate the path-resolution block in status/getfiles.php.\n"
        . "  If it moved or was rewritten, point this test at it -- do not\n"
        . "  delete the assertion.\n"
    );
    exit(1);
}
$block = substr($getfiles, $from, $to - $from);

if (!class_exists('FOGCore')) {
    eval(
        'class FOGCore { public static function fastmerge(...$a) { $o = [];'
        . ' foreach ($a as $x) { $o = array_merge($o, (array)$x); } return $o; } }'
    );
}

$tmp = sys_get_temp_dir() . '/fog-getfiles-' . getmypid();
@mkdir($tmp . '/snapins', 0777, true);
foreach (['a.txt', 'b.txt'] as $f) {
    file_put_contents($tmp . '/snapins/' . $f, 'x');
}

// What Route::getIds('storagenode', [], 'snapinpath') hands back on a
// four-node install: the same directory, four times.
$decodePath = $tmp . '/snapins';
$validPaths = [$decodePath, $decodePath, $decodePath, $decodePath];
$realpaths = [];
eval($block);

$fails = array_merge(
    $fails,
    count($realpaths) === 1
        ? []
        : [
            'getfiles.php resolved ' . count($realpaths) . ' paths for one'
            . ' directory named by four nodes; every file it holds would be'
            . ' listed ' . count($realpaths) . ' times',
        ]
);

// Two genuinely different directories must both survive -- a dedupe that
// collapses those is worse than the bug.
@mkdir($tmp . '/images', 0777, true);
file_put_contents($tmp . '/images/c.txt', 'x');
$decodePath = $tmp . '/snapins';
$validPaths = [$tmp . '/snapins', $tmp . '/snapins', $tmp . '/images'];
$realpaths = [];
eval($block);
$fails = array_merge(
    $fails,
    count($realpaths) === 1
        ? []
        : ['a request for one directory resolved ' . count($realpaths)
            . ' paths; it must not pick up the other allowed directory'],
);

// Two allowed directories that SHARE A BASENAME, which is what a multi-mount
// or multi-node layout looks like: /storage1/images and /storage2/images. A
// dedupe keyed on anything but the full path collapses these into one and the
// second node's files vanish -- a quieter version of the bug being fixed, and
// the reason the check below counts distinct paths rather than distinct names.
@mkdir($tmp . '/n1/images', 0777, true);
@mkdir($tmp . '/n2/images', 0777, true);
file_put_contents($tmp . '/n1/images/d.txt', 'x');
file_put_contents($tmp . '/n2/images/e.txt', 'x');
$decodePath = $tmp . '/n[12]/images';
$validPaths = [$tmp . '/n1/images', $tmp . '/n2/images'];
$realpaths = [];
eval($block);
$fails = array_merge(
    $fails,
    count($realpaths) === 2
        ? []
        : ['two allowed directories sharing the basename "images" collapsed to '
            . count($realpaths) . '; the dedupe is matching on the name rather'
            . ' than the path, so one node\'s files disappear'],
);

foreach (['/snapins', '/images', '/n1/images', '/n2/images'] as $dir) {
    array_map('unlink', (array)glob($tmp . $dir . '/*'));
    @rmdir($tmp . $dir);
}
@rmdir($tmp . '/n1');
@rmdir($tmp . '/n2');
@rmdir($tmp);

// ---------------------------------------------------------------------------
// 2. _getData(): the filtered listing must still encode as a JSON list.
// ---------------------------------------------------------------------------
$node = (string)file_get_contents($web . '/src/Items/StorageNode.php');
if (!preg_match('#return array_values\(\s*preg_grep\((.*?)\)\s*\);#s', $node, $rm)) {
    fwrite(
        STDERR,
        "FAIL: StorageNode::_getData() no longer returns array_values(preg_grep(...)).\n"
        . "  preg_grep PRESERVES KEYS, so without array_values a filtered entry\n"
        . "  leaves a gap and json_encode emits an OBJECT where the API\n"
        . "  documents a list. If this moved, point the test at it.\n"
    );
    exit(1);
}
// The real expression, with the real filter, against a listing whose second
// entry is one the filter drops -- which is exactly what produces the gap.
$response = [0 => json_encode(['/p/a', '/p/ssl', '/p/b'])];
$filtered = eval('return array_values(preg_grep(' . $rm[1] . '));');
$encoded = json_encode($filtered);

if ($encoded === false || $encoded === '' || $encoded[0] !== '[') {
    $fails[] = 'a filtered listing encodes as ' . var_export($encoded, true)
        . '; it must be a JSON array, not an object';
}
if ($filtered !== ['/p/a', '/p/b']) {
    $fails[] = 'the filter returned ' . json_encode($filtered)
        . '; expected the two entries that are not excluded';
}

if ($fails) {
    fwrite(
        STDERR,
        "FAIL: a storage node's file listing is not what it claims.\n\n"
        . '  - ' . implode("\n  - ", $fails) . "\n\n"
        . "  Both of these answer 200 with wrong content, which is what makes\n"
        . "  them expensive: a caller has no way to tell. See GH-1312.\n"
    );
    exit(1);
}

echo "ok  a node's file listing is a list, and names each file once\n";
exit(0);
