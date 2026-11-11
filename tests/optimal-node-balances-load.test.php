<?php
/**
 * StorageGroup::getOptimalStorageNode() picks the least-loaded node.
 *
 * It used to read `clientload` off the serialized storagenode row. The 1.6
 * serializer carries no such field, so every node compared as null < null,
 * nothing beat the first node, and a whole lab of unicast deploys queued on
 * one node while the group's other nodes took nothing (forums 18310).
 *
 * The real StorageGroup.php runs here against stub collaborators. The router
 * stub serializes a node with the fields the real Route getter emits for a
 * storagenode -- the columns plus online, storagegroup and location_url --
 * and no `clientload`. The node stub answers getClientLoad() from a table.
 *
 * Usage: php tests/optimal-node-balances-load.test.php
 * Exit status 0 = pass, 1 = fail.
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

/**
 * Holds the nodes of the test group: id => [max, busy].
 */
class LoadNodes
{
    public static $all = [];
}

/**
 * Stands in for the ORM base: a field bag and the hook manager.
 */
abstract class LoadController
{
    public static $HookManager;
    public $data = [];
    /**
     * Reads a field.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key = '')
    {
        return $this->data[$key] ?? null;
    }
}

/**
 * Referenced by StorageGroup's imports; unused here.
 */
class LoadNodeManager
{
}

/**
 * Stands in for the API router.
 */
class LoadRoute
{
    private static $_data = '';
    /**
     * Lists the nodes asked for.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return void
     */
    public static function listem($class, $find = [])
    {
        $rows = [];
        foreach ((array)$find['id'] as $id) {
            $rows[] = ['id' => $id];
        }
        self::$_data = (string)json_encode(['data' => $rows]);
    }
    /**
     * Returns what listem() produced.
     *
     * @return string
     */
    public static function getData()
    {
        return self::$_data;
    }
    /**
     * Serializes one node the way the real getter does: no clientload.
     *
     * @param string $class The route class.
     * @param int    $id    The id.
     *
     * @return object|null
     */
    public static function getItem($class, $id)
    {
        if (!isset(LoadNodes::$all[$id])) {
            return null;
        }
        return (object)[
            'id' => $id,
            'isEnabled' => 1,
            'maxClients' => LoadNodes::$all[$id]['max'],
            'online' => true,
            'storagegroup' => null,
            'location_url' => 'http://10.0.0.' . $id . '/fog',
        ];
    }
}

/**
 * A storage node whose load comes from the test's table.
 */
class LoadStorageNode
{
    private $_id;
    /**
     * Builds the node.
     *
     * @param int $id The id.
     */
    public function __construct($id)
    {
        $this->_id = (int)$id;
    }
    /**
     * The load the real model computes from the task table.
     *
     * @return float
     */
    public function getClientLoad()
    {
        $n = LoadNodes::$all[$this->_id];
        return (float)$n['busy'] / $n['max'];
    }
    /**
     * Whether the node exists.
     *
     * @return bool
     */
    public function isValid()
    {
        return isset(LoadNodes::$all[$this->_id]);
    }
    /**
     * Reads the id, the only field the caller asks for.
     *
     * @return int
     */
    public function get()
    {
        return $this->_id;
    }
}

/**
 * Swallows hook events.
 */
class LoadHookStub
{
    /**
     * Does nothing.
     *
     * @param string $event The event.
     * @param array  $args  The arguments.
     *
     * @return void
     */
    public function processEvent($event, $args = [])
    {
    }
}

// Uniquely named stubs, aliased onto the names the real file imports.
// Declaring `class Route` here would give PHPStan's tests pass a second
// FOG\Route beside the other tests' stubs, and it would check their calls
// against this one.
class_alias(LoadController::class, 'FOG\\Base\\FOGController');
class_alias(LoadNodeManager::class, 'FOG\\Managers\\StorageNodeManager');
class_alias(LoadRoute::class, 'FOG\\Router\\Route');
class_alias(LoadStorageNode::class, 'FOG\\Items\\StorageNode');
require_once dirname(__DIR__) . '/packages/web/src/Items/StorageGroup.php';

LoadController::$HookManager = new LoadHookStub();

/**
 * Runs getOptimalStorageNode() on a group of the given nodes.
 *
 * @param array $nodes id => [max, busy]
 *
 * @return int the chosen node id
 */
function pick(array $nodes)
{
    LoadNodes::$all = $nodes;
    $group = (new \ReflectionClass('FOG\\Items\\StorageGroup'))
        ->newInstanceWithoutConstructor();
    $data = new \ReflectionProperty('FOG\\Base\\FOGController', 'data');
    $data->setAccessible(true);
    $data->setValue(
        $group,
        [
            'id' => 1,
            'name' => 'lab',
            'enablednodes' => array_keys($nodes),
            'allnodes' => array_keys($nodes),
        ]
    );
    return $group->getOptimalStorageNode()->get('id');
}

$results = [];

// The forum report: the master already holds a full queue, two other nodes
// hold nothing. The next deploy must go to an idle node.
$got = pick([
    1 => ['max' => 10, 'busy' => 10],
    2 => ['max' => 10, 'busy' => 0],
    3 => ['max' => 10, 'busy' => 0],
]);
$results[] = [2 === $got, 'a busy master loses to an idle node (got ' . $got . ')'];

// Load is relative to maxClients: 3 of 10 beats 2 of 4.
$got = pick([
    1 => ['max' => 4, 'busy' => 2],
    2 => ['max' => 10, 'busy' => 3],
]);
$results[] = [2 === $got, 'the lower load ratio wins (got ' . $got . ')'];

// A tie keeps the first node, as before.
$got = pick([
    1 => ['max' => 10, 'busy' => 0],
    2 => ['max' => 10, 'busy' => 0],
]);
$results[] = [1 === $got, 'a tie keeps the first node (got ' . $got . ')'];

$failed = 0;
foreach ($results as [$ok, $what]) {
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $what);
    $failed += $ok ? 0 : 1;
}
exit($failed ? 1 : 0);
