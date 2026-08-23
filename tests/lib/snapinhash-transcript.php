<?php
/**
 * Runs SnapinHash against stubs and prints everything it would do.
 *
 * SnapinHash is a daemon with no coverage, and the thing worth pinning is
 * not what it logs but what it WRITES BACK: hash_file() on a file that is
 * not there returns false, and false being saved as a snapin's hash is
 * invisible in every log and fatal to every client that compares against it.
 * Every set()/save() is therefore recorded.
 *
 * Takes a directory so the same harness can be pointed at an older copy of
 * the daemon out of git and the two transcripts diffed directly.
 *
 * Usage: php tests/lib/snapinhash-transcript.php [<dir>]
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

define('FOG_LOG_DIR', '/var/log/fog/');
define('DS', '/');

/**
 * Scenario state, read by the stubs below.
 */
class Scenario
{
    public static $enabled = 1;
    public static $snapinIDs = array();
    public static $files = array();
    public static $dir = '';
    public static $out = array();
}

/**
 * Records reads and writes against a row.
 */
class RowStub
{
    private $_kind = '';
    private $_data = array();
    /**
     * Builds the stub.
     *
     * @param string $kind The record type.
     * @param array  $data The fields.
     */
    public function __construct($kind, $data = array())
    {
        $this->_kind = $kind;
        $this->_data = $data;
    }
    /**
     * Reads a field.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : '';
    }
    /**
     * Records a field write.
     *
     * @param string $key   The field.
     * @param mixed  $value The value.
     *
     * @return RowStub
     */
    public function set($key, $value)
    {
        // Hashes are recorded by length so the transcript does not depend
        // on the contents of a fixture file.
        if ('hash' === $key && is_string($value) && strlen($value) > 16) {
            $value = sprintf('<%d hex chars>', strlen($value));
        }
        Scenario::$out[] = sprintf(
            '[set %s#%d %s=%s]',
            $this->_kind,
            (int)$this->get('id'),
            $key,
            var_export($value, true)
        );
        return $this;
    }
    /**
     * Records the save.
     *
     * @return RowStub
     */
    public function save()
    {
        Scenario::$out[] = sprintf(
            '[save %s#%d]',
            $this->_kind,
            (int)$this->get('id')
        );
        return $this;
    }
    /**
     * Returns this node's storage group.
     *
     * @return RowStub
     */
    public function getStorageGroup()
    {
        return new RowStub('StorageGroup', array('id' => 3, 'name' => 'GroupOne'));
    }
}

/**
 * Stands in for SnapinManager.
 */
class ManagerStub
{
    /**
     * Counts matching rows.
     *
     * @param array $find The filter.
     *
     * @return int
     */
    public function count($find = array())
    {
        Scenario::$out[] = '[count SnapinManager]';
        return count(Scenario::$snapinIDs);
    }
    /**
     * Returns matching rows.
     *
     * @param array $find The filter.
     *
     * @return array
     */
    public function find($find = array())
    {
        Scenario::$out[] = '[find SnapinManager]';
        $rows = array();
        foreach (Scenario::$snapinIDs as $id) {
            $rows[] = new RowStub(
                'Snapin',
                array(
                    'id' => $id,
                    'name' => 'snapin' . $id,
                    'file' => isset(Scenario::$files[$id])
                        ? Scenario::$files[$id]
                        : 'missing' . $id
                )
            );
        }
        return $rows;
    }
}

/**
 * Stands in for the real service base.
 */
abstract class FOGService
{
    public static $sleeptime = '';
    public static $logpath = '/var/log/fogcustom/';
    public static $log = '';
    public static $dev = '';
    public static $zzz = 0;
    /**
     * Builds the stub.
     */
    public function __construct()
    {
    }
    /**
     * Returns fixture settings.
     *
     * @param mixed $keys One key or a list of them.
     *
     * @return mixed
     */
    public static function getSetting($keys)
    {
        if (is_array($keys)) {
            return array('', '', '');
        }
        if (false !== strpos((string)$keys, 'GLOBALENABLED')) {
            return Scenario::$enabled;
        }
        return '';
    }
    /**
     * Returns a manager stub.
     *
     * @param string $class The class.
     *
     * @return ManagerStub
     */
    public static function getClass($class)
    {
        return new ManagerStub();
    }
    /**
     * Returns the associated ids.
     *
     * @param string $class The class.
     * @param array  $find  The filter.
     * @param string $field The field.
     *
     * @return array
     */
    public static function getSubObjectIDs($class, $find = array(), $field = '')
    {
        Scenario::$out[] = sprintf('[getSubObjectIDs %s field=%s]', $class, $field);
        return Scenario::$snapinIDs;
    }
    /**
     * Returns a deterministic size.
     *
     * @param string $path The file.
     *
     * @return int
     */
    public static function getFilesize($path)
    {
        return (int)@filesize($path);
    }
    /**
     * Records a line.
     *
     * @param string $string The line.
     *
     * @return void
     */
    public static function outall($string)
    {
        Scenario::$out[] = $string;
    }
    /**
     * Returns the nodes this host masters.
     *
     * @return array
     */
    protected function checkIfNodeMaster()
    {
        return array(
            new RowStub(
                'StorageNode',
                array(
                    'id' => 7,
                    'name' => 'NodeSeven',
                    'storagegroupID' => 3,
                    'snapinpath' => Scenario::$dir
                )
            )
        );
    }
    /**
     * Ends the pass.
     *
     * @return void
     */
    public function serviceRun()
    {
        Scenario::$out[] = '[serviceRun end]';
    }
}

$dir = isset($argv[1]) ? $argv[1] : (dirname(dirname(__DIR__)) . '/packages/web/lib/service');
require_once $dir . '/snapinhash.class.php';

// One file that is really there and one that is not. The missing one is the
// whole point.
$tmp = sys_get_temp_dir() . '/fog-snapinhash-' . getmypid();
@mkdir($tmp, 0700, true);
file_put_contents($tmp . '/present.bin', 'fog');
Scenario::$dir = $tmp;

$scenarios = array(
    'globally disabled' => array(
        'enabled' => 0
    ),
    'nothing primary to this group' => array(
        'snapinIDs' => array()
    ),
    'one file present, one missing' => array(
        'snapinIDs' => array(11, 22),
        'files' => array(11 => 'present.bin', 22 => 'gone.bin')
    )
);

foreach ($scenarios as $name => $state) {
    Scenario::$enabled = 1;
    Scenario::$snapinIDs = array();
    Scenario::$files = array();
    Scenario::$out = array();
    foreach ($state as $key => $value) {
        Scenario::${$key} = $value;
    }
    $svc = new SnapinHash();
    printf("=== SnapinHash :: %s ===\n", $name);
    printf("log=%s dev=%s zzz=%s\n", $svc::$log, $svc::$dev, $svc::$zzz);
    $svc->serviceRun();
    foreach (Scenario::$out as $line) {
        echo '  ' . str_replace($tmp, '<dir>', $line) . "\n";
    }
    echo "\n";
}

@unlink($tmp . '/present.bin');
@rmdir($tmp);
