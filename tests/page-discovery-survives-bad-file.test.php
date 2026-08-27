<?php
/**
 * One bad page file must not take the whole admin UI down.
 *
 *   tests/page-discovery-survives-bad-file.test.php
 *
 * FOGPageManager::loadPageClasses() derives a class name from a file's
 * basename and then calls get_class_vars() on it. In PHP 8 that is an
 * uncaught TypeError when no class declares the name -- thrown out of
 * FOGPageManager's constructor, which management/index.php builds before it
 * can render anything. So one file whose class does not match its name is a
 * bodyless 500 on EVERY page of the site, not just on that page's own node.
 *
 * Two ways to get there, and both are real:
 *
 *   1. The file is gone. The class-file list is a TTL-cached snapshot
 *      (Initiator::classFileList) and is ALLOWED to be stale -- that is the
 *      documented design. FOGBase::startClassFromFiles() was hardened for
 *      exactly this after fog-plugins v1.6.11 dropped a hook; this consumer
 *      was not, so deleting a page file leaves the UI dead until the TTL
 *      expires rather than fixing it.
 *   2. The file is present and declares something else. Since ADR 0009 that
 *      is reachable from outside this repository: a third-party plugin
 *      uploaded through Plugin Management whose page file declares
 *      `namespace FOG; class Foo` with no class_alias back to the global
 *      name. The bare name never exists, and the whole UI dies for everyone
 *      with `rm` plus clearing /opt/fog/cache as the only way out.
 *
 * Observed both ways while verifying the PSR-4 move.
 *
 * EXECUTED, not read. A textual check passes on a guard that names the right
 * things and gets the condition backwards, and the condition is the whole of
 * this. loadPageClasses() is driven directly with a synthetic file list --
 * walk order and file content are the inputs under test, so they are supplied
 * rather than found -- and the manager is built without its constructor so no
 * database is needed.
 *
 * Usage: php tests/page-discovery-survives-bad-file.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$init = $root . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-page-discovery-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/extplugins', 0700, true);
@mkdir($tmp . '/pages', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);

if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/extplugins');
}

require_once $init;
new Initiator();

$failures = [];
$checks = 0;
function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/*
 * Three page files, one of each kind, and a $node that matches none of them.
 *
 * Nothing is CONSTRUCTED here on purpose: _register() requires a real
 * FOGPage subclass, whose constructor wants a session and a database, and
 * this test has nothing to say about either. Every assertion below is about
 * the walk -- did it survive each bad file, did it keep going, and did it
 * leave a trace -- which is exactly what the guard decides.
 *
 * probealiased is emitted LAST for that reason: a guard that threw instead of
 * continuing would never reach it, so "probealiased was loaded and NOT
 * complained about" is simultaneously the proof that the walk continued and
 * the proof that the guard does not over-reject the forward-compatible
 * spelling ADR 0013 tells plugin authors to use.
 */
$pages = $tmp . '/pages/';
file_put_contents(
    $pages . 'probebadns.page.php',
    "<?php\nnamespace FOG;\nclass probebadns { public \$node = 'probebadns'; }\n"
);
// Deliberately NOT written: probemissing.page.php. Named by the file list and
// absent from disk, which is the stale-cache half.
file_put_contents(
    $pages . 'probealiased.page.php',
    "<?php\nnamespace FOG;\nclass probealiased { public \$node = 'probealiased'; }\n"
    . "class_alias(__NAMESPACE__ . '\\probealiased', 'probealiased');\n"
);

$fileList = new \ReflectionProperty('Initiator', 'fileList');
$fileList->setAccessible(true);
$held = $fileList->getValue();
$fileList->setValue(null, [
    $pages . 'probebadns.page.php',
    $pages . 'probemissing.page.php',
    $pages . 'probealiased.page.php',
]);
$classMap = new \ReflectionProperty('Initiator', 'classMap');
$classMap->setAccessible(true);
$classMap->setValue(null, null);

// A node none of the three declares, so the walk runs to the end and
// constructs nothing.
$GLOBALS['node'] = 'probe-matches-nothing';

$errLog = ini_get('error_log');
$quiet = $tmp . '/discovery.log';
ini_set('error_log', $quiet);

// newInstanceWithoutConstructor: the real constructor does session, database
// and hook work this has nothing to say about.
$ref = new \ReflectionClass('FOG\FOGPageManager');
$mgr = $ref->newInstanceWithoutConstructor();
$nodes = $ref->getProperty('_nodes');
$nodes->setAccessible(true);
$nodes->setValue($mgr, []);

$threw = null;
try {
    $mgr->loadPageClasses();
} catch (\Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}

ini_set('error_log', (string)$errLog);
$fileList->setValue(null, $held);
$classMap->setValue(null, null);
$logged = is_readable($quiet) ? (string)file_get_contents($quiet) : '';

check(
    'loadPageClasses() survives a page file that declares no such class'
    . ($threw ? " (threw $threw)" : ''),
    $threw === null,
    $failures,
    $checks
);
check(
    'it survives a file the cached list names but disk no longer has',
    $threw === null,
    $failures,
    $checks
);
check(
    'the vanished file was reported, not silently skipped',
    strpos($logged, 'probemissing.page.php') !== false
    && strpos($logged, 'no longer exists') !== false,
    $failures,
    $checks
);
check(
    'the mis-declared file was reported, and the message names class_alias',
    strpos($logged, 'probebadns.page.php') !== false
    && strpos($logged, 'does not declare') !== false
    && strpos($logged, 'class_alias') !== false,
    $failures,
    $checks
);
// The walk reached the third file: it was loaded (so the guard let it past)
// and nothing was logged about it (so the guard did not reject the
// namespaced-plus-alias spelling, which is what ADR 0013 tells plugin
// authors to write).
check(
    'the walk continued to the last file and loaded it',
    class_exists('probealiased', false),
    $failures,
    $checks
);
check(
    'a namespaced page that aliases itself back drew no complaint',
    strpos($logged, 'probealiased.page.php') === false,
    $failures,
    $checks
);

if ($failures) {
    fwrite(STDERR, sprintf("FAIL (%d of %d):\n", count($failures), $checks));
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
fwrite(STDERR, sprintf("ok  %d checks passed\n", $checks));
exit(0);
