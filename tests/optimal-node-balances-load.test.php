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
    /**
     * The queued states, as TaskState answers them.
     *
     * @return array
     */
    public static function getQueuedStates()
    {
        return [0, 1, 2];
    }
    /**
     * The in-progress state.
     *
     * @return int
     */
    public static function getProgressState()
    {
        return 3;
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
     * Counts a node's active tasks: its `busy` value.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return int
     */
    public static function getCount($class, $find = [])
    {
        return LoadNodes::$all[$find['storagenodeID']]['busy'];
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
     * Reads a field from the test's table.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key = 'id')
    {
        $n = LoadNodes::$all[$this->_id];
        $map = [
            'id' => $this->_id,
            'isEnabled' => $n['enabled'] ?? 1,
            'maxClients' => $n['max'],
            'online' => $n['online'] ?? true,
            'usedtasks' => [1, 15, 17],
        ];
        return $map[$key] ?? null;
    }
    /**
     * Whether this node failed the host recently.
     *
     * @return bool
     */
    public function getNodeFailure()
    {
        return LoadNodes::$all[$this->_id]['failed'] ?? false;
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
 * Builds the real StorageGroup over the given nodes.
 *
 * @param array $nodes id => [max, busy, ...]
 *
 * @return object
 */
function group(array $nodes)
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
    return $group;
}

/**
 * Runs getOptimalStorageNode() on a group of the given nodes.
 *
 * @param array $nodes id => [max, busy]
 *
 * @return int the chosen node id
 */
function pick(array $nodes)
{
    return group($nodes)->getOptimalStorageNode()->get('id');
}

/**
 * Runs getOverflowNode() for a host on node 1. `busy` is the active count.
 *
 * @param array $nodes id => [max, busy, enabled?, online?, failed?]
 *
 * @return int|null the node to move to, or null to stay
 */
function overflow(array $nodes)
{
    $got = group($nodes)->getOverflowNode(new LoadStorageNode(1), 7);
    return $got ? $got->get('id') : null;
}

$results = [];

// getOverflowNode() writes to the error log when a host must wait.
$log = tempnam(sys_get_temp_dir(), 'overflow-log-');
ini_set('error_log', $log);
/**
 * Returns what the error log gained since the last call, and clears it.
 *
 * @return string
 */
function takeLog()
{
    global $log;
    $text = (string)file_get_contents($log);
    file_put_contents($log, '');
    return $text;
}

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

// getOverflowNode(): node 1 holds the host's own task, so its active count
// includes the host.
$idle = ['max' => 10, 'busy' => 0];

// The forum report: 30 tasks on the master, two idle nodes.
$got = overflow([1 => ['max' => 10, 'busy' => 30], 2 => $idle, 3 => $idle]);
$results[] = [
    2 === $got,
    'an over-capacity node overflows to an idle node (got ' . var_export($got, true) . ')'
];

// At exactly maxClients the host fits, so it stays.
$got = overflow([1 => ['max' => 10, 'busy' => 10], 2 => $idle]);
$results[] = [
    null === $got,
    'a node at its limit keeps the host (got ' . var_export($got, true) . ')'
];

// One over the limit moves.
$got = overflow([1 => ['max' => 10, 'busy' => 11], 2 => $idle]);
$results[] = [
    2 === $got,
    'one task over the limit moves (got ' . var_export($got, true) . ')'
];

// The node with the most free slots wins; a full node is no candidate.
$got = overflow([
    1 => ['max' => 10, 'busy' => 20],
    2 => ['max' => 10, 'busy' => 10],
    3 => ['max' => 10, 'busy' => 5],
]);
$results[] = [
    3 === $got,
    'the node with the most free slots wins (got ' . var_export($got, true) . ')'
];

// Disabled, unreachable and recently failed nodes are skipped.
$got = overflow([
    1 => ['max' => 10, 'busy' => 20],
    2 => $idle + ['enabled' => 0],
    3 => $idle + ['online' => false],
    4 => $idle + ['failed' => true],
]);
$results[] = [
    null === $got,
    'disabled, offline and failed nodes are skipped (got ' . var_export($got, true) . ')'
];
$text = takeLog();
$results[] = [
    1 === substr_count($text, 'FOG storage node overflow:')
    && false !== strpos($text, 'node id 1 has 20 tasks, over its Max Clients 10, so host id 7')
    && false !== strpos($text, 'node id 2 is disabled or has Max Clients 0')
    && false !== strpos($text, 'node id 3 did not answer on its SSH or web port')
    && false !== strpos($text, 'node id 4 failed this host recently'),
    'a host that must wait logs each node and why (got ' . trim($text) . ')'
];

// A full node is named with its count.
overflow([1 => ['max' => 10, 'busy' => 12], 2 => ['max' => 10, 'busy' => 10]]);
$text = takeLog();
$results[] = [
    false !== strpos($text, 'node id 2 is full (10 tasks, Max Clients 10)'),
    'a full node is logged with its count (got ' . trim($text) . ')'
];

// A group of one node has nowhere to go.
$got = overflow([1 => ['max' => 10, 'busy' => 30]]);
$results[] = [
    null === $got,
    'a one-node group keeps the host (got ' . var_export($got, true) . ')'
];
$text = takeLog();
$results[] = [
    false !== strpos($text, 'no node to take it: no other enabled node'),
    'a one-node group logs that it has no other node (got ' . trim($text) . ')'
];

// A host that moves, or fits, writes nothing.
overflow([1 => ['max' => 10, 'busy' => 11], 2 => $idle]);
overflow([1 => ['max' => 10, 'busy' => 10], 2 => $idle]);
$text = takeLog();
$results[] = ['' === $text, 'a host that moves or fits logs nothing (got ' . trim($text) . ')'];

// A tie keeps the first node, as before.
$got = pick([
    1 => ['max' => 10, 'busy' => 0],
    2 => ['max' => 10, 'busy' => 0],
]);
$results[] = [1 === $got, 'a tie keeps the first node (got ' . $got . ')'];

unlink($log);

$failed = 0;
foreach ($results as [$ok, $what]) {
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $what);
    $failed += $ok ? 0 : 1;
}
exit($failed ? 1 : 0);
