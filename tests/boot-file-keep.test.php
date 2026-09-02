<?php
/**
 * Marking a boot file to be kept places the copy that IS the record.
 *
 * The pruner and the restore are shell functions running while the web root
 * is being rebuilt, with no database in reach. So the alternative to a copy
 * was a manifest for them to read -- and a copy needs no parsing and cannot
 * drift from what it describes, which is the same reasoning
 * bin/restorekernel.sh gives for using xattrs rather than a manifest.
 *
 * Two orderings matter and are pinned here:
 *
 *   - the copy is placed BEFORE the flag is written. A flag without a copy
 *     promises protection nothing delivers, and the file would be gone after
 *     the next upgrade with the record still claiming it was kept.
 *   - unpinning removes the copy BEFORE clearing the flag, for the mirror
 *     reason: a cleared flag with the copy still there leaves a file
 *     protected that the UI says is not.
 *
 * Usage: php tests/boot-file-keep.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

/**
 * BEFORE boot(), not after. commons/init.php defines FOG_BASE_DIR only when
 * nothing else has -- the same relocatability the shell side has for
 * _resolveCustomizationsDir(), and for the same reason ADR 0040 gives: a
 * hardcoded path means every test that reaches this code writes into the real
 * /opt/fog on whatever machine the suite runs on.
 *
 * Defining it afterward silently does nothing, which is how this test first
 * reported a bug that was entirely its own.
 */
$base = sys_get_temp_dir() . '/fog-keep-' . getmypid();
define('FOG_BASE_DIR', $base);

FogTestHarness::boot('boot-file-keep');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$ipxe = $base . '/service/ipxe';
$keep = $base . '/customizations/kernel-backups/keep';
mkdir($ipxe, 0755, true);
register_shutdown_function(
    function () use ($base) {
        foreach ([
            $base . '/customizations/kernel-backups/keep',
            $base . '/customizations/kernel-backups',
            $base . '/customizations',
            $base . '/service/ipxe',
            $base . '/service'
        ] as $dir) {
            foreach ((array)glob($dir . '/*') as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
    }
);

file_put_contents($ipxe . '/bzImage.20260701-093344', 'known-good-kernel');
file_put_contents($ipxe . '/bzImage', 'current-kernel');

$db->responder = function ($sql, $params) use ($ipxe) {
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        return [
            [
                'settingKey' => 'FOG_TFTP_PXE_KERNEL_DIR',
                'settingValue' => $ipxe . '/'
            ]
        ];
    }

    return null;
};

$copy = new \ReflectionMethod('FOG\Base\FOGPage', '_bootFileKeepCopy');
$copy->setAccessible(true);

// --- no directory: the installer makes it, not the web tier -------------

/**
 * Refused rather than created here. The directory is created once by the
 * installer, owned by the service user and group-writable to the web user;
 * one the web tier made for itself would be owned by the web user instead.
 */
$t->check(
    'keeping a file refuses when the installer has not made the directory',
    false === $copy->invoke(null, 'bzImage.20260701-093344', true)
);
$t->check(
    'and no copy is left behind by the attempt',
    !is_file($keep . '/bzImage.20260701-093344')
);

mkdir($keep, 0755, true);

// --- the copy ------------------------------------------------------------

$t->check(
    'keeping a file places the copy',
    true === $copy->invoke(null, 'bzImage.20260701-093344', true)
    && is_file($keep . '/bzImage.20260701-093344')
);
$t->check(
    'and the copy holds the same bytes',
    'known-good-kernel'
    === file_get_contents($keep . '/bzImage.20260701-093344')
);
$t->check(
    'keeping it again is not an error and does not disturb the copy',
    true === $copy->invoke(null, 'bzImage.20260701-093344', true)
    && 'known-good-kernel'
    === file_get_contents($keep . '/bzImage.20260701-093344')
);

// --- and the removal ----------------------------------------------------

$t->check(
    'no longer keeping it removes the copy',
    true === $copy->invoke(null, 'bzImage.20260701-093344', false)
    && !is_file($keep . '/bzImage.20260701-093344')
);
$t->check(
    'removing a copy that is not there is not an error',
    true === $copy->invoke(null, 'bzImage.20260701-093344', false)
);
$t->check(
    'the file itself is untouched by any of this',
    'known-good-kernel'
    === file_get_contents($ipxe . '/bzImage.20260701-093344')
);

// --- a name that is not there -------------------------------------------

$t->check(
    'keeping a file that does not exist is refused',
    false === $copy->invoke(null, 'no-such-kernel', true)
);
/**
 * basename() first, then the source has to be a real file in the boot
 * directory -- these endpoints turn a request parameter into a path.
 */
$t->check(
    'a traversal attempt cannot reach outside the boot directory',
    false === $copy->invoke(null, '../../../../etc/passwd', true)
);

// --- the ordering, which is what makes the flag honest ------------------

$src = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Base/FOGPage.php'
);
$at = strpos($src, 'function bootFileSetPinned(');
$body = false === $at ? '' : substr($src, $at, 1800);
$t->check(
    'the copy is attempted before the record is written',
    false !== strpos($body, '_bootFileKeepCopy(')
    && strpos($body, '_bootFileKeepCopy(') < strpos($body, "->save()")
);
$t->check(
    'and a failed copy stops the record being written at all',
    1 === preg_match(
        '/if \(!self::_bootFileKeepCopy\(\$name, \$keep\)\) \{\s*'
        . 'return false;/',
        $body
    )
);

$t->finish();
