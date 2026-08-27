<?php
/**
 * A class-file list that has gone stale must not take the site down.
 *
 * Initiator::classFileList() caches the scanned file list to FOG_CACHE_DIR
 * with a 300 second TTL. Staleness is DESIGNED IN -- forgetClassFileList()
 * exists precisely because the cache can describe a tree that has since
 * changed, and its own docblock says so. Every consumer therefore has to
 * tolerate it.
 *
 * startClassFromFiles() was the one that did not. Handed a path the cache
 * named and the tree no longer has, it ran include_once on nothing and then
 * getClass(), whose ReflectionClass throws -- uncaught, inside
 * EventManager::load(), inside LoadGlobals. That is a bodyless 500 on every
 * page for the rest of the TTL.
 *
 * Not hypothetical. An install rm -rf's the web root and rebuilds it, so a
 * plugin release that DROPS a file really removes it: fog-plugins v1.6.11
 * drops ldap/hooks/addldapapi.hook.php, and an install landing it inside the
 * TTL window fails its own "Checking web server serves FOG" probe with an
 * empty body. It then heals when the TTL expires, which is why it reads as a
 * flaky install rather than as a bug.
 *
 * Two guards, both checked here because either alone leaves a hole:
 *
 *   the installer drops $fogprogramdir/cache/filelist.*.json after replacing
 *   the web root, so the window does not exist;
 *
 *   startClassFromFiles() skips a vanished file and says so, so a stale list
 *   from any other cause -- an admin deleting a plugin by hand, an NFS lag --
 *   costs one missing listener rather than the whole server.
 *
 * Behavioural for the second, source-level for the first: the installer half
 * is one line of shell inside a 9000-line function that needs a live install
 * to run, and the comment says which is which rather than dressing one up as
 * the other.
 *
 * Usage: php tests/stale-class-file-list.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
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
 * 1. The loader tolerates a path that is no longer there.
 *
 *    Driven for real: a temp file is created, named to startClassFromFiles()
 *    exactly as the cache would name it, then deleted before the call. What
 *    is asserted is that the call RETURNS -- before the fix it threw, and a
 *    throw here is a dead server.
 */
$webroot = $root . '/packages/web';
require_once $webroot . '/commons/init.php';
new Initiator();

$tmp = sys_get_temp_dir() . '/fog-stale-filelist-' . getmypid();
@mkdir($tmp, 0700, true);
$gone = $tmp . '/nosuchhook.hook.php';
file_put_contents($gone, "<?php\n");
unlink($gone);

$threw = null;
// The error_log line is the point of the guard, so it is captured rather than
// left to scroll past: "skipped" and "skipped silently" are different answers
// and only one of them is acceptable for a listener that stops running.
$errLog = $tmp . '/php-errors.log';
$prevLog = ini_get('error_log');
ini_set('error_log', $errLog);
try {
    \FOG\Base\FOGBase::startClassFromFiles([$gone], -strlen('.hook.php'));
} catch (\Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}
ini_set('error_log', (string)$prevLog);

check(
    'a file named by the cache and since deleted does not throw'
    . (null === $threw ? '' : " (threw $threw)"),
    null === $threw,
    $failures,
    $checks
);
$logged = is_file($errLog) ? (string)file_get_contents($errLog) : '';
check(
    'the skip is reported, so a listener that stops running leaves a trace',
    false !== strpos($logged, 'startClassFromFiles')
    && false !== strpos($logged, 'nosuchhook'),
    $failures,
    $checks
);
// error_log(), not self::error(): _writeLog() is gated on
// `self::$mySchema >= FOG_SCHEMA` and a globalSettings read, and this path
// runs inside LoadGlobals during an install -- exactly when that gate is
// closed. A guard that reports nothing in the only situation it fires in is
// not a guard.
$src = (string)file_get_contents(
    $webroot . '/src/Base/FOGBase.php'
);
$body = '';
if (preg_match(
    '/function startClassFromFiles\(.*?\n    \}/s',
    $src,
    $m
)) {
    $body = $m[0];
}
check(
    'startClassFromFiles() is what was measured, and it guards on is_file',
    '' !== $body && false !== strpos($body, 'if (!is_file($file))'),
    $failures,
    $checks
);
// Matched at the start of a statement, not anywhere in the text: the
// comment above the guard NAMES self::error() to explain why it is not used,
// and a bare strpos() reads its own rationale as the thing it forbids.
check(
    'it reports through error_log(), which is not gated on the schema',
    (bool)preg_match('/\n\s*error_log\(/', $body)
    && !preg_match('/\n\s*self::error\(/', $body),
    $failures,
    $checks
);
check(
    'a file present but not declaring its class is also survivable',
    false !== strpos($body, 'catch (\\ReflectionException $e)'),
    $failures,
    $checks
);

@unlink($errLog);
@rmdir($tmp);

/*
 * 2. The installer drops the cached list after replacing the web root.
 *
 *    Source-level, and deliberately anchored to the copy it must follow: the
 *    ordering is the whole content of the fix. Dropping the cache BEFORE the
 *    new tree is in place just rebuilds it from the old one.
 */
$fn = (string)file_get_contents($root . '/lib/common/functions.sh');
$copy = strpos($fn, 'cp -Rf $webdirsrc/* $webdirdest/');
$drop = strpos($fn, 'rm -f $fogprogramdir/cache/filelist.*.json');
check(
    'the installer drops the cached class file list',
    false !== $drop,
    $failures,
    $checks
);
check(
    'it does so AFTER the new web root is in place, not before',
    false !== $copy && false !== $drop && $drop > $copy,
    $failures,
    $checks
);
// Narrow on purpose. The same directory carries the settings-cache flush
// signal that configureFOGService sets up; a blanket wipe of
// $fogprogramdir/cache would take it with them.
check(
    'it removes the file lists only, not everything in the cache directory',
    false === strpos($fn, 'rm -rf $fogprogramdir/cache/*')
    && false === strpos($fn, 'rm -f $fogprogramdir/cache/*'),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
echo "ok  $checks checks passed\n";
