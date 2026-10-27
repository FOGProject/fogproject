<?php
/**
 * One reported Performance Test run on a host (fos#55).
 *
 * PHP version 7.4+
 *
 * @category PerfTest
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One reported Performance Test run on a host (fos#55).
 *
 * FOS boots with mode=perftest, times the disk, the network and
 * decompression, and POSTs the result to service/perftest.php. That
 * endpoint has no credential, so this class holds the rules that decide
 * what it accepts. They are static and take plain values, so a test can
 * drive the real code without a database.
 *
 * @category PerfTest
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostPerfTest extends FOGController
{
    /**
     * The largest rate FOS may report, in MB/s.
     */
    const MAX_RATE = 1000000;
    /**
     * The largest compression ratio FOS may report (ratio times 100).
     */
    const MAX_RATIO = 100000;
    /**
     * The largest decoded sample path, in bytes.
     */
    const MAX_SAMPLE = 255;
    /**
     * The compression formats FOS may report.
     */
    const FORMATS = ['zstd', 'gzip', 'unknown', ''];
    /**
     * The limiting resources FOS may report.
     */
    const BOTTLENECKS = ['disk', 'network', 'cpu', ''];
    /**
     * The hostPerfTests table.
     *
     * @var string
     */
    protected $databaseTable = 'hostPerfTests';
    /**
     * The hostPerfTests fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hptID',
        'hostID' => 'hptHostID',
        'taskID' => 'hptTaskID',
        'dateTime' => 'hptDateTime',
        'disk' => 'hptDisk',
        'diskMBps' => 'hptDiskMBps',
        'netMBps' => 'hptNetMBps',
        'cpuMBps' => 'hptCpuMBps',
        'ratio' => 'hptRatio',
        'format' => 'hptFormat',
        'sample' => 'hptSample',
        'bottleneck' => 'hptBottleneck',
        'kernel' => 'hptKernel',
        'init' => 'hptInit'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'taskID'
    ];
    /**
     * Whether a posted value is a plain run of digits within a cap.
     *
     * Digits only: no sign, no decimal point, no space. A value that FOS
     * could not have produced is refused rather than cleaned up.
     *
     * @param mixed $value the posted value
     * @param int   $max   the largest value accepted
     *
     * @return bool
     */
    public static function isCappedInt($value, $max)
    {
        if (!is_string($value) || !preg_match('/^[0-9]{1,9}$/', $value)) {
            return false;
        }
        return (int)$value <= $max;
    }
    /**
     * Check a posted report and build the row to store.
     *
     * Returns the name of the first field that is not acceptable, or an
     * empty string with the row in $row. Nothing is stored here.
     *
     * @param array $in  the posted fields, as strings
     * @param array $row the cleaned values, keyed by common name
     *
     * @return string the name of the bad field, or '' when all are good
     */
    public static function validate(array $in, array &$row = [])
    {
        $row = [];
        $get = function ($key) use ($in) {
            return isset($in[$key]) ? $in[$key] : '';
        };
        foreach (['diskmbps', 'netmbps', 'cpumbps'] as $key) {
            if (!self::isCappedInt($get($key), self::MAX_RATE)) {
                return $key;
            }
        }
        if (!self::isCappedInt($get('ratio'), self::MAX_RATIO)) {
            return 'ratio';
        }
        if (!in_array($get('format'), self::FORMATS, true)) {
            return 'format';
        }
        if (!in_array($get('bottleneck'), self::BOTTLENECKS, true)) {
            return 'bottleneck';
        }
        $disk = $get('disk');
        if ('' !== $disk
            && !preg_match('#^/dev/[A-Za-z0-9/_.-]{1,58}$#D', $disk)
        ) {
            return 'disk';
        }
        $kernel = $get('kernel');
        if (!preg_match('/^[A-Za-z0-9._+-]{0,64}$/D', $kernel)) {
            return 'kernel';
        }
        $init = $get('initversion');
        if (!preg_match('/^[A-Za-z0-9._+-]{0,32}$/D', $init)) {
            return 'initversion';
        }
        // The sample path arrives base64 encoded, so a file name with odd
        // bytes cannot break the form. Strict decoding refuses stray
        // characters, and the re-encode check refuses missing padding, which
        // PHP's strict mode allows. The result must be printable UTF-8 with
        // no control character, and short enough for the column.
        $sample = '';
        if ('' !== $get('sample')) {
            $sample = base64_decode($get('sample'), true);
            if (false === $sample
                || base64_encode($sample) !== $get('sample')
                || strlen($sample) > self::MAX_SAMPLE
                || !preg_match('/^[^\x00-\x1F\x7F]*$/Du', $sample)
            ) {
                return 'sample';
            }
        }
        $row = [
            'disk' => $disk,
            'diskMBps' => (int)$get('diskmbps'),
            'netMBps' => (int)$get('netmbps'),
            'cpuMBps' => (int)$get('cpumbps'),
            'ratio' => (int)$get('ratio'),
            'format' => $get('format'),
            'sample' => $sample,
            'bottleneck' => $get('bottleneck'),
            'kernel' => $kernel,
            'init' => $init
        ];
        return '';
    }
    /**
     * Decide whether a report is accepted, and build the row to store.
     *
     * The endpoint has no credential. The host must have a Performance
     * Test task in flight, which is true when FOS calls it because the task
     * is what booted FOS. Without that rule any caller that knows a MAC
     * could add rows to a host that was never tested.
     *
     * @param object|null $Host the host found for the posted MAC
     * @param array  $in   the posted fields, as strings
     * @param array  $row  the row to store, keyed by common name
     *
     * @return string the reason for a refusal, or '' when accepted
     */
    public static function refusal($Host, array $in, array &$row = [])
    {
        $row = [];
        if (!$Host || !$Host->isValid()) {
            return 'Unknown host';
        }
        $Task = $Host->get('task');
        if (!$Task
            || !$Task->isValid()
            || TaskType::PERF_TEST != $Task->get('typeID')
        ) {
            return 'No active Performance Test task for this host';
        }
        $bad = self::validate($in, $row);
        if ('' !== $bad) {
            $row = [];
            return 'Invalid field: ' . $bad;
        }
        $row['hostID'] = (int)$Host->get('id');
        $row['taskID'] = (int)$Task->get('id');
        return '';
    }
    /**
     * Format a stored ratio (times 100) for display.
     *
     * 250 is 2.50:1. A ratio of 0 was not measured.
     *
     * @param mixed $ratio the stored ratio
     *
     * @return string
     */
    public static function formatRatio($ratio)
    {
        $ratio = (int)$ratio;
        if ($ratio < 1) {
            return '-';
        }
        return sprintf('%d.%02d:1', intdiv($ratio, 100), $ratio % 100);
    }
    /**
     * Format a stored rate for display.
     *
     * A rate of 0 means FOS did not measure it.
     *
     * @param mixed $rate the stored rate, in MB/s
     *
     * @return string
     */
    public static function formatRate($rate)
    {
        $rate = (int)$rate;
        return $rate < 1 ? _('not measured') : (string)$rate;
    }
}
