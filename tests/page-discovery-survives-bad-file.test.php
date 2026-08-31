<?php
/**
 * One bad page file must not take the whole admin UI down.
 *
 *   tests/page-discovery-survives-bad-file.test.php
 *
 * FOGPageManager::loadPageClasses() derives a class name from a file's path
 * and then calls get_class_vars() on it. In PHP 8 that is an
 * uncaught TypeError when no class declares the name -- thrown out of
 * FOGPageManager's constructor, which management/index.php builds before it
 * can render anything. So one file whose class does not match its name is a
 * bodyless 500 on EVERY page of the site, not just on that page's own node.
 *
 * Two ways to get there, and both are real:
 *
 *   1. The file is gone. The plugin source list is a TTL-cached snapshot
 *      (Initiator::pluginFileList) and is ALLOWED to be stale -- that is the
 *      documented design. FOGBase::startClassFromFiles() was hardened for
 *      exactly this after fog-plugins v1.6.11 dropped a hook; this consumer
 *      was not, so deleting a page file leaves the UI dead until the TTL
 *      expires rather than fixing it.
 *   2. The file is present and declares something else. Since ADR 0009 that
 *      is reachable from outside this repository: a third-party plugin
 *      uploaded through Plugin Management whose page file declares a class
 *      under a namespace nothing will look in -- `namespace FOG; class Foo`
 *      is the easy way there now that a plugin page must declare
 *      FOG\Plugins\<Segment>\Pages\<Class> (ADR 0035). The name discovery
 *      derives never exists, and the whole UI dies for everyone with `rm`
 *      plus clearing /opt/fog/cache as the only way out.
 *
 * The first is narrower than it was and the second is not. Discovery derives
 * the class name from the PATH now rather than from a basename, so a filename
 * and a class that merely disagree can no longer name a class no file
 * declares -- what is left is a file that declares something else entirely,
 * which is exactly what a wrong `namespace` line produces.
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
@mkdir($tmp . '/extplugins/probeplug/src/Pages', 0700, true);
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
 * ProbeGood is emitted LAST for that reason: a guard that threw instead of
 * continuing would never reach it, so "ProbeGood was loaded and NOT
 * complained about" is simultaneously the proof that the walk continued and
 * the proof that the guard does not over-reject a correctly laid out page.
 */
$pages = $tmp . '/extplugins/probeplug/src/Pages/';
file_put_contents(
    $pages . 'ProbeBadNS.php',
    "<?php\nnamespace FOG;\nclass ProbeBadNS { public \$node = 'probebadns'; }\n"
);
// Deliberately NOT written: ProbeMissing.php. Named by the file list and
// absent from disk, which is the stale-cache half.
file_put_contents(
    $pages . 'ProbeGood.php',
    "<?php\nnamespace FOG\\Plugins\\ProbePlug\\Pages;\n"
    . "class ProbeGood { public \$node = 'probegood'; }\n"
);

/*
 * The two lists loadPageClasses() merges are supplied rather than found:
 * walk order and file content are the inputs under test. src/ is emptied so
 * the assertions are about the three fixtures and not about the 28 real core
 * pages, which have nothing to do with this failure.
 */
$statics = [];
foreach (['srcMap', 'srcClassMap', 'pluginFileList', 'pluginSegments'] as $name) {
    $r = new \ReflectionProperty('Initiator', $name);
    $r->setAccessible(true);
    $statics[$name] = [$r, $r->getValue()];
}
$statics['srcMap'][0]->setValue(null, []);
$statics['srcClassMap'][0]->setValue(null, []);
$statics['pluginSegments'][0]->setValue(null, null);
$statics['pluginFileList'][0]->setValue(null, [
    $pages . 'ProbeBadNS.php',
    $pages . 'ProbeMissing.php',
    $pages . 'ProbeGood.php',
]);
// pluginitems() serves INSTALLED plugins only, and nothing here booted the
// database that normally populates that list.
$heldInstalled = \FOG\Base\FOGBase::$pluginsinstalled;
\FOG\Base\FOGBase::$pluginsinstalled = ['probeplug'];

// A node none of the three declares, so the walk runs to the end and
// constructs nothing.
$GLOBALS['node'] = 'probe-matches-nothing';

$errLog = ini_get('error_log');
$quiet = $tmp . '/discovery.log';
ini_set('error_log', $quiet);

// newInstanceWithoutConstructor: the real constructor does session, database
// and hook work this has nothing to say about.
$ref = new \ReflectionClass('FOG\Base\FOGPageManager');
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
foreach ($statics as list($r, $was)) {
    $r->setValue(null, $was);
}
\FOG\Base\FOGBase::$pluginsinstalled = $heldInstalled;
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
    strpos($logged, 'ProbeMissing.php') !== false
    && strpos($logged, 'no longer exists') !== false,
    $failures,
    $checks
);
// The message is asserted, not just its existence. It is the only thing a
// plugin author gets, and it used to tell them to write a class_alias() --
// advice that is now wrong, since core resolves FOG\Plugins\<Plugin>\<Class>
// directly. A diagnostic that names the wrong cure is worse than a terse one.
check(
    'the mis-declared file was reported, and the message names the fix',
    strpos($logged, 'ProbeBadNS.php') !== false
    && strpos($logged, 'does not declare') !== false
    && strpos($logged, 'FOG\\Plugins\\<Segment>\\Pages\\<Class>') !== false
    && strpos($logged, 'class_alias') === false,
    $failures,
    $checks
);
// The walk reached the third file: it was loaded (so the guard let it past)
// and nothing was logged about it (so the guard does not reject a correctly
// laid out page).
check(
    'the walk continued to the last file and loaded it',
    class_exists('FOG\\Plugins\\ProbePlug\\Pages\\ProbeGood', false),
    $failures,
    $checks
);
check(
    'a correctly laid out page drew no complaint',
    strpos($logged, 'ProbeGood.php') === false,
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
