<?php
/**
 * Runs an item scanner against stubs and prints everything it would log.
 *
 * Companion to replicator-transcript.php, same reasoning: ImageSize and
 * SnapinHash are daemons with no coverage, and the risk in giving them a
 * shared base is that the ORDER of the calls, one of the messages, or --
 * most importantly -- what gets WRITTEN BACK to the record quietly changes.
 * Every set()/save() is recorded here for exactly that reason.
 *
 * Usage: php tests/lib/scanner-transcript.php <class> [<dir>]
 *   class  ImageSize | SnapinHash
 *   dir    directory holding the daemon class files, for running an older
 *          copy of the daemon against the same stubs
 */

namespace FOG;

if (!function_exists('FOG\_')) {
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
    public static $groupExists = true;
    public static $itemIDs = [];
    public static $files = [];
    public static $dir = '';
    public static $out = [];
}

/**
 * Stands in for the API router.
 */
class Route
{
    /**
     * Returns a storage group, or null when it is gone.
     *
     * @param string $class The route class.
     * @param int    $id    The id.
     *
     * @return object|null
     */
    public static function getItem($class, $id)
    {
        if (!Scenario::$groupExists) {
            return null;
        }
        return (object)['id' => $id, 'name' => 'GroupOne'];
    }
    /**
     * Returns matching ids.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     * @param string $field The field to pluck.
     *
     * @return array
     */
    public static function getIds($class, $find = [], $field = '')
    {
        Scenario::$out[] = sprintf(
            '[getIds %s field=%s find=%s]',
            $class,
            $field === '' ? '-' : $field,
            json_encode($find)
        );
        return Scenario::$itemIDs;
    }
    /**
     * Returns matching rows.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return array
     */
    public static function getList($class, $find = [])
    {
        Scenario::$out[] = sprintf('[getList %s]', $class);
        $rows = [];
        foreach (Scenario::$itemIDs as $id) {
            $rows[] = (object)[
                'id' => $id,
                'name' => 'item' . $id,
                // Both models are covered: images read ->path, snapins
                // read ->file, and the base picks by descriptor.
                'path' => Scenario::$files[$id] ?? ('missing' . $id),
                'file' => Scenario::$files[$id] ?? ('missing' . $id)
            ];
        }
        return $rows;
    }
}

/**
 * Records what a daemon writes back to a record.
 */
class ModelStub
{
    private $_class = '';
    private $_id = 0;
    /**
     * Builds the stub.
     *
     * @param string $class The model.
     * @param int    $id    The row.
     */
    public function __construct($class, $id)
    {
        $this->_class = $class;
        $this->_id = (int)$id;
    }
    /**
     * Records a field write.
     *
     * @param string $key   The field.
     * @param mixed  $value The value.
     *
     * @return ModelStub
     */
    public function set($key, $value)
    {
        // Hashes are recorded by length, so the transcript does not depend
        // on the contents of a fixture file.
        if ('hash' === $key && is_string($value) && strlen($value) > 16) {
            $value = sprintf('<%d hex chars>', strlen($value));
        }
        Scenario::$out[] = sprintf(
            '[set %s#%d %s=%s]',
            $this->_class,
            $this->_id,
            $key,
            var_export($value, true)
        );
        return $this;
    }
    /**
     * Records the save.
     *
     * @return ModelStub
     */
    public function save()
    {
        Scenario::$out[] = sprintf(
            '[save %s#%d]',
            $this->_class,
            $this->_id
        );
        return $this;
    }
}

/**
 * Stands in for the Image model.
 */
class Image extends ModelStub
{
}

/**
 * Stands in for the Snapin model.
 */
class Snapin extends ModelStub
{
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
            return ['', '', ''];
        }
        if (false !== strpos((string)$keys, 'GLOBALENABLED')) {
            return Scenario::$enabled;
        }
        return '';
    }
    /**
     * Instantiates a model stub.
     *
     * @param string $class The class.
     * @param mixed  $data  The row id.
     *
     * @return ModelStub
     */
    public static function getClass($class, $data = null)
    {
        // strrpos() answers false for an unqualified name, and false + 1
        // is 1 -- which silently chopped the first letter off every class
        // in this transcript. The old daemons pass 'Image', the new ones
        // pass 'FOG\Image', so both shapes have to work.
        $at = strrpos($class, '\\');
        $short = false === $at ? $class : substr($class, $at + 1);
        return new ModelStub($short, (int)$data);
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
     * Records a line written to a side log.
     *
     * @param string $string The line.
     * @param string $file   The log.
     *
     * @return void
     */
    public static function wlog($string, $file)
    {
        Scenario::$out[] = sprintf('[wlog %s] %s', basename($file), $string);
    }
    /**
     * Returns the nodes this host masters.
     *
     * @return array
     */
    protected function checkIfNodeMaster()
    {
        return [
            (object)[
                'id' => 7,
                'name' => 'NodeSeven',
                'storagegroupID' => 3,
                'path' => Scenario::$dir,
                'snapinpath' => Scenario::$dir
            ]
        ];
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

$class = $argv[1] ?? 'ImageSize';
$dir = $argv[2] ?? (dirname(__DIR__, 2) . '/packages/web/src/Service');

if (is_readable($dir . '/FOGItemScanner.php')) {
    require_once $dir . '/FOGItemScanner.php';
} elseif (is_readable($dir . '/fogitemscanner.class.php')) {
    require_once $dir . '/fogitemscanner.class.php';
}
// PSR-4: the basename is the class name exactly, no lowercasing and no
// .class.php suffix. The old spelling is still accepted so the "check out an
// older tree into /tmp and diff the transcripts" workflow in this file's
// header keeps working across the move.
if (is_readable($dir . '/' . $class . '.php')) {
    require_once $dir . '/' . $class . '.php';
} else {
    require_once $dir . '/' . strtolower($class) . '.class.php';
}

// One file that is really there and one that is not. The missing one is the
// whole point: SnapinHash used to hash_file() it, get false, and save false.
$tmp = sys_get_temp_dir() . '/fog-scanner-' . getmypid();
@mkdir($tmp, 0700, true);
file_put_contents($tmp . '/present.img', 'fog');
Scenario::$dir = $tmp;

$scenarios = [
    'globally disabled' => [
        'enabled' => 0
    ],
    'storage group has gone' => [
        'groupExists' => false
    ],
    'nothing primary to this group' => [
        'itemIDs' => []
    ],
    'one file present, one missing' => [
        'itemIDs' => [11, 22],
        'files' => [11 => 'present.img', 22 => 'gone.img']
    ]
];

foreach ($scenarios as $name => $state) {
    Scenario::$enabled = 1;
    Scenario::$groupExists = true;
    Scenario::$itemIDs = [];
    Scenario::$files = [];
    Scenario::$out = [];
    foreach ($state as $key => $value) {
        Scenario::${$key} = $value;
    }
    $fqcn = __NAMESPACE__ . '\\' . $class;
    $svc = new $fqcn();
    printf("=== %s :: %s ===\n", $class, $name);
    printf("log=%s dev=%s zzz=%s\n", $svc::$log, $svc::$dev, $svc::$zzz);
    $svc->serviceRun();
    foreach (Scenario::$out as $line) {
        echo '  ' . str_replace($tmp, '<dir>', $line) . "\n";
    }
    echo "\n";
}

@unlink($tmp . '/present.img');
@rmdir($tmp);
