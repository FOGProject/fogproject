<?php
/**
 * FOGBase, the base class for pretty much all of fog.
 *
 * PHP version 5
 *
 * This gives all the rest of the classes a common frame to work from.
 *
 * @category FOGBase
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FOGBase, the base class for pretty much all of fog.
 *
 * @category FOGBase
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGBase
{
    /**
     * Locale
     *
     * @var string
     */
    public static $locale = '';
    /**
     * Ping is active?
     *
     * @var bool
     */
    public static $fogpingactive = false;
    /**
     * Delete auth is active?
     *
     * @var bool
     */
    public static $fogdeleteactive = false;
    /**
     * Export auth is active?
     *
     * @var bool
     */
    public static $fogexportactive = false;
    /**
     * The pending macs count.
     *
     * @var int
     */
    public static $pendingMACs = 0;
    /**
     * The pending hosts count.
     *
     * @var int
     */
    public static $pendingHosts = 0;
    /**
     * Default screen.
     *
     * @var string
     */
    public static $defaultscreen = '';
    /**
     * Plugins installed.
     *
     * @var array
     */
    public static $pluginsinstalled = array();
    /**
     * User agent string.
     *
     * @var string
     */
    public static $useragent;
    /**
     * Language variables brought in from text.php.
     *
     * @var array
     */
    public static $foglang;
    /**
     * Sets if the requesting call is ajax requested.
     *
     * @var bool
     */
    public static $ajax = false;
    /**
     * Sets if this is a form submit.
     *
     * @var bool
     */
    public static $post = false;
    /**
     * Tells whether or not its a fog/service request.
     *
     * @var bool
     */
    public static $service = false;
    /**
     * Tells if we are json or not
     *
     * @var bool
     */
    public static $json = false;
    /**
     * Tells if we are new service or not
     *
     * @var bool
     */
    public static $newService = false;
    /**
     * Tests/sets if a given key is loaded already.
     *
     * @var array
     */
    protected $isLoaded = array();
    /**
     * Tracks which keys a caller has actually written (via set()/add()/
     * remove()), as opposed to keys merely lazy-loaded for reading. See
     * isDirty()'s docblock.
     *
     * @var array
     */
    protected $dirty = array();
    /**
     * The length of a given string item.
     *
     * @var int
     */
    protected static $strlen;
    /**
     * Display debug information.
     *
     * @var bool
     */
    protected static $debug = false;
    /**
     * Display extra information about items.
     *
     * @var bool
     */
    protected static $info = false;
    /**
     * Select box creator function stored in variable.
     *
     * @var callable
     */
    protected static $buildSelectBox;
    /**
     * Sets what's selected for the select box.
     *
     * @var bool|int
     */
    protected static $selected;
    /**
     * The database handler.
     *
     * @var object
     */
    protected static $DB;
    /**
     * FTP Handler.
     *
     * @var object
     */
    protected static $FOGFTP;
    /**
     * Core usage elements as FOGBase is abstract.
     *
     * @var object
     */
    protected static $FOGCore;
    /**
     * Event handling.
     *
     * @var object
     */
    protected static $EventManager;
    /**
     * Hook handling.
     *
     * @var object
     */
    protected static $HookManager;
    /**
     * The default timezone for all of fog to use.
     *
     * @var object
     */
    protected static $TimeZone;
    /**
     * The logged in user.
     *
     * @var object
     */
    protected static $FOGUser;
    /**
     * View/Page Controller-Manager.
     *
     * @var object
     */
    protected static $FOGPageManager;
    /**
     * URL Manager | mainly for ajax, and external getters.
     *
     * @var object
     */
    protected static $FOGURLRequests;
    /**
     * Side/Sub menu manager.
     *
     * @var object
     */
    protected static $FOGSubMenu;
    /**
     * Current requests script name.
     *
     * @var string
     */
    public static $scriptname;
    /**
     * Current requests query string.
     *
     * @var string
     */
    public static $querystring;
    /**
     * Current requests http requested with string.
     *
     * @var string
     */
    public static $httpreqwith;
    /**
     * Current request method.
     *
     * @var string
     */
    public static $reqmethod;
    /**
     * Current remote address.
     *
     * @var string
     */
    public static $remoteaddr;
    /**
     * Current http referer.
     *
     * @var string
     */
    public static $httpreferer;
    /**
     * The current server's IP information.
     *
     * @var array
     */
    protected static $ips = array();
    /**
     * The current server's Interface information.
     *
     * @var array
     */
    protected static $interface = array();
    /**
     * The current base pages requiring search functionality.
     *
     * @var array
     */
    protected static $searchPages = array(
        'user',
        'host',
        'group',
        'image',
        //'storage',
        'snapin',
        'printer',
        'task',
    );
    /**
     * Is our current element already initialized?
     *
     * @var bool
     */
    private static $_initialized = false;
    /**
     * Memoized result of hasFogUsers(). Null until first probed.
     *
     * @var bool|null
     */
    private static $_hasFogUsers = null;
    /**
     * The current running schema information.
     *
     * @var int
     */
    public static $mySchema = 0;
    /**
     * Allows pages to include the main gui or not.
     *
     * @var bool
     */
    public static $showhtml = true;
    /**
     * HTTPS set or not store protocol to use.
     *
     * @var string
     */
    public static $httpproto = false;
    /**
     * HTTP_HOST variable.
     *
     * @var string
     */
    public static $httphost = '';
    /**
     * Hosts are what we work with.
     * To help simplify changing elements using hosts,
     * store as a static variable.
     *
     * @var Host
     */
    public static $Host = null;
    /**
     * Initializes the FOG System if needed.
     *
     * @return void
     */
    private static function _init()
    {
        if (self::$_initialized === true) {
            return;
        }
        global $foglang;
        global $FOGFTP;
        global $FOGCore;
        global $DB;
        global $currentUser;
        global $EventManager;
        global $HookManager;
        global $FOGURLRequests;
        global $FOGPageManager;
        global $TimeZone;
        self::$foglang = &$foglang;
        self::$FOGFTP = &$FOGFTP;
        self::$FOGCore = &$FOGCore;
        self::$DB = &$DB;
        self::$EventManager = &$EventManager;
        self::$HookManager = &$HookManager;
        self::$FOGUser = &$currentUser;
        global $sub;
        $scriptPattern = 'service';
        $queryPattern = 'sub=requestClientInfo';
        self::$querystring = filter_input(INPUT_SERVER, 'QUERY_STRING');
        self::$scriptname = filter_input(INPUT_SERVER, 'SCRIPT_NAME');
        self::$httpreqwith = filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH');
        self::$reqmethod = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
        self::$remoteaddr = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        self::$httpreferer = filter_input(INPUT_SERVER, 'HTTP_REFERER');
        if (false !== stripos(self::$scriptname, $scriptPattern)) {
            self::$service = true;
        } elseif (false !== stripos(self::$querystring, $queryPattern)) {
            self::$service = true;
        }
        self::$ajax = false !== stripos(self::$httpreqwith, 'xmlhttprequest');
        self::$post = false !== stripos(self::$reqmethod, 'post');
        self::$newService = isset($_POST['newService'])
            || isset($_GET['newService'])
            || $sub == 'requestClientInfo';
        self::$json = isset($_POST['json'])
            || isset($_GET['json'])
            || self::$newService
            || $sub == 'requestClientInfo';
        self::$FOGURLRequests = &$FOGURLRequests;
        self::$FOGPageManager = &$FOGPageManager;
        self::$TimeZone = &$TimeZone;
        /*
         * Lambda function to allow building of select boxes.
         *
         * @param string $option the option to iterate
         * @param bool|int $index the index to operate on if needed.
         *
         * @return void
         */
        self::$buildSelectBox = function ($option, $index = false) {
            $value = $option;
            if ($index) {
                $value = $index;
            }
            printf(
                '<option value="%s"%s>%s</option>',
                Initiator::e($value),
                (self::$selected == $value ? ' selected' : ''),
                Initiator::e($option)
            );
        };
        /**
         * Set proto and host.
         */
        self::$httpproto = 'http'
            . (
                filter_input(INPUT_SERVER, 'HTTPS') ?
                's' :
                ''
            );
        self::$httphost = filter_input(INPUT_SERVER, 'HTTP_HOST');
        self::$_initialized = true;
    }
    /**
     * Initiates the base class for FOG.
     *
     * @return this
     */
    public function __construct()
    {
        self::$useragent = self::_getUserAgent();
        self::_init();

        return $this;
    }
    /**
     * Return the user agent.
     *
     * @return string
     */
    private static function _getUserAgent()
    {
        return filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
    }
    /**
     * Defines string as class name.
     *
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }
    /**
     * Returns the class after verifying reflection of the class.
     *
     * @param string $class the name of the class to load
     * @param mixed  $data  the data to load into the class
     * @param bool   $props return just properties or full object
     *
     * @throws Exception
     *
     * @return class Returns the instantiated class
     */
    public static function getClass($class, $data = '', $props = false)
    {
        // Get all args, even unnamed args.
        $args = func_get_args();
        array_shift($args);

        if (!is_string($class)) {
            throw new Exception(_('Class name must be a string'));
        }
        // Trim the class var
        $class = trim($class);

        // Test what the class is and return if it is Reflection.
        $lClass = strtolower($class);
        if ($lClass === 'reflectionclass') {
            return new ReflectionClass(count($args) === 1 ? $args[0] : $args);
        }

        global $sub;
        // If class is Storage, test if sub is group or node.
        if ($class === 'Storage') {
            $class = 'StorageNode';
            if (preg_match('#storage[-|_]group#i', $sub)) {
                $class = 'StorageGroup';
            }
        }

        // Initiate Reflection item.
        $obj = new ReflectionClass($class);

        // If props is set to true return the properties of the class.
        if ($props === true) {
            return $obj->getDefaultProperties();
        }

        // Return the main object
        if ($obj->getConstructor()) {
            // If there's only one argument return the instance using it.
            // Otherwise return with full call.
            if (count($args) === 1) {
                $class = $obj->newInstance($args[0]);
            } else {
                $class = $obj->newInstanceArgs($args);
            }
        } else {
            $class = $obj->newInstanceWithoutConstructor();
        }

        return $class;
    }
    /**
     * Gets the relevant host item.
     *
     * @param bool $service         Is this a service request
     * @param bool $encoded         Is this data encoded
     * @param bool $hostnotrequired Is the host return needed
     * @param bool $returnmacs      Only return macs?
     * @param bool $override        Perform an override of the items?
     * @param bool $mac             Mac Override?
     *
     * @throws Exception
     *
     * @return array|object Returns either th macs or the host
     */
    public static function getHostItem(
        $service = true,
        $encoded = false,
        $hostnotrequired = false,
        $returnmacs = false,
        $override = false,
        $mac = false
    ) {
        self::$Host = new Host(0);
        // Store the mac
        if (!$mac) {
            $mac = filter_input(INPUT_POST, 'mac');
            if (!$mac) {
                $mac = filter_input(INPUT_GET, 'mac');
            }
            if (!$mac) {
                parse_str(
                    file_get_contents('php://input'),
                    $vars
                );
                $mac = $vars['mac'] ?? '';
            }
        }
        // disabling sysuuid detection code for now as it is causing
        // trouble with machines having the same UUID like we've seen
        // on some MSI motherboards having FFFFFFFF-FFFF-FFFF-FFFF...
        /*        $sysuuid = filter_input(INPUT_POST, 'sysuuid');
                if (!$sysuuid) {
                    $sysuuid = filter_input(INPUT_GET, 'sysuuid');
                }
         */
        // Normalize the mac. stripAndDecode() rewrites $_REQUEST, but the mac
        // is read here from the raw request via filter_input() (or passed in
        // explicitly), which that rewrite never touches, so the encoding has
        // to be resolved here. The legacy $encoded flag is now redundant but
        // kept for call-signature compatibility.
        $mac = self::stripAndDecodeMac($mac);
        // See if we can find the host by system uuid rather than by mac's first.
        /*        if ($sysuuid) {
                    $Inventory = self::getClass('Inventory')
                        ->set('sysuuid', $sysuuid)
                        ->load('sysuuid');
                    $Host = self::getClass('Inventory')
                        ->set('sysuuid', $sysuuid)
                        ->load('sysuuid')
                        ->getHost();
                    if ($Host->isValid() && !$returnmacs) {
                        self::$Host = $Host;
                        return;
                    }
                }
         */
        // Trim the mac list.
        $mac = trim($mac);
        // Parsing the macs
        $MACs = self::parseMacList(
            $mac,
            !$service,
            $service
        );
        $macs = array();
        foreach ((array) $MACs as &$mac) {
            if (!$mac->isValid()) {
                continue;
            }
            $macs[] = $mac->__toString();
            unset($mac);
        }
        // Get the host element based on the mac address
        self::getClass('HostManager')->getHostByMacAddresses($macs);
        // If no macs are returned and the host is not required,
        // throw message that it's an invalid mac.
        if (count($macs) < 1 && $hostnotrequired === false) {
            if ($service) {
                $msg = '#!im';
            } else {
                $msg = sprintf(
                    '%s %s',
                    self::$foglang['InvalidMAC'],
                    $mac
                );
            }
            throw new Exception($msg);
        }

        // If returnmacs parameter is true, return the macs as an array
        if ($returnmacs) {
            if (!is_array($macs)) {
                $macs = (array) $macs;
            }

            return $macs;
        }

        if ($hostnotrequired === false && $override === false) {
            if (self::$Host->get('pending')) {
                self::$Host = new Host(0);
            }
            if (!self::$Host->isValid()) {
                if ($service) {
                    $msg = '#!ih';
                } else {
                    $msg = _('Invalid Host');
                }
                throw new Exception($msg);
            }
        }
        return;
    }
    /**
     * Gets blamed nodes for failures.
     *
     * @param Host $Host The host to work with.
     *
     * @return array
     */
    public static function getAllBlamedNodes($Host)
    {
        $DateInterval = self::niceDate()->modify('-5 minutes');
        /**
         * Returns the node id if still accurate
         * or will clean up past time nodes.
         *
         * @param object $NodeFailure the node that is in failed state
         *
         * @return int|bool
         */
        $nodeFail = function ($NodeFailure) use ($DateInterval) {
            if ($NodeFailure->isValid()) {
                return false;
            }
            $DateTime = self::niceDate($NodeFailure->get('failureTime'));
            if ($DateTime < $DateInterval) {
                $NodeFailure->destroy();

                return false;
            }

            return $NodeFailure->get('id');
        };
        $find = array(
            'taskID' => self::$Host->get('task')->get('id'),
            'hostID' => self::$Host->get('id'),
        );
        $nodeRet = array_map(
            $nodeFail,
            (array)self::getClass('NodeFailureManager')->find($find)
        );
        $nodeRet = array_filter($nodeRet);
        $nodeRet = array_unique($nodeRet);
        $nodeRet = array_values($nodeRet);

        return $nodeRet;
    }
    /**
     * Returns array of plugins installed.
     *
     * @return array
     */
    protected static function getActivePlugins()
    {
        $plugins = self::getSubObjectIDs(
            'Plugin',
            array(
                'installed' => 1,
                'state' => 1,
            ),
            'name'
        );

        return array_map('strtolower', (array) $plugins);
    }
    /**
     * Converts our string if needed.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return string
     */
    private static function _setString($txt, $data = array())
    {
        if (count($data)) {
            $data = vsprintf($txt, $data);
        } else {
            $data = $txt;
        }

        return $data;
    }
    /**
     * Prints fatal errors.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    protected static function fatalError($txt, $data = array())
    {
        if (self::$service || self::$ajax) {
            return;
        }
        $data = self::_setString($txt, $data);
        $string = sprintf(
            'FOG FATAL ERROR: %s: %s',
            get_called_class(),
            $data
        );
        printf('<div class="debug debug-error">%s</div>', $string);
    }
    /**
     * Prints error.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    protected static function error($txt, $data = array())
    {
        if ((self::$service || self::$ajax) || !self::$debug) {
            return;
        }
        $data = self::_setString($txt, $data);
        $string = sprintf(
            'FOG ERROR: %s: %s',
            get_called_class(),
            $data
        );
        printf('<div class="debug debug-error">%s</div>', $string);
    }
    /**
     * Prints debug.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    protected static function debug($txt, $data = array())
    {
        if ((self::$service || self::$ajax) || !self::$debug) {
            return;
        }
        $data = self::_setString($txt, $data);
        $string = sprintf(
            'FOG DEBUG: %s: %s',
            get_called_class(),
            $data
        );
        printf('<div class="debug debug-error">%s</div>', $string);
    }
    /**
     * Prints info.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    protected static function info($txt, $data = array())
    {
        if (!self::$info || self::$service || self::$ajax) {
            return;
        }
        $data = self::_setString($txt, $data);
        $string = sprintf(
            'FOG INFO: %s: %s',
            get_called_class(),
            $data
        );
        printf('<div class="debug debug-info">%s</div>', $string);
    }
    /**
     * Sets message banner at top of pages.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    protected static function setMessage($txt, $data = array())
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['FOG_MESSAGES'] = self::_setString($txt, $data);
        }
    }
    /**
     * Gets message banner and prepares to display it.
     *
     * @return string
     */
    protected static function getMessages()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['FOG_MESSAGES'])) {
            $_SESSION['FOG_MESSAGES'] = array();
        }
        $messages = (array) $_SESSION['FOG_MESSAGES'];
        unset($_SESSION['FOG_MESSAGES']);
        // Create a hook in for messages
        if (self::$HookManager instanceof HookManager) {
            self::$HookManager->processEvent(
                'MessageBox',
                array('data' => &$messages)
            );
        }
        /**
         * Lambda that simply prints the messages as passed.
         *
         * @param string $message the message to print
         */
        $print_messages = function ($message) {
            printf('<div class="fog-message-box">%s</div>', $message);
        };
        // Print the messages
        array_map($print_messages, $messages);
        unset($messages);
    }
    /**
     * Redirect pages where/when necessary.
     *
     * @param string $url The url to redirect to
     *
     * @return void
     */
    public static function redirect($url = '')
    {
        if (self::$service) {
            return;
        }
        header('Strict-Transport-Security: "max-age=15768000"');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Robots-Tag: none');
        header('X-Frame-Options: SAMEORIGIN');
        header("Location: $url");
        exit;
    }
    /**
     * Insert before key in array.
     *
     * @param string $key       the key to insert before
     * @param array  $array     the array to modify
     * @param string $new_key   the new key to insert
     * @param mixed  $new_value the value to insert
     *
     * @throws Exception
     * @return void
     */
    protected static function arrayInsertBefore(
        $key,
        array &$array,
        $new_key,
        $new_value
    ) {
        if (!is_string($key)) {
            throw new Exception(_('Key must be a string or index'));
        }
        $new = array();
        foreach ($array as $k => &$value) {
            if ($k === $key) {
                $new[$new_key] = $new_value;
            }
            $new[$k] = $value;
            unset($k, $value);
        }
        $array = $new;
    }
    /**
     * Insert after key in array.
     *
     * @param string $key       the key to insert after
     * @param array  $array     the array to modify
     * @param string $new_key   the new key to insert
     * @param mixed  $new_value the value to insert
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
     * Remove value based on the key from array.
     *
     * @param string|array $key   the key to remove
     * @param array        $array the array to work with
     *
     * @throws Exception
     * @return void
     */
    protected static function arrayRemove($key, array &$array)
    {
        if (!(is_string($key) || is_array($key))) {
            throw new Exception(_('Key must be an array of keys or a string.'));
        }
        if (is_array($key)) {
            foreach ($key as &$k) {
                self::arrayRemove($k, $array);
                unset($k);
            }
        } else {
            foreach ($array as &$value) {
                if (is_array($value)) {
                    self::arrayRemove($key, $value);
                } else {
                    unset($array[$key]);
                }
                unset($value);
            }
        }
    }
    /**
     * Find the key of a needle within the haystack that is an array.
     *
     * @param mixed      $needle     the needle to find
     * @param array      $haystack   the array to search in
     * @param bool|mixed $ignorecase whether to care about case
     *
     * @return key or false
     */
    protected static function arrayFind(
        $needle,
        array $haystack,
        $ignorecase = false
    ) {
        $key = array_search($needle, $haystack);
        if (false !== $key) {
            return $key;
        }
        $cmd = $ignorecase !== false ? 'stripos' : 'strpos';
        foreach ($haystack as $key => &$value) {
            if (false !== $cmd($value, $needle)) {
                return $key;
            }
            unset($value);
        }

        return -1;
    }
    /**
     * Internal recursion guard for the get()/set()/loadItem() lazy-load
     * chain -- NOT a "does this key hold data" predicate.
     *
     * This is a test-and-set: every call marks $key loaded, even one that
     * returns false. That side effect is required so a loadX() method's own
     * set() call doesn't see "not loaded" again, re-trigger loadItem(), and
     * recurse forever. It means a bare `if ($this->isLoaded($key))` used as
     * a standalone precondition -- anywhere the false branch isn't
     * immediately followed, in the same call, by an actual load -- silently
     * poisons the flag for the rest of the request: later code calling
     * get($key) and expecting a real lazy-loaded value instead sees the
     * (now-true) flag, skips the load, and falls back to get()'s default.
     *
     * Use isPopulated($key) for "do I actually have data for this key"
     * checks. On 1.6 this exact confusion made assocSetter() delete every
     * snapin association for a host and insert a phantom snapinID=0 row on
     * each client check-in (FOGProject/fogproject#906).
     *
     * @param string|int $key the key to see if loaded
     *
     * @return bool|string
     */
    protected function isLoaded($key)
    {
        $key = $this->key($key);
        $result = isset($this->isLoaded[$key]) ? true : false;
        $this->isLoaded[$key] = true;

        return $result ? $result : false;
    }
    /**
     * Whether $key currently holds resolved data -- a pure, side-effect-free
     * predicate. Unlike isLoaded() (a recursion guard for the internal
     * lazy-load chain; see its docblock), this is safe to use as a
     * standalone precondition anywhere the caller means "do I have real data
     * for this key", e.g. "only sync this association if the caller actually
     * touched it".
     *
     * Deliberately isset(), not array_key_exists(): get()'s own fallback to
     * '' is gated the same way, so the two must agree or a key explicitly
     * set to null would read as populated here while get() still handed back
     * the '' fallback.
     *
     * @param string|int $key the key to check
     *
     * @return bool
     */
    protected function isPopulated($key)
    {
        $key = $this->key($key);

        return isset($this->data[$key]);
    }
    /**
     * Whether a caller has actually written $key this request -- via
     * set(), add(), or remove() -- as opposed to it merely being
     * lazy-loaded for reading. A pure, side-effect-free predicate.
     *
     * Stricter than isPopulated(): a key can be populated (get() will
     * return real data) without being dirty (nothing asked to change it).
     * Use this instead of isPopulated() wherever the guard means "only do
     * this if the caller actually intended a change" -- e.g. assocSetter(),
     * which otherwise re-runs its DB diff on every save() for any
     * association a request happened to read for display, even when
     * nothing about it changed.
     *
     * set()/add()/remove() mark their key dirty as the last thing they do.
     * loadItem() clears the mark for its own key immediately after
     * dispatching to a loadX() method, since anything set() does purely to
     * cache a lazy load is not a caller-driven change. Because a genuine
     * write always marks dirty *after* any nested loadItem() call it
     * triggers first (both check isLoaded() and lazy-load before
     * mutating), a real change can never be erased by a load that
     * happens to run first in the same call.
     *
     * @param string|int $key the key to check
     *
     * @return bool
     */
    protected function isDirty($key)
    {
        $key = $this->key($key);

        return isset($this->dirty[$key]);
    }
    /**
     * Reduce a value to the positive integer ids it contains.
     *
     * Association writers diff on integer id columns, so a non-positive or
     * non-numeric entry can only ever be junk -- and a falsy scalar casts to
     * array('') which subtracts nothing from the current set and then
     * inserts as id 0.
     *
     * @param mixed $ids the collection (or scalar) to reduce
     *
     * @return array
     */
    public static function positiveIntIds($ids)
    {
        return array_values(
            array_filter(
                array_map('intval', (array)$ids),
                function ($id) {
                    return $id > 0;
                }
            )
        );
    }
    /**
     * Reset request variables.
     *
     * @return void
     */
    protected static function resetRequest()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['post_request_vals'])) {
            $_SESSION['post_request_vals'] = array();
        }
        $sesVars = $_SESSION['post_request_vals'];
        if (isset($sesVars) && count($sesVars) > 0) {
            foreach ($sesVars as $key => $val) {
                $_POST[$key] = $val;
                unset($key, $val);
            }
        }
        unset($_SESSION['post_request_vals'], $sesVars);
    }
    /**
     * Set request vars particularly for post failures really.
     *
     * @return void
     */
    protected function setRequest()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['post_request_vals'])) {
            $_SESSION['post_request_vals'] = array();
        }
        if (!$_SESSION['post_request_vals'] && self::$post) {
            $_SESSION['post_request_vals'] = $_POST;
        }
    }
    /**
     * Return nicely formatted byte sizes.
     *
     * @param int|float $size the size to convert
     *
     * @return float
     */
    protected static function formatByteSize($size)
    {
        $units = array('iB', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
        $factor = floor((strlen($size) - 1) / 3);

        return sprintf('%3.2f %s', $size / pow(1024, $factor), $units[$factor]);
    }
    /**
     * Gets the global module status.
     *
     * Can return just the shortnames or the long.
     *
     * @param bool $names if set will return the services as set
     * @param bool $keys  will return just the shortnames if set
     *
     * @return array
     */
    protected static function getGlobalModuleStatus($names = false, $keys = false)
    {
        // The shortnames are on the left, the long names are on the right
        // If the right is true it means the short is accurate.
        // If the left is not the right caller in form of:
        //     FOG_CLIENT_<name>_ENABLED in lowercase.
        $services = array(
            'autologout' => 'autologoff',
            'clientupdater' => true,
            'dircleanup' => 'directorycleaner',
            'displaymanager' => true,
            'greenfog' => true,
            'hostnamechanger' => true,
            'hostregister' => true,
            'powermanagement' => true,
            'printermanager' => true,
            'snapinclient' => 'snapin',
            'taskreboot' => true,
            'usercleanup' => true,
            'usertracker' => true,
        );
        // If keys is set, return just the keys.
        if ($keys) {
            $keys = array_keys($services);
            $keys = array_filter($keys);
            $keys = array_unique($keys);

            return array_values($keys);
        }
        // Change the keys values
        foreach ($services as $short => &$value) {
            $tmp = $value === true ? $short : $value;
            $value = sprintf('FOG_CLIENT_%s_ENABLED', strtoupper($tmp));
            unset($value);
        }
        // If names is set, send back the short and long names together.
        if ($names) {
            return $services;
        }
        // Now lets get their status'
        $serviceEn = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array_values($services),
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            false
        );

        return array_combine(array_keys($services), $serviceEn);
    }
    /**
     * Sets the date.
     *
     * @param mixed $date The date stamp, defaults to now if not set
     * @param bool  $utc  Whether to use utc timezone or not
     *
     * @return DateTime
     */
    public static function niceDate($date = 'now', $utc = false)
    {
        if ($utc || empty(self::$TimeZone)) {
            $tz = new DateTimeZone('UTC');
        } else {
            $tz = new DateTimeZone(self::$TimeZone);
        }
        try {
            $niceDate = new DateTime($date, $tz);
        } catch (Exception $e) {
            throw new Exception("Given date of '$date' is invalid! Can't create nicedate!");
            $niceDate = $date;
        }
        return $niceDate;
    }
    /**
     * Do formatting things.
     *
     * @param mixed $time   The time to work from
     * @param mixed $format Specified format to return
     * @param bool  $utc    Use UTC Timezone?
     *
     * @return mixed
     */
    public static function formatTime($time, $format = false, $utc = false)
    {
        if (!$time instanceof DateTime) {
            $time = self::niceDate($time, $utc);
        }
        if ($format) {
            if (!self::validDate($time)) {
                return _('No Data');
            }

            return $time->format($format);
        }
        $now = self::niceDate('now', $utc);
        // Get difference of the current to supplied.
        $diff = $now->format('U') - $time->format('U');
        $absolute = abs($diff);
        if (is_nan($diff)) {
            return _('Not a number');
        }
        if (!self::validDate($time)) {
            return _('No Data');
        }
        $date = $time->format('Y/m/d');
        if ($now->format('Y/m/d') == $date) {
            if (0 <= $diff && $absolute < 60) {
                return 'Moments ago';
            } elseif ($diff < 0 && $absolute < 60) {
                return 'Seconds from now';
            } elseif ($absolute < 3600) {
                return self::humanify($diff / 60, 'minute');
            } else {
                return self::humanify($diff / 3600, 'hour');
            }
        }
        $dayAgo = clone $now;
        $dayAgo->modify('-1 day');
        $dayAhead = clone $now;
        $dayAhead->modify('+1 day');
        if ($dayAgo->format('Y/m/d') == $date) {
            return 'Ran Yesterday at '.$time->format('H:i');
        } elseif ($dayAhead->format('Y/m/d') == $date) {
            return 'Runs today at '.$time->format('H:i');
        } elseif ($absolute / 86400 <= 7) {
            return self::humanify($diff / 86400, 'day');
        } elseif ($absolute / 604800 <= 5) {
            return self::humanify($diff / 604800, 'week');
        } elseif ($absolute / 2628000 < 12) {
            return self::humanify($diff / 2628000, 'month');
        }

        return self::humanify($diff / 31536000, 'year');
    }
    /**
     * Checks if the time passed is valid or not.
     *
     * @param mixed $date   the date to use
     * @param mixed $format the format to test
     *
     * @return object
     */
    protected static function validDate($date, $format = '')
    {
        if ($format == 'N') {
            if ($date instanceof DateTime) {
                return $date->format('N') >= 0;
            } else {
                return $date >= 0 && $date <= 7;
            }
        }
        if (!$date instanceof DateTime) {
            $date = self::niceDate($date);
        }
        if (!$format) {
            $format = 'm/d/Y';
        }
        if (empty(self::$TimeZone)) {
            $tz = new DateTimeZone('UTC');
        } else {
            $tz = new DateTimeZone(self::$TimeZone);
        }

        return DateTime::createFromFormat(
            $format,
            $date->format($format),
            $tz
        );
    }
    /**
     * Simply returns if the item should be with an s or not.
     *
     * @param int    $count The count of the element
     * @param string $text  The string to append to
     * @param bool   $space Use a space or not
     *
     * @throws Exception
     *
     * @return string
     */
    protected static function pluralize($count, $text, $space = false)
    {
        if (!is_bool($space)) {
            throw new Exception(_('Space variable must be boolean'));
        }

        return sprintf(
            '%d %s%s%s',
            $count,
            $text,
            $count != 1 ? 's' : '',
            $space === true ? ' ' : ''
        );
    }
    /**
     * Returns the difference given from a start and end time.
     *
     * @param mixed $start the starting date
     * @param mixed $end   the ending date
     * @param bool  $ago   Return immediate highest down
     *
     * @throws Exception
     *
     * @return DateTime
     */
    protected static function diff($start, $end, $ago = false)
    {
        if (!is_bool($ago)) {
            throw new Exception(_('Ago must be boolean'));
        }
        if (!$start instanceof DateTime) {
            $start = self::niceDate($start);
        }
        if (!$end instanceof DateTime) {
            $end = self::niceDate($end);
        }
        $Duration = $start->diff($end);
        $str = '';
        $suffix = '';
        if ($ago === true) {
            $str = '%s %s';
            if ($Duration->invert) {
                $suffix = 'ago';
            }
            if (($v = $Duration->y) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'year'),
                    $suffix
                );
            }
            if (($v = $Duration->m) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'month'),
                    $suffix
                );
            }
            if (($v = $Duration->d) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'day'),
                    $suffix
                );
            }
            if (($v = $Duration->h) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'hour'),
                    $suffix
                );
            }
            if (($v = $Duration->i) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'minute'),
                    $suffix
                );
            }
            if (($v = $Duration->s) > 0) {
                return sprintf(
                    $str,
                    self::pluralize($v, 'second'),
                    $suffix
                );
            }
        }
        if (($v = $Duration->y) > 0) {
            $str .= self::pluralize($v, 'year', true);
        }
        if (($v = $Duration->m) > 0) {
            $str .= self::pluralize($v, 'month', true);
        }
        if (($v = $Duration->d) > 0) {
            $str .= self::pluralize($v, 'day', true);
        }
        if (($v = $Duration->h) > 0) {
            $str .= self::pluralize($v, 'hour', true);
        }
        if (($v = $Duration->i) > 0) {
            $str .= self::pluralize($v, 'minute', true);
        }
        if (($v = $Duration->s) > 0) {
            $str .= self::pluralize($v, 'second');
        }

        return $str;
    }
    /**
     * Return more human friendly time.
     *
     * @param int    $diff the difference passed
     * @param string $unit the unit of time (minute, hour, etc...)
     *
     * @throws Exception
     *
     * @return string
     */
    protected static function humanify($diff, $unit)
    {
        if (!is_numeric($diff)) {
            throw new Exception(_('Diff parameter must be numeric'));
        }
        if (!is_string($unit)) {
            throw new Exception(_('Unit of time must be a string'));
        }
        $before = $after = '';
        if ($diff < 0) {
            $before = sprintf('%s ', _('In'));
        }
        if ($diff < 0) {
            $after = sprintf(' %s', _('ago'));
        }
        $diff = floor(abs($diff));
        if ($diff != 1) {
            $unit .= 's';
        }

        return sprintf(
            '%s%d %s%s',
            $before,
            $diff,
            $unit,
            $after
        );
    }
    /**
     * Changes the keys around as needed.
     *
     * @param array  $array   the array to change key for
     * @param string $old_key the original key
     * @param string $new_key the key to change to
     *
     * @throws Exception
     * @return void
     */
    protected static function arrayChangeKey(array &$array, $old_key, $new_key)
    {
        if (!is_string($old_key)) {
            throw new Exception(_('Old key must be a string'));
        }
        if (!is_string($new_key)) {
            throw new Exception(_('New key must be a string'));
        }
        $array[$old_key] = (
            is_string($array[$old_key]) ?
            trim($array[$old_key]) :
            $array[$old_key]
        );
        if (!self::$service && is_string($array[$old_key])) {
            $item = mb_convert_encoding(
                $array[$old_key],
                'utf-8'
            );
            $array[$new_key] = Initiator::sanitizeItems(
                $item
            );
        } else {
            $array[$new_key] = $array[$old_key];
        }
        if ($old_key != $new_key) {
            unset($array[$old_key]);
        }
    }
    /**
     * Converts to bits.
     *
     * @param int|float $kilobytes the bytes to convert
     *
     * @return float
     */
    protected static function byteconvert($kilobytes)
    {
        return ($kilobytes / 8) * 1024;
    }
    /**
     * Converts hex to binary equivalent.
     *
     * @param mixed $hex The hex to convert.
     *
     * @return string
     */
    protected static function hex2bin($hex)
    {
        if (function_exists('hex2bin')) {
            return hex2bin($hex);
        }
        $n = strlen($hex);
        $i = 0;
        $sbin = '';
        while ($i < $n) {
            $a = substr($hex, $i, 2);
            $sbin .= pack('H*', $a);
            $i += 2;
        }

        return $sbin;
    }
    /**
     * Tells whether a caller may be issued a host token.
     *
     * Aisle 016. status/hostgetkey.php is unauthenticated by necessity -- FOS
     * has no credential during boot -- and identifies the caller only by a MAC,
     * which is not a secret. The token it hands out is the sole gate on
     * service/hostinfo.php, which returns server-decrypted plaintext AD join
     * credentials and the product key. The only signal left to distinguish a
     * booting client from an arbitrary caller is network position.
     *
     * Strict "REMOTE_ADDR must equal the host's recorded ip" cannot be the rule:
     * it breaks a DHCP re-lease between PXE and FOS, a PXE NIC that differs from
     * the OS NIC, a VLAN hop, a relayed DHCP, or a NAT'd storage node. So the
     * policy is admin-declared instead, and DEFAULTS TO EMPTY = no restriction,
     * which is exactly the behaviour before this setting existed. Sites that can
     * state their imaging networks opt in; nobody's upgrade breaks.
     *
     * Accepts a comma/whitespace separated list of IPv4 CIDR ranges and/or
     * literal addresses (v4 or v6).
     *
     * @param string $ip the caller address, normally REMOTE_ADDR
     *
     * @return bool
     */
    public static function hostKeySourceAllowed($ip)
    {
        $allowed = trim((string)self::getSetting('FOG_HOSTKEY_ALLOWED_SOURCES'));
        if ($allowed === '') {
            // Unconfigured: preserve pre-existing behaviour.
            return true;
        }
        $ip = trim((string)$ip);
        if ($ip === '') {
            // A policy is configured but we cannot tell where the caller is.
            return false;
        }
        $entries = preg_split('/[\s,]+/', $allowed, -1, PREG_SPLIT_NO_EMPTY);
        foreach ((array)$entries as $entry) {
            if (strcasecmp($entry, $ip) === 0) {
                return true;
            }
            if (strpos($entry, '/') === false) {
                continue;
            }
            list($subnet, $bits) = explode('/', $entry, 2);
            $subnetLong = ip2long($subnet);
            $ipLong = ip2long($ip);
            // ip2long only speaks IPv4; a v6 caller falls through to the exact
            // match above rather than being silently accepted by a v4 range.
            if ($subnetLong === false || $ipLong === false) {
                continue;
            }
            $bits = (int)$bits;
            if ($bits < 0 || $bits > 32) {
                continue;
            }
            if ($bits === 0) {
                return true;
            }
            $mask = -1 << (32 - $bits);
            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Create security token.
     *
     * @return string
     */
    public static function createSecToken()
    {
        if (function_exists('random_bytes')) {
            $token = bin2hex(
                random_bytes(64)
            );
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $token = bin2hex(
                openssl_random_pseudo_bytes(
                    64
                )
            );
        }
        return $token;
    }
    /**
     * AES Encrypt function.
     *
     * @param mixed  $data    the item to encrypt
     * @param string $key     the key to use if false will generate own
     * @param int    $enctype the type of encryption to use
     *
     * @return string
     */
    public static function aesencrypt(
        $data,
        $key = false,
        $enctype = 'aes-256-cbc'
    ) {
        if (!$data) {
            echo json_encode(
                array(
                    'error' => _('Data is blank')
                )
            );
            exit;
        }
        $iv_size = openssl_cipher_iv_length($enctype);
        $key = self::hex2bin($key);
        if (mb_strlen($key, '8bit') !== ($iv_size * 2)) {
            echo json_encode(
                array(
                    'error' => _('Needs a 256-bit key')
                )
            );
            exit;
        }
        $iv = openssl_random_pseudo_bytes($iv_size, $cstrong);

        // Pad the plaintext
        if (strlen($data) % $iv_size) {
            $data = str_pad(
                $data,
                ((strlen($data) + $iv_size) - (strlen($data) % $iv_size)),
                "\0"
            );
        }

        $cipher = openssl_encrypt(
            $data,
            $enctype,
            $key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
            $iv
        );
        if (!$cipher) {
            echo json_encode(
                array(
                    'error' => openssl_error_string()
                )
            );
            exit;
        }
        $iv = bin2hex($iv);
        $cipher = bin2hex($cipher);
        return sprintf(
            '%s|%s',
            $iv,
            $cipher
        );
    }
    /**
     * AES Decrypt function.
     *
     * @param mixed  $encdata the item to decrypt
     * @param string $key     the key to use
     * @param int    $enctype the type of encryption to use
     * @param int    $mode    the mode of encryption
     *
     * @return string
     */
    public static function aesdecrypt(
        $encdata,
        $key = false,
        $enctype = 'aes-128-cbc'
    ) {
        $iv_size = openssl_cipher_iv_length($enctype) * 2;
        if (false === strpos($encdata, '|')) {
            return $encdata;
        }
        $data = explode('|', $encdata);
        if (strlen($data[0]) != $iv_size || strlen($data[1]) != $iv_size) {
            return $encdata;
        }
        // add error handler to catch warnings we might get from pack() with non-hex strings
        set_error_handler(
            function ($severity, $message, $file, $line) {
                throw new ErrorException($message, $severity, $severity, $file, $line);
            }
        );
        try {
            $iv = pack('H*', $data[0]);
            $encoded = pack('H*', $data[1]);
            if (!$key && isset($data[2]) && strlen($data[2]) == $iv_size) {
                $key = pack('H*', $data[2]);
            }
        } catch (Exception $e) {
            return $encdata;
        }
        restore_error_handler();
        if (empty($key)) {
            return '';
        }
        $decipher = openssl_decrypt(
            $encoded,
            $enctype,
            $key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
            $iv
        );
        if (!$decipher) {
            echo json_encode(
                array(
                    'error' => openssl_error_string()
                )
            );
            exit;
        }

        return trim($decipher);
    }
    /**
     * Encrypts the data using the host information.
     * Really just an alias to aesencrypt for now.
     *
     * @param mixed $data the data to encrypt
     *
     * @throws Exception
     *
     * @return string
     */
    protected static function certEncrypt($data)
    {
        if (!self::$Host->isValid()) {
            throw new Exception('#!ih');
        }
        if (!self::$Host->get('pub_key')) {
            throw new Exception('#!ihc');
        }
        return self::aesencrypt($data, self::$Host->get('pub_key'));
    }
    /**
     * Decrypts the information passed.
     *
     * @param mixed $dataArr the data to decrypt
     * @param bool  $padding to use padding or not
     *
     * @throws Exception
     *
     * @return mixed
     */
    protected static function certDecrypt($dataArr, $padding = true)
    {
        if ($padding) {
            $padding = OPENSSL_PKCS1_PADDING;
        } else {
            $padding = OPENSSL_NO_PADDING;
        }
        $tmpssl = array();
        $sslfile = self::getSubObjectIDs('StorageNode', '', 'sslpath');
        foreach ($sslfile as &$path) {
            if (!file_exists($path) || !is_readable($path)) {
                continue;
            }
            $tmpssl[] = $path;
            unset($path);
        }
        if (count($tmpssl) < 1) {
            throw new Exception(_('Private key path not found'));
        }
        $sslfile = sprintf(
            '%s%s.srvprivate.key',
            str_replace(
                array('\\', '/'),
                array(
                    DS,
                    DS
                ),
                $tmpssl[0]
            ),
            DS
        );
        unset($tmpssl);
        if (!file_exists($sslfile)) {
            throw new Exception(_('Private key not found'));
        }
        if (!is_readable($sslfile)) {
            throw new Exception(_('Private key not readable'));
        }
        $sslfilecontents = file_get_contents($sslfile);
        $priv_key = openssl_pkey_get_private($sslfilecontents);
        if (!$priv_key) {
            throw new Exception(_('Private key failed'));
        }
        $a_key = openssl_pkey_get_details($priv_key);
        $chunkSize = ceil($a_key['bits'] / 8);
        $output = array();
        foreach ((array) $dataArr as &$data) {
            $dataun = '';
            while ($data) {
                $data = self::hex2bin($data);
                $chunk = substr($data, 0, $chunkSize);
                $data = substr($data, $chunkSize);
                $decrypt = '';
                $test = openssl_private_decrypt(
                    $chunk,
                    $decrypt,
                    $priv_key,
                    $padding
                );
                if (!$test) {
                    throw new Exception(_('Failed to decrypt data on server'));
                }
                $dataun .= $decrypt;
            }
            unset($data);
            $output[] = $dataun;
        }
        openssl_free_key($priv_key);

        return (array) $output;
    }
    /**
     * Cycle the macs and return valid.
     *
     * @param string|array $stringlist the macs to parse
     * @param bool         $image      check if image type ignored
     * @param bool         $client     check if client type ignored
     *
     * @return array
     */
    public static function parseMacList(
        $stringlist,
        $image = false,
        $client = false
    ) {
        $MAClist = array();
        $MACs = $stringlist;
        $lowerAndTrim = function ($element) {
            return strtolower(trim($element));
        };
        if (!is_array($stringlist)) {
            $MACs = array_map($lowerAndTrim, explode('|', $stringlist));
        } else {
            $MACs = array_map($lowerAndTrim, $stringlist);
        }
        $MACs = array_filter($MACs);
        $MACs = array_unique($MACs);
        $MACs = array_values($MACs);
        if (count($MACs) < 1) {
            return array();
        }
        $pending_filter = explode(
            ',',
            self::getSetting('FOG_QUICKREG_PENDING_MAC_FILTER')
        );
        $Ignore = array_map($lowerAndTrim, $pending_filter);
        $Ignore = array_filter($Ignore);
        if (count($Ignore) > 0) {
            $pattern = sprintf(
                '#%s#i',
                implode('|', (array) $Ignore)
            );
            $found_macs = preg_grep($pattern, $MACs);
            $MACs = array_diff($MACs, $found_macs);
            $MACs = array_filter($MACs);
            $MACs = array_unique($MACs);
            $MACs = array_values($MACs);
        }
        if (count($MACs) < 1) {
            return array();
        }
        $count = self::getClass('MACAddressAssociationManager')->count(
            array(
                'mac' => $MACs,
                'pending' => array(0, ''),
            )
        );
        if ($count > 0) {
            $existingMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'mac' => $MACs,
                    'pending' => array(0, ''),
                ),
                'mac'
            );
            $existingMACs = array_map($lowerAndTrim, $existingMACs);
            $existingMACs = array_filter($existingMACs);
            $existingMACs = array_unique($existingMACs);
            $existingMACs = array_values($existingMACs);
            $MACs = self::fastmerge((array) $MACs, (array) $existingMACs);
            $MACs = array_unique($MACs);
        }
        if ($client) {
            $clientIgnored = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'mac' => $MACs,
                    'clientIgnore' => 1,
                ),
                'mac'
            );
            $clientIgnored = array_map($lowerAndTrim, $clientIgnored);
            $MACs = array_diff((array) $MACs, (array) $clientIgnored);
            unset($clientIgnored);
        }
        if ($image) {
            $imageIgnored = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'mac' => $MACs,
                    'imageIgnore' => 1,
                ),
                'mac'
            );
            $imageIgnored = array_map($lowerAndTrim, (array) $imageIgnored);
            $MACs = array_diff((array) $MACs, (array) $imageIgnored);
            unset($imageIgnored);
        }
        $MACs = array_filter($MACs);
        $MACs = array_unique($MACs);
        $MACs = array_values($MACs);
        if (count($MACs) < 1) {
            return array();
        }
        $validMACs = array();
        foreach ($MACs as &$MAC) {
            $MAC = self::getClass('MACAddress', $MAC);
            if (!$MAC->isValid()) {
                continue;
            }
            $validMACs[] = $MAC;
            unset($MAC);
        }
        $validMACs = array_filter($validMACs);

        return $validMACs;
    }
    /**
     * Prints the data encrypted as needed.
     *
     * @param string $datatosend the data to send
     * @param bool   $service    if not a service simply return
     * @param array  $array      The non-encoded array data.
     *
     * @return string
     */
    protected function sendData(
        $datatosend,
        $service = true,
        $array = array()
    ) {
        global $sub;
        if (false === $service) {
            return;
        }
        try {
            if (!self::$Host->isValid()) {
                throw new Exception('#!ih');
            }
            $datatosend = trim($datatosend);
            $curdate = self::niceDate();
            $secdate = self::niceDate(self::$Host->get('sec_time'));
            if ($curdate >= $secdate) {
                self::$Host
                    ->set('pub_key', '')
                    ->save()
                    ->load();
                if (self::$newService || self::$json) {
                    throw new Exception('#!ihc');
                }
            }
            if (self::$newService) {
                printf(
                    '#!enkey=%s',
                    self::certEncrypt($datatosend)
                );
                exit;
            } else {
                echo $datatosend;
                exit;
            }
        } catch (Exception $e) {
            if (self::$json) {
                if ($e->getMessage() === '#!ihc') {
                    echo $e->getMessage();
                    exit;
                }
                $repData = str_replace('#!', '', $e->getMessage());
                $array['error'] = $repData;
                $data = array('error' => $repData);
                if ($sub === 'requestClientInfo') {
                    echo json_encode($array);
                    exit;
                } else {
                    return $data;
                }
            }
            throw new Exception($e->getMessage());
        }
    }
    /**
     * Checks if an array of needles is found in the main array.
     *
     * @param array $haystack the array to search
     * @param array $needles  the items to test for
     * @param bool  $case     whether to be case insensitive
     *
     * @return bool
     */
    protected static function arrayStrpos($haystack, $needles, $case = true)
    {
        $cmd = sprintf('str%spos', ($case ? 'i' : ''));
        $mapinfo = array();
        foreach ((array) $needles as &$needle) {
            $mapinfo[] = $cmd($haystack, $needle);
            unset($needle);
        }
        $mapinfo = array_filter($mapinfo);

        return count($mapinfo) > 0;
    }
    /**
     * How to log this file.
     *
     * @param string $txt     The text to log.
     * @param int    $curlog  The logLevel setting.
     * @param int    $logfile The logToFile setting.
     * @param int    $logbrow The logToBrowser setting.
     * @param object $obj     The object.
     * @param int    $level   The basic log level.
     *
     * @return void
     */
    protected static function log(
        $txt,
        $curlog,
        $logfile,
        $logbrow,
        $obj,
        $level = 1
    ) {
        if (!is_string($txt)) {
            throw new Exception(_('Txt must be a string'));
        }
        if (!is_int($level)) {
            throw new Exception(_('Level must be an integer'));
        }
        if (self::$ajax) {
            return;
        }
        $findStr = array("\r", "\n", "\t", ' ,');
        $repStr = array('', ' ', ' ', ',');
        $txt = str_replace($findStr, $repStr, $txt);
        $txt = trim($txt);
        if (empty($txt)) {
            return;
        }
        $txt = sprintf('[%s] %s', self::niceDate()->format('Y-m-d H:i:s'), $txt);
        if ($curlog >= $level) {
            echo $txt;
        }
        self::logHistory($txt);
    }
    /**
     * Log to history table.
     *
     * @param string $string the string to store
     *
     * @return void
     */
    protected static function logHistory($string)
    {
        if (!is_string($string)) {
            throw new Exception(_('String must be a string'));
        }
        $string = sprintf(
            '[%s] %s',
            self::niceDate()->format('Y-m-d H:i:s'),
            $string
        );
        $string = trim($string);
        if (!$string) {
            return;
        }
        $name = (
            self::$FOGUser->isValid() ?
            self::$FOGUser->get('name') :
            'fog'
        );
        if (!self::$FOGUser->isValid()) {
            return;
        }
        if (self::$DB) {
            self::getClass('History')
                ->set('info', $string)
                ->set('ip', self::$remoteaddr)
                ->save();
        }
    }
    /**
     * Sets the order by element of sql.
     *
     * @param string $orderBy the string to order by
     *
     * @return void
     */
    public function orderBy(&$orderBy)
    {
        if (empty($orderBy)) {
            $orderBy = 'name';
            if (!array_key_exists($orderBy, $this->databaseFields)) {
                $orderBy = 'id';
            }
        } else {
            if (!is_array($orderBy)) {
                $orderBy = trim($orderBy);
                if (!array_key_exists($orderBy, $this->databaseFields)) {
                    $orderBy = 'name';
                }
                if (!array_key_exists($orderBy, $this->databaseFields)) {
                    $orderBy = 'id';
                }
            }
        }
    }
    /**
     * Gets the object ids only.
     *
     * @param string $object    The object to use
     * @param array  $findWhere How to find the elements we need
     * @param string $getField  The field value to return
     * @param mixed  $not       DB to search with not or no not
     * @param string $operator  How to join strings (And or Or)
     * @param mixed  $orderBy   Order the return by
     * @param mixed  $groupBy   Group the return by
     * @param string $filter    How to filter the data returning
     *
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
        if (empty($object)) {
            $object = 'Host';
        }
        if (empty($getField)) {
            $getField = 'id';
        }
        if (empty($operator)) {
            $operator = 'AND';
        }
        if (is_array($getField)) {
            foreach ((array)$getField as &$field) {
                $data[$field] = self::getSubObjectIDs(
                    $object,
                    $findWhere,
                    $field,
                    $not,
                    $operator,
                    $orderBy,
                    $groupBy,
                    $filter
                );
                unset($field);
            }
            return $data;
        }
        return self::getClass($object)->getManager()->find(
            $findWhere,
            $operator,
            $orderBy,
            '',
            '',
            $groupBy,
            $not,
            $getField,
            '',
            $filter
        );
    }
    /**
     * Get global setting value by key.
     *
     * @param string $key What to get
     *
     * @throws Exception
     *
     * @return string
     */
    public static function getSetting($key)
    {
        if (!is_string($key)) {
            throw new Exception(_('Key must be a string'));
        }
        $findStr = '\r\n';
        $repStr = "\n";
        $value = self::getClass('Service')
            ->set('name', $key)
            ->load('name')
            ->get('value');

        return trim(
            str_replace(
                $findStr,
                $repStr,
                $value
            )
        );
    }
    /**
     * Set global setting value by key.
     *
     * @param string $key   What to set
     * @param string $value Value to set
     *
     * @throws Exception
     *
     * @return this
     */
    public static function setSetting($key, $value)
    {
        self::getClass('ServiceManager')->update(
            array('name' => $key),
            '',
            array('value' => trim($value))
        );
    }
    /**
     * Gets queued state ids.
     *
     * @return array
     */
    public static function getQueuedStates()
    {
        return (array)TaskState::getQueuedStates();
    }
    /**
     * Get queued state main id.
     *
     * @return int
     */
    public static function getQueuedState()
    {
        return TaskState::getQueuedState();
    }
    /**
     * Get checked in state id.
     *
     * @return int
     */
    public static function getCheckedInState()
    {
        return TaskState::getCheckedInState();
    }
    /**
     * Get in progress state id.
     *
     * @return int
     */
    public static function getProgressState()
    {
        return TaskState::getProgressState();
    }
    /**
     * Get complete state id.
     *
     * @return int
     */
    public static function getCompleteState()
    {
        return TaskState::getCompleteState();
    }
    /**
     * Get cancelled state id.
     *
     * @return int
     */
    public static function getCancelledState()
    {
        return TaskState::getCancelledState();
    }
    /**
     * Safe min() over a collection that may be empty.
     *
     * PHP 8's min()/max() throw an uncaught ValueError on an empty array
     * (the @ operator does not suppress it, and it is an Error not an
     * Exception so surrounding try/catch blocks miss it). Use these
     * wrappers wherever the source collection can legitimately be empty.
     *
     * @param mixed $ids the collection (or scalar) to reduce
     *
     * @return mixed the minimum value, or 0 when empty
     */
    public static function minId($ids)
    {
        $ids = (array)$ids;
        return empty($ids) ? 0 : min($ids);
    }
    /**
     * Safe max() over a collection that may be empty.
     *
     * @param mixed $ids the collection (or scalar) to reduce
     *
     * @return mixed the maximum value, or 0 when empty
     *
     * @see self::minId()
     */
    public static function maxId($ids)
    {
        $ids = (array)$ids;
        return empty($ids) ? 0 : max($ids);
    }
    /**
     * Put string between two strings.
     *
     * @param string $string the string to insert
     * @param string $start  the string to place after
     * @param string $end    the string to place before
     *
     * @return string
     */
    public static function stringBetween($string, $start, $end)
    {
        $string = " $string";
        $ini = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;

        return substr($string, $ini, $len);
    }
    /**
     * Strips and decodes items.
     *
     * @param mixed $item the item to strip and decode
     *
     * @return mixed
     */
    public static function stripAndDecode(&$item)
    {
        foreach ((array) $item as $key => &$val) {
            $tmp = str_replace(' ', '+', $val);
            $tmp = base64_decode($tmp);
            $tmp = trim($tmp);
            if (mb_detect_encoding($tmp, 'utf-8', true)) {
                $val = Initiator::e($tmp);
            }
            unset($tmp);
            $item[$key] = trim($val);
            unset($val);
        }

        return $item;
    }
    /**
     * Strips and decodes a mac, or a '|' separated list of macs.
     *
     * FOS base64-encodes the mac on some paths (registration, deploy) and
     * sends it plain on others (checkin, the standalone inventory task), so
     * the encoding has to be sniffed. The sniff cannot be the one
     * stripAndDecode() uses -- "do the decoded bytes happen to be valid
     * UTF-8" -- because a hex mac is built entirely out of base64 alphabet
     * characters, so a plain mac decodes to accidentally-valid UTF-8 roughly
     * once in every few hundred (measured: 0.26% lowercase, 0.84% upper) and
     * that host would then silently fail to resolve, intermittently and per
     * mac. Sniff on shape instead: keep the plain value when it is already a
     * well formed mac list, and accept the decoded value only when it is one.
     *
     * @param mixed $mac the raw mac value
     *
     * @return string
     */
    public static function stripAndDecodeMac($mac)
    {
        $mac = trim((string) ($mac ?? ''));
        if ($mac === '' || self::isMacList($mac)) {
            return Initiator::e($mac);
        }
        $decoded = trim(base64_decode(str_replace(' ', '+', $mac)));
        if (self::isMacList($decoded)) {
            return Initiator::e($decoded);
        }

        // Neither shape matched; hand back the plain value so the caller
        // reports the mac it was actually sent.
        return Initiator::e($mac);
    }
    /**
     * Tests whether a string is a '|' separated list of mac addresses.
     *
     * @param string $macs the string to test
     *
     * @return bool
     */
    private static function isMacList($macs)
    {
        $parts = array_filter(
            array_map(
                'trim',
                explode('|', $macs)
            )
        );
        if (count($parts) < 1) {
            return false;
        }
        foreach ($parts as $part) {
            if (!preg_match(MACAddress::PATTERN, $part)) {
                return false;
            }
        }

        return true;
    }
    /**
     * Gets the master interface based on the ip found.
     *
     * @param string $ip_find the interface ip's to find
     *
     * @return string
     */
    public static function getMasterInterface($ip_find)
    {
        if (count(self::$interface) > 0) {
            return self::$interface;
        }
        self::getIPAddress();
        exec(
            "/sbin/ip route | grep '$ip_find' | awk -F'[ /]+' '/kernel.*src/ {print $4}'",
            $Interfaces,
            $retVal
        );
        $ip_find = trim($ip_find);
        if (!$ip_find) {
            return;
        }
        self::$interface = array();
        $index = 0;
        foreach ((array) self::$ips as &$ip) {
            $ip = trim($ip);
            if ($ip_find !== $ip) {
                continue;
            }
            self::$interface[] = $Interfaces[$index++];
            unset($ip);
        }
        if (count(self::$interface) < 1) {
            return false;
        }

        return array_shift(self::$interface);
    }
    /**
     * Get IP Addresses of the server.
     *
     * @return array
     */
    protected static function getIPAddress($force = false)
    {
        if (!$force && count(self::$ips) > 0) {
            return self::$ips;
        }
        $output = array();
        exec(
            "/sbin/ip -4 addr | awk -F'[ /]+' '/global/ {print $3}'",
            $IPs,
            $retVal
        );
        if (!count($IPs)) {
            exec(
                "/sbin/ifconfig -a | awk -F'[ /:]+' '/(cast)/ {print $4}'",
                $IPs,
                $retVal
            );
        }
        natcasesort($IPs);
        $retIPs = array();
        $Names = array();
        foreach ($IPs as $IP) {
            $IP = trim($IP);
            if (!filter_var($IP, FILTER_VALIDATE_IP)) {
                array_push($Names, $IP);
                $IP = gethostbyname($IP);
                if (filter_var($IP, FILTER_VALIDATE_IP)) {
                    array_push($retIPs, $IP);
                }
            } else {
                array_push($retIPs, $IP);
                array_push($Names, gethostbyaddr($IP));
            }
        }
        $IPs = $retIPs;
        $output = self::fastmerge(
            $IPs,
            $Names,
            array('127.0.0.1', '127.0.1.1')
        );
        unset($IPs, $Names);
        natcasesort($output);
        self::$ips = array_values(array_filter(array_unique((array) $output)));

        return self::$ips;
    }
    /**
     * Returns the last error.
     *
     * @return string
     */
    public static function lasterror()
    {
        $error = error_get_last();

        return sprintf(
            '%s: %s, %s: %s, %s: %s, %s: %s',
            _('Type'),
            $error['type'],
            _('File'),
            $error['file'],
            _('Line'),
            $error['line'],
            _('Message'),
            $error['message']
        );
    }
    /**
     * Gets the filesize in a non-arch dependent way.
     *
     * @param string $file the file to get size of
     *
     * @return string|int|float
     */
    public static function getFilesize($path)
    {
        $size = 0;
        if (is_dir($path)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if ($file->getFilename() != "." && $file->getFilename() != "..") {
                    $size += filesize($file);
                }
            }
        } else {
            $size = filesize($path);
        }
        return is_numeric($size) ? $size : 0;
    }
    /**
     * Returns the shared inter-node secret, lazily generating it if absent.
     *
     * Storage nodes read the master's globalSettings (shared DB), so this
     * value is common to all nodes and is used to authenticate server-to-server
     * requests that cannot carry a user session -- e.g. the Wake-on-LAN relay
     * (see wakeUp()/FOGPage::wakeEmUp()).
     *
     * @return string
     */
    public static function nodeSecret()
    {
        $secret = self::getSetting('FOG_NODE_SECRET');
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
            self::getClass('Service')
                ->set('name', 'FOG_NODE_SECRET')
                ->set('description', 'Auto-generated shared secret used to authenticate inter-node requests (e.g. the Wake-on-LAN relay). Do not edit.')
                ->set('value', $secret)
                ->set('category', 'FOG Boot Settings')
                ->save();
        }
        return $secret;
    }
    /**
     * Perform enmass wake on lan.
     *
     * @param array $macs The macs to send
     *
     * @return void
     */
    public static function wakeUp($macs)
    {
        if (!is_array($macs)) {
            $macs = array($macs);
        }
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        $macs = self::parseMacList($macs);
        if (count($macs) < 1) {
            return;
        }
        $macStr = implode(
            '|',
            $macs
        );
        $macStr = trim($macStr);
        if (empty($macStr)) {
            return;
        }
        $url = '%s://%s/fog/management/index.php?';
        $url .= 'node=client&sub=wakeEmUp';
        $nodeURLs = array();
        $macCount = count($macs);
        if ($macCount < 1) {
            return;
        }
        foreach ((array)self::getClass('StorageNodeManager')
            ->find(
                array('isEnabled' => 1)
            ) as &$Node
        ) {
            $ip = $Node->get('ip');
            $nodeURLs[] = sprintf(
                $url,
                'https',
                $ip
            );
            $nodeURLs[] = sprintf(
                $url,
                'http',
                $ip
            );
            unset($Node);
        }
        list(
            $gHost
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_WEB_HOST'
                ),
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $ip = $gHost;
        $nodeURLs[] = $ip;
        $ret = self::$FOGURLRequests->process(
            $nodeURLs,
            'POST',
            array('mac' => $macStr),
            false,
            false,
            false,
            false,
            false,
            array('X-FOG-Node-Secret: ' . self::nodeSecret())
        );
    }
    /**
     * Faster array merge operation.
     *
     * @param array $array1 The array to merge with.
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
     * Returns hash of passed file.
     *
     * @param string $file The file to get hash of.
     *
     * @return string
     */
    public static function getHash($file)
    {
        $filesize = self::getFilesize($file);
        $fp = fopen($file, 'r');
        if ($fp) {
            $data = fread($fp, 10485760);
            if ($filesize >= 20971520) {
                fseek($fp, -10485760, SEEK_END);
                $data .= fread($fp, 10485760);
            }
            fclose($fp);
        }
        return isset($data) ? hash('sha256', $data) : '';
    }
    /**
     * Attempts to login
     *
     * @param string $username the username to attempt
     * @param string $password the password to attempt
     *
     * @return object
     */
    public static function attemptLogin($username, $password)
    {
        return self::getClass('User')
            ->validatePw($username, $password);
    }
    /**
     * Clears the mac lookup table
     *
     * @return bool
     */
    public static function clearMACLookupTable()
    {
        $OUITable = self::getClass('OUI', '', true);
        $OUITable = $OUITable['databaseTable'];
        return self::$DB->query("TRUNCATE TABLE `$OUITable`");
    }
    /**
     * Returns the count of mac lookups
     *
     * @return int
     */
    public static function getMACLookupCount()
    {
        return self::getClass('OUIManager')->count();
    }
    /**
     * Resolves a hostname to its IP address
     *
     * @param string $host the item to test
     *
     * @return string
     */
    public static function resolveHostname($host)
    {
        $host = trim($host);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $host = gethostbyname($host);
        $host = trim($host);
        return $host;
    }
    /**
     * Gets the broadcast address of the server
     *
     * @return array
     */
    public static function getBroadcast()
    {
        $output = array();
        $cmd = sprintf(
            '%s | %s | %s',
            '/sbin/ip -4 addr',
            "awk -F'[ /]+' '/global/ {print $6}'",
            "grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'"
        );
        exec($cmd, $IPs, $retVal);
        if (!count($IPs)) {
            $cmd = sprintf(
                '%s | %s | %s | %s',
                '/sbin/ifconfig -a',
                "awk '/(cast)/ {print $3}'",
                "cut -d':' -f2",
                "grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'"
            );
            exec($cmd, $IPs, $retVal);
        }
        $IPs = array_map('trim', (array)$IPs);
        $IPs = array_filter($IPs);
        $IPs = array_values($IPs);
        return $IPs;
    }
    /**
     * Wait a random interval between 1/2 second to 2 seconds.
     *
     * @return void
     */
    public static function randWait()
    {
        usleep(
            rand(
                5000,
                2000000
            )
        );
    }
    /**
     * Tests if the item is an associative array
     *
     * @param $arr The item to test
     * @return bool
     */
    public static function is_assoc_array($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    /**
     * Tests if the item is an array of associative arrays
     *
     * @param $arr The item to test
     * @return bool
     */
    public static function is_array_of_assoc_arrays($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        foreach ($arr as $item) {
            if (!self::is_assoc_array($item)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Many endpoints may need both checks so helper to just do it.
     *
     * @return void
     */
    public static function checkAuthAndCSRF()
    {
        self::is_authorized();
        CSRF::requireForStateChanging();
    }
    /**
     * Validates the per-install schema bootstrap token.
     *
     * The schema deploy endpoint must run before any user/session or database
     * exists (fresh install), so it cannot pass is_authorized()/CSRF. Instead
     * the installer generates a random token, writes it to config.class.php as
     * FOG_SCHEMA_INSTALL_TOKEN, and presents it back (X-Fog-Install-Token
     * header, or fogtoken POST/GET param). Only a caller holding that secret
     * may run schema operations without a logged-in session.
     *
     * @return bool
     */
    public static function validInstallToken()
    {
        return self::installTokenHeader() || self::installTokenParam();
    }
    /**
     * Compares a candidate against FOG_SCHEMA_INSTALL_TOKEN in constant time.
     *
     * @param string|null $provided The value presented by the caller.
     *
     * @return bool
     */
    private static function _matchesInstallToken($provided)
    {
        if (!defined('FOG_SCHEMA_INSTALL_TOKEN') || !FOG_SCHEMA_INSTALL_TOKEN) {
            return false;
        }
        return is_string($provided)
            && $provided !== ''
            && hash_equals((string)FOG_SCHEMA_INSTALL_TOKEN, $provided);
    }
    /**
     * The install token presented as a request header.
     *
     * This is the installer's own channel. A header cannot be set by a
     * cross-site form, a link or an <img>, and it never lands in browser
     * history, a bookmark or a Referer -- so it carries no CSRF exposure and
     * no leak surface, and stays valid on fresh installs and upgrades alike.
     * The installer's non-interactive update runs on upgrades too, where users
     * already exist, so this channel must not be gated on install state.
     *
     * @return bool
     */
    public static function installTokenHeader()
    {
        return self::_matchesInstallToken(
            $_SERVER['HTTP_X_FOG_INSTALL_TOKEN'] ?? null
        );
    }
    /**
     * The install token presented as a GET/POST parameter.
     *
     * This is the leaky copy: it is printed to the installer's stdout, ends up
     * in the tee'd install log, and reaches browser history, bookmarks and
     * access logs. Callers must additionally require schemaNeedsDeploy(), which
     * makes it self-expiring -- the deploy it authorizes brings the schema up
     * to date, after which this channel is permanently closed.
     *
     * @return bool
     */
    public static function installTokenParam()
    {
        return self::_matchesInstallToken(
            filter_input(INPUT_POST, 'fogtoken')
            ?? filter_input(INPUT_GET, 'fogtoken')
        );
    }
    /**
     * Is there actually a schema deploy outstanding?
     *
     * This is what makes the URL-token channel self-expiring. It used to be
     * !hasFogUsers(), on the assumption that a token deploy only ever happens
     * on a fresh install -- but an *upgrade* has users and a stale schema, and
     * that is precisely when the browser path is needed. See GH-927.
     *
     * $mySchema stays 0 when the database could not be read, which reads as
     * "behind" and keeps the recovery path open on a broken database. That is
     * the same direction updateDB() already fails in, and it is safe: the
     * caller still has to present the per-install secret.
     *
     * @return bool
     */
    public static function schemaNeedsDeploy()
    {
        return self::$mySchema < FOG_SCHEMA;
    }
    /**
     * Does the caller hold a credential that permits a session-less schema
     * deploy? Either the installer's header (always), or the URL token while a
     * deploy is still outstanding.
     *
     * The URL token has to survive on an upgrade, not just a fresh install.
     * The installer's fallback hands the admin a ?node=schema URL and tells
     * them to log in there -- but on an old enough database that login reads
     * schema the deploy has not created yet, so it cannot be passed. On
     * working-1.6 that is an outright deadlock via StorageNode's
     * `ngmGraphColor` (migration 275). This branch has no such column, so here
     * it is the weaker form of the same defect: the tokenized URL is simply
     * ignored on an upgrade, leaving the printed link useless. GH-927.
     *
     * Widening this to an upgrade does not weaken the channel. The header tier
     * already accepts the identical secret with users present; this only adds
     * the leakier transport for it, bounded to the window where a deploy is
     * genuinely outstanding.
     *
     * @return bool
     */
    public static function validSchemaBootstrap()
    {
        return self::installTokenHeader()
            || (self::schemaNeedsDeploy() && self::installTokenParam());
    }
    /**
     * Is the acting user a FOG administrator (uType 0)?
     *
     * Deliberately not is_authorized(), which is true for any valid user --
     * including uType 1 mobile users -- and whose third clause nominally
     * admits a registered fog-client. "An administrator is driving this"
     * needs to mean exactly that, nothing looser.
     *
     * This is the whole of 1.5's authorization model. There is no RBAC on
     * this branch, so uType 0 vs anything else is the only boundary
     * available; GHSA-2hqx-5ffg-w4c3 was reported because the API never
     * consulted it at all.
     *
     * @return bool
     */
    public static function isAdminUser()
    {
        if (!self::$FOGUser || !self::$FOGUser->isValid()) {
            return false;
        }
        // Resolve the type through USER_TYPE_HOOK, the same way ProcessLogin
        // does, so directory-sourced admins count. The LDAP plugin maps its
        // own 990 (admin) to 0 and 991 (mobile) to 1, so this admits LDAP
        // administrators without loosening anything -- mobile accounts, LDAP
        // or local, still fail the === 0 test. Without it an LDAP-only site
        // could never apply a schema update from the browser.
        $type = self::$FOGUser->get('type');
        if (self::$HookManager) {
            self::$HookManager->processEvent(
                'USER_TYPE_HOOK',
                array('type' => &$type)
            );
        }
        return (int)$type === 0;
    }
    /**
     * Is the current session a FOG administrator?
     *
     * Kept as the name the schema-deploy path asks the question by; the test
     * itself is the generic one above, which the API gate shares.
     *
     * @return bool
     */
    public static function isSchemaAdmin()
    {
        return self::isAdminUser();
    }
    /**
     * Does this install have any FOG user rows yet?
     *
     * Distinguishes a fresh install (bootstrap token permitted) from an
     * established one (admin login required). Runs against a possibly ancient
     * schema, so it must degrade rather than throw: every PDODB failure path
     * already returns falsy instead of raising, and an unknown answer is
     * reported as "fresh" so recovery stays possible on a broken database.
     *
     * Counts user rows rather than uType = 0 rows on purpose: uType was
     * VARCHAR(2) before it became INT, and MySQL coerces '' = 0 to true, so an
     * admin-typed count is unreliable on legacy rows. "Any user exists" is the
     * question being asked.
     *
     * @return bool
     */
    public static function hasFogUsers()
    {
        if (self::$_hasFogUsers !== null) {
            return self::$_hasFogUsers;
        }
        self::$_hasFogUsers = false;
        if (!self::$DB || !DatabaseManager::getLink()) {
            return false;
        }
        $db = self::$DB->query('SELECT COUNT(`uId`) AS `total` FROM `users`');
        if (false !== $db->error) {
            return false;
        }
        self::$_hasFogUsers = ((int)$db->fetch()->get('total') > 0);
        return self::$_hasFogUsers;
    }
    /**
     * Is Authorized to perform action simplified
     *
     * @param $return_bool Defaults to false, but can return bool
     *
     * @return void|bool
     */
    public static function is_authorized($return_bool = false)
    {
        $authorized = (self::$FOGUser && self::$FOGUser->isValid())
            || ((self::$newService || filter_input(INPUT_GET, 'clientver')) && basename(self::$scriptname) == 'getversion.php')
            || (self::$newService && self::$Host->isValid() && self::$Host->get('pub_key'));
        if ($return_bool) {
            return $authorized;
        }
        if (!$authorized) {
            http_response_code(HTTPResponseCodes::HTTP_UNAUTHORIZED);
            echo _('Unauthorized');
            exit;
        }
    }
    /**
     * Output var_dump for logging
     *
     * @param object $object The item to var_dump
     *
     * @return string|null
     */
    public static function var_dump_log($object = null)
    {
        ob_start();
        var_dump($object);
        $contents = ob_get_contents();
        ob_end_clean();
        error_log($contents);
    }
}
