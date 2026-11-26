<?php
/**
 * An IPv6 multicast session derives its data address in IPv6, and an IPv4
 * session keeps the exact command line it always had.
 *
 * getCMD() builds the udp-sender command. For a node whose interface is an
 * IPv6 literal (the session is IPv6) it must:
 *
 *   * add the session's pool offset to the LOW 32 BITS of the multicast
 *     address, the way IPv4 adds it to the whole address;
 *   * drop an IPv4 multicast address or rendezvous address, and say so,
 *     because udp-sender cannot send an IPv6 session to an IPv4 group.
 *
 * And, the hard constraint on this change, the IPv4 command line must not
 * move by one byte. GOLDEN_IPV4 below was captured by running this probe
 * against MulticastTask.php as it stood on origin/dev-branch, before the
 * IPv6 change.
 *
 * Drives the REAL getCMD(); only the accessors that reach the database and
 * the settings reader are replaced (same pattern as
 * multicast-streams-are-self-identifying.test.php).
 *
 * Usage: php tests/multicast-ipv6-session.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

use FOG\Service\MulticastTask;

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('UDPSENDERPATH') || define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');

/**
 * Stand-in for the real base class: settings come from $GLOBALS['mcSettings'].
 */
abstract class FOGBase
{
    /**
     * Same as the real FOGBase::isIPv6().
     *
     * @param mixed $ip The value.
     *
     * @return bool
     */
    protected static function isIPv6($ip)
    {
        return false !== filter_var(
            trim((string)$ip),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        );
    }

    /**
     * wlog() reads the log size before it writes.
     *
     * @param string $path File.
     *
     * @return int
     */
    public static function getFilesize($path)
    {
        return filesize($path);
    }

    /**
     * The log line timestamp reads this.
     *
     * @return \DateTime
     */
    public static function niceDate()
    {
        return new \DateTime();
    }

    /**
     * Settings reader.
     *
     * @param mixed $keys One key, or a list of them.
     *
     * @return mixed
     */
    public static function getSetting($keys)
    {
        if (is_array($keys)) {
            $out = [];
            foreach ($keys as $key) {
                $out[] = isset($GLOBALS['mcSettings'][$key])
                    ? $GLOBALS['mcSettings'][$key] : '';
            }
            return $out;
        }
        return isset($GLOBALS['mcSettings'][$keys])
            ? $GLOBALS['mcSettings'][$keys] : '';
    }
}

/**
 * Stand-in for the session: only the port pool is read.
 */
class MulticastSession
{
    /**
     * @return array
     */
    public static function portPool()
    {
        return [63100, 63102, 63104];
    }
}

require_once __DIR__ . '/lib/stub-buckets.php';
require_once dirname(__DIR__) . '/packages/web/src/Service/FOGService.php';
require_once dirname(__DIR__) . '/packages/web/src/Service/MulticastTask.php';

/**
 * Drives getCMD() without a session row behind it.
 */
class SessionProbe extends MulticastTask
{
    /** @var string */
    public $path = '';
    /** @var string */
    public $iface = 'eno2';
    /** @var string The daemon log: outall() writes here (static::$log). */
    public static $log = '';
    public function getImagePath()
    {
        return $this->path;
    }
    public function getImageType()
    {
        return 1;
    }
    public function getImageFormat()
    {
        return 5;
    }
    public function getOSID()
    {
        return 9;
    }
    public function getPartitions()
    {
        return 0;
    }
    public function getPortBase()
    {
        return 63102;
    }
    public function getClientCount()
    {
        return 3;
    }
    public function getInterface()
    {
        return $this->iface;
    }
    public function getMaxwait()
    {
        return 600;
    }
    public function getBitrate()
    {
        return '100m';
    }
    public function getHelloInterval()
    {
        return '50';
    }
    public function getUDPCastLogFile()
    {
        return '/dev/null';
    }
}

/** @var string[] $failures Labels of the checks that did not hold. */
$failures = [];

/**
 * Records one assertion.
 *
 * @param string $label What is being asserted.
 * @param bool   $cond  Whether it held.
 *
 * @return void
 */
function check($label, $cond)
{
    global $failures;
    if (!$cond) {
        $failures[] = $label;
    }
}

$dir = sys_get_temp_dir() . '/fogmcv6-' . getmypid();
SessionProbe::$log = $dir . '.log';
mkdir($dir, 0777, true);
file_put_contents($dir . '/d1p1.img', 'x');
register_shutdown_function(function () use ($dir) {
    @unlink($dir . '/d1p1.img');
    @unlink($dir . '.log');
    @rmdir($dir);
});

/**
 * Runs the real getCMD() with these settings.
 *
 * @param string $iface     Sender interface (the node address).
 * @param string $address   FOG_MULTICAST_ADDRESS.
 * @param string $rendezvous FOG_MULTICAST_RENDEZVOUS.
 *
 * @return string The command.
 */
function cmdFor($iface, $address, $rendezvous)
{
    global $dir;
    $GLOBALS['mcSettings'] = [
        'FOG_MULTICAST_ADDRESS' => $address,
        'FOG_MULTICAST_DUPLEX' => '--full-duplex',
        'FOG_MULTICAST_RENDEZVOUS' => $rendezvous,
        'FOG_MULTICAST_MAX_SESSIONS' => 64,
    ];
    $probe = (new \ReflectionClass('FOG\SessionProbe'))
        ->newInstanceWithoutConstructor();
    $probe->path = $dir;
    $probe->iface = $iface;
    return $probe->getCMD();
}

/**
 * The udp-sender part of a command: from the binary to the first pipe.
 *
 * @param string $cmd The whole command.
 *
 * @return string
 */
function senderArgs($cmd)
{
    $at = strpos($cmd, UDPSENDERPATH);
    return false === $at ? '' : substr($cmd, $at, strcspn($cmd, '|;&', $at));
}

// GOLDEN_IPV4: the IPv4 udp-sender arguments from origin/dev-branch's code.
const GOLDEN_IPV4 = "/usr/local/sbin/udp-sender --max-bitrate '100m' --rexmit-hello-interval '50' --interface 'eno2' --min-receivers 3 --max-wait 600 --mcast-data-address 239.0.0.3 --mcast-rdv-address 239.0.0.9 --portbase 63102 --full-duplex --ttl 32 --nokbd --nopointopoint";

// --- 1. IPv4: byte-identical --------------------------------------------
$v4 = senderArgs(cmdFor('eno2', '239.0.0.1', '239.0.0.9'));
check(
    "IPv4 sender arguments changed:\n  got      $v4\n  expected " . GOLDEN_IPV4,
    GOLDEN_IPV4 === $v4
);
check('a plain IPv4 session logged something', !is_file(SessionProbe::$log));

// --- 2. IPv6: offset on the low 32 bits ---------------------------------
// Port 63102 is index 1 in the pool, so the offset is 2.
$v6 = senderArgs(cmdFor('fd00:f06:0:1::1', 'ff15::100', ''));
check(
    "IPv6 data address is not ff15::102: $v6",
    false !== strpos($v6, ' --mcast-data-address ff15::102 ')
);
check(
    "IPv6 sender lost --interface: $v6",
    false !== strpos($v6, " --interface 'fd00:f06:0:1::1'")
);
foreach ([' --ttl 32', ' --nokbd', ' --nopointopoint'] as $flag) {
    check("IPv6 sender lost $flag", false !== strpos($v6, $flag));
}

// The low 32 bits wrap on their own and never carry into the prefix.
$wrap = senderArgs(cmdFor('fd00:f06:0:1::1', 'ff15::ffff:ffff', ''));
check(
    "low 32 bits did not wrap: $wrap",
    false !== strpos($wrap, ' --mcast-data-address ff15::1 ')
);

// A value inet_pton rejects reaches a root shell if it is kept as typed.
foreach (['x;id', 'ff15::zz;id', '$(id)', 'ff15::1;id'] as $evil) {
    $bad = cmdFor('fd00:f06:0:1::1', $evil, '');
    check(
        "an invalid IPv6-session address reached the command line: $evil",
        false === strpos($bad, $evil)
    );
    check(
        "an invalid IPv6-session address left --mcast-data-address: $evil",
        false === strpos(senderArgs($bad), '--mcast-data-address')
    );
}

// --- 3. cross-family settings are dropped, and logged -------------------
$mixed = senderArgs(cmdFor('fd00:f06:0:1::1', '239.0.0.1', '239.0.0.9'));
check(
    "IPv4 multicast address kept on an IPv6 session: $mixed",
    false === strpos($mixed, '--mcast-data-address')
);
check(
    "IPv4 rendezvous kept on an IPv6 session: $mixed",
    false === strpos($mixed, '--mcast-rdv-address')
);
check(
    'the drop was not logged',
    is_file(SessionProbe::$log)
    && false !== strpos((string)file_get_contents(SessionProbe::$log), 'Ignoring IPv4 multicast address')
);

$mixed4 = senderArgs(cmdFor('eno2', 'ff15::100', 'ff15::9'));
check(
    "IPv6 settings kept on an IPv4 session: $mixed4",
    false === strpos($mixed4, '--mcast-data-address')
    && false === strpos($mixed4, '--mcast-rdv-address')
);

// Same-family IPv6 rendezvous is kept.
$rdv6 = senderArgs(cmdFor('fd00:f06:0:1::1', 'ff15::100', 'ff15::9'));
check(
    "IPv6 rendezvous was dropped on an IPv6 session: $rdv6",
    false !== strpos($rdv6, ' --mcast-rdv-address ff15::9')
);

if ($failures) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}
echo "ok: IPv6 session address, family rule and golden IPv4 line hold\n";
exit(0);
