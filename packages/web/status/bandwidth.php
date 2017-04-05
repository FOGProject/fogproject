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
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
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
$getBytes = function ($dev, $file) {
    if (!is_string($dev)) {
        throw new Exception(_('Device must be a string'));
    }
    if (!is_string($file)) {
        throw new Exception(_('File must be a string'));
    }
    if (!in_array($file, array('tx_bytes', 'rx_bytes'))) {
        throw new Exception(_('Only tx and rx bytes files can be read'));
    }
    $path = "/sys/class/net/$dev/statistics/$file";
    if (!(file_exists($path) && is_readable($path))) {
        return 0;
    } else {
        $data = file_get_contents($path);
        return trim($data);
    }
};
// Make sure a device is set
if (!isset($_REQUEST['dev'])) {
    $_REQUEST['dev'] = 'eth0';
}
// Only use the last bit in case somebody is doing stuff bad
$baseint = basename($_REQUEST['dev']);
$dev = trim($baseint);
// Directory to check for interfaces and get all system interfaces
$scan = scandir('/sys/class/net');
// Filter out dots
$dir_interfaces = array_diff(
    $scan,
    array(
        '..',
        '.'
    )
);
// Initiate our interfaces variable
$interfaces = array();
// Loop the captured data and set up interfaces
foreach ($dir_interfaces as &$iface) {
    $operstateFile = "/sys/class/net/$iface/operstate";
    $content = file_get_contents($operstateFile);
    $content = trim($content);
    if ($content !== 'up') {
        continue;
    }
    $interfaces[] = $iface;
    unset($iface);
};
// Check up interfaces to see if our specified device is present
$interface = preg_grep("#^$dev$#", $interfaces);
// If our interface isn't found, try getting it directly off the system
if (count($interface) < 1) {
    // Find our server address
    $srvAddr = $_SERVER['SERVER_ADDR'];
    // If accessed by hostname resolve to ip
    $resName = FOGCore::resolveHostname($srvAddr);
    // Use the resolved name to find our interface
    $dev = FOGCore::getMasterInterface($resname);
}
// Trim the device
$dev = trim($dev);
// If the device is not set or found return Unknown
if (!$dev) {
    $ret = array(
        'dev' => 'Unknown',
        'rx' => 0,
        'tx' => 0,
    );
    echo json_encode($ret);
    exit;
}
// Set our rx and tx data values
$rx = $getBytes($dev, 'rx_bytes');
$tx = $getBytes($dev, 'tx_bytes');
// Setup our return array
$ret = array(
    'dev' => $dev,
    'rx' => $rx,
    'tx' => $tx,
);
// Return
echo json_encode($ret);
exit;
