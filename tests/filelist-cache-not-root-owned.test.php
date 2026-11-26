<?php
/**
 * A root process never leaves a file-list cache the web user cannot use.
 *
 * The FOG daemons run as root and load the same Initiator as the web tier.
 * _writeFileListCache() used to write srcmap.*.json and pluginsrc.*.json as
 * root:root 0600 in the sticky, world-writable FOG_CACHE_DIR. The web user
 * could neither read that copy nor replace it. So every web request
 * rescanned src/ and the plugin roots until the next installer run.
 *
 * As root, the function skips the write. It removes a root-owned copy. It
 * keeps a copy that another user owns. As any other user, the write lands,
 * 0600. A non-root runner checks the root half again under `unshare -r`,
 * where it is uid 0. That namespace maps no other user, so only real root
 * checks the "another user" case.
 *
 * Usage: php tests/filelist-cache-not-root-owned.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$init = dirname(__DIR__) . '/packages/web/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}
$nsRoot = in_array('--as-ns-root', $argv, true);

$tmp = sys_get_temp_dir() . '/fog-cache-owner-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        foreach (glob($tmp . '/{cache,log}/*', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($tmp . '/cache');
        @rmdir($tmp . '/log');
        @rmdir($tmp);
    }
);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');

require_once $init;

$write = new ReflectionMethod('Initiator', '_writeFileListCache');
$write->setAccessible(true);

$failures = [];
$checks = 0;
function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

$file = $tmp . '/cache/srcmap.test.json';
$root = trim((string) shell_exec('id -u')) === '0';

if ($root) {
    // A copy an older release left as root goes, and nothing replaces it.
    file_put_contents($file, '{"old":"x"}');
    $write->invoke(null, $file, ['a' => '/x']);
    clearstatcache();
    check('root removes its own old copy', !file_exists($file), $failures, $checks);
    check(
        'root leaves no temp file',
        glob($tmp . '/cache/*.tmp') === [],
        $failures,
        $checks
    );
    if (!$nsRoot) {
        // The web user's copy stays.
        file_put_contents($file, '{"web":"x"}');
        if (!chown($file, 'nobody')) {
            echo "SKIP: no user 'nobody' to own the web user's copy\n";
        } else {
            $write->invoke(null, $file, ['a' => '/x']);
            clearstatcache();
            check(
                'root keeps a copy another user owns',
                is_file($file) && file_get_contents($file) === '{"web":"x"}',
                $failures,
                $checks
            );
        }
    }
} else {
    $write->invoke(null, $file, ['a' => '/x']);
    clearstatcache();
    check('a non-root write lands', is_file($file), $failures, $checks);
    check(
        'a non-root write is 0600',
        is_file($file) && (fileperms($file) & 0777) === 0600,
        $failures,
        $checks
    );
    // The root half, as uid 0 in a user namespace.
    exec('unshare -r true 2>/dev/null', $out, $rc);
    if ($rc !== 0) {
        echo "SKIP: no user namespaces, so the root half did not run\n";
    } else {
        passthru(
            'unshare -r ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(__FILE__) . ' --as-ns-root',
            $rc
        );
        check('the root half passes under unshare -r', $rc === 0, $failures, $checks);
    }
}

foreach ($failures as $f) {
    echo "  FAIL  $f\n";
}
echo ($checks - count($failures)) . " passed, " . count($failures) . " failed\n";
exit($failures ? 1 : 0);
