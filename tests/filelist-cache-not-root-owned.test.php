<?php
/**
 * A root process never leaves a file-list cache the web user cannot use.
 *
 * The FOG daemons run as root and load the same Initiator as the web tier.
 * _writeFileListCache() used to write srcmap.*.json and pluginsrc.*.json as
 * root:root 0600 in the sticky, world-writable FOG_CACHE_DIR. The web user
 * could neither read that copy nor replace it, so every web request
 * rescanned src/ and the plugin roots until the next installer run.
 *
 * As root: the write is skipped, a root-owned copy is removed, and a copy
 * another user owns is kept. As any other user: the write lands, 0600.
 * The root half runs only under root, for example with sudo.
 *
 * Usage: php tests/filelist-cache-not-root-owned.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$init = dirname(__DIR__) . '/packages/web/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

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
$root = function_exists('posix_geteuid')
    ? posix_geteuid() === 0
    : trim((string) shell_exec('id -u')) === '0';

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
    // The web user's copy stays.
    file_put_contents($file, '{"web":"x"}');
    chown($file, 'nobody');
    $write->invoke(null, $file, ['a' => '/x']);
    clearstatcache();
    check(
        'root keeps a copy another user owns',
        is_file($file) && file_get_contents($file) === '{"web":"x"}',
        $failures,
        $checks
    );
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
    echo "  skip  root half: run with sudo to check it\n";
}

foreach ($failures as $f) {
    echo "  FAIL  $f\n";
}
echo ($checks - count($failures)) . " passed, " . count($failures) . " failed\n";
exit($failures ? 1 : 0);
