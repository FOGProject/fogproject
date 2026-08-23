<?php
/**
 * The first partition's wait is the configured one; later ones wait longer.
 *
 * GH-536. Every machine reaches the FIRST partition at the same moment,
 * straight off the wire. It reaches the ones after it only once it has
 * finished writing the previous one, and identical hardware does not finish
 * together -- the reporter's i3-4170 labs drift far enough that one machine
 * is still in partclone's "Syncing..." while the rest have moved on.
 *
 * A single wait covering both cases cannot be right. It has to be long
 * enough for the slowest machine on the LAST partition, which means a
 * machine that never shows up at all for the FIRST one holds the whole
 * session open for that long too. FOG_UDPCAST_MAXWAIT was that single wait:
 * getCMD() substituted $maxwait * 60 into every stream, and a comment in
 * the file recorded the stream index being deleted because both arms of the
 * ternary it fed had become identical.
 *
 * This drives the REAL getCMD(). Every input it reads is an overridable
 * accessor, so the subclass below supplies them and the method itself does
 * the work -- no database, no storage node, no udp-sender. The three values
 * matter individually:
 *
 *   10 (the default)  600 / 600   -- nothing changes for anyone who never
 *                                    touched the setting, which is nearly
 *                                    everyone, and that is the point
 *    2 (lowered)      120 / 600   -- the fix the issue asks for
 *   20 (raised)      1200 / 1200  -- an admin who raised this raised it as
 *                                    policy for every partition, because on
 *                                    this branch it is a GLOBAL. Shortening
 *                                    the later ones to a flat 600, which is
 *                                    what working-1.6 does with its
 *                                    per-session value, would quietly undo
 *                                    that. Hence max(600, $maxwait * 60).
 *
 * Usage: php tests/multicast-partition-maxwait.test.php
 * Exit status 0 = pass, 1 = fail.
 */

if (!function_exists('_')) {
    /**
     * Stands in for ext-gettext when it is absent.
     *
     * @param string $msgid The message.
     *
     * @return string
     */
    function _($msgid)
    {
        return $msgid;
    }
}

define('DS', '/');
define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');

$GLOBALS['FOG_TEST_MAXWAIT'] = 10;

/**
 * Stands in for the session model.
 */
class MulticastSession
{
    /**
     * No port pool configured, which is the ordinary case.
     *
     * @return array
     */
    public static function portPool()
    {
        return array();
    }
}

/**
 * Stands in for the real service base.
 */
abstract class FOGService
{
    public static $log = '';
    public static $dev = '';
    public static $zzz = 0;
    public static $logpath = '';
    public static $sleeptime = '';
    /**
     * Builds the stub.
     */
    public function __construct()
    {
    }
    /**
     * Returns fixture settings.
     *
     * @param string $key The setting.
     *
     * @return mixed
     */
    public static function getSetting($key)
    {
        $map = array(
            'FOG_MULTICAST_ADDRESS' => '',
            'FOG_MULTICAST_DUPLEX' => '',
            'FOG_MULTICAST_RENDEZVOUS' => '',
            'FOG_MULTICAST_MAX_SESSIONS' => 5,
            'FOG_UDPCAST_MAXWAIT' => $GLOBALS['FOG_TEST_MAXWAIT']
        );
        return isset($map[$key]) ? $map[$key] : '';
    }
    /**
     * Swallows log output.
     *
     * @param string $string The line.
     *
     * @return void
     */
    public static function outall($string)
    {
    }
    /**
     * Never reached from getCMD().
     *
     * @param string $class The class.
     * @param mixed  $data  The argument.
     *
     * @return null
     */
    public static function getClass($class, $data = null)
    {
        return null;
    }
}

$dir = isset($argv[1])
    ? $argv[1]
    : (dirname(__DIR__) . '/packages/web/lib/service');
require_once $dir . '/multicasttask.class.php';

/**
 * A task whose every input is fixed, so getCMD() is the only thing running.
 */
class MaxWaitProbe extends MulticastTask
{
    private $_dir = '';
    /**
     * Builds the probe.
     *
     * @param string $imagedir The directory holding the partition files.
     */
    public function __construct($imagedir)
    {
        $this->_dir = $imagedir;
    }
    /**
     * The image directory.
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->_dir;
    }
    /**
     * A single-disk, multi-partition image.
     *
     * @return int
     */
    public function getImageType()
    {
        return 1;
    }
    /**
     * A Linux/other OS id, so the directory is scanned.
     *
     * @return int
     */
    public function getOSID()
    {
        return 5;
    }
    /**
     * Not a split format.
     *
     * @return int
     */
    public function getImageFormat()
    {
        return 1;
    }
    /**
     * All partitions.
     *
     * @return int
     */
    public function getPartitions()
    {
        return 0;
    }
    /**
     * A port outside any pool.
     *
     * @return int
     */
    public function getPortBase()
    {
        return 51000;
    }
    /**
     * The sending interface.
     *
     * @return string
     */
    public function getInterface()
    {
        return 'eth0';
    }
    /**
     * No bitrate cap.
     *
     * @return string
     */
    public function getBitrate()
    {
        return '';
    }
    /**
     * Two clients.
     *
     * @return int
     */
    public function getClientCount()
    {
        return 2;
    }
    /**
     * No hello interval.
     *
     * @return string
     */
    public function getHelloInterval()
    {
        return '';
    }
}

$failures = array();
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   The observed value.
 * @param mixed  $want  The required value.
 *
 * @return void
 */
function is($label, $got, $want)
{
    global $failures, $checks;
    $checks++;
    if ($got !== $want) {
        $failures[] = sprintf(
            '%s (got %s, wanted %s)',
            $label,
            var_export($got, true),
            var_export($want, true)
        );
    }
}

$tmp = sys_get_temp_dir() . '/fog-maxwait-' . getmypid();
@mkdir($tmp, 0700, true);
// Two partitions, so there are two streams and therefore a first and a rest.
file_put_contents($tmp . '/d1p1.img', 'x');
file_put_contents($tmp . '/d1p2.img', 'x');

/**
 * Returns the --max-wait value of each stream in the built command.
 *
 * @param int    $setting FOG_UDPCAST_MAXWAIT, in minutes.
 * @param string $tmp     The image directory.
 *
 * @return array
 */
function waitsFor($setting, $tmp)
{
    $GLOBALS['FOG_TEST_MAXWAIT'] = $setting;
    $probe = new MaxWaitProbe($tmp);
    preg_match_all('/--max-wait (\d+)/', $probe->getCMD(), $m);
    return array_map('intval', $m[1]);
}

is(
    'the default setting is unchanged for every partition',
    waitsFor(10, $tmp),
    array(600, 600)
);
is(
    'a lowered setting shortens only the first partition',
    waitsFor(2, $tmp),
    array(120, 600)
);
is(
    'a raised setting is not undone on the later partitions',
    waitsFor(20, $tmp),
    array(1200, 1200)
);
is(
    'an unset setting falls back to ten minutes',
    waitsFor(0, $tmp),
    array(600, 600)
);

array_map('unlink', glob($tmp . '/*'));
@rmdir($tmp);

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    exit(1);
}

printf("ok  %d checks passed\n", $checks);
exit(0);
