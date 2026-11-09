<?php
/**
 * A host waiting on a full node moves to a free node in its group.
 *
 * A unicast deploy gets its node once, at task creation. At boot,
 * TaskQueue::checkIn() used to check only that node: when it was full the
 * host waited in line there, while the group's other nodes sat idle (forums
 * 18310). checkIn() now asks _nodeWithOpenSlot() for another node in the
 * task's storage group before the host waits.
 *
 * The real TaskQueue.php runs here against stub collaborators, and the
 * private _nodeWithOpenSlot() is called directly.
 *
 * Usage: php tests/queued-host-moves-to-free-node.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

/**
 * Holds the nodes of the test group, and whether the task is a capture.
 */
class QueueFixture
{
    public static $nodes = [];
    public static $capture = false;
}

/**
 * Stands in for the API router: counts hosts waiting on a node.
 */
class QueueRoute
{
    /**
     * Counts checked-in tasks on one node.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return int
     */
    public static function getCount($class, $find = [])
    {
        return QueueFixture::$nodes[$find['storagenodeID']]['waiting'];
    }
}

/**
 * The task's storage group.
 */
class QueueStorageGroup
{
    /**
     * Reads the enabled node ids.
     *
     * @param string $key The field.
     *
     * @return array
     */
    public function get($key)
    {
        return array_keys(QueueFixture::$nodes);
    }
}

/**
 * A storage node read from the fixture.
 */
class QueueStorageNode
{
    private $_id;
    /**
     * Builds the node.
     *
     * @param int $id The id.
     */
    public function __construct($id = 0)
    {
        $this->_id = (int)$id;
    }
    /**
     * The fixture row.
     *
     * @return array
     */
    private function _row()
    {
        return QueueFixture::$nodes[$this->_id];
    }
    /**
     * Whether the node exists.
     *
     * @return bool
     */
    public function isValid()
    {
        return isset(QueueFixture::$nodes[$this->_id]);
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
        $row = $this->_row();
        $map = [
            'id' => $this->_id,
            'isEnabled' => $row['enabled'] ?? 1,
            'maxClients' => $row['max'],
            'online' => $row['online'] ?? true,
            'usedtasks' => [1, 15, 17],
        ];
        return $map[$key] ?? null;
    }
    /**
     * Hosts imaging from this node now.
     *
     * @return int
     */
    public function getUsedSlotCount()
    {
        return $this->_row()['busy'];
    }
    /**
     * Whether this node failed the host recently.
     *
     * @return bool
     */
    public function getNodeFailure()
    {
        return $this->_row()['failed'] ?? false;
    }
}

/**
 * The host being imaged and the task being checked in.
 */
class QueueTaskStub
{
    /**
     * Every id the caller reads (host id, storage group id) is 1 here.
     *
     * @return int
     */
    public function get()
    {
        return 1;
    }
    /**
     * Whether the task uploads an image.
     *
     * @return bool
     */
    public function isCapture()
    {
        return QueueFixture::$capture;
    }
}

/**
 * Stands in for the tasking base: the state _nodeWithOpenSlot() reads.
 */
abstract class QueueTaskingElement
{
    public static $Host;
    protected $Task;
    protected $StorageNode;
    /**
     * The checked-in state id.
     *
     * @return int
     */
    public static function getCheckedInState()
    {
        return 2;
    }
}

// Uniquely named stubs, aliased onto the names the real file uses.
// Declaring `class Route` here would give PHPStan's tests pass a second
// FOG\Route beside the other tests' stubs, and it would check their calls
// against this one.
class_alias(QueueRoute::class, 'FOG\\Router\\Route');
class_alias(QueueStorageGroup::class, 'FOG\\Items\\StorageGroup');
class_alias(QueueStorageNode::class, 'FOG\\Items\\StorageNode');
class_alias(QueueTaskingElement::class, 'FOG\\TaskHandling\\TaskingElement');
require_once dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskQueue.php';

/**
 * Runs _nodeWithOpenSlot() for a host whose task sits on node 1.
 *
 * @param array $nodes   id => node fixture row
 * @param bool  $capture whether the task is a capture
 *
 * @return int|null the node chosen, or null to keep waiting
 */
function freeNode(array $nodes, $capture = false)
{
    QueueFixture::$nodes = $nodes;
    QueueFixture::$capture = $capture;
    QueueTaskingElement::$Host = new QueueTaskStub();
    $ref = new \ReflectionClass(\FOG\TaskHandling\TaskQueue::class);
    $queue = $ref->newInstanceWithoutConstructor();
    $props = [
        'StorageNode' => new QueueStorageNode(1),
        'Task' => new QueueTaskStub(),
    ];
    foreach ($props as $prop => $value) {
        $p = new \ReflectionProperty(QueueTaskingElement::class, $prop);
        $p->setAccessible(true);
        $p->setValue($queue, $value);
    }
    $m = $ref->getMethod('_nodeWithOpenSlot');
    $m->setAccessible(true);
    $got = $m->invoke($queue);
    return $got ? $got->get('id') : null;
}

$full = ['max' => 10, 'busy' => 10, 'waiting' => 22];
$idle = ['max' => 10, 'busy' => 0, 'waiting' => 0];
$results = [];

// The forum report: the master is full, two nodes are idle.
$got = freeNode([1 => $full, 2 => $idle, 3 => $idle]);
$results[] = [
    2 === $got,
    'a host on a full node moves to an idle node (got ' . var_export($got, true) . ')'
];

// The node with the most free slots wins.
$got = freeNode([
    1 => $full,
    2 => ['max' => 10, 'busy' => 8, 'waiting' => 0],
    3 => ['max' => 10, 'busy' => 2, 'waiting' => 1],
]);
$results[] = [
    3 === $got,
    'the node with the most free slots wins (got ' . var_export($got, true) . ')'
];

// Hosts already waiting on another node hold its slots.
$got = freeNode([1 => $full, 2 => ['max' => 10, 'busy' => 5, 'waiting' => 5]]);
$results[] = [
    null === $got,
    'slots claimed by waiting hosts are not free (got ' . var_export($got, true) . ')'
];

// Unreachable, disabled, and recently failed nodes are skipped.
$got = freeNode([
    1 => $full,
    2 => $idle + ['online' => false],
    3 => $idle + ['enabled' => 0],
    4 => $idle + ['failed' => true],
]);
$results[] = [
    null === $got,
    'offline, disabled and failed nodes are skipped (got ' . var_export($got, true) . ')'
];

// A capture stays on the master even when another node is idle.
$got = freeNode([1 => $full, 2 => $idle], true);
$results[] = [
    null === $got,
    'a capture is never moved off its node (got ' . var_export($got, true) . ')'
];

// The host's own node is never returned as the alternative.
$got = freeNode([1 => $idle]);
$results[] = [
    null === $got,
    'the current node is not an alternative (got ' . var_export($got, true) . ')'
];

$failed = 0;
foreach ($results as [$ok, $what]) {
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $what);
    $failed += $ok ? 0 : 1;
}
exit($failed ? 1 : 0);
