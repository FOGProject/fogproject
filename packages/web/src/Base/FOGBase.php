<?php
/**
 * FOGBase, the base class for pretty much all of fog.
 *
 * PHP version 7.4+
 *
 * This gives all the rest of the classes a common frame to work from.
 *
 * @category FOGBase
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Auth\Authorization;
use FOG\Auth\CSRF;
use FOG\Db\DatabaseManager;
use FOG\Db\SchemaReconciler;
use FOG\Items\History;
use FOG\Items\Host;
use FOG\Items\MACAddress;
use FOG\Items\Plugin;
use FOG\Items\Setting;
use FOG\Items\TaskState;
use FOG\Items\User;
use FOG\Managers\HostManager;
use FOG\Managers\SettingManager;
use FOG\Managers\UserPrefManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
     * Tracks which keys a caller has actually written (via set()/add()/
     * remove()), as opposed to keys merely lazy-loaded for reading. See
     * isDirty()'s docblock.
     *
     * @var array
     */
    protected $dirty = [];
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
     * SSH Handler.
     *
     * @var object
     */
    protected static $FOGSSH;
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
     * The zone stored datetimes are written in and read back as.
     *
     * NOT a display setting, whatever the name suggests. niceDate() uses it to
     * INTERPRET a stored string, and the 34 sites that write a timestamp
     * produce it through the same call, so this is the zone the database is in.
     * Changing it therefore re-labels every row that already exists rather
     * than re-presenting it -- which is why moving storage to UTC is a
     * migration, not a settings change.
     *
     * @var object
     */
    protected static $TimeZone;
    /**
     * The zone datetimes are SHOWN in, resolved once per request.
     *
     * Separate from $TimeZone so a user can be shown times in their own zone
     * without changing what is stored. Null until first use; see
     * displayTimeZone().
     *
     * @var \DateTimeZone|null
     */
    private static $_displayTimeZone = null;
    /**
     * The user preference holding a viewer's chosen display zone.
     *
     * An empty or absent value means "use the install default", which is what
     * every account has until someone deliberately chooses otherwise.
     */
    const TIMEZONE_PREF = 'display.timezone';
    /**
     * Resolved once per request, same as $_displayTimeZone.
     *
     * '' means "follow the operating system", which is not the same as
     * 'light' -- see displayTheme().
     *
     * @var string|null
     */
    private static $_displayTheme = null;
    /**
     * The user preference holding a viewer's chosen color theme.
     *
     * Absent or empty means "follow the operating system". Storing the theme
     * per USER rather than in a cookie is deliberate: it is a property of the
     * person, so signing in on another machine or another browser brings it
     * with you.
     */
    const THEME_PREF = 'display.theme';
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
     * Names that did not resolve, and the time to stop believing that.
     *
     * name => unix timestamp the entry expires at. Only FAILURES are held --
     * see resolveHostname() for why caching a success would be the wrong
     * trade.
     *
     * @var array
     */
    protected static $unresolved = [];
    /**
     * How many times the system resolver has actually been called.
     *
     * Counts calls that reached gethostbyname(), so a caller can report how
     * much of a cycle's resolving was served from $unresolved instead. Read
     * it with resolverCalls().
     *
     * @var int
     */
    protected static $resolvercalls = 0;
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
        'role',
        'setting',
        // Sites came in from the site plugin, which added itself here from
        // its menu hook (`$arguments['searchPages'][] = $this->node`). Core
        // pages are listed statically instead, and missing from this list a
        // page renders "Index page of: SiteManagement" rather than its grid
        // -- FOGPage::index() only takes the list branch for a node it finds
        // here. No error, just the wrong half of an if.
        'site',
        'snapin',
        'software',
        'storagegroup',
        'storagenode',
        'task',
        'user',
        'usergroup'
    ];
    /**
     * Per-process cache of global settings.
     *
     * Keyed by setting name; each entry is ['value' => mixed, 'ts' => int]
     * where ts is the unix time the value was loaded.
     *
     * @var array
     */
    private static $_settingsCache = [];
    /**
     * Time-to-live, in seconds, for entries in the settings cache.
     *
     * Overridable at runtime to tune staleness vs. database load.
     *
     * @var int
     */
    public static $settingsCacheTTL = 300;
    /**
     * Settings cache instrumentation for this process: per-key reads served
     * from cache (hits), per-key reads that fell through to the database
     * (misses), and the number of actual database round-trips issued.
     *
     * @var int
     */
    private static $_settingsCacheHits = 0;
    private static $_settingsCacheMisses = 0;
    private static $_settingsCacheQueries = 0;
    /**
     * Persistent (file-backed) settings cache control.
     *
     * null = automatic (enabled for the web tier, disabled for CLI daemons,
     * which already benefit from their long-lived in-memory cache). Set to
     * true/false to force it on or off (kill-switch / testing).
     *
     * @var bool|null
     */
    public static $settingsFileCache = null;
    /**
     * Whether the file-backed cache has already been consulted this request.
     *
     * @var bool
     */
    private static $_settingsFileChecked = false;
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
     * Schema step that backfilled roles onto every pre-RBAC local account.
     *
     * A database below this version predates RBAC: role assignments either do
     * not exist yet or are incomplete, so permission checks there report "no
     * access" for accounts that are in fact administrators. isSchemaAdmin()
     * uses it to bound the legacy uType fallback to exactly that window. A
     * fixed step number, not FOG_SCHEMA -- it must not follow future bumps,
     * or the fallback would revive on every subsequent upgrade.
     *
     * @var int
     */
    const RBAC_ROLE_BACKFILL_SCHEMA = 316;
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
        global $FOGSSH;
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
        self::$FOGSSH = &$FOGSSH;
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
                \Initiator::e($value),
                (self::$selected == $value ? ' selected' : ''),
                \Initiator::e($option)
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
     * Returns a class' short name, with any namespace prefix stripped.
     *
     * Phase 3 of the refactor declares FOG's classes inside the FOG\
     * namespace, at which point get_class() starts reporting 'FOG\Host'
     * where it reports 'Host' today. That is harmless wherever the result
     * is used as a class *reference* -- PHP resolves it either way -- but
     * FOG also uses it as *data*: as a database column name, as a switch
     * case, as an HTML attribute, as a Route::$validClasses lookup, and as
     * the text written to the history table. In those positions the prefix
     * is silently wrong, so every one of them derives its string here.
     *
     * On the current tree this is a no-op: with no namespace declared there
     * is no prefix to strip. That is deliberate -- it lets the derivation
     * sites be fixed and shipped ahead of the namespacing itself.
     *
     * tests/class-name-derivation.test.php holds the line.
     *
     * @param object|string $class object or class name to shorten
     *
     * @return string
     */
    public static function shortName($class)
    {
        $name = is_object($class) ? get_class($class) : (string) $class;
        $pos = strrpos($name, '\\');
        return false === $pos ? $name : substr($name, $pos + 1);
    }
    /**
     * Defines string as class name.
     *
     * @return string
     */
    public function __toString()
    {
        return self::shortName($this);
    }
    /**
     * Resolves a bare class name to its fully qualified one.
     *
     * Every FOG class under src/ is namespaced, and so is every bundled
     * plugin class, but almost nothing that NAMES one is: `getClass('Host')`,
     * `new $short.'Manager'`, the 52 lowercase strings in
     * Route::$validClasses and FOGPage's $childClass all spell the bare name.
     * `new $string` and a class name in a string resolve from the GLOBAL
     * namespace -- `use` is not consulted and the enclosing namespace is not
     * applied -- so they worked only while each file under src/ ended in a
     * class_alias() re-exporting itself there. Those aliases are retired
     * (ADR 0013 §2), and this is the one place the translation happens, so
     * retiring them did not mean editing every caller.
     *
     * Two maps, consulted in that order:
     *
     *  - Initiator::srcClassMap(), core, under src/;
     *  - Initiator::pluginShortMap(), the plugin classes that declare a
     *    FOG\Plugins\<Plugin>\ namespace.
     *
     * Core first is a guarantee, not a preference -- see the comment in the
     * body.
     *
     * Two things still pass through untouched, each on purpose:
     *
     *  - a name neither map declares -- \DateTimeZone reaches here from a
     *    real caller, and a plugin still written in the global namespace
     *    resolves by its bare name through Initiator::autoload() exactly as
     *    it did before;
     *  - a name that is already qualified. That needs no guard of its own:
     *    both maps are keyed on lowercased SHORT names, so 'fog\items\host'
     *    matches nothing and falls through. An explicit early return for it
     *    was written first and removed -- mutation testing showed deleting
     *    it changed no behavior, which is the definition of a branch
     *    describing a case that cannot happen.
     *
     * Passing an unknown name through rather than failing is what keeps this
     * a widening of resolution rather than a narrowing: no name that
     * resolved before stops resolving.
     *
     * @param string $class the class name, bare or qualified
     *
     * @return string the FQCN where core or a plugin declares one, else
     *                $class unchanged
     */
    public static function qualify(string $class): string
    {
        $key = strtolower($class);
        $map = \Initiator::srcClassMap();
        if (isset($map[$key])) {
            return $map[$key];
        }
        // Then the plugins, which are laid out and namespaced exactly as core
        // is -- FOG\Plugins\<Segment>\<Bucket>\<Class> (ADR 0035). Same
        // problem, same shape of answer: roughly 150 getClass('X') literals
        // live inside the plugins themselves, and a plugin model reaches the
        // REST API as the lowercase string the plugin pushed into
        // Route::$validClasses through API_VALID_CLASSES. One lookup here
        // serves all of them.
        //
        // Core is consulted FIRST and that order is load-bearing, not
        // stylistic: it is what stops a plugin answering a core name. Both
        // sides now have a namespace of their own, so the only place they can
        // still collide is the BARE spelling -- which is this map's key, so
        // this is the one place the collision has to be decided.
        //
        // A name in neither map falls through untouched, which is what keeps
        // getClass('DateTime') and the like working.
        return \Initiator::pluginShortMap()[$key] ?? $class;
    }

    /**
     * Returns the class after verifying reflection of the class.
     *
     * @param string $class the name of the class to load
     * @param mixed  $data  the data to load into the class
     * @param bool   $props return just properties or full object
     *
     * @return object|mixed
     * @throws Exception
     *
     */
    public static function getClass($class, $data = '', $props = false)
    {
        if (!is_string($class)) {
            throw new \Exception(_('Class name must be a string'));
        }
        // Get all args, even unnamed args.
        $args = func_get_args();
        array_shift($args);

        // Trim the class var
        $class = trim($class);

        // Test what the class is and return if it is Reflection.
        $lClass = strtolower($class);
        if ($lClass === 'reflectionclass') {
            return new \ReflectionClass(count($args ?: []) === 1 ? $args[0] : $args);
        }

        // Bare core name -> FQCN, so the ~520 literal callers stop depending
        // on the compatibility alias. A plugin name, a built-in and an
        // already-qualified name all pass through unchanged.
        $class = self::qualify($class);

        // Initiate Reflection item.
        $obj = new \ReflectionClass($class);

        // If props is set to true return the properties of the class.
        if ($props === true) {
            return $obj->getDefaultProperties();
        }

        // Return the main object
        if ($obj->getConstructor()) {
            // If there's only one argument return the instance using it.
            // Otherwise, return with full call.
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
        // Normalize the mac. stripAndDecode() rewrites $_REQUEST, but the mac
        // is read here from the raw request via filter_input() (or passed in
        // explicitly), which that rewrite never touches, so the encoding has
        // to be resolved here. The legacy $encoded flag is now redundant but
        // kept for call-signature compatibility.
        $mac = self::stripAndDecodeMac($mac);
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
        (new HostManager())->getHostByMacAddresses($macs);
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
            throw new \Exception($msg);
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
                throw new \Exception($msg);
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
        /**
         * Returns the storage node id if still accurate
         * or will clean up past time nodes.
         *
         * @param object $NodeFailure the node that is in failed state
         *
         * @return int|bool
         */
        $nodeFail = function ($NodeFailure) {
            if (!self::validDate($NodeFailure->failureTime)) {
                return false;
            }
            $curr = self::niceDate();
            $prev = self::niceDate($NodeFailure->failureTime);
            if ($curr < $prev) {
                return $NodeFailure->storagenodeID;
            }
            Route::delete('nodefailure', $NodeFailure->id);
            return false;
        };
        $find = [
            'taskID' => self::$Host->get('task')->get('id'),
            'hostID' => self::$Host->get('id'),
        ];
        $NodeFails = Route::getList(
            'nodefailure',
            $find
        );
        $nodeRet = array_values(
            array_unique(
                array_filter(
                    array_map(
                        $nodeFail,
                        $NodeFails
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
        (new Plugin())->getPlugins();
        $find = [
            'installed' => 1,
            'state' => 1,
        ];
        $plugins = Route::getIds(
            'plugin',
            $find,
            'name'
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
     * Writes a log line at the given level.
     *
     * Appends to the daily log file when schema is current and the matching
     * FOG_LOG_* setting is on, then optionally echoes the line to the page.
     *
     * @param string $label    the level label, e.g. 'ERROR'
     * @param string $setting  the FOG_LOG_* setting gating file output
     * @param string $prefix   the log filename prefix, e.g. 'error_log'
     * @param string $cssClass the css class for the printed div
     * @param bool   $show     whether this level prints to the page
     * @param string $txt      the string to use
     * @param array  $data     the data if txt is a formatted string
     *
     * @return void
     */
    private static function _writeLog(
        $label,
        $setting,
        $prefix,
        $cssClass,
        $show,
        $txt,
        $data
    ) {
        /*
         * GH-1245: logging must never depend on logging.
         *
         * getSetting() below issues a query. PDODB's own error handling calls
         * debug() -- sqlerror() does, on every failed fetch -- so one failed
         * statement used to run:
         *
         *   fetch() -> sqlerror() -> debug() -> _writeLog() -> getSetting()
         *     -> query()/fetch() -> sqlerror() -> debug() -> ...
         *
         * unbounded, until the PHP worker died on memory. Nothing reported the
         * original error, because the process never got back to report it.
         *
         * It stayed hidden because PDODB cleared sql_mode on every connection,
         * so statements almost never failed. It was never really about
         * sql_mode though: a locked table, a lost connection or a permission
         * change would have done it just as well.
         *
         * Re-entry is dropped rather than deferred. A log line produced while
         * writing a log line describes the logger, not the request.
         */
        static $inWriteLog = false;
        if ($inWriteLog) {
            return;
        }
        $inWriteLog = true;

        try {
            self::_writeLogLine(
                $label,
                $setting,
                $prefix,
                $cssClass,
                $show,
                $txt,
                $data
            );
        } finally {
            $inWriteLog = false;
        }
    }

    /**
     * The body of _writeLog(), which is only ever reached non-reentrantly.
     *
     * @param string $label    the level label, e.g. 'ERROR'
     * @param string $setting  the FOG_LOG_* setting gating file output
     * @param string $prefix   the log filename prefix, e.g. 'error_log'
     * @param string $cssClass the css class for the printed div
     * @param bool   $show     whether this level prints to the page
     * @param string $txt      the string to use
     * @param array  $data     the data if txt is a formatted string
     *
     * @return void
     */
    private static function _writeLogLine(
        $label,
        $setting,
        $prefix,
        $cssClass,
        $show,
        $txt,
        $data
    ) {
        $data = self::_setString($txt, $data);
        $date = self::niceDate();
        $string = sprintf(
            '[%s] FOG %s: %s: %s',
            $date->format('l F d Y H:i:s'),
            $label,
            // Log prefix, so the short name -- not 'FOG\FOGBase'.
            self::shortName(__CLASS__),
            $data
        );
        if (self::$mySchema >= FOG_SCHEMA && self::getSetting($setting) > 0) {
            $log_filename = BASEPATH . 'management/logs';
            if (!is_dir($log_filename)) {
                self::_createLogDir($log_filename);
            }
            $log_file_data = $log_filename
                . '/' . $prefix . '_'
                . $date->format('d-m-Y')
                . '.log';
            // Whoever creates the day's file owns it, and file_put_contents
            // creates 0666 masked by the umask -- 0644 under systemd. A root
            // daemon that logs first therefore locks the web user out of
            // today's file even when the DIRECTORY is right. Only the creator
            // can chmod it, so widen it here, at creation, rather than trying
            // to repair it from the side that has already been denied.
            $isNew = !file_exists($log_file_data);
            file_put_contents($log_file_data, $string."\n", FILE_APPEND);
            if ($isNew) {
                @chmod($log_file_data, 0664);
            }
        }
        if (self::$service || self::$ajax || !$show) {
            return;
        }
        printf('<div class="debug %s">%s</div>', $cssClass, $string);
    }
    /**
     * Creates the web tree's log directory so every FOG process can write it.
     *
     * BASEPATH/management/logs is shared by two sets of writers that do not
     * share a uid. The web UI runs as the web user; so do FOGPluginRunner and
     * FOGRetentionRunner. The other ten daemons run as ROOT and boot this same
     * web tree -- packages/service/etc/config.php points WEBROOT at it and
     * service_lib.php requires commons/base.inc.php -- so a FOGBase::info()
     * from any of them lands here too.
     *
     * The directory is not shipped in the repo and installfog.sh's
     * configureHttpd() rm -rf's the web tree, so it is recreated from scratch
     * after every deploy by whichever process logs first. That is a race, and
     * root usually wins it: ten of the twelve daemons start at boot.
     *
     * Two things make root winning fatal rather than merely untidy:
     *
     *   - mkdir()'s mode argument is masked by the process umask (0022 under
     *     systemd), so mkdir(0777) actually lands 0755. chmod() is NOT masked,
     *     which is why the mode is asserted separately here instead of being
     *     passed to mkdir and assumed.
     *   - the group would otherwise be root's, so widening the mode alone
     *     would not help the web user either. BASEPATH's own group is the one
     *     installfog.sh chowns the web tree to, which makes it the right
     *     answer without this class having to know the distro's web user.
     *
     * Get it wrong and every non-root writer is denied for the life of the
     * install, once per log line, with nothing to explain it but a PHP warning
     * -- one box produced half a million of them in a day.
     *
     * setgid so that files created in here by a root daemon inherit the web
     * group rather than root's; _writeLogLine() widens the file mode to match.
     *
     * Failures are deliberately swallowed. mkdir() warns "File exists" when it
     * loses the race, and PHP's stat cache makes a long-lived daemon lose it
     * repeatedly against a directory it created itself. The write that follows
     * still warns if it genuinely cannot proceed, so no real failure is hidden.
     *
     * Only ever called for a directory that does not exist yet, because
     * repairing one from here is not possible: a non-root process cannot
     * chmod or chgrp a directory root owns, and root's own is_writable() is
     * true whatever the mode says, so the process that could repair it never
     * detects that it needs to. An install left in the old state is fixed by
     * configureHttpd(), which is where the web tree's permissions belong.
     *
     * @param string $dir the directory to create
     *
     * @return void
     */
    private static function _createLogDir($dir)
    {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return;
        }
        $gid = @filegroup(BASEPATH);
        if ($gid !== false) {
            @chgrp($dir, $gid);
        }
        @chmod($dir, 02775);
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
        self::_writeLog(
            'ERROR',
            'FOG_LOG_ERROR',
            'error_log',
            'debug-error',
            self::$debug,
            $txt,
            $data
        );
    }
    /**
     * The subdirectory of FOG's log directory that fault lines are written to.
     *
     * Its own subdirectory rather than the top level, for the reason ADR 0010
     * gives for the plugin runner's and TaskError's: rotation renames and
     * unlinks, and the top level is root's -- the eight daemons' logs live
     * there and nothing running as the web user should be able to remove
     * them.
     *
     * @var string
     */
    /**
     * How many TYPE_LOG history rows one request may write.
     *
     * ADR 0020 decision 6. See FOGBase::log() for why this is a cap rather
     * than a log-level check. Generous on purpose: the point is to stop a
     * runaway, not to ration logging, and anything under this bound behaves
     * exactly as it did before.
     *
     * @var int
     */
    const LOG_HISTORY_MAX = 100;
    /**
     * TYPE_LOG history rows written so far in this request.
     *
     * Static and never reset: a request is one process, so process lifetime
     * IS the window being bounded.
     *
     * @var int
     */
    protected static $logHistoryRows = 0;
    const FAULT_LOG_SUBDIR = 'faults';
    /**
     * How big a fault log may get before one old copy is kept, in bytes.
     *
     * A literal, NOT the SERVICE_LOG_SIZE setting the daemons and
     * TaskError::_rotate() use, and that is the whole point: getSetting()
     * issues a query, and the thing being reported here is a query that just
     * failed. See logFault()'s docblock.
     *
     * One generation rather than the daemons' five. This file gains a line
     * per failed write; on a healthy server it never gets a line at all.
     *
     * @var int
     */
    const FAULT_LOG_MAX = 10485760;
    /**
     * How long a single fault line may get, in bytes, before it is cut.
     *
     * A backstop, not the main defense: logFault() drops PDODB's debug tail
     * outright (see there). This catches what has no tail to drop -- a
     * driver message that is itself enormous, or a caller that built its
     * own. One fault stays one readable line either way.
     *
     * @var int
     */
    const FAULT_LINE_MAX = 2048;
    /**
     * Records that something FOG needed to write did not get written.
     *
     * The failure sink of last resort, and deliberately the only logger here
     * that asks nobody's permission to run.
     *
     * WHY THIS EXISTS AT ALL. FOGController::save() and destroy() recorded a
     * failed write by calling logHistory(), which returns without doing
     * anything unless self::$FOGUser is a valid User. Nothing on a machine
     * -facing path ever sets one -- packages/web/service/, lib/reg-task/ and
     * the eight daemons are matched to a HOST by MAC or token, and the
     * daemons have no request at all -- so on every one of those paths the
     * failure branch ran and wrote nowhere. debug() and error() were no
     * better: both only reach a file when FOG_LOG_DEBUG / FOG_LOG_ERROR are
     * non-zero, and schema step 280 ships them at '0'. The framework
     * recorded failures for humans and discarded them for machines.
     *
     * WHY A FILE AND NOT A TABLE. logHistory() writes a row, so it shares its
     * failure mode with the thing it is reporting on: a lost connection, a
     * locked table or a full disk takes out the report along with the write.
     * A sink for a failed database write cannot itself be a database write.
     * That -- not the user gate -- is the structural reason this is not
     * simply logHistory() with the gate widened. The user gate is correct
     * where it is: `history` is the audit trail, "who did what", and nobody
     * did this.
     *
     * THREE THINGS IT MUST NOT DO, each earned:
     *
     *   No getSetting(), for the path or the rotation size or anything else.
     *     getSetting() issues a query. PDODB's error handling calls debug(),
     *     and GH-1245 has the transcript of what that costs: fetch() ->
     *     sqlerror() -> debug() -> getSetting() -> query() -> sqlerror() ->
     *     ... until the worker died with nothing reported. FAULT_LOG_MAX is
     *     a literal for exactly this reason.
     *
     *   Not routed through _writeLog(). Its re-entry guard DROPS the line
     *     rather than deferring it, which is right for a debug line and
     *     fatal here: a save that failed while a log line was being written
     *     would be the one report thrown away.
     *
     *   Never gated on a setting. An operator turning FOG_LOG_DEBUG off is
     *     saying "stop telling me what is working", not "stop telling me
     *     what broke".
     *
     * error_log() is the fallback, not the destination, and it is
     * load-bearing rather than tidiness. The directory is the installer's,
     * so a server whose web tree has been updated but which has not been
     * re-installed has nowhere to write yet -- and PHP's own channel is
     * already pointed somewhere useful in both tiers: service_lib.php sets
     * error_log to servicemaster.log for the daemons, and the web tier's
     * php-fpm log is one FOGLogPaths::readable() already offers the Log
     * Viewer.
     *
     * IF THE LOG-TABLE / AUDIT / SPAN ADRs LAND: this gains a second
     * destination, a best-effort row written AFTER the file line and never
     * instead of it. The file write stays whatever else arrives, because at
     * the moment this is called the database is the untrusted party. One
     * function to change; do not tidy the file away.
     *
     * @param string $message what did not get written, and why
     *
     * @return void
     */
    public static function logFault($message)
    {
        // Same shape as _writeLog()'s guard and for the same reason, except
        // that nothing here can re-enter through the database. It covers the
        // one real case: logFault() failing on its own file write.
        static $inFault = false;
        if ($inFault) {
            return;
        }
        $inFault = true;

        try {
            /*
             * Drop PDODB's debug tail BEFORE anything else looks at the
             * message. Its error text always appends
             * "\nSQL: ...\nParams: ...\nErrorInfo: ...\nDebug: ..."
             * (pdodb.class.php, both sqlerror() formats), and the Params and
             * Debug sections print every BOUND VALUE of the statement that
             * failed. On `users` that is the password hash, on `hosts` the
             * client security token, on `nfsGroupMembers` the storage node's
             * FTP password -- the credential GHSA-2hqx turns into root.
             *
             * That was survivable while this text only ever reached
             * logHistory(), which is user-gated and so dropped it on exactly
             * the machine paths that fail most, and debug(), which ships
             * off. It is NOT survivable in a file written unconditionally on
             * every failed write, and readable by any local account. What an
             * operator actually needs is the part before the tail: the
             * driver, the SQLSTATE and the message.
             */
            $raw = (string) $message;
            foreach (array("\nSQL: ", "\nParams: ", "\nErrorInfo: ", "\nDebug: ") as $marker) {
                $tail = strpos($raw, $marker);
                if (false !== $tail) {
                    $raw = substr($raw, 0, $tail);
                }
            }
            // One line per fault, so `tail -f` on this file is readable and
            // a multi-line message cannot be mistaken for several faults.
            $flat = preg_replace('#\s+#', ' ', $raw);
            // Never let a message become an empty one. preg_replace returns
            // null when it gives up, and a (string) cast of that is '', which
            // the guard below would then throw away -- losing the one record
            // this method exists to keep. The pattern carries no /u, so it
            // works bytewise and invalid UTF-8 cannot trip it; this covers
            // whatever else might.
            $line = trim(null === $flat ? $raw : $flat);
            if ('' === $line) {
                return;
            }
            if (strlen($line) > self::FAULT_LINE_MAX) {
                $line = substr($line, 0, self::FAULT_LINE_MAX) . ' [truncated]';
            }
            $stamped = sprintf(
                '[%s] %s%s',
                self::niceDate()->format('Y-m-d H:i:s'),
                $line,
                PHP_EOL
            );
            $file = self::_faultLogPath();
            if ('' !== $file) {
                self::_rotateFaultLog($file);
                if (false !== @file_put_contents($file, $stamped, FILE_APPEND)) {
                    return;
                }
            }
            error_log($line);
        } finally {
            $inFault = false;
        }
    }
    /**
     * The fault log's path, or '' if there is nowhere to write.
     *
     * Split by SAPI, into faults-web.log and faults-service.log. This is the
     * one FOG log directory written by BOTH tiers -- the web user, and root
     * for the eight daemons -- and a single shared file would be owned by
     * whichever wrote first. A root-owned file appears the moment any daemon
     * hits a failed write, and from then on every web-tier fault would fall
     * silently to error_log(). Silently diverting to a worse destination is
     * the exact failure this whole path exists to end, so the two writers get
     * two files instead.
     *
     * The directory is never created here. It is the installer's, which gives
     * it to the web user with the right SELinux label (GH-964: /opt/fog
     * inherits usr_t and httpd_t may read it but not write it, so an
     * unlabeled mkdir would produce a directory that looks right and
     * silently swallows every write on an enforcing host).
     *
     * @return string
     */
    private static function _faultLogPath()
    {
        if (!defined('FOG_LOG_DIR')) {
            return '';
        }
        $dir = rtrim(FOG_LOG_DIR, DS) . DS . self::FAULT_LOG_SUBDIR;
        if (!is_dir($dir) || !is_writable($dir)) {
            return '';
        }

        return $dir . DS . sprintf(
            'faults-%s.log',
            'cli' === PHP_SAPI ? 'service' : 'web'
        );
    }
    /**
     * Keeps one old copy once the fault log passes FAULT_LOG_MAX.
     *
     * @param string $file the fault log
     *
     * @return void
     */
    private static function _rotateFaultLog($file)
    {
        $size = @filesize($file);
        if (false === $size || $size < self::FAULT_LOG_MAX) {
            return;
        }
        @rename($file, $file . '.1');
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
        self::_writeLog(
            'DEBUG',
            'FOG_LOG_DEBUG',
            'debug_log',
            'debug-error',
            self::$debug,
            $txt,
            $data
        );
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
        self::_writeLog(
            'INFO',
            'FOG_LOG_INFO',
            'info_log',
            'debug-info',
            self::$info,
            $txt,
            $data
        );
    }
    /**
     * Redirect pages where/when necessary.
     *
     * @param string $url The url to redirect to
     *
     * @return void
     */
    public static function redirect($url = '', $status = 308)
    {
        if (self::$service) {
            return;
        }
        header('Strict-Transport-Security: "max-age=15768000"');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Robots-Tag: none');
        header('X-Frame-Options: SAMEORIGIN');
        /*
         * 308 stays the default because every existing caller redirects to a
         * fixed place and has done for years. It is the wrong answer for a
         * ONE-OFF destination: 308 is permanent and cacheable, so a browser
         * may keep sending that URL to the same target without asking again.
         * Single logout is exactly that case -- the target carries an
         * id_token_hint that is valid for one sign-in -- so those callers
         * pass 302. (fog-plugins#15)
         */
        $status = (int)$status;
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            $status = 308;
        }
        /*
         * A redirect issued while serving an AJAX page load (?contentOnly=1)
         * is followed by the XHR transparently, so whatever the TARGET
         * renders is what lands in #ajaxPageWrapper. Without the flag the
         * target renders the whole page -- sidebar, header, the lot -- inside
         * that wrapper, and the user sees the menu twice. Reported against
         * Reports -> History Report, which redirects to the activity viewer
         * (ADR 0023), but it is the same for every redirect a menu click can
         * reach: a permission denial bouncing to ?node=home, objectNotFound
         * bouncing to a node's list, the schema updater.
         *
         * Relative targets only. An absolute URL is leaving this application
         * -- OIDC single logout, a configured login/logout redirect -- and
         * must not have a FOG query parameter bolted onto it.
         */
        if (filter_input(INPUT_GET, 'contentOnly')
            && false === strpos($url, '://')
            && false === strpos($url, 'contentOnly=')
        ) {
            $url .= (false === strpos($url, '?') ? '?' : '&') . 'contentOnly=1';
        }
        header("Location: $url", true, $status);
        exit;
    }
    /**
     * Queue a flash message to be shown to the user on the next full page
     * render. The message survives a logout/redirect (see User::logout)
     * so callers can, for example, tell the user why they were signed out.
     * The shape mirrors the client $.notify(title, body, type) helper.
     *
     * @param string $body  the message body
     * @param string $title the message title
     * @param string $type  success|info|warning|error
     *
     * @return void
     */
    public static function setMessage($body, $title = '', $type = 'info')
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION['FOG_MESSAGES'][] = [
            'title' => (string)$title,
            'body' => (string)$body,
            'type' => (string)$type
        ];
    }
    /**
     * Pull and clear all queued flash messages.
     *
     * @return array list of ['title', 'body', 'type'] entries
     */
    public static function getMessage()
    {
        if (session_status() !== PHP_SESSION_ACTIVE
            || empty($_SESSION['FOG_MESSAGES'])
        ) {
            return [];
        }
        $messages = $_SESSION['FOG_MESSAGES'];
        unset($_SESSION['FOG_MESSAGES']);
        return (array)$messages;
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
            throw new \Exception(_('Key must be a string or index'));
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
            throw new \Exception(_('Key must be a string or index'));
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
            throw new \Exception(_('Key must be an array of keys or a string.'));
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
     * returns false. That side effect is required so a loadX() method's
     * own set() call doesn't see "not loaded" again, re-trigger loadItem(),
     * and recurse forever. It means a bare `if ($this->isLoaded($key))`
     * used as a standalone precondition -- anywhere the false branch isn't
     * immediately followed, in the same call, by an actual load -- will
     * silently poison the flag for the rest of the request: later code
     * that calls get($key) expecting a real lazy-loaded value instead
     * sees the (now-true) flag, skips the real load, and falls back to
     * get()'s "never set" default.
     *
     * Use isPopulated($key) instead for "do I actually have data for this
     * key" checks -- it has no such side effect. This exact confusion
     * (Host::save()'s snapins guard + assocSetter()'s own isLoaded() gate)
     * caused phantom saSnapinID=0 rows to be inserted on every FOG client
     * check-in.
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
     * Whether $key currently holds resolved data -- a pure,
     * side-effect-free predicate. Unlike isLoaded() (a recursion guard for
     * the internal lazy-load chain; see its docblock), this is safe to use
     * as a standalone precondition anywhere the caller means "do I have
     * real data for this key," e.g. "only sync this association if the
     * caller actually touched it."
     *
     * Deliberately isset(), not array_key_exists(): get()'s own fallback
     * to '' is gated the same way (`isset($this->data[$key]) ? ... : ''`),
     * so this only reports true when get() is actually about to return
     * real data instead of that fallback -- the two must agree, or a key
     * explicitly set to null would read as "populated" here while get()
     * still silently handed back ''.
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
            $_SESSION['post_request_vals'] = [];
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
     * @return string
     */
    protected static function formatByteSize($size)
    {
        $units = ['iB', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $size = (float)$size;
        if ($size <= 0) {
            return sprintf('%3.2f %s', 0, $units[0]);
        }
        // log(1024), not the DECIMAL digit count. The original picked the
        // unit with floor((strlen($size) - 1) / 3) -- three digits per
        // step, which is a step of 1000 -- and then divided by a power of
        // 1024. The two disagree for every value between 10^(3n) and
        // 1024^n, which reads as a fraction of the unit above: an agent
        // host with 968 MB of RAM (1,015,021,568 bytes, ten digits, so the
        // old code chose GiB) rendered "0.95 GiB" instead of "968.00 MiB",
        // and a 1 GB image showed "0.93 GiB". Found on the Inventory tab,
        // 2026-09-04.
        //
        // Clamped because the array ends at YiB: beyond that, keep the
        // largest unit and let the number grow rather than index past the
        // end.
        $factor = (int)floor(log($size, 1024));
        $factor = max(0, min($factor, count($units) - 1));

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
            'software' => true,
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
        $serviceEn = Route::getIds(
            'setting',
            $find,
            'value'
        );

        return array_combine(array_keys($services), $serviceEn);
    }
    /**
     * Sets the date.
     *
     * @param mixed $date The date stamp, defaults to now if not set
     * @param bool  $utc  Whether to use utc timezone or not
     *
     * @return \DateTime
     */
    /**
     * The zone stored datetimes are written in and interpreted as.
     *
     * UTC on any install that has crossed the boundary; FOG_TZ_INFO on one
     * that has not yet.
     *
     * @return \DateTimeZone
     */
    public static function storageTimeZone()
    {
        // Once the boundary is recorded, storage IS UTC and FOG_TZ_INFO has
        // stopped meaning anything about what is written -- see
        // StorageEpoch and docs/development/utc-storage-boundary.md. Gating
        // on the row rather than on a schema number is deliberate: a
        // half-upgraded install then behaves exactly as it did before,
        // rather than in some third way nobody has thought about.
        // class_exists() rather than a bare call: this is reached from
        // LoadGlobals and from the installer, and a test harness that pulls
        // FOGBase in on its own has no autoloader for anything else. A
        // missing StorageEpoch has to mean "no boundary" -- the behavior
        // before any of this -- rather than a fatal on every date in the
        // install.
        if (class_exists(StorageEpoch::class) && StorageEpoch::active()) {
            return new \DateTimeZone('UTC');
        }
        if (empty(self::$TimeZone)) {
            return new \DateTimeZone('UTC');
        }
        try {
            return new \DateTimeZone(self::$TimeZone);
        } catch (\Exception $e) {
            // An unusable name must not take out every page that shows a date.
            return new \DateTimeZone('UTC');
        }
    }
    /**
     * The zone an install shows dates in when the reader has no preference.
     *
     * Split out from displayTimeZone() when storage moved to UTC. Before the
     * boundary the two answers were the same value read twice, so nothing
     * had to distinguish them; after it, defaulting the DISPLAY zone to the
     * STORAGE zone would show UTC to every user who has not chosen
     * otherwise -- which is every user, on an install that has just
     * upgraded.
     *
     * @return \DateTimeZone
     */
    public static function defaultDisplayTimeZone()
    {
        // FOG_TZ_INFO. Before the boundary this is also the storage zone, so
        // nothing moves; after it, this is all it ever means -- the zone an
        // install shows dates in for anyone who has not chosen their own.
        if (empty(self::$TimeZone)) {
            return self::storageTimeZone();
        }
        try {
            return new \DateTimeZone(self::$TimeZone);
        } catch (\Exception $e) {
            // An unusable name must not take out every page that shows a
            // date; the storage zone is at least a real one.
            return self::storageTimeZone();
        }
    }
    /**
     * The zone datetimes are SHOWN in for whoever is asking.
     *
     * The signed-in user's own preference if they have set one, otherwise
     * FOG_TZ_INFO, which stays the install-wide default. Resolved once per
     * request and cached: this is consulted for every date on a page, and the
     * preference is a database read.
     *
     * Resolved lazily rather than in setEnv() on purpose -- the acting user is
     * not known that early, and a lazy read has no ordering to get wrong.
     *
     * @return \DateTimeZone
     */
    public static function displayTimeZone()
    {
        if (null !== self::$_displayTimeZone) {
            return self::$_displayTimeZone;
        }
        $storage = self::defaultDisplayTimeZone();
        $user = self::$FOGUser;
        // NOTHING is cached until the answer is one worth keeping.
        //
        // FOG_TZ_INFO arrives in setEnv(), which runs INSIDE LoadGlobals --
        // and LoadGlobals formats a date before it gets there. Caching the
        // answer on that first call pinned the whole request to
        // storageTimeZone()'s own fallback of UTC, whatever FOG_TZ_INFO said,
        // which on an America/Chicago install put every rendered date five
        // hours out and (through the write sites this method used to reach)
        // stored them that way too.
        //
        // So the empty case answers and returns without remembering. The
        // cache exists for the DATABASE read below, which is the only
        // expensive part and cannot happen before there is a user anyway.
        if (empty(self::$TimeZone) || !$user || !$user->isValid()) {
            return $storage;
        }
        self::$_displayTimeZone = $storage;
        try {
            $pref = (new UserPrefManager())
                ->fetch((int)$user->get('id'), self::TIMEZONE_PREF);
        } catch (\Exception $e) {
            // A preference store that cannot be read is not a reason to stop
            // rendering dates; the install-wide default still applies.
            return self::$_displayTimeZone;
        }
        if ('' === trim((string)$pref)) {
            return self::$_displayTimeZone;
        }
        try {
            self::$_displayTimeZone = new \DateTimeZone(trim((string)$pref));
        } catch (\Exception $e) {
            // A stored name the platform no longer knows -- a retired zone, or
            // a tzdata that moved underneath us. Fall back rather than throw.
        }
        return self::$_displayTimeZone;
    }
    /**
     * Drop the per-request display memos so they resolve against whoever
     * self::$FOGUser is NOW.
     *
     * Exists for exactly one caller: Identity::bind(), which swaps the
     * acting user mid-boot when a session is impersonating. Both memos above
     * read self::$FOGUser and cache the answer for the rest of the request,
     * so a value resolved before the swap would show the ADMINISTRATOR their
     * own timezone while they believe they are seeing the target's -- which
     * is the exact question impersonation exists to answer, answered wrong
     * and silently. Nothing between LoadGlobals' first date format and the
     * swap is supposed to populate them; this makes that not need to be true.
     *
     * @return void
     */
    public static function forgetDisplayPreferences()
    {
        self::$_displayTimeZone = null;
        self::$_displayTheme = null;
    }
    /**
     * A datetime the VIEWER typed, as a DateTime the database can be
     * compared against.
     *
     * niceDate() reads a value in the STORAGE zone, which is right for
     * something that came out of a column and wrong for something somebody
     * just typed into a form: they typed it while looking at a page rendered
     * in THEIR zone. Reading it as storage schedules a task, or bounds a
     * report, at an hour they did not ask for -- silently, and only for the
     * users who have set a preference, which is the worst possible
     * distribution for a bug.
     *
     * A no-op whenever the two zones are the same, which is every account
     * that has not chosen one.
     *
     * @param string $value as the viewer typed it.
     *
     * @return \DateTime
     */
    public static function viewerDate($value)
    {
        return self::niceDate(self::displayToStorage($value));
    }
    /**
     * The current time, in the zone the database is written in.
     *
     * This is what every WRITE must use. formatTime() is its display-side
     * counterpart and converts to the VIEWER's zone -- correct for something
     * on a page, wrong for something about to be stored, because it makes the
     * value depend on who happened to be signed in when the row was made.
     *
     * The two were the same function until a display zone existed at all, so
     * the write sites were written against formatTime() and were harmless
     * right up until they were not.
     *
     * @param string $format any format DateTime understands.
     *
     * @return string
     */
    public static function storageNow($format = 'Y-m-d H:i:s')
    {
        return self::niceDate('now')->format($format);
    }
    /**
     * Turns a datetime the VIEWER typed into one the database can be
     * compared against.
     *
     * The inverse of toDisplay(), and it exists so filtering stays honest: a
     * grid that shows times in the viewer's zone but filters them in the
     * storage zone answers a different question than the one on screen, and
     * near midnight it silently returns the wrong day.
     *
     * A no-op whenever the two zones are the same, which is every account
     * that has not chosen a preference.
     *
     * @param string $value 'Y-m-d H:i:s' as the viewer means it.
     *
     * @return string 'Y-m-d H:i:s' as the database holds it.
     */
    public static function displayToStorage($value)
    {
        $display = self::displayTimeZone();
        $storage = self::storageTimeZone();
        if ($display->getName() === $storage->getName()) {
            return (string)$value;
        }
        try {
            $date = new \DateTime((string)$value, $display);
        } catch (\Exception $e) {
            // Not a date we can read: hand it back untouched rather than
            // inventing a bound. The caller's own validation still applies.
            return (string)$value;
        }
        return $date->setTimezone($storage)->format('Y-m-d H:i:s');
    }
    /**
     * Reads a stored datetime and returns it in the viewer's zone.
     *
     * Interpretation and presentation are two different questions and this is
     * the only place that answers both: the value is read in the STORAGE zone,
     * because that is the zone it was written in, then moved to the DISPLAY
     * zone. Formatting the result gives the same wall-clock time the storer
     * meant, expressed where the viewer is.
     *
     * @param mixed $date The stored value, or a DateTime.
     *
     * @return \DateTime
     */
    /**
     * Renders a value that came OUT OF A COLUMN, in the viewer's zone.
     *
     * toDisplay() assumes the value means what storageTimeZone() says values
     * mean. That is true of everything written after the boundary and false
     * of everything written before it: those were written when FOG_TZ_INFO
     * was the storage zone, so reading one as UTC and converting moves it by
     * the offset between the two, silently, on data nobody can check.
     *
     * A pre-boundary value is therefore read in the zone that WAS the
     * storage zone, which is what StorageEpoch::priorZone() recorded. That
     * is not a guess about what the value means -- it is exactly the
     * interpretation FOG applied to it before this change, so an old row
     * renders the same as it always did.
     *
     * The column type is not a detail. A TIMESTAMP column has always held a
     * UTC instant and hands one back whatever the row's age, so it is never
     * pre-boundary; passing false here for a DATETIME column, or true for a
     * TIMESTAMP one, is the one mistake that turns a correct timestamp into
     * a wrong one.
     *
     * @param mixed $value      The stored value.
     * @param bool  $isDatetime Whether it came from a DATETIME column.
     *
     * @return \DateTime
     */
    public static function toDisplayStored($value, $isDatetime = true)
    {
        if (!StorageEpoch::isPreBoundary($value, $isDatetime)) {
            return self::toDisplay($value);
        }
        try {
            $out = new \DateTime(
                trim((string)$value),
                StorageEpoch::priorZone()
            );
        } catch (\Exception $e) {
            return self::toDisplay($value);
        }

        return $out->setTimezone(self::displayTimeZone());
    }
    public static function toDisplay($date = 'now')
    {
        $out = $date instanceof \DateTime ? clone $date : self::niceDate($date);
        // A zero or invalid date carries no instant to convert, and shifting
        // one can push it across a day boundary and change how it renders.
        if (!self::validDate($out)) {
            return $out;
        }
        return $out->setTimezone(self::displayTimeZone());
    }
    /**
     * The viewer's chosen color theme.
     *
     * Three states, and the difference between two of them is the whole
     * point:
     *
     *  - ''      follow the operating system, in EITHER direction. Somebody
     *            who has never touched the setting and runs a dark desktop
     *            gets dark. Collapsing this into 'light' would make the
     *            default wrong for exactly the people who care most.
     *  - 'light' force light whatever the system says.
     *  - 'dark'  force dark whatever the system says.
     *
     * Only the last two can be answered here at all: '' is resolved by the
     * browser, because prefers-color-scheme is not something the server can
     * see. The page shell stamps data-bs-theme on <html> for a forced choice
     * and leaves the attribute off otherwise, which is the signal its
     * pre-paint script uses to decide whether to resolve the system
     * preference itself.
     *
     * @return string '' , 'light' or 'dark'.
     */
    public static function displayTheme()
    {
        if (null !== self::$_displayTheme) {
            return self::$_displayTheme;
        }
        self::$_displayTheme = '';
        $user = self::$FOGUser;
        // The same guard displayTimeZone() uses: unset before LoadGlobals has
        // run, and invalid for anyone not signed in -- the login page has no
        // session to hold a preference, and falls back to the system.
        if (!$user || !$user->isValid()) {
            return self::$_displayTheme;
        }
        try {
            $pref = (new UserPrefManager())
                ->fetch((int)$user->get('id'), self::THEME_PREF);
        } catch (\Exception $e) {
            // A preference store that cannot be read is not a reason to fail
            // the render; following the system is the documented default.
            return self::$_displayTheme;
        }
        $pref = trim((string)$pref);
        // Anything else stored -- a value from a future release, or something
        // hand-written into the table -- means the same as no opinion.
        if ('light' === $pref || 'dark' === $pref) {
            self::$_displayTheme = $pref;
        }
        return self::$_displayTheme;
    }
    public static function niceDate($date = 'now', $utc = false)
    {
        /*
         * GH-1245: an empty value means "this never happened", not "now".
         *
         * new \DateTime('') and new \DateTime(null) both return the CURRENT
         * time, so a date column holding no value renders as a real
         * timestamp. That has stayed hidden because FOGController::save()
         * writes '' into date columns and PDODB clears sql_mode on every
         * connection, so the server coerces it to '0000-00-00 00:00:00' --
         * and THAT parses to year -0001, which validDate() rejects and
         * formatTime() renders as "No Data". The empty case is only reached
         * by the columns that are already nullable, where it is wrong today:
         * a NULL tasks.stateChangedTime currently shows the current time in
         * the task grid.
         *
         * Mapping empty onto the same zero date makes the two spellings of
         * "no value" render identically, which is also what lets the columns
         * move to NULL without the display changing -- FOGController::get()
         * hands back '' for a NULL column, because isset() is false for null.
         *
         * Callers that genuinely want the current time pass 'now', which is
         * this method's own default.
         */
        if (null === $date || (is_string($date) && '' === trim($date))) {
            $date = '0000-00-00 00:00:00';
        }
        //we could optionally just catch 'No Data' or any !validDate dates and change them to now
        // if ($date !== 'now' && (!self::validDate($date))) {
        //      $date = 'now';
        // }
        // The STORAGE zone, deliberately: this call interprets a value that
        // has already been written, and is also what the write sites format
        // through. Presentation is a separate question -- see toDisplay().
        $tz = $utc ? new \DateTimeZone('UTC') : self::storageTimeZone();
        //Added try catch to catch when an invalid date is being brought in
        try {
            $niceDate = new \DateTime($date, $tz);
        } catch (\Exception $e) {
            throw new \Exception("Given date of '$date' is invalid! Can't create nicedate!");
            $niceDate = $date;
        }
        return $niceDate;
        // return new DateTime($date, $tz);
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
        if (!$time instanceof \DateTime) {
            $time = self::niceDate($time, $utc);
        }
        // Everything below this line is OUTPUT, so it happens in the viewer's
        // zone -- unless the caller asked for UTC explicitly, which is a
        // request for a specific zone and not a display preference.
        //
        // $now moves with it. The "today / yesterday / runs today" branches
        // compare calendar days, and comparing a day in one zone against a day
        // in another is how a timestamp ends up labeled "Ran Yesterday" on
        // the same afternoon it happened.
        if (!$utc) {
            $time = self::toDisplay($time);
        }
        if ($format) {
            if (!self::validDate($time)) {
                return _('No Data');
            }

            return $time->format($format);
        }
        $now = $utc
            ? self::niceDate('now', true)
            : self::toDisplay('now');
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
     * validDate(), expressed in SQL, for one column.
     *
     * The same question as validDate() asked of the server instead of of
     * PHP, and here for the same reason: there is ONE definition of what an
     * empty date is, and getting it half right -- NULL but not the zero
     * date, or the other way round -- produces a plausible number rather
     * than an error. Both spellings have to be covered because an upgraded
     * server carries both until schema step 344 has run.
     *
     * Concatenated, not bound: a column name is not a value, so it cannot
     * be a placeholder. Callers pass a literal column reference they wrote
     * themselves -- never anything off a request.
     *
     * @param string $column a fully qualified, backtick-quoted column
     *
     * @return string a SQL boolean expression, already parenthesised
     */
    protected static function noDateSql($column)
    {
        return "($column IS NULL OR $column = '0000-00-00 00:00:00')";
    }
    /**
     * Checks if the time passed is valid or not.
     *
     * FALSY FOR EVERY FORM OF "no date": '', NULL, an unparseable string,
     * and both spellings of the zero date ('0000-00-00' and
     * '0000-00-00 00:00:00'). That makes it the one definition of what an
     * empty date means, which is why
     * tests/date-columns-nullable.test.php fails a 0000-00-00 literal
     * written anywhere else.
     *
     * It returns a DateTime rather than true on the way through, so it
     * reads as a predicate and is usable as one. The tag said `object`,
     * which claimed it could never be falsy and made every truth test on
     * it look redundant to a static analyzer.
     *
     * @param mixed $date   the date to use
     * @param mixed $format the format to test
     *
     * @return bool|\DateTime false when the value is not a usable date
     */
    protected static function validDate($date, $format = '')
    {
        if ($format == 'N') {
            if ($date instanceof \DateTime) {
                return $date->format('N') >= 0;
            } else {
                return $date >= 0 && $date <= 7;
            }
        }
        try {
            if (!$date instanceof \DateTime) {
                $date = self::niceDate($date);
            }
        } catch (\Exception $e) {
            return false;
        }
        if (!$format) {
            $format = 'm/d/Y';
        }
        $tz = self::storageTimeZone();

        return \DateTime::createFromFormat(
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
            throw new \Exception(_('Space variable must be boolean'));
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
     * @return string
     */
    protected static function diff($start, $end, $ago = false)
    {
        if (!is_bool($ago)) {
            throw new \Exception(_('Ago must be boolean'));
        }
        if (!$start instanceof \DateTime) {
            $start = self::niceDate($start);
        }
        if (!$end instanceof \DateTime) {
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
            throw new \Exception(_('Diff parameter must be numeric'));
        }
        if (!is_string($unit)) {
            throw new \Exception(_('Unit of time must be a string'));
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
            throw new \Exception(_('Old key must be a string'));
        }
        if (!is_string($new_key)) {
            throw new \Exception(_('New key must be a string'));
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
            $array[$new_key] = \Initiator::sanitizeItems(
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
     * which is exactly the behavior before this setting existed. Sites that can
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
            // Unconfigured: preserve pre-existing behavior.
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
     * Strips a product key down to its raw alphanumeric characters.
     *
     * Removes hyphens, spaces, bullets and anything else, and uppercases
     * what remains. Shared by the host, group and Windows Key product-key
     * handling so the normalization lives in exactly one place.
     *
     * @param mixed $val the value to strip
     *
     * @return string
     */
    public static function productKeyStrip($val)
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            strtoupper((string)$val)
        );
    }
    /**
     * Formats a product key as XXXXX-XXXXX-XXXXX-XXXXX-XXXXX.
     *
     * Strips first, then regroups into hyphen-separated blocks of five.
     * A short/long value is grouped as far as it goes (no padding).
     *
     * @param mixed $val the value to format
     *
     * @return string
     */
    public static function productKeyFormat($val)
    {
        $stripped = self::productKeyStrip($val);
        if ($stripped === '') {
            return '';
        }
        return rtrim(chunk_split($stripped, 5, '-'), '-');
    }
    /**
     * Validates a Windows product key as true Base24.
     *
     * A valid key is exactly 25 characters drawn only from the Base24
     * alphabet (BCDFGHJKMPQRTVWXY2346789). This is the tightest definition
     * -- it excludes A E I O U L N S Z and 0 1.
     *
     * @param mixed $val the value to validate
     *
     * @return bool
     */
    public static function productKeyIsValid($val)
    {
        return (bool)preg_match(
            '/^[BCDFGHJKMPQRTVWXY2346789]{25}$/',
            self::productKeyStrip($val)
        );
    }
    /**
     * Tells whether a value is a masked product key.
     *
     * Masked keys carry the bullet character used by productKeyMask(); the
     * key entry alphabet never produces one, so its presence unambiguously
     * marks "this is the masked placeholder, not a real key".
     *
     * @param mixed $val the value to test
     *
     * @return bool
     */
    public static function productKeyIsMasked($val)
    {
        return strpos((string)$val, '•') !== false;
    }
    /**
     * Masks a product key for display.
     *
     * A valid key shows its first and last groups with the middle three
     * bulleted (ABCDE-•••••-•••••-•••••-VWXYZ). Any other non-empty value
     * is fully bulleted so legacy/encrypted blobs never leak. Empty stays
     * empty, and an already-masked value is returned unchanged so
     * redisplaying a posted-back value is idempotent.
     *
     * @param mixed $val the value to mask
     *
     * @return string
     */
    public static function productKeyMask($val)
    {
        $val = (string)$val;
        if ($val === '') {
            return '';
        }
        if (self::productKeyIsMasked($val)) {
            return $val;
        }
        $bullets = str_repeat('•', 5);
        if (self::productKeyIsValid($val)) {
            $groups = str_split(self::productKeyStrip($val), 5);
            return implode(
                '-',
                [
                    $groups[0],
                    $bullets,
                    $bullets,
                    $bullets,
                    $groups[4],
                ]
            );
        }
        return implode('-', array_fill(0, 5, $bullets));
    }
    /**
     * Resolves a posted product-key value against the stored one.
     *
     * Mirrors the AD-password masking contract: if the posted value is the
     * masked placeholder the field was untouched, so the stored value is
     * kept as-is. An emptied field clears the key. Anything else must be a
     * well-formed Base24 key or an exception is thrown (strict entry).
     *
     * @param mixed  $posted the value posted from the form
     * @param string $stored the currently-stored value (kept when masked)
     *
     * @throws Exception when a non-empty value is not a valid product key
     *
     * @return string the value to persist
     */
    public static function productKeyResolve($posted, $stored = '')
    {
        if (self::productKeyIsMasked($posted)) {
            return (string)$stored;
        }
        if (self::productKeyStrip($posted) === '') {
            return '';
        }
        if (!self::productKeyIsValid($posted)) {
            throw new \Exception(_('Invalid Windows product key'));
        }
        return self::productKeyFormat($posted);
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
        if (!($iv = pack('H*', $data[0]))) {
            return '';
        }
        if (!($encoded = pack('H*', $data[1]))) {
            return '';
        }
        if (!$key && $data[2]) {
            if (!($key = pack('H*', $data[2]))) {
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
            throw new \Exception('#!ih');
        }
        if (!self::$Host->get('pub_key')) {
            throw new \Exception('#!ihc');
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
        $sslfile = Route::getIds(
            'storagenode',
            [],
            'sslpath'
        );
        foreach ($sslfile as &$path) {
            if (!file_exists($path) || !is_readable($path)) {
                continue;
            }
            $tmpssl[] = $path;
            unset($path);
        }
        if (count($tmpssl ?: []) < 1) {
            throw new \Exception(_('Private key path not found'));
        }
        $sslbase = str_replace(
            ['\\', '/'],
            [DS, DS],
            $tmpssl[0]
        );
        unset($tmpssl);
        /**
         * .srvprivate.key, always.
         *
         * Historically this file was the client communication key AND the web
         * vhost's TLS private key, which is why replacing the web certificate
         * -- an ACME renewal, --recreate-keys, a purchased cert dropped in
         * place -- silently broke client authentication while installing a
         * perfectly valid certificate.
         *
         * The installer now issues the web certificate from a separate Web CA
         * with its own keypair and leaves this file to do the one job it is
         * named for. So there is no layout to detect and nothing here changes:
         * the fix was to stop pointing the web server at this key, not to move
         * the key.
         */
        $sslfile = sprintf('%s%s.srvprivate.key', $sslbase, DS);
        if (!file_exists($sslfile)) {
            throw new \Exception(_('Private key not found'));
        }
        if (!is_readable($sslfile)) {
            throw new \Exception(_('Private key not readable'));
        }
        $sslfilecontents = file_get_contents($sslfile);
        $priv_key = openssl_pkey_get_private($sslfilecontents);
        if (!$priv_key) {
            throw new \Exception(_('Private key failed'));
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
                    throw new \Exception(_('Failed to decrypt data on server'));
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
     * Cycle the MACs and return valid.
     *
     * @param string|array $stringlist The MACs to parse.
     * @param bool         $image      Check if image type ignored.
     * @param bool         $client     Check if client type ignored.
     *
     * @return array
     */
    public static function parseMacList(
        $stringlist,
        $image = false,
        $client = false
    ) {
        $lowerAndTrim = function ($element) {
            return strtolower(trim($element));
        };

        // Convert stringlist to array and normalize MACs
        $MACs = is_array($stringlist) ? $stringlist : explode('|', $stringlist);
        $MACs = array_values(
            array_unique(
                array_filter(
                    array_map(
                        $lowerAndTrim,
                        $MACs
                    )
                )
            )
        );

        if (empty($MACs)) {
            return [];
        }

        // Apply pending MAC filter
        $ignoreList = array_values(
            array_unique(
                array_filter(
                    array_map(
                        $lowerAndTrim,
                        explode(
                            ',',
                            self::getSetting('FOG_QUICKREG_PENDING_MAC_FILTER')
                        )
                    )
                )
            )
        );
        if (!empty($ignoreList)) {
            $pattern = sprintf(
                '#%s#i',
                implode('|', $ignoreList)
            );
            $MACs = array_values(
                array_unique(
                    array_filter(
                        array_diff(
                            $MACs,
                            preg_grep($pattern, $MACs)
                        )
                    )
                )
            );
        }

        if (empty($MACs)) {
            return [];
        }

        // Check for existing MACs
        $count = Route::getCount(
            'macaddressassociation',
            [
                'mac' => $MACs,
                'pending' => [0, '']
            ]
        );
        if ($count > 0) {
            $existingMACs = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            $lowerAndTrim,
                            Route::getIds('macaddressassociation', ['mac' => $MACs, 'pending' => [0, '']], 'mac')
                        )
                    )
                )
            );
            $MACs = array_values(
                array_unique(
                    array_merge(
                        $MACs,
                        $existingMACs
                    )
                )
            );
        }

        // Apply client ignore filter
        if ($client) {
            $clientIgnored = array_map(
                $lowerAndTrim,
                Route::getIds('macaddressassociation', ['mac' => $MACs, 'clientIgnore' => 1], 'mac')
            );
            $MACs = array_values(
                array_diff(
                    $MACs,
                    $clientIgnored
                )
            );
        }

        // Apply image ignore filter
        if ($image) {
            $imageIgnored = array_map(
                $lowerAndTrim,
                Route::getIds('macaddressassociation', ['mac' => $MACs, 'imageIgnore' => 1], 'mac')
            );
            $MACs = array_values(
                array_diff($MACs, $imageIgnored)
            );
        }

        if (empty($MACs)) {
            return [];
        }

        // Validate remaining MACs
        $validMACs = [];
        foreach ($MACs as $MAC) {
            $MACObject = new MACAddress($MAC);
            if ($MACObject->isValid()) {
                $validMACs[] = $MACObject;
            }
        }

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
                throw new \Exception('#!ih');
            }
            $datatosend = trim($datatosend);
            $curdate = self::niceDate();
            $secdate = self::niceDate(self::$Host->get('sec_time'));
            if ($curdate >= $secdate) {
                self::$Host
                    ->set('pub_key', '')
                    ->save();
                if (self::$newService || self::$json) {
                    throw new \Exception('#!ihc');
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
        } catch (\Exception $e) {
            if (self::$json) {
                //die($datatosend);
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception(_('Txt must be a string'));
        }
        if (!is_int($level)) {
            throw new \Exception(_('Level must be an integer'));
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
        // ADR 0020 decision 6: this cap is what REPLACES the unique index
        // on (hText, hTime) that schema step 355 drops. That index bounded
        // the debug firehose by discarding rows after the fact -- two
        // different events in the same second with the same prose became
        // one row, silently -- and the ADR is explicit that the bound
        // belongs on the writer instead.
        //
        // A per-request cap and NOT the level check the ADR offers as the
        // alternative. `$curlog >= $level` is unusable as a gate here: it
        // compares two arguments that real call sites already get wrong.
        // Both of user.class.php's calls (the login and the failed-login
        // rows, which are the ones that must never be dropped) passed the
        // object in the `$logbrow` slot and left `$level` at its default of
        // 1 against a `$curlog` of 0, so a level gate would have silenced
        // exactly the two events worth keeping. Their argument order is
        // fixed now, but a bound whose correctness depends on six
        // positional arguments being right at every call site -- including
        // in plugins, which this class is the base of -- is not a bound.
        //
        // The cap does not care. It never drops the first
        // LOG_HISTORY_MAX rows, so anything that logs once or twice per
        // request -- every real event -- always lands, and only a caller
        // emitting hundreds per request is stopped. hookdebugger, which
        // print_r()s every hook payload, is that caller.
        //
        // Per REQUEST rather than per second or per install: a request is
        // the unit a runaway loop lives inside, the counter needs no
        // storage, and it cannot silently swallow the first occurrence of
        // anything.
        if (self::$logHistoryRows >= self::LOG_HISTORY_MAX) {
            return;
        }
        self::$logHistoryRows++;
        // No subject: log() takes a string and has no object in hand. The
        // type still says which writer produced the row, which is what
        // separates the debug firehose from a model's own history line.
        self::logHistory($txt, ['type' => History::TYPE_LOG]);
    }
    /**
     * Log to history table.
     *
     * ADR 0020 phase 3: a row now carries the structured frame beside the
     * prose, not instead of it. The prose is unchanged and every reader
     * still reads it -- readers switch in phase 4, which is the release
     * where the discontinuity becomes visible. Until then a caller that
     * passes no frame writes exactly the row it wrote before.
     *
     * The frame is passed as an array rather than four parameters because
     * three of the five call sites have a subject and two do not, and a
     * positional list of four optional arguments at five call sites is how
     * the wrong value ends up in the wrong column silently.
     *
     * Unset frame keys are left unset rather than defaulted: save() skips a
     * null and lets the column's own DEFAULT apply, which is what keeps
     * `hSubjectID` NULL on a subjectless row instead of writing 0 -- a real
     * id that points at nothing.
     *
     * @param string $string the string to store
     * @param array  $frame  optional 'type', 'subjectType', 'subjectID' and
     *                       'subjectLabel'; see History's TYPE_ constants
     *
     * @return void
     */
    protected static function logHistory($string, array $frame = [])
    {
        if (!is_string($string)) {
            throw new \Exception(_('String must be a string'));
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
            $History = (new History())
                ->set('info', $string)
                ->set('ip', self::$remoteaddr);
            foreach (['type', 'subjectType', 'subjectID', 'subjectLabel'] as $k) {
                if (!array_key_exists($k, $frame)) {
                    continue;
                }
                $History->set($k, $frame[$k]);
            }
            $History->save();
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
     * Path to the cross-process settings cache flush signal file.
     *
     * @return string
     */
    private static function _cacheFlushFile()
    {
        // FOG_CACHE_DIR is created sticky world-writable (mode 1777) by the
        // installer so both the web tier (whose php-fpm user is not guaranteed
        // to match $apacheuser) and the CLI daemons can write the flush signal.
        return FOG_CACHE_DIR . DS . '.settings_cache_flush';
    }
    /**
     * Current mtime of the cross-process flush signal file (0 if absent).
     *
     * @return int
     */
    private static function _cacheFlushMtime()
    {
        $file = self::_cacheFlushFile();
        clearstatcache(true, $file);
        return is_file($file) ? (int) filemtime($file) : 0;
    }
    /**
     * Raise the cross-process flush signal so other processes re-read on their
     * next access. Kept world-writable so any tier can update its mtime.
     *
     * @return void
     */
    private static function _raiseFlushSignal()
    {
        $file = self::_cacheFlushFile();
        @touch($file);
        @chmod($file, 0666);
    }
    /**
     * Path to the persistent (file-backed) settings cache.
     *
     * @return string
     */
    private static function _settingsCacheFile()
    {
        return FOG_CACHE_DIR . DS . 'settings.cache.json';
    }
    /**
     * Whether the persistent file-backed cache is in use for this process.
     *
     * @return bool
     */
    private static function _useSettingsFileCache()
    {
        if (self::$settingsFileCache !== null) {
            return (bool) self::$settingsFileCache;
        }
        // Web requests are short-lived and rebuild static state every time, so
        // they benefit from a shared file; CLI daemons keep their long-lived
        // in-memory cache and never touch the shared file.
        return PHP_SAPI !== 'cli';
    }
    /**
     * Load every global setting from the database in a single query, applying
     * the same normalization as getSetting().
     *
     * @return array Map of settingKey => normalized value.
     */
    private static function _loadAllSettings()
    {
        $findStr = '\r\n';
        $repStr = "\n";
        $rows = self::$DB->query(
            "SELECT `settingKey`, `settingValue` FROM `globalSettings`"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $data = [];
        foreach ((array) $rows as $row) {
            $data[$row['settingKey']] = trim(
                str_replace($findStr, $repStr, trim($row['settingValue']))
            );
        }

        return $data;
    }
    /**
     * Whether a setting key holds sensitive data (password/token/secret) that
     * must never be written to the on-disk cache.
     *
     * Mirrors the FOG UI's own "password field" rule (a key containing "pass"
     * but not "valid"/"min"), plus tokens, secrets and private keys so that
     * plugin-provided credentials are covered too.
     *
     * @param string $key The setting key.
     *
     * @return bool
     */
    private static function _isSensitiveSettingKey($key)
    {
        if (preg_match('#pass#i', $key) && !preg_match('#(valid|min)#i', $key)) {
            return true;
        }
        return (bool) preg_match(
            '#(token|secret|privkey|private_key|apikey)#i',
            $key
        );
    }
    /**
     * Atomically (re)write the persistent settings cache file.
     *
     * Stored as JSON data (never an included PHP file) because FOG_CACHE_DIR is
     * world-writable; executing code from it would be a local RCE vector.
     * Sensitive values (passwords, tokens, secrets) are stripped before writing
     * and are served from the database instead, so they never touch disk. The
     * file is still mode 0600 (defense in depth) and read only by the web user;
     * daemons do not read it.
     *
     * @param array $data Map of settingKey => value.
     * @param int   $ts   Build timestamp.
     *
     * @return void
     */
    private static function _writeSettingsCacheFile(array $data, $ts)
    {
        if (!self::_useSettingsFileCache()) {
            return;
        }
        // Never persist sensitive values to disk; getSetting() reads them from
        // the database on demand (they are looked up rarely, not in hot loops).
        foreach (array_keys($data) as $k) {
            if (self::_isSensitiveSettingKey($k)) {
                unset($data[$k]);
            }
        }
        $json = json_encode(['ts' => (int) $ts, 'data' => $data]);
        if ($json === false) {
            return;
        }
        $file = self::_settingsCacheFile();
        $tmp = $file . '.' . getmypid() . '.' . mt_rand() . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }
    /**
     * Remove the persistent settings cache file (web tier only).
     *
     * @return void
     */
    private static function _deleteSettingsCacheFile()
    {
        if (!self::_useSettingsFileCache()) {
            return;
        }
        $file = self::_settingsCacheFile();
        clearstatcache(true, $file);
        if (is_file($file)) {
            @unlink($file);
        }
    }
    /**
     * Warm the in-memory cache from the persistent file once per request.
     *
     * On a fresh, unexpired, un-flushed file this populates every setting with
     * zero database queries. Otherwise it rebuilds the file from the database
     * in a single query so sibling requests read it for free.
     *
     * @return void
     */
    private static function _warmFromFileCache()
    {
        if (self::$_settingsFileChecked || !self::_useSettingsFileCache()) {
            return;
        }
        self::$_settingsFileChecked = true;

        $file = self::_settingsCacheFile();
        $now = time();
        $flushMtime = self::_cacheFlushMtime();

        clearstatcache(true, $file);
        $payload = null;
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $payload = json_decode($raw, true);
            }
        }

        if (is_array($payload)
            && isset($payload['ts'], $payload['data'])
            && is_array($payload['data'])
            && ($now - (int) $payload['ts']) < self::$settingsCacheTTL
            && $flushMtime <= (int) $payload['ts']
        ) {
            $ts = (int) $payload['ts'];
            foreach ($payload['data'] as $k => $v) {
                // Never clobber an entry already set this request (e.g. a
                // setSetting() write that has not yet been persisted).
                if (!isset(self::$_settingsCache[$k])) {
                    self::$_settingsCache[$k] = ['value' => $v, 'ts' => $ts];
                }
            }
            return;
        }

        // Missing, stale, or flushed: rebuild from the database and persist.
        ++self::$_settingsCacheQueries;
        $data = self::_loadAllSettings();
        foreach ($data as $k => $v) {
            self::$_settingsCache[$k] = ['value' => $v, 'ts' => $now];
        }
        self::_writeSettingsCacheFile($data, $now);
    }
    /**
     * Get global setting value by key.
     *
     * Values are served from a per-process TTL cache when available. A key is
     * (re)read from the database when it is uncached, its TTL has elapsed, or
     * the cross-process flush signal file is newer than the cached entry.
     *
     * @param string|array $key What to get
     *
     * @throws Exception
     *
     * @return string|array|null the value, null when the key has no row; the
     *                           array form holds one entry per requested key,
     *                           null in the slots that are missing
     */
    public static function getSetting($key)
    {
        if (!is_string($key) && !is_array($key)) {
            throw new \Exception(_('Key must be a string or array of strings'));
        }
        $findStr = '\r\n';
        $repStr = "\n";

        $keys = (array) $key;

        // Web tier: warm the whole cache from the shared file once per request
        // (zero queries on a fresh file). No-op for CLI daemons.
        self::_warmFromFileCache();

        // Cross-process invalidation: a flush signal newer than a cached entry
        // forces that entry to be re-read.
        $flushMtime = self::_cacheFlushMtime();
        $now = time();

        // When the file cache has warmed a coherent snapshot this request, trust
        // present entries without re-validating TTL/flush (warm already did, and
        // re-checking would trigger a redundant query). Daemons do not warm, so
        // they keep the per-entry staleness check from the in-memory design.
        $warmed = self::$_settingsFileChecked;
        $missing = [];
        foreach ($keys as $k) {
            $entry = self::$_settingsCache[$k] ?? null;
            $stale = $entry !== null && !$warmed
                && ($flushMtime > $entry['ts']
                    || ($now - $entry['ts']) >= self::$settingsCacheTTL);
            if ($entry === null || $stale) {
                $missing[] = $k;
                ++self::$_settingsCacheMisses;
            } else {
                ++self::$_settingsCacheHits;
            }
        }

        if (count($missing) > 0) {
            ++self::$_settingsCacheQueries;
            $sql = "SELECT `settingKey`, `settingValue` FROM `globalSettings` "
                . "WHERE `settingKey` IN ('"
                . implode("','", $missing)
                . "')";

            $rows = self::$DB->query($sql)
                ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

            foreach ((array) $rows as $row) {
                self::$_settingsCache[$row['settingKey']] = [
                    'value' => trim(
                        str_replace(
                            $findStr,
                            $repStr,
                            trim($row['settingValue'])
                        )
                    ),
                    'ts' => $now,
                ];
            }
        }

        if (is_string($key)) {
            return isset(self::$_settingsCache[$key])
                ? self::$_settingsCache[$key]['value']
                : null;
        }

        // One entry per requested key, in the order asked for, whether or not
        // the row exists. Every array-form caller destructures the result
        // positionally -- list($dev, $log, $zzz) = getSetting($keys) -- so
        // skipping an absent key shifted every later value one place to the
        // left and left the last variable undefined. That is the same defect
        // as issue #728 on 1.5, which reached it through getSubObjectIDs()
        // instead; there the multicast daemon took its log filename from the
        // device setting and ran with no sleep interval.
        //
        // null rather than '' to match what the single-key form above returns
        // for a missing row, so both shapes agree and the existing "value or
        // default" checks at the call sites keep working.
        $vals = [];
        foreach ($keys as $k) {
            $vals[] = isset(self::$_settingsCache[$k])
                ? self::$_settingsCache[$k]['value']
                : null;
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
     * @return bool true when the write reached the database
     */
    public static function setSetting($key, $value)
    {
        $result = (new SettingManager())->update(
            ['name' => $key],
            '',
            ['value' => trim($value)]
        );

        // Only refresh the cache on a successful write, and normalize exactly
        // as getSetting() would so cached reads match a fresh database read.
        if ($result) {
            self::$_settingsCache[$key] = [
                'value' => trim(
                    str_replace('\r\n', "\n", trim($value))
                ),
                'ts' => time(),
            ];
            // Invalidate the shared web file so sibling requests rebuild with
            // the new value on their next read. Daemons keep TTL staleness
            // (unchanged from the in-memory design), so no flush storm here.
            self::_deleteSettingsCacheFile();
        }

        return $result;
    }
    /**
     * Clear the per-process settings cache and raise the cross-process flush
     * signal so other processes re-read on their next access.
     *
     * @param string|null $key A single key to drop, or null to clear all.
     *
     * @return void
     */
    public static function clearSettingsCache($key = null)
    {
        if ($key === null) {
            self::$_settingsCache = [];
        } else {
            unset(self::$_settingsCache[$key]);
        }
        // Drop the shared file and force a re-warm; raise the cross-process
        // signal so every other process re-reads on its next access.
        self::$_settingsFileChecked = false;
        self::_deleteSettingsCacheFile();
        self::_raiseFlushSignal();
    }
    /**
     * Reload every global setting into the per-process cache using a single
     * query, then raise the cross-process flush signal.
     *
     * @return int Number of settings loaded into the cache.
     */
    public static function refreshSettingsCache()
    {
        $now = time();
        $data = self::_loadAllSettings();

        self::$_settingsCache = [];
        foreach ($data as $k => $v) {
            self::$_settingsCache[$k] = ['value' => $v, 'ts' => $now];
        }
        // We just rebuilt the in-memory cache, so mark the file as consulted,
        // persist it for sibling requests, and signal other processes.
        self::$_settingsFileChecked = true;
        self::_writeSettingsCacheFile($data, $now);
        self::_raiseFlushSignal();

        return count(self::$_settingsCache);
    }
    /**
     * Read-only snapshot of the settings cache for this process.
     *
     * Exposes counters and per-key freshness but never setting values, since
     * globalSettings holds secrets (API token, AD/MySQL passwords, etc.). On
     * the web tier the counters are per-request (static state is reset between
     * requests); in a daemon they are cumulative for the process lifetime.
     *
     * @return array
     */
    public static function getSettingsCacheStats()
    {
        $hits = self::$_settingsCacheHits;
        $misses = self::$_settingsCacheMisses;
        $reads = $hits + $misses;
        $now = time();
        $flushMtime = self::_cacheFlushMtime();

        $keys = [];
        foreach (self::$_settingsCache as $name => $entry) {
            $keys[$name] = $now - (int) $entry['ts'];
        }
        ksort($keys);

        $fileEnabled = self::_useSettingsFileCache();
        $cacheFile = self::_settingsCacheFile();
        clearstatcache(true, $cacheFile);
        $fileExists = $fileEnabled && is_file($cacheFile);

        return [
            'hits' => $hits,
            'misses' => $misses,
            'dbQueries' => self::$_settingsCacheQueries,
            'hitRatePct' => $reads > 0 ? round(($hits / $reads) * 100, 1) : 0,
            'keysCached' => count(self::$_settingsCache),
            'ttl' => self::$settingsCacheTTL,
            'flushAgeSeconds' => $flushMtime > 0 ? $now - $flushMtime : null,
            'fileCache' => [
                'enabled' => $fileEnabled,
                'exists' => $fileExists,
                'ageSeconds' => $fileExists ? $now - (int) filemtime($cacheFile) : null,
            ],
            'cachedKeys' => $keys,
        ];
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
     * Get canceled state id.
     *
     * @return int
     */
    public static function getCancelledState()
    {
        return TaskState::getCancelledState();
    }
    /**
     * Get failed state id.
     *
     * @return int
     */
    public static function getFailedState()
    {
        return TaskState::getFailedState();
    }
    /**
     * Normalizes a value to a re-indexed list of positive integer ids.
     *
     * Casts to an array, intval's every element, drops anything <= 0 (blank,
     * 0, or negative ids that would otherwise seed phantom association rows or
     * a run-order entry), and re-indexes the result.
     *
     * @param mixed $ids the raw id value(s)
     *
     * @return array list of positive ints, 0-indexed
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
     * A datatable cell linking to an entity's edit page.
     *
     * The canonical format is `Name - (id)`. The name leads because it is what
     * an admin actually reads and what every one of these grids sorts on; the
     * id follows as the disambiguator for the case where two rows share a name.
     *
     * This exists because FOG's two shared sinks disagreed with each other:
     * FOGController::getItemsList() emitted `Name - (id)` on every association
     * tab while Route::mainlink emitted `(id) - Name` on every router-backed
     * grid -- and a comment on the former claimed to mirror the latter, which
     * it never did. One helper means the next sink cannot invent a third order.
     *
     * $node is an internal literal at every call site; it is escaped anyway so
     * that stays true rather than being assumed.
     *
     * @param string $node the management node to link at
     * @param mixed  $id   the entity's id
     * @param string $name the entity's name
     *
     * @return string the cell markup
     */
    public static function entityLink($node, $id, $name)
    {
        return sprintf(
            '<a href="../management/index.php?node=%s&sub=edit&id=%d">'
            . '%s - (%d)</a>',
            \Initiator::e($node),
            (int)$id,
            \Initiator::e($name),
            (int)$id
        );
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
     * Decodes a credential FOS sent base64-encoded.
     *
     * NOT stripAndDecode(), which is what the registration path used to use.
     * That helper finishes with \Initiator::e() -- HTML escaping, which is
     * right for a value about to be rendered into a page and wrong for one
     * about to be compared against a password hash. A password containing
     * & < > " or ' arrived at password_verify() as its entity form and could
     * never match, so those accounts could not register-with-deploy while
     * working perfectly in the web UI, which does not go through that helper.
     * Forums topic 18228.
     *
     * STRICT decoding, unlike stripAndDecodeItem()'s. base64_decode() without
     * $strict silently drops every character outside the alphabet and always
     * "succeeds", so a corrupted field became a plausible wrong credential
     * rather than a refused one.
     *
     * Shared rather than written out twice because service/checkcredentials.php
     * validates the SAME credential for the SAME caller. The two disagreeing is
     * the bug: that endpoint answered '#!ok' for a password registration then
     * rejected.
     *
     * @param mixed $value the raw request value
     *
     * @return string|bool the decoded credential, or false if it was not
     *                     valid base64
     */
    public static function decodeCredential($value)
    {
        /*
         * Restore '+' from ' ' before decoding, exactly as stripAndDecode()
         * has always done. '+' is in the base64 alphabet and a bare '+' in a
         * urlencoded body decodes back to a space, so a credential whose
         * encoding contains one arrives corrupted. A space is never valid
         * base64, so the swap is lossless -- and without it the strict decode
         * below would REFUSE those credentials rather than mangle them, which
         * is a worse failure than the one being fixed.
         */
        $value = str_replace(' ', '+', trim((string) ($value ?? '')));
        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            return false;
        }

        // Trimmed to match checkcredentials.php. Both ends must agree, and a
        // credential that differs only by surrounding whitespace is not one
        // anybody can type reliably at the FOS prompt anyway.
        return trim($decoded);
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
            $item[$key] = self::stripAndDecodeItem($val);
            unset($val);
        }

        return $item;
    }
    /**
     * Strips and decodes a single value.
     *
     * The client base64-encodes some values and sends others plain, so the
     * decode is conditional: the decoded bytes are used only when they are
     * valid UTF-8, otherwise the original (plain) value is kept. The result
     * is then sanitized the same way every request value is. Exposed so code
     * that reads a raw request value directly (e.g. filter_input(), which is
     * not affected by stripAndDecode() rewriting $_REQUEST) can normalize it
     * identically.
     *
     * @param mixed $val the value to strip and decode
     *
     * @return string
     */
    public static function stripAndDecodeItem($val)
    {
        $val = (string) ($val ?? '');
        $tmp = str_replace(' ', '+', $val);
        $tmp = base64_decode($tmp);
        $tmp = trim($tmp);
        if (mb_detect_encoding($tmp, 'utf-8', true)) {
            $val = $tmp;
        }

        return \Initiator::e(trim($val));
    }
    /**
     * Strips and decodes a mac, or a '|' separated list of macs.
     *
     * FOS base64-encodes the mac on some paths (registration, deploy) and
     * sends it plain on others (checkin, the standalone inventory task), so
     * the encoding has to be sniffed. The sniff cannot be the one
     * stripAndDecodeItem() uses -- "do the decoded bytes happen to be valid
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
            return \Initiator::e($mac);
        }
        $decoded = trim(base64_decode(str_replace(' ', '+', $mac)));
        if (self::isMacList($decoded)) {
            return \Initiator::e($decoded);
        }

        // Neither shape matched; hand back the plain value so the caller
        // reports the mac it was actually sent.
        return \Initiator::e($mac);
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
        @natcasesort($IPs);
        $retIPs = function ($IP) {
            $IP = trim($IP);
            if (!filter_var($IP, FILTER_VALIDATE_IP)) {
                $IP = gethostbyname($IP);
            }
            if (filter_var($IP, FILTER_VALIDATE_IP)) {
                return $IP;
            }
        };
        $retNames = function ($IP) {
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
            ['127.0.0.1', '127.0.1.1', self::getSetting('FOG_WEB_HOST')]
        );
        unset($IPs, $Names);
        @natcasesort($output);
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
            // SKIP_DOTS is a RecursiveDirectoryIterator flag, not a
            // RecursiveIteratorIterator mode. Passing it as the mode left the
            // directory iterator including '.' and '..', so their inode sizes
            // (and every subdirectory's) were added to the total, and the
            // mode -- 4096, not LEAVES_ONLY -- stopped the walk descending at
            // all. A FOG image is a directory, so both errors hit image sizing
            // and replication's size comparison.
            $di = new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS
            );
            $rii = new \RecursiveIteratorIterator($di);
            foreach ($rii as $file) {
                $size += filesize($file);
            }
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
            (new Setting())
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
        $StorageNodes = Route::getList(
            'storagenode',
            ['isEnabled' => 1]
        );
        foreach ($StorageNodes as &$StorageNode) {
            // getItem(), not indiv(): a node deleted between the list and the
            // fetch answers null here rather than ending the response.
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode || !$StorageNode->online) {
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
            false,
            false,
            ['X-FOG-Node-Secret: ' . self::nodeSecret()]
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
        return (new User())
            ->validatePw($username, $password, $remember);
    }
    /**
     * Proves a credential without establishing a session.
     *
     * For callers that have no browser to carry a session -- the iPXE boot
     * menu and service/ipxe/advanced.php -- where attemptLogin() would
     * otherwise stamp $_SESSION['FOG_USER'] for a request that can never
     * present the cookie back.
     *
     * Returns a User either way, exactly like attemptLogin(), so callers
     * MUST test isValid(). A returned object is never itself the answer.
     *
     * @param string $username the username to attempt
     * @param string $password the password to attempt
     *
     * @return object
     */
    public static function authenticateOnly($username, $password)
    {
        return (new User())
            ->authenticate($username, $password);
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
        return Route::getCount('oui');
    }
    /**
     * Resolves a hostname to its IP address
     *
     * Optionally remembers FAILURES for $negativeTtl seconds, and only
     * failures. The asymmetry is the whole design, and it is measured rather
     * than assumed -- on the reference server:
     *
     *   a name that resolves     :    0.2 ms local, 23.8 ms remote
     *   a name that does not     : 3776.3 ms, every single time
     *
     * A miss costs a full resolver timeout and nothing anywhere caches it:
     * resolving the same dead name twice in a row costs 3.75 s twice. That is
     * what makes a PingHosts cycle on a fleet that is not in DNS take 5m26s
     * instead of 2s.
     *
     * So a success is re-resolved on every call -- it is cheap, and it is the
     * answer that must not go stale, because a host whose address moved and
     * is pinged at its old one reports the wrong thing. A failure is held,
     * because it is expensive and because a stale failure is not a new wrong
     * answer: the caller already treats an unresolvable name as unreachable,
     * so a cached failure produces byte-identical behavior to a fresh one.
     * The only thing it can miss is a name that STARTS resolving mid-run,
     * which is why the entry expires rather than being permanent.
     *
     * $negativeTtl defaults to 0, which is no caching at all, so every
     * existing caller behaves exactly as it did.
     *
     * @param string $host        the item to test
     * @param int    $negativeTtl seconds to remember a failure, 0 to not
     *
     * @return string
     */
    public static function resolveHostname($host, $negativeTtl = 0)
    {
        $host = trim($host);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $negativeTtl = (int)$negativeTtl;
        $now = time();
        if ($negativeTtl > 0
            && isset(self::$unresolved[$host])
            && self::$unresolved[$host] > $now
        ) {
            return $host;
        }
        self::$resolvercalls++;
        $resolved = trim((string)gethostbyname($host));
        // gethostbyname() hands back the name it was given when resolution
        // fails, which is the only signal it gives -- there is no error to
        // read. Ping::executeBatch() reads the same tell.
        if ($negativeTtl > 0 && $resolved === $host) {
            self::$unresolved[$host] = $now + self::negativeTtlJitter(
                $negativeTtl
            );
            return $host;
        }
        unset(self::$unresolved[$host]);
        return $resolved;
    }
    /**
     * How long one failure is remembered for: somewhere in the upper half of
     * $ttl, chosen per name.
     *
     * Without the spread every name cached in a single cycle expires in the
     * same one, so the cost this removes comes back in a lump -- an 88-host
     * fleet would run 2 s cycles for an hour and then one 5m26s cycle,
     * forever. Jittered, the re-checks dribble across the window a few names
     * at a time and no cycle is the expensive one.
     *
     * The lower bound is $ttl/2 rather than 0 so a short $ttl cannot collapse
     * to "cache nothing"; the upper bound is $ttl so the documented ceiling
     * on staleness is the ceiling.
     *
     * @param int $ttl the configured ceiling, in seconds
     *
     * @return int
     */
    public static function negativeTtlJitter($ttl)
    {
        $ttl = (int)$ttl;
        if ($ttl < 2) {
            return $ttl > 0 ? $ttl : 0;
        }
        return rand((int)ceil($ttl / 2), $ttl);
    }
    /**
     * How many times the system resolver has been called this process.
     *
     * @return int
     */
    public static function resolverCalls()
    {
        return self::$resolvercalls;
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
     * @param array $files The array of files.
     *
     * @return void
     */
    public static function startClassFromFiles($files)
    {
        foreach ($files as &$file) {
            // Derives the FQCN from the path -- FOG\Hooks\<Class> for core,
            // FOG\Plugins\<Segment>\<Bucket>\<Class> for a plugin. That is
            // also the short circuit below: neither answers to a bare
            // basename now that the global aliases are gone (ADR 0013 sec 2).
            $className = self::classFromDiscoveredFile($file);
            if (class_exists($className, false)) {
                continue;
            }
            // The file list is a TTL-cached snapshot (Initiator::
            // pluginFileList), so it is ALLOWED to be stale -- that is the
            // documented design, and forgetClassFileList() exists because of
            // it. This was the one consumer that treated staleness as fatal:
            // a file named by the cache and since removed produced an
            // include_once warning and then an uncaught ReflectionException
            // out of getClass(), which is a bodyless 500 on every page of the
            // site for the rest of the TTL.
            //
            // Not hypothetical. An install swaps the whole of lib/plugins for
            // a new pinned release; if that release drops a hook -- ldap lost
            // addldapapi.hook.php in fog-plugins v1.6.11 -- every request in
            // the remaining TTL window dies in LoadGlobals, and the installer
            // reports "Checking web server serves FOG ... Failed!" with an
            // empty body. It then heals itself, which is why it reads as a
            // flaky install rather than a bug.
            //
            // Skipped and logged rather than swallowed: a listener that does
            // not load is a feature that silently stops happening, so it has
            // to leave a trace.
            //
            // error_log(), not self::error(). _writeLog() is gated on
            // `self::$mySchema >= FOG_SCHEMA` and on a globalSettings lookup,
            // and this runs inside LoadGlobals during an install -- which is
            // to say it would be silently dropped in exactly the situation it
            // exists to report. The PHP error log is also where the fatal
            // this replaces showed up, so both lines land in one place for
            // whoever is reading. Precedent: authorization.class.php,
            // hostmanager.class.php.
            if (!is_file($file)) {
                error_log(
                    sprintf(
                        'FOG startClassFromFiles: %s is in the cached class'
                        . ' file list but no longer exists; skipping %s.'
                        . ' Harmless if a plugin was just updated -- the list'
                        . ' refreshes on its own.',
                        $file,
                        $className
                    )
                );
                continue;
            }
            // Second guard, different cause: the file is present but does not
            // declare the class its name promises. Same consequence if it
            // throws here -- the whole boot dies -- and the same reasoning
            // applies, so it is reported rather than fatal.
            try {
                self::getClass($className);
            } catch (\ReflectionException $e) {
                error_log(
                    sprintf(
                        'FOG startClassFromFiles: %s does not declare %s'
                        . ' (%s); skipping it.',
                        $file,
                        $className,
                        $e->getMessage()
                    )
                );
            }
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
            // Re-authentication proves a human with valid credentials is
            // present; it is not a second authorization step. Whether this
            // deletion is allowed was already decided by the node's delete
            // permission before we got here.
            $validate = (new User())
                ->passwordValidate(
                    $user,
                    $pass
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
     * Every core class file in one src/ bucket.
     *
     * The core half of discovery. Pages, hooks, reports and events used to be
     * found by a filename suffix -- lib/<dir>/<name>.<type>.php, matched by a
     * regex over a recursive scan of the whole tree. They are PSR-4 files
     * under src/<Bucket>/<Class>.php now, so the bucket directory IS the
     * question discovery is asking and there is nothing to pattern-match.
     * pluginitems() below is the same thing for a plugin (ADR 0035).
     *
     * Read off Initiator::srcFileList() rather than by walking the bucket:
     * that map is already built, already cached on its own TTL and already
     * invalidated by forgetClassFileList(), so this adds no stat to a request
     * and cannot disagree with what qualify() will resolve.
     *
     * @param string $bucket The src/ subdirectory, e.g. 'Pages'.
     *
     * @return string[] Absolute file paths.
     */
    public static function coreitems(string $bucket): array
    {
        $files = [];
        foreach (\Initiator::srcFileList() as $path) {
            if (basename(dirname($path)) === $bucket) {
                $files[] = $path;
            }
        }
        @natcasesort($files);
        return $files;
    }
    /**
     * Every class file one installed plugin bucket holds, across all plugins.
     *
     * The plugin half of discovery, and the exact mirror of coreitems(): a
     * plugin lays its PHP out the way core does, so the same question --
     * "what is in this bucket?" -- answers both (ADR 0035).
     *
     * Read off Initiator::pluginFileList(), which is walked once per request
     * and cached on the same TTL as the rest of the boot lists, so this adds
     * no stat and cannot disagree with what the autoloader will resolve.
     *
     * Filtered to INSTALLED plugins. A plugin's directory being present is
     * not consent to run its code: lib/plugins ships every bundled plugin on
     * every install, and Plugin Management is what decides which of them are
     * live. This filter is why an uninstalled plugin registers no hooks,
     * serves no page and contributes no report -- the same job the
     * $pluginsinstalled preg_grep did in the discovery-suffix scheme it
     * replaces.
     *
     * @param string $bucket The src/ subdirectory, e.g. 'Hooks'.
     *
     * @return string[] Absolute file paths.
     */
    public static function pluginitems(string $bucket): array
    {
        $installed = array_flip(
            array_map('strtolower', (array) self::$pluginsinstalled)
        );
        $files = [];
        foreach (\Initiator::pluginFileList() as $path) {
            if (basename(dirname($path)) !== $bucket) {
                continue;
            }
            if (!isset($installed[strtolower(\Initiator::pluginOf($path))])) {
                continue;
            }
            $files[] = $path;
        }
        @natcasesort($files);
        return array_values($files);
    }
    /**
     * The name of the class a discovered file declares.
     *
     * One shape reaches discovery now, and both halves of the tree use it:
     * src/<Bucket>/<Class>.php declaring FOG\<Bucket>\<Class> for core, and
     * <plugin>/src/<Bucket>/<Class>.php declaring
     * FOG\Plugins\<Segment>\<Bucket>\<Class> for a plugin.
     *
     * So this is pure derivation -- the path IS the name. It used to strip a
     * *.<type>.php suffix to recover a bare short name and hand that to
     * qualify() to be looked up in one of two maps, which meant discovery
     * could name a class no file declared (a plugin whose filename and class
     * disagreed took the whole admin UI down with an uncaught TypeError;
     * see FOGPageManager::loadPageClasses). A derived name cannot do that:
     * if the file exists, the name is right by construction, and if the class
     * inside disagrees the callers' own guards report which file lied.
     *
     * @param string $file Absolute path to the discovered file.
     *
     * @return string the fully qualified class name.
     */
    public static function classFromDiscoveredFile(string $file): string
    {
        $plugin = \Initiator::pluginFqcnFor($file);
        if ($plugin !== '') {
            return $plugin;
        }
        return 'FOG\\' . basename(dirname($file)) . '\\'
            . basename($file, '.php');
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
     * Deploying the schema requires an admin login, but logging in reads the
     * schema the deploy is about to create -- on working-1.6, StorageNode's
     * `ngmGraphColor` (migration 275) breaks login against every released FOG
     * (stable is 274). That is an unrecoverable deadlock: the installer's own
     * fallback prints this URL and tells the user to open it, and it landed on
     * a sign-in page they could never get through. GH-927.
     *
     * Widening this to an upgrade does not weaken the channel. Tier 1 already
     * accepts the identical secret with users present; this only adds the
     * leakier transport for it, bounded to the window where a deploy is
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
     * The globalSettings key holding the shared node-signing secret.
     *
     * Deliberately NOT a config.class.php constant like
     * FOG_SCHEMA_INSTALL_TOKEN: every storage node's installer generates its
     * own config.class.php with its own random values, so a constant would
     * differ on every machine and never verify. globalSettings is the one
     * store master and node genuinely share -- functions.sh points a node's
     * DATABASE_HOST at the master (see `[[ -z ${DB_host} ]] &&
     * DB_host="$snmysqlhost"`), so a row written once is readable everywhere
     * with nothing to distribute.
     *
     * That last clause holds for a TRUE storage node and only for one. A
     * peer that is itself a full FOG server -- its own DATABASE_HOST, its
     * own globalSettings -- shares no row with the master, mints its own
     * key here, and cannot verify anything the master signs. Nothing in the
     * installer distributes this value, and validNodeSignature() must never
     * mint one, so a pure receiver cannot heal itself either.
     *
     * nodeSigningKeyFor() is the answer for that topology: a per-peer key
     * on the master's storage node record, which the administrator also
     * sets as that peer's own FOG_NODE_API_KEY. Same model as ngmUser and
     * ngmPass, which have always had to be kept in step with the account
     * that actually exists on the node.
     *
     * @var string
     */
    const NODE_API_KEY_SETTING = 'FOG_NODE_API_KEY';
    /**
     * How far, in seconds, a signed request's timestamp may be from ours.
     *
     * This is the property service/nodecert.php does NOT have: its HMAC
     * covers only the payload, so a captured request is replayable forever.
     * Node traffic runs with CURLOPT_SSL_VERIFYPEER off (NODE_TLS_OPTIONS in
     * FOGURLRequests -- a node's certificate is self-signed and there is no
     * chain to check), so a capture is a realistic thing to defend against.
     *
     * Five minutes rather than something tighter because master and node
     * clocks are not disciplined to each other by anything FOG installs, and
     * the failure mode of too-tight is a node that silently serves nothing.
     * It bounds replay to the same method on the same path -- for the reads
     * this authenticates, that is a re-read of a directory listing.
     *
     * @var int
     */
    const NODE_SIGNATURE_WINDOW = 300;
    /**
     * The shared secret FOG's own components sign inter-node requests with,
     * created on first use if it is not there yet.
     *
     * Purpose-scoped on purpose. The obvious existing secret to reuse was
     * FOG_STORAGENODE_MYSQLPASS, which is what service/nodecert.php signs
     * with -- but that password is direct database access. Leaking it during
     * transport hands an attacker the whole schema; leaking this hands them
     * the ability to list directories on a node, which is all it authorizes.
     *
     * INSERT IGNORE rather than setSetting(), for two reasons. setSetting()
     * is an UPDATE through SettingManager and does nothing at all when the
     * row is absent, which is exactly the case being healed here. And the
     * UNIQUE KEY on settingKey makes the INSERT the arbiter when two
     * processes race -- both then re-read and agree, instead of the loser
     * signing with a key the verifier has already replaced.
     *
     * @return string The key, or '' if one could not be established.
     */
    public static function nodeApiKey()
    {
        $key = trim((string) self::getSetting(self::NODE_API_KEY_SETTING));
        if ($key !== '') {
            return $key;
        }
        try {
            $candidate = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            // No CSPRNG means no key. Returning '' leaves callers
            // unauthenticated, which is the safe direction: an unsigned
            // request is refused, a weakly signed one would not be.
            return '';
        }
        self::$DB->query(
            sprintf(
                "INSERT IGNORE INTO `globalSettings` (`settingKey`, "
                . "`settingDesc`, `settingValue`, `settingCategory`) "
                . "VALUES (%s, %s, %s, %s)",
                self::$DB->escape(self::NODE_API_KEY_SETTING),
                self::$DB->escape(
                    'Shared secret FOG signs its own server-to-server '
                    . 'requests with. Generated automatically; there is '
                    . 'nothing to set here, and the FOG Configuration page '
                    . 'does not show it. Delete the row to rotate the key -- '
                    . 'every component reads it from this table, so the next '
                    . 'request regenerates one and they agree again.'
                ),
                self::$DB->escape($candidate),
                self::$DB->escape('FOG Storage Nodes')
            )
        );
        // Re-read rather than trusting $candidate: on a race the INSERT was
        // ignored and the row holds the other process's value. No cache
        // flush is needed -- getSetting() never caches a key it did not
        // find, so this reads the row that just landed.
        return trim((string) self::getSetting(self::NODE_API_KEY_SETTING));
    }
    /**
     * The signing key for one peer, or '' to fall back to the shared one.
     *
     * A storage node that shares the master's database verifies with the
     * global key and needs nothing here. A peer that is a full FOG server
     * has its own globalSettings and cannot see the master's row at all, so
     * the two ends have to be given a value in common by hand.
     *
     * nfsGroupMembers.ngmKey is where it goes. The column has existed since
     * 1.5, is declared on StorageNode as `key`, and has never been read or
     * written by anything -- and it is already listed in
     * Route::$sensitiveAlwaysFields, so no route has ever emitted it and
     * none will start now.
     *
     * Matched on ngmHostname because that is what the caller has: signing
     * happens in FOGURLRequests, which knows a URL and not which node it
     * belongs to. A host that matches no node, or a node with an empty key,
     * returns '' and the caller signs with the installation-wide key --
     * which is what every existing shared-database install keeps doing.
     *
     * @param string $host The host part of the URL about to be requested.
     *
     * @return string The peer's key, or '' if it has none.
     */
    public static function nodeSigningKeyFor($host)
    {
        $host = trim((string) $host);
        if ($host === '') {
            return '';
        }
        // fetch_all rather than fetch()->get('ngmKey'): on a host that
        // matches no node the single-row form hands back the empty result
        // set itself, which casts to the string 'Array' -- a non-empty
        // "key" that signs every request to an unknown host with a
        // constant nobody can verify. Indexing a list makes "no row" and
        // "no key" the same, empty, answer.
        $rows = self::$DB->query(
            sprintf(
                'SELECT `ngmKey` FROM `nfsGroupMembers` '
                . 'WHERE `ngmHostname` = %s AND `ngmKey` <> %s LIMIT 1',
                self::$DB->escape($host),
                self::$DB->escape('')
            )
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $rows = (array) $rows;
        if (count($rows) < 1) {
            return '';
        }
        return trim((string) ($rows[0]['ngmKey'] ?? ''));
    }
    /**
     * Every key a signature reaching THIS server could legitimately carry.
     *
     * The global key first, because on a shared-database install that is
     * the one the master signed with and the common case should cost one
     * comparison.
     *
     * Then every non-empty ngmKey this server can see. Two topologies need
     * it and they need it for opposite reasons:
     *
     *   - Shared database. The master signed with the target node's own
     *     ngmKey; the node reads the same table, so the key is right there.
     *   - Standalone peer. The administrator set this server's
     *     FOG_NODE_API_KEY to match, so the global key already covers it --
     *     but this server's OWN node rows are also legitimate signers if it
     *     is a master in its own right.
     *
     * The candidate set is bounded by the number of storage nodes and each
     * miss is one hash_hmac, so the cost is not worth a cache that could
     * then go stale against a rotated key.
     *
     * @return array Distinct non-empty keys.
     */
    private static function _nodeVerificationKeys()
    {
        $keys = [];
        $global = trim((string) self::getSetting(self::NODE_API_KEY_SETTING));
        if ($global !== '') {
            $keys[] = $global;
        }
        $rows = self::$DB->query(
            'SELECT `ngmKey` FROM `nfsGroupMembers` '
            . "WHERE `ngmKey` <> ''"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ((array) $rows as $row) {
            $candidate = trim((string) ($row['ngmKey'] ?? ''));
            if ($candidate !== '') {
                $keys[] = $candidate;
            }
        }
        return array_values(array_unique($keys));
    }
    /**
     * The exact bytes both ends run through hash_hmac().
     *
     * Method and path are in the signed material so a captured signature
     * cannot be lifted onto a different request -- a GET of a directory
     * listing must not become a POST of anything. The timestamp is in it so
     * it cannot be adjusted to widen the window it was issued for.
     *
     * @param string $method    The HTTP method, upper case.
     * @param string $uri       Path plus query string, exactly as sent.
     * @param string $timestamp Unix seconds, as a decimal string.
     *
     * @return string
     */
    private static function _nodeSignaturePayload($method, $uri, $timestamp)
    {
        return $timestamp . "\n" . $method . "\n" . $uri;
    }
    /**
     * Headers proving a request came from this FOG installation.
     *
     * Header-only, for the reason installTokenHeader() already sets out: a
     * header cannot be set by a cross-site form, a link or an <img>, and it
     * never lands in browser history, a bookmark, a Referer or an access
     * log. A query parameter would put a long-lived shared secret in every
     * one of those.
     *
     * The signature covers path-and-query rather than the whole URL, so the
     * http -> https redirect FOG's own vhost issues does not invalidate it.
     *
     * @param string $url    The URL about to be requested.
     * @param string $method The HTTP method that will be used.
     *
     * @return array Header lines, or an empty array when unavailable.
     */
    public static function nodeSignatureHeaders($url, $method = 'GET')
    {
        $parts = parse_url((string) $url);
        if ($parts === false) {
            return [];
        }
        // The peer's own key if it has one, otherwise the installation-wide
        // key. Ordered this way round so a shared-database install -- where
        // no ngmKey is ever set -- signs exactly as it did before, and a
        // full FOG server registered as a peer gets a secret that is only
        // good for talking to it.
        //
        // Resolved before the empty-key bail below, because "this peer has
        // no key of its own" is not the same as "this installation has no
        // key" and only the second is a reason to give up.
        $key = self::nodeSigningKeyFor($parts['host'] ?? '');
        if ($key === '') {
            $key = self::nodeApiKey();
        }
        if ($key === '') {
            return [];
        }
        $uri = ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $uri .= '?' . $parts['query'];
        }
        $timestamp = (string) time();
        $signature = hash_hmac(
            'sha256',
            self::_nodeSignaturePayload(
                strtoupper((string) $method),
                $uri,
                $timestamp
            ),
            $key
        );
        return [
            'X-Fog-Node-Timestamp: ' . $timestamp,
            'X-Fog-Node-Signature: ' . $signature
        ];
    }
    /**
     * Is this request signed by a FOG component that holds the node key?
     *
     * Authentication, not authorization: it says the caller is part of this
     * installation, and nothing about what it may do. Endpoints accepting it
     * must still be ones a node is entitled to reach -- the same split
     * service/nodecert.php makes when it checks the HMAC and then separately
     * matches the source IP against a registered node.
     *
     * getSetting() rather than nodeApiKey() deliberately: verification must
     * never mint a key. If no key exists there is nothing this request can
     * have signed with, and the answer is no. That is also why a peer which
     * only ever RECEIVES signed requests cannot heal itself, and why its
     * key has to be put there by hand -- see nodeSigningKeyFor().
     *
     * More than one key can be correct. _nodeVerificationKeys() has the
     * reasoning; the short version is that the signer may have used this
     * installation's shared key or a per-peer one, and the receiver cannot
     * tell which from the request.
     *
     * @return bool
     */
    public static function validNodeSignature()
    {
        $timestamp = $_SERVER['HTTP_X_FOG_NODE_TIMESTAMP'] ?? null;
        $signature = $_SERVER['HTTP_X_FOG_NODE_SIGNATURE'] ?? null;
        if (!is_string($timestamp)
            || !is_string($signature)
            || $signature === ''
            || !ctype_digit($timestamp)
        ) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::NODE_SIGNATURE_WINDOW) {
            return false;
        }
        $keys = self::_nodeVerificationKeys();
        if (count($keys) < 1) {
            return false;
        }
        $payload = self::_nodeSignaturePayload(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            $timestamp
        );
        // Every candidate is compared, and the result is accumulated rather
        // than returned early, so the time taken does not depend on WHICH
        // key matched -- an early return would let a caller learn a node's
        // position in the list by timing it. hash_equals is already constant
        // time for the comparison itself; this keeps the loop from undoing
        // that.
        $matched = false;
        foreach ($keys as $key) {
            if (hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
                $matched = true;
            }
        }
        return $matched;
    }
    /**
     * Is the current session a FOG administrator?
     *
     * Deliberately not is_authorized(), which is true for any valid user --
     * including uType 1 mobile users -- and whose third clause nominally
     * admits a registered fog-client. Schema deploys need to mean "an admin is
     * driving this", nothing looser.
     *
     * @return bool
     */
    public static function isSchemaAdmin()
    {
        if (!self::$FOGUser || !self::$FOGUser->isValid()) {
            return false;
        }
        // RBAC is the authority. A schema deploy rewrites the whole database,
        // so the global '*' holder -- and only the global '*' holder -- gets
        // to drive one. No scoped role, however broad, qualifies.
        if (Authorization::isUnrestricted()) {
            return true;
        }
        // Legacy fallback, deliberately confined to the pre-RBAC upgrade
        // window and nothing else.
        //
        // Schema step 316 is what gives every pre-existing local account an
        // explicit role (uType 0 -> Administrator, uType 1 -> Legacy
        // Restricted). Until it has run, roleUserAssoc either does not exist
        // at all or holds no row for this user, so getPermissions() correctly
        // resolves to nothing and the check above cannot succeed. Without
        // this branch an administrator upgrading from any pre-RBAC release
        // would find the schema updater -- the one page able to create the
        // role tables in the first place -- permanently unreachable.
        //
        // Keyed on the installed schema version rather than probing for the
        // tables so that it retires itself: the moment the deploy it enables
        // finishes, mySchema is 316 and this branch is dead for good. uType
        // is a migration input here, not a standing second opinion on who is
        // an administrator.
        if (self::$mySchema >= self::RBAC_ROLE_BACKFILL_SCHEMA) {
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
                ['type' => &$type]
            );
        }
        return (int)$type === 0;
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
     * Is the given remote IP a trusted node-to-node caller?
     *
     * Used by the unauthenticated node status endpoints (freespace.php,
     * hw.php) so a master/management server can poll a storage node. A caller
     * is trusted when it either matches an exact registered storage node IP
     * (plus loopback), or falls within a CIDR/IP range configured on the
     * serving node's own storage group(s) (FOG_TRUSTED_NODE_CIDRS-style list
     * stored per group). IPv4-mapped IPv6 sources (::ffff:1.2.3.4) are
     * normalized before comparison.
     *
     * @param string|null $remoteIP the REMOTE_ADDR of the caller
     *
     * @return bool
     */
    public static function isTrustedNodeIp($remoteIP)
    {
        if (!is_string($remoteIP) || $remoteIP === '') {
            return false;
        }
        // Normalize IPv4-mapped IPv6 (::ffff:1.2.3.4) to plain IPv4 so it can
        // match an IPv4 storage node IP / CIDR.
        if (stripos($remoteIP, '::ffff:') === 0) {
            $mapped = substr($remoteIP, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $remoteIP = $mapped;
            }
        }
        // Exact match against every registered storage node IP + loopback.
        $storageNodeIPs = Route::getIds('storagenode', [], 'ip') ?: [];
        $trustedIPs = array_merge(
            (array)$storageNodeIPs,
            ['127.0.0.1', '::1']
        );
        if (in_array($remoteIP, $trustedIPs, true)) {
            return true;
        }
        // CIDR/IP ranges configured on this node's own storage group(s).
        foreach (self::getLocalGroupTrustedCIDRs() as $cidr) {
            if (self::ipInCidr($remoteIP, $cidr)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Returns the trusted CIDR/IP entries configured on the storage group(s)
     * this server is itself a member of.
     *
     * Self-identification uses the box's own interface IPs (getIPAddress) to
     * find the matching storage node row(s), then the storage group(s) those
     * rows belong to. Returns a flat list of trimmed, non-empty entries.
     *
     * @return array
     */
    protected static function getLocalGroupTrustedCIDRs()
    {
        $localIPs = self::getIPAddress();
        if (count($localIPs ?: []) < 1) {
            return [];
        }
        $groupIDs = Route::getIds('storagenode', ['ip' => $localIPs], 'storagegroupID') ?: [];
        $groupIDs = array_values(array_unique(array_filter((array)$groupIDs)));
        if (count($groupIDs) < 1) {
            return [];
        }
        $rawLists = Route::getIds('storagegroup', ['id' => $groupIDs], 'trustedcidrs') ?: [];
        $entries = [];
        foreach ((array)$rawLists as $rawList) {
            foreach (preg_split('/[\s,]+/', (string)$rawList) as $entry) {
                $entry = trim($entry);
                if ($entry !== '') {
                    $entries[] = $entry;
                }
            }
        }
        return array_values(array_unique($entries));
    }
    /**
     * Tests whether an IP falls within a CIDR range (or equals a bare IP).
     *
     * Works for both IPv4 and IPv6 by comparing the raw inet_pton bytes under
     * the prefix mask. A $cidr with no "/" is treated as an exact-IP match.
     *
     * @param string $ip   the IP to test
     * @param string $cidr the CIDR range or bare IP to test against
     *
     * @return bool
     */
    protected static function ipInCidr($ip, $cidr)
    {
        $cidr = trim((string)$cidr);
        if ($cidr === '') {
            return false;
        }
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }
        if (strpos($cidr, '/') === false) {
            $cidrBin = @inet_pton($cidr);
            return $cidrBin !== false && $ipBin === $cidrBin;
        }
        list($subnet, $bits) = explode('/', $cidr, 2);
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bits = (int)$bits;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $fullBytes = intdiv($bits, 8);
        $remBits = $bits % 8;
        if ($fullBytes > 0
            && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)
        ) {
            return false;
        }
        if ($remBits === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $remBits)) & 0xff);
        return (ord($ipBin[$fullBytes]) & ord($mask))
            === (ord($subnetBin[$fullBytes]) & ord($mask));
    }
    /**
     * Column types per table, from commons/schema-expected.php.
     *
     * Lives on FOGBase rather than FOGController because the two write
     * paths that need it are SIBLINGS, not parent and child:
     * FOGController::save() writes one row, FOGManagerController::
     * insertBatch() writes many, and both extend FOGBase directly. It
     * started on FOGController because save() was its only caller, and
     * the cost of leaving it there was that GH-1245's fix reached one of
     * the two paths -- which is how a strict server came to reject saving
     * settings and tasking a group's snapins while saving a host worked.
     *
     * Null until first asked for; built once per request.
     *
     * @var array|null
     */
    private static $columnTypes = null;

    /**
     * The declared SQL type of a column, or '' when it is not in the manifest.
     *
     * commons/schema-expected.php carries per-column types and is what
     * OpenAPI::_entitySchema() reads for the same question, so this adds no
     * new source of truth. A missing or unreadable manifest returns '', which
     * emptyValueFor() treats as "assume a string column" -- the behavior that
     * shipped before any of this.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return string
     */
    protected static function columnType($table, $column)
    {
        if (null === self::$columnTypes) {
            self::$columnTypes = [];
            $manifest = SchemaReconciler::manifest();
            $tables = isset($manifest['tables']) && is_array($manifest['tables'])
                ? $manifest['tables']
                : [];
            foreach ($tables as $tName => $tDef) {
                if (!isset($tDef['columns']) || !is_array($tDef['columns'])) {
                    continue;
                }
                foreach ($tDef['columns'] as $cName => $cType) {
                    self::$columnTypes[strtolower($tName)][strtolower($cName)]
                        = trim($cType);
                }
            }
        }
        $t = strtolower($table);
        if (!isset(self::$columnTypes[$t])) {
            self::_loadPluginColumnTypes($t);
        }
        return self::$columnTypes[$t][strtolower($column)] ?? '';
    }

    /**
     * Loads one table's columns from the server's own catalog.
     *
     * commons/schema-expected.php describes core's 67 tables and nothing
     * else, so a plugin's table is not in it -- and GH-1245's first cut
     * therefore answered '' for every plugin column, which is precisely the
     * bug it set out to fix. That was invisible while PDODB cleared sql_mode;
     * with the clear gone the server refuses the write instead of coercing
     * it, so saving an LDAP server without a port is error 1366 rather than a
     * silently stored 0. On the maintainer's own 1.6 install that is 18
     * tables, 16 enum/set and 44 integer columns.
     *
     * Not solved by adding plugin tables to the manifest: the manifest is
     * generated from core's schema and the reconciler uses it to decide what
     * to CREATE, so a plugin table listed there would be created for
     * everyone whether the plugin is installed or not.
     *
     * Asked once per table per request, and only for a table the manifest
     * does not cover -- core never gets here. An empty result is cached too,
     * so a table that genuinely does not exist is asked about once and then
     * behaves exactly as it did before this method existed.
     *
     * The definition is rebuilt as "<type> NOT NULL" rather than returned
     * raw, so columnIsNullable() reads it with the same regex it applies to
     * the manifest's strings and there is only one notion of "nullable".
     *
     * @param string $table the table, already lowercased
     *
     * @return void
     */
    private static function _loadPluginColumnTypes($table)
    {
        self::$columnTypes[$table] = [];
        try {
            $rows = self::$DB->query(
                "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty`, "
                . "`IS_NULLABLE` AS `n` FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND LOWER(`TABLE_NAME`) = :table",
                [],
                [':table' => $table]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        } catch (\Exception $e) {
            // A catalog FOG cannot read leaves every column looking unknown,
            // which is the behavior that shipped before GH-1245 rather than
            // a broken one.
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Column type lookup failed'),
                    _('Table'),
                    $table,
                    _('Error'),
                    $e->getMessage()
                )
            );
            return;
        }
        /*
         * The degradation is deliberate, the SILENCE was not. PDODB swallows
         * a rejected statement, so this never reached the catch above on a
         * real error -- it cached an empty type map and every column on the
         * table went back to being untyped, which is exactly the GH-1245 bug
         * this method exists to prevent, reappearing with nothing said.
         *
         * Behavior is unchanged: still an empty map, still asked once per
         * table per request. Only now it is written down.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Column type lookup failed'),
                    _('Table'),
                    $table,
                    _('Error'),
                    self::$DB->error,
                    _('every column on this table will be treated as untyped')
                )
            );
            return;
        }
        foreach ((array) $rows as $row) {
            if (!isset($row['c'], $row['ty'])) {
                continue;
            }
            self::$columnTypes[$table][strtolower($row['c'])] = trim($row['ty'])
                . (isset($row['n']) && strtoupper($row['n']) === 'NO' ? ' NOT NULL' : '');
        }
    }

    /**
     * Can this column hold NULL?
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return bool
     */
    protected static function columnIsNullable($table, $column)
    {
        $type = self::columnType($table, $column);
        return '' !== $type && !preg_match('/\bNOT\s+NULL\b/i', $type);
    }

    /**
     * What an unset optional field should actually be written as.
     *
     * GH-1245. save() used to write '' for every unset optional field whose
     * key does not end in "id". '' is a value only a string column can hold.
     * Everywhere else the server either refuses it under a strict sql_mode or
     * coerces it without one, and FOG only ever saw the second, because
     * PDODB::_connect() cleared sql_mode on every connection. So this is not
     * new behavior being introduced -- it is the coercion the server was
     * already performing, written down and made legal:
     *
     *   date/time  ->  NULL          (was '0000-00-00 00:00:00')
     *   integer    ->  NULL if the column is nullable, else 0
     *   enum/set   ->  first member  (was '', the error value at index 0)
     *   anything   ->  ''            (unchanged; '' is a real value here)
     *
     * The nullable half of the integer rule is GH-1572's, arrived at here
     * a second time. That fixed the branch save() takes for a key ending
     * in "id"; this is the branch it takes for every other key, and the
     * two have to answer the same way because the column does not care
     * what the model calls it. Once ADR 0031 gave a nullable int a real
     * foreign key, 0 stopped being a spelling of "no reference" and became
     * a reference to a row that does not exist -- error 1452, and the save
     * is refused outright.
     *
     * `multicastSessions`.`msSenderNode` is the column that proves it. Its
     * model key is `sendernode`, which does not end in "id", so it misses
     * GH-1572's branch entirely; step 386 made it nullable and step 388
     * gave it a foreign key to `nfsGroupMembers`. A session is created
     * before any udp-sender exists, so the field is always unset at
     * INSERT -- which means EVERY multicast session save on this branch
     * failed, from the group page, the host page and the image page
     * alike. save() reports that failure by returning false rather than
     * throwing, so Group::createImagePackage() carried on and built the
     * per-host tasks against a session id that was never allocated.
     *
     * The integer and enum choices deliberately match the coercion rather
     * than the column's DEFAULT. `hosts.hostEnforce` is declared
     * DEFAULT '1' and 73 of 86 rows on the maintainer's server hold '' --
     * so honoring the default would silently turn enforcement ON for those
     * hosts as a side effect of a storage fix. '' and '0' are both falsey in
     * PHP, so the first enum member behaves as the error value already did.
     *
     * The column's TYPE is the only reliable way to tell these apart; the
     * key's name is not, which is the lesson $databaseFieldsNotInt already
     * exists for.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return mixed the value to write; null means a real SQL NULL
     */
    protected static function emptyValueFor($table, $column)
    {
        $type = self::columnType($table, $column);
        if ('' === $type) {
            return '';
        }
        if (preg_match('/^(datetime|timestamp|date)\b/i', $type)) {
            return null;
        }
        if (preg_match('/^(tiny|small|medium|big)?int\b/i', $type)) {
            // NULL where the column can hold one: it is the honest spelling
            // of "no value", and it is the only one a foreign key accepts.
            // 0 stays for a NOT NULL column, where omitting the value is
            // error 1364 and NULL is error 1048.
            return self::columnIsNullable($table, $column) ? null : 0;
        }
        if (preg_match("/^(enum|set)\\s*\\(\\s*'((?:[^']|'')*)'/i", $type, $match)) {
            return str_replace("''", "'", $match[2]);
        }

        return '';
    }

    /**
     * Columns per table that an INSERT must name, built on demand.
     *
     * @var array
     */
    private static $requiredColumns = [];

    /**
     * Columns this table will not accept an INSERT without.
     *
     * A column is on this list when it is NOT NULL, carries no DEFAULT, and
     * is not AUTO_INCREMENT. Under a strict sql_mode, omitting one of those
     * from an INSERT is error 1364 -- "Field 'x' doesn't have a default
     * value" -- and the row is rejected outright. Without a strict mode the
     * server invents a zero value instead and says nothing, which is what FOG
     * saw for nine years because PDODB cleared sql_mode on every connection.
     *
     * Read from the server's catalog rather than commons/schema-expected.php,
     * for two reasons the manifest cannot cover. It describes core's tables
     * only, so a plugin's table would answer "nothing is required" -- the
     * same blind spot _loadPluginColumnTypes() exists for. And its per-column
     * strings drop AUTO_INCREMENT, so `stID` reads as a plain NOT NULL int
     * with no default and would be filled with 0, writing over the primary
     * key the server was about to generate.
     *
     * Asked once per table per request. A lookup that fails returns nothing,
     * which leaves the caller building exactly the statement it built before
     * this method existed -- the degradation is "no worse than before", not
     * "silently write something else".
     *
     * @param string $table the database table
     *
     * @return array column name (lowercased) => declared SQL type
     */
    protected static function columnsRequiringValue($table)
    {
        $t = strtolower((string) $table);
        if (isset(self::$requiredColumns[$t])) {
            return self::$requiredColumns[$t];
        }
        self::$requiredColumns[$t] = [];
        try {
            $rows = self::$DB->query(
                "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty` "
                . "FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND LOWER(`TABLE_NAME`) = :table "
                . "AND `IS_NULLABLE` = 'NO' "
                . "AND `COLUMN_DEFAULT` IS NULL "
                . "AND `EXTRA` NOT LIKE '%auto_increment%'",
                [],
                [':table' => $t]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        } catch (\Exception $e) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Required column lookup failed'),
                    _('Table'),
                    $t,
                    _('Error'),
                    $e->getMessage()
                )
            );
            return self::$requiredColumns[$t];
        }
        // PDODB swallows a rejected statement, so a real error arrives here
        // as an empty result rather than an exception. Same trap, and same
        // handling, as _loadPluginColumnTypes().
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Required column lookup failed'),
                    _('Table'),
                    $t,
                    _('Error'),
                    self::$DB->error
                )
            );
            return self::$requiredColumns[$t];
        }
        foreach ((array) $rows as $row) {
            if (!isset($row['c'])) {
                continue;
            }
            self::$requiredColumns[$t][strtolower($row['c'])]
                = trim((string) ($row['ty'] ?? ''));
        }
        return self::$requiredColumns[$t];
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
