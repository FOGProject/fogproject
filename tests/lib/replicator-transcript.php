<?php
/**
 * Runs a replicator against stubs and prints everything it would log.
 *
 * The replicators are daemons with no test coverage, and the risk in giving
 * them a shared base is not that the code stops parsing -- it is that the
 * ORDER of operations, or one of the messages, quietly changes. Diffing a
 * transcript catches exactly that, and it does so without a database, a
 * storage node, or an ftp server.
 *
 * It is deliberately not a mock framework. Every stub here answers the one
 * question the replicator asks it, and the scenarios are chosen to walk each
 * branch of the sequence once.
 *
 * Usage: php tests/lib/replicator-transcript.php <class> [<dir>]
 *   class  ImageReplicator | SnapinReplicator
 *   dir    directory holding the *.class.php files, for running an older
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
    public static $assocCount = 0;
    public static $primary = [];
    public static $devSetting = '';
    public static $logSetting = '';
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
     * Returns a matching count.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return int
     */
    public static function getCount($class, $find = [])
    {
        Scenario::$out[] = sprintf(
            '[getCount %s find=%s]',
            $class,
            json_encode($find)
        );
        return Scenario::$assocCount;
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
            $rows[] = (object)['id' => $id, 'name' => 'item' . $id];
        }
        return $rows;
    }
}

/**
 * Stands in for the Image model.
 */
class Image
{
    public $id = 0;
    /**
     * Builds the stub.
     *
     * @param int $id The id.
     */
    public function __construct($id = 0)
    {
        $this->id = $id;
    }
    /**
     * Is the group primary for this item?
     *
     * @param int $groupID The group.
     * @param int $itemID  The item.
     *
     * @return bool
     */
    public static function getPrimaryGroup($groupID, $itemID)
    {
        return in_array($itemID, Scenario::$primary);
    }
}

/**
 * Stands in for the Snapin model.
 */
class Snapin extends Image
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
    public $procRef = [];
    public $procPipes = [];
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
            // Device and log come from the scenario so both the "unset,
            // fall back to the built-in default" and the "admin configured
            // it" paths are walked. The third is the sleep time, left
            // empty so the default is exercised.
            return [
                Scenario::$devSetting,
                Scenario::$logSetting,
                ''
            ];
        }
        if (false !== strpos((string)$keys, 'GLOBALENABLED')) {
            return Scenario::$enabled;
        }
        return '';
    }
    /**
     * Instantiates a class by name.
     *
     * @param string $class The class.
     * @param mixed  $data  Optional constructor argument.
     *
     * @return object
     */
    public static function getClass($class, $data = null)
    {
        return null === $data ? new $class() : new $class($data);
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
                'storagegroupID' => 3
            ]
        ];
    }
    /**
     * Records a replication request.
     *
     * @param int    $groupID The group.
     * @param int    $nodeID  The node.
     * @param object $item    The item.
     * @param bool   $primary Group-to-group rather than group-to-nodes.
     * @param string $path    An extra path, when not an item.
     *
     * @return void
     */
    protected function replicateItems(
        $groupID,
        $nodeID,
        $item,
        $primary = false,
        $path = ''
    ) {
        Scenario::$out[] = sprintf(
            '[replicateItems group=%d node=%d item=%s#%d primary=%s path=%s]',
            $groupID,
            $nodeID,
            get_class($item),
            (int)($item->id ?? 0),
            $primary ? 'yes' : 'no',
            '' === $path || null === $path ? '-' : $path
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

$class = $argv[1] ?? 'ImageReplicator';
$dir = $argv[2] ?? (dirname(__DIR__, 2) . '/packages/web/src/Service');

if (is_readable($dir . '/FOGReplicator.php')) {
    // Publishes the FOGService stub above as FOG\Service\FOGService,
    // the name FOGReplicator extends since Move 2.
    require_once dirname(__DIR__) . '/lib/stub-buckets.php';
    require_once $dir . '/FOGReplicator.php';
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

$scenarios = [
    'globally disabled' => [
        'enabled' => 0
    ],
    'storage group has gone' => [
        'groupExists' => false
    ],
    'nothing enabled to replicate' => [
        'itemIDs' => []
    ],
    'enabled but nothing associated' => [
        'itemIDs' => [11, 22],
        'assocCount' => 0
    ],
    'two items, one primary to this group' => [
        'itemIDs' => [11, 22],
        'assocCount' => 2,
        'primary' => [11]
    ],
    // The admin has set both, so the built-in defaults must NOT win. This
    // scenario exists because a log path built from the wrong variable
    // still lands in the right place when logpath and FOG_LOG_DIR happen
    // to agree -- which they do on a default install, and which made an
    // earlier version of this harness blind to exactly that regression.
    'log path and console device configured' => [
        'itemIDs' => [11],
        'assocCount' => 1,
        'primary' => [11],
        'devSetting' => '/dev/tty9',
        'logSetting' => 'custom-name.log'
    ]
];

foreach ($scenarios as $name => $state) {
    Scenario::$enabled = 1;
    Scenario::$groupExists = true;
    Scenario::$itemIDs = [];
    Scenario::$assocCount = 0;
    Scenario::$primary = [];
    Scenario::$devSetting = '';
    Scenario::$logSetting = '';
    Scenario::$out = [];
    foreach ($state as $key => $value) {
        Scenario::${$key} = $value;
    }
    // Bucketed first, flat as the fallback: Move 2 put the daemons in
    // FOG\Service, and the flat spelling keeps the "diff this transcript
    // against an older checkout" workflow in this file's header working.
    $fqcn = 'FOG\\Service\\' . $class;
    if (!class_exists($fqcn, false)) {
        $fqcn = __NAMESPACE__ . '\\' . $class;
    }
    $svc = new $fqcn();
    printf("=== %s :: %s ===\n", $class, $name);
    printf("log=%s dev=%s zzz=%s\n", $svc::$log, $svc::$dev, $svc::$zzz);
    $svc->serviceRun();
    foreach (Scenario::$out as $line) {
        echo '  ' . $line . "\n";
    }
    echo "\n";
}
