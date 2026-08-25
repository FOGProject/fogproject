<?php
/**
 * Characterization test for the iPXE script BootMenu emits.
 *
 * BootMenu has no return value worth checking: every screen is an array of
 * strings that _parseMe() flattens straight to stdout, and that stdout IS
 * the iPXE program the client runs. So the only meaningful assertion is a
 * byte comparison of the emitted script.
 *
 * The golden file was generated from bootmenu.class.php as it stood BEFORE
 * the dead-code removal it now guards, which is what makes it proof rather
 * than decoration: the cleanup is correct exactly insofar as the bytes did
 * not move. It doubles as a regression net for future edits to a file where
 * a stray line is a client that will not boot.
 *
 * Each scenario runs in its own process because several paths -- noMenu()
 * and the tasking path -- end in exit().
 *
 * Usage:
 *   php tests/bootmenu-ipxe-output.test.php            # compare to golden
 *   php tests/bootmenu-ipxe-output.test.php --update   # rewrite golden
 *   php tests/bootmenu-ipxe-output.test.php --against <file.php>
 *
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$renderer = __DIR__ . '/lib/bootmenu-render.php';
$golden = __DIR__ . '/fixtures/bootmenu-ipxe-output.golden';
$classFile = $root . '/packages/web/lib/fog/bootmenu.class.php';

$update = in_array('--update', $argv, true);
$againstAt = array_search('--against', $argv, true);
if (false !== $againstAt && isset($argv[$againstAt + 1])) {
    $classFile = $argv[$againstAt + 1];
}

if (!is_file($classFile)) {
    fwrite(STDERR, "FAIL: no such class file: $classFile\n");
    exit(1);
}

/*
 * The matrix. Each entry names a branch of BootMenu that renders differently,
 * so a change that moves any of these bytes shows up as a diff rather than as
 * a client that silently fails to boot.
 *
 * Coverage is deliberate, not exhaustive:
 *  - every exit type, because each is a distinct chunk of iPXE built in the
 *    constructor, and 'reboot' in particular was built through a pointless
 *    sprintf() that this test pins the output of;
 *  - every arch, because bzImage/init and the refind loader all switch on it;
 *  - hidden vs shown menu, which is the only path through _chainBoot();
 *  - registered / pending / unregistered, which select the pxeRegOnly set and
 *    therefore which menu rows exist at all;
 *  - MOK.der present and absent, the two branches of the Secure Boot choice;
 *  - bios vs efi, which decides both the exit-type setting read and whether
 *    pxeID 14 is filtered out.
 */
$scenarios = array(
    'unregistered-bios-x86' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
    ),
    'unregistered-efi-x86' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'efi'),
    ),
    'unregistered-efi-x86-mok' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'efi'),
        'mok' => true,
    ),
    'unregistered-efi-i386' => array(
        'request' => array('arch' => 'i386', 'platform' => 'efi'),
    ),
    'unregistered-efi-arm64' => array(
        'request' => array('arch' => 'arm64', 'platform' => 'efi'),
    ),
    'unregistered-no-platform' => array(
        'request' => array('arch' => 'x86_64'),
    ),
    'registered-bios' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'host' => array('id' => 1, 'name' => 'testhost'),
    ),
    'registered-efi-mok' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'efi'),
        'host' => array('id' => 1, 'name' => 'testhost'),
        'mok' => true,
    ),
    'pending-approval' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'host' => array('id' => 1, 'name' => 'testhost', 'pending' => 1),
    ),
    'registered-host-kernel-override' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'host' => array(
            'id' => 1,
            'name' => 'testhost',
            'kernel' => 'custom-bzImage',
            'init' => 'custom-init.xz',
            'kernelArgs' => 'nomodeset',
        ),
    ),
    'arm64-host-x86-kernel-override' => array(
        'request' => array('arch' => 'arm64', 'platform' => 'efi'),
        'host' => array(
            'id' => 1, 'name' => 'testhost', 'kernel' => 'custom-bzImage'
        ),
    ),
    'arm64-host-arm-kernel-override' => array(
        'request' => array('arch' => 'arm64', 'platform' => 'efi'),
        'host' => array(
            'id' => 1, 'name' => 'testhost', 'kernel' => 'arm_custom_Image'
        ),
    ),
    'x86-host-arm-kernel-override' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'efi'),
        'host' => array(
            'id' => 1, 'name' => 'testhost', 'init' => 'arm_init.cpio.gz'
        ),
    ),
    'host-kernel-override-unsafe-chars' => array(
        'request' => array('arch' => 'arm64', 'platform' => 'efi'),
        'host' => array(
            'id' => 1,
            'name' => 'testhost',
            'kernel' => 'evil; echo pwned && chain http://x/`id`',
        ),
    ),
    'debug-requested' => array(
        'request' => array(
            'arch' => 'x86_64', 'platform' => 'bios', 'debug' => 1
        ),
        'host' => array('id' => 1, 'name' => 'testhost'),
    ),
    'registration-disabled' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_REGISTRATION_ENABLED' => '0'),
    ),
    'advanced-menu' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_PXE_ADVANCED' => '1'),
    ),
    'advanced-menu-login' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array(
            'FOG_PXE_ADVANCED' => '1', 'FOG_ADVANCED_MENU_LOGIN' => '1'
        ),
    ),
    'hidden-menu' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_PXE_MENU_HIDDEN' => '1'),
    ),
    'hidden-menu-keyseq' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array(
            'FOG_PXE_MENU_HIDDEN' => '1', 'FOG_KEY_SEQUENCE' => 'F12'
        ),
    ),
    'hidden-menu-accessed' => array(
        'request' => array(
            'arch' => 'x86_64', 'platform' => 'bios', 'menuAccess' => 1
        ),
        'settings' => array('FOG_PXE_MENU_HIDDEN' => '1'),
    ),
    'exit-sanboot' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'sanboot'),
    ),
    'exit-reboot' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'reboot'),
    ),
    'exit-exit' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'exit'),
    ),
    'exit-grub' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'grub'),
    ),
    'exit-grub-first-cdrom' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'grub_first_cdrom'),
    ),
    'exit-grub-first-found-windows' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'grub_first_found_windows'),
    ),
    'exit-refind-efi' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'efi'),
        'settings' => array('FOG_EFI_BOOT_EXIT_TYPE' => 'refind_efi'),
    ),
    'exit-unknown-falls-back-to-sanboot' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_BOOT_EXIT_TYPE' => 'not-a-real-exit-type'),
    ),
    'host-exit-override' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'host' => array('id' => 1, 'name' => 'testhost', 'biosexit' => 'reboot'),
    ),
    'no-menu' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'host' => array('id' => 1, 'name' => 'testhost'),
        'settings' => array('FOG_NO_MENU' => '1'),
    ),
    'keymap-set' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_KEYMAP' => 'uk'),
    ),
    'keymap-norwegian' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_KEYMAP' => 'no-latin1'),
    ),
    'keymap-serbian' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_KEYMAP' => 'sr'),
    ),
    'kernel-args-and-debug' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array(
            'FOG_KERNEL_ARGS' => 'acpi=off',
            'FOG_KERNEL_DEBUG' => '1',
        ),
    ),
    'nested-webroot' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_WEB_ROOT' => '/apps/fog/'),
    ),
    'bare-webroot' => array(
        'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        'settings' => array('FOG_WEB_ROOT' => 'fog'),
    ),
);

/**
 * Renders one scenario in a child process.
 *
 * @param string $renderer  path to the renderer
 * @param array  $scenario  the scenario definition
 * @param string $classFile the BootMenu source under test
 *
 * @return string the emitted iPXE script, plus any stderr
 */
function renderScenario($renderer, array $scenario, $classFile)
{
    $cmd = sprintf(
        '%s %s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($renderer),
        escapeshellarg(json_encode($scenario)),
        escapeshellarg($classFile)
    );
    $out = shell_exec($cmd);
    return null === $out ? '' : $out;
}

$actual = '';
foreach ($scenarios as $name => $scenario) {
    $actual .= "########## $name ##########\n";
    $actual .= rtrim(renderScenario($renderer, $scenario, $classFile), "\n");
    $actual .= "\n\n";
}

if ($update) {
    if (!is_dir(dirname($golden))) {
        mkdir(dirname($golden), 0775, true);
    }
    file_put_contents($golden, $actual);
    printf(
        "bootmenu-ipxe-output: wrote golden from %s (%d scenarios, %d bytes)\n",
        str_replace($root . '/', '', $classFile),
        count($scenarios),
        strlen($actual)
    );
    exit(0);
}

if (!is_file($golden)) {
    fwrite(STDERR, "FAIL: golden file missing: $golden\n");
    fwrite(STDERR, "Generate it with --update against the pre-change source.\n");
    exit(1);
}

$expected = file_get_contents($golden);

printf(
    "bootmenu-ipxe-output: %d scenarios, %d bytes rendered\n",
    count($scenarios),
    strlen($actual)
);

/*
 * A rendered scenario that is empty, or that carries a PHP diagnostic, means
 * the harness broke rather than that the output matched. Catch that here so a
 * silently-empty render can never be mistaken for a pass.
 */
$broken = array();
foreach ($scenarios as $name => $scenario) {
    $chunk = renderScenario($renderer, $scenario, $classFile);
    if ('' === trim($chunk)) {
        $broken[] = "$name: rendered nothing";
        continue;
    }
    if (preg_match('/(Fatal error|Parse error|Uncaught|harness:)/i', $chunk, $m)) {
        $broken[] = "$name: {$m[1]} in rendered output";
    }
    if (false === strpos($chunk, '#!ipxe')) {
        $broken[] = "$name: output does not start an iPXE script";
    }
}
if ($broken) {
    foreach ($broken as $b) {
        fwrite(STDERR, "\nFAIL: $b\n");
    }
    exit(1);
}

/*
 * Hook-argument checks.
 *
 * BOOT_ITEM_NEW_SETTINGS is the seam plugins use to retarget a boot, and what
 * it hands them never appears in the emitted script -- so the golden file
 * cannot cover it. 'webroot' in particular was passed by reference without
 * ever being assigned, meaning every plugin that read it got NULL; these
 * checks fail against that older source and pass against the fixed one.
 */
$hookChecks = array(
    'webroot reaches plugins as the bare form' => array(
        'scenario' => array(
            'hooks' => true,
            'host' => array('id' => 1, 'name' => 'testhost'),
            'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
        ),
        'expect' => array('webserver' => '10.0.0.1', 'webroot' => 'fog'),
    ),
    'webroot keeps every segment of a nested install' => array(
        'scenario' => array(
            'hooks' => true,
            'host' => array('id' => 1, 'name' => 'testhost'),
            'request' => array('arch' => 'x86_64', 'platform' => 'bios'),
            'settings' => array('FOG_WEB_ROOT' => '/apps/fog/'),
        ),
        'expect' => array('webroot' => 'apps/fog'),
    ),
);

$hookFailures = array();
foreach ($hookChecks as $label => $check) {
    $raw = renderScenario($renderer, $check['scenario'], $classFile);
    $fired = json_decode($raw, true);
    if (!is_array($fired)) {
        $hookFailures[] = "$label: could not decode hook payloads";
        continue;
    }
    $scalars = null;
    foreach ($fired as $event) {
        if ('BOOT_ITEM_NEW_SETTINGS' === ($event['event'] ?? '')) {
            $scalars = $event['scalars'] ?? array();
            break;
        }
    }
    if (null === $scalars) {
        $hookFailures[] = "$label: BOOT_ITEM_NEW_SETTINGS never fired";
        continue;
    }
    foreach ($check['expect'] as $key => $want) {
        $have = array_key_exists($key, $scalars) ? $scalars[$key] : '<absent>';
        if ($have !== $want) {
            $hookFailures[] = sprintf(
                "%s: '%s' was %s, expected %s",
                $label,
                $key,
                var_export($have, true),
                var_export($want, true)
            );
        }
    }
}
if ($hookFailures) {
    fwrite(STDERR, "\nFAIL: hook arguments\n");
    foreach ($hookFailures as $f) {
        fwrite(STDERR, "  $f\n");
    }
    exit(1);
}
printf("bootmenu-ipxe-output: %d hook-argument checks passed\n", count($hookChecks));

if ($actual === $expected) {
    echo "PASS\n";
    exit(0);
}

fwrite(STDERR, "\nFAIL: emitted iPXE differs from the golden file.\n\n");

$aLines = explode("\n", $expected);
$bLines = explode("\n", $actual);
$max = max(count($aLines), count($bLines));
$shown = 0;
for ($i = 0; $i < $max && $shown < 40; $i++) {
    $a = $aLines[$i] ?? '<missing>';
    $b = $bLines[$i] ?? '<missing>';
    if ($a === $b) {
        continue;
    }
    fwrite(STDERR, sprintf("  line %d:\n    -golden: %s\n    +actual: %s\n", $i + 1, $a, $b));
    $shown++;
}
if ($shown >= 40) {
    fwrite(STDERR, "  ... further differences suppressed\n");
}
exit(1);
