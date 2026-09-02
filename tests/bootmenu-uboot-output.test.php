<?php
/**
 * Golden-output test for UbootBootMenu.
 *
 * The sibling of bootmenu-ipxe-output.test.php, and it exists for a harder
 * reason than that one: nobody working on FOG has an ARM board wired to a
 * lab server, so the only thing standing between a change here and a Pi that
 * silently does not boot is this file. What it can prove is that the bytes
 * FOG emits are the bytes intended; what it cannot prove is that a real
 * U-Boot consumes them, which stays a hardware question.
 *
 * Scenarios are deliberately few and every one is a distinct BRANCH of
 * UbootBootMenu -- there is no value in re-covering the tasking arithmetic
 * here, because BootMenuBase computes it and bootmenu-ipxe-output.test.php
 * already pins all 47 scenarios of it.
 *
 *   php tests/bootmenu-uboot-output.test.php            # compare to golden
 *   php tests/bootmenu-uboot-output.test.php --update   # rewrite golden
 *
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$renderer = __DIR__ . '/lib/bootmenu-render.php';
$golden = __DIR__ . '/fixtures/bootmenu-uboot-output.golden';
$classFile = $root . '/packages/web/src/Boot/UbootBootMenu.php';

$update = in_array('--update', $argv, true);

if (!is_file($classFile)) {
    fwrite(STDERR, "FAIL: no such class file: $classFile\n");
    exit(1);
}

$deployTask = [
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
];

$scenarios = [
    // No task: the board has to fall through to its own disk, and quickly.
    'no-task' => [
        'host' => ['id' => 1, 'name' => 'pi4'],
    ],
    // A host FOG has never seen. printDefault() is reached without a Host at
    // all, which is the path a first-boot board takes.
    'unknown-host' => [
        'host' => [],
    ],
    'task-deploy' => [
        'host' => ['id' => 1, 'name' => 'pi4', 'task' => $deployTask],
    ],
    'task-capture' => [
        'host' => [
            'id' => 1,
            'name' => 'pi4',
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
    // Task type 4. There is no aarch64 memdisk or memtest86+, so this must
    // say why rather than emitting a chain that dies in U-Boot.
    'task-memtest' => [
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'task' => [
                'id' => 1,
                'typeID' => 4,
                'tasktype' => ['id' => 4, 'imaging' => false, 'initneeded' => false],
            ],
        ],
    ],
    // The host is tasked, but a MAC it is registered under is image-ignored.
    'image-ignored' => [
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'imageignored' => true,
            'task' => $deployTask,
        ],
    ],
    // Host-level kernel/init override, which on this path is taken with no
    // _fileFitsArch() filename check -- there is only one architecture here.
    'host-kernel-override' => [
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'kernel' => 'arm_Image.custom',
            'init' => 'arm_init.custom.cpio.gz',
            'task' => $deployTask,
        ],
    ],
    // The TFTP-sync rendering: a wget-less board fetches kernel and initrd
    // over TFTP relative to the DHCP bootfile's directory, so the lines
    // carry bare filenames, never URLs (U-Boot boot/pxe_utils.c has no URL
    // handling at all). Everything else is identical to the HTTP document.
    'task-deploy-tftp' => [
        'tftp' => true,
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'task' => $deployTask,
        ],
    ],
    // A host's own kernel/init override stays a bare filename too.
    'host-kernel-override-tftp' => [
        'tftp' => true,
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'kernel' => 'arm_Image.custom',
            'init' => 'arm_init.custom.cpio.gz',
            'task' => $deployTask,
        ],
    ],
    // Host kernelArgs reach the append line, same as they do under iPXE.
    'host-kernel-args' => [
        'host' => [
            'id' => 1,
            'name' => 'pi4',
            'kernelArgs' => 'console=ttyAMA0,115200',
            'task' => $deployTask,
        ],
    ],
];

/**
 * Renders one scenario in a child process.
 *
 * @param string $renderer  path to the renderer
 * @param array  $scenario  the scenario definition
 * @param string $classFile the UbootBootMenu source under test
 *
 * @return string the emitted config, plus any stderr
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
        "bootmenu-uboot-output: wrote golden (%d scenarios, %d bytes)\n",
        count($scenarios),
        strlen($actual)
    );
    exit(0);
}

if (!is_file($golden)) {
    fwrite(STDERR, "FAIL: golden file missing: $golden\n");
    exit(1);
}

printf(
    "bootmenu-uboot-output: %d scenarios, %d bytes rendered\n",
    count($scenarios),
    strlen($actual)
);

$fails = [];

/*
 * A rendered scenario that is empty, or that carries a PHP diagnostic, means
 * the harness broke rather than that the output matched.
 */
foreach ($rendered as $name => $chunk) {
    if ('' === trim($chunk)) {
        $fails[] = "$name: rendered nothing";
        continue;
    }
    if (preg_match('/(Fatal error|Parse error|Uncaught|harness:)/i', $chunk, $m)) {
        $fails[] = "$name: {$m[1]} in rendered output";
    }
    /*
     * Structural invariants of the format itself, asserted per scenario
     * because the golden cannot say "this must hold for every future
     * scenario too".
     *
     * `default fog` must name a label that exists: U-Boot's parser matches
     * `default` against label names and silently boots nothing when it finds
     * no match, which is indistinguishable from a hang on a board with no
     * console.
     *
     * `timeout` must be present and non-zero: 0 means wait forever in
     * PXELINUX-derived parsers, which hangs a headless board.
     */
    if (false === strpos($chunk, "\nlabel fog\n")) {
        $fails[] = "$name: no 'label fog' in the emitted config";
    }
    if (false === strpos($chunk, "\ndefault fog\n")) {
        $fails[] = "$name: 'default' does not name the emitted label";
    }
    if (!preg_match('/^timeout ([1-9]\d*)$/m', $chunk)) {
        $fails[] = "$name: missing or zero timeout";
    }
    /*
     * No iPXE-isms. ${...} is iPXE variable syntax; U-Boot would hand the
     * literal characters to the kernel, and setmacto=${setmacto} arriving as
     * a literal is a corrupted MAC rather than a visible error.
     */
    if (false !== strpos($chunk, '${')) {
        $fails[] = "$name: iPXE variable syntax leaked into the config";
    }
    if (false !== strpos($chunk, '#!ipxe')) {
        $fails[] = "$name: iPXE shebang leaked into the config";
    }
}

/*
 * Branch-specific assertions. Each names the one thing that scenario exists
 * to prove, so a golden regenerated over a real regression still fails here.
 */
$expect = [
    'no-task' => ['localboot 0'],
    'unknown-host' => ['localboot 0'],
    'task-deploy' => [
        'kernel http://10.0.0.1/fog/service/ipxe/arm_Image',
        'initrd http://10.0.0.1/fog/service/ipxe/arm_init.cpio.gz',
        'type=down',
        'storage=10.0.0.1:/images/',
    ],
    'task-capture' => ['type=up'],
    'task-memtest' => ['localboot 0', 'FOG ships no ARM build'],
    'image-ignored' => ['localboot 0', 'ignored for imaging'],
    'host-kernel-override' => [
        'kernel http://10.0.0.1/fog/service/ipxe/arm_Image.custom',
        'initrd http://10.0.0.1/fog/service/ipxe/arm_init.custom.cpio.gz',
    ],
    'host-kernel-args' => ['console=ttyAMA0,115200'],
];
foreach ($expect as $name => $needles) {
    foreach ($needles as $needle) {
        if (false === strpos($rendered[$name], $needle)) {
            $fails[] = "$name: expected to contain '$needle'";
        }
    }
}

/*
 * A deploy tasking must NOT localboot -- that is the whole failure this
 * endpoint exists to prevent, and it is the one an over-eager guard would
 * silently reintroduce.
 */
foreach (['task-deploy', 'task-capture', 'host-kernel-override'] as $name) {
    if (false !== strpos($rendered[$name], 'localboot')) {
        $fails[] = "$name: a tasked host was told to boot locally";
    }
}

if ($fails) {
    foreach ($fails as $f) {
        fwrite(STDERR, "\nFAIL: $f\n");
    }
    exit(1);
}

printf("bootmenu-uboot-output: %d behavior checks passed\n", count($expect));

$expected = file_get_contents($golden);
if ($expected === $actual) {
    echo "PASS\n";
    exit(0);
}

fwrite(STDERR, "\nFAIL: emitted config differs from the golden file.\n\n");
$a = explode("\n", (string)$expected);
$b = explode("\n", $actual);
$shown = 0;
for ($i = 0; $i < max(count($a), count($b)); $i++) {
    if (($a[$i] ?? null) === ($b[$i] ?? null)) {
        continue;
    }
    fwrite(
        STDERR,
        sprintf(
            "  line %d:\n    -golden: %s\n    +actual: %s\n",
            $i + 1,
            $a[$i] ?? '(none)',
            $b[$i] ?? '(none)'
        )
    );
    if (++$shown >= 20) {
        fwrite(STDERR, "  ...\n");
        break;
    }
}
exit(1);
