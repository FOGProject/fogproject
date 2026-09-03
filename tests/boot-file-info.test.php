<?php
/**
 * Boot file metadata: read what can be read, and say why for the rest.
 *
 * The Kernel Versions panel printed `Unknown` for a value it could not get,
 * and at least seven unrelated problems produced that same word: no `attr`
 * binary, SELinux refusing httpd_t the exec, a mount without user_xattr, an
 * attribute genuinely never set, a permissions failure, disabled shell
 * functions, and a parse artifact from calling `attr -g` without -q. The
 * cause went to stderr and was discarded, so an admin could not tell which
 * of those to go and fix.
 *
 * Two halves to the fix, and this pins both:
 *
 *   - the kernel VERSION no longer depends on the shell at all. It is read
 *     from the offset the image's own setup header points at, so it works
 *     under SELinux, on a filesystem with no xattr support, and on a server
 *     where `attr` was never installed.
 *   - the FOS RELEASE tag cannot escape that, because it exists only as an
 *     extended attribute and PHP has no xattr reader. So it answers with a
 *     reason instead of an empty string, and a tag once read is kept in the
 *     record rather than re-derived -- on a server where the read never
 *     works, a value stored at download time is the only copy there will be.
 *
 * DB-free: the fake DB answers the directory setting. The record store is
 * unreachable here, which is itself worth asserting -- a listing has to
 * render without it.
 *
 * Usage: php tests/boot-file-info.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('boot-file-info');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$dir = dirname(FOG_CACHE_DIR) . '/service/ipxe';
mkdir($dir, 0755, true);

$banner = '6.6.30 (fos@buildroot) #1 SMP Wed Aug 6 11:10:46 UTC 2026';
$kernel = str_repeat("\x00", 4096);
$kernel = substr_replace($kernel, 'MZ', 0, 2);
// A real PE header and a current boot protocol: HdrS alone is also true of
// grub.exe and memdisk. See tests/boot-file-roles.test.php.
$kernel = substr_replace($kernel, pack('V', 0x40), 0x3c, 4);
$kernel = substr_replace($kernel, "PE\x00\x00", 0x40, 4);
$kernel = substr_replace($kernel, 'HdrS', 0x202, 4);
$kernel = substr_replace($kernel, pack('v', 0x020f), 0x206, 2);
$kernel = substr_replace($kernel, pack('v', 0x100), 0x20e, 2);
$kernel = substr_replace($kernel, $banner . "\x00", 0x300, strlen($banner) + 1);
file_put_contents($dir . '/bzImage', $kernel);

$armKernel = str_repeat("\x00", 4096);
$armKernel = substr_replace($armKernel, 'MZ', 0, 2);
$armKernel = substr_replace($armKernel, pack('V', 0x40), 0x3c, 4);
$armKernel = substr_replace($armKernel, "PE\x00\x00", 0x40, 4);
$armKernel = substr_replace($armKernel, 'ARMd', 0x38, 4);
file_put_contents($dir . '/arm_Image', $armKernel);

file_put_contents($dir . '/init.xz', "\xfd" . '7zXZ' . "\x00" . str_repeat('z', 512));

$db->responder = function ($sql, $params) use ($dir) {
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        return [
            [
                'settingKey' => 'FOG_TFTP_PXE_KERNEL_DIR',
                'settingValue' => $dir . '/'
            ]
        ];
    }

    return null;
};

$page = 'FOG\Base\FOGPage';

// --- the xattr reader's contract -----------------------------------------

/**
 * Deliberately environment-agnostic. Whether `attr` exists on the machine
 * running this suite is not the point; the contract is that a value and a
 * reason are never both empty, and never both present. That is the whole
 * difference from the old behavior, where "no value" carried no reason.
 */
$tag = $page::bootFileXattr($dir . '/bzImage', 'tag_name');
// No is_array() guard: the method is declared @return array, so phpstan
// proves that branch always true and fails the tests pass on it.
$t->check(
    'the xattr reader answers value and reason',
    array_key_exists('value', $tag) && array_key_exists('reason', $tag)
);
$t->check(
    'a value and a reason are mutually exclusive, and one is always given',
    ('' === $tag['value']) !== ('' === $tag['reason'])
);
$missing = $page::bootFileXattr($dir . '/not-here', 'tag_name');
$t->check(
    'a file that does not exist gets a reason, not an empty answer',
    '' === $missing['value'] && '' !== $missing['reason']
);

// --- the version, which must not depend on the shell ---------------------

$info = $page::bootFileInfo($dir . '/bzImage');
$t->check(
    'an x86 kernel reports the banner from its own header',
    $banner === $info['kernelVersion']
);
$t->check(
    'the role travels with the metadata',
    'kernel' === $info['role']
);
$t->check(
    'the file is reported as present',
    true === $info['exists']
);
$t->check(
    'the size is the real size',
    4096 === $info['size']
);
/**
 * mtime, not ctime. The old panel used ctime and called it "Installed
 * Date" -- and restorePreservedCustomizations() chowns this whole directory
 * on every install, which moves ctime on files it never touched. So every
 * file claimed to have been installed on the date of the last upgrade.
 */
$t->check(
    'the timestamp is the modification time, not the inode change time',
    filemtime($dir . '/bzImage') === $info['mtime']
);
$t->check(
    'a checksum is recorded, so two names can be known to be one kernel',
    hash_file('sha256', $dir . '/bzImage') === $info['checksum']
);

$armInfo = $page::bootFileInfo($dir . '/arm_Image');
$t->check(
    'an arm64 kernel is still classified, with no version to report',
    'kernel' === $armInfo['role'] && '' === $armInfo['kernelVersion']
);

$initInfo = $page::bootFileInfo($dir . '/init.xz');
$t->check(
    'an init is classified from its archive magic',
    'init' === $initInfo['role']
);

// --- a value that cannot be read says why --------------------------------

$t->check(
    'an unreadable release tag carries a reason instead of "Unknown"',
    '' === $info['releaseTag'] ? '' !== $info['tagReason'] : true
);
$t->check(
    'a readable release tag carries no reason',
    '' !== $info['releaseTag'] ? '' === $info['tagReason'] : true
);

// --- a missing file, and an unreachable record store ---------------------

$gone = $page::bootFileInfo($dir . '/never-existed');
$t->check(
    'a file that does not exist is reported as absent, not as an error',
    false === $gone['exists']
    && 'unclassified' === $gone['role']
    && 0 === $gone['size']
);

/**
 * The records are an accelerator and a place to keep an admin's pin, not the
 * inventory. This harness has no bootFile table at all, so every read and
 * write against it fails -- and the listing still has to answer.
 */
$t->check(
    'the listing renders with the record store unreachable',
    ['bzImage'] === $page::kernelFileList('kernel')
    || in_array('bzImage', $page::kernelFileList('kernel'), true)
);
$t->check(
    'metadata is stable across calls when the file has not changed',
    $page::bootFileInfo($dir . '/bzImage') == $info
);

// --- a stored record is USED, not re-derived -----------------------------

/**
 * The map of bootFile rows was never populated: the loader called a 1.5
 * manager method that does not exist on 1.6, threw into its own catch, and
 * every host and group page re-read, re-hashed and re-saved every file in
 * the boot directory -- 264MB and ~1.8s on a stock install. The catch is
 * there for an unreachable store, so nothing else could see the difference
 * between "no rows" and "the query never ran".
 *
 * So answer the store here, with a row that is fresh for bzImage (same size,
 * same mtime) and carries a checksum no hash could produce. A loader that
 * reads the row serves that checksum; one that never gets the row re-hashes
 * the file and reports the real digest. The row is deliberately NOT stale:
 * a stale row is re-inspected by design, so it could not tell the two apart.
 */
$cached = 'served-from-the-record-not-the-file';
$db->responder = function ($sql, $params) use ($dir, $cached) {
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        return [
            [
                'settingKey' => 'FOG_TFTP_PXE_KERNEL_DIR',
                'settingValue' => $dir . '/'
            ]
        ];
    }
    if (false !== stripos($sql, 'FROM `bootFile`')) {
        $row = [
                'bfID' => 7,
                'bfName' => 'bzImage',
                'bfSize' => 4096,
                'bfMtime' => gmdate('Y-m-d H:i:s', filemtime($dir . '/bzImage')),
                'bfChecksum' => $cached,
                'bfRole' => 'kernel',
                'bfKernelVersion' => 'cached',
                'bfReleaseTag' => 'cached-tag',
                'bfInspected' => gmdate('Y-m-d H:i:s'),
                'bfPinned' => 0
        ];
        // load() reads its by-id SELECT through fetch()->get() with no
        // field -- ONE flat row -- where the id listing wants a list of
        // rows. Same distinction tests/route-write-path-guards.test.php
        // draws; a wrapped row here is not an error, it is an empty record.
        return preg_match('/WHERE `bfID`=:id\b/', $sql) ? $row : [$row];
    }

    return null;
};
// The per-request map was filled (with nothing) by the calls above.
FogTestHarness::setStatic($page, '_bootFileRows', null);
$writesBefore = count(
    preg_grep('/^\s*(INSERT|UPDATE)\b.*`bootFile`/i', $db->log)
);
$fromRow = $page::bootFileInfo($dir . '/bzImage');
$writesAfter = count(
    preg_grep('/^\s*(INSERT|UPDATE)\b.*`bootFile`/i', $db->log)
);
$t->check(
    'a fresh record answers for the file: the checksum is the stored one',
    $cached === $fromRow['checksum']
);
$t->check(
    'a fresh record is not rewritten on read',
    $writesBefore === $writesAfter
);

// --- the panel no longer hardcodes six names or drops stderr -------------

$panel = file_get_contents(
    dirname(__DIR__) . '/packages/web/status/kernelvers.php'
);
$t->check(
    'the panel reads the configured directory, not BASEPATH/service/ipxe',
    false !== strpos($panel, 'FOG_TFTP_PXE_KERNEL_DIR')
    && false === strpos($panel, "BASEPATH, DS, DS, DS")
);
// The call form, not the word: the file's own docblock names both of these
// while explaining what it used to do, and that prose is the point.
$t->check(
    'the panel no longer shells out to attr itself',
    false === strpos($panel, "'attr -q -g")
    && false === strpos($panel, 'shell_exec(')
);
$t->check(
    'the panel no longer joins its values with a pipe for the browser to split',
    false === strpos($panel, "'|'")
);
$t->check(
    'the panel reports every boot file rather than six fixed names',
    false === strpos($panel, "'bzImage32'")
    && false === strpos($panel, "'arm_init.cpio.gz'")
);

$home = file_get_contents(
    dirname(__DIR__)
    . '/packages/web/management/js/fog/about/fog.about.home.js'
);
$t->check(
    'the browser guards the parse, so an unreachable node is not a blank panel',
    false !== strpos($home, 'try {')
    && false !== strpos($home, 'JSON.parse')
);
$t->check(
    'the browser escapes what the node sent it',
    false !== strpos($home, 'function esc(')
);

$t->finish();
