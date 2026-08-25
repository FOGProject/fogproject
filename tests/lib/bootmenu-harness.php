<?php
/**
 * A stub FOG runtime just large enough to render BootMenu's iPXE output.
 *
 * BootMenu extends FOGBase and reaches the database only through a handful
 * of static helpers, so replacing FOGBase replaces the entire world it can
 * see. Nothing here talks to MySQL, the network, or the filesystem outside
 * a caller-supplied BASEPATH, which is what lets the boot menu be rendered
 * byte-for-byte in a test.
 *
 * Fidelity notes -- these are the places where a lazy stub would silently
 * produce the wrong golden file:
 *
 * 1. getSubObjectIDs('Service', ['name' => [...]], 'value', ..., 'name', ...)
 *    returns setting VALUES ORDERED BY SETTING NAME, not in the order the
 *    caller listed them. BootMenu destructures the result positionally with
 *    list(), so the sort is load-bearing: it is the reason $serviceNames and
 *    $ipxeGrabs are written in alphabetical order. The stub sorts the same
 *    way, and throws on an unknown key rather than returning a short array,
 *    because a missing row shifts every subsequent variable by one and would
 *    bake a plausible-looking but wrong golden file.
 * 2. fastmerge() and arrayInsertAfter() are copied verbatim from
 *    FOGBase rather than reimplemented -- fastmerge's numeric-vs-string key
 *    handling decides menu line ordering.
 * 3. resolveHostname() never does DNS. A test that resolves a name is a test
 *    that fails on an airplane.
 *
 * Not a mock framework by design: the house style is a plain PHP script with
 * an exit status, runnable from the pre-commit hook.
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!function_exists('_')) {
    /**
     * gettext stand-in; the real one is unavailable without the web bootstrap.
     *
     * @param string $string the string to translate
     *
     * @return string
     */
    function _($string)
    {
        return $string;
    }
}

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
    protected $data = array();
    /**
     * Instantiates the record.
     *
     * @param array $data the field data
     */
    public function __construct(array $data = array())
    {
        $this->data = $data;
    }
    /**
     * A record is valid when it carries a non-zero id, matching
     * FOGController's own notion of validity closely enough for rendering.
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
}

/**
 * MAC list stub. BootMenu stringifies this in _ipxeLog().
 */
class StubMac extends StubModel
{
    /**
     * Renders the MAC as BootMenu expects.
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
}

/**
 * Storage node stub.
 */
class StubStorageNode extends StubModel
{
    /**
     * Returns the owning group.
     *
     * @return StubModel
     */
    public function getStorageGroup()
    {
        return new StubModel(array('id' => 1, 'name' => 'default'));
    }
}

/**
 * Host stub, with the nested objects BootMenu reaches through.
 */
class StubHost extends StubModel
{
    /**
     * Reads a field, materialising the nested objects BootMenu expects.
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
                    array(
                        'id' => 1,
                        'mac' => $this->data['mac'] ?? '00:11:22:33:44:55',
                        'ignored' => $this->data['imageignored'] ?? false,
                    )
                );
            case 'task':
                return new StubTask((array)($this->data['task'] ?? array()));
            case 'inventory':
                return new StubModel(
                    array('id' => 1, 'sysuuid' => $this->data['sysuuid'] ?? '')
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
        return new StubModel((array)($this->data['image'] ?? array()));
    }
}

/**
 * Collection stub returned by the *Manager classes.
 */
class StubManager
{
    /**
     * The records this manager hands back.
     *
     * @var array
     */
    private $_rows = array();
    /**
     * Instantiates the manager.
     *
     * @param array $rows the records to serve
     */
    public function __construct(array $rows = array())
    {
        $this->_rows = $rows;
    }
    /**
     * Filters the records the way FOGManagerController::find() would for the
     * equality-on-a-field cases BootMenu actually uses. A filter value that
     * is an array means "field IN (...)".
     *
     * @param mixed  $findWhere the filter
     * @param string $whereOp   unused; kept for signature parity
     * @param string $orderBy   field to sort by
     *
     * @return array
     */
    public function find($findWhere = array(), $whereOp = '', $orderBy = '')
    {
        $rows = $this->_rows;
        foreach ((array)$findWhere as $field => $want) {
            $rows = array_values(
                array_filter(
                    $rows,
                    function ($row) use ($field, $want) {
                        $have = $row->get($field);
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
                    return $a->get($orderBy) <=> $b->get($orderBy);
                }
            );
        }
        return $rows;
    }
}

/**
 * Hook manager stub. Fires nothing, so the golden output is the
 * plugin-free baseline, and records what was fired so a test can assert
 * the integration points still exist.
 */
class StubHookManager
{
    /**
     * Events seen, in order, as event name => argument keys.
     *
     * @var array
     */
    public $fired = array();
    /**
     * Records an event without altering its arguments.
     *
     * @param string $event     the event name
     * @param array  $arguments the event arguments
     *
     * @return void
     */
    public function processEvent($event, $arguments = array())
    {
        $scalars = array();
        foreach ((array)$arguments as $key => $value) {
            if (null === $value || is_scalar($value)) {
                $scalars[$key] = $value;
            }
        }
        $this->fired[] = array(
            'event' => $event,
            'args' => array_keys((array)$arguments),
            'scalars' => $scalars,
        );
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
 * The stub FOGBase. BootMenu's entire view of the system is these members.
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
     * Settings, keyed by name, as the globalSettings table would hold them.
     *
     * @var array
     */
    public static $settings = array();
    /**
     * PXE menu rows, as StubModel records.
     *
     * @var array
     */
    public static $menus = array();
    /**
     * Images, as StubModel records.
     *
     * @var array
     */
    public static $images = array();
    /**
     * Storage nodes, as StubStorageNode records.
     *
     * @var array
     */
    public static $storagenodes = array();
    /**
     * Base constructor; nothing to bootstrap.
     */
    public function __construct()
    {
    }
    /**
     * Serves the object-id lookups BootMenu performs.
     *
     * Only the shapes BootMenu actually issues are supported; anything else
     * throws, so a future caller cannot silently receive an empty array and
     * bake a wrong golden file.
     *
     * @param string $object    the class to query
     * @param array  $findWhere the filter
     * @param string $getField  the field to return
     *
     * @throws Exception on an unsupported query or unknown setting
     * @return array
     */
    public static function getSubObjectIDs(
        $object = 'Host',
        $findWhere = array(),
        $getField = 'id',
        $not = false,
        $operator = 'AND',
        $orderBy = 'name',
        $groupBy = false,
        $filter = 'array_unique'
    ) {
        if ('Service' === $object) {
            $names = (array)($findWhere['name'] ?? array());
            /*
             * The real query orders by setting name and returns values.
             * BootMenu destructures positionally, so sorting here is what
             * keeps each value bound to the right variable.
             */
            sort($names);
            $out = array();
            foreach ($names as $name) {
                if (!array_key_exists($name, self::$settings)) {
                    throw new Exception(
                        "harness: no fixture for setting '$name'; add it, "
                        . "because a missing row shifts every later list() "
                        . "variable by one"
                    );
                }
                $out[] = self::$settings[$name];
            }
            return $out;
        }
        if ('StorageNode' === $object) {
            $mgr = new StubManager(self::$storagenodes);
            return array_map(
                function ($n) {
                    return $n->get('id');
                },
                $mgr->find($findWhere)
            );
        }
        if ('PXEMenuOptions' === $object) {
            $mgr = new StubManager(self::$menus);
            return array_map(
                function ($m) {
                    return $m->get('id');
                },
                $mgr->find($findWhere)
            );
        }
        if ('iPXE' === $object || 'MulticastSessionAssociation' === $object) {
            return array();
        }
        throw new Exception("harness: unsupported getSubObjectIDs('$object')");
    }
    /**
     * Reads a setting.
     *
     * @param string $key the setting name
     *
     * @return mixed
     */
    public static function getSetting($key)
    {
        return self::$settings[$key] ?? '';
    }
    /**
     * Factory for the classes BootMenu asks for by name.
     *
     * @param string $class the class name
     *
     * @throws Exception on an unsupported class
     * @return mixed
     */
    public static function getClass($class)
    {
        $args = func_get_args();
        array_shift($args);
        switch ($class) {
            case 'PXEMenuOptionsManager':
                return new StubManager(self::$menus);
            case 'StorageNodeManager':
                return new StubManager(self::$storagenodes);
            case 'ImageManager':
                return new StubManager(self::$images);
            case 'MulticastSessionManager':
                return new StubManager(array());
            case 'KeySequence':
                $seq = trim((string)($args[0] ?? ''));
                if ('' === $seq) {
                    return new StubModel(array());
                }
                return new StubModel(
                    array('id' => 1, 'ascii' => '0x1b', 'name' => $seq)
                );
            case 'iPXE':
                return new StubModel(array('id' => (int)($args[0] ?? 0)));
            case 'Image':
                return new StubModel((array)($args[0] ?? array()));
        }
        throw new Exception("harness: unsupported getClass('$class')");
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
     * @throws Exception
     * @return void
     */
    protected static function arrayInsertAfter(
        $key,
        array &$array,
        $new_key,
        $new_value
    ) {
        if (!is_string($key) && !is_numeric($key)) {
            throw new Exception(_('Key must be a string or index'));
        }
        $new = array();
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
     * Deterministic stand-in: never performs DNS, so the test does not
     * depend on the resolver.
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
     * Login attempts are not exercised by the rendering scenarios.
     *
     * @param string $user the username
     * @param string $pass the password
     *
     * @return StubModel
     */
    public static function attemptLogin($user, $pass)
    {
        return new StubModel(array());
    }
}

/**
 * Concrete stand-ins for the classes BootMenu instantiates with `new`.
 */
class StorageNode extends StubStorageNode
{
    /**
     * Looks the node up by id from the fixture set.
     *
     * @param int $id the node id
     */
    public function __construct($id = 0)
    {
        $found = array();
        foreach (FOGBase::$storagenodes as $node) {
            if ($node->get('id') == $id) {
                $found = $node->get();
                break;
            }
        }
        parent::__construct($found);
    }
}

/**
 * PXE menu option, looked up by id from the fixture set.
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
        $found = array();
        foreach (FOGBase::$menus as $menu) {
            if ($menu->get('id') == $id) {
                $found = $menu->get();
                break;
            }
        }
        parent::__construct($found);
    }
}
