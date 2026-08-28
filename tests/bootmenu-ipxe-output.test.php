<?php
/**
 * Characterization test for the iPXE script IpxeBootMenu emits.
 *
 * IpxeBootMenu has no return value worth checking: every screen is an array of
 * strings that _parseMe() flattens straight to stdout, and that stdout IS the
 * iPXE program the client runs. So the only meaningful assertion is a byte
 * comparison of the emitted script.
 *
 * The golden file was generated from bootmenu.class.php as it stood BEFORE
 * the dead-code removal it now guards, which is what makes it proof rather
 * than decoration: the cleanup is correct exactly insofar as the bytes did not
 * move. It doubles as a regression net for future edits to a file where a
 * stray line is a client that will not boot.
 *
 * Three things the golden cannot cover get their own checks below:
 *   - what BOOT_ITEM_NEW_SETTINGS hands a plugin (never appears in output);
 *   - whether a plugin's value survives to the emitted script;
 *   - that no host secret reaches this unauthenticated endpoint.
 *
 * Each scenario runs in its own process: several IpxeBootMenu paths end in exit(),
 * and the architecture profile is memoised in a static that would leak between
 * scenarios sharing a process.
 *
 * Usage:
 *   php tests/bootmenu-ipxe-output.test.php            # compare to golden
 *   php tests/bootmenu-ipxe-output.test.php --update   # rewrite golden
 *   php tests/bootmenu-ipxe-output.test.php --against FILE
 *
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$renderer = __DIR__ . '/lib/bootmenu-render.php';
$golden = __DIR__ . '/fixtures/bootmenu-ipxe-output.golden';
$classFile = $root . '/packages/web/src/Boot/IpxeBootMenu.php';

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
 * The matrix. Each entry names a branch of IpxeBootMenu that renders differently,
 * so a change that moves any of these bytes shows up as a diff rather than as
 * a client that silently fails to boot.
 *
 * Coverage is deliberate, not exhaustive:
 *  - every exit type, each a distinct chunk of iPXE built in the constructor;
 *  - every arch, because the kernel/init keys, the refind loader, memdisk
 *    availability and the override guard all switch on it;
 *  - hidden vs shown menu, the only path through _chainBoot();
 *  - registered / pending / unregistered, which select the pxeRegOnly set;
 *  - all four Secure Boot file states, which _filterMenus() and
 *    _enrollSecureBootChoice() read separately (MOK.der gates the attended
 *    item's body, PK/KEK/db.auth gate whether the unattended item is listed);
 *  - both arch-mismatch notices, and the _echoSafe() whitelist.
 */
$scenarios = [
    'unregistered-bios-x86' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
    ],
    'unregistered-efi-x86' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
    ],
    'unregistered-efi-x86-mok' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'mok' => true,
    ],
    'unregistered-efi-x86-mok-authvars' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'mok' => true,
        'authvars' => true,
    ],
    'unregistered-efi-x86-authvars-only' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'authvars' => true,
    ],
    'unregistered-efi-i386' => [
        'request' => ['arch' => 'i386', 'platform' => 'efi'],
    ],
    'unregistered-efi-arm64' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
    ],
    'unregistered-no-platform' => [
        'request' => ['arch' => 'x86_64'],
    ],
    'registered-bios' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => ['id' => 1, 'name' => 'testhost'],
    ],

    /*
     * Tasking. Everything above renders the MENU -- every one of those
     * scenarios carries an invalid task so getTasking() falls straight
     * through to printDefault(), which left the ~360 lines that build a
     * task's kernel arguments with no characterization at all. These cover
     * the branches that decide what FOS is told to do, because a wrong
     * argument here is a client that boots and then images the wrong thing.
     */
    'task-deploy' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => [
                'id' => 1,
                'typeID' => 1,
                'tasktype' => [
                    'id' => 1,
                    'imaging' => true,
                    'initneeded' => true,
                    'kernelArgs' => 'type=down',
                ],
                'image' => [
                    'id' => 1,
                    'osID' => 50,
                    'path' => 'testimage',
                    'format' => 5,
                    'imagetype' => 'mps',
                    'partitiontype' => 'all',
                ],
            ],
        ],
    ],
    'task-capture' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => [
                'id' => 1,
                'typeID' => 2,
                'capture' => true,
                'tasktype' => [
                    'id' => 2,
                    'imaging' => true,
                    'initneeded' => true,
                    'capture' => true,
                    'kernelArgs' => 'type=up',
                ],
                'image' => [
                    'id' => 1,
                    'osID' => 50,
                    'path' => 'testimage',
                    'format' => 5,
                    'imagetype' => 'mps',
                    'partitiontype' => 'all',
                ],
            ],
        ],
    ],
    'task-deploy-arm64' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => [
                'id' => 1,
                'typeID' => 1,
                'tasktype' => [
                    'id' => 1,
                    'imaging' => true,
                    'initneeded' => true,
                    'kernelArgs' => 'type=down',
                ],
                'image' => [
                    'id' => 1,
                    'osID' => 50,
                    'path' => 'testimage',
                    'format' => 5,
                    'imagetype' => 'mps',
                    'partitiontype' => 'all',
                ],
            ],
        ],
    ],
    'task-deploy-debug' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => [
                'id' => 1,
                'typeID' => 1,
                'tasktype' => [
                    'id' => 1,
                    'imaging' => true,
                    'initneeded' => true,
                    'kernelArgs' => 'type=down mode=debug',
                ],
                'image' => [
                    'id' => 1,
                    'osID' => 50,
                    'path' => 'testimage',
                    'format' => 5,
                    'imagetype' => 'mps',
                    'partitiontype' => 'all',
                ],
            ],
        ],
    ],
    'task-non-imaging' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => [
                'id' => 1,
                'typeID' => 12,
                'tasktype' => [
                    'id' => 12,
                    'imaging' => false,
                    'initneeded' => true,
                    'kernelArgs' => 'mode=wipe',
                ],
                'image' => ['id' => 1, 'osID' => 50, 'path' => 'testimage'],
            ],
        ],
    ],
    'task-snapin-falls-to-menu' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'task' => ['id' => 1, 'typeID' => 13, 'snapin' => true],
        ],
    ],
    'task-image-ignored' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'imageignored' => true,
            'task' => [
                'id' => 1,
                'typeID' => 1,
                'tasktype' => [
                    'id' => 1,
                    'imaging' => true,
                    'initneeded' => true,
                    'kernelArgs' => 'type=down',
                ],
                'image' => [
                    'id' => 1,
                    'osID' => 50,
                    'path' => 'testimage',
                    'format' => 5,
                    'imagetype' => 'mps',
                    'partitiontype' => 'all',
                ],
            ],
        ],
    ],
    'registered-efi-mok-authvars' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'host' => ['id' => 1, 'name' => 'testhost'],
        'mok' => true,
        'authvars' => true,
    ],
    'pending-approval' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => ['id' => 1, 'name' => 'testhost', 'pending' => 1],
    ],
    'registered-host-kernel-override' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'kernel' => 'custom-bzImage',
            'init' => 'custom-init.xz',
            'kernelArgs' => 'nomodeset',
        ],
    ],
    'arm64-host-x86-kernel-override' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
        'host' => ['id' => 1, 'name' => 'testhost', 'kernel' => 'custom-bzImage'],
    ],
    'arm64-host-arm-kernel-override' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
        'host' => ['id' => 1, 'name' => 'testhost', 'kernel' => 'arm_custom_Image'],
    ],
    'x86-host-arm-init-override' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'host' => ['id' => 1, 'name' => 'testhost', 'init' => 'arm_init.cpio.gz'],
    ],
    'host-kernel-override-unsafe-chars' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
        'host' => [
            'id' => 1,
            'name' => 'testhost',
            'kernel' => 'evil; echo pwned && chain http://x/`id`',
        ],
    ],
    'debug-requested' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios', 'debug' => 1],
        'host' => ['id' => 1, 'name' => 'testhost'],
    ],
    'registration-disabled' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_REGISTRATION_ENABLED' => '0'],
    ],
    'advanced-menu' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_PXE_ADVANCED' => '1'],
    ],
    'advanced-menu-login' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => [
            'FOG_PXE_ADVANCED' => '1', 'FOG_ADVANCED_MENU_LOGIN' => '1',
        ],
    ],
    'hidden-menu' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_PXE_MENU_HIDDEN' => '1'],
    ],
    'hidden-menu-keyseq' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => [
            'FOG_PXE_MENU_HIDDEN' => '1', 'FOG_KEY_SEQUENCE' => 'F12',
        ],
    ],
    'hidden-menu-accessed' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios', 'menuAccess' => 1],
        'settings' => ['FOG_PXE_MENU_HIDDEN' => '1'],
    ],
    'exit-sanboot' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'sanboot'],
    ],
    'exit-exit' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'exit'],
    ],
    'exit-grub' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'grub'],
    ],
    'exit-grub-first-hdd' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'grub_first_hdd'],
    ],
    'exit-grub-first-cdrom' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'grub_first_cdrom'],
    ],
    'exit-grub-first-found-windows' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'grub_first_found_windows'],
    ],
    'exit-refind-efi' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'efi'],
        'settings' => ['FOG_EFI_BOOT_EXIT_TYPE' => 'refind_efi'],
    ],
    'exit-refind-efi-i386' => [
        'request' => ['arch' => 'i386', 'platform' => 'efi'],
        'settings' => ['FOG_EFI_BOOT_EXIT_TYPE' => 'refind_efi'],
    ],
    'exit-refind-efi-arm64' => [
        'request' => ['arch' => 'arm64', 'platform' => 'efi'],
        'settings' => ['FOG_EFI_BOOT_EXIT_TYPE' => 'refind_efi'],
    ],
    'exit-unknown-falls-back-to-sanboot' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_BOOT_EXIT_TYPE' => 'not-a-real-exit-type'],
    ],
    'host-exit-override' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => ['id' => 1, 'name' => 'testhost', 'biosexit' => 'exit'],
    ],
    'no-menu' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'host' => ['id' => 1, 'name' => 'testhost'],
        'settings' => ['FOG_NO_MENU' => '1'],
    ],
    'keymap-set' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_KEYMAP' => 'uk'],
    ],
    'kernel-args-and-debug' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => [
            'FOG_KERNEL_ARGS' => 'acpi=off', 'FOG_KERNEL_DEBUG' => '1',
        ],
    ],
    'nested-webroot' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_WEB_ROOT' => '/apps/fog/'],
    ],
    'bare-webroot' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'settings' => ['FOG_WEB_ROOT' => 'fog'],
    ],
];

/**
 * Renders one scenario in a child process.
 *
 * @param string $renderer  path to the renderer
 * @param array  $scenario  the scenario definition
 * @param string $classFile the IpxeBootMenu source under test
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

$rendered = [];
$actual = '';
foreach ($scenarios as $name => $scenario) {
    $rendered[$name] = renderScenario($renderer, $scenario, $classFile);
    $actual .= "########## $name ##########\n";
    $actual .= rtrim($rendered[$name], "\n");
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
$broken = [];
foreach ($rendered as $name => $chunk) {
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
 * Behaviour checks the golden cannot express.
 *
 * Each renders a scenario and asserts on something other than the exact bytes:
 * what a plugin was handed, whether a plugin's value survived, or whether a
 * secret escaped. 'expectHook' reads the BOOT_ITEM_NEW_SETTINGS payload;
 * 'expectLine' / 'refuteLine' match against the emitted script.
 */
$checks = [
    'webroot reaches plugins as the bare form' => [
        'scenario' => [
            'hooks' => true,
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        ],
        'expectHook' => ['webserver' => '10.0.0.1', 'webroot' => 'fog'],
    ],
    'webroot keeps every segment of a nested install' => [
        'scenario' => [
            'hooks' => true,
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            'settings' => ['FOG_WEB_ROOT' => '/apps/fog/'],
        ],
        'expectHook' => ['webroot' => 'apps/fog'],
    ],
    "a plugin's initrd survives to imgfetch" => [
        'scenario' => [
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            'hookMutations' => [
                'BOOT_ITEM_NEW_SETTINGS' => ['initrd' => 'plugin-init.xz'],
            ],
        ],
        'expectLine' => ['imgfetch plugin-init.xz', 'initrd=plugin-init.xz'],
        'refuteLine' => ['imgfetch init.xz'],
    ],
    "a plugin's imagefile still wins when it sets only that" => [
        'scenario' => [
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            'hookMutations' => [
                'BOOT_ITEM_NEW_SETTINGS' => ['imagefile' => 'plugin-image.xz'],
            ],
        ],
        'expectLine' => ['imgfetch plugin-image.xz', 'initrd=plugin-image.xz'],
    ],
    'initrd wins over imagefile when a plugin sets both' => [
        'scenario' => [
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            'hookMutations' => [
                'BOOT_ITEM_NEW_SETTINGS' => [
                    'initrd' => 'plugin-init.xz',
                    'imagefile' => 'plugin-image.xz',
                ],
            ],
        ],
        'expectLine' => ['imgfetch plugin-init.xz'],
    ],
    'no host secret reaches this unauthenticated endpoint' => [
        'scenario' => [
            'host' => ['id' => 1, 'name' => 'testhost'],
            'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
            'hostRow' => [
                'token' => 'TOKENSHOULDNOTAPPEAR',
                'pub_key' => 'PUBKEYSHOULDNOTAPPEAR',
                'sec_tok' => 'SECTOKSHOULDNOTAPPEAR',
                'productKey' => 'PRODKEYSHOULDNOTAPPEAR',
                'ADPass' => 'ADPASSSHOULDNOTAPPEAR',
                'ADPassLegacy' => 'ADPASSLEGACYSHOULDNOTAPPEAR',
            ],
        ],
        'expectLine' => ['set hostname testhost'],
        'refuteLine' => [
            'TOKENSHOULDNOTAPPEAR',
            'PUBKEYSHOULDNOTAPPEAR',
            'SECTOKSHOULDNOTAPPEAR',
            'PRODKEYSHOULDNOTAPPEAR',
            'ADPASSSHOULDNOTAPPEAR',
            'ADPASSLEGACYSHOULDNOTAPPEAR',
        ],
    ],
];

$checkFailures = [];
foreach ($checks as $label => $check) {
    $raw = renderScenario($renderer, $check['scenario'], $classFile);
    if (isset($check['expectHook'])) {
        $fired = json_decode($raw, true);
        if (!is_array($fired)) {
            $checkFailures[] = "$label: could not decode hook payloads";
            continue;
        }
        $scalars = null;
        foreach ($fired as $event) {
            if ('BOOT_ITEM_NEW_SETTINGS' === ($event['event'] ?? '')) {
                $scalars = $event['scalars'] ?? [];
                break;
            }
        }
        if (null === $scalars) {
            $checkFailures[] = "$label: BOOT_ITEM_NEW_SETTINGS never fired";
            continue;
        }
        foreach ($check['expectHook'] as $key => $want) {
            $have = array_key_exists($key, $scalars) ? $scalars[$key] : '<absent>';
            if ($have !== $want) {
                $checkFailures[] = sprintf(
                    "%s: '%s' was %s, expected %s",
                    $label,
                    $key,
                    var_export($have, true),
                    var_export($want, true)
                );
            }
        }
    }
    foreach ((array)($check['expectLine'] ?? []) as $needle) {
        if (false === strpos($raw, $needle)) {
            $checkFailures[] = "$label: expected to find '$needle'";
        }
    }
    foreach ((array)($check['refuteLine'] ?? []) as $needle) {
        if (false !== strpos($raw, $needle)) {
            $checkFailures[] = "$label: '$needle' must NOT appear";
        }
    }
}
if ($checkFailures) {
    fwrite(STDERR, "\nFAIL: behaviour checks\n");
    foreach ($checkFailures as $f) {
        fwrite(STDERR, "  $f\n");
    }
    exit(1);
}
printf(
    "bootmenu-ipxe-output: %d behaviour checks passed\n",
    count($checks)
);

$expected = file_get_contents($golden);
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
    fwrite(
        STDERR,
        sprintf("  line %d:\n    -golden: %s\n    +actual: %s\n", $i + 1, $a, $b)
    );
    $shown++;
}
if ($shown >= 40) {
    fwrite(STDERR, "  ... further differences suppressed\n");
}
exit(1);
