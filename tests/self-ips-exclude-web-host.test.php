<?php
/**
 * A server's own address list holds only its own addresses.
 *
 * getIPAddress() used to append the FOG_WEB_HOST setting to the list. That
 * setting is the main server's address, and every storage node reads the
 * same database. So every storage node listed the main server's address as
 * its own. checkIfNodeMaster() then matched the main server's storage node
 * row on every node, and each node replicated as the main server: a node
 * with a short copy of a file pushed it over the full copy on the other
 * groups every cycle (forum 18313).
 *
 * The append existed so that waitInterfaceReady() could finish on a storage
 * node: it waited until FOG_WEB_HOST was a local address, which is never
 * true there. So the wait must now pass on a node address alone, as 1.5
 * does, or every storage node hangs at daemon startup.
 *
 * Usage: php tests/self-ips-exclude-web-host.test.php
 * Exit status 0 = pass, 1 = fail.
 */

define('DS', DIRECTORY_SEPARATOR);
// A directory that does not exist: no flush signal, no shared cache file.
define('FOG_CACHE_DIR', sys_get_temp_dir() . DS . 'fog-self-ips-test-' . getmypid());

$repo = dirname(__DIR__);
require $repo . '/packages/web/src/Base/FOGBase.php';

$fails = [];

// TEST-NET-2: never an address on a real interface.
$webHost = '198.51.100.77';

$class = new \ReflectionClass('FOG\Base\FOGBase');
$cache = $class->getProperty('_settingsCache');
$cache->setAccessible(true);
$cache->setValue(null, ['FOG_WEB_HOST' => ['value' => $webHost, 'ts' => time()]]);
$getIPAddress = $class->getMethod('getIPAddress');
$getIPAddress->setAccessible(true);
$ips = (array)$getIPAddress->invoke(null, true);

if (in_array($webHost, $ips, true)) {
    $fails[] = "getIPAddress() lists FOG_WEB_HOST ($webHost) as one of this "
        . 'server\'s own addresses';
}
if (!in_array('127.0.0.1', $ips, true)) {
    $fails[] = 'getIPAddress() no longer lists 127.0.0.1';
}

// waitInterfaceReady(): the loop exit must not need FOG_WEB_HOST to be local.
$src = (string)file_get_contents($repo . '/packages/web/src/Service/FOGService.php');
$at = strpos($src, 'function waitInterfaceReady(');
$open = false === $at ? false : strpos($src, '{', $at);
$wait = null;
if (false !== $open) {
    $depth = 0;
    for ($i = $open, $len = strlen($src); $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i] && 0 === --$depth) {
            $wait = substr($src, $open, $i - $open + 1);
            break;
        }
    }
}
if (null === $wait) {
    $fails[] = 'FOGService::waitInterfaceReady() not found';
} elseif (!preg_match('/if\s*\((.*?)\)\s*\{\s*break;/s', $wait, $m)) {
    $fails[] = 'waitInterfaceReady(): loop exit condition not found';
} else {
    if (false !== strpos($m[1], 'FOG_WEB_HOST')) {
        $fails[] = 'waitInterfaceReady() still waits for FOG_WEB_HOST to be a '
            . 'local address; a storage node never passes that';
    }
    if (false === strpos($m[1], 'array_intersect(')
        || false === strpos($m[1], 'knownips')
    ) {
        $fails[] = 'waitInterfaceReady() no longer waits for a storage node '
            . 'address to be up';
    }
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: own address list excludes FOG_WEB_HOST; interface wait needs a node address only\n";
exit(0);
