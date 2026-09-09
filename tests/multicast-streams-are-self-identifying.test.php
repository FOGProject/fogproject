<?php
/**
 * Every multicast stream must announce which image file it carries.
 *
 * udpcast carries no metadata. The server chains one udp-sender per image
 * file on a shared portbase, FOS opens one udp-receiver per file it
 * expects, and the Nth receiver gets the Nth stream. Position is the only
 * thing binding them, so a client that reboots mid-session -- or whose
 * receiver opens after a sender has already stopped waiting for it --
 * lands one stream out of step, and every partition after that point is
 * restored from the wrong image. partclone objects only when the target
 * partition is smaller than the source; when it is larger the wrong
 * filesystem is written and the deploy reports success. Issue #1742.
 *
 * So getCMD() prefixes each stream with a fixed-width record naming the
 * file, and FOS refuses a stream that is not the one it asked for. These
 * checks pin the half that lives here: that the header is on EVERY stream
 * (a single --file invocation would slip through unlabeled), that it is
 * the width FOS reads, and that the name is the one FOS derives from its
 * own side of the same layout.
 *
 * The naming rule is not uniform, and that asymmetry is the point:
 *
 *   d1p2.img            flat            -> d1p2.img      (verbatim)
 *   d1p1.img.000/.001   split chunks    -> d1p1.img      (stem)
 *   d1p2.img.000        split, 1 chunk  -> d1p2.img      (stem)
 *   sys.img.000/.001    ONE partition   -> sys.img       (stem)
 *   rec.img.000/.001    TWO partitions  -> rec.img.000   (verbatim)
 *
 * FOS asks for a chunked partition with a glob (d1p1.img*, sys.img.*) and
 * for a whole one by name, so both sides land on the same string from
 * their own layout without either re-deriving the other's ordinal.
 *
 * These checks drive the REAL getCMD() over real fixture directories. The
 * accessors that would reach the database are overridden; the file
 * enumeration, the glob expansion and the emission are the shipped code.
 *
 * Usage: php tests/multicast-streams-are-self-identifying.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

use FOG\Service\MulticastTask;

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
// Normally defined by the generated config; getCMD() interpolates it.
defined('UDPSENDERPATH') || define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');

/**
 * Stand-in for the real base class.
 *
 * getCMD() reads three globalSettings keys and logs through outall(); it
 * touches nothing else on the parent. FOG_MULTICAST_ADDRESS is left empty
 * deliberately -- a value there sends getCMD() into MulticastSession's
 * port pool, which is a database read and is not what these checks are
 * about.
 */
abstract class FOGBase
{
    /**
     * Settings reader.
     *
     * @param mixed $keys One key, or a list of them.
     *
     * @return mixed
     */
    public static function getSetting($keys)
    {
        $answers = [
            'FOG_MULTICAST_ADDRESS' => '',
            'FOG_MULTICAST_DUPLEX' => '',
            'FOG_MULTICAST_RENDEZVOUS' => '',
            'FOG_MULTICAST_MAX_SESSIONS' => 64,
        ];
        if (is_array($keys)) {
            $out = [];
            foreach ($keys as $key) {
                $out[] = isset($answers[$key]) ? $answers[$key] : '';
            }
            return $out;
        }
        return isset($answers[$keys]) ? $answers[$keys] : '';
    }
}

require_once __DIR__ . '/lib/stub-buckets.php';
require_once dirname(__DIR__) . '/packages/web/src/Service/FOGService.php';
require_once dirname(__DIR__) . '/packages/web/src/Service/MulticastTask.php';

/**
 * Drives getCMD() without a session row behind it.
 */
class StreamProbe extends MulticastTask
{
    /** @var string */
    public $path = '';
    /** @var int */
    public $type = 1;
    /** @var int */
    public $format = 5;
    /** @var int */
    public $osid = 9;

    /**
     * Silences the service logger.
     *
     * @param string $string Message.
     *
     * @return void
     */
    public static function outall($string)
    {
    }

    // The accessors below are the whole database surface of getCMD().
    public function getImagePath()
    {
        return $this->path;
    }
    public function getImageType()
    {
        return $this->type;
    }
    public function getImageFormat()
    {
        return $this->format;
    }
    public function getOSID()
    {
        return $this->osid;
    }
    public function getPartitions()
    {
        return 0;
    }
    public function getPortBase()
    {
        return 63100;
    }
    public function getClientCount()
    {
        return 1;
    }
    public function getInterface()
    {
        return 'eno2';
    }
    public function getMaxwait()
    {
        return 600;
    }
    public function getBitrate()
    {
        return '';
    }
    public function getHelloInterval()
    {
        return '';
    }
    public function getUDPCastLogFile()
    {
        return '/dev/null';
    }
}

/** @var string[] $failures Labels of the checks that did not hold. */
$failures = [];
/** @var int $checks How many assertions ran. */
$checks = 0;

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
    global $failures, $checks;
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/**
 * Builds a fixture image directory.
 *
 * @param string $name  Directory name.
 * @param array  $files Filenames to create.
 *
 * @return string Absolute path.
 */
function fixture($name, array $files)
{
    $dir = sys_get_temp_dir() . '/fogmcid-' . getmypid() . '/' . $name;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    foreach ($files as $file) {
        file_put_contents($dir . '/' . $file, $file);
    }
    return $dir;
}

/**
 * Runs the real getCMD() and returns the stream ids it announced, in order.
 *
 * @param string $dir    Image directory.
 * @param int    $type   Image type.
 * @param int    $format Image format.
 * @param int    $osid   Image OS id.
 *
 * @return array [ids, raw command]
 */
function idsFor($dir, $type, $format, $osid = 9)
{
    $probe = (new \ReflectionClass('FOG\StreamProbe'))
        ->newInstanceWithoutConstructor();
    $probe->path = $dir;
    $probe->type = $type;
    $probe->format = $format;
    $probe->osid = $osid;
    $cmd = $probe->getCMD();
    preg_match_all(
        "#\\{ printf 'FOGMC1 %-120s\\\\n' '([^']*)';#",
        $cmd,
        $m
    );
    return [$m[1], $cmd];
}

// --- 1. the header is the width FOS reads -------------------------------

// Read through constant() so the assertions below are a genuine read of
// the shipped values rather than something the analyzer folds away.
/** @var int $headerBytes */
$headerBytes = constant('FOG\\Service\\MulticastTask::STREAM_HEADER_BYTES');
/** @var string $headerFormat */
$headerFormat = constant('FOG\\Service\\MulticastTask::STREAM_HEADER_FORMAT');
check(
    'STREAM_HEADER_BYTES is 128',
    128 === $headerBytes
);
check(
    'the format expands to exactly STREAM_HEADER_BYTES',
    $headerBytes === strlen(
        sprintf(
            str_replace('\\n', "\n", $headerFormat),
            'd1p1.img'
        )
    )
);
check(
    'a long LV image name still fits the header',
    $headerBytes === strlen(
        sprintf(
            str_replace('\\n', "\n", $headerFormat),
            'd1p3.a-rather-long-volume-group-name.img'
        )
    )
);

// --- 2. naming, per layout ---------------------------------------------

$cases = [
    'flat, one file per partition' => [
        ['d1p1.img', 'd1p2.img', 'd1p3.img'],
        1,
        5,
        ['d1p1.img', 'd1p2.img', 'd1p3.img'],
    ],
    'split, several chunks per partition' => [
        ['d1p1.img.000', 'd1p1.img.001', 'd1p2.img.000', 'd1p2.img.001'],
        1,
        6,
        ['d1p1.img', 'd1p2.img'],
    ],
    'split, a partition small enough for one chunk' => [
        ['d1p1.img.000', 'd1p2.img.000', 'd1p2.img.001'],
        1,
        6,
        ['d1p1.img', 'd1p2.img'],
    ],
    'legacy sys/rec: sys is one partition, rec is per-partition' => [
        ['rec.img.000', 'sys.img.000', 'sys.img.001'],
        1,
        5,
        ['rec.img.000', 'sys.img'],
        5,
    ],
    'legacy sys/rec: two rec partitions keep their own names' => [
        ['rec.img.000', 'rec.img.001', 'sys.img.000'],
        1,
        5,
        ['rec.img.000', 'rec.img.001', 'sys.img'],
        5,
    ],
];

foreach ($cases as $label => $case) {
    list($files, $type, $format, $want) = $case;
    $osid = isset($case[4]) ? $case[4] : 9;
    list($got, $cmd) = idsFor(
        fixture(md5($label), $files),
        $type,
        $format,
        $osid
    );
    check(
        sprintf('%s: stream ids are %s', $label, implode(', ', $want)),
        $want === $got
    );
    check(
        sprintf('%s: one header per udp-sender', $label),
        count($got) === substr_count($cmd, 'udp-sender')
    );
    check(
        sprintf('%s: no stream is sent unlabeled via --file', $label),
        false === strpos($cmd, '--file ')
    );
}

// --- 3. the payload is unchanged ---------------------------------------

// The header is prepended, never substituted: the image file itself must
// still be cat'd in whole, or this would fix the naming by breaking the
// transfer.
list($ids, $cmd) = idsFor(
    fixture('payload', ['d1p1.img', 'd1p2.img']),
    1,
    5
);
check(
    'each stream still cats its image file after the header',
    1 === preg_match_all("#; cat '[^']*d1p1\\.img'; \\}#", $cmd)
    && 1 === preg_match_all("#; cat '[^']*d1p2\\.img'; \\}#", $cmd)
);
check(
    'the header is inside the pipeline feeding udp-sender',
    (bool)preg_match(
        "#\\{ printf 'FOGMC1[^}]*\\} \\| /usr/local/sbin/udp-sender#",
        $cmd
    ) || (bool)preg_match("#\\{ printf 'FOGMC1[^}]*\\} \\| \\S*udp-sender#", $cmd)
);

// --- 4. the capability is advertised -----------------------------------

// FOS must not strip 128 bytes off a server that does not prepend them, so
// it probes for this token before trusting the header. Without the token
// on this side the check is dead and the desync is back.
$caps = file_get_contents(
    dirname(__DIR__) . '/packages/web/service/getversion.php'
);
check(
    'getversion.php advertises the mcstreamid capability',
    (bool)preg_match("#\\\$ver = '[^']*\\bmcstreamid\\b[^']*';#", $caps)
);

// --- report ------------------------------------------------------------

$dir = sys_get_temp_dir() . '/fogmcid-' . getmypid();
exec('rm -rf ' . escapeshellarg($dir));

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - $failure\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
