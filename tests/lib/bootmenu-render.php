<?php
/**
 * Renders one IpxeBootMenu scenario and prints the resulting iPXE script.
 *
 * Run as a child process by bootmenu-ipxe-output.test.php. It is a separate
 * process because several IpxeBootMenu paths end in exit() -- noMenu() and the
 * tasking path among them -- which a single-process runner could not survive,
 * and because IpxeBootMenu caches the architecture profile in a static
 * (self::$_archProfile) that a second scenario in the same process would
 * inherit.
 *
 * Usage: php bootmenu-render.php <scenario-json> <path-to-bootmenu.class.php>
 */

require_once __DIR__ . '/bootmenu-harness.php';
// Publishes the harness's flat FOG\ stubs -- FOGBase, Route, StorageNode,
// PXEMenuOptions -- under the bucket names both this file's `use` lines and
// IpxeBootMenu's own imports expect since Move 2. Must run before the first
// use of a bucketed name, not just before the class under test is required.
require_once __DIR__ . '/stub-buckets.php';

use FOG\Base\FOGBase;
use FOG\Router\Route;
use FOG\StubHookManager;
use FOG\StubHost;

$scenario = json_decode($argv[1] ?? '{}', true);
$classFile = $argv[2] ?? '';

if (!is_array($scenario)) {
    fwrite(STDERR, "render: could not decode scenario json\n");
    exit(2);
}
if (!is_file($classFile)) {
    fwrite(STDERR, "render: no such class file: $classFile\n");
    exit(2);
}

/*
 * BASEPATH decides which Secure Boot branches render: MOK.der gates the
 * attended enrol inside _enrollSecureBootChoice(), and PK/KEK/db.auth gate
 * whether _filterMenus() shows the unattended one at all. Point it at a
 * scenario-owned temp directory so every combination is reachable without
 * touching a real install.
 */
$base = sys_get_temp_dir() . '/bootmenu-test-' . getmypid() . '/';
@mkdir($base . 'service/secureboot', 0777, true);
$sbFiles = [];
if (!empty($scenario['mok'])) {
    $sbFiles[] = 'MOK.der';
}
if (!empty($scenario['authvars'])) {
    $sbFiles = array_merge($sbFiles, ['PK.auth', 'KEK.auth', 'db.auth']);
}
foreach ($sbFiles as $f) {
    file_put_contents($base . 'service/secureboot/' . $f, 'test');
}
if (!defined('BASEPATH')) {
    define('BASEPATH', $base);
}

/*
 * Several IpxeBootMenu paths end in exit(), so the only reliable place to clean
 * up the scenario's temp tree is a shutdown handler.
 */
register_shutdown_function(
    function () use ($base) {
        foreach (glob($base . 'service/secureboot/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (['service/secureboot', 'service', ''] as $dir) {
            @rmdir($base . $dir);
        }
    }
);

/**
 * Default globalSettings values, mirroring commons/schema.php so the golden
 * file reflects what a stock install actually emits.
 */
FOGBase::$settings = array_merge(
    [
        'FOG_ADVANCED_MENU_LOGIN' => '0',
        'FOG_BOOT_EXIT_TYPE' => 'sanboot',
        'FOG_EFI_BOOT_EXIT_TYPE' => 'refind_efi',
        'FOG_IMAGE_LIST_MENU' => '0',
        'FOG_IPXE_BG_FILE' => 'bg.png',
        'FOG_IPXE_HOST_CPAIRS' => "cpair --foreground 1 1 ||\n"
            . "cpair --foreground 0 3 ||\ncpair --foreground 4 4 ||",
        'FOG_IPXE_INVALID_HOST_COLOURS' => 'colour --rgb 0xff0000 0 ||',
        'FOG_IPXE_MAIN_COLOURS' => "colour --rgb 0x00567a 1 ||\n"
            . "colour --rgb 0x00567a 2 ||\ncolour --rgb 0x00567a 4 ||",
        'FOG_IPXE_MAIN_CPAIRS' => 'cpair --foreground 7 --background 2 2 ||',
        'FOG_IPXE_MAIN_FALLBACK_CPAIRS' => "cpair --background 0 1 ||\n"
            . "cpair --background 1 2 ||",
        'FOG_IPXE_VALID_HOST_COLOURS' => 'colour --rgb 0x00567a 0 ||',
        'FOG_KERNEL_ARGS' => '',
        'FOG_KERNEL_DEBUG' => '',
        'FOG_KERNEL_LOGLEVEL' => '4',
        'FOG_KERNEL_RAMDISK_SIZE' => '275000',
        'FOG_KEYMAP' => '',
        'FOG_KEY_SEQUENCE' => '',
        'FOG_MEMTEST_KERNEL' => 'memtest.bin',
        'FOG_NO_MENU' => '0',
        'FOG_PXE_ADVANCED' => '0',
        'FOG_PXE_BOOT_IMAGE' => 'init.xz',
        'FOG_PXE_BOOT_IMAGE_32' => 'init_32.xz',
        'FOG_PXE_BOOT_IMAGE_ARM' => 'arm_init.cpio.gz',
        'FOG_PXE_HIDDENMENU_TIMEOUT' => '3',
        'FOG_PXE_MENU_HIDDEN' => '0',
        'FOG_PXE_MENU_TIMEOUT' => '3',
        'FOG_REGISTRATION_ENABLED' => '1',
        'FOG_TFTP_PXE_KERNEL' => 'bzImage',
        'FOG_TFTP_PXE_KERNEL_32' => 'bzImage32',
        'FOG_TFTP_PXE_KERNEL_ARM' => 'arm_Image',
        'FOG_WEB_HOST' => '10.0.0.1',
        'FOG_WEB_ROOT' => '/fog/',
    ],
    (array)($scenario['settings'] ?? [])
);

/**
 * The stock pxeMenu rows, per commons/schema.php. pxeParams is only set on
 * the rows schema.php gives one to, because _menuOpt() branches on whether a
 * row has params at all.
 *
 * @param string $extra the distinguishing param line
 *
 * @return string
 */
$loginParams = function ($extra) {
    return "login\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\n"
        . "param username \${username}\nparam password \${password}\n"
        . "param $extra\n"
        . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
        . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme";
};
$rows = [
    [1, 'fog.local', 'Boot from hard disk', '1', '2', null, ''],
    [2, 'fog.memtest', 'Run Memtest86+', '0', '2', null, ''],
    [3, 'fog.reginput', 'Perform Full Host Registration and Inventory',
        '0', '0', 'mode=manreg', ''],
    [4, 'fog.keyreg', 'Update Product Key', '0', '1', null,
        $loginParams('keyreg 1')],
    [5, 'fog.reg', 'Quick Registration and Inventory', '0', '0',
        'mode=autoreg', ''],
    [6, 'fog.deployimage', 'Deploy Image', '0', '1', null,
        $loginParams('qihost 1')],
    [7, 'fog.multijoin', 'Join Multicast Session', '0', '1', null,
        $loginParams('sessionJoin 1')],
    [8, 'fog.quickdel', 'Quick Host Deletion', '0', '1', null,
        $loginParams('delhost 1')],
    [9, 'fog.sysinfo', 'Client System Information (Compatibility)',
        '0', '2', 'mode=sysinfo', ''],
    [10, 'fog.debug', 'Debug Mode', '0', '3', 'mode=onlydebug', ''],
    [11, 'fog.advanced', 'Advanced Menu', '0', '4', null, ''],
    [12, 'fog.advancedlogin', 'Advanced Menu', '0', '5', null,
        $loginParams('advLog 1')],
    [13, 'fog.approvehost', 'Approve This Host', '0', '6', null,
        $loginParams('approveHost 1')],
    [14, 'fog.enrollsecureboot', 'Enroll Secure Boot Key', '0', '2', null, ''],
    [15, 'fog.enrollsecurebootunattended',
        'Enroll Secure Boot Key (Unattended - secure boot in setup mode '
        . 'required)', '0', '2', 'mode=enrollsb', ''],
];

Route::$rows = [
    'pxemenuoptions' => array_map(
        function ($r) {
            return (object)[
                'id' => $r[0],
                'name' => $r[1],
                'description' => $r[2],
                'default' => $r[3],
                'regMenu' => $r[4],
                'args' => $r[5],
                'params' => $r[6],
                'hotkey' => '0',
                'keysequence' => '',
            ];
        },
        $rows
    ),
    'storagenode' => [
        (object)[
            'id' => 1,
            'name' => 'DefaultMember',
            'ip' => '10.0.0.1',
            'path' => '/images/',
            'isEnabled' => 1,
            'isMaster' => 1,
        ],
    ],
    'image' => [
        (object)[
            'id' => 1, 'name' => 'Win11', 'path' => 'win11', 'isEnabled' => 1,
        ],
        (object)[
            'id' => 2, 'name' => 'Ubuntu', 'path' => 'ubuntu', 'isEnabled' => 1,
        ],
    ],
    'host' => [],
    'inventory' => [],
    'ipxe' => [],
    'multicastsessionassociation' => [],
];

/*
 * The host row Route::getItem('host', id) returns, which generateIpxeItems()
 * turns into `set ...` lines. Scenario-supplied extra columns land here too,
 * so a scenario can plant a secret field and assert it never reaches iPXE.
 */
$hostData = (array)($scenario['host'] ?? []);
if (!empty($hostData['id'])) {
    $row = ['id' => $hostData['id'], 'name' => $hostData['name'] ?? 'testhost'];
    foreach ((array)($scenario['hostRow'] ?? []) as $k => $v) {
        $row[$k] = $v;
    }
    Route::$rows['host'][] = (object)$row;
}

FOGBase::$Host = new StubHost($hostData);
FOGBase::$HookManager = new StubHookManager(
    (array)($scenario['hookMutations'] ?? [])
);

$_REQUEST = (array)($scenario['request'] ?? []);
$_GET = $_REQUEST;

require_once $classFile;

if (!empty($scenario['hooks'])) {
    /*
     * Hook-payload mode: what a plugin receives is not visible in the emitted
     * script, so it cannot be covered by the golden file. Swallow the script
     * and report the payloads instead.
     */
    ob_start();
    new \FOG\Boot\IpxeBootMenu();
    ob_end_clean();
    echo json_encode(FOGBase::$HookManager->fired, JSON_PRETTY_PRINT), "\n";
    exit(0);
}

new \FOG\Boot\IpxeBootMenu();
