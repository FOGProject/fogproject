<?php
/**
 * mcastrdv= reaches FOS only when it is the storage node's IP family.
 *
 * FOS gives udp-receiver the multicast rendezvous address. The receiver
 * joins the node's session in the node's own family, so an IPv4 rendezvous
 * address on an IPv6 node (or the other way round) cannot work. The kernel
 * line leaves it off then. An IPv4 pair is unchanged, which the golden in
 * bootmenu-ipxe-output.test.php pins for the no-setting case; this test
 * pins the matrix for both emitters:
 *
 *   BootMenuBase   a host's task (kernel line built for a tasking)
 *   IpxeBootMenu   falseTasking(), the unregistered-host deploy
 *
 * Renders through tests/lib/bootmenu-render.php, one process per scenario,
 * like bootmenu-ipxe-output.test.php.
 *
 * Usage: php tests/bootmenu-mcastrdv-family.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$renderer = __DIR__ . '/lib/bootmenu-render.php';
$classFile = dirname(__DIR__) . '/packages/web/src/Boot/IpxeBootMenu.php';

$image = [
    'id' => 1,
    'osID' => 50,
    'path' => 'testimage',
    'format' => 5,
    'imagetype' => 'mps',
    'partitiontype' => 'all',
];
$bases = [
    'host task' => [
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
                'image' => $image,
            ],
        ],
    ],
    'false tasking' => [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios'],
        'requestMacs' => ['aa:bb:cc:dd:ee:ff'],
        'falseTasking' => $image,
    ],
];

// [rendezvous, node, kept?]
$matrix = [
    ['239.0.0.9', '192.0.2.5', true],
    ['ff15::9', 'fd00::2', true],
    ['239.0.0.9', 'fd00::2', false],
    ['ff15::9', '192.0.2.5', false],
    // A hostname node is resolved as IPv4 by the multicast daemon.
    ['239.0.0.9', 'node1.example.test', true],
    ['ff15::9', 'node1.example.test', false],
];

$failures = [];
foreach ($bases as $label => $base) {
    foreach ($matrix as list($rdv, $node, $kept)) {
        $scenario = array_merge(
            $base,
            [
                'settings' => ['FOG_MULTICAST_RENDEZVOUS' => $rdv],
                'storageNodeIp' => $node,
            ]
        );
        $out = (string)shell_exec(
            sprintf(
                '%s %s %s %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($renderer),
                escapeshellarg(json_encode($scenario)),
                escapeshellarg($classFile)
            )
        );
        $has = false !== strpos($out, "mcastrdv=$rdv");
        // A render that printed no kernel line proves nothing either way.
        if (false === strpos($out, "storageip=$node")) {
            $failures[] = "$label, rdv $rdv, node $node: no kernel line "
                . 'rendered: ' . substr($out, 0, 200);
        } elseif ($has !== $kept) {
            $failures[] = "$label, rdv $rdv, node $node: mcastrdv= is "
                . ($has ? 'present' : 'absent')
                . ', expected ' . ($kept ? 'present' : 'absent');
        }
    }
}

if ($failures) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}
echo "ok: mcastrdv= follows the storage node's IP family in both emitters\n";
exit(0);
