<?php
/**
 * A deploy is refused only when the host positively cannot run the image.
 *
 * Architecture::canRun() is the single authority for that question, and it is a
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
 * The same rule is stated in prose in IpxeBootMenu::_fileFitsArch()'s docblock
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

require_once $web . '/src/Base/FOGBase.php';
require_once $web . '/src/Base/FOGController.php';
require_once $web . '/src/Items/Image.php';
require_once $web . '/src/Items/Architecture.php';

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
    $got = \FOG\Items\Architecture::canRun($img, $host);
    $t->check(
        sprintf(
            'canRun(%s image -> %s host) is %s -- %s',
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
        \FOG\Items\Architecture::canRun($blank, 'arm64') === true
    );
    $t->check(
        "unknown host arch ($shown) against a known image is allowed",
        \FOG\Items\Architecture::canRun('x86_64', $blank) === true
    );
}
$t->check(
    'both sides unknown is allowed',
    \FOG\Items\Architecture::canRun('', '') === true
);

// --- normalization --------------------------------------------------------
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
        sprintf("normalizeName('%s') is '%s'", $raw, $want),
        \FOG\Items\Architecture::normalizeName($raw) === $want
    );
}
$t->check(
    'normalizeName leaves an unknown architecture alone rather than guessing',
    \FOG\Items\Architecture::normalizeName('riscv64') === 'riscv64'
);
$t->check(
    'an aarch64 image is compatible with an arm64 host after normalization',
    \FOG\Items\Architecture::canRun('aarch64', 'arm64') === true
);

// --- the wiring the relation depends on -----------------------------------
// Without these the method is correct and never consulted.
$hostSrc = (string)file_get_contents($web . '/src/Items/Host.php');
$imageSrc = (string)file_get_contents($web . '/src/Items/Image.php');
$schemaSrc = (string)file_get_contents($web . '/commons/schema.php');
$queueSrc = (string)file_get_contents($web . '/src/TaskHandling/TaskQueue.php');
$menuSrc = (string)file_get_contents($web . '/src/Boot/IpxeBootMenu.php');
$archSrc = (string)file_get_contents($web . '/src/Items/Architecture.php');

$t->check(
    "Host maps 'archID' to hostArchID",
    (bool)preg_match("/'archID'\s*=>\s*'hostArchID'/", $hostSrc)
);
$t->check(
    "Image maps 'archID' to imageArchID",
    (bool)preg_match("/'archID'\s*=>\s*'imageArchID'/", $imageSrc)
);
$t->check(
    'schema creates the architectures lookup table',
    false !== strpos($schemaSrc, 'CREATE TABLE IF NOT EXISTS `architectures`')
);
$t->check(
    'the flag is an enum of both/host/image, mirroring taskTypes.ttIsAccess',
    false !== strpos(
        $schemaSrc,
        "`archIsAccess` enum('both','host','image') NOT NULL DEFAULT 'both'"
    )
);
$t->check(
    'schema adds hosts.hostArchID',
    false !== strpos($schemaSrc, 'ADD `hostArchID` mediumint(9) NULL DEFAULT NULL')
);
$t->check(
    'schema adds images.imageArchID',
    false !== strpos($schemaSrc, 'ADD `imageArchID` mediumint(9) NULL DEFAULT NULL')
);
$t->check(
    'the id columns are added behind an information_schema probe, not bare',
    false !== strpos($schemaSrc, "AND `COLUMN_NAME` IN (\$quoted)")
);
// The drop is only lossless if every value the fleet already holds has a row
// to point at first. Without the adopt pass it is lossless only because the
// boot-menu whitelist happens to agree with the seed list, which is a
// coincidence two edits from now.
$t->check(
    'the old strings are adopted into the table before the columns are dropped',
    strpos($schemaSrc, 'SELECT DISTINCT `hostArch` FROM `hosts`')
    < strpos($schemaSrc, 'ALTER TABLE `hosts` DROP COLUMN `hostArch`')
    && strpos($schemaSrc, 'SELECT DISTINCT `imageArch` FROM `images`')
    < strpos($schemaSrc, 'ALTER TABLE `images` DROP COLUMN `imageArch`')
);
$t->check(
    'the backfill maps by name before the drop, on both sides',
    strpos($schemaSrc, 'SET `h`.`hostArchID` = `a`.`archID`')
    < strpos($schemaSrc, 'ALTER TABLE `hosts` DROP COLUMN `hostArch`')
    && strpos($schemaSrc, 'SET `i`.`imageArchID` = `a`.`archID`')
    < strpos($schemaSrc, 'ALTER TABLE `images` DROP COLUMN `imageArch`')
);
$t->check(
    'createImagePackage consults Architecture::canRun before a deploy task',
    (bool)preg_match('/Architecture::canRun/', $hostSrc)
);
$t->check(
    'the refusal names both architectures, not two ids nobody can read',
    (bool)preg_match('/\$imageArchName\s*=\s*\$Image->get\(\x27arch\x27\)/', $hostSrc)
);
$t->check(
    'the capture path copies the capturing host arch id onto the image',
    (bool)preg_match("/set\(\s*'archID',\s*\\\$capturedArchID\s*\)/", $queueSrc)
);
$t->check(
    'the boot path resolves the reported arch to a row before storing it',
    false !== strpos($menuSrc, 'Architecture::idFromName($raw)')
);
// boot.php is unauthenticated by necessity, so this is the only guard between
// a request body and a stored value. It has to stay a whitelist AND it has to
// stay non-creating -- either half alone lets an anonymous request decide what
// architectures this server believes in.
$t->check(
    'the boot path still whitelists the raw value before resolving it',
    false !== strpos($menuSrc, "in_array(\$raw, ['i386', 'x86_64', 'arm64'], true)")
);
$t->check(
    'idFromName never creates a row, so boot.php cannot invent one',
    false === strpos($archSrc, 'ArchitectureManager')
    || false === strpos($archSrc, "->set('name'")
);

// --- the three regressions found on a live server 2026-08-25 -------------
// All three were invisible to CI: a fresh install creates the columns, so
// only an UPGRADE showed them.
$paneSrc = (string)file_get_contents($web . '/src/Pages/ImageManagement.php');
$listJs = (string)file_get_contents(
    $web . '/management/js/fog/image/fog.image.list.js'
);
$systemSrc = (string)file_get_contents($web . '/src/Base/System.php');

$t->check(
    'steps 369-372 are their own column-zero appends, not nested in another',
    (bool)preg_match('/^\/\/ 369$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
    && (bool)preg_match('/^\/\/ 370$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
    && (bool)preg_match('/^\/\/ 371$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
    && (bool)preg_match('/^\/\/ 372$\s*^\$this->schema\[\] = \[/m', $schemaSrc)
);
$t->check(
    'FOG_SCHEMA reaches 372, so the updater actually offers the new steps',
    (bool)preg_match("/define\('FOG_SCHEMA',\s*(\d+)\)/", $systemSrc, $fs)
    && (int)$fs[1] >= 372
);
$t->check(
    'the Architectures page refuses to render against an unmigrated database',
    false !== strpos($paneSrc, "DatabaseManager::tableColumns('hosts')")
    && false !== strpos($paneSrc, 'hostarchid')
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

// --- sector size (schema step 371) ---------------------------------------
// The value FOS already refuses on, finally visible to the server. The parse
// target is the `sector-size:` line util-linux 2.35+ writes into the sfdisk
// dump -- the exact line validateImageSectorSize() in funcs.sh reads.
$dump4k = "label: gpt\n"
    . "label-id: 3C1B8A0E-1111-4222-8333-444455556666\n"
    . "device: /dev/nvme0n1\n"
    . "unit: sectors\n"
    . "first-lba: 6\n"
    . "sector-size: 4096\n"
    . "\n"
    . "/dev/nvme0n1p1 : start=6, size=32768, type=EF00\n";
$dump512 = str_replace('sector-size: 4096', 'sector-size: 512', $dump4k);
// Pre-2.35 dumps have no such line at all. FOS treats that as "allow the
// deploy rather than guess", so this must read as unknown, never as 512.
$dumpOld = str_replace("sector-size: 4096\n", '', $dump4k);

$t->check(
    'a 4Kn dump parses to 4096',
    \FOG\Items\Image::parseSectorSize($dump4k) === 4096
);
$t->check(
    'a 512-byte dump parses to 512',
    \FOG\Items\Image::parseSectorSize($dump512) === 512
);
$t->check(
    'a dump with no sector-size line is unknown (0), not assumed 512',
    \FOG\Items\Image::parseSectorSize($dumpOld) === 0
);
$t->check(
    'an empty dump is unknown rather than an error',
    \FOG\Items\Image::parseSectorSize('') === 0
);
$t->check(
    'a sector-size mentioned mid-line is not mistaken for the field',
    \FOG\Items\Image::parseSectorSize("# note: sector-size: 4096 was seen\n") === 0
);
$t->check(
    '4096 is labeled 4Kn',
    false !== strpos(\FOG\Items\Image::sectorSizeLabel(4096), '4Kn')
);
$t->check(
    '512 is labeled 512n/512e -- the two are not separable from a capture',
    false !== strpos(\FOG\Items\Image::sectorSizeLabel(512), '512n/512e')
);
$t->check(
    'unknown has no label at all, rather than a misleading default',
    \FOG\Items\Image::sectorSizeLabel(0) === ''
);
$t->check(
    "Image maps 'sectorsize' to imageSectorSize",
    (bool)preg_match("/'sectorsize'\s*=>\s*'imageSectorSize'/", $imageSrc)
);
$t->check(
    'schema adds images.imageSectorSize',
    false !== strpos($schemaSrc, 'ADD `imageSectorSize` INT(11) NULL DEFAULT NULL')
);
$t->check(
    'the capture path reads the dump in the same candidate order as FOS',
    (bool)preg_match(
        "/'minimum\.partitions',\s*'partitions',\s*'original\.partitions'/s",
        $queueSrc
    )
);

$t->finish();
