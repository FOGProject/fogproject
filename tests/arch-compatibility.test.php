<?php
/**
 * A deploy is refused only when the host positively cannot run the image.
 *
 * Image::archCanRun() is the single authority for that question, and it is a
 * COMPATIBILITY test, not an equality test. The distinction is the whole
 * reason the method exists:
 *
 *   - 32-bit x86 code runs on a 64-bit x86 CPU, so an i386 image onto an
 *     x86_64 host is a legitimate deployment that a `!==` comparison would
 *     wrongly refuse. Genuine i386 hosts are 32-bit-only hardware -- iPXE's
 *     cpuid promotion reports x86_64 for anything 64-bit capable -- so this
 *     case is rare, which is exactly why a later "simplification" back to
 *     inequality would pass review and break somebody's lab of old machines.
 *
 *   - ARM and x86 are different instruction sets in BOTH directions, so
 *     neither can substitute for the other.
 *
 *   - UNKNOWN on either side is allowed. Every image captured before schema
 *     step 370 has no architecture and every host that has not PXE booted
 *     since step 369 has none either; refusing on absence would turn an
 *     upgrade into a fleet-wide outage. A refusal must rest on two observed
 *     facts, never on a missing one.
 *
 * The same rule is stated in prose in BootMenu::_fileFitsArch()'s docblock
 * for kernel overrides. Two copies of a rule drift, so the pin below also
 * checks the caller wiring is present: the field mappings that give the
 * relation something to read, and the refusal site that acts on it.
 *
 * DB-free: pure statics plus source reads.
 *
 * Usage: php tests/arch-compatibility.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require_once $web . '/lib/fog/fogbase.class.php';
require_once $web . '/lib/fog/fogcontroller.class.php';
require_once $web . '/lib/fog/image.class.php';

$t = new FogChecks();

// --- the compatibility matrix ---------------------------------------------
// image arch, host arch, may it proceed, why
$matrix = [
    // Same architecture, always fine.
    ['x86_64', 'x86_64', true,  'identical x86_64'],
    ['i386',   'i386',   true,  'identical i386'],
    ['arm64',  'arm64',  true,  'identical arm64'],

    // The one asymmetric pair.
    ['i386',   'x86_64', true,  '32-bit image on 64-bit x86 hardware runs'],
    ['x86_64', 'i386',   false, '64-bit image cannot run on a 32-bit-only CPU'],

    // Different instruction sets, refused both ways.
    ['x86_64', 'arm64',  false, 'x86_64 image on an ARM host'],
    ['i386',   'arm64',  false, 'i386 image on an ARM host'],
    ['arm64',  'x86_64', false, 'arm64 image on an x86_64 host'],
    ['arm64',  'i386',   false, 'arm64 image on an i386 host'],
];
foreach ($matrix as list($img, $host, $want, $why)) {
    $got = \FOG\Image::archCanRun($img, $host);
    $t->check(
        sprintf(
            'archCanRun(%s image -> %s host) is %s -- %s',
            $img,
            $host,
            $want ? 'allowed' : 'refused',
            $why
        ),
        $got === $want
    );
}

// --- unknown is never a refusal -------------------------------------------
// Guarding upgrade day: pre-369 hosts and pre-370 images are all NULL here,
// and a strict reading would refuse every deploy on the server.
$unknowns = ['', null, '   '];
foreach ($unknowns as $i => $blank) {
    $shown = var_export($blank, true);
    $t->check(
        "unknown image arch ($shown) against a known host is allowed",
        \FOG\Image::archCanRun($blank, 'arm64') === true
    );
    $t->check(
        "unknown host arch ($shown) against a known image is allowed",
        \FOG\Image::archCanRun('x86_64', $blank) === true
    );
}
$t->check(
    'both sides unknown is allowed',
    \FOG\Image::archCanRun('', '') === true
);

// --- normalisation --------------------------------------------------------
// FOS reports uname -m; iPXE reports ${buildarch}. Only iPXE's spelling is
// stored, so anything arriving from elsewhere has to fold onto it or an
// arm64 host would read as incompatible with its own arm64 image.
$aliases = [
    'aarch64' => 'arm64',
    'amd64' => 'x86_64',
    'i486' => 'i386',
    'i586' => 'i386',
    'i686' => 'i386',
    'X86_64' => 'x86_64',
    '  arm64  ' => 'arm64',
];
foreach ($aliases as $raw => $want) {
    $t->check(
        sprintf("normalizeArch('%s') is '%s'", $raw, $want),
        \FOG\Image::normalizeArch($raw) === $want
    );
}
$t->check(
    'normalizeArch leaves an unknown architecture alone rather than guessing',
    \FOG\Image::normalizeArch('riscv64') === 'riscv64'
);
$t->check(
    'an aarch64 image is compatible with an arm64 host after normalisation',
    \FOG\Image::archCanRun('aarch64', 'arm64') === true
);

// --- the wiring the relation depends on -----------------------------------
// Without these the method is correct and never consulted.
$hostSrc = (string)file_get_contents($web . '/lib/fog/host.class.php');
$imageSrc = (string)file_get_contents($web . '/lib/fog/image.class.php');
$schemaSrc = (string)file_get_contents($web . '/commons/schema.php');
$queueSrc = (string)file_get_contents($web . '/lib/reg-task/taskqueue.class.php');
$menuSrc = (string)file_get_contents($web . '/lib/fog/bootmenu.class.php');

$t->check(
    "Host maps 'arch' to hostArch",
    (bool)preg_match("/'arch'\s*=>\s*'hostArch'/", $hostSrc)
);
$t->check(
    "Image maps 'arch' to imageArch",
    (bool)preg_match("/'arch'\s*=>\s*'imageArch'/", $imageSrc)
);
$t->check(
    'schema adds hosts.hostArch',
    false !== strpos($schemaSrc, 'ADD `hostArch` VARCHAR(16) NULL DEFAULT NULL')
);
$t->check(
    'schema adds images.imageArch',
    false !== strpos($schemaSrc, 'ADD `imageArch` VARCHAR(16) NULL DEFAULT NULL')
);
$t->check(
    'both columns are added behind an information_schema probe, not bare',
    substr_count($schemaSrc, "AND `COLUMN_NAME` IN ('hostArch')") === 1
    && substr_count($schemaSrc, "AND `COLUMN_NAME` IN ('imageArch')") === 1
);
$t->check(
    'createImagePackage consults archCanRun before creating a deploy task',
    (bool)preg_match('/archCanRun/', $hostSrc)
);
$t->check(
    'the capture path stamps the image with the capturing host arch',
    (bool)preg_match('/archCanRun|imageArch|set\(\s*\'arch\'/', $queueSrc)
);
$t->check(
    'the boot path records the observed architecture on the host',
    (bool)preg_match('/hostArch|set\(\s*\'arch\'/', $menuSrc)
);

// --- the three regressions found on a live server 2026-08-25 -------------
// All three were invisible to CI: a fresh install creates the columns, so
// only an UPGRADE showed them.
$paneSrc = (string)file_get_contents($web . '/lib/pages/imagemanagement.page.php');
$listJs = (string)file_get_contents(
    $web . '/management/js/fog/image/fog.image.list.js'
);
$systemSrc = (string)file_get_contents($web . '/lib/fog/system.class.php');

$t->check(
    'steps 369/370 are their own column-zero appends, not nested in step 368',
    (bool)preg_match('/^\/\/ 369$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
    && (bool)preg_match('/^\/\/ 370$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
);
$t->check(
    'FOG_SCHEMA reaches 370, so the updater actually offers the new steps',
    (bool)preg_match("/define\('FOG_SCHEMA',\s*(\d+)\)/", $systemSrc, $fs)
    && (int)$fs[1] >= 370
);
$t->check(
    'the Architectures page refuses to render against an unmigrated database',
    false !== strpos($paneSrc, "DatabaseManager::tableColumns('hosts')")
    && false !== strpos($paneSrc, 'hostarch')
);
$t->check(
    'no (array) cast on a query result -- (array)false is [false], one blank row',
    false === strpos($paneSrc, '(array)$rows')
    && false === strpos($paneSrc, '(array)$images')
);
$t->check(
    'the image grid targets render columns by name, never by index',
    false === strpos($listJs, 'targets: 0')
    && false === strpos($listJs, 'targets: 1')
    && false === strpos($listJs, 'targets: 2')
    && false !== strpos($listJs, 'colIndex')
);

$t->finish();
