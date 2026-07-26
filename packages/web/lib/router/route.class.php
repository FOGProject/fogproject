<?php
/**
 * Creates our routes for api configuration.
 *
 * PHP Version 7.4
 *
 * @category Route
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
class Route extends FOGBase
{
    /**
     * Muted em-dash used to render an empty list cell (no value / no
     * associated entity) consistently across every entity list.
     *
     * @var string
     */
    const EMPTY_CELL = '<span class="text-muted">&mdash;</span>';
    /**
     * The api setup is enabled?
     *
     * @var bool
     */
    private static $_enabled = false;
    /**
     * The currently defined token.
     *
     * @var string
     */
    private static $_token = '';
    /**
     * AltoRouter object container.
     *
     * @var AltoRouter
     */
    public static $router = null;
    /**
     * Matches from AltoRouter.
     *
     * @var array
     */
    public static $matches = [];
    /**
     * Stores the data to print.
     *
     * @var mixed
     */
    public static $data = [];
    /**
     * True only for requests that entered through the REST API
     * (api/index.php constructs a Route; the web UI calls the static
     * helpers). Lets listeners tell a genuine REST call apart from a web
     * AJAX list without trusting the client-set X-Requested-With header.
     *
     * @var bool
     */
    public static $apiRequest = false;
    /**
     * Requested relation-expansion tokens (lowercased) from ?expand=a,b,c.
     *
     * @var array
     */
    public static $expand = [];
    /**
     * True when ?expand=all was requested (expand every known relation).
     *
     * @var bool
     */
    public static $expandAll = false;
    /**
     * Re-entrancy guard so relation expansion stays depth-limited.
     *
     * @var int
     */
    protected static $expandDepth = 0;
    /**
     * Depth of the current getter() serialization stack. Non-zero means we are
     * serializing an object (or one of its sub-objects) and any listem()/getter
     * call is INTERNAL, not the entity the API route targeted. Expansion is
     * gated on this being 0 so it only decorates the top-level request and
     * never leaks into the many internal list/getter calls fired while
     * serializing relations.
     *
     * @var int
     */
    protected static $getterDepth = 0;
    /**
     * The class the current API route is serving, recorded so printer() can
     * strip secrets from a payload that does not name its own class.
     *
     * A list payload carries a '_lang' stamp, but a single-entity GET is a
     * flat object with no such marker. Rather than add one -- which would
     * change the documented response shape for every consumer -- the class is
     * kept alongside the payload and read back at the emitter.
     *
     * @var string
     */
    protected static $emitClassname = '';
    /**
     * Maximum relation-expansion depth. Related objects are serialized with
     * plain getter() (no further expansion), so expansion is one level deep
     * and cannot recurse back onto the parent entity.
     */
    const EXPAND_MAX_DEPTH = 1;
    /**
     * Hard cap on how many items a single expanded relation (or an expanded
     * list) will materialize, to bound memory. Overflow is truncated and
     * flagged with a companion `<relation>_truncated` key.
     */
    const EXPAND_MAX_ITEMS = 2500;
    /**
     * Fields stripped from any host/user object serialized as a related or
     * list item, so decrypted secrets are never exposed outside a direct
     * single-entity GET.
     *
     * @var array
     */
    /**
     * Recursion depth of deletemass(), so the lockout guard runs once per
     * operation rather than once per cascaded table.
     *
     * @var int
     */
    private static $_deleteDepth = 0;
    public static $sensitiveFields = [
        'host' => [
            'ADPass',
            'ADPassLegacy',
            'productKey',
            'pub_key',
            'sec_tok',
            'sec_time',
            'token',
        ],
        'user' => [
            'password',
            'token',
        ],
    ];
    /**
     * Fields stripped from EVERY API payload, a direct single-entity GET
     * included.
     *
     * The single-GET carve-out above exists for one reason: fog-client reads
     * a host's ADPass back out to join a domain, so that field has a real
     * consumer and cannot be closed off. Nothing reads the LDAP bind password
     * back out -- only the web tier binds with it, and it does so through the
     * ORM, not the API -- so it has no reason to leave the server at all.
     *
     * Anything listed here is also stripped from lists; the two tiers are
     * unioned for list payloads. Add a field here rather than above whenever
     * "some client legitimately needs to read this" is not true of it.
     *
     * @var array
     */
    public static $sensitiveAlwaysFields = [
        'ldap' => [
            'bindPwd',
        ],
    ];
    /**
     * globalSettings rows whose VALUE is a credential.
     *
     * Settings are the odd one out: they are key/value rows, so the secret is
     * the value of a particular *row* rather than a column present on every
     * row. A setting matched here has its 'value' removed from API output
     * while its name, description and category stay, so a consumer can still
     * see the setting exists.
     *
     * Matching is a pattern plus a short explicit list rather than a hand
     * maintained enumeration of every key, so a credential setting added
     * later is masked by default instead of silently leaking until someone
     * remembers to add it. The pattern deliberately requires PASSWORD/PASSWD/
     * PWD and not a bare "PASS": FOG_USER_MINPASSLENGTH,
     * FOG_USER_VALIDPASSCHARS and FOG_USER_VALIDPASSHELPMSG are password
     * *policy* and must stay readable for the UI to describe its own rules.
     *
     * @var string
     */
    const SENSITIVE_SETTING_PATTERN = '#(PASSWORD|PASSWD|PWD|SECRET|TOKEN)#i';
    /**
     * Credential settings the pattern does not catch.
     *
     * @var array
     */
    public static $sensitiveSettings = [
        'FOG_STORAGENODE_MYSQLPASS',
    ];
    /**
     * Settings the pattern catches that are not credentials.
     *
     * FOG_ENABLE_SHOW_PASSWORDS is a boolean toggle whose name merely
     * contains "PASSWORDS"; masking it would hide a UI preference.
     *
     * @var array
     */
    public static $sensitiveSettingsExempt = [
        'FOG_ENABLE_SHOW_PASSWORDS',
    ];
    /**
     * Stores the valid classes.
     *
     * @var array
     */
    public static $validClasses = [
        'filedeletequeue',
        'group',
        'groupassociation',
        'history',
        'hookevent',
        'host',
        'hostautologout',
        'hostscreensetting',
        'image',
        'imageassociation',
        'imagepartitiontype',
        'imagetype',
        'imaginglog',
        'inventory',
        'ipxe',
        'keysequence',
        'macaddressassociation',
        'module',
        'moduleassociation',
        'multicastsession',
        'multicastsessionassociation',
        'nodefailure',
        'notifyevent',
        'os',
        'oui',
        'plugin',
        'powermanagement',
        'printer',
        'printerassociation',
        'pxemenuoptions',
        'role',
        'rolepermission',
        'roleuserassociation',
        'roleusergroupassociation',
        'scheduledtask',
        'setting',
        'snapin',
        'snapinassociation',
        'snapingroupassociation',
        'snapinjob',
        'snapintask',
        'storagegroup',
        'storagenode',
        'task',
        'tasklog',
        'taskstate',
        'tasktype',
        'user',
        'usergroup',
        'usergroupmember',
        'usertracking',
    ];
    /**
     * Valid Tasking classes.
     *
     * @var array
     */
    public static $validTaskingClasses = [
        'filedeletequeue',
        'group',
        'host',
        'multicastsession',
        'scheduledtask',
        'snapinjob',
        'snapintask',
        'task'
    ];
    /**
     * Names not unique
     *
     * @var array
     */
    public static $nonUniqueNameClasses = [
        'filedeletequeue',
        'scheduledtask',
        'task'
    ];
    /**
     * Valid active tasking classes.
     *
     * @var array
     */
    public static $validActiveTasks = [
        'filedeletequeue',
        'multicastsession',
        'powermanagement',
        'scheduledtask',
        'snapinjob',
        'snapintask',
        'task'
    ];
    /**
     * Initialize element.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$apiRequest = true;
        list(
            self::$_enabled,
            self::$_token
        ) = self::getSetting(
            [
                'FOG_API_ENABLED',
                'FOG_API_TOKEN'
            ]
        );

        /**
         * If API is not enabled redirect to home page.
         */
        if (!self::$ajax && !self::$_enabled) {
            header(
                sprintf(
                    'Location: %s://%s/fog/management/index.php',
                    self::$httpproto,
                    self::$httphost
                ),
                true,
                308
            );
            exit;
        }
        /**
         * Routes reachable without API auth. The wildcard routes below
         * vary in their trailing segment, so they are matched on their
         * parent path via dirname(). The /system endpoints are matched
         * exactly instead: status/info are public (dashboard polling),
         * but privileged siblings like /system/export must NOT be swept
         * in by a shared parent-path match.
         */
        $unauthprefixes = [
            '/fog/bandwidth',
            '/fog/storagegroupid',
            '/fog/storagenodeid'
        ];
        $unauthexact = [
            '/fog/system/status',
            '/fog/system/info'
        ];
        $requripath = strtok((string)self::$requesturi, '?');
        $requribase = dirname($requripath);
        $isunauth = in_array($requribase, $unauthprefixes)
            || in_array(rtrim($requripath, '/'), $unauthexact);
        /**
         * Snapshot auth state BEFORE running the token/basic-auth tests.
         * At this point FOGUser is valid only when the request arrived
         * already-authenticated via the browser session cookie (populated
         * by loadglobals). Token/basic-auth API clients arrive invalid and
         * become valid inside the block below, so they are excluded here.
         * That distinction is what lets us enforce CSRF on session-cookie
         * traffic (the CSRF-able surface) without touching headless clients.
         */
        $sessionAuthed = self::$FOGUser->isValid();
        if (!$sessionAuthed
            && !$isunauth
        ) {
            /**
             * Test our token.
             */
            self::_testToken();
            /**
             * Test our authentication.
             */
            self::_testAuth();
        }
        /**
         * A valid session cookie authenticates API calls with no token,
         * so state-changing routes reached that way are CSRF-able. The web
         * UI already sends X-CSRF-Token on every same-origin request
         * (bootstrap-csrf.js); headless clients use a token instead of a
         * session cookie and are exempt via $sessionAuthed above.
         */
        if ($sessionAuthed) {
            CSRF::requireForStateChanging();
        }
        /**
         * Ensure api has unlimited time.
         */
        ignore_user_abort(true);
        set_time_limit(0);
        /**
         * Define the event so plugins/hooks can modify what/when/where.
         */
        self::$HookManager->processEvent(
            'API_VALID_CLASSES',
            ['validClasses' => &self::$validClasses]
        );
        self::$HookManager->processEvent(
            'API_TASKING_CLASSES',
            ['validTaskingClasses' => &self::$validTaskingClasses]
        );
        self::$HookManager->processEvent(
            'API_ACTIVE_TASK_CLASSES',
            ['validActiveTasks' => &self::$validActiveTasks]
        );
        /**
         * If the router is already defined,
         * don't re-instantiate it.
         */
        if (self::$router) {
            return;
        }
        self::$router = new AltoRouter(
            [],
            '/fog'
        );
        self::defineRoutes();
        self::setMatches();
        self::runMatches();
        self::printer(self::$data);
    }
    /**
     * Just ensures the where items are consistent for later use
     *
     * A STRING argument is request-supplied (the `[*:whereItems]` URL
     * segment), so its values get the caller-facing wildcard convenience --
     * see expandSearchWildcards(). An ARRAY argument came from PHP code and
     * is left exactly as passed, so a value is matched literally.
     *
     * @param string|array $whereItems The test item.
     * @return array $whereItems The normalized structure
     */
    public static function handleWhereItems($whereItems)
    {
        if (is_string($whereItems)) {
            parse_str(urldecode($whereItems), $whereItems);

            // Process comma-separated values
            foreach ($whereItems as $key => $val) {
                if (!empty($val) && strpos($val, ',') !== false) {
                    $whereItems[$key] = explode(',', $val);
                }
            }
            $whereItems = self::expandSearchWildcards($whereItems);
        }
        return $whereItems;
    }
    /**
     * Turn the caller-facing wildcards '*' and '+' into the SQL '%'.
     *
     * This USED to live in _buildSql(), where it ran against every filter
     * value including ones built in PHP -- so any internal lookup whose
     * value could legitimately contain '*' or '+' silently became a LIKE
     * against a wildcard. Authorization::adminExistsGiven() looked up the
     * holders of the RBAC global permission with ['name' => '*'], which
     * compiled to `rpName LIKE '%'` and matched every permission row; the
     * lockout guard consequently believed an administrator always remained
     * and let an install delete its last one.
     *
     * The convenience is real, but it belongs to values that came from the
     * request -- a URL where-string or the JSON search body -- not to
     * values assembled by code. Applying it at those two entry points
     * instead of in the builder keeps the feature and removes the trap.
     *
     * _buildSql() still switches to LIKE when a value contains '%', so a
     * caller that genuinely wants a pattern (the capone plugin looks up
     * 'FOG_PLUGIN_CAPONE_%') passes one and is unaffected.
     *
     * @param array $whereItems the parsed filters
     *
     * @return array
     */
    public static function expandSearchWildcards($whereItems)
    {
        foreach ((array)$whereItems as $key => $val) {
            if (is_array($val)) {
                // Array values compile to IN (...) and were never wildcarded.
                continue;
            }
            if (!is_string($val)) {
                continue;
            }
            $whereItems[$key] = str_replace(['+', '*'], '%', $val);
        }
        return $whereItems;
    }
    /**
     * Defines our standard routes.
     *
     * @return void
     */
    protected static function defineRoutes()
    {
        $expanded = sprintf(
            '/[%s:class]',
            implode('|', self::$validClasses)
        );
        $expandedt = sprintf(
            '/[%s:class]',
            implode('|', self::$validTaskingClasses)
        );
        $expandeda = sprintf(
            '/[%s:class]',
            implode('|', self::$validActiveTasks)
        );
        self::$router->map(
            'HEAD|GET',
            '/system/[status|info]',
            [__CLASS__, 'status'],
            'status'
        )->get(
            '/system/export',
            [__CLASS__, 'export'],
            'export'
        )->map(
            'GET|POST',
            '/[search|unisearch]/[*:item]/[i:limit]?',
            [__CLASS__, 'unisearch'],
            'unisearch'
        )->map(
            'PUT|POST',
            "${expanded}/join",
            [__CLASS__, 'joining'],
            'join'
        )->get(
            '/availablekernels',
            [__CLASS__, 'availablekernels'],
            'kernelUpdate'
        )->get(
            '/availableinitrds',
            [__CLASS__, 'availableinitrds'],
            'initrdUpdate'
        )->get(
            "${expandeda}/[current|active]",
            [__CLASS__, 'active'],
            'active'
        )->get(
            "${expanded}/count/[*:whereItems]?",
            [__CLASS__, 'count'],
            'count'
        )->get(
            "${expanded}/names/[*:whereItems]?",
            [__CLASS__, 'names'],
            'names'
        )->get(
            "${expanded}/ids/[*:whereItems]?/[*:getField]?",
            [__CLASS__, 'ids'],
            'ids'
        )->get(
            '/bandwidth/[*:dev]',
            [__CLASS__, 'bandwidth'],
            'bandwidth'
        )->get(
            "${expanded}/search/[*:item]",
            [__CLASS__, 'search'],
            'search'
        )->get(
            "${expanded}/[i:id]",
            [__CLASS__, 'indiv'],
            'indiv'
        )->get(
            "${expanded}/[list|all]?/[*:whereItems]?",
            [__CLASS__, 'listem'],
            'list'
        )->get(
            '/pendingmacs',
            [__CLASS__, 'pendingmacs'],
            'pendingmacs'
        )->get(
            '/whoami',
            [__CLASS__, 'whoami'],
            'whoami'
        )->get(
            '/logfiles/[i:id]',
            [__CLASS__, 'logfiles'],
            'logfiles'
        )->put(
            "${expanded}/[i:id]/[update|edit]?",
            [__CLASS__, 'edit'],
            'update'
        )->post(
            "${expandedt}/[i:id]/[task]",
            [__CLASS__, 'task'],
            'task'
        )->post(
            '/snapin/createwithfile',
            [__CLASS__, 'createSnapinWithFile'],
            'snapinCreateWithFile'
        )->post(
            '/storagegroup/[i:id]/uploadsnapinfiles',
            [__CLASS__, 'uploadSnapinFiles'],
            'uploadSnapinFiles'
        )->post(
            "${expanded}/[create|new]?",
            [__CLASS__, 'create'],
            'create'
        )->get(
            '/settings/cache',
            [__CLASS__, 'settingsCacheView'],
            'settingsCacheView'
        )->post(
            '/settings/cache/flush',
            [__CLASS__, 'settingsCacheFlush'],
            'settingsCacheFlush'
        )->post(
            '/settings/cache/refresh',
            [__CLASS__, 'settingsCacheRefresh'],
            'settingsCacheRefresh'
        )->delete(
            "${expandedt}/[i:id]?/[cancel]",
            [__CLASS__, 'cancel'],
            'cancel'
        )->delete(
            "${expanded}/[i:id]/[delete|remove]?",
            [__CLASS__, 'delete'],
            'delete'
        );
    }
    /**
     * Sets the matches variable
     *
     * @return void
     */
    public static function setMatches()
    {
        self::$matches = self::$router->match();
    }
    /**
     * Gets the matches.
     *
     * @return array
     */
    public static function getMatches()
    {
        return self::$matches;
    }

    /**
     * Runs the matches
     *
     * @return void
     */
    public static function runMatches()
    {
        if (self::$matches
            && is_callable(self::$matches['target'])
        ) {
            Authorization::requireApiPermission(
                Authorization::resolveApiPermission(
                    self::$matches['name'] ?? '',
                    self::$matches['params']['class'] ?? ''
                )
            );
            // Object-scope boundary (optional, plugin-enforced): a per-object
            // REST call carries the target id; confirm it is within the acting
            // user's scope. Inert unless a listener registers.
            Authorization::requireApiObjectScope(
                self::$matches['params']['class'] ?? '',
                self::$matches['params']['id'] ?? 0
            );
            $args = array_values(self::$matches['params']);
            // Splitting call to get closure from 'target' index of self::$matches
            // from the execution of the closure.
            // For some reason this trips up some versions of PHP, thus breaking search.
            $target = self::$matches['target'];
            $target(...$args);
            return;
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
        );
    }
    /**
     * Test token information.
     *
     * @return void
     */
    private static function _testToken()
    {
        $passtoken = base64_decode(
            filter_input(INPUT_SERVER, 'HTTP_FOG_API_TOKEN')
        );
        $passtoken = trim($passtoken);
        if (!hash_equals((string)self::$_token, (string)$passtoken)) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_FORBIDDEN
            );
        }
    }
    /**
     * Reads a $_SERVER value, preferring filter_input.
     *
     * filter_input(INPUT_SERVER) reads the SAPI's original request table,
     * not the live $_SERVER array. Keys PHP synthesises after startup --
     * PHP_AUTH_USER/PHP_AUTH_PW among them -- can therefore come back null
     * under FPM even though $_SERVER holds them. Falling back to $_SERVER
     * is the only way to see them reliably; the value is treated as
     * untrusted either way (see _basicAuthCredentials).
     *
     * @param string $key The $_SERVER key to read.
     *
     * @return string
     */
    private static function _serverVar($key)
    {
        $val = filter_input(INPUT_SERVER, $key);
        if ($val === null || $val === false) {
            $val = $_SERVER[$key] ?? '';
        }
        return (string)$val;
    }
    /**
     * Resolves HTTP basic auth credentials for the current request.
     *
     * PHP only populates PHP_AUTH_USER/PHP_AUTH_PW when the Authorization
     * header actually reaches it. Under FastCGI it does not arrive on its
     * own: nginx passes only the fastcgi_params whitelist, and Apache's
     * proxy_fcgi strips it unless CGIPassAuth/SetEnvIf puts it back. FOG's
     * installer now emits that config, but every install that predates the
     * fix still has webserver config without it, and those must keep
     * working without a reinstall -- so decode the header ourselves when
     * PHP_AUTH_USER is absent.
     *
     * REDIRECT_HTTP_AUTHORIZATION is checked too: the Apache vhost rewrites
     * /fog/* into /fog/api/index.php, and Apache prefixes REDIRECT_ onto
     * environment variables that survive an internal redirect.
     *
     * This only recovers a credential the client already sent. It is not a
     * second way in: the caller still has to clear passwordValidate() and
     * the uAllowAPI test below.
     *
     * @return array The username and password, empty strings if absent.
     */
    private static function _basicAuthCredentials()
    {
        $user = self::_serverVar('PHP_AUTH_USER');
        if ($user !== '') {
            return [$user, self::_serverVar('PHP_AUTH_PW')];
        }
        $keys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION'
        ];
        foreach ($keys as $key) {
            $header = trim(self::_serverVar($key));
            if (stripos($header, 'basic ') !== 0) {
                continue;
            }
            // strict base64 decode: a malformed header is not a credential.
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded === false || strpos($decoded, ':') === false) {
                continue;
            }
            // Split on the first colon only -- colons are legal in a
            // password, but not in a username.
            return explode(':', $decoded, 2);
        }
        return ['', ''];
    }
    /**
     * Test authentication.
     *
     * @return void
     */
    private static function _testAuth()
    {
        $usertoken = base64_decode(
            filter_input(INPUT_SERVER, 'HTTP_FOG_USER_TOKEN')
        );
        $usertoken = trim($usertoken);
        $pwtoken = self::getClass('User')
            ->set('token', $usertoken)
            ->load('token');
        if ($pwtoken->isValid() && $pwtoken->get('api')) {
            // Bind the token's owner as the acting user so role-based
            // authorization applies to token-authenticated requests.
            self::$FOGUser = $pwtoken;
            return;
        }
        list($authUser, $authPass) = self::_basicAuthCredentials();
        $auth = self::$FOGUser->passwordValidate(
            $authUser,
            $authPass
        );
        if (!$auth) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
        // passwordValidate() proves the credential; it says nothing about
        // whether this account may use the API. The token branch above
        // tests uAllowAPI, so without the same test here turning "Allow
        // API" off left basic auth as an unaffected way in.
        //
        // Reloading also gives the acting user a fully populated object,
        // matching what the token branch binds -- passwordValidate() only
        // fills in id, name and type on the object it was called against.
        $apiUser = self::getClass('User', (int)self::$FOGUser->get('id'));
        if (!$apiUser->isValid() || !$apiUser->get('api')) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
        self::$FOGUser = $apiUser;
    }
    /**
     * Sends the response code through break head as needed.
     *
     * @param int $code The code to break head on.
     * @param int $msg  The message to send.
     *
     * @return void
     */
    public static function sendResponse($code, $msg = false)
    {
        HTTPResponseCodes::breakHead(
            $code,
            $msg
        );
    }
    /**
     * Presents status to show up or down state.
     *
     * @return void
     */
    public static function status()
    {
        self::$data = [
            'version' => FOG_VERSION,
            'msg' => _('success')
        ];
    }
    /**
     * Streams a full SQL backup of the FOG database.
     *
     * Token-authenticated, headless equivalent of the management
     * "Export Database" button (management/export.php?type=sql), which
     * requires a logged-in session and CSRF token and so cannot be used
     * by scripts. This endpoint relies only on the standard API auth
     * already enforced in the constructor (fog-api-token plus an
     * api-enabled fog-user-token, or HTTP basic auth) and reuses
     * Schema::exportdb() so the dump matches the web UI byte-for-byte.
     *
     * The dump is streamed as an attachment; we exit afterward to keep
     * printer() from appending JSON to the SQL body.
     *
     * @return void
     */
    public static function export()
    {
        /**
         * Belt-and-suspenders: this streams the entire database, so never
         * serve it to an unauthenticated caller even if the routing
         * whitelist is ever mis-scoped again. A valid session bypasses
         * the token checks exactly as the constructor does.
         */
        if (!self::$FOGUser->isValid()) {
            self::_testToken();
            self::_testAuth();
        }
        $backup_name = sprintf(
            'fog_backup_%s.sql',
            self::formatTime('', 'Ymd_His')
        );
        self::getClass('Schema')->exportdb($backup_name);
        exit;
    }
    /**
     * Flushes the per-process settings cache and raises the cross-process
     * flush signal. Auth is enforced by the constructor.
     *
     * @return void
     */
    public static function settingsCacheFlush()
    {
        FOGBase::clearSettingsCache();
        self::$data = ['status' => 'flushed'];
    }
    /**
     * Read-only view of the settings cache (counters + cached key names and
     * ages, never values). Auth is enforced by the constructor.
     *
     * @return void
     */
    public static function settingsCacheView()
    {
        self::$data = FOGBase::getSettingsCacheStats();
    }
    /**
     * Reloads every global setting into the cache in a single query and
     * raises the cross-process flush signal. Auth is enforced by the
     * constructor.
     *
     * @return void
     */
    public static function settingsCacheRefresh()
    {
        self::$data = [
            'status' => 'refreshed',
            'count' => FOGBase::refreshSettingsCache(),
        ];
    }
    /**
     * Presents the equivalent of a page's list all.
     *
     * @param string $class         The class to work with.
     * @param mixed  $whereItems    Any special things to search for.
     * @param bool   $inputoverride Override php://input to blank.
     * @param string $operator      The operator for the SQL. AND is default.
     * @param string $orderby       How to order the returned values.
     *
     * @return void
     */
    public static function listem(
        $class,
        $whereItems = false,
        $inputoverride = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            if (empty($operator)) {
                $operator = 'AND';
            }
            if (!$inputoverride) {
                parse_str(
                    file_get_contents('php://input'),
                    $pass_vars
                );
                // DataTables POSTs carry pagination in the request body, but a
                // plain GET (?length=3&start=0) carries it in the query string,
                // which FOG's rewrite drops from $_GET. Fold ?length/?start in
                // so GET clients get real pagination too. Only fill fields the
                // body didn't already set.
                $qsLen = self::queryParam('length');
                if ($qsLen !== null && $qsLen !== ''
                    && !isset($pass_vars['length'])
                ) {
                    $pass_vars['length'] = (int)$qsLen;
                    $qsStart = self::queryParam('start');
                    // Default start=0 so complex()'s LIMIT is well-formed; a
                    // length without a start would otherwise LIMIT start,0 and
                    // return zero rows.
                    $pass_vars['start'] = ($qsStart !== null && $qsStart !== '')
                        ? (int)$qsStart
                        : 0;
                }
            }
            self::parseExpand();
            if (self::$getterDepth === 0
                && self::expandRequested()
                && isset($pass_vars)
            ) {
                // Expansion materializes a full object per row; bound memory
                // by clamping an unbounded/oversized page to EXPAND_MAX_ITEMS.
                $len = isset($pass_vars['length'])
                    ? (int)$pass_vars['length']
                    : 0;
                if ($len <= 0 || $len > self::EXPAND_MAX_ITEMS) {
                    $pass_vars['length'] = self::EXPAND_MAX_ITEMS;
                    if (!isset($pass_vars['start'])) {
                        $pass_vars['start'] = 0;
                    }
                }
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }
            $whereItems = self::handleWhereItems($whereItems);
            if ('snapintask' === strtolower($class)
                && isset($whereItems['jobID'])
            ) {
                $jobIDs = self::positiveIntIds($whereItems['jobID']);
                if (count($jobIDs) > 0) {
                    $whereItems['jobID'] = $jobIDs;
                } else {
                    // Force an empty result set for invalid job filters.
                    $whereItems['jobID'] = [-1];
                }
            }
            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($class);
            }

            self::$data = $columns = [];
            $classname = strtolower($class);
            $classman = self::getClass("{$classname}manager");
            $table = $classman->getTable();
            $sqlstr = $classman->getQueryStr();
            $fltrstr = $classman->getFilterStr();
            $ttlstr = $classman->getTotalStr();
            $tmpcolumns = $classman->getColumns();

            $classVars = self::getClass(
                $class,
                '',
                true
            );

            $where = self::_buildSql(
                '',
                $classVars,
                $whereItems,
                true,
                $operator,
                $orderby
            );

            /**
             * Any custom fields that we need removed
             */
            switch ($classname) {
                case 'user':
                    self::arrayRemove(
                        [
                            'password',
                            'token'
                        ],
                        $tmpcolumns
                    );
                    break;
                case 'host':
                    self::arrayRemove(
                        [
                            'sec_tok',
                            'sec_time',
                            'pub_key',
                            'ADUser',
                            'ADPass',
                            'ADPassLegacy',
                            'ADOU',
                            'ADDomain',
                            'useAD',
                            'token'
                        ],
                        $tmpcolumns
                    );
            }
            self::$HookManager->processEvent(
                'API_REMOVE_COLUMNS',
                ['tmpcolumns' => &$tmpcolumns]
            );

            // Setup our columns to return
            foreach ((array)$tmpcolumns as $common => &$real) {
                switch ($common) {
                    case 'id':
                        $tableID = $real;
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'DT_RowId',
                            'formatter' => function ($d, $row) {
                                return 'row_'.$d;
                            }
                        ];
                        break;
                    case 'name':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'mainlink',
                            'formatter' => function ($d, $row) use ($classname, $tmpcolumns) {
                                return '<a href="../management/index.php?node='
                                    . ($classname == 'pxemenuoptions' ? 'ipxe' : $classname)
                                    . '&sub=edit&id='
                                    . $row[$tmpcolumns['id']]
                                    . '">'
                                    . '(' . $row[$tmpcolumns['id']] . ') - ' . Initiator::e($d)
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'start':
                    case 'finish':
                    case 'failureTime':
                    case 'completetime':
                    case 'starttime':
                    case 'sec_time':
                    case 'checkInTime':
                    case 'scheduledStartTime':
                    case 'deployed':
                    case 'datetime':
                    case 'createdTime':
                    case 'completedTime':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common,
                            'formatter' => function ($d, $row) {
                                if (self::validDate($d)) {
                                    return self::niceDate($d)->format('Y-m-d H:i:s');
                                }
                                return self::EMPTY_CELL;
                            }
                        ];
                        break;
                    case 'pingstatus':
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'pingstatuscode',
                            'formatter' => function ($d, $row) {
                                return (int)$d;
                            }
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'pingstatustext',
                            'formatter' => function ($d, $row) {
                                return socket_strerror((int)$d);
                            }
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common,
                            'formatter' => function ($d, $row) {
                                // hostPingCode: NULL/'' = never pinged,
                                // 0 = online, any non-zero errno = unreachable.
                                // Only "online" is worth an attention color;
                                // an unreachable host is the normal resting
                                // state for a managed host, so keep it neutral
                                // and surface the specific reason as the text.
                                if ($d === null || $d === '') {
                                    return '<span class="badge bg-secondary">'
                                        . _('Not pinged')
                                        . '</span>';
                                }
                                if ((int)$d === 0) {
                                    return '<span class="badge bg-success">'
                                        . _('Online')
                                        . '</span>';
                                }
                                return '<span class="badge bg-secondary">'
                                    . _(socket_strerror((int)$d))
                                    . '</span>';
                            }
                        ];
                        break;
                    case 'groupID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'groupLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=group&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('group', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'hostID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'hostLink',
                            'formatter' => function ($d, $row) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=host&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('host', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'image':
                    case 'imageID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'imageLink',
                            'formatter' => function ($d, $row) use ($classname) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                switch ($classname) {
                                    case 'imaginglog':
                                        $image = self::getClass('Image')
                                            ->set('name', $d)
                                            ->load('name');
                                        $imageName = $d;
                                        break;
                                    default:
                                        $image = self::getClass('Image', $d);
                                        $imageName = $image->get('name');
                                }
                                if ($image->isValid()) {
                                    return '<a href="../management/index.php?node=image&'
                                        . 'sub=edit&id='
                                        . $d
                                        . '">'
                                        . '(' . $d . ') - ' . $imageName
                                        . '</a>';
                                }
                                return $imageName;
                            }
                        ];
                        break;
                    case 'snapinID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'snapinLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=snapin&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('Snapin', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'mem':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common,
                            'formatter' => function ($d, $row) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return Inventory::getMemory($d);
                            }
                        ];
                        break;
                    case 'storagegroupID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'storagegroupLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=storagegroup&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('storagegroup', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'storagenodeID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'storagenodeLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=storagenode&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('storagenode', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'userID':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                        $columns[] = [
                            'db' => $real,
                            'dt' => 'userLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return self::EMPTY_CELL;
                                }
                                return '<a href="../management/index.php?node=user&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . self::getClass('user', $d)->get('name')
                                    . '</a>';
                            }
                        ];
                        break;
                    case 'regMenu':
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common,
                            'formatter' => function ($d, $row) {
                                return PXEMenuOptionsManager::regText($d);
                            }
                        ];
                        break;
                    default:
                        $columns[] = [
                            'db' => $real,
                            'dt' => $common
                        ];
                }
                unset($real);
            }
            // Any extra columns not in the db fields.
            switch ($classname) {
                case 'host':
                    $columns[] = ['db' => 'imageName', 'dt' => 'imagename'];
                    $columns[] = ['db' => 'hmMAC', 'dt' => 'primac'];
                    // Vendor name for the primary MAC; rides along in the JSON
                    // and is rendered as a tooltip icon client-side. Not a
                    // visible column and never reaches the CSV export path.
                    $columns[] = [
                        'db' => 'hmMAC',
                        'dt' => 'primac_vendor',
                        'formatter' => function ($d, $row) {
                            return MACAddress::getVendor($d);
                        }
                    ];
                    break;
                case 'macaddressassociation':
                    // Vendor name for each MAC row (additional + pending MACs).
                    $columns[] = [
                        'db' => 'hmMAC',
                        'dt' => 'mac_vendor',
                        'formatter' => function ($d, $row) {
                            return MACAddress::getVendor($d);
                        }
                    ];
                    break;
                case 'group':
                    $columns[] = [
                        'db' => 'gmMembers',
                        'dt' => 'members',
                        'removeFromQuery' => true
                    ];
                    break;
                case 'inventory':
                    $columns[] = ['db' => 'hostName', 'dt' => 'hostname'];
                    $columns[] = [
                        'db' => 'hostID',
                        'dt' => 'hostLink',
                        'formatter' => function ($d, $row) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            // Aisle 019: this column is an intentional anchor, so
                            // the Inventory Report cannot neutralise it with
                            // DataTables render.text() the way it now does for the
                            // other columns -- escape the host name here instead.
                            // Mirrors the 'mainlink' formatter above.
                            return '<a href="../management/index.php?node=host&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . Initiator::e($row['hostName'])
                                . '</a>';
                        }
                    ];
                    break;
                case 'scheduledtask':
                    $columns[] = [
                        'db' => 'stGroupHostID',
                        'dt' => 'hostLink',
                        'formatter' => function ($d, $row) {
                            $linkName = $row['stIsGroup'] ? 'group' : 'host';
                            $capName = $row['stIsGroup'] ? 'Group' : 'Host';
                            $itemName = self::getClass($capName, $d)->get('name');
                            return sprintf(
                                '<a href="../management/index.php?node=%s&sub=edit&id=%s">%s: (%s) - %s</a>',
                                $linkName,
                                $d,
                                _($capName),
                                $d,
                                $itemName
                            );
                        }
                    ];
                    $columns[] = [
                        'db' => 'stType',
                        'dt' => 'type',
                        'formatter' => function ($d, $row) {
                            $type = strtolower($d);
                            switch ($type) {
                                case 'c':
                                    return _('Cron');
                                default:
                                    return _('Delayed');
                            }
                        }
                    ];
                    $columns[] = [
                        'db' => 'stID',
                        'dt' => 'starttime',
                        'formatter' => function ($d, $row) {
                            $type = strtolower($row['stType']);
                            switch ($type) {
                                case 'c':
                                    $cronstr = sprintf(
                                        '%s %s %s %s %s',
                                        $row['stMinute'],
                                        $row['stHour'],
                                        $row['stDOM'],
                                        $row['stMonth'],
                                        $row['stDOW']
                                    );
                                    $date = FOGCron::parse($cronstr);
                                    break;
                                default:
                                    $date = $row['stDateTime'];
                            }
                            return self::niceDate()
                                ->setTimestamp($date)
                                ->format('Y-m-d H:i:s');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stTaskTypeID',
                        'dt' => 'taskTypeName',
                        'formatter' => function ($d, $row) {
                            return self::getClass('TaskType', $d)->get('name');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stActive',
                        'dt' => 'isActive',
                        'formatter' => function ($d, $row) {
                            return $d <= 0 ? _('No') : _('Yes');
                        }
                    ];
                    break;
                case 'filedeletequeue':
                    $columns[] = [
                        'db' => 'fdqState',
                        'dt' => 'taskstateicon',
                        'formatter' => function ($d, $row) {
                            return self::getClass('taskstate', $d)->get('icon');
                        }
                    ];
                    $columns[] = [
                        'db' => 'fdqState',
                        'dt' => 'taskstatename',
                        'formatter' => function ($d, $row) {
                            return self::getClass('taskstate', $d)->get('name');
                        }
                    ];
                    break;
                case 'snapintask':
                    $columns[] = [
                        'db' => 'stJobID',
                        'dt' => 'hostID',
                        'formatter' => function ($d, $row) {
                            return self::getClass('snapinjob', $d)
                                ->get('host')
                                ->get('id');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stJobID',
                        'dt' => 'hostname',
                        'formatter' => function ($d, $row) {
                            return self::getClass('snapinjob', $d)
                                ->get('host')
                                ->get('name');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stJobID',
                        'dt' => 'hostLink',
                        'formatter' => function ($d, $row) {
                            $tmphost = self::getClass('snapinjob', $d)->get('host');
                            return '<a href="../management/index.php?node=host&'
                                . 'sub=edit&id='
                                . $tmphost->get('id')
                                . '">'
                                . '(' . $tmphost->get('id') . ') - ' . $tmphost->get('name')
                                . '</a>';
                        }
                    ];
                    $columns[] = [
                        'db' => 'stState',
                        'dt' => 'taskstateicon',
                        'formatter' => function ($d, $row) {
                            return self::getClass('taskstate', $d)->get('icon');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stState',
                        'dt' => 'taskstatename',
                        'formatter' => function ($d, $row) {
                            return self::getClass('taskstate', $d)->get('name');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stSnapinID',
                        'dt' => 'snapinID',
                        'formatter' => function ($d, $row) {
                            return self::getClass('Snapin', $d)->get('id');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stSnapinID',
                        'dt' => 'snapinname',
                        'formatter' => function ($d, $row) {
                            return self::getClass('Snapin', $d)->get('name');
                        }
                    ];
                    $columns[] = [
                        'db' => 'stSnapinID',
                        'dt' => 'snapinLink',
                        'formatter' => function ($d, $row) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=snapin&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::getClass('Snapin', $d)->get('name')
                                . '</a>';
                        }
                    ];
                    $columns[] = [
                        'db' => 'stCheckinDate',
                        'dt' => 'diff',
                        'formatter' => function ($d, $row) {
                            $start = $d;
                            $end = $row['stCompleteDate'];
                            return self::diff($start, $end);
                        }
                    ];
                    break;
                case 'imaginglog':
                    $columns[] = [
                        'db' => 'ilStartTime',
                        'dt' => 'diff',
                        'formatter' => function ($d, $row) {
                            $start = $d;
                            $end = $row['ilFinishTime'];
                            return self::diff($start, $end);
                        }
                    ];
                    $columns[] = [
                        'db' => 'hostName',
                        'dt' => 'hostname',
                    ];
                    break;
                case 'storagegroup':
                    $StorageGroup = new StorageGroup();
                    $columns[] = [
                        'dt' => 'enablednodes',
                        'formatter' => function ($d, $row) use (&$StorageGroup) {
                            return $StorageGroup->set('id', $row['ngID'])
                                ->load()
                                ->get('enablednodes');
                        }
                    ];
                    $columns[] = [
                        'dt' => 'masternode',
                        'formatter' => function ($d, $row) use (&$StorageGroup) {
                            try {
                                $sn = $StorageGroup->getMasterStorageNode();
                            } catch (Exception $e) {
                                $sn = new StorageNode();
                            }
                            return self::getter('storagenode', $sn);
                        }
                    ];
                    $columns[] = [
                        'db' => 'totalclients',
                        'dt' => 'totalclients',
                        'removeFromQuery' => true
                    ];
                    break;
                case 'storagenode':
                    $columns[] = ['db' => 'ngID', 'dt' => 'storagegroupID'];
                    $columns[] = ['db' => 'ngName', 'dt' => 'storagegroupName'];
                    $columns[] = [
                        'db' => 'ngmID',
                        'dt' => 'clientload',
                        'formatter' => function ($d, $row) {
                            return self::getClass('StorageNode', $d)->getClientLoad();
                        }
                    ];
                    $columns[] = [
                        'db' => 'ngmID',
                        'dt' => 'location_url',
                        'formatter' => function ($d, $row) {
                            $node = new StorageNode($d);
                            return sprintf(
                                '%s://%s/%s',
                                self::$httpproto,
                                $node->get('ip'),
                                $node->get('webroot')
                            );
                        }
                    ];
                    /*$columns[] = [
                        'db' => 'ngmID',
                        'dt' => 'online',
                        'formatter' => function ($d, $row) {
                            return self::getClass('StorageNode', $d)->get('online');
                        }
                    ];*/
                    /*$columns[] = [
                        'db' => 'ngmID',
                        'dt' => 'logfiles',
                        'formatter' => function ($d, $row) {
                            return self::getClass('StorageNode', $d)->get('logfiles');
                        }
                    ];*/
                    break;
                case 'usertracking':
                    $columns[] = [
                        'db' => 'utUserName',
                        'dt' => 'username',
                        'formatter' => function ($d, $row) {
                            return Initiator::e($d);
                        }
                    ];
                    $columns[] = [
                        'db' => 'utHostID',
                        'dt' => 'hostname',
                        'formatter' => function ($d, $row) {
                            return Initiator::e(self::getClass('Host', $d)->get('name'));
                        }
                    ];
                    $columns[] = [
                        'db' => 'utAction',
                        'dt' => 'action',
                        'formatter' => function ($d, $row) {
                            switch ($d) {
                                case '0':
                                    return _('Logout');
                                case '1':
                                    return _('Login');
                                case '99':
                                    return _('Service Start');
                            }
                        }
                    ];
                    break;
                case 'plugin':
                    $columns[] = [
                        'dt' => 'hash',
                        'formatter' => function ($d, $row) {
                            return md5($row['pName']);
                        }
                    ];
            }
            self::$HookManager->processEvent(
                'CUSTOMIZE_DT_COLUMNS',
                [
                    'columns' => &$columns,
                    'classman' => &$classman,
                    'classname' => &$classname
                ]
            );

            self::$data = FOGManagerController::complex(
                isset($pass_vars) ? $pass_vars : '',
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $fltrstr,
                $ttlstr,
                $where,
                null,
                $orderby
            );
            self::$HookManager->processEvent(
                'API_MASSDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'pass_vars' => &$pass_vars,
                    'table' => &$table,
                    'tableID' => &$tableID,
                    'columns' => &$columns,
                    'sqlstr' => &$sqlstr,
                    'fltrstr' => &$fltrstr,
                    'ttlstr' => &$ttlstr,
                    'classname' => &$classname,
                    'classman' => &$classman
                ]
            );
            self::$data['_lang'] = $classname;
            if (self::$getterDepth === 0
                && self::expandRequested()
                && isset(self::$data['data'])
                && is_array(self::$data['data'])
            ) {
                // Serializing a row calls getter()/expandRelations()/plugin
                // hooks, which reach helpers like getIds() that overwrite the
                // shared static self::$data (getIds even leaves it an empty
                // string). Snapshot the payload and enrich a local copy so the
                // loop is immune to that clobbering, then restore it.
                $listData = self::$data;
                $rows = $listData['data'];
                foreach ($rows as $i => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $rid = isset($row['id']) ? (int)$row['id'] : 0;
                    if ($rid < 1) {
                        continue;
                    }
                    $robj = self::getClass($class, $rid);
                    if (!$robj->isValid()) {
                        continue;
                    }
                    // Inline ONLY the requested relations onto the flat grid
                    // row. Merging the full getter() output here would drag in
                    // every relation the entity's base serialization embeds
                    // (for Host: inventory/image/hostscreen/hostalo/macs),
                    // which defeats the selective contract of ?expand=token.
                    $exp = self::expandRelations($classname, $robj, $row);
                    $exp = self::enrichPluginItems($classname, $robj, $exp);
                    // Strip AFTER expanding so sensitive columns are removed
                    // whether they arrive on the raw grid row (which may carry
                    // the encrypted column) or on an inlined related object.
                    // List rows never expose secrets, matching the bare-grid
                    // contract.
                    $rows[$i] = self::stripSensitive($classname, $exp);
                }
                $listData['data'] = $rows;
                self::$data = $listData;
            }
            self::paginate(isset($pass_vars) ? $pass_vars : []);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Annotates a list envelope with pagination metadata so a client can see
     * that a response is a page and walk the pages without recomputing offsets.
     *
     * Always adds recordsReturned (rows actually in this response). When the
     * request asked for a bounded page (?length) against a non-empty result
     * set, also adds first/prev/next/last page URLs; each is null when it does
     * not apply (e.g. prevUrl on the first page). recordsTotal/recordsFiltered
     * keep their existing full-count meaning — the DataTables UI depends on it.
     *
     * @param array $pass_vars The resolved pagination request (start/length).
     *
     * @return void
     */
    public static function paginate($pass_vars)
    {
        if (!isset(self::$data['data']) || !is_array(self::$data['data'])) {
            return;
        }
        self::$data['recordsReturned'] = count(self::$data['data']);
        $length = isset($pass_vars['length']) ? (int)$pass_vars['length'] : 0;
        $start = isset($pass_vars['start']) ? (int)$pass_vars['start'] : 0;
        $filtered = isset(self::$data['recordsFiltered'])
            ? (int)self::$data['recordsFiltered']
            : 0;
        if ($length <= 0 || $filtered <= 0) {
            self::$data['firstUrl'] = null;
            self::$data['prevUrl'] = null;
            self::$data['nextUrl'] = null;
            self::$data['lastUrl'] = null;
            return;
        }
        if ($start < 0) {
            $start = 0;
        }
        $lastStart = intdiv(max(0, $filtered - 1), $length) * $length;
        self::$data['firstUrl'] = self::pageUrl(0, $length);
        self::$data['prevUrl'] = $start > 0
            ? self::pageUrl(max(0, $start - $length), $length)
            : null;
        self::$data['nextUrl'] = ($start + $length) < $filtered
            ? self::pageUrl($start + $length, $length)
            : null;
        self::$data['lastUrl'] = self::pageUrl($lastStart, $length);
    }
    /**
     * Builds a request-relative URL for a given pagination window, preserving
     * every other query parameter of the current request.
     *
     * @param int $start  The row offset for the page.
     * @param int $length The page size.
     *
     * @return string
     */
    public static function pageUrl($start, $length)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = $uri;
        $query = '';
        $qpos = strpos($uri, '?');
        if ($qpos !== false) {
            $path = substr($uri, 0, $qpos);
            $query = substr($uri, $qpos + 1);
        }
        $params = [];
        if ($query !== '') {
            parse_str($query, $params);
        }
        $params['start'] = (int)$start;
        $params['length'] = (int)$length;
        return $path . '?' . http_build_query($params);
    }
    /**
     * Presents the equivalent of a page's list all but only returns count.
     *
     * @param string $class         The class to work with.
     * @param mixed  $whereItems    Any special things to search for.
     * @param bool   $inputoverride Override php://input to blank.
     * @param string $operator      The operator for the SQL. AND is default.
     * @param string $orderby       How to order the returned values.
     *
     * @return void
     */
    public static function count(
        $class,
        $whereItems = false,
        $inputoverride = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            self::listem($class, $whereItems, $inputoverride, $operator, $orderby);
            self::$data = ['total' => self::$data['recordsFiltered']];
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Returns the count of matching records directly as an int.
     *
     * Wraps the count()/getData() pair callers repeat throughout the
     * codebase: count() stashes ['total' => N] in self::$data, getData()
     * JSON-encodes it, and the caller decodes and plucks ->total.
     *
     * @param string $class         The class to work with.
     * @param mixed  $whereItems    Any special things to search for.
     * @param bool   $inputoverride Override php://input to blank.
     * @param string $operator      The operator for the SQL. AND is default.
     * @param string $orderby       How to order the returned values.
     *
     * @return int
     */
    public static function getCount(
        $class,
        $whereItems = false,
        $inputoverride = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        self::count($class, $whereItems, $inputoverride, $operator, $orderby);
        $result = json_decode(self::getData());
        return (int)($result->total ?? 0);
    }
    /**
     * Presents the equivalent of a universal search.
     *
     * @param string   $item  The "search" term.
     * @param bool|int $limit Limit the results?
     *
     * @return void
     */
    public static function unisearch($item, $limit = 0)
    {
        try {
            if (empty(trim($limit))) {
                $limit = 0;
            }
            $item = trim($item);
            $like = '%' . $item . '%';

            $data = [];
            $data['_query'] = $item;
            $data['_lang']['AllResults'] = _('See all results');
            self::$HookManager->processEvent(
                'SEARCH_PAGES',
                ['searchPages' => &self::$searchPages]
            );
            foreach (self::$searchPages as &$search) {
                if ($search == 'task') {
                    continue;
                }
                // Skip entities the acting user may not view; unknown
                // (plugin-added) entries resolve to null = allowed.
                if (!Authorization::can(
                    Authorization::resolveApiPermission('list', $search)
                )
                ) {
                    continue;
                }
                $data['_lang'][$search] = (
                    $search != 'setting' ?
                    _($search) :
                    _('settings')
                );
                $searchfor = $search;
                if ($search === 'ipxe') {
                    $searchfor = 'pxemenuoptions';
                }
                $classVars = self::getClass(
                    $searchfor,
                    '',
                    true
                );
                $j = $w = $g = '';
                $params = ['item1' => $like, 'item2' => $like];
                switch ($search) {
                    case 'host':
                        $j = "LEFT OUTER JOIN `hostMAC`
                        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`";
                        $w = " OR `hostMAC`.`hmMAC` LIKE :item3";
                        $params['item3'] = $like;
                        $g = "GROUP BY `hosts`.`hostName`";
                        break;
                    case 'setting':
                        $w = " OR `settingValue` LIKE :item3";
                        $params['item3'] = $like;
                        break;
                    case 'storagenode':
                        $w = " OR `ngmHostname` LIKE :item3";
                        $params['item3'] = $like;
                }
                $sql = "SELECT `{$classVars['databaseFields']['id']}`,"
                    . "`{$classVars['databaseFields']['name']}`
                    FROM `{$classVars['databaseTable']}`
                {$j}
                WHERE `{$classVars['databaseFields']['id']}` LIKE :item1
                OR `{$classVars['databaseFields']['name']}` LIKE :item2
                ${w}
                ${g}";
                if ($limit > 0) {
                    $sql .= " LIMIT " . (int)$limit;
                }
                $vals = self::$DB->query(
                    $sql,
                    [],
                    $params
                )->fetch(
                    PDO::FETCH_ASSOC,
                    'fetch_all'
                )->get();
                foreach ($vals as $val) {
                    // Skip if the fields don't exist
                    if (!($val[$classVars['databaseFields']['id']] ?? '')) {
                        continue;
                    }
                    if (!($val[$classVars['databaseFields']['name']] ?? '')) {
                        continue;
                    }
                    if (!self::$ajax) {
                        $api = stripos(
                            $val[$classVars['databaseFields']['name']],
                            '_api'
                        );
                        if (false !== $api) {
                            continue;
                        }
                    }
                    $data[$search][] = [
                        'id' => $val[$classVars['databaseFields']['id']],
                        'name' => $val[$classVars['databaseFields']['name']]
                    ];
                }
                if (array_key_exists($search, $data)) {
                    $data['_results'][$search] = count(isset($data[$search]) ? $data[$search] : []);
                }
                unset($items);
                unset($search);
            }
            self::$HookManager->processEvent(
                'API_UNISEARCH_RESULTS',
                ['data' => &$data]
            );
            self::$data = $data;
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Presents the equivalent of a page's search.
     *
     * @param string $class The class to work with.
     * @param string $item  The "search".
     *
     * @return void
     */
    public static function search($class, $item)
    {
        try {
            $classname = strtolower($class);
            $classman = $classname . 'manager';
            self::$data = [];
            self::unisearch($item);
            $items = json_decode(self::getData());
            $ids = [];
            foreach ((array)$items->{$classname} as &$obj) {
                if (false != stripos($obj->name, '_api')) {
                    continue;
                }
                $ids[] = $obj->id;
                unset($obj);
            }
            self::listem($classname, ['id' => $ids]);
            self::$HookManager->processEvent(
                'API_MASSDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'classman' => &$classman
                ]
            );
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Displays the individual item.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of the item.
     *
     * @return void
     */
    public static function indiv($class, $id)
    {
        try {
            $classname = strtolower($class);
            // Recorded for printer(): a single-entity payload is flat and does
            // not name its own class.
            self::$emitClassname = $classname;
            $class = new $class($id);
            if (!$class->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            self::$data = [];
            self::$data = self::getter(
                $classname,
                $class
            );
            self::parseExpand();
            self::$data = self::expandRelations(
                $classname,
                $class,
                self::$data
            );
            self::$data = self::enrichPluginItems(
                $classname,
                $class,
                self::$data
            );
            self::$HookManager->processEvent(
                'API_INDIVDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'class' => &$class
                ]
            );
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Enables editing/updating a specified object.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of the item.
     *
     * @return void
     */
    public static function edit($class, $id)
    {
        try {
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $class = new $class($id);
            if (!$class->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            $vars = json_decode(
                file_get_contents('php://input')
            );
            $exists = false;
            $var_name = false;
            if (property_exists($vars, 'name')) {
                $exists = self::getClass($classname)
                    ->getManager()
                    ->exists($vars->name);
                $var_name = strtolower($vars->name);
                if (!$var_name) {
                    self::setErrorMessage(
                        _('A name must be defined if using the "name" property'),
                        HTTPResponseCodes::HTTP_FORBIDDEN
                    );
                }
            }
            $uniqueNames = !in_array($classname, self::$nonUniqueNameClasses);
            if ($uniqueNames
                && $exists
                && $var_name
                && strtolower($class->get('name')) != $var_name
            ) {
                self::setErrorMessage(
                    _('Already created'),
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            foreach ($classVars['databaseFields'] as &$key) {
                $key = $class->key($key);
                if (!isset($vars->$key)) {
                    $val = $class->get($key);
                } else {
                    $val = $vars->$key;
                }
                if ($key == 'id') {
                    continue;
                }
                $class->set($key, $val);
                unset($key);
            }
            switch ($classname) {
                case 'host':
                    if (isset($vars->macs)) {
                        $macsToAdd = array_diff(
                            (array)$vars->macs,
                            $class->getMyMacs()
                        );
                        $macsToRem = array_diff(
                            $class->getMyMacs(),
                            (array)$vars->macs
                        );
                        $class
                            ->addMAC($macsToAdd)
                            ->removeMAC($macsToRem);
                    }
                    if (isset($vars->primac)) {
                        $oldMac = $class->get('mac');
                        if ($vars->primac != $oldMac) {
                            $class
                                ->removeMAC([$oldMac])
                                ->addMAC([$oldMac])
                                ->addPriMAC($vars->primac);

                        }
                    }
                    if (isset($vars->snapins)) {
                        $snapinsToAdd = array_diff(
                            (array)$vars->snapins,
                            $class->get('snapins')
                        );
                        $snapinsToRem = array_diff(
                            $class->get('snapins'),
                            (array)$vars->snapins
                        );
                        $class
                            ->removeSnapin($snapinsToRem)
                            ->addSnapin($snapinsToAdd);
                    }
                    if (isset($vars->printers)) {
                        $printersToAdd = array_diff(
                            (array)$vars->printers,
                            $class->get('printers')
                        );
                        $printersToRem = array_diff(
                            $class->get('printers'),
                            (array)$vars->printers
                        );
                        $class
                            ->removePrinter($printersToRem)
                            ->addPrinter($printersToAdd);
                    }
                    if (isset($vars->modules)) {
                        $modulesToAdd = array_diff(
                            (array)$vars->modules,
                            $class->get('modules')
                        );
                        $modulesToRem = array_diff(
                            $class->get('modules'),
                            (array)$vars->modules
                        );
                        $class
                            ->removeModule($modulesToRem)
                            ->addModule($modulesToAdd);
                    }
                    if (isset($vars->groups)) {
                        $groupsToAdd = array_diff(
                            (array)$vars->groups,
                            $class->get('groups')
                        );
                        $groupsToRem = array_diff(
                            $class->get('groups'),
                            (array)$vars->groups
                        );
                        $class
                            ->removeGroup($groupsToRem)
                            ->addGroup($groupsToAdd);
                    }
                    break;
                case 'group':
                    if (isset($vars->snapins)) {
                        $snapins = Route::getIds('snapin', false);
                        $snapinsToRem = array_diff(
                            $snapins,
                            (array)$vars->snapins
                        );
                        $class
                            ->removeSnapin($snapinsToRem)
                            ->addSnapin($vars->snapins);
                    }
                    if (isset($vars->printers)) {
                        $printers = Route::getIds('printer', false);
                        $printersToRem = array_diff(
                            $printers,
                            (array)$vars->printers
                        );
                        $class
                            ->removePrinter($printersToRem)
                            ->addPrinter($vars->printers);
                    }
                    if (isset($vars->modules)) {
                        $modules = Route::getIds('module', false);
                        $modulesToRem = array_diff(
                            $modules,
                            (array)$vars->modules
                        );
                        $class
                            ->removeModule($modulesToRem)
                            ->addModule($vars->modules);
                    }
                    if (isset($vars->hosts)) {
                        $hostsToAdd = array_diff(
                            (array)$vars->hosts,
                            $class->get('hosts')
                        );
                        $hostsToRem = array_diff(
                            $class->get('hosts'),
                            (array)$vars->hosts
                        );
                        $class
                            ->removeHost($hostsToRem)
                            ->addHost($hostsToAdd);
                    }
                    if (isset($vars->imageID)) {
                        $class
                            ->addImage($vars->imageID);
                    }
                    break;
                case 'image':
                case 'snapin':
                    if (isset($vars->hosts)) {
                        $hostsToAdd = array_diff(
                            (array)$vars->hosts,
                            $class->get('hosts')
                        );
                        $hostsToRem = array_diff(
                            $class->get('hosts'),
                            (array)$vars->hosts
                        );
                        $class
                            ->removeHost($hostsToRem)
                            ->addHost($hostsToAdd);
                    }
                    if (isset($vars->storagegroups)) {
                        $storageGroupsToAdd = array_diff(
                            (array)$vars->storagegroups,
                            $class->get('storagegroups')
                        );
                        $storageGroupsToRem = array_diff(
                            $class->get('storagegroups'),
                            (array)$vars->storagegroups
                        );
                        $class
                            ->removeGroup($storageGroupsToRem)
                            ->addGroup($storageGroupsToAdd);
                    }
                    break;
                case 'printer':
                    if (isset($vars->hosts)) {
                        $hostsToAdd = array_diff(
                            (array)$vars->hosts,
                            $class->get('hosts')
                        );
                        $hostsToRem = array_diff(
                            $class->get('hosts'),
                            (array)$vars->hosts
                        );
                        $class
                            ->removeHost($hostsToRem)
                            ->addHost($hostsToAdd);
                    }
                    break;
            }
            // Store the data and recreate.
            // If failed present so.
            if ($class->save()) {
                $class = new $class($id);
            } else {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            self::indiv($classname, $id);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Generates our task element.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of the item.
     *
     * @return void
     */
    public static function task($class, $id)
    {
        $classname = strtolower($class);
        $class = new $class($id);
        if (!$class->isValid()) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
        }
        $tids = Route::getIds('tasktype', false);
        $task = json_decode(
            file_get_contents('php://input')
        );
        Route::indiv('tasktype', $task->taskTypeID);
        $TaskType = json_decode(Route::getData());
        try {
            $deploySnapins = false;
            $snapinAbortOnFailure = false;
            if (isset($task->deploySnapins)) {
                $deploySnapins = $task->deploySnapins;
                if ($deploySnapins === true) {
                    $deploySnapins = -1;
                } elseif (
                    !is_numeric($deploySnapins) || (
                        $deploySnapins < 0 && $deploySnapins != -1
                    )
                ) {
                    $deploySnapins = false;
                }
            }
            if (isset($task->snapinAbortOnFailure)) {
                $snapinAbortOnFailure = (bool)$task->snapinAbortOnFailure;
            }
            $class->createImagePackage(
                $TaskType,
                ($task->taskName ?? ''),
                ($task->shutdown ?? false),
                ($task->debug ?? false),
                $deploySnapins,
                $class instanceof Group,
                (self::_basicAuthCredentials()[0] ?: 'API'),
                $task->passreset ?? '',
                $task->sessionjoin ?? '',
                $task->wol ?? 1,
                false,
                $snapinAbortOnFailure
            );
        } catch (\Exception $e) {
            self::setErrorMessage(
                $e->getMessage(),
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    /**
     * Creates an item.
     *
     * @param string $class The class to work with.
     *
     * @return void
     */
    public static function create($class)
    {
        try {
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $class = new $class;

            $vars = json_decode(
                file_get_contents(
                    'php://input'
                )
            );

            $exists = false;
            if (property_exists($vars, 'name')) {
                $exists = self::getClass($classname)
                    ->getManager()
                    ->exists($vars->name);
            }
            $uniqueNames = !in_array($classname, self::$nonUniqueNameClasses);
            if ($exists && $uniqueNames) {
                self::setErrorMessage(
                    _('Already created'),
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            foreach ($classVars['databaseFields'] as &$key) {
                $key = $class->key($key);
                if (property_exists($vars, $key)) {
                    $val = $vars->$key;
                } else {
                    $val = null;
                }
                if ('id' == $key
                    || null === $val
                ) {
                    continue;
                }
                $class->set($key, $val);
                unset($key);
            }
            switch ($classname) {
                case 'host':
                    if (isset($vars->mac)) {
                        $vars->macs = array_merge(
                            (array)$vars->mac,
                            isset($vars->macs) ? (array)$vars->macs : []
                        );
                    }
                    if (isset($vars->macs)) {
                        // Set the primary MAC now (deferred via the 'mac' key
                        // and persisted by save() once the host id exists).
                        // Secondaries are added after save() below, when
                        // $this->get('id') is valid — otherwise they would be
                        // inserted with hmHostID=0 and orphaned.
                        $vars->macs = array_unique((array)$vars->macs);
                        $class->addPriMAC(array_shift($vars->macs));
                    }
                    if (isset($vars->snapins)) {
                        $class->addSnapin($vars->snapins);
                    }
                    if (isset($vars->printers)) {
                        $class->addPrinter($vars->printers);
                    }
                    if (isset($vars->modules)) {
                        $class->set('modules', $vars->modules);
                    }
                    if (isset($vars->groups)) {
                        $class->addGroup($vars->groups);
                    }
                    break;
                case 'group':
                    if (isset($vars->snapins)) {
                        $class->addSnapin($vars->snapins);
                    }
                    if (isset($vars->printers)) {
                        $class
                            ->addPrinter($vars->printers);
                    }
                    if (isset($vars->modules)) {
                        $class->addModule($vars->modules);
                    }
                    if (isset($vars->hosts)) {
                        $class->addHost($vars->hosts);
                        if (isset($vars->imageID)) {
                            $class->addImage($vars->imageID);
                        }
                    }
                    break;
                case 'image':
                case 'snapin':
                    if (isset($vars->hosts)) {
                        $class->addHost($vars->hosts);
                    }
                    if (isset($vars->storagegroups)) {
                        $class->addGroup($vars->storagegroups);
                    }
                    break;
                case 'printer':
                    if (isset($vars->hosts)) {
                        $class->addHost($vars->hosts);
                    }
                    break;
            }
            foreach ($classVars['databaseFieldsRequired'] as &$key) {
                $key = $class->key($key);
                $val = $class->get($key);
                if (null === $val) {
                    self::setErrorMessage(
                        self::$foglang['RequiredDB'] . ": " . $key,
                        HTTPResponseCodes::HTTP_EXPECTATION_FAILED
                    );
                }
            }
            // Store the data and recreate.
            // If failed present so.
            if ($class->save()) {
                $id = $class->get('id');
                $class = new $class($id);
                if ('host' === $classname && !empty($vars->macs)) {
                    $class->addMAC($vars->macs);
                }
            } else {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            self::indiv($classname, $id);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Creates a Snapin from a multipart/form-data POST that includes
     * the snapin file. The UI's snapin add flow accepts an uploaded
     * file; the generic JSON `create` endpoint does not, so this is
     * the API-side counterpart for that flow.
     *
     * Maps the same exception types as the helper but with REST-
     * conventional codes: validation -> 400, transport/save -> 500.
     * The UI page deliberately preserves the legacy 400 for SSH/SFTP
     * RuntimeExceptions. See
     * docs/adr/0001-api-ui-http-status-divergence.md.
     *
     * @return void
     */
    public static function createSnapinWithFile()
    {
        try {
            if (empty($_FILES['snapinfile']['name'])
                || (int)($_FILES['snapinfile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            ) {
                self::setErrorMessage(
                    _('A file must be uploaded via the "snapinfile" multipart field'),
                    HTTPResponseCodes::HTTP_BAD_REQUEST
                );
                return;
            }
            $Snapin = Snapin::uploadAndCreate($_POST, $_FILES);
            http_response_code(HTTPResponseCodes::HTTP_CREATED);
            self::indiv('Snapin', $Snapin->get('id'));
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                $e->getMessage()
            );
        } catch (\RuntimeException $e) {
            // Covers SnapinSaveException (subclass) and any SSH/SFTP failure.
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }
    /**
     * Uploads one or more snapin files to a Storage Group's Master
     * Node over SFTP. Pure transport — does not create or modify any
     * Snapin DB row. After upload, the FOGSnapinReplicator daemon
     * propagates the files to the group's other nodes on its normal
     * cycle.
     *
     * Closes #823.
     *
     * Multi-file: pass files as snapinfiles[]=@file1 snapinfiles[]=@file2.
     * Collision: existing files with the same basename are overwritten
     * (matches the createwithfile / UI behavior).
     *
     * @param int $id Storage Group ID
     *
     * @return void
     */
    public static function uploadSnapinFiles($id)
    {
        try {
            $StorageGroup = self::getClass('StorageGroup', (int)$id);
            if (!$StorageGroup->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND,
                    _('Storage Group not found')
                );
                return;
            }
            if (empty($_FILES['snapinfiles']['name'])
                || !is_array($_FILES['snapinfiles']['name'])
            ) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    _('One or more files must be uploaded via the "snapinfiles[]" multipart field')
                );
                return;
            }
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode || !$StorageNode->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                    _('Storage Group has no reachable Master Node')
                );
                return;
            }
            Snapin::uploadFilesToNode($StorageNode, $_FILES['snapinfiles']);
            // sendResponse exits, so printer() doesn't run and override
            // the status / emit a null body. 204 = success, no body.
            self::sendResponse(HTTPResponseCodes::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                $e->getMessage()
            );
        } catch (\RuntimeException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }
    /**
     * Cancels a task element.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of the item.
     *
     * @return void
     */
    public static function cancel($class, $id)
    {
        try {
            $classname = strtolower($class);
            $class = new $class($id);
            switch ($classname) {
                case 'group':
                    if (!$class->isValid()) {
                        self::sendResponse(
                            HTTPResponseCodes::HTTP_NOT_FOUND
                        );
                    }
                    Route::listem(
                        'task',
                        ['hostID' => $class->get('hosts')]
                    );
                    $Tasks = json_decode(
                        Route::getData()
                    );
                    foreach ($Tasks as &$Task) {
                        self::getClass('Task', $Task->id)->cancel();
                        unset($Task);
                    }
                    break;
                case 'host':
                    if (!$class->isValid()) {
                        self::sendResponse(
                            HTTPResponseCodes::HTTP_NOT_FOUND
                        );
                    }
                    if ($class->get('task') instanceof Task) {
                        $class->get('task')->cancel();
                    }
                    break;
                default:
                    $states = self::fastmerge(
                        (array)self::getQueuedStates(),
                        (array)self::getProgressState()
                    );
                    if (!$class->isValid()) {
                        $classman = $class->getManager();
                        $find = self::getsearchbody($classname);
                        $find['stateID'] = $states;
                        $ids = self::ids($classname, $find);
                        $classman->cancel($ids);
                    } else {
                        if (in_array($class->get('stateID'), $states)) {
                            $class->cancel();
                        }
                    }
            }
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Gets the json body and sets our vars.
     *
     * @param string $class The class to get vars for/from.
     *
     * @return array
     */
    public static function getsearchbody($class)
    {
        try {
            $vars = json_decode(
                file_get_contents('php://input')
            );
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $find = [];
            $class = new $class;
            foreach ($classVars['databaseFields'] as &$key) {
                $key = $class->key($key);
                if (isset($vars->$key)) {
                    $find[$key] = $vars->$key;
                }
                unset($key);
            }

            // Request-supplied, so the caller-facing '*'/'+' wildcards apply
            // here (they used to be expanded down in _buildSql, which also
            // caught internally-built filters -- see expandSearchWildcards).
            return self::expandSearchWildcards($find);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Gets current/active tasks.
     *
     * @param string $class The class to use.
     *
     * @return void
     */
    public static function active($class)
    {
        try {
            $classname = strtolower($class);
            $states = self::getQueuedStates();
            $states[] = self::getProgressState();
            switch ($classname) {
                case 'scheduledtask':
                    $find = ['isActive' => 1];
                    break;
                case 'powermanagement':
                    $find = [
                        'action' => 'wol',
                        'onDemand' => [0, '']
                    ];
                    break;
                case 'filedeletequeue':
                    $find = ['stateID' => $states];
                    break;
                default:
                    $find = ['stateID' => $states];
            }
            self::listem($class, $find);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Deletes an element.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of class to remove.
     *
     * @return void
     */
    public static function delete($class, $id)
    {
        try {
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $vars = json_decode(
                file_get_contents('php://input')
            );
            $whereItems = ['id' => $id];
            $count = self::getCount($classname, $whereItems);
            if (!$count) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            // Funnel REST single-delete through the shared cascade authority so it
            // runs the same removeItems map and fires DELETEMASS_API for plugins,
            // instead of a bare row delete that orphaned every association.
            return self::deletemass($class, $whereItems);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Sets an error message.
     *
     * @param string   $message The error message to pass.
     * @param bool|int $code    Send custom error code.
     *
     * @return void
     */
    public static function setErrorMessage($message, $code = false)
    {
        self::$data = ['error' => $message];
        self::printer(self::$data, $code);
        exit;
    }
    /**
     * Emits an RFC 5988 Link header from a paginated list envelope so clients
     * that prefer headers over the body can walk pages. No-op unless the
     * payload carries the pagination *Url fields paginate() adds.
     *
     * @param mixed $data The response payload.
     *
     * @return void
     */
    public static function emitLinkHeader($data)
    {
        if (!is_array($data) || headers_sent()) {
            return;
        }
        $rels = [
            'first' => $data['firstUrl'] ?? null,
            'prev' => $data['prevUrl'] ?? null,
            'next' => $data['nextUrl'] ?? null,
            'last' => $data['lastUrl'] ?? null,
        ];
        $parts = [];
        foreach ($rels as $rel => $url) {
            if ($url) {
                $parts[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }
        if (count($parts) > 0) {
            header('Link: ' . implode(', ', $parts));
        }
    }
    /**
     * Gets json data
     *
     * @return string
     */
    public static function getData()
    {
        $message = json_encode(
            self::$data,
            JSON_UNESCAPED_UNICODE
        );
        self::$data = '';
        return $message;
    }
    /**
     * Generates a default means to print data to screen.
     *
     * @param mixed    $data The data to print.
     * @param bool|int $code Send custom error code.
     *
     * @return void
     */
    public static function printer($data, $code = false)
    {
        $data = self::stripSensitivePayload($data);
        self::emitLinkHeader($data);
        $message = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
        );
        if (false !== $code) {
            self::sendResponse(
                $code,
                $message
            );
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_SUCCESS,
            $message
        );
    }
    /**
     * Removes sensitive columns from a list payload on its way out of the API.
     *
     * Stripping used to happen only inside listem()'s ?expand branch, so a
     * PLAIN list -- overwhelmingly the common call -- returned the raw grid row
     * untouched and handed ldap.bindPwd (and host.productKey wherever one is
     * set) to any API caller. Whether a row was decorated with ?expand has
     * nothing to do with whether it may carry a secret, so the guard has to be
     * unconditional or it is not a guard.
     *
     * It belongs HERE, in the single emitter every API route returns through,
     * and deliberately not in listem(). listem() is shared with the web tier:
     * the LDAP login path calls Route::listem('ldap') and needs bindPwd to
     * bind at all, and the Product Keys report calls Route::listem('host') and
     * needs productKey to have anything to report. Both read the result with
     * getData() and never reach printer(), so stripping at the emitter closes
     * the API surface without breaking the internal callers that legitimately
     * want the value. Stripping inside listem() would have broken LDAP
     * authentication outright.
     *
     * Only list-shaped payloads are touched -- those carrying both the '_lang'
     * classname stamp and a 'data' array. A single-entity GET is a flat object
     * with neither, so it keeps the documented "secrets only on a direct
     * single GET" contract that fog-client depends on for host ADPass.
     *
     * @param mixed $data The payload about to be encoded.
     *
     * @return mixed
     */
    public static function stripSensitivePayload($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $classname = isset($data['_lang'])
            ? strtolower((string)$data['_lang'])
            : self::$emitClassname;
        if ('' === $classname) {
            return $data;
        }
        if (isset($data['data']) && is_array($data['data'])) {
            // List payload: both tiers, every row.
            foreach ($data['data'] as $i => $row) {
                $data['data'][$i] = self::stripSensitive($classname, $row);
            }
            return $data;
        }
        // Single-entity payload: only the fields no client may read back.
        return self::stripSensitive($classname, $data, true);
    }
    /**
     * This is a commonizing element so list/search/getinfo
     * will operate in the same fashion.
     *
     * @param string $classname The name of the class.
     * @param object $class     The class to work with.
     *
     * @return object|array
     */
    public static function getter($classname, $class)
    {
        self::$getterDepth++;
        try {
            if (!$class instanceof $classname) {
                return;
            }
            switch ($classname) {
                case 'host':
                    $pass = $class->get('ADPass');
                    $passtest = FOGCore::aesdecrypt($pass);
                    if ($test_base64 = base64_decode($passtest)) {
                        if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                            $pass = $test_base64;
                        }
                    } elseif (mb_detect_encoding($passtest, 'utf-8', true)) {
                        $pass = $passtest;
                    }
                    $productKey = $class->get('productKey');
                    $productKeytest = FOGCore::aesdecrypt($productKey);
                    if ($test_base64 = base64_decode($productKeytest)) {
                        if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                            $productKey = $test_base64;
                        }
                    } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
                        $productKey = $productKeytest;
                    }
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'ADPass' => $pass,
                            'productKey' => $productKey,
                            'hostscreen' => $class->get('hostscreen')->get(),
                            'hostalo' => $class->get('hostalo')->get(),
                            'inventory' => self::getter(
                                'inventory',
                                $class->get('inventory')
                            ),
                            'image' => $class->get('imagename')->get(),
                            'imagename' => $class->getImageName(),
                            'primac' => $class->get('mac')->__toString(),
                            'macs' => $class->getMyMacs(),
                        ]
                    );
                    break;
                case 'inventory':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        ['memory' => $class->getMem()]
                    );
                    break;
                case 'group':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        ['hostcount' => $class->getHostCount()]
                    );
                    break;
                case 'image':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'os' => $class->get('os')->get(),
                            'imagepartitiontype' => $class->get('imagepartitiontype')->get(),
                            'imagetype' => $class->get('imagetype')->get(),
                            'imagetypename' => $class->getImageType()->get('name'),
                            'imageparttypename' => $class->getImagePartitionType()->get(
                                'name'
                            ),
                            'osname' => $class->getOS()->get('name'),
                            'storagegroupname' => $class->getStorageGroup()->get('name')
                        ]
                    );
                    break;
                case 'snapin':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        ['storagegroupname' => $class->getStorageGroup()->get('name')]
                    );
                    break;
                case 'storagenode':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'online' => $class->get('online'),
                            //'logfiles' => $class->get('logfiles'),
                            'snapinfiles' => $class->get('snapinfiles'),
                            'images' => $class->get('images'),
                            'storagegroup' => $class->get('storagegroup')->get(),
                            'location_url' => sprintf(
                                '%s://%s/%s',
                                self::$httpproto,
                                $class->get('ip'),
                                $class->get('webroot')
                            )
                        ]
                    );
                    break;
                case 'storagegroup':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'totalsupportedclients' => $class->getTotalSupportedClients(),
                            'enablednodes' => $class->get('enablednodes'),
                            'allnodes' => $class->get('allnodes')
                        ]
                    );
                    break;
                case 'task':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'image' => $class->get('image')->get(),
                            'host' => self::getter(
                                'host',
                                $class->get('host')
                            ),
                            'type' => $class->get('type')->get(),
                            'state' => $class->get('state')->get(),
                            'storagenode' => $class->get('storagenode')->get(),
                            'storagegroup' => $class->get('storagegroup')->get()
                        ]
                    );
                    break;
                case 'plugin':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        ['hash' => md5($class->get('name'))]
                    );
                    break;
                case 'imaginglog':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'host' => self::getter(
                                'host',
                                $class->get('host')
                            ),
                            'image' => (
                                ($class->get('images') instanceof Image
                                    && $class->get('images')->isValid())
                                ? $class->get('images')->get()
                                : $class->get('image')
                            )
                        ]
                    );
                    unset($data['images']);
                    break;
                case 'snapintask':
                    $sj = new Snapinjob($class->get('snapinjob')->get('id'));
                    $host = new Host($class->get('snapinjob')->get('hostID'));
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'snapin' => $class->get('snapin')->get(),
                            'snapinjob' => self::getter(
                                'snapinjob',
                                $sj
                            ),
                            'host' => self::getter(
                                'host',
                                $host
                            ),
                            'state' => $class->get('state')->get()
                        ]
                    );
                    break;
                case 'snapinjob':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'host' => self::getter(
                                'host',
                                $class->get('host')
                            ),
                            'state' => $class->get('state')->get()
                        ]
                    );
                    break;
                case 'usertracking':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'host' => self::getter(
                                'host',
                                $class->get('host')
                            ),
                            'hostname' => $class->get('host')->get('name')
                        ]
                    );
                    break;
                case 'multicastsession':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'imageID' => $class->get('image'),
                            'image' => $class->get('imagename')->get(),
                            'state' => $class->get('state')->get()
                        ]
                    );
                    unset($data['imagename']);
                    break;
                case 'scheduledtask':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            (
                                $class->isGroupBased() ?
                                'group' :
                                'host'
                            ) => (
                                $class->isGroupBased() ?
                                self::getter(
                                    'group',
                                    $class->getGroup()
                                ) :
                                self::getter(
                                    'host',
                                    $class->getHost()
                                )
                            ),
                            'tasktype' => $class->getTaskType()->get(),
                            'runtime' => $class->getTime()
                        ]
                    );
                    break;
                case 'tasktype':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'isSnapinTasking' => $class->isSnapinTasking(),
                            'isSnapinTask' => $class->isSnapinTask(),
                            'isImagingTask' => $class->isImagingTask(),
                            'isCapture' => $class->isCapture(),
                            'isDeploy' => $class->isDeploy(),
                            'isInitNeeded' => $class->isInitNeededTasking(),
                            'initIDs' => $class->isInitNeededTasking(true),
                            'isMulticast' => $class->isMulticast(),
                            'isDebug' => $class->isDebug()
                        ]
                    );
                    break;
                default:
                    $data = $class->get();
            }
            self::$HookManager->processEvent(
                'API_GETTER',
                [
                    'data' => &$data,
                    'classname' => &$classname,
                    'class' => &$class
                ]
            );
            return $data;
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        } finally {
            self::$getterDepth--;
        }
    }
    /**
     * Parses the ?expand=a,b,c[,all] query parameter into the static
     * expansion state used by expandRelations()/enrichPluginItems().
     *
     * Called once at the entry of indiv()/listem(). Resets any prior state
     * so a single PHP request that serves multiple entities stays clean.
     *
     * @return void
     */
    public static function parseExpand()
    {
        self::$expand = [];
        self::$expandAll = false;
        self::$expandDepth = 0;
        $raw = self::queryParam('expand');
        if ($raw === null || $raw === false || $raw === '') {
            return;
        }
        $tokens = array_filter(
            array_map('trim', explode(',', strtolower((string)$raw)))
        );
        if (in_array('all', $tokens, true)) {
            self::$expandAll = true;
        }
        self::$expand = array_values($tokens);
    }
    /**
     * Reads a query-string parameter for the API.
     *
     * FOG's API is served through an nginx/apache internal rewrite to
     * api/index.php that does not propagate the request's query string into
     * QUERY_STRING/$_GET (the API otherwise takes every input from the request
     * body). The original request line still carries the query string, so fall
     * back to parsing REQUEST_URI when $_GET has not been populated.
     *
     * @param string $key The parameter name to read.
     *
     * @return string|null The raw value, or null when absent.
     */
    protected static function queryParam($key)
    {
        $val = filter_input(INPUT_GET, $key);
        if ($val !== null && $val !== false) {
            return $val;
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs === '') {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $pos = strpos($uri, '?');
            if ($pos !== false) {
                $qs = substr($uri, $pos + 1);
            }
        }
        if ($qs === '') {
            return null;
        }
        parse_str($qs, $parsed);
        return isset($parsed[$key]) ? (string)$parsed[$key] : null;
    }
    /**
     * True when the client asked for any relation expansion.
     *
     * @return bool
     */
    public static function expandRequested()
    {
        return self::$expandAll || !empty(self::$expand);
    }
    /**
     * True when a specific relation token was requested (or ?expand=all).
     *
     * @param string $token The relation token to test.
     *
     * @return bool
     */
    public static function wantsExpand($token)
    {
        return self::$expandAll || in_array($token, self::$expand, true);
    }
    /**
     * Removes decrypted secrets from a related/list object so they are only
     * ever exposed on a direct single-entity GET.
     *
     * @param string $classname The class the data belongs to.
     * @param mixed  $data      The serialized object (assoc array).
     *
     * @return mixed
     */
    public static function stripSensitive($classname, $data, $alwaysOnly = false)
    {
        if (!is_array($data)) {
            return $data;
        }
        $fields = (array)(self::$sensitiveAlwaysFields[$classname] ?? []);
        if (!$alwaysOnly) {
            $fields = array_merge(
                $fields,
                (array)(self::$sensitiveFields[$classname] ?? [])
            );
        }
        foreach ($fields as $field) {
            unset($data[$field]);
        }
        if ('setting' === $classname) {
            $data = self::maskSensitiveSetting($data);
        }
        return $data;
    }
    /**
     * Blanks the value of a globalSettings row that holds a credential.
     *
     * Unlike every other class the sensitive part of a setting is not a
     * column but the value of a particular key, so this keys off the row's
     * name. The name/description/category stay, so the setting is still
     * visible -- only its value goes.
     *
     * @param mixed $data A serialized setting row.
     *
     * @return mixed
     */
    public static function maskSensitiveSetting($data)
    {
        if (!is_array($data) || !isset($data['name'])) {
            return $data;
        }
        if (self::isSensitiveSetting((string)$data['name'])) {
            unset($data['value']);
        }
        return $data;
    }
    /**
     * Whether a globalSettings key holds a credential.
     *
     * @param string $key The settingKey to test.
     *
     * @return bool
     */
    public static function isSensitiveSetting($key)
    {
        if (in_array($key, self::$sensitiveSettingsExempt, true)) {
            return false;
        }
        if (in_array($key, self::$sensitiveSettings, true)) {
            return true;
        }
        return 1 === preg_match(self::SENSITIVE_SETTING_PATTERN, $key);
    }
    /**
     * Declarative per-class relation map driving ?expand. Each entry:
     *   token => ['class' => <target>, 'many' => bool, 'field' => <accessor>]
     * where <accessor> is a get() key returning either a related object
     * (many=false) or an array of integer ids (many=true).
     *
     * Only relations whose target getter does NOT re-embed the parent are
     * listed, so expansion is inherently free of back-references.
     *
     * @param string $classname The entity being serialized.
     *
     * @return array
     */
    protected static function relationMap($classname)
    {
        $maps = [
            'host' => [
                'image' => [
                    'class' => 'image',
                    'many' => false,
                    'field' => 'imagename',
                ],
                'snapins' => [
                    'class' => 'snapin',
                    'many' => true,
                    'field' => 'snapins',
                ],
                'printers' => [
                    'class' => 'printer',
                    'many' => true,
                    'field' => 'printers',
                ],
                'groups' => [
                    'class' => 'group',
                    'many' => true,
                    'field' => 'groups',
                ],
                'modules' => [
                    'class' => 'module',
                    'many' => true,
                    'field' => 'modules',
                ],
            ],
        ];
        return $maps[$classname] ?? [];
    }
    /**
     * Additively inlines requested related objects onto an already
     * serialized entity. Scalar foreign keys are preserved; expanded
     * objects are added under their relation token. One-to-many relations
     * become a capped array plus companion `<token>_total`/`<token>_truncated`
     * keys. Related objects are serialized one level deep (no further
     * expansion) and have their secrets stripped.
     *
     * @param string $classname The entity classname.
     * @param object $class      The loaded entity object.
     * @param array  $data       The serialized entity data.
     *
     * @return array
     */
    public static function expandRelations($classname, $class, $data)
    {
        if (!is_array($data) || !self::expandRequested()) {
            return $data;
        }
        if (self::$expandDepth >= self::EXPAND_MAX_DEPTH) {
            return $data;
        }
        $map = self::relationMap($classname);
        if (empty($map)) {
            return $data;
        }
        self::$expandDepth++;
        foreach ($map as $token => $rel) {
            if (!self::wantsExpand($token)) {
                continue;
            }
            if (empty($rel['many'])) {
                $obj = $class->get($rel['field']);
                if ($obj instanceof FOGController && $obj->isValid()) {
                    $g = self::getter($rel['class'], $obj);
                    if (is_array($g)) {
                        $data[$token] = self::stripSensitive($rel['class'], $g);
                    }
                }
                continue;
            }
            $ids = self::positiveIntIds((array)$class->get($rel['field']));
            $total = count($ids);
            $truncated = false;
            if ($total > self::EXPAND_MAX_ITEMS) {
                $ids = array_slice($ids, 0, self::EXPAND_MAX_ITEMS);
                $truncated = true;
            }
            $items = [];
            foreach ($ids as $rid) {
                $robj = self::getClass($rel['class'], $rid);
                if (!$robj->isValid()) {
                    continue;
                }
                $g = self::getter($rel['class'], $robj);
                if (is_array($g)) {
                    $items[] = self::stripSensitive($rel['class'], $g);
                }
            }
            $data[$token] = $items;
            $data[$token . '_total'] = $total;
            $data[$token . '_truncated'] = $truncated;
        }
        self::$expandDepth--;
        return $data;
    }
    /**
     * Fires the API_PLUGIN_ITEMS event so plugins can inject their
     * associations into a namespaced `pluginItems` envelope without
     * clobbering core fields. Only invoked at the top level of a single
     * GET or of each list row (never on nested/related objects), which is
     * what keeps plugin back-references out of expanded children.
     *
     * @param string $classname The entity classname.
     * @param object $class      The loaded entity object.
     * @param array  $data       The serialized entity data.
     *
     * @return array
     */
    public static function enrichPluginItems($classname, $class, $data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $pluginItems = [];
        self::$HookManager->processEvent(
            'API_PLUGIN_ITEMS',
            [
                'data' => &$data,
                'pluginItems' => &$pluginItems,
                'classname' => &$classname,
                'class' => &$class
            ]
        );
        if (!empty($pluginItems)) {
            $data['pluginItems'] = $pluginItems;
        }
        return $data;
    }
    /**
     * Returns the current bandwidth.
     *
     * @param string $dev The device to get bandwidth from.
     *
     * @return mixed
     */
    public static function bandwidth($dev)
    {
        if (!$dev || !preg_match('/^[a-zA-Z0-9._-]+$/', $dev)) {
            echo json_encode(
                [
                    'dev' => _('Unknown'),
                    'rx' => 0,
                    'tx' => 0
                ]
            );
            exit;
        }
        $txlast = file_get_contents("/sys/class/net/$dev/statistics/tx_bytes");
        $rxlast = file_get_contents("/sys/class/net/$dev/statistics/rx_bytes");
        usleep(200000);
        $txcurr = file_get_contents("/sys/class/net/$dev/statistics/tx_bytes");
        $rxcurr = file_get_contents("/sys/class/net/$dev/statistics/rx_bytes");
        $tx = round(ceil(($txcurr - $txlast)) / 1024 * 8 / 200, 2);
        $rx = round(ceil(($rxcurr - $rxlast)) / 1024 * 8 / 200, 2);
        echo json_encode(
            [
                'dev' => $dev,
                'rx' => $rx,
                'tx' => $tx
            ]
        );
        exit;
    }
    /**
     * Returns only the ids of the class.
     *
     * @param string $class      The class to get list of.
     * @param array  $whereItems The items to filter.
     * @param string $getField   The field to get.
     * @param string $operator   The operator for the SQL. AND is default.
     * @param string $orderby       How to order the returned values.
     *
     * @return void
     */
    public static function ids(
        $class,
        $whereItems = [],
        $getField = 'id',
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            if (empty($operator)) {
                $operator = 'AND';
            }
            $data = [];
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $vars = json_decode(
                file_get_contents('php://input')
            );

            if (empty($orderby)) {
                $orderby = 'name';
            }

            $whereItems = self::handleWhereItems($whereItems);
            if (false !== $whereItems && count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }
            if (isset($vars->getField) && $vars->getField) {
                $getField = $vars->getField;
            }

            $sql = 'SELECT `'
                . $classVars['databaseFields'][$getField]
                . '` FROM `'
                . $classVars['databaseTable']
                . '`';

            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );

            $vals = self::$DB->query($sqlResult['sql'], [], $sqlResult['params'])->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
            foreach ($vals as &$val) {
                $data[] = $val[$classVars['databaseFields'][$getField]];
                unset($val);
            }
            self::$data = $data;
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Returns the matching ids directly as a PHP array.
     *
     * Convenience wrapper around ids() that skips the
     * json_encode/json_decode round-trip getData() incurs, for the
     * common `Route::ids(...); json_decode(Route::getData())` idiom.
     *
     * @param string $class      The class to get list of.
     * @param array  $whereItems The items to filter.
     * @param string $getField   The field to get.
     * @param string $operator   The operator for the SQL. AND is default.
     * @param string $orderby    How to order the returned values.
     *
     * @return array
     */
    public static function getIds(
        $class,
        $whereItems = [],
        $getField = 'id',
        $operator = 'AND',
        $orderby = 'name'
    ) {
        self::ids($class, $whereItems, $getField, $operator, $orderby);
        $data = self::$data;
        self::$data = '';
        return is_array($data) ? $data : [];
    }
    /**
     * Delete items in mass.
     *
     * @param string $class      The class we're to remove items.
     * @param array  $whereItems The items we're removing.
     * @param string $operator   The operator for the SQL. AND is default.
     *
     * @return void
     */
    public static function deletemass(
        $class,
        $whereItems = [],
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            if (empty($operator)) {
                $operator = 'AND';
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }
            $data = [];
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $vars = json_decode(
                file_get_contents('php://input')
            );

            self::ids($classname, $whereItems);
            $itemIDs = json_decode(Route::getData(), true);
            // Lockout guard. Only at the outermost call: the cascade below
            // re-enters deletemass() for each dependent table, and those
            // intermediate states are part of one operation that has
            // already been judged as a whole -- checking them individually
            // would refuse deletes that are actually fine.
            if (self::$_deleteDepth < 1) {
                Authorization::assertAdminRemainsAfterDelete(
                    $classname,
                    $itemIDs
                );
            }
            self::$_deleteDepth++;
            switch ($classname) {
                case 'host':
                    $snapinjobIDs = ['jobID' => Route::getIds('snapinjob', ['hostID' => $itemIDs])];
                    $findWhere = ['hostID' => $itemIDs];
                    $removeItems = [
                        'nodefailure' => $findWhere,
                        'imaginglog' => $findWhere,
                        'snapintask' => $snapinjobIDs,
                        'snapinjob' => $findWhere,
                        'task' => $findWhere,
                        'scheduledtask' => $findWhere,
                        'hostautologout' => $findWhere,
                        'hostscreensetting' => $findWhere,
                        'groupassociation' => $findWhere,
                        'snapinassociation' => $findWhere,
                        'printerassociation' => $findWhere,
                        'moduleassociation' => $findWhere,
                        'inventory' => $findWhere,
                        'macaddressassociation' => $findWhere,
                        'powermanagement' => $findWhere
                    ];
                    break;
                case 'group':
                    $findWhere = ['groupID' => $itemIDs];
                    $removeItems = [
                        'groupassociation' => $findWhere
                    ];
                    break;
                case 'image':
                    $findWhere = ['imageID' => $itemIDs];
                    self::getClass('HostManager')->update(
                        $findWhere,
                        '',
                        ['imageID' => 0]
                    );
                    $removeItems = [
                        'imageassociation' => $findWhere
                    ];
                    break;
                case 'module':
                    $findWhere = ['moduleID' => $itemIDs];
                    $removeItems = [
                        'moduleassociation' => $findWhere
                    ];
                    break;
                case 'printer':
                    $findWhere = ['printerID' => $itemIDs];
                    $removeItems = [
                        'printerassociation' => $findWhere
                    ];
                    break;
                case 'snapin':
                    $findWhere = ['snapinID' => $itemIDs];
                    $snapinjobIDs = Route::getIds(
                        'snapintask',
                        $findWhere,
                        'jobID'
                    );
                    $removeItems = [
                        'snapinassociation' => $findWhere,
                        'snapingroupassociation' => $findWhere
                    ];
                    $queuedStates = self::getQueuedStates();
                    $queuedStates[] = self::getProgressState();
                    $snapinjobIDs = Route::getIds(
                        'snapinjob',
                        [
                            'id' => $snapinjobIDs,
                            'stateID' => $queuedStates
                        ]
                    );
                    $sjIDs = [];
                    foreach ((array)$snapinjobIDs as &$sjID) {
                        $jobCount = self::getCount(
                            'snapintask',
                            ['jobID' => $sjID]
                        );
                        if ($jobCount) {
                            continue;
                        }
                        $sjIDs[] = $sjID;
                        unset($sjID);
                    }
                    if (count($sjIDs ?: [])) {
                        self::getClass('SnapinJobManager')->cancel($sjIDs);
                    }
                    break;
                case 'user':
                    $findWhere = ['userID' => $itemIDs];
                    $removeItems = [
                        'roleuserassociation' => $findWhere,
                        'usergroupmember' => $findWhere
                    ];
                    break;
                case 'role':
                    $findWhere = ['roleID' => $itemIDs];
                    $removeItems = [
                        'rolepermission' => $findWhere,
                        'roleuserassociation' => $findWhere,
                        'roleusergroupassociation' => $findWhere
                    ];
                    break;
                case 'usergroup':
                    $findWhere = ['usergroupID' => $itemIDs];
                    $removeItems = [
                        'usergroupmember' => $findWhere,
                        'roleusergroupassociation' => $findWhere
                    ];
                    break;
                default:
                    $findWhere = [];
                    $removeItems = [];
            }

            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }

            self::$HookManager->processEvent(
                'DELETEMASS_API',
                [
                    'classname' => &$classname,
                    'itemIDs' => &$itemIDs,
                    'removeItems' => &$removeItems
                ]
            );
            foreach ((array)$removeItems as $item => &$vals) {
                Route::deletemass(
                    $item,
                    $vals
                );
                unset($vals);
            }

            $sql = 'DELETE FROM `'
                . $classVars['databaseTable']
                . '`';

            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );

            return self::$DB->query($sqlResult['sql'], [], $sqlResult['params']);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        } finally {
            // max() because the guard above can throw before the increment.
            self::$_deleteDepth = max(0, self::$_deleteDepth - 1);
        }
    }
    /**
     * Builds the sql query with the where.
     *
     * When $retWhere is false, returns ['sql' => string, 'params' => array]
     * where params contains named PDO placeholders (e.g. 'where_0' => value).
     * When $retWhere is true, returns only the WHERE clause string with values
     * safely escaped via PDO::quote() for use with the DataTables complex() path.
     *
     * @param string $sql        The sql string we need to adjust.
     * @param array  $classVars  The current class variables.
     * @param mixed  $whereItems The where items to build up.
     * @param bool   $retWhere   Only return where element.
     * @param string $operator   The logical operator between conditions.
     * @param string $orderby    How to order the returned values.
     *
     * @return string|array
     */
    private static function _buildSql(
        $sql,
        $classVars,
        $whereItems = '',
        $retWhere = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            if (empty($operator)) {
                $operator = 'AND';
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }

            $whereItems = self::handleWhereItems($whereItems);
            // If the caller passed any filter as an empty array, they mean
            // "value IN ()" — logically match nothing. Stripping the empty key
            // and letting the rest of the WHERE run would silently broaden
            // the query, returning rows the caller never asked for.
            $hadFilters = is_array($whereItems) && count($whereItems) > 0;
            $emptyArrayFilter = false;
            if ($hadFilters) {
                foreach ($whereItems as $v) {
                    if (is_array($v) && count($v) < 1) {
                        $emptyArrayFilter = true;
                        break;
                    }
                }
            }
            $whereItems = array_filter(
                $whereItems ?: [],
                function ($v) {
                    return !is_array($v) || count($v) > 0;
                }
            );

            // Filters were supplied but nothing survived (or any filter was an
            // empty IN-set) → match nothing.
            if ($hadFilters && ($emptyArrayFilter || count($whereItems) < 1)) {
                if ($retWhere) {
                    return '1=0';
                }
                $sql .= ' WHERE 1=0 ORDER BY `'
                    . (
                        isset($classVars['databaseFields'][$orderby]) ?
                        $classVars['databaseFields'][$orderby] :
                        $classVars['databaseFields']['id']
                    )
                    . '` ASC';
                return ['sql' => $sql, 'params' => []];
            }

            $params = [];
            if (count($whereItems) > 0) {
                $where = '';
                $paramIdx = 0;
                foreach ($whereItems as $key => &$field) {
                    if (!$where) {
                        $where = (!$retWhere ? ' WHERE `' : ' `')
                            . $classVars['databaseFields'][$key]
                            . '`';
                    } else {
                        $where .= ' ' . $operator . ' `'
                            . $classVars['databaseFields'][$key]
                            . '`';
                    }
                    if (is_array($field)) {
                        if ($retWhere) {
                            $db = DatabaseManager::getLink();
                            $escaped = array_map([$db, 'quote'], $field);
                            $where .= ' IN (' . implode(',', $escaped) . ')';
                        } else {
                            $placeholders = [];
                            foreach ($field as $idx => $val) {
                                $pname = 'where_' . $paramIdx . '_' . $idx;
                                $placeholders[] = ':' . $pname;
                                $params[$pname] = $val;
                            }
                            $where .= ' IN (' . implode(',', $placeholders) . ')';
                        }
                    } else {
                        // No wildcard rewriting here. '*'/'+' are expanded at
                        // the request-facing entry points only (see
                        // expandSearchWildcards); a value assembled in PHP is
                        // matched literally, so a permission string like '*'
                        // or a path containing '+' cannot turn itself into a
                        // LIKE that matches far more than the caller meant. A
                        // caller wanting a pattern passes '%' explicitly.
                        $oper = false !== strpos((string)$field, '%')
                            ? 'LIKE'
                            : '=';
                        if ($retWhere) {
                            $db = DatabaseManager::getLink();
                            $where .= ' ' . $oper . ' ' . $db->quote($field);
                        } else {
                            $pname = 'where_' . $paramIdx;
                            $params[$pname] = $field;
                            $where .= ' ' . $oper . ' :' . $pname;
                        }
                    }
                    $paramIdx++;
                }
                $sql .= $where;
            }
            if ($retWhere) {
                return isset($where) ? $where : '';
            }
            $sql .= ' ORDER BY `'
                . (
                    isset($classVars['databaseFields'][$orderby]) ?
                    $classVars['databaseFields'][$orderby] :
                    $classVars['databaseFields']['id']
                )
                . '` ASC';

            return ['sql' => $sql, 'params' => $params];
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Returns only the ids and names of the class.
     *
     * @param string $class      The class to get list of.
     * @param string $whereItems If we want to filter items.
     * @param string $operator   The operator for the SQL. AND is default.
     * @param string $orderby    How to order the returned values.
     *
     * @return mixed
     */
    public static function names(
        $class,
        $whereItems = [],
        $operator = 'AND',
        $orderby = 'name'
    ) {
        try {
            header('Content-type: application/json');
            if (empty($operator)) {
                $operator = 'AND';
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }
            $data = [];
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );

            $sql = 'SELECT `'
                . $classVars['databaseFields']['id']
                . '`,`'
                . $classVars['databaseFields']['name']
                . '` FROM `'
                . $classVars['databaseTable']
                . '`';
            
            $whereItems = self::handleWhereItems($whereItems);
            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }

            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );
            $vals = self::$DB->query($sqlResult['sql'], [], $sqlResult['params'])->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
            foreach ($vals as &$val) {
                $data[] = [
                    'id' => $val[$classVars['databaseFields']['id']],
                    'name' => $val[$classVars['databaseFields']['name']]
                ];
                unset($val);
            }

            self::$data = $data;
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Allows joining items.
     *
     * @param string $class The class to join items to.
     *
     * @return void
     */
    public function joining($class)
    {
        try {
            $classname = strtolower($class);
            $classVars = self::getClass(
                $class,
                '',
                true
            );
            $vars = json_decode(
                file_get_contents('php://input')
            );
            if ('POST' == self::$reqmethod) {
                if ($classname != 'group') {
                    self::sendResponse(
                        HTTPResponseCodes::HTTP_BAD_REQUEST
                    );
                }
            }
            $classman = self::getClass($class.'Manager');
            switch (self::$reqmethod) {
                case 'PUT':
                    Route::listem(
                        $classname,
                        ['id' => $vars->ids]
                    );
                    $classes = json_decode(
                        Route::getData()
                    );
                    foreach ($classes->data as &$c) {
                        $c = self::getClass($classname, $c->id);
                        foreach ($classVars['databaseFields'] as &$key) {
                            $key = $c->key($key);
                            if (!isset($vars->$key)) {
                                $val = $c->get($key);
                            } else {
                                $val = $vars->$key;
                            }
                            if ($key == 'id') {
                                continue;
                            }
                            $c->set($key, $val);
                            unset($key);
                        }
                        switch ($classname) {
                            case 'host':
                                if (isset($vars->macs)) {
                                    $c->addMAC($vars->macs);
                                }
                                if (isset($vars->snapins)) {
                                    $c->addSnapin($vars->snapins);
                                }
                                if (isset($vars->printers)) {
                                    $c->addPrinter($vars->printers);
                                }
                                if (isset($vars->modules)) {
                                    $c->addModules($vars->modules);
                                }
                                if (isset($vars->groups)) {
                                    $c->addGroup($vars->groups);
                                }
                                break;
                            case 'group':
                                if (isset($vars->hosts)) {
                                    $c->addHost($vars->hosts);
                                }
                                if (isset($vars->snapins)) {
                                    $c->addSnapin($vars->snapins);
                                }
                                if (isset($vars->printers)) {
                                    $c->addPrinter($vars->printers);
                                }
                                if (isset($vars->modules)) {
                                    $c->addModule($vars->modules);
                                }
                                if ($vars->imageID) {
                                    $c->addImage($vars->imageID);
                                }
                                break;
                            case 'image':
                            case 'snapin':
                                if (isset($vars->hosts)) {
                                    $c->addHost($vars->hosts);
                                }
                                if (isset($vars->storagegroups)) {
                                    $c->addGroup($vars->storagegroups);
                                }
                                break;
                            case 'printer':
                                if (isset($vars->hosts)) {
                                    $c->addHost($vars->hosts);
                                }
                        }
                        // Store the data and recreate.
                        // If failed present so.
                        if (!$c->save()) {
                            self::sendResponse(
                                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                            );
                        }
                        unset($c);
                    }
                    $code = HTTPResponseCodes::HTTP_ACCEPTED;
                    break;
                case 'POST':
                    $ids = [];
                    foreach ($vars->names as &$name) {
                        $exists = $classman->exists($name);
                        $id = Route::getIds(
                            $classname,
                            ['name' => $name]
                        );
                        if ($exists) {
                            foreach ($id as &$i) {
                                $ids[] = $i;
                                unset($i);
                            }
                            continue;
                        }
                        $c = self::getClass($classname)
                            ->set('name', $name);
                        if (!$c->save()) {
                            self::sendResponse(
                                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                            );
                        }
                        $ids[] = $c->get('id');
                        unset($name);
                    }
                    Route::listem(
                        $classname,
                        ['id' => $ids]
                    );
                    $classes = json_decode(
                        Route::getData()
                    );
                    foreach ($classes->data as &$c) {
                        $c = self::getClass($classname, $c->id);
                        if (count($vars->hosts ?: [])) {
                            $c->addHost($vars->hosts);
                        }
                        // Store the data and recreate.
                        // If failed present so.
                        if (!$c->save()) {
                            self::sendResponse(
                                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                            );
                        }
                        unset($c);
                    }
                    $code = HTTPResponseCodes::HTTP_CREATED;
                    break;
                default:
                    $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            }
            self::sendResponse($code);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Presents pending mac addresses.
     *
     * @return void
     */
    public static function pendingmacs()
    {
        Route::listem(
            'macaddressassociation',
            ['pending' => [1]]
        );
    }
    /**
     * Presents the kernel or initrd listing from github
     * @param array $data The data from github to parse.
     * @param string $type The type of data to parse, either kernel or initrd
     *
     * @return array The parsed data to present in the frontend
     */
    public static function kernelOrInitJson($data, $type)
    {
        if ($type != 'kernel' && $type != 'initrd') {
            return [];
        }
        $jsonData = [];
        foreach ($data as &$release) {
            if ($type == 'kernel') {
                $patt = '/Linux kernel (.*)?/';
            } elseif ($type == 'initrd') {
                $patt = '/Buildroot (.*)?/';
            }
            $found_match = preg_match(
                $patt,
                $release->body,
                $release_version,
                PREG_OFFSET_CAPTURE
            );
            if (!$found_match) {
                continue;
            }
            $rel_ver = $release->tag_name;
            foreach ($release->assets as &$asset) {
                if ($type == 'kernel' && !in_array($asset->name, ['arm_Image', 'bzImage', 'bzImage32'])) {
                    continue;
                }
                if ($type == 'initrd' && !in_array($asset->name, ['arm_init.cpio.gz', 'init.xz', 'init_32.xz'])) {
                    continue;
                }
                $k_i_ver = $release_version[1][0];
                $arch_short = '';
                $arch = '';
                switch ($asset->name) {
                    case 'arm_Image':
                    case 'arm_init.cpio.gz':
                        $arch_short = 'arm64';
                        $arch = _('ARM 64 Bit');
                        break;
                    case 'bzImage':
                    case 'init.xz':
                        $arch_short = '64';
                        $arch = _('Intel 64 Bit');
                        break;
                    case 'bzImage32':
                    case 'init_32.xz':
                        $arch_short = '32';
                        $arch = _('Intel 32 Bit');
                        break;
                }
                if ($arch_short) {
                    $download_url = base64_encode($asset->browser_download_url);
                    switch (substr($release->name, 0, 3)) {
                        case 'FOG':
                            $_fogParts = explode(' ', $release->name);
                            $k_hint = ' (FOG ' . ($_fogParts[1] ?? '?') . ')';
                            break;
                        case 'Lat':
                            $k_hint = ' (devel)';
                            break;
                        case 'Exp':
                            $k_hint = ' (experimental)';
                            break;
                        default:
                            $k_hint = '';
                            break;

                    }
                    $id = ucfirst($type)
                        . '_'
                        . str_replace(
                            '.',
                            '_',
                            $k_i_ver
                        )
                        . '_'
                        . $arch_short;
                    $date = date('F j, Y', strtotime($asset->created_at));
                    $version = $k_i_ver;
                    $k_i_type = $k_hint;
                    $download = "../management/index.php?node=about&sub=$type"
                        . "&file=$download_url&arch=$arch_short";
                    $jsonData[] = [
                        'id' => $id,
                        'date' => $date,
                        'version' => $version,
                        'type' => $k_i_type,
                        'arch' => $arch,
                        'download' => $download,
                        'tag_name' => $rel_ver
                    ];
                }
            }
        }

        return $jsonData;
    }
    /**
     * Presents the kernel listing from fogproject.org
     *
     * @return void
     */
    public static function availablekernels()
    {
        $assetsInfo = self::$FOGURLRequests->process(
            //'https://fogproject.org/kernels/kernelupdate_datatables_fog2.php'
            'https://api.github.com/repos/FOGProject/fos/releases'
        );

        self::$data = self::kernelOrInitJson(json_decode($assetsInfo[0]), 'kernel');
    }
    /**
     * Presents the Initrd listing from github
     *
     * @return void
     */
    public static function availableinitrds()
    {
        $assetsInfo = self::$FOGURLRequests->process(
            //'https://fogproject.org/kernels/kernelupdate_datatables_fog2.php'
            'https://api.github.com/repos/FOGProject/fos/releases'
        );

        self::$data = self::kernelOrInitJson(json_decode($assetsInfo[0]), 'initrd');
    }
    /**
     * Return node's log files.
     *
     * @return void
     */
    public static function logfiles($id)
    {
        self::$data = self::getClass('StorageNode', $id)->get('logfiles');
    }
    /**
     * Return node's image files.
     *
     * @return void
     */
    public static function imagefiles($id)
    {
        self::$data = self::getClass('StorageNode', $id)->get('images');
    }
    /**
     * Return node's snapin files.
     *
     * @return void
     */
    public static function snapinfiles($id)
    {
        self::$data = self::getClass('StorageNode', $id)->get('snapinfiles');
    }
    /**
     * Returns settings from fogsettings file.
     *
     * @return void
     */
    public static function whoami()
    {
        $data = parse_ini_file('/opt/fog/.fogsettings', true);
        extract($data);
        self::$data = [
            'ipaddress' => $ipaddress,
            'hostname' => $hostname,
            'osid' => $osid,
            'osname' => $osname,
            'installtype' => $installtype
        ];
    }
}
