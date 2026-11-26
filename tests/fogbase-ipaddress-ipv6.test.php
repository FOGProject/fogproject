<?php
/**
 * getIPAddress() lists this server's global IPv6 addresses after its IPv4 ones.
 *
 * A storage node row can hold an IPv6 literal (IPv6 multicast). The daemons
 * find their own node by looking its address up in getIPAddress(), so an
 * IPv6-only node never matched itself. The list now adds the global IPv6
 * addresses `ip -6` reports, minus the ones that cannot name a node:
 * tentative, deprecated and temporary (privacy) addresses.
 *
 * `exec` is replaced by a stub in the FOG\Base namespace, so the test feeds
 * getIPAddress() the text `ip` prints and needs no IPv6 on the box.
 *
 * Usage: php tests/fogbase-ipaddress-ipv6.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG\Base {
    /**
     * Stub for the exec() calls inside getIPAddress().
     *
     * @param string $cmd    the command
     * @param array  $output lines
     * @param int    $ret    status
     *
     * @return string
     */
    function exec($cmd, &$output = null, &$ret = 0)
    {
        $output = [];
        $ret = 0;
        if (false !== strpos($cmd, 'ip -4')) {
            $output = $GLOBALS['stubIp4'];
        } elseif (false !== strpos($cmd, 'ip -6')) {
            $output = $GLOBALS['stubIp6'];
        }
        return '';
    }
}

namespace {
    define('DS', DIRECTORY_SEPARATOR);
    define('FOG_CACHE_DIR', sys_get_temp_dir() . DS . 'fog-ipaddr6-test-' . getmypid());

    $repo = dirname(__DIR__);
    require $repo . '/packages/web/src/Base/FOGBase.php';

    $fails = [];

    // What the first exec() in getIPAddress() returns: already reduced to
    // addresses by its own awk.
    $stubIp4 = ['192.0.2.10', '10.9.8.7'];
    // `ip -6 -o addr show scope global` output.
    $tail = 'valid_lft forever preferred_lft forever';
    $stubIp6 = [
        "2: eth0    inet6 fd00:f06:0:1::1/64 scope global \\       $tail",
        "2: eth0    inet6 fd00:f06:0:1:aaaa::5/64 scope global dynamic mngtmpaddr noprefixroute \\       $tail",
        "2: eth0    inet6 fd00:f06:0:1:bbbb::6/64 scope global tentative \\       $tail",
        "2: eth0    inet6 fd00:f06:0:1:cccc::7/64 scope global deprecated dynamic \\       $tail",
        "2: eth0    inet6 fd00:f06:0:1:dddd::8/64 scope global temporary dynamic \\       $tail",
    ];

    $class = new \ReflectionClass('FOG\Base\FOGBase');
    $get = $class->getMethod('getIPAddress');
    $get->setAccessible(true);
    $ips = (array)$get->invoke(null, true);

    foreach (['fd00:f06:0:1::1', 'fd00:f06:0:1:aaaa::5'] as $want) {
        if (!in_array($want, $ips, true)) {
            $fails[] = "global IPv6 address $want is not listed";
        }
    }
    foreach (['bbbb::6' => 'tentative', 'cccc::7' => 'deprecated', 'dddd::8' => 'temporary'] as $tail6 => $why) {
        foreach ($ips as $ip) {
            if (false !== strpos($ip, $tail6)) {
                $fails[] = "a $why IPv6 address is listed: $ip";
            }
        }
    }

    // The IPv4 entries are all there, in the same order they had before.
    $v4 = array_values(array_filter($ips, function ($ip) {
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }));
    $expect = ['10.9.8.7', '127.0.0.1', '127.0.1.1', '192.0.2.10'];
    if ($v4 !== $expect) {
        $fails[] = 'IPv4 entries changed: ' . implode(',', $v4)
            . ' (expected ' . implode(',', $expect) . ')';
    }

    // IPv6 comes after every IPv4 entry, not sorted in among them.
    $lastV4 = -1;
    $firstV6 = PHP_INT_MAX;
    foreach ($ips as $i => $ip) {
        if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $lastV4 = $i;
        } elseif (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $firstV6 = min($firstV6, $i);
        }
    }
    if ($firstV6 < $lastV4) {
        $fails[] = 'an IPv6 address is listed before an IPv4 one';
    }

    // No IPv6 on the box: the list is exactly the IPv4 list.
    $stubIp6 = [];
    $ips = (array)$get->invoke(null, true);
    $v6 = array_filter($ips, function ($ip) {
        return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    });
    if (count($v6) > 0) {
        $fails[] = 'IPv6 addresses listed when ip -6 reports none';
    }

    if ($fails) {
        foreach ($fails as $f) {
            fwrite(STDERR, "FAIL: $f\n");
        }
        exit(1);
    }
    echo "ok: getIPAddress() adds global IPv6, filters, keeps IPv4 order\n";
    exit(0);
}
