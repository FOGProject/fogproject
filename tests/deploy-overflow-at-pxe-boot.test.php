<?php
/**
 * A unicast deploy on a full node is moved at PXE boot, into `storage=`.
 *
 * FOS mounts the node named in the kernel argument `storage=`, which the boot
 * menu writes, and never asks again. PR #2082 moved a waiting host at
 * check-in instead: the task list then showed the hosts spread over three
 * nodes while every client still imaged from the master (forums 18310).
 *
 * This renders the real IpxeBootMenu through the boot menu harness and
 * checks the three things that must agree:
 *
 *   - the node in `storage=` is the overflow node;
 *   - the task records that same node;
 *   - the overflow check sees the node a plugin set in the SECOND
 *     BOOT_TASK_NEW_SETTINGS event. The location plugin sets its fixed node
 *     there, so a check made before it would be overwritten.
 *
 * Captures and multicast are never moved.
 *
 * Usage: php tests/deploy-overflow-at-pxe-boot.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$renderer = __DIR__ . '/lib/bootmenu-render.php';
$classFile = dirname(__DIR__) . '/packages/web/src/Boot/IpxeBootMenu.php';

/**
 * Renders one scenario in its own process, as the other boot menu tests do.
 *
 * @param string $renderer  the render script
 * @param array  $scenario  the scenario
 * @param string $classFile the boot menu class file
 *
 * @return string
 */
function renderOverflow($renderer, array $scenario, $classFile)
{
    $cmd = sprintf(
        '%s %s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($renderer),
        escapeshellarg((string)json_encode($scenario)),
        escapeshellarg($classFile)
    );
    $out = shell_exec($cmd);
    return null === $out ? '' : (string)$out;
}

/**
 * A task of the given type for host 1, starting on node 1.
 *
 * @param array $tasktype the task type fields
 *
 * @return array
 */
function overflowTask(array $tasktype)
{
    return [
        'id' => 1,
        'name' => 'testhost',
        'task' => [
            'id' => 1,
            'typeID' => $tasktype['id'],
            'storagenodeID' => 1,
            'tasktype' => $tasktype,
            'image' => [
                'id' => 1,
                'osID' => 50,
                'path' => 'testimage',
                'format' => 5,
                'imagetype' => 'mps',
                'partitiontype' => 'all',
            ],
        ],
    ];
}

$deploy = [
    'id' => 1,
    'imaging' => true,
    'initneeded' => true,
    'kernelArgs' => 'type=down',
];
$capture = [
    'id' => 2,
    'imaging' => true,
    'initneeded' => true,
    'capture' => true,
    'kernelArgs' => 'type=up',
];
$base = [
    'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
    'printTaskNode' => true,
    'extraStorageNodes' => [
        [
            'id' => 2,
            'name' => 'Node2',
            'ip' => '10.0.0.2',
            'path' => '/images/',
            'isEnabled' => 1,
            'isMaster' => 0,
        ],
    ],
];

$results = [];

// The master is over capacity; node 2 has room. The client must image
// from node 2, and the task must say node 2.
$out = renderOverflow(
    $renderer,
    $base + ['host' => overflowTask($deploy), 'overflowTo' => 2],
    $classFile
);
$results[] = [
    false !== strpos($out, 'storage=10.0.0.2:/images/'),
    'a deploy over capacity gets the overflow node in storage='
];
$results[] = [
    false === strpos($out, 'storage=10.0.0.1:'),
    'the full node is not in storage='
];
$results[] = [
    false !== strpos($out, '#harness-task-node 2'),
    'the task records the node the client uses'
];

// A plugin pins node 1 on both hook events, as the location plugin does
// with a fixed node. The overflow check must see that pinned node, and its
// answer must still win.
$pinned = $base + [
    'host' => overflowTask($deploy),
    'overflowTo' => 2,
    'hookMutations' => [
        'BOOT_TASK_NEW_SETTINGS' => ['StorageNode' => ['storagenode' => 1]],
    ],
];
$out = renderOverflow($renderer, $pinned, $classFile);
$results[] = [
    false !== strpos($out, 'storage=10.0.0.2:/images/'),
    'the overflow node survives a plugin that pins the full node'
];
$results[] = [
    false !== strpos($out, '#harness-overflow-asked [1]'),
    'the check runs once, on the node the plugin set last'
];

// No overflow answer: the deploy keeps its node.
$out = renderOverflow($renderer, $base + ['host' => overflowTask($deploy)], $classFile);
$results[] = [
    false !== strpos($out, 'storage=10.0.0.1:/images/')
        && false !== strpos($out, '#harness-task-node 1'),
    'a deploy with room keeps its node'
];

// A capture uploads to the master and is never moved.
$out = renderOverflow(
    $renderer,
    $base + ['host' => overflowTask($capture), 'overflowTo' => 2],
    $classFile
);
$results[] = [
    false !== strpos($out, '#harness-overflow-asked []')
        && false === strpos($out, 'storage=10.0.0.2:'),
    'a capture is never moved'
];

$failed = 0;
foreach ($results as [$ok, $what]) {
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $what);
    $failed += $ok ? 0 : 1;
}
if ($failed) {
    echo "--- last render ---\n", $out;
}
exit($failed ? 1 : 0);
