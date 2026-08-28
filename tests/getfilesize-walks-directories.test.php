<?php
/**
 * FOGBase::getFilesize() must total a directory tree, not its dot entries.
 *
 * A FOG image is a directory, so getFilesize() is called on one by the
 * FOGImageSize daemon (src/Service/ImageSize.php), by replication's local
 * vs remote size comparison (src/Service/FOGService.php) and by the task
 * queue writing srvsize. It was wrong in two directions at once:
 *
 *   SKIP_DOTS is a RecursiveDirectoryIterator flag. It had been passed as
 *   RecursiveIteratorIterator's $mode, whose values are LEAVES_ONLY(0),
 *   SELF_FIRST(1), CHILD_FIRST(2) and CATCH_GET_CHILD(16). So the directory
 *   iterator kept '.' and '..', whose inode sizes were added to the total,
 *   and the mode -- 4096 -- was not a walk mode at all, so nothing below
 *   the top level was ever counted.
 *
 * On the two-file fixture below that reported 583 bytes for a true 9.
 * Both halves are silent: an image's reported size is simply wrong, and a
 * replication size comparison against it decides on that wrong number.
 *
 * The assertion is the exact byte total, deliberately. A test that only
 * checked "greater than zero", or that grepped for the SKIP_DOTS constant,
 * passes with the flag back on the wrong constructor -- which is the whole
 * defect. Adding a file to the fixture means updating EXPECTED_TOTAL.
 *
 * Usage: php tests/getfilesize-walks-directories.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
require_once $root . '/packages/web/vendor/autoload.php';

$failures = 0;

/**
 * Report one assertion.
 *
 * @param string $label what is being asserted
 * @param bool   $ok    whether it held
 * @param string $extra detail to print on failure
 *
 * @return void
 */
function check($label, $ok, $extra = '')
{
    global $failures;
    if ($ok) {
        echo "ok   - $label\n";
        return;
    }
    $failures++;
    echo "FAIL - $label" . ($extra !== '' ? " ($extra)" : '') . "\n";
}

// a.txt 3 + sub/b.txt 6 + sub/deeper/c.txt 2. Nested two levels down so a
// walk that does not descend cannot produce the right answer by accident.
$files = [
    'a.txt' => 'abc',
    'sub/b.txt' => 'abcdef',
    'sub/deeper/c.txt' => 'ab',
];
$expectedTotal = 11;

$base = sys_get_temp_dir() . '/fog-getfilesize-' . getmypid();
foreach ($files as $rel => $content) {
    $full = $base . '/' . $rel;
    if (!is_dir(dirname($full))) {
        mkdir(dirname($full), 0700, true);
    }
    file_put_contents($full, $content);
}

/**
 * Remove the fixture tree.
 *
 * @param string $dir directory to remove
 *
 * @return void
 */
function rmtree($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

try {
    $total = \FOG\Base\FOGBase::getFilesize($base);
    check(
        "a directory totals only its files, recursively (expected $expectedTotal)",
        $total === $expectedTotal,
        "got " . var_export($total, true)
    );

    $one = \FOG\Base\FOGBase::getFilesize($base . '/a.txt');
    check(
        'a plain file still reports its own size',
        $one === 3,
        'got ' . var_export($one, true)
    );

} finally {
    rmtree($base);
}

if ($failures > 0) {
    echo "\n$failures check(s) failed\n";
    exit(1);
}
echo "\nAll checks passed\n";
exit(0);
