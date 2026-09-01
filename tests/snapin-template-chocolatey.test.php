<?php
/**
 * The Chocolatey snapin templates compose into a command choco will accept.
 *
 * GH-356 asked for Chocolatey in the snapin template dropdowns. A template
 * is three strings, so the whole of the correctness lives in whether the
 * command the FOG client assembles from them is one the tool understands --
 * and the client's assembly rule is what constrains the answer:
 *
 *   non-pack  runWith  runWithArgs  "<downloaded file>"  args
 *   pack      runWith  runWithArgs                            (args forced
 *                                                              empty, and
 *                                                              [FOG_SNAPIN_PATH]
 *                                                              substituted)
 *
 * (fog-client Modules/SnapinClient/SnapinClient.cs, GenerateProcess() and
 * GenerateSnapinPackProcess().)
 *
 * The downloaded file is injected UNCONDITIONALLY in non-pack mode, and the
 * client refuses to run a snapin whose hash is empty -- so "no file at all"
 * was never available and the placeholder .bat in the issue was working
 * around that, not around a missing feature. `choco install` reads any
 * argument ending in `.config` as a package list and accepts an absolute
 * path to it, which makes the forced injection the right shape rather than
 * an obstacle. Installing from a `.nupkg` path is deprecated upstream, so
 * that variant is deliberately not offered. `choco upgrade` REJECTS a
 * .config outright ("A packages.config file is only used with installs."),
 * which is why there is no upgrade counterpart of the non-pack entry.
 *
 * A pack has no injected file, so there the package is named directly and
 * the unpacked directory serves as an offline `--source`.
 *
 * The templates are read by reflection and the command is assembled here in
 * the client's own order, so an edit that leaves the strings looking
 * plausible but breaks the resulting command fails this.
 *
 * Usage: php tests/snapin-template-chocolatey.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('snapin-template-chocolatey');

$t = new FogChecks();

$class = new \ReflectionClass('FOG\Pages\SnapinManagement');

/*
 * 1. The non-pack template.
 */
$prop = $class->getProperty('_argTypes');
$prop->setAccessible(true);
$argTypes = $prop->getValue();

$choco = null;
$chocoLabel = '';
foreach ($argTypes as $label => $cmd) {
    if (false !== stripos($label, 'chocolatey')) {
        $choco = $cmd;
        $chocoLabel = $label;
        break;
    }
}

if (!$t->check('the non-pack dropdown offers a Chocolatey template', null !== $choco)) {
    $t->finish();
}

$t->check(
    'its label says which file to upload',
    false !== stripos($chocoLabel, 'packages.config')
);
$t->check(
    'it runs choco.exe',
    false !== stripos($choco[0], 'choco.exe')
);
$t->check(
    'the path to choco.exe is one the client can expand',
    false !== strpos($choco[0], '%ProgramData%')
);

// The client puts the downloaded file straight after runWithArgs, so
// anything else in there becomes a second positional package name.
$t->check(
    'runWithArgs is the bare install verb',
    'install' === trim($choco[1])
);
$t->check(
    'the switches are unattended and non-interactive',
    false !== strpos($choco[2], '-y')
    && false !== strpos($choco[2], '--no-progress')
);
// [FOG_SNAPIN_PATH] is a pack-only token; in non-pack mode it is passed
// through to the command line verbatim and choco sees a literal directory
// name that does not exist.
foreach ($choco as $i => $part) {
    $t->check(
        "non-pack field $i carries no [FOG_SNAPIN_PATH] token",
        false === strpos($part, '[FOG_SNAPIN_PATH]')
    );
}

// Assembled the way GenerateProcess() does it, with the file the label
// tells the user to upload.
$file = 'C:\\Program Files (x86)\\FOG\\tmp\\packages.config';
$command = trim(
    $choco[0] . ' ' . trim($choco[1]) . ' "' . $file . '" ' . $choco[2]
);
$t->check(
    'the assembled command is choco install <packages.config> <switches>',
    '%ProgramData%\\chocolatey\\bin\\choco.exe install'
    . ' "C:\\Program Files (x86)\\FOG\\tmp\\packages.config"'
    . ' -y -r --no-progress' === $command
);

/*
 * 2. The pack template. _maker() is a private instance method that only
 * builds a string, so it runs on an object made without its constructor.
 */
$page = $class->newInstanceWithoutConstructor();
$maker = $class->getMethod('_maker');
$maker->setAccessible(true);
$packHtml = (string)$maker->invoke($page);

$t->check(
    'the pack dropdown offers a Chocolatey template too',
    false !== stripos($packHtml, 'Chocolatey')
);
if (preg_match(
    '#<option file="([^"]*)" args="([^"]*)">[^<]*Chocolatey[^<]*</option>#i',
    $packHtml,
    $m
)) {
    $t->check('the pack template runs choco.exe', false !== stripos($m[1], 'choco.exe'));
    // A pack gets no injected file, so the package is named directly and
    // the unpacked directory is what makes an offline install possible.
    $t->check(
        'the pack template sources packages from the unpacked directory',
        false !== strpos($m[2], '--source=')
        && false !== strpos($m[2], '[FOG_SNAPIN_PATH]')
    );
    $t->check(
        'the pack template does not pass a packages.config',
        false === stripos($m[2], '.config')
    );
} else {
    $t->check('the pack Chocolatey option renders as an <option>', false);
}

$t->finish();
