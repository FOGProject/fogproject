<?php
/**
 * A boot directory file's role is decided by reading it, not by its name.
 *
 * The Host Kernel dropdown offered memdisk, memtest.bin and grub.exe because
 * FOGPage::kernelFileList() had no positive test for a kernel -- it knew what
 * an init looked like and called everything else a kernel, behind a blacklist
 * of extensions. Two consequences, both reported:
 *
 *   - memdisk/memtest.bin/grub.exe are boot payloads. They were kept in the
 *     kernel list on purpose, because the SAME list feeds FOG_MEMTEST_KERNEL,
 *     which legitimately points at them. One list, two meanings.
 *   - a blacklist cannot be completed. `.efi` covered refind.efi; an old
 *     backup script leaving refind.efi.new behind walked straight through it.
 *
 * So the fixtures below are built to the real on-disk shapes and the role is
 * asserted for each. The two that matter most are the pair a name-based rule
 * gets wrong in OPPOSITE directions: bzImage_MyHardware is a hand-compiled
 * kernel under a name FOG never ships and must be offered, while
 * bzImage.unsigned holds genuine kernel bytes and must NOT be -- it is a
 * _resignKernels() working file, not something to boot.
 *
 * The previous round of this bug was caught by rendering the helper against a
 * real service/ipxe rather than reasoning about what lives there. This is that
 * directory, as a fixture.
 *
 * DB-free: the fake DB answers the one globalSettings read for the directory.
 *
 * Usage: php tests/boot-file-roles.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('boot-file-roles');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * An x86/x86_64 bzImage: PE for the EFI stub, plus the setup header magic at
 * 0x202 and, when a version is wanted, the 16-bit offset at 0x20e that says
 * where the version banner lives (relative to 0x200).
 *
 * @param string $version banner to embed, or '' for none
 *
 * @return string
 */
function fixtureX86Kernel($version = '')
{
    $buf = str_repeat("\x00", 4096);
    $buf = substr_replace($buf, 'MZ', 0, 2);
    $buf = substr_replace($buf, 'HdrS', 0x202, 4);
    if ($version !== '') {
        // 0x300 in the file is 0x100 past the 0x200 the field is relative to.
        $buf = substr_replace($buf, pack('v', 0x100), 0x20e, 2);
        $buf = substr_replace(
            $buf,
            $version . "\x00",
            0x300,
            strlen($version) + 1
        );
    }

    return $buf;
}

/**
 * An arm64 Image: header magic 'ARMd' at 0x38, and PE as well because the
 * EFI stub is built in. No version field exists in this header.
 *
 * @return string
 */
function fixtureArmKernel()
{
    $buf = str_repeat("\x00", 4096);
    $buf = substr_replace($buf, 'MZ', 0, 2);
    $buf = substr_replace($buf, 'ARMd', 0x38, 4);

    return $buf;
}

/**
 * A PE executable that is NOT a kernel: grub.exe, refind*.efi, and the .new
 * copy an old backup script left behind. This is the shape a PE-only check
 * would wrongly call a kernel.
 *
 * @return string
 */
function fixturePeBinary()
{
    return 'MZ' . str_repeat('X', 4094);
}

$dir = dirname(FOG_CACHE_DIR) . '/service/ipxe';
mkdir($dir, 0755, true);
// The TFTP uploader's backup directory. A directory, so nothing may list it.
mkdir($dir . '/backup', 0755, true);
file_put_contents($dir . '/backup/bzImage_20260806_111046', 'old');

$kernelVersion = '6.6.30 (fos@buildroot) #1 SMP Wed Aug 6 11:10:46 UTC 2026';

$fixtures = [
    // name => [bytes, expected role]
    'bzImage' => [fixtureX86Kernel($kernelVersion), 'kernel'],
    'bzImage32' => [fixtureX86Kernel('6.6.30 (fos@buildroot) #1 SMP'), 'kernel'],
    'bzImage.20260701-093344' => [fixtureX86Kernel('6.6.12'), 'kernel'],
    'bzImage_MyHardware' => [fixtureX86Kernel('6.1.99-custom'), 'kernel'],
    'arm_Image' => [fixtureArmKernel(), 'kernel'],
    'init.xz' => ["\xfd" . '7zXZ' . "\x00" . str_repeat('z', 512), 'init'],
    'init_32.xz' => ["\xfd" . '7zXZ' . "\x00" . str_repeat('z', 512), 'init'],
    'init.xz.20260701-093344' => [
        "\xfd" . '7zXZ' . "\x00" . str_repeat('z', 512),
        'init'
    ],
    'arm_init.cpio.gz' => ["\x1f\x8b" . str_repeat('g', 512), 'init'],
    'customInit.lz4' => ["\x04\x22\x4d\x18" . str_repeat('l', 512), 'init'],
    'memdisk' => [str_repeat('D', 4096), 'payload'],
    'memtest.bin' => [str_repeat('T', 4096), 'payload'],
    'grub.exe' => [fixturePeBinary(), 'payload'],
    'refind.efi' => [fixturePeBinary(), 'payload'],
    'refind_x64.efi' => [fixturePeBinary(), 'payload'],
    'refind.efi.new' => [fixturePeBinary(), 'payload'],
    'bzImage.unsigned' => [fixtureX86Kernel('6.6.30'), 'unclassified'],
    'refind.conf' => ["timeout 20\n", 'unclassified'],
    'boot.php' => ['<?php // boot', 'unclassified'],
    'advanced.php' => ['<?php // advanced', 'unclassified'],
    'index.php' => ['<?php // index', 'unclassified'],
    'bg.png' => ["\x89PNG\r\n\x1a\n" . str_repeat('p', 64), 'unclassified'],
];

foreach ($fixtures as $name => $spec) {
    file_put_contents($dir . '/' . $name, $spec[0]);
}

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

// --- the role of every file in the directory ------------------------------

foreach ($fixtures as $name => $spec) {
    $got = $page::bootFileRole($dir . '/' . $name);
    $t->check(
        sprintf('%s is a %s (got %s)', $name, $spec[1], $got),
        $spec[1] === $got
    );
}

$t->check(
    'a directory is not a boot file',
    'unclassified' === $page::bootFileRole($dir . '/backup')
);
$t->check(
    'a file that does not exist is not a boot file',
    'unclassified' === $page::bootFileRole($dir . '/nope')
);

// --- what each dropdown is actually offered ------------------------------

$kernels = $page::kernelFileList('kernel');
$inits = $page::kernelFileList('init');
$payloads = $page::kernelFileList('payload');

/**
 * The reported bug, stated as an assertion. These four were offered as host
 * kernels; three of them deliberately, because one list served both the boot
 * kernel fields and FOG_MEMTEST_KERNEL.
 */
foreach (['memdisk', 'memtest.bin', 'grub.exe', 'refind.efi.new'] as $notK) {
    $t->check(
        $notK . ' is not offered as a kernel',
        !in_array($notK, $kernels, true)
    );
}
$t->check(
    'refind.efi and its arch siblings are not offered as kernels',
    !in_array('refind.efi', $kernels, true)
    && !in_array('refind_x64.efi', $kernels, true)
);
$t->check(
    'a resign working file is not offered as a kernel',
    !in_array('bzImage.unsigned', $kernels, true)
);
$t->check(
    'the web assets sharing the directory are not offered as kernels',
    !in_array('boot.php', $kernels, true)
    && !in_array('index.php', $kernels, true)
    && !in_array('bg.png', $kernels, true)
    && !in_array('refind.conf', $kernels, true)
);
$t->check(
    'the TFTP backup directory is not offered as a kernel',
    !in_array('backup', $kernels, true)
);

$gotKernels = $kernels;
sort($gotKernels);
$wantKernels = [
    'arm_Image',
    'bzImage',
    'bzImage.20260701-093344',
    'bzImage32',
    'bzImage_MyHardware'
];
sort($wantKernels);
$t->check(
    'the kernels are offered, custom names and per-release siblings included',
    $gotKernels === $wantKernels
);

$t->check(
    'no init is offered as a kernel',
    !in_array('init.xz', $kernels, true)
    && !in_array('arm_init.cpio.gz', $kernels, true)
);

$t->check(
    'the inits are offered, including a versioned sibling and lz4',
    5 === count($inits)
    && in_array('init.xz', $inits, true)
    && in_array('init_32.xz', $inits, true)
    && in_array('init.xz.20260701-093344', $inits, true)
    && in_array('arm_init.cpio.gz', $inits, true)
    && in_array('customInit.lz4', $inits, true)
);
$t->check(
    'no kernel is offered as an init',
    !in_array('bzImage', $inits, true)
    && !in_array('arm_Image', $inits, true)
);

/**
 * FOG_MEMTEST_KERNEL points at these, so narrowing the kernel list must not
 * cost the payload field its options -- that would trade one bug for another.
 */
$t->check(
    'the payload field still offers memdisk, memtest.bin and grub.exe',
    in_array('memdisk', $payloads, true)
    && in_array('memtest.bin', $payloads, true)
    && in_array('grub.exe', $payloads, true)
);
$t->check(
    'refind.efi.new is a payload, not hidden -- it can still be chained',
    in_array('refind.efi.new', $payloads, true)
    && in_array('refind.efi', $payloads, true)
);
$t->check(
    'no kernel or init is offered as a payload',
    !in_array('bzImage', $payloads, true)
    && !in_array('init.xz', $payloads, true)
);

// --- ordering -------------------------------------------------------------

$t->check(
    'a plain name sorts above its per-release sibling',
    array_search('bzImage', $kernels, true)
    < array_search('bzImage.20260701-093344', $kernels, true)
);

// --- the version banner --------------------------------------------------

$t->check(
    'an x86 kernel reports the version recorded in its own setup header',
    $kernelVersion === $page::bootFileKernelVersion($dir . '/bzImage')
);
$t->check(
    'an arm64 kernel reports no version rather than inventing one',
    '' === $page::bootFileKernelVersion($dir . '/arm_Image')
);
$t->check(
    'a payload reports no version',
    '' === $page::bootFileKernelVersion($dir . '/grub.exe')
);
$t->check(
    'a kernel with no version field reports none',
    '' === $page::bootFileKernelVersion($dir . '/memdisk')
);

// --- an unreadable directory still leaves the field editable -------------

$db->responder = function ($sql, $params) {
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        return [
            [
                'settingKey' => 'FOG_TFTP_PXE_KERNEL_DIR',
                'settingValue' => '/nonexistent/service/ipxe/'
            ]
        ];
    }

    return null;
};
FogTestHarness::setStatic('FOGBase', '_settingsCache', []);
$t->check(
    'a missing directory answers an empty list, not an error',
    [] === $page::kernelFileList('kernel')
);

$t->finish();
