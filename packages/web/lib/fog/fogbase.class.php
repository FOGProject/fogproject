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
    public static $pluginsinstalled = [];
    /**
     * Plugin system available
     *
     * @var bool
     */
    public static $pluginIsAvailable = false;
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
    protected $isLoaded = [];
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
     * URL Manager | mainly for ajax, and externel getters.
     *
     * @var object
     */
    protected static $FOGURLRequests;
    /**
     * Current request uri.
     *
     * @var string
     */
    public static $requesturi;
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
    protected static $ips = [];
    /**
     * The current server's Interface information.
     *
     * @var array
     */
    protected static $interface = [];
    /**
     * The current base pages requiring search functionality.
     *
     * @var array
     */
    protected static $searchPages = [
        'group',
        'host',
        'image',
        'ipxe',
        'module',
        'plugin',
        'printer',
        'setting',
        'snapin',
        'storagegroup',
        'storagenode',
        'task',
        'user'
    ];
    /**
     * Is our current element already initialized?
     *
     * @var bool
     */
    private static $_initialized = false;
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
        self::$requesturi = filter_input(INPUT_SERVER, 'REQUEST_URI');
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
                $value,
                (self::$selected == $value ? ' selected' : ''),
                $option
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
        if (!is_string($class)) {
            throw new Exception(_('Class name must be a string'));
        }
        // Get all args, even unnamed args.
        $args = func_get_args();
        array_shift($args);

        // Trim the class var
        $class = trim($class);

        // Test what the class is and return if it is Reflection.
        $lClass = strtolower($class);
        if ($lClass === 'reflectionclass') {
            return new ReflectionClass(count($args ?: []) === 1 ? $args[0] : $args);
        }

        global $sub;
        // If class is Storage, test if sub is group or node.
        if ($class === 'Storage') {
            $class = 'StorageNode';
            if (preg_match('#storage[\-|_]group#i', $sub)) {
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
            if (count($args ?: []) === 1) {
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
     * Get's the relevant host item.
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
        }
        // disabling sysuuid detection code for now as it is causing
        // trouble with machines having the same UUID like we've seen
        // on some MSI motherboards having FFFFFFFF-FFFF-FFFF-FFFF...
        //$sysuuid = filter_input(INPUT_POST, 'sysuuid');
        //if (!$sysuuid) {
        //    $sysuuid = filter_input(INPUT_GET, 'sysuuid');
        //}
        //$mbserial = filter_input(INPUT_POST, 'mbserial');
        //if (!$mbserial) {
        //    $mbserial = filter_input(INPUT_GET, 'mbserial');
        //}
        //$sysserial = filter_input(INPUT_POST, 'sysserial');
        //if (!$sysserial) {
        //    $sysserial = filter_input(INPUT_GET, 'sysserial');
        //}
        // If encoded decode and store value
        if ($encoded === true) {
            $mac = base64_decode($mac);
            //$sysuuid = base64_decode($sysuuid);
            //$mbserial = base64_decode($mbserial);
            //$sysserial = base64_decode($sysserial);
        }
        // See if we can find the host by system uuid rather than by mac's first.
        /*if ($sysuuid) {
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
        }*/
        //self::getClass('HostManager')->getHostByUuidAndSerial(
        //$sysuuid,
        //$mbserial,
        //$sysserial
        //);
        //if (self::$Host->isValid() && !$returnmacs) {
        //    return;
        //}
        // Trim the mac list.
        $mac = trim($mac);
        // Parsing the macs
        $MACs = self::parseMacList(
            $mac,
            !$service,
            $service
        );
        $macs = [];
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
        if (count($macs ?: []) < 1 && $hostnotrequired === false) {
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
     * Get's blamed nodes for failures.
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
            if (!self::validDate($NodeFailure->failureTime)) {
                return false;
            }
            if ($DateTime < $DateInterval) {
                Route::delete('nodefailure', $NodeFailure->id);
                return false;
            }

            return $NodeFailure->id;
        };
        $find = [
            'taskID' => self::$Host->get('task')->get('id'),
            'hostID' => self::$Host->get('id'),
        ];
        Route::listem(
            'nodefailure',
            $find
        );
        $NodeFails = json_decode(
            Route::getData()
        );
        $nodeRet = array_values(
            array_unique(
                array_filter(
                    array_map(
                        $nodeFail,
                        $NodeFails->data
                    )
                )
            )
        );

        return $nodeRet;
    }
    /**
     * Returns array of plugins installed.
     *
     * @return array
     */
    protected static function getActivePlugins()
    {
        if (!self::getSetting('FOG_PLUGINSYS_ENABLED')) {
            return [];
        }
        self::getClass('Plugin')->getPlugins();
        $find = [
            'installed' => 1,
            'state' => 1,
        ];
        Route::ids(
            'plugin',
            $find,
            'name'
        );
        $plugins = json_decode(
            Route::getData(),
            true
        );
        return array_map('strtolower', $plugins);
    }
    /**
     * Converts our string if needed.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return string
     */
    private static function _setString($txt, $data = [])
    {
        if (count($data ?: [])) {
            $data = vsprintf($txt, $data);
        } else {
            $data = $txt;
        }

        return $data;
    }
    /**
     * Prints error.
     *
     * @param string $txt  the string to use
     * @param array  $data the data if txt is formatted string
     *
     * @return void
     */
    public static function error($txt, $data = [])
    {
        $data = self::_setString($txt, $data);
        $date = self::niceDate();
        $string = sprintf(
            '[%s] FOG ERROR: %s: %s',
            $date->format('l F d Y H:i:s'),
            __CLASS__,
            $data
        );
        if (self::$mySchema >= FOG_SCHEMA) {
            $tolog = self::getSetting('FOG_LOG_ERROR') > 0;
            if ($tolog) {
                $log_filename = BASEPATH . 'management/logs';
                if (!file_exists($log_filename)) {
                    mkdir($log_filename, 0777, true);
                }
                $log_file_data = $log_filename
                    . '/error_log_'
                    . $date->format('d-m-Y')
                    . '.log';
                file_put_contents($log_file_data, $string."\n", FILE_APPEND);
            }
        }
        if ((self::$service || self::$ajax) || !self::$debug) {
            return;
        }
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
    public static function debug($txt, $data = [])
    {
        $data = self::_setString($txt, $data);
        $date = self::niceDate();
        $string = sprintf(
            '[%s] FOG DEBUG: %s: %s',
            $date->format('l F d Y H:i:s'),
            __CLASS__,
            $data
        );
        if (self::$mySchema >= FOG_SCHEMA) {
            $tolog = self::getSetting('FOG_LOG_DEBUG') > 0;
            if ($tolog) {
                $log_filename = BASEPATH . 'management/logs';
                if (!file_exists($log_filename)) {
                    mkdir($log_filename, 0777, true);
                }
                $log_file_data = $log_filename
                    . '/debug_log_'
                    . $date->format('d-m-Y')
                    . '.log';
                file_put_contents($log_file_data, $string."\n", FILE_APPEND);
            }
        }
        if ((self::$service || self::$ajax) || !self::$debug) {
            return;
        }
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
    public static function info($txt, $data = [])
    {
        $data = self::_setString($txt, $data);
        $date = self::niceDate();
        $string = sprintf(
            '[%s] FOG INFO: %s: %s',
            $date->format('l F d Y H:i:s'),
            __CLASS__,
            $data
        );
        if (self::$mySchema >= FOG_SCHEMA) {
            $tolog = self::getSetting('FOG_LOG_INFO') > 0;
            if ($tolog) {
                $log_filename = BASEPATH . 'management/logs';
                if (!file_exists($log_filename)) {
                    mkdir($log_filename, 0777, true);
                }
                $log_file_data = $log_filename
                    . '/info_log_'
                    . $date->format('d-m-Y')
                    . '.log';
                file_put_contents($log_file_data, $string."\n", FILE_APPEND);
            }
        }
        if (!self::$info || self::$service || self::$ajax) {
            return;
        }
        printf('<div class="debug debug-info">%s</div>', $string);
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
        header("Location: $url", true, 308);
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
        $new = [];
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
     * Check if isLoaded.
     *
     * @param string|int $key the key to see if loaded
     *
     * @return bool|string
     */
    protected function isLoaded($key)
    {
        $key = $this->key($key);
        $result = isset($this->isLoaded[$key]) ? $this->isLoaded[$key] : 0;
        $this->isLoaded[$key] = true;
        ++$this->isLoaded[$key];

        return $result ? $result : false;
    }
    /**
     * Reset request variables.
     *
     * @return void
     */
    protected static function resetRequest()
    {
        if (session_status() == PHP_SESSION_NONE) {
            return;
        }
        if (!isset($_SESSION['post_request_vals'])) {
            $_SESSION['post_request_vals'] = [];
        }
        $sesVars = $_SESSION['post_request_vals'];
        $setReq = function (&$val, &$key) {
            $_POST[$key] = $val;
            unset($val, $key);
        };
        if (count($sesVars ?: []) > 0) {
            array_walk($sesVars, $setReq);
        }
        unset($_SESSION['post_request_vals'], $sesVars, $reqVars);
    }
    /**
     * Set request vars particularly for post failures really.
     *
     * @return void
     */
    protected function setRequest()
    {
        if (session_status() == PHP_SESSION_NONE) {
            return;
        }
        if (!isset($_SESSION['post_request_vals'])) {
            $_SESSION['post_request_vals'] = [];
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
        $units = ['iB', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
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
        $services = [
            'autologout' => 'autologoff',
            'displaymanager' => true,
            'hostnamechanger' => true,
            'hostregister' => true,
            'powermanagement' => true,
            'printermanager' => true,
            'snapinclient' => 'snapin',
            'taskreboot' => true,
            'usertracker' => true
        ];
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
        $find = ['name' => array_values($services)];
        Route::ids(
            'setting',
            $find,
            'value'
        );
        $serviceEn = json_decode(
            Route::getData(),
            true
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
            try {
                $tz = new DateTimeZone(self::$TimeZone);
            } catch (Exception $e) {
                $tz = new DateTimeZone('UTC');
            }
        }

        return new DateTime($date, $tz);
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
        try {
            if (!$date instanceof DateTime) {
                $date = self::niceDate($date);
            }
        } catch (Exception $e) {
            return false;
        }
        if (!$format) {
            $format = 'm/d/Y';
        }
        if (empty(self::$TimeZone)) {
            self::$TimeZone = 'UTC';
        }
        $tz = new DateTimeZone(self::$TimeZone);

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
        $iv_size = openssl_cipher_iv_length($enctype);
        $key = self::hex2bin($key);
        if (mb_strlen($key, '8bit') !== ($iv_size * 2)) {
            echo json_encode(
                ['error' => _('Needs a 256-bit key!')]
            );
            exit;
        }
        $iv = openssl_random_pseudo_bytes($iv_size, $cstrong);
        if (!$iv) {
            echo json_encode(
                ['error' => openssl_error_string()]
            );
            exit;
        }

        // Pad the plaintext
        if (strlen($data) % $iv_size) {
            $data = str_pad(
                $data,
                (strlen($data) + $iv_size - strlen($data) % $iv_size),
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
                ['error' => openssl_error_string()]
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
     *
     * @return string
     */
    public static function aesdecrypt(
        $encdata,
        $key = false,
        $enctype = 'aes-128-cbc'
    ) {
        $iv_size = openssl_cipher_iv_length($enctype);
        if (false === strpos($encdata, '|')) {
            return $encdata;
        }
        $data = explode('|', $encdata);
        if ($iv != pack('H*', $data[0])) {
            return '';
        }
        if ($encoded != pack('H*', $data[1])) {
            return '';
        }
        if (!$key && $data[2]) {
            if ($key != pack('H*', $data[2])) {
                return '';
            }
        }
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
                ['error' => openssl_error_string()]
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
        $tmpssl = [];
        Route::ids(
            'storagenode',
            [],
            'sslpath'
        );
        $sslfile = json_decode(
            Route::getData(),
            true
        );
        foreach ($sslfile as &$path) {
            if (!file_exists($path) || !is_readable($path)) {
                continue;
            }
            $tmpssl[] = $path;
            unset($path);
        }
        if (count($tmpssl ?: []) < 1) {
            throw new Exception(_('Private key path not found'));
        }
        $sslfile = sprintf(
            '%s%s.srvprivate.key',
            str_replace(
                ['\\', '/'],
                [DS, DS],
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
        $output = [];
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
        $MAClist = [];
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
        if (count($MACs ?: []) < 1) {
            return [];
        }
        $pending_filter = explode(
            ',',
            self::getSetting('FOG_QUICKREG_PENDING_MAC_FILTER')
        );
        $Ignore = array_map($lowerAndTrim, $pending_filter);
        $Ignore = array_filter($Ignore);
        if (count($Ignore ?: []) > 0) {
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
        if (count($MACs ?: []) < 1) {
            return [];
        }
        Route::count(
            'macaddressassociation',
            [
                'mac' => $MACs,
                'pending' => [0, '']
            ]
        );
        $count = json_decode(Route::getData());
        $count = $count->total;
        if ($count > 0) {
            $find = [
                'mac' => $MACs,
                'pending' => [0, '']
            ];
            Route::ids(
                'macaddressassociation',
                $find,
                'mac'
            );
            $existingMACs = json_decode(
                Route::getData(),
                true
            );
            $existingMACs = array_map($lowerAndTrim, $existingMACs);
            $existingMACs = array_filter($existingMACs);
            $existingMACs = array_unique($existingMACs);
            $existingMACs = array_values($existingMACs);
            $MACs = self::fastmerge((array) $MACs, (array) $existingMACs);
            $MACs = array_unique($MACs);
        }
        if ($client) {
            $find = [
                'mac' => $MACs,
                'clientIgnore' => 1
            ];
            Route::ids(
                'macaddressassociation',
                $find,
                'mac'
            );
            $clientIgnored = json_decode(
                Route::getData(),
                true
            );
            $clientIgnored = array_map($lowerAndTrim, $clientIgnored);
            $MACs = array_diff((array) $MACs, (array) $clientIgnored);
            unset($clientIgnored);
        }
        if ($image) {
            $find = [
                'mac' => $MACs,
                'imageIgnore' => 1
            ];
            Route::ids(
                'macaddressassociation',
                $find,
                'mac'
            );
            $imageIgnored = json_decode(
                Route::getData(),
                true
            );
            $imageIgnored = array_map($lowerAndTrim, (array) $imageIgnored);
            $MACs = array_diff((array) $MACs, (array) $imageIgnored);
            unset($imageIgnored);
        }
        $MACs = array_filter($MACs);
        $MACs = array_unique($MACs);
        $MACs = array_values($MACs);
        if (count($MACs ?: []) < 1) {
            return [];
        }
        $validMACs = [];
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
     * @param bool   $service    if not a service simpy return
     * @param array  $array      The non-encoded array data.
     *
     * @return string
     */
    protected function sendData(
        $datatosend,
        $service = true,
        $array = []
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
                    ->save();
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
                $data = ['error' => $repData];
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
        $mapinfo = [];
        foreach ((array) $needles as &$needle) {
            $mapinfo[] = $cmd($haystack, $needle);
            unset($needle);
        }
        $mapinfo = array_filter($mapinfo);

        return count($mapinfo ?: []) > 0;
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
    public static function log(
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
        $findStr = ["\r", "\n", "\t", ' ,'];
        $repStr = ['', ' ', ' ', ','];
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
        $userValid = self::$FOGUser instanceof User && self::$FOGUser->isValid();
        if (!$userValid) {
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
     * Get global setting value by key.
     *
     * @param string|array $key What to get
     *
     * @throws Exception
     *
     * @return string
     */
    public static function getSetting($key)
    {
        if (!is_string($key) && !is_array($key)) {
            throw new Exception(_('Key must be a string or array of strings'));
        }
        $findStr = '\r\n';
        $repStr = "\n";

        $sql = "SELECT `settingValue` FROM `globalSettings` WHERE `settingKey` %s";

        $where = '';
        if (is_array($key)) {
            $where = " IN ('"
                . implode("','", $key)
                . "')";
        } else {
            $where = " = '$key'";
        }

        $sqlStr = sprintf($sql, $where);

        $vals = self::$DB->query(sprintf($sql, $where))
            ->fetch('', 'fetch_all')->get('settingValue');

        foreach ((array)$vals as $ind => &$val) {
            $vals[$ind] = trim(
                str_replace(
                    $findStr,
                    $repStr,
                    trim($val)
                )
            );
            unset($val);
        }

        if (is_string($key)) {
            return array_shift($vals);
        }

        return $vals;
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
        return self::getClass('SettingManager')->update(
            ['name' => $key],
            '',
            ['value' => trim($value)]
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
                $val = $tmp;
            }
            unset($tmp);
            $item[$key] = trim($val);
            unset($val);
        }

        return $item;
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
        if (count(self::$interface ?: []) > 0) {
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
        self::$interface = [];
        $index = 0;
        foreach ((array) self::$ips as &$ip) {
            $ip = trim($ip);
            if ($ip_find !== $ip) {
                continue;
            }
            self::$interface[] = $Interfaces[$index++];
            unset($ip);
        }
        if (count(self::$interface ?: []) < 1) {
            return false;
        }

        return array_shift(self::$interface);
    }
    /**
     * Get IP Addresses of the server.
     *
     * @param bool $force Wither to force an ip.
     *
     * @return array
     */
    protected static function getIPAddress($force = false)
    {
        if (!$force && count(self::$ips ?: []) > 0) {
            return self::$ips;
        }
        $output = [];
        exec(
            "/sbin/ip -4 addr | awk -F'[ /]+' '/global/ {print $3}'",
            $IPs,
            $retVal
        );
        if (!count($IPs ?: [])) {
            exec(
                "/sbin/ifconfig -a | awk -F'[ /:]+' '/(cast)/ {print $4}'",
                $IPs,
                $retVal
            );
        }
        /*$test = self::$FOGURLRequests->isAvailable('ipinfo.io', 2, 80, 'tcp');
        $test = array_shift($test);
        if (false !== $test) {
            $res = self::$FOGURLRequests->process('http://ipinfo.io/ip');
            $IPs[] = $res[0];
        }*/
        natcasesort($IPs);
        $retIPs = function (&$IP) {
            $IP = trim($IP);
            if (!filter_var($IP, FILTER_VALIDATE_IP)) {
                $IP = gethostbyname($IP);
            }
            if (filter_var($IP, FILTER_VALIDATE_IP)) {
                return $IP;
            }
        };
        $retNames = function (&$IP) {
            $IP = trim($IP);
            if (filter_var($IP, FILTER_VALIDATE_IP)) {
                return gethostbyaddr($IP);
            }

            return $IP;
        };
        $IPs = array_map($retIPs, (array) $IPs);
        $Names = array_map($retNames, (array) $IPs);
        $output = self::fastmerge(
            $IPs,
            $Names,
            ['127.0.0.1', '127.0.1.1']
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
     * @param string $path the file to get size of
     *
     * @return string|int|float
     */
    public static function getFilesize($path)
    {
        $size = filesize($path);
        if (is_dir($path)) {
            $size = 0;
            $di = new RecursiveDirectoryIterator($path);
            $rii = new RecursiveIteratorIterator(
                $di,
                FilesystemIterator::SKIP_DOTS
            );
            foreach ($rii as $file) {
                $size += filesize($file);
            }
        }
        return is_numeric($size) ? $size : 0;
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
            $macs = [$macs];
        }
        ignore_user_abort(true);
        set_time_limit(0);
        $macs = self::parseMacList($macs);
        if (count($macs ?: []) < 1) {
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
        $nodeURLs = [];
        $macCount = count($macs ?: []);
        if ($macCount < 1) {
            return;
        }
        Route::listem(
            'storagenode',
            ['isEnabled' => 1]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        foreach ($StorageNodes->data as &$StorageNode) {
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            if (!$StorageNode->online) {
                continue;
            }
            $nodeURLs[] = sprintf(
                $url,
                self::$httpproto,
                $StorageNode->ip
            );
            unset($StorageNode);
        }
        $gHost = self::getSetting('FOG_WEB_HOST');
        $ip = $gHost;
        $nodeURLs[] = $ip;
        $ret = self::$FOGURLRequests->process(
            $nodeURLs,
            'POST',
            ['mac' => $macStr],
            false,
            false,
            false,
            false
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
            if ($filesize >=  20971529) {
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
     * @param bool   $remember Are we remembering user?
     *
     * @return object
     */
    public static function attemptLogin(
        $username,
        $password,
        $remember = false
    ) {
        return self::getClass('User')
            ->validatePw($username, $password, $remember);
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
        Route::count('oui');
        $count = json_decode(Route::getData());
        return $count->total;
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
        $output = [];
        $cmd = sprintf(
            '%s | %s | %s',
            '/sbin/ip -4 addr',
            "awk -F'[ /]+' '/global/ {print $6}'",
            "grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'"
        );
        exec($cmd, $IPs, $retVal);
        if (!count($IPs ?: [])) {
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
     * Starts the class based on the filename passed.
     *
     * @param array $files  The array of files.
     * @param int   $strlen How much of file to strip off end to get classname.
     *
     * @return void
     */
    public static function startClassFromFiles($files, $strlen)
    {
        foreach ($files as &$file) {
            $className = str_replace(
                ["\t","\n",' '],
                '_',
                substr(
                    basename($file),
                    0,
                    $strlen
                )
            );
            if (class_exists($className, false)) {
                continue;
            }
            self::getClass($className);
            unset($file);
        }
    }
    /**
     * Does the work for reauthentication during delete, if needed.
     *
     * @return void
     */
    public static function checkauth()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $user = filter_input(INPUT_POST, 'fogguiuser');
            if (empty($user)) {
                $user = self::$FOGUser->get('name');
            }
            $pass = filter_input(INPUT_POST, 'fogguipass');
            $validate = self::getClass('User')
                ->passwordValidate(
                    $user,
                    $pass,
                    true
                );
            if (!$validate) {
                header('Content-type: application/json');
                echo json_encode(
                    [
                        'error' => self::$foglang['InvalidLogin'],
                        'title' => _('Unable to Authenticate')
                    ]
                );
                http_response_code(HTTPResponseCodes::HTTP_UNAUTHORIZED);
                exit;
            }
        }
    }
    /**
     * Get the file items.
     *
     * @param string $extension The file extension.
     * @param string $dirpath   The folder path to scan within.
     * @param bool   $split     Do we need to split the normal/plugin files?
     * @param bool   $needplug  Do we need plugins?
     *
     * @return string
     */
    public static function fileitems(
        $extension = '.class.php',
        $dirpath = 'fog',
        $split = false,
        $needplug = true
    ) {
        // Quote the regex strings in this string (e.g. . becomes \.)
        $regex_ext = preg_quote($extension);
        // Set our pathing directory separators to that of this system.
        $regex_dir = str_replace(['\\','/'], [DS,DS], $dirpath);
        // Set our pathing directory for plugins with the directory separator also.
        $regex_pdir = DS . 'plugins' . DS;
        // Main regex string.
        $regext = "#^.+{$regex_dir}.*{$regex_ext}$#";
        // Preg Grep Regex.
        $regex_pgrep = '#'
            . DS
            . '('
            . implode('|', self::$pluginsinstalled)
            . ')'
            . DS
            . '#';
        // initialize plugin regex caller.
        $plugins = '';

        // Get all of our files.
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        $files = iterator_to_array($RegexIterator, false);
        if (!$needplug) {
            natcasesort($files);
            return $files;
        }
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );

        // Closure so we can use a common function call.
        $fileitems = function ($element) use (
            $regex_dir,
            $regex_pdir,
            &$plugins
        ) {
            preg_match(
                "#^($plugins.+{$regex_pdir})(?=.*{$regex_dir}).*$#",
                $element[0],
                $match
            );
            return $match[0];
        };

        $normalfiles = [];
        $pluginfiles = [];
        foreach ($files as &$file) {
            $plugins = '?!';
            $normalfiles[] = $fileitems($file);
            $plugins = '?=';
            $pluginfiles[] = $fileitems($file);
            unset($file);
        }

        $pluginfiles = preg_grep(
            $regex_pgrep,
            $pluginfiles
        );

        $files = self::fastmerge(
            $normalfiles,
            $pluginfiles
        );
        if ($split) {
            natcasesort($normalfiles);
            natcasesort($pluginfiles);
            $normalfiles = array_values(
                array_filter(
                    array_unique(
                        $normalfiles
                    )
                )
            );
            $pluginfiles = array_values(
                array_filter(
                    array_unique(
                        $pluginfiles
                    )
                )
            );

            return [$normalfiles, $pluginfiles];
        }
        unset($normalfiles, $pluginfiles);

        natcasesort($files);
        $files = array_values(
            array_filter(
                array_unique(
                    $files
                )
            )
        );

        return $files;
    }
    /**
     * Get's token for our cookie
     *
     * @return string
     */
    public static function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet .= "0123456789";
        $max = strlen($codeAlphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[self::cryptoRandSecure(0, $max)];
        }
        return $token;
    }
    /**
     * Sets a random crypto secured element.
     *
     * @return string
     */
    public static function cryptoRandSecure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) {
            return $min; // not so random...
        }
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }
    /**
     * Clears the authorization cookie
     *
     * @return void
     */
    public static function clearAuthCookie()
    {
        $id = filter_input(INPUT_COOKIE, 'foguserauthid');
        $pass = filter_input(INPUT_COOKIE, 'foguserauthpass');
        $sel = filter_input(INPUT_COOKIE, 'foguserauthsel');
        if (isset($id)) {
            setcookie('foguserauthid', '');
            Route::delete(
                'userauth',
                $id
            );
        }
        if (isset($pass)) {
            setcookie('foguserauthpass', '');
        }
        if (isset($sel)) {
            setcookie('foguserauthsel', '');
        }
    }
}
