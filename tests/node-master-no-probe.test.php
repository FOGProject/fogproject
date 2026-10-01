<?php
/**
 * FOGService::checkIfNodeMaster() decides by address, and probes nothing.
 *
 * Serializing a storage node through Route::getList() or getItem() reads its
 * `online` field, and that read opens a TCP connection to the node's ssh
 * port. checkIfNodeMaster() used to serialize every enabled master, remote
 * ones included, before it matched the node against this machine's
 * addresses. The multicast loop calls it two or more times a pass,
 * so sshd on every master logged "Connection closed by <server>" every few
 * seconds (#1806). It also made a stopped sshd switch multicast off on the
 * server that runs it.
 *
 * The real FOGService.php runs here against stub collaborators. The router
 * stub counts serializations per node, and the node stub counts direct reads
 * of `online`. Each one costs a connection in the real tree.
 *
 * Usage: php tests/node-master-no-probe.test.php
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
 * A storage node that counts how often it is probed.
 */
class ProbeCountingNode
{
    public static $probes = 0;
    public $id;
    public $ip;
    private $_online;
    /**
     * Builds the node.
     *
     * @param int    $id     The id.
     * @param string $ip     The address.
     * @param bool   $online What a probe would answer.
     */
    public function __construct($id, $ip, $online)
    {
        $this->id = $id;
        $this->ip = $ip;
        $this->_online = $online;
    }
    /**
     * How many times `online` has been read.
     *
     * @return int
     */
    public static function probes()
    {
        return self::$probes;
    }
    /**
     * `online` is computed on read in the real model, by a probe.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function __get($key)
    {
        if ('online' === $key) {
            self::$probes++;
            return $this->_online;
        }
        return null;
    }
}

/**
 * Holds the nodes the router stub returns.
 */
class Nodes
{
    public static $all = [];
}

/**
 * Stands in for the API router.
 */
class Route
{
    public static $serialized = [];
    /**
     * The node ids serialized so far.
     *
     * @return array
     */
    public static function serialized()
    {
        return self::$serialized;
    }
    /**
     * Returns every enabled master's id, or one field of one node.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     * @param string $field The field to pluck.
     *
     * @return array
     */
    public static function getIds($class, $find = [], $field = 'id')
    {
        $nodes = isset($find['id'])
            ? array_intersect_key(Nodes::$all, [$find['id'] => 1])
            : Nodes::$all;
        return array_values(
            array_map(
                function ($n) use ($field) {
                    return $n->{$field};
                },
                $nodes
            )
        );
    }
    /**
     * Serializes every enabled master.
     *
     * @param string $class The route class.
     * @param array  $find  The filter.
     *
     * @return array
     */
    public static function getList($class, $find = [])
    {
        return array_values(
            array_map(
                function ($n) {
                    return self::getItem('storagenode', $n->id);
                },
                Nodes::$all
            )
        );
    }
    /**
     * Serializes one node by id.
     *
     * @param string $class The route class.
     * @param int    $id    The id.
     *
     * @return object|null
     */
    public static function getItem($class, $id)
    {
        self::$serialized[] = $id;
        return Nodes::$all[$id] ?? null;
    }
}

/**
 * Swallows hook events.
 */
class HookStub
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

/**
 * Stands in for the class root.
 */
abstract class FOGBase
{
    public static $ips = ['10.0.0.1'];
    public static $HookManager;
    /**
     * Returns this machine's addresses.
     *
     * @return array
     */
    protected static function getIPAddress()
    {
        return self::$ips;
    }
    /**
     * Returns an address unchanged.
     *
     * @param string $host The address.
     *
     * @return string
     */
    public static function resolveHostname($host)
    {
        return $host;
    }
}

/**
 * Referenced by FOGService's imports; unused here.
 */
class DatabaseManager
{
}

require_once dirname(__DIR__) . '/tests/lib/stub-buckets.php';
require_once dirname(__DIR__) . '/packages/web/src/Service/FOGService.php';

/**
 * Exposes the protected method under test.
 */
class MasterProbe extends \FOG\Service\FOGService
{
    /**
     * Skips the parent constructor, which needs a booted install.
     */
    public function __construct()
    {
    }
    /**
     * Calls checkIfNodeMaster() and reports the ids it returned.
     *
     * @return array
     */
    public function masters()
    {
        try {
            return array_map(
                function ($n) {
                    return $n->id;
                },
                $this->checkIfNodeMaster()
            );
        } catch (\Exception $e) {
            return [];
        }
    }
}

FOGBase::$HookManager = new HookStub();
$results = [];

// This machine masters node 1. Node 2 is another server's master.
Nodes::$all = [
    1 => new ProbeCountingNode(1, '10.0.0.1', true),
    2 => new ProbeCountingNode(2, '10.0.0.2', true),
];
ProbeCountingNode::$probes = 0;
Route::$serialized = [];
$got = (new MasterProbe())->masters();
$results[] = [$got === [1], 'the local master is returned and the remote one is not'];
$serialized = Route::serialized();
$results[] = [
    !in_array(2, $serialized, true),
    'the remote master is never serialized (serialized: '
        . implode(',', $serialized) . ')'
];
$probes = ProbeCountingNode::probes();
$results[] = [0 === $probes, 'no node is probed (got ' . $probes . ' probes)'];

// sshd is stopped on this machine, so a probe would answer false. Multicast
// on this machine needs no ssh, so it is still the master.
Nodes::$all = [1 => new ProbeCountingNode(1, '10.0.0.1', false)];
$got = (new MasterProbe())->masters();
$results[] = [$got === [1], 'a local master whose ssh is down is still the master'];

$failed = 0;
foreach ($results as [$ok, $what]) {
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $what);
    $failed += $ok ? 0 : 1;
}
exit($failed ? 1 : 0);
