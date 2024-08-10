<?php
/**
 * Gets bandwidth usage of requested interface
 *
 * If interface cannot be found it will try to get it more
 * directly from within linux.
 *
 * PHP version 5
 *
 * @category Bandwidth
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Gets bandwidth usage of requested interface
 *
 * @category Bandwidth
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
header('Content-Type: application/json');
/**
 * Lambda for returning the bytes from the file requested.
 *
 * If the path specified is not found or not existing, return 0
 * otherwise return the data.
 *
 * @param string $dev  the device label to search
 * @param string $file the file to get data from
 *
 * @throws Exception
 * @return int
 */
$getBytes = function ($dev) {
    if (!is_string($dev)) {
        throw new Exception(_('Device must be a string'));
    }
    $txpath = "/sys/class/net/$dev/statistics/tx_bytes";
    $rxpath = "/sys/class/net/$dev/statistics/rx_bytes";
    $tx = (int)trim(file_get_contents($txpath));
    $rx = (int)trim(file_get_contents($rxpath));
    return [$rx,$tx];
};
// Make sure a device is set
$dev = filter_input(INPUT_GET, 'dev');
if (!$dev) {
    $dev = 'eth0';
}
// Only use the last bit in case somebody is doing stuff bad
$dev = trim(basename($dev));
// Directory to check for interfaces and get all system interfaces
$scan = scandir('/sys/class/net');
// Filter out dots
$dir_interfaces = array_diff(
    $scan,
    [
        '..',
        '.'
    ]
);
// Initiate our interfaces variable
$interfaces = [];
// Loop the captured data and set up interfaces
foreach ($dir_interfaces as $iface) {
    $operstateFile = "/sys/class/net/$iface/operstate";
    $content = file_get_contents($operstateFile);
    $content = trim($content);
    if ($content !== 'up') {
        continue;
    }
    $interfaces[] = $iface;
};
// Check up interfaces to see if our specified device is present
$interface = preg_grep("#^$dev$#", $interfaces);
// If our interface isn't found, try getting it directly off the system
if (count($interface) < 1) {
    include '../commons/base.inc.php';
    ignore_user_abort(true);
    set_time_limit(0);
    FOGCore::is_authorized();
    // Find our server address
    $srvAddr = $_SERVER['SERVER_ADDR'];
    // If accessed by hostname resolve to ip
    $resName = FOGCore::resolveHostname($srvAddr);
    // Use the resolved name to find our interface
    $dev = FOGCore::getMasterInterface($resName);
}
// Trim the device
$dev = trim($dev);
// If the device is not set or found return Unknown
if (!$dev) {
    $ret = [
        'dev' => 'Unknown',
        'rx' => 0,
        'tx' => 0,
    ];
    echo json_encode($ret);
    exit;
}
// Set our rx and tx data values
list($rxlast, $txlast) = $getBytes($dev);
usleep(100000);
list($rxcur, $txcur) = $getBytes($dev);
$rx = round(ceil(($rxcur - $rxlast) / 1024 * 8 / 100), 2);
$tx = round(ceil(($txcur - $txlast) / 1024 * 8 / 100), 2);
// Setup our return array
$ret = [
    'dev' => $dev,
    'rx' => (int)$rx,
    'tx' => (int)$tx,
];
// Return
echo json_encode($ret);
exit;
