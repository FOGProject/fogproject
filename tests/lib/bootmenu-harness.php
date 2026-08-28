<?php
/**
 * A stub FOG runtime just large enough to render IpxeBootMenu's iPXE output.
 *
 * IpxeBootMenu extends FOGBase and reaches storage only through FOGBase's statics
 * and the Route facade, so replacing those two replaces the entire world it
 * can see. Nothing here talks to MySQL, the network, or the filesystem outside
 * a caller-supplied BASEPATH, which is what lets the boot menu be rendered
 * byte-for-byte in a test.
 *
 * Fidelity notes -- these are the places where a lazy stub would silently
 * produce the wrong golden file:
 *
 * 1. getSetting() has two shapes. A string key returns one value or null; an
 *    array of keys returns one entry per key IN THE ORDER ASKED FOR, null for
 *    a row that does not exist. IpxeBootMenu destructures the array form
 *    positionally with list(), so both the ordering and the never-skip rule
 *    are load-bearing. The stub reproduces them, and additionally throws on a
 *    key with no fixture: silently returning null for an unknown setting would
 *    bake a plausible-looking but wrong golden file.
 * 2. Route::getList() hands back objects with PUBLIC PROPERTIES ($Menu->name,
 *    $StorageNode->ip), not get()-style models. RouteItem below is that shape;
 *    using a get() model instead would fatal inside _menuItem().
 * 3. fastmerge() and arrayInsertAfter() are copied verbatim from FOGBase --
 *    fastmerge's numeric-vs-string key handling decides menu line ordering.
 * 4. resolveHostname() never does DNS. A test that resolves a name is a test
 *    that fails on an airplane.
 *
 * Everything lives in namespace FOG because bootmenu.class.php does; the
 * class_alias at the foot of that file then re-exports IpxeBootMenu globally
 * exactly as it does in production.
 *
 * Not a mock framework by design: the house style is a plain PHP script with
 * an exit status, discovered by tests/run-all.sh.
 */

namespace FOG;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/*
 * Route rows are plain stdClass objects, created with (object)[...].
 *
 * Not a class with __get: IpxeBootMenu::generateIpxeItems() builds its iPXE
 * output by iterating the row -- foreach ($object as $property => $value) --
 * which only sees genuinely public properties. A row backed by a private
 * array with an accessor iterates as empty, so every `set hostname ...` line
 * would silently vanish from the golden file and the host-info block would
 * look correctly empty rather than broken.
 */

/**
 * Generic record stub standing in for a FOGController model.
 */
class StubModel
{
    /**
     * Backing field data.
     *
     * @var array
     */
    protected $data = [];
    /**
     * Instantiates the record.
     *
     * @param array $data the field data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    /**
     * A record is valid when it carries a non-zero id.
     *
     * @return bool
     */
    public function isValid()
    {
        return !empty($this->data['id']);
    }
    /**
     * Reads a field.
     *
     * @param string $key the field to read
     *
     * @return mixed
     */
    public function get($key = '')
    {
        if ('' === $key) {
            return $this->data;
        }
        return array_key_exists($key, $this->data) ? $this->data[$key] : '';
    }
    /**
     * Writes a field.
     *
     * @param string $key   the field to write
     * @param mixed  $value the value to write
     *
     * @return self
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
    /**
     * Persistence is a no-op; nothing here has a database.
     *
     * @return bool
     */
    public function save()
    {
        return true;
    }
    /**
     * Destruction is a no-op.
     *
     * @return bool
     */
    public function destroy()
    {
        return true;
    }
    /**
     * Property reads mirror get(), because 1.6 code freely uses both.
     *
     * @param string $name the field
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }
}

/**
 * MAC list stub. IpxeBootMenu stringifies this when logging the request.
 */
class StubMac extends StubModel
{
    /**
     * Renders the MAC as IpxeBootMenu expects.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->get('mac');
    }
    /**
     * Imaging-ignore flag, read only on the tasking path.
     *
     * @return bool
     */
    public function isImageIgnored()
    {
        return (bool)$this->get('ignored');
    }
}

/**
 * Task stub. The rendering scenarios use invalid tasks so getTasking()
 * falls through to printDefault(), which is where the menu is built.
 */
class StubTask extends StubModel
{
    /**
     * Whether this is a snapin-only tasking.
     *
     * @return bool
     */
    public function isSnapinTasking()
    {
        return (bool)$this->get('snapin');
    }
    /**
     * Whether the task itself is a capture.
     *
     * Separate from TaskType::isCapture() on purpose -- getTasking() consults
     * both, and a scenario needs to be able to disagree between them to reach
     * the getMasterStorageNode() branch.
     *
     * @return bool
     */
    public function isCapture()
    {
        return (bool)$this->get('capture');
    }
    /**
     * Returns the task's type.
     *
     * @return TaskType
     */
    public function getTaskType()
    {
        return new TaskType((array)($this->data['tasktype'] ?? []));
    }
    /**
     * Returns the image being deployed or captured.
     *
     * @return Image
     */
    public function getImage()
    {
        return new Image((array)($this->data['image'] ?? []));
    }
}

/**
 * Task type stub.
 *
 * The four is*() predicates are what steer getTasking(): whether an init is
 * needed at all, whether a storage node is resolved as master or optimal, and
 * whether the multicast session lookup runs.
 */
class TaskType extends StubModel
{
    /**
     * Whether the type images a disk.
     *
     * @return bool
     */
    public function isImagingTask()
    {
        return (bool)$this->get('imaging');
    }
    /**
     * Whether the type is multicast.
     *
     * @return bool
     */
    public function isMulticast()
    {
        return (bool)$this->get('multicast');
    }
    /**
     * Whether the type needs FOS booted.
     *
     * @return bool
     */
    public function isInitNeededTasking()
    {
        return (bool)$this->get('initneeded');
    }
    /**
     * Whether the type captures rather than deploys.
     *
     * @return bool
     */
    public function isCapture()
    {
        return (bool)$this->get('capture');
    }
}

/**
 * Image stub, with the two type lookups getTasking() chains through.
 */
class Image extends StubModel
{
    /**
     * Returns the image type (mps, mpa, n).
     *
     * @return StubModel
     */
    public function getImageType()
    {
        return new StubModel(['id' => 1, 'type' => $this->get('imagetype')]);
    }
    /**
     * Returns the partition type (all, mbr, ...).
     *
     * A string, not an object: the real Image::getPartitionType() is
     * getImagePartitionType()->get('type'), and getTasking() concatenates the
     * result straight into imgPartitionType=. Returning an object here makes
     * the render die with "could not be converted to string", which is how
     * this was found.
     *
     * @return string
     */
    public function getPartitionType()
    {
        return (string)$this->get('partitiontype');
    }
    /**
     * Returns the group a deploy reads from.
     *
     * @return StorageGroup
     */
    public function getStorageGroup()
    {
        return new StorageGroup(['id' => 1, 'name' => 'default']);
    }
    /**
     * Returns the group a capture writes to.
     *
     * @return StorageGroup
     */
    public function getPrimaryStorageGroup()
    {
        return new StorageGroup(['id' => 1, 'name' => 'default', 'primary' => true]);
    }
}

/**
 * Storage group stub.
 *
 * Both getters return the same node deliberately: which one getTasking()
 * calls is the behaviour under test, and the golden shows it through the
 * storage= argument rather than through the node's identity.
 */
class StorageGroup extends StubModel
{
    /**
     * The node a deploy reads from.
     *
     * @return StorageNode
     */
    public function getOptimalStorageNode()
    {
        return new StorageNode(1);
    }
    /**
     * The node a capture writes to.
     *
     * @return StorageNode
     */
    public function getMasterStorageNode()
    {
        return new StorageNode(1);
    }
}

/**
 * Multicast session stub.
 */
class MulticastSession extends StubModel
{
}

/**
 * Multicast session association stub, looked up by id from the fixture rows.
 */
class MulticastSessionAssociation extends StubModel
{
    /**
     * Looks the association up by id.
     *
     * @param int $id the association id
     */
    public function __construct($id = 0)
    {
        $found = [];
        foreach ((array)(Route::$rows['multicastsessionassociation'] ?? []) as $assoc) {
            if (($assoc->id ?? null) == $id) {
                $found = (array)$assoc;
                break;
            }
        }
        parent::__construct($found);
    }
    /**
     * Returns the session this association points at.
     *
     * @return MulticastSession
     */
    public function getMulticastSession()
    {
        return new MulticastSession(
            [
                'id' => $this->get('msID'),
                'image' => $this->get('image'),
            ]
        );
    }
}

/**
 * Host stub, with the nested objects IpxeBootMenu reaches through.
 */
class StubHost extends StubModel
{
    /**
     * Reads a field, materialising the nested objects IpxeBootMenu expects.
     *
     * @param string $key the field to read
     *
     * @return mixed
     */
    public function get($key = '')
    {
        switch ($key) {
            case 'mac':
                return new StubMac(
                    [
                        'id' => 1,
                        'mac' => $this->data['mac'] ?? '00:11:22:33:44:55',
                        'ignored' => $this->data['imageignored'] ?? false,
                    ]
                );
            case 'task':
                return new StubTask((array)($this->data['task'] ?? []));
            case 'inventory':
                return new StubModel(
                    ['id' => 1, 'sysuuid' => $this->data['sysuuid'] ?? '']
                );
        }
        return parent::get($key);
    }
    /**
     * Returns the assigned image.
     *
     * @return StubModel
     */
    public function getImage()
    {
        return new StubModel((array)($this->data['image'] ?? []));
    }
    /**
     * Every MAC on the host.
     *
     * Read on the tasking path only, where it becomes the mac= kernel
     * argument. Defaults to the host's primary so a scenario that does not
     * care about multi-NIC still renders.
     *
     * @return array
     */
    public function getMyMacs()
    {
        return (array)($this->data['macs']
            ?? [$this->data['mac'] ?? '00:11:22:33:44:55']);
    }
}

/**
 * Hook manager stub.
 *
 * Records every event, and can act as a plugin: `mutate` maps an event name
 * to argument overrides, applied on fire. Because BOOT_ITEM_NEW_SETTINGS is
 * built with reference elements (['initrd' => &$initrd]), writing to the
 * argument array here writes through to the caller's variable -- which is
 * exactly the mechanism a real plugin uses, and the only way to test whether
 * a plugin's value actually survives.
 */
class StubHookManager
{
    /**
     * Events seen, in order.
     *
     * @var array
     */
    public $fired = [];
    /**
     * Event name => [argument => replacement value].
     *
     * @var array
     */
    public $mutate = [];
    /**
     * Instantiates the manager.
     *
     * @param array $mutate the plugin-like overrides to apply
     */
    public function __construct(array $mutate = [])
    {
        $this->mutate = $mutate;
    }
    /**
     * Records an event, then applies any configured overrides.
     *
     * @param string $event     the event name
     * @param array  $arguments the event arguments
     *
     * @return void
     */
    public function processEvent($event, $arguments = [])
    {
        $scalars = [];
        foreach ((array)$arguments as $key => $value) {
            if (null === $value || is_scalar($value)) {
                $scalars[$key] = $value;
            }
        }
        $this->fired[] = [
            'event' => $event,
            'args' => array_keys((array)$arguments),
            'scalars' => $scalars,
        ];
        foreach ((array)($this->mutate[$event] ?? []) as $key => $value) {
            if (array_key_exists($key, $arguments)) {
                $arguments[$key] = $value;
            }
        }
    }
    /**
     * Registration is a no-op here.
     *
     * @param string $event    the event name
     * @param mixed  $callback the callback
     *
     * @return void
     */
    public function register($event, $callback)
    {
    }
}

/**
 * The stub Route facade.
 */
class Route
{
    /**
     * Fixture rows, keyed by the lowercase class name Route is asked for.
     *
     * @var array
     */
    public static $rows = [];
    /**
     * Filters fixture rows the way Route would for the equality-on-a-field
     * cases IpxeBootMenu uses. An array filter value means "field IN (...)".
     *
     * @param string $class     the class to query
     * @param mixed  $findWhere the filter
     * @param string $whereOp   unused; signature parity
     * @param string $orderBy   field to sort by
     *
     * @throws \Exception on a class with no fixture
     * @return array
     */
    public static function getList(
        $class,
        $findWhere = [],
        $whereOp = 'AND',
        $orderBy = ''
    ) {
        $class = strtolower($class);
        if (!array_key_exists($class, self::$rows)) {
            throw new \Exception("harness: no fixture rows for '$class'");
        }
        $rows = self::$rows[$class];
        foreach ((array)$findWhere as $field => $want) {
            $rows = array_values(
                array_filter(
                    $rows,
                    function ($row) use ($field, $want) {
                        $have = $row->{$field} ?? null;
                        if (is_array($want)) {
                            return in_array($have, $want);
                        }
                        return $have == $want;
                    }
                )
            );
        }
        if ($orderBy) {
            usort(
                $rows,
                function ($a, $b) use ($orderBy) {
                    return ($a->{$orderBy} ?? null) <=> ($b->{$orderBy} ?? null);
                }
            );
        }
        return $rows;
    }
    /**
     * Returns the ids of the matching rows.
     *
     * @param string $class     the class to query
     * @param mixed  $findWhere the filter
     * @param string $getField  the column to return
     *
     * @return array
     */
    public static function getIds($class, $findWhere = [], $getField = 'id')
    {
        return array_map(
            function ($row) use ($getField) {
                return $row->{$getField} ?? null;
            },
            self::getList($class, $findWhere ?: [])
        );
    }
    /**
     * Returns one row by id.
     *
     * @param string $class the class to query
     * @param int    $id    the row id
     *
     * @return RouteItem|null
     */
    public static function getItem($class, $id)
    {
        $rows = self::getList($class, ['id' => $id]);
        return $rows ? $rows[0] : null;
    }
    /**
     * Field names IpxeBootMenu must not echo into an iPXE script. Kept small and
     * real: generateIpxeItems() uses it to decide what to omit.
     *
     * @return array
     */
    public static function sensitiveFieldMap()
    {
        /*
         * Three tiers, each keyed by class name with a list of that class'
         * field names -- generateIpxeItems() walks it two levels deep
         * (foreach tier, foreach class => $fields) and array_merges the
         * innermost lists, so a flatter stub fatals on array_merge().
         * Mirrors Route::$sensitiveFields / $sensitiveAlwaysFields /
         * Redaction::$patternExempt.
         */
        return [
            'fields' => [
                'host' => [
                    'ADPass',
                    'ADPassLegacy',
                    'productKey',
                    'pub_key',
                    'sec_tok',
                    'prev_sec_tok',
                    'sec_time',
                    'token',
                ],
            ],
            'always' => [
                'storagenode' => ['pass'],
            ],
            'exempt' => [],
        ];
    }
}

/**
 * The stub FOGBase. IpxeBootMenu's entire view of the system is these members
 * plus Route.
 */
class FOGBase
{
    /**
     * The host being booted.
     *
     * @var StubHost
     */
    public static $Host;
    /**
     * The hook manager.
     *
     * @var StubHookManager
     */
    public static $HookManager;
    /**
     * The HTTP protocol string.
     *
     * @var string
     */
    public static $httpproto = 'http';
    /**
     * Settings, keyed by name, as globalSettings would hold them.
     *
     * @var array
     */
    public static $settings = [];
    /**
     * Base constructor; nothing to bootstrap.
     */
    public function __construct()
    {
    }
    /**
     * Reads one setting, or several in the order asked for.
     *
     * @param string|array $key the setting name(s)
     *
     * @throws \Exception on a key with no fixture
     * @return mixed
     */
    public static function getSetting($key)
    {
        $keys = (array)$key;
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, self::$settings)) {
                throw new \Exception(
                    "harness: no fixture for setting '$k'; add it, because a "
                    . "missing row would otherwise render as null and bake a "
                    . "wrong golden file"
                );
            }
            $out[] = self::$settings[$k];
        }
        return is_string($key) ? $out[0] : $out;
    }
    /**
     * Factory for the classes IpxeBootMenu asks for by name.
     *
     * @param string $class the class name
     *
     * @throws \Exception on an unsupported class
     * @return mixed
     */
    public static function getClass($class)
    {
        $args = func_get_args();
        array_shift($args);
        switch ($class) {
            case 'KeySequence':
                $seq = trim((string)($args[0] ?? ''));
                if ('' === $seq) {
                    return new StubModel([]);
                }
                return new StubModel(
                    ['id' => 1, 'ascii' => '0x1b', 'name' => $seq]
                );
            case 'Ipxe':
                return new StubModel(['id' => (int)($args[0] ?? 0)]);
            case 'StorageNode':
                return new StorageNode((int)($args[0] ?? 0));
            case 'Image':
                return new StubModel((array)($args[0] ?? []));
        }
        throw new \Exception("harness: unsupported getClass('$class')");
    }
    /**
     * Copied verbatim from FOGBase: its numeric-vs-string key handling
     * decides the order menu lines land in.
     *
     * @param array $array1 the base array
     *
     * @return array
     */
    public static function fastmerge($array1)
    {
        $others = func_get_args();
        array_shift($others);
        foreach ((array)$others as &$other) {
            foreach ((array)$other as $key => &$oth) {
                if (is_numeric($key)) {
                    $array1[] = $oth;
                    continue;
                } elseif (isset($array1[$key])) {
                    $array1[$key] = $oth;
                    continue;
                }
                unset($oth);
            }
            $array1 += $other;
            unset($other);
        }

        return $array1;
    }
    /**
     * Copied verbatim from FOGBase.
     *
     * @param string $key       the key to insert after
     * @param array  $array     the array to work with
     * @param string $new_key   the key to add
     * @param mixed  $new_value the value to add
     *
     * @throws \Exception
     * @return void
     */
    protected static function arrayInsertAfter(
        $key,
        array &$array,
        $new_key,
        $new_value
    ) {
        if (!is_string($key) && !is_numeric($key)) {
            throw new \Exception('Key must be a string or index');
        }
        $new = [];
        foreach ($array as $k => &$value) {
            $new[$k] = $value;
            if ($k === $key) {
                $new[$new_key] = $new_value;
            }
            unset($k, $value);
        }
        $array = $new;
    }
    /**
     * Copied verbatim from FOGBase.
     *
     * @param mixed $ids the collection to reduce
     *
     * @return mixed
     */
    public static function minId($ids)
    {
        $ids = (array)$ids;
        return empty($ids) ? 0 : min($ids);
    }
    /**
     * Copied verbatim from FOGBase.
     *
     * @param mixed $ids the collection to reduce
     *
     * @return mixed
     */
    public static function maxId($ids)
    {
        $ids = (array)$ids;
        return empty($ids) ? 0 : max($ids);
    }
    /**
     * Deterministic stand-in: never performs DNS.
     *
     * @param string $host the host to resolve
     *
     * @return string
     */
    public static function resolveHostname($host)
    {
        return trim((string)$host);
    }
    /**
     * Authentication is not exercised by the rendering scenarios.
     *
     * @param string $user the username
     * @param string $pass the password
     *
     * @return bool
     */
    public static function authenticateOnly($user, $pass)
    {
        return false;
    }
    /**
     * Task states; only reached on the tasking path.
     *
     * @return array
     */
    public static function getQueuedStates()
    {
        return [1, 2, 3];
    }
    /**
     * Progress state; only reached on the tasking path.
     *
     * @return int
     */
    public static function getProgressState()
    {
        return 3;
    }
    /**
     * Splits a MAC list the way FOGBase does.
     *
     * @param string $macs the list to split
     *
     * @return array
     */
    public static function parseMacList($macs)
    {
        return array_values(array_filter(explode('|', (string)$macs)));
    }
    /**
     * Product-key helpers; only reached on the key-registration path.
     *
     * @param string $key the key to format
     *
     * @return string
     */
    public static function productKeyFormat($key)
    {
        return (string)$key;
    }
    /**
     * Product-key validity; only reached on the key-registration path.
     *
     * @param string $key the key to check
     *
     * @return bool
     */
    public static function productKeyIsValid($key)
    {
        return (bool)$key;
    }
}

/**
 * Storage node, looked up by id from the fixture rows.
 */
class StorageNode extends StubModel
{
    /**
     * Looks the node up by id.
     *
     * @param int $id the node id
     */
    public function __construct($id = 0)
    {
        $found = [];
        foreach ((array)(Route::$rows['storagenode'] ?? []) as $node) {
            if (($node->id ?? null) == $id) {
                $found = (array)$node;
                break;
            }
        }
        parent::__construct($found);
    }
    /**
     * Returns the owning group.
     *
     * @return StubModel
     */
    public function getStorageGroup()
    {
        return new StubModel(['id' => 1, 'name' => 'default']);
    }
}

/**
 * PXE menu option, looked up by id from the fixture rows.
 */
class PXEMenuOptions extends StubModel
{
    /**
     * Looks the row up by id.
     *
     * @param int $id the row id
     */
    public function __construct($id = 0)
    {
        $found = [];
        foreach ((array)(Route::$rows['pxemenuoptions'] ?? []) as $menu) {
            if (($menu->id ?? null) == $id) {
                $found = (array)$menu;
                break;
            }
        }
        parent::__construct($found);
    }
}
