<?php
/**
 * service/hostinfo.php sends the same task variables as the kernel line.
 *
 * ADR 0023 (fos): FOS reads its task settings from service/hostinfo.php when
 * the kernel line carries taskinfo=1, and a value in the reply overrides the
 * kernel line. So the reply must equal the kernel line in both directions:
 * every task variable on the kernel line is in the reply with the same value,
 * and every task variable in the reply is on the kernel line with the same
 * value. hostinfo.php builds them with BootMenuBase::taskKernelArgs(), the
 * code that builds the kernel line. This test proves it on real output.
 *
 * Each scenario renders once in a child process of tests/lib/bootmenu-render.php
 * (see tests/lib/hostinfo-parity.php): the boot menu prints its kernel line,
 * then the script's own multicast image and storage node choice and its merge
 * block, cut out of hostinfo.php by their anchors, build the reply fields from
 * the same fixtures.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';
require_once __DIR__ . '/lib/hostinfo-parity.php';

$t = new \FogChecks();

// BootMenuBase::TASK_KEYS, read from a render process: the class needs the
// whole runtime to load. hostinfo.php must send a key of it only when the
// kernel line carries it.
$taskKeys = [];

// A plugin hook that picks node 2, and an over-capacity move to node 3.
$nodes = [
    'extraStorageNodes' => [
        ['id' => 2, 'name' => 'Location', 'ip' => '10.0.0.2', 'path' => '/images/', 'isEnabled' => 1, 'isMaster' => 0],
        ['id' => 3, 'name' => 'Overflow', 'ip' => '10.0.0.3', 'path' => '/images/', 'isEnabled' => 1, 'isMaster' => 0],
    ],
    'hookMutations' => ['BOOT_TASK_NEW_SETTINGS' => ['StorageNode' => ['storagenode' => 2]]],
    'overflowTo' => 3,
];

$scenarios = [
    'deploy' => parityScenario(PARITY_DEPLOY, ['kernelDevice' => '/dev/sdb'], [], [
        'settings' => [
            'FOG_CHANGE_HOSTNAME_EARLY' => '1',
            'FOG_MULTICAST_RENDEZVOUS' => '10.0.0.7',
        ],
    ]),
    'capture' => parityScenario(PARITY_CAPTURE, [], [], [
        'settings' => ['FOG_CAPTURERESIZEPCT' => '20', 'FOG_CAPTUREIGNOREPAGEHIBER' => '1'],
    ]),
    // The kernel line adds dev/ to storage= for a capture and for a task
    // that is not an imaging task.
    'non-imaging' => parityScenario(PARITY_NON_IMAGING),
    // A task that needs no init has no image data at all.
    'no-init' => parityScenario(PARITY_NO_INIT),
    'wipe-seconds' => parityScenario(
        PARITY_WIPE,
        [],
        [],
        ['settings' => ['FOG_WIPE_TIMEOUT' => '120']]
    ),
    'multicast' => parityScenario(
        PARITY_MULTICAST,
        [],
        ['imageID' => 1],
        ['mcastAssoc' => [['id' => 1, 'taskID' => 1, 'msID' => 1, 'image' => 1, 'port' => 53000, 'maxwait' => 7]]]
    ),
    // The task names image 1 and the session streams image 2. Both outputs
    // must name the stream's image.
    'multicast-image' => parityScenario(
        PARITY_MULTICAST,
        [],
        ['imageID' => 1],
        [
            'mcastAssoc' => [['id' => 1, 'taskID' => 1, 'msID' => 1, 'image' => 2, 'port' => 53000, 'maxwait' => 7]],
            'imagesById' => [2 => parityImage(['id' => 2, 'osID' => 10, 'path' => 'streamimage'])],
        ]
    ),
    'debug-shutdown-bitlocker' => parityScenario(
        ['kernelArgs' => 'type=down mode=debug'] + PARITY_DEPLOY,
        [],
        ['isDebug' => 1, 'shutdown' => 1, 'bypassbitlocker' => 1]
    ),
    'winuser' => parityScenario(PARITY_WINPASS, [], ['passreset' => 'Administrator']),
    // array_unique drops the host's repeated chkdsk=1, so the kernel line
    // ends with chkdsk=0 and the reply must too.
    'chkdsk-precedence' => parityScenario(PARITY_DEPLOY, ['kernelArgs' => 'chkdsk=1'], [], [
        'settings' => ['FOG_DISABLE_CHKDSK' => '0', 'FOG_KERNEL_ARGS' => 'chkdsk=0'],
    ]),
    'host-args' => parityScenario(PARITY_DEPLOY, ['kernelArgs' => 'a=b=c splash x=1'], [], [
        'settings' => ['FOG_KERNEL_ARGS' => 'g=1 quiet x=0'],
    ]),
    // The kernel line carries the raw node address; a name would resolve.
    'ipv6-node' => parityScenario(PARITY_DEPLOY, [], [], ['storageNodeIp' => 'fd00::2']),
    // A chain back into the task adds shutdown=1 to the kernel line. The
    // reply has no extraargs, so it must send no shutdown: FOS would let a
    // shutdown=0 here override the kernel line and reboot instead of powering off.
    'extraargs-shutdown' => parityScenario(PARITY_DEPLOY, [], [], [
        'request' => ['arch' => 'x86_64', 'platform' => 'bios', 'extraargs' => 'shutdown=1'],
    ]),
    // A listener on HOST_INFO_EXPOSE sets shutdown=0 and chkdsk=9. Neither may
    // reach the reply: the kernel line has no shutdown, and FOS would let the
    // reply's value override it.
    'hook-task-keys' => parityScenario(PARITY_DEPLOY, [], [], [
        'hookMutations' => ['HOST_INFO_EXPOSE' => ['repFields' => ['shutdown' => '0', 'chkdsk' => '9']]],
    ]),
    // A PXE task boot: the boot menu's hook and overflow chose node 3 and
    // recorded it. The reply keeps it, though the hook picks node 2.
    'node-recorded' => parityScenario(PARITY_DEPLOY, [], [], $nodes + ['hostinfoTaskinfo' => true]),
    // A task from registration has not been through the boot menu, so the
    // hook still chooses its node: node 2, not the recorded node 3.
    'node-registration' => parityScenario(PARITY_DEPLOY, [], [], $nodes),
];

// Keys where a scenario's reply differs from its kernel line on purpose.
$differs = [
    'extraargs-shutdown' => ['shutdown'],
    'node-registration' => ['storage', 'storageip', 'ftp'],
];

$results = [];
foreach ($scenarios as $name => $scenario) {
    list($kernel, $fields, $out) = renderParity($scenario);
    $vars = kernelVars($kernel);
    $results[$name] = [$vars, $fields];
    if ([] === $taskKeys) {
        $taskKeys = taskKeysFrom($out);
        $t->check('BootMenuBase::TASK_KEYS is read from the render', count($taskKeys) > 20);
    }
    // A key on the kernel line is a task variable, or one the scenario's own
    // arguments name. A new variable that is not in TASK_KEYS fails here.
    $userArgs = ($scenario['host']['task']['tasktype']['kernelArgs'] ?? '') . ' '
        . ($scenario['settings']['FOG_KERNEL_ARGS'] ?? '') . ' '
        . ($scenario['host']['kernelArgs'] ?? '');
    $userKeys = [];
    foreach (preg_split('/\s+/', trim($userArgs)) as $token) {
        if (false !== strpos($token, '=')) {
            $userKeys[] = explode('=', $token, 2)[0];
        }
    }
    $unlisted = array_diff(array_keys($vars), $taskKeys, $userKeys);
    $t->check("$name: every kernel line variable is in TASK_KEYS", [] === $unlisted);
    if ($unlisted) {
        fwrite(STDERR, "$name has kernel variables not in TASK_KEYS: " . implode(', ', $unlisted) . "\n");
    }
    $t->check("$name: the kernel line and the hostinfo fields rendered", '' !== $kernel && is_array($fields));
    if (!is_array($fields)) {
        fwrite(STDERR, "--- $name render output ---\n$out\n");
        continue;
    }
    $skip = $differs[$name] ?? [];
    $diff = [];
    foreach ($vars as $key => $value) {
        if (in_array($key, $skip, true)) {
            continue;
        }
        if (!array_key_exists($key, $fields) || (string)$fields[$key] !== (string)$value) {
            $diff[] = $key . ' kernel=' . $value . ' hostinfo=' . ($fields[$key] ?? '(absent)');
        }
    }
    $t->check("$name: every task variable on the kernel line is in the reply, equal", [] === $diff);
    if ($diff) {
        fwrite(STDERR, "$name differs: " . implode('; ', $diff) . "\n");
    }
    // The other direction: a task variable in the reply is on the kernel line.
    $extra = [];
    foreach ($taskKeys as $key) {
        if (in_array($key, $skip, true)) {
            continue;
        }
        if (array_key_exists($key, $fields)
            && (!array_key_exists($key, $vars) || (string)$vars[$key] !== (string)$fields[$key])
        ) {
            $extra[] = $key . ' hostinfo=' . $fields[$key] . ' kernel=' . ($vars[$key] ?? '(absent)');
        }
    }
    $t->check("$name: every task variable in the reply is on the kernel line, equal", [] === $extra);
    if ($extra) {
        fwrite(STDERR, "$name sends more than the kernel line: " . implode('; ', $extra) . "\n");
    }
}

// Spot checks, so a pass cannot come from both sides being empty.
$f = $results['deploy'][1];
$t->check('deploy: mac is the host MAC, not the request list', '00:11:22:33:44:55' === $f['mac']);
$t->check('deploy: the reply carries taskinfo=1', '1' === ($f['taskinfo'] ?? ''));
$t->check('deploy: imaging fields are sent', 'testimage' === ($f['img'] ?? '') && '3' === ltrim((string)($f['PIGZ_COMP'] ?? ''), '-'));
$t->check('deploy: hostearly and the kernel device are sent', '1' === ($f['hostearly'] ?? '') && '/dev/sdb' === ($f['fdrive'] ?? ''));
$t->check('deploy: no shutdown is sent when nothing asks for one', !array_key_exists('shutdown', $f));
$f = $results['capture'][1];
$t->check('capture: pct, ignorepg and a dev/ storage are sent', '20' === ($f['pct'] ?? '') && '1' === ($f['ignorepg'] ?? '') && '/dev/' === substr((string)($f['storage'] ?? ''), -5));
$f = $results['non-imaging'][1];
$t->check('non-imaging: storage ends in dev/ and no imaging field is sent', '/dev/' === substr((string)($f['storage'] ?? ''), -5) && !isset($f['img']) && !isset($f['chkdsk']));
$f = $results['wipe-seconds'][1];
$t->check('wipe: seconds is the wipe timeout', '120' === ($f['seconds'] ?? ''));
$f = $results['multicast'][1];
$t->check('multicast: port and mcastMaxWait come from the session', '53000' === ($f['port'] ?? '') && '7' === ($f['mcastMaxWait'] ?? ''));
list($v, $f) = $results['multicast-image'];
$t->check(
    'multicast image: the kernel line and the reply both name the stream image',
    'streamimage' === ($v['img'] ?? '') && 'streamimage' === ($f['img'] ?? '') && '2' === ($f['imgid'] ?? '') && 'windows' === ($f['osfamily'] ?? '')
);
$f = $results['debug-shutdown-bitlocker'][1];
$t->check(
    'debug: isdebug, shutdown and bitlockerbypass are sent, and mode=debug is not',
    'yes' === ($f['isdebug'] ?? '') && '1' === ($f['shutdown'] ?? '') && '1' === ($f['bitlockerbypass'] ?? '') && !isset($f['mode'])
);
$f = $results['winuser'][1];
$t->check('winuser is sent for task type 11', 'Administrator' === ($f['winuser'] ?? ''));
$f = $results['chkdsk-precedence'][1];
$t->check('precedence: FOG_KERNEL_ARGS chkdsk=0 beats the host chkdsk=1 after de-duplication', '0' === ($f['chkdsk'] ?? ''));
$f = $results['host-args'][1];
$t->check(
    'host arguments: a value keeps its =, the host wins over FOG_KERNEL_ARGS, a flag is skipped',
    'b=c' === ($f['a'] ?? '') && '1' === ($f['x'] ?? '') && '1' === ($f['g'] ?? '') && !isset($f['splash']) && !isset($f['quiet'])
);
$f = $results['ipv6-node'][1];
$t->check('ipv6 node: storageip is the raw address', 'fd00::2' === ($f['storageip'] ?? ''));
list($v, $f) = $results['extraargs-shutdown'];
$t->check(
    'extraargs: the kernel line has shutdown=1 and the reply has no shutdown key',
    '1' === ($v['shutdown'] ?? '') && !array_key_exists('shutdown', $f)
);
list($v, $f) = $results['hook-task-keys'];
$t->check(
    'a HOST_INFO_EXPOSE listener cannot send shutdown, and cannot change a task variable',
    !array_key_exists('shutdown', $f) && !array_key_exists('shutdown', $v) && ($v['chkdsk'] ?? '') === ($f['chkdsk'] ?? 'x')
);
list($v, $f) = $results['node-recorded'];
$t->check(
    'node: a PXE task boot keeps the recorded overflow node 3, not the hook node 2',
    '10.0.0.3' === ($v['storageip'] ?? '') && '10.0.0.3' === ($f['storageip'] ?? '') && '10.0.0.3' === ($f['ftp'] ?? '')
);
list($v, $f) = $results['node-registration'];
$t->check(
    'node: a task that has not booted still takes the hook node 2',
    '10.0.0.3' === ($v['storageip'] ?? '') && '10.0.0.2' === ($f['storageip'] ?? '') && '10.0.0.2:/images/' === ($f['storage'] ?? '')
);

/*
 * The render above starts from an empty field list: the script's own list
 * needs the host, the inventory and the AD account. So read that list's keys
 * from the source, and require that none is a task variable. A task variable
 * set there would override the kernel line in FOS where the kernel line omits
 * it.
 */
$source = (string)file_get_contents(dirname(__DIR__) . '/packages/web/service/hostinfo.php');
$listStart = strpos($source, '    $repFields = [');
$listEnd = false === $listStart ? false : strpos($source, "\n    ];", $listStart);
$t->check('the reply field list is found by its anchors', false !== $listStart && false !== $listEnd);
$ownKeys = [];
if (false !== $listStart && false !== $listEnd) {
    preg_match_all(
        "/^        '([A-Za-z_]+)' =>/m",
        substr($source, $listStart, $listEnd - $listStart),
        $m
    );
    $ownKeys = $m[1];
}
$t->check('the reply field list has keys', count($ownKeys) > 10);
$t->check(
    'the reply field list sets no task variable of the kernel line',
    [] === array_intersect($ownKeys, $taskKeys)
);

$t->finish();
