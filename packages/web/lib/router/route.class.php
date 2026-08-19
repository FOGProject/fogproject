<?php
/**
 * Creates our routes for api configuration.
 *
 * PHP version 7.4+
 *
 * @category Route
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
/**
 * Creates our routes for api configuration.
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
     * The configured webroot in '/x/' form, e.g. '/fog/'.
     *
     * GH-529: every API route is registered under this, and it used to be the
     * literal '/fog', so at a custom webroot no route matched at all -- the
     * API answered 501 for endpoints that exist.
     *
     * @var string
     */
    private static $_webrootbase = '/fog/';
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
    public static $matches = array();
    /**
     * Stores the data to print.
     *
     * @var mixed
     */
    public static $data;
    /**
     * Stores the valid classes.
     *
     * @var array
     */
    public static $validClasses = array(
        'clientupdater',
        'dircleaner',
        'greenfog',
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
        'scheduledtask',
        'service',
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
        //'user',
        'usercleanup',
        'usertracking',
        'virus'
    );
    /**
     * Valid Tasking classes.
     *
     * @var array
     */
    public static $validTaskingClasses = array(
        'group',
        'host',
        'multicastsession',
        'scheduledtask',
        'snapinjob',
        'snapintask',
        'task'
    );
    /**
     * Names not unique
     *
     * @var array
     */
    public static $nonUniqueNameClasses = array(
        'scheduledtask',
        'task'
    );
    /**
     * Valid active tasking classes.
     *
     * @var array
     */
    public static $validActiveTasks = array(
        'multicastsession',
        'scheduledtask',
        'snapinjob',
        'snapintask',
        'task'
    );
    /**
     * Classes a non-administrator (uType != 0) may reach.
     *
     * The uType 1 "mobile" account exists to look at hosts and images and
     * task them; that is the whole of what this list covers, plus the
     * association and lookup tables needed to render those objects.
     *
     * Everything absent is administrator-only, and absence is the default --
     * notably storagenode and storagegroup (their ftpUser/ftpPass reach the
     * root-running replicator and are returned in cleartext by the read
     * routes), service (the globalSettings table), plugin, ipxe and
     * pxemenuoptions (boot configuration), and anything a plugin injects via
     * API_VALID_CLASSES. See _requireAuthorized(); GHSA-2hqx-5ffg-w4c3.
     *
     * @var array
     */
    public static $nonAdminClasses = array(
        'group',
        'groupassociation',
        'host',
        'image',
        'imageassociation',
        'imagepartitiontype',
        'imagetype',
        'macaddressassociation',
        'multicastsession',
        'os',
        'scheduledtask',
        'snapin',
        'snapinassociation',
        'snapingroupassociation',
        'snapinjob',
        'snapintask',
        'task',
        'taskstate',
        'tasktype'
    );
    /**
     * Route names a non-administrator (uType != 0) may reach, subject to the
     * class allowlist above. Read-only: every route that creates, edits or
     * deletes a record requires an administrator, as does /system/export.
     * Tasking ('task', 'cancel') is added separately in _requireAuthorized()
     * because it writes but is the mobile account's entire purpose.
     *
     * @var array
     */
    public static $nonAdminRoutes = array(
        'active',
        'ids',
        'indiv',
        'list',
        'listdetails',
        'names',
        'search'
    );
    /**
     * Initialize element.
     *
     * @return void
     */
    public function __construct()
    {
        list(
            self::$_enabled,
            self::$_token
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_API_ENABLED',
                    'FOG_API_TOKEN'
                )
            ),
            'value'
        );
        /**
         * GH-529: normalise the configured webroot rather than trusting the
         * stored form -- it is written by the installer, edited by hand in FOG
         * Settings, and carried by older versions, so it turns up with and
         * without either slash.
         */
        $webrootbase = trim((string)self::getSetting('FOG_WEB_ROOT'), '/');
        self::$_webrootbase = '/' . ($webrootbase === '' ? '' : $webrootbase . '/');
        /**
         * If API is not enabled redirect to home page.
         */
        if (!self::$_enabled) {
            header(
                sprintf(
                    'Location: %s://%s%smanagement/index.php',
                    self::$httpproto,
                    self::$httphost,
                    self::$_webrootbase
                )
            );
            exit;
        }
        if (!self::$FOGUser->isValid()) {
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
         * Ensure api has unlimited time.
         */
        ignore_user_abort(true);
        session_write_close();
        set_time_limit(0);
        /**
         * Define the event so plugins/hooks can modify what/when/where.
         */
        self::$HookManager
            ->processEvent(
                'API_VALID_CLASSES',
                array(
                    'validClasses' => &self::$validClasses
                )
            );
        self::$HookManager
            ->processEvent(
                'API_TASKING_CLASSES',
                array(
                    'validTaskingClasses' => &self::$validTaskingClasses
                )
            );
        self::$HookManager
            ->processEvent(
                'API_ACTIVE_TASK_CLASSES',
                array(
                    'validActiveTasks' => &self::$validActiveTasks
                )
            );
        /**
         * If the router is already defined,
         * don't re-instantiate it.
         */
        if (self::$router) {
            return;
        }
        /**
         * GH-529: the base path was the literal '/fog', so every route was
         * registered somewhere the request could never reach on a custom
         * webroot. AltoRouter wants it without the trailing slash, and an
         * install served from the document root itself wants it empty.
         */
        self::$router = new AltoRouter(
            array(),
            rtrim(self::$_webrootbase, '/')
        );
        self::defineRoutes();
        self::setMatches();
        self::runMatches();
        self::printer(self::$data);
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
        self::$router
            ->map(
                'HEAD|GET',
                '/system/[status|info]',
                array(__CLASS__, 'status'),
                'status'
            )
            ->get(
                '/system/export',
                array(__CLASS__, 'export'),
                'export'
            )
            ->get(
                "{$expandeda}/[current|active]",
                array(__CLASS__, 'active'),
                'active'
            )
            ->get(
                "{$expanded}/search/[*:item]",
                array(__CLASS__, 'search'),
                'search'
            )
            ->get(
                "{$expanded}/[list|all]?",
                array(__CLASS__, 'listem'),
                'list'
            )
            ->get(
                "{$expanded}/[details]/?[*:item]?",
                array(__CLASS__, 'listdetails'),
                'listdetails'
            )
            ->get(
                "{$expanded}/[i:id]/?[*:item]?",
                array(__CLASS__, 'indiv'),
                'indiv'
            )
            ->get(
                "{$expanded}/names/[*:whereItems]?",
                array(__CLASS__, 'names'),
                'names'
            )
            ->get(
                "{$expanded}/ids/[*:whereItems]?/[*:getField]?",
                array(__CLASS__, 'ids'),
                'ids'
            )
            ->put(
                "{$expanded}/[i:id]/[update|edit]?",
                array(__CLASS__, 'edit'),
                'update'
            )
            ->post(
                "{$expandedt}/[i:id]/[task]",
                array(__CLASS__, 'task'),
                'task'
            )
            ->post(
                '/snapin/createwithfile',
                array(__CLASS__, 'createSnapinWithFile'),
                'snapinCreateWithFile'
            )
            ->post(
                '/storagegroup/[i:id]/uploadsnapinfiles',
                array(__CLASS__, 'uploadSnapinFiles'),
                'uploadSnapinFiles'
            )
            ->post(
                "{$expanded}/[create|new]?",
                array(__CLASS__, 'create'),
                'create'
            )
            ->delete(
                "{$expandedt}/[i:id]?/[cancel]",
                array(__CLASS__, 'cancel'),
                'cancel'
            )
            ->delete(
                "{$expanded}/[i:id]/[delete|remove]?",
                array(__CLASS__, 'delete'),
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
     * Runs the matches.
     *
     * @return void
     */
    public static function runMatches()
    {
        if (self::$matches
            && is_callable(self::$matches['target'])
        ) {
            self::_requireAuthorized(
                isset(self::$matches['name']) ? self::$matches['name'] : '',
                isset(self::$matches['params']['class'])
                ? self::$matches['params']['class']
                : ''
            );
            call_user_func_array(
                self::$matches['target'],
                array_values(self::$matches['params'])
            );
            return;
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
        );
    }
    /**
     * Gates the matched route on the acting user's uType.
     *
     * GHSA-2hqx-5ffg-w4c3: authentication was the only test the API ever
     * made. Any account with a valid token and uAllowAPI set reached every
     * route on every class -- including PUT /storagenode/<id>/edit, whose
     * ftpUser/ftpPass/ip land in the root-running replicator's lftp
     * invocation, and GET /storagenode/<id>, which returns that password in
     * cleartext. `service` is the globalSettings table, so the same account
     * could rewrite every FOG setting.
     *
     * 1.5 has no RBAC, so uType 0 is the only boundary there is. Rather than
     * make the API admin-only outright, this keeps the one thing a uType 1
     * "mobile" account was ever meant to do -- look at hosts and images and
     * task them -- and requires an administrator for everything else.
     *
     * The policy is deliberately deny-by-default: a class absent from
     * $nonAdminClasses (anything a plugin injects through API_VALID_CLASSES
     * included) is administrator-only for non-admins. Widening it is a
     * deliberate edit here, never something a plugin can do by accident.
     *
     * Enforced at dispatch rather than per-handler so there is exactly one
     * place to audit, matching where 1.6 puts its RBAC check.
     *
     * @param string $name  The matched route's name.
     * @param string $class The class the route is acting on, if any.
     *
     * @return void
     */
    private static function _requireAuthorized($name, $class)
    {
        if (self::isAdminUser()) {
            return;
        }
        /**
         * Dashboard polling. Note this covers /system/status and
         * /system/info only -- /system/export is a full database dump and
         * is a separate route, so it falls through to the denial below.
         */
        if ($name === 'status') {
            return;
        }
        $allowed = self::fastmerge(
            self::$nonAdminRoutes,
            array('task', 'cancel')
        );
        if (in_array($name, $allowed, true)
            && in_array(strtolower((string)$class), self::$nonAdminClasses, true)
        ) {
            return;
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_FORBIDDEN
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
        if ($passtoken !== self::$_token) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_FORBIDDEN
            );
        }
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
        $pwtoken = self::getClass('User')
            ->set('token', $usertoken)
            ->load('token');
        if ($pwtoken->isValid() && $pwtoken->get('api')) {
            /**
             * Bind the token's owner as the acting user. Without this the
             * request runs with no identity at all, so _requireAuthorized()
             * below has nothing to test uType against. GHSA-2hqx-5ffg-w4c3.
             */
            self::$FOGUser = $pwtoken;
            return;
        }
        $auth = self::$FOGUser->passwordValidate(
            $_SERVER['PHP_AUTH_USER'],
            $_SERVER['PHP_AUTH_PW']
        );
        if (!$auth) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
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
        self::sendResponse(
            HTTPResponseCodes::HTTP_SUCCESS,
            "success\n"
        );
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
        $backup_name = sprintf(
            'fog_backup_%s.sql',
            self::formatTime('', 'Ymd_His')
        );
        self::getClass('Schema')->exportdb($backup_name);
        exit;
    }
    /**
     * Presents the equivalent of a page's list all.
     *
     * @param string $class  The class to work with.
     * @param string $sortby How to sort the data.
     * @param bool   $bypass Allow showing hidden data.
     * @param array  $find   Additional filter items.
     *
     * @return void
     */
    public static function listem(
        $class,
        $sortby = 'name',
        $bypass = false,
        $find = array(),
        $item = ''
    ) {
        $classname = strtolower($class);
        $classman = self::getClass($class)->getManager();
        self::$data = array();
        self::$data['count'] = 0;
        self::$data[$classname.'s'] = array();
        $find = self::fastmerge(
            $find,
            self::getsearchbody($classname)
        );
        switch ($classname) {
            case 'plugin':
                self::$data['count_active'] = 0;
                self::$data['count_installed'] = 0;
                self::$data['count_not_active'] = 0;
                foreach (self::getClass('Plugin')->getPlugins() as $class) {
                    self::$data[$classname.'s'][] = self::getter(
                        $classname,
                        $class,
                        $item
                    );
                    if ($class->isActive() && !$class->isInstalled()) {
                        self::$data['count_active']++;
                    }
                    if ($class->isActive() && $class->isInstalled()) {
                        self::$data['count_installed']++;
                    }
                    if (!$class->isActive() && !$class->isInstalled()) {
                        self::$data['count_not_active']++;
                    }
                    self::$data['count']++;
                    unset($class);
                }
                break;
            default:
                foreach ((array)$classman->find($find, 'AND', $sortby) as &$class) {
                    $test = stripos(
                        $class->get('name'),
                        '_api_'
                    );
                    if (!$bypass && false != $test) {
                        continue;
                    }
                    self::$data[$classname.'s'][] = self::getter(
                        $classname,
                        $class,
                        $item
                    );
                    self::$data['count']++;
                    unset($class);
                }
                break;
        }
        self::$HookManager
            ->processEvent(
                'API_MASSDATA_MAPPING',
                array(
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'classman' => &$classman
                )
            );
    }
    /**
     * Presents the equivalent of a detailed page list.
     *
     * @param string $class  The class to work with.
     * @param string $sortby How to sort the data.
     * @param bool   $bypass Allow showing hidden data.
     * @param array  $find   Additional filter items.
     *
     * @return void
     */
    public static function listdetails(
        $class,
        $item,
        $sortby = 'name',
        $bypass = false,
        $find = array()
    ) {
        $item = empty($item) ? 'all' : $item;
        self::listem($class, $sortby, $bypass, $find, $item);
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
        $classname = strtolower($class);
        $classman = self::getClass($class)->getManager();
        self::$data = array();
        self::$data['count'] = 0;
        self::$data[$classname.'s'] = array();
        foreach ($classman->search($item, true) as &$class) {
            if (false != stripos($class->get('name'), '_api_')) {
                continue;
            }
            self::$data[$classname.'s'][] = self::getter(
                $classname,
                $class
            );
            self::$data['count']++;
            unset($class);
        }
        self::$HookManager
            ->processEvent(
                'API_MASSDATA_MAPPING',
                array(
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'classman' => &$classman
                )
            );
    }
    /**
     * Displays the individual item.
     *
     * @param string $class The class to work with.
     * @param int    $id    The id of the item.
     *
     * @return void
     */
    public static function indiv($class, $id, $item = '')
    {
        $classname = strtolower($class);
        $class = new $class($id);
        if (!$class->isValid()) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
        }
        self::$data = array();
        self::$data = self::getter(
            $classname,
            $class,
            $item
        );
        self::$HookManager
            ->processEvent(
                'API_INDIVDATA_MAPPING',
                array(
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'class' => &$class
                )
            );
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
            if ($key == 'id') {
                unset($key);
                continue;
            }
            // A field the body did not mention is left exactly as loaded.
            // It used to be re-set to its own current value, which reads
            // as a no-op and is not: set() may transform, and User::set()
            // hashes any non-override write to 'password'. So every PUT to
            // a user re-hashed the stored hash -- password_verify() then
            // fails against the real password and that account is locked
            // out permanently, with the request answering 200. save()
            // builds its statement from $this->data for every
            // databaseField regardless of what was set(), so skipping is
            // otherwise byte-identical.
            if (!isset($vars->$key)) {
                unset($key);
                continue;
            }
            $class->set($key, $vars->$key);
            unset($key);
        }
        switch ($classname) {
            case 'host':
                if (isset($vars->macs)) {
                    $macsToAdd = array_diff(
                        (array)$vars->macs,
                        $class->getMyMacs()
                    );
                    $primac = array_shift($macsToAdd);
                    $macsToRem = array_diff(
                        $class->getMyMacs(),
                        (array)$vars->macs
                    );
                    $class
                        ->removeAddMAC($macsToRem)
                        ->addPriMAC($primac)
                        ->addAddMAC($macsToAdd);
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
                    Route::ids('snapin');
                    $snapins = json_decode(
                        Route::getData(),
                        true
                    );
                    $snapinsToRem = array_diff(
                        $snapins,
                        (array)$vars->snapins
                    );
                    $class
                        ->removeSnapin($snapinsToRem)
                        ->addSnapin($vars->snapins);
                }
                if (isset($vars->printers)) {
                    Route::ids('printer');
                    $printers = json_decode(
                        Route::getData(),
                        true
                    );
                    $printersToRem = array_diff(
                        $printers,
                        (array)$vars->printers
                    );
                    $class
                        ->removePrinter($printersToRem)
                        ->addPrinter($vars->printers);
                }
                if (isset($vars->modules)) {
                    Route::ids('module');
                    $modules = json_decode(
                        Route::getData(),
                        true
                    );
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
                if ($vars->imageID) {
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
                        ->addgroup($storageGroupsToAdd);
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
        $tids = self::getSubObjectIDs('TaskType');
        $task = json_decode(
            file_get_contents('php://input')
        );
        $TaskType = new TaskType($task->taskTypeID);
        if (!$TaskType->isValid()) {
            $message = _('Invalid tasking type passed');
            self::setErrorMessage(
                $message,
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
        }
        try {
            $class->createImagePackage(
                $task->taskTypeID,
                isset($task->taskName) ? $task->taskName : '',
                isset($task->shutdown) ? $task->shutdown : false,
                isset($task->debug) ? $task->debug : false,
                (
                    (isset($task->deploySnapins) && $task->deploySnapins === true) ?
                    -1 :
                    (
                        (isset($task->deploySnapins)
                        && is_numeric($task->deploySnapins)
                        && $task->deploySnapins > 0)
                        || isset($task->deploySnapins) && $task->deploySnapins == -1 ?
                        $task->deploySnapins :
                        false
                    )
                ),
                $class instanceof Group,
                isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '',
                isset($task->passreset) ? $task->passreset : '',
                isset($task->sessionjoin) ? $task->sessionjoin : false,
                isset($task->wol) ? $task->wol : false
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
        $exists = self::getClass($classname)
            ->getManager()
            ->exists($vars->name);
        $uniqueNames = !in_array($classname, self::$nonUniqueNameClasses);
        if ($exists && $uniqueNames) {
            self::setErrorMessage(
                _('Already created'),
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        foreach ($classVars['databaseFields'] as &$key) {
            $key = $class->key($key);
            if (!isset($vars->$key)) {
                continue;
            }
            $val = $vars->$key;
            if ($key == 'id'
                || null === $val
            ) {
                continue;
            }
            $class->set($key, $val);
            unset($key);
        }
        switch ($classname) {
            case 'host':
                if (count($vars->macs)) {
                    $class
                        ->removeAddMAC($vars->macs)
                        ->addPriMAC(array_shift($vars->macs))
                        ->addAddMAC($vars->macs);
                }
                if (isset($vars->snapins)) {
                    $class
                        ->addSnapin($vars->snapins);
                }
                if (isset($vars->printers)) {
                    $class
                        ->addPrinter($vars->printers);
                }
                if (isset($vars->modules)) {
                    $class
                        ->addModule($vars->modules);
                }
                if (isset($vars->groups)) {
                    $class
                        ->addGroup($vars->groups);
                }
                break;
            case 'group':
                if (isset($vars->snapins)) {
                    $class
                        ->addSnapin($vars->snapins);
                }
                if (isset($vars->printers)) {
                    $class
                        ->addPrinter($vars->printers);
                }
                if (isset($vars->modules)) {
                    $class
                        ->addModule($vars->modules);
                }
                if (isset($vars->hosts)) {
                    $class
                        ->addHost($vars->hosts);
                    if (isset($vars->imageID)) {
                        $class
                            ->addImage($vars->imageID);
                    }
                }
                break;
            case 'image':
            case 'snapin':
                if (isset($vars->hosts)) {
                    $class
                        ->addHost($vars->hosts);
                }
                if (isset($vars->storagegroups)) {
                    $class
                        ->addGroup($vars->storagegroups);
                }
                break;
            case 'printer':
                if (isset($vars->hosts)) {
                    $class
                        ->addHost($vars->hosts);
                }
                break;
        }
        global $foglang;
        foreach ($classVars['databaseFieldsRequired'] as &$key) {
            $key = $class->key($key);
            $val = $class->get($key);
            if (null === $val) {
                self::setErrorMessage(
                    $foglang['RequiredDB'] . ": " . $key,
                    HTTPResponseCodes::HTTP_EXPECTATION_FAILED
                );
            }
        }
        // Store the data and recreate.
        // If failed present so.
        if ($class->save()) {
            $id = $class->get('id');
            $class = new $class($id);
        } else {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        self::indiv($classname, $id);
    }
    /**
     * Create a Snapin from a multipart upload.
     *
     * POST /fog/snapin/createwithfile
     *
     * The only Snapin endpoint that accepts a binary file. Delegates
     * validation / FTP / DB save to Snapin::uploadAndCreate, then returns
     * the freshly-loaded row using the standard indiv() formatter so the
     * response shape matches GET /fog/snapin/<id>.
     *
     * @return void
     */
    public static function createSnapinWithFile()
    {
        try {
            $Snapin = Snapin::uploadAndCreate($_POST, $_FILES);
        } catch (SnapinSaveException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                json_encode(
                    array(
                        'error' => $e->getMessage(),
                        'title' => _('Snapin Create Fail'),
                    )
                )
            );
            return;
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(
                    array(
                        'error' => $e->getMessage(),
                        'title' => _('Snapin Create Fail'),
                    )
                )
            );
            return;
        } catch (\RuntimeException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                json_encode(
                    array(
                        'error' => $e->getMessage(),
                        'title' => _('Snapin Create Fail'),
                    )
                )
            );
            return;
        }
        self::indiv('snapin', $Snapin->get('id'));
        self::printer(self::$data, HTTPResponseCodes::HTTP_CREATED);
    }
    /**
     * Upload one or more snapin files to a Storage Group's Master Node
     * without creating any database row. Files land in the snapin path;
     * FOGSnapinReplicator distributes them to other nodes on its cycle.
     *
     * POST /fog/storagegroup/[i:id]/uploadsnapinfiles
     *
     * Form field MUST be 'snapinfiles[]' (the [] is what makes PHP
     * populate $_FILES as a multi-file array, even for one file).
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
                    json_encode(
                        array(
                            'error' => _('Storage Group not found'),
                            'title' => _('Snapin File Upload Fail'),
                        )
                    )
                );
                return;
            }
            if (empty($_FILES['snapinfiles']['name'])
                || !is_array($_FILES['snapinfiles']['name'])
            ) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    json_encode(
                        array(
                            'error' => _(
                                'One or more files must be uploaded via the "snapinfiles[]" multipart field'
                            ),
                            'title' => _('Snapin File Upload Fail'),
                        )
                    )
                );
                return;
            }
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode || !$StorageNode->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                    json_encode(
                        array(
                            'error' => _('Storage Group has no reachable Master Node'),
                            'title' => _('Snapin File Upload Fail'),
                        )
                    )
                );
                return;
            }
            Snapin::uploadFilesToNode($StorageNode, $_FILES['snapinfiles']);
            // sendResponse exits via breakHead, which prevents printer()
            // from later overriding the status / emitting a body. 204 = OK.
            self::sendResponse(HTTPResponseCodes::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(
                    array(
                        'error' => $e->getMessage(),
                        'title' => _('Snapin File Upload Fail'),
                    )
                )
            );
        } catch (\RuntimeException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                json_encode(
                    array(
                        'error' => $e->getMessage(),
                        'title' => _('Snapin File Upload Fail'),
                    )
                )
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
        $classname = strtolower($class);
        $class = new $class($id);
        // The states a task can be cancelled FROM. The same allowlist every
        // "is this task live" test uses, so anything outside it -- Complete
        // or Cancelled -- is already finished.
        $states = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        switch ($classname) {
            case 'group':
                if (!$class->isValid()) {
                    self::sendResponse(
                        HTTPResponseCodes::HTTP_NOT_FOUND
                    );
                }
                // isValid(), not instanceof. Host::loadTask() sets this field
                // to `new Task(null)` when the host has nothing running and
                // that IS instanceof Task, so the test was true for every
                // host in the group. Task::cancel() then opens with
                // getHost()->get('snapinjob'), and getHost() on an empty task
                // returns the empty STRING -- "Call to a member function
                // get() on string", an Error rather than an Exception. This
                // method has no catch at all, so one idle member killed the
                // request outright: every host after it in the loop kept its
                // task running, under a bodyless 500. Reproduced on the 1.6
                // copy of this code, whose Task::cancel() is identical here.
                $cancelled = 0;
                foreach (self::getClass('HostManager')
                    ->find(array('id' => $class->get('hosts'))) as &$Host
                ) {
                    $Task = $Host->get('task');
                    if ($Task instanceof Task && $Task->isValid()) {
                        $Task->cancel();
                        $cancelled++;
                    }
                    unset($Host);
                }
                if ($cancelled < 1) {
                    self::_notCancellable(
                        _('No active tasks to cancel for this group')
                    );
                }
                break;
            case 'host':
                if (!$class->isValid()) {
                    self::sendResponse(
                        HTTPResponseCodes::HTTP_NOT_FOUND
                    );
                }
                // Same empty-Task trap as the group arm above, reached one
                // host at a time: an idle host answered a bodyless 500
                // instead of saying it had nothing running.
                $Task = $class->get('task');
                if (!($Task instanceof Task) || !$Task->isValid()) {
                    self::_notCancellable(
                        _('Host has no active task to cancel')
                    );
                }
                $Task->cancel();
                break;
            case 'scheduledtask':
                // Carries isActive, not stateID, so it fell into the default
                // arm and failed a state test it could never pass: the
                // endpoint returned 200 and cancelled nothing, every time.
                // Its cancel() is a destroy(), which is what the management
                // page does, and there is no state to be wrong about.
                if (!$class->isValid()) {
                    self::sendResponse(
                        HTTPResponseCodes::HTTP_NOT_FOUND
                    );
                }
                $class->cancel();
                break;
            default:
                if (!$class->isValid()) {
                    $classman = $class->getManager();
                    $find = self::getsearchbody($classname, $class);
                    $find['stateID'] = $states;
                    $ids = self::getSubObjectIDs(
                        $classname,
                        $find
                    );
                    // A search that matches nothing stays a 200. This arm is
                    // a bulk filter and an empty result is a legitimate
                    // outcome for one; the 409s here are for a caller who
                    // named a specific resource.
                    $classman->cancel($ids);
                } else {
                    // Falling out of this test used to be silent -- the
                    // method returned normally and the caller was told the
                    // task had been cancelled while its state sat untouched.
                    if (!in_array($class->get('stateID'), $states)) {
                        $stateName = self::getClass(
                            'TaskState',
                            $class->get('stateID')
                        )->get('name');
                        self::_notCancellable(
                            sprintf(
                                '%s: %s',
                                _('Task is not active and cannot be cancelled'),
                                ($stateName ? $stateName : $class->get('stateID'))
                            )
                        );
                    }
                    $class->cancel();
                }
        }
    }
    /**
     * Refuses a cancel the named resource is not in a state to accept.
     *
     * The body is a JSON object rather than the bare reason string the older
     * non-2xx paths here emit: breakHead() has always declared
     * `Content-Type: application/json` and then echoed whatever it was given,
     * so those replies claim a type they are not. 409 is a status no caller
     * receives today, so it can start out matching its own header without
     * breaking anyone.
     *
     * @param string $msg Why the resource cannot be cancelled.
     *
     * @return void
     */
    private static function _notCancellable($msg)
    {
        self::sendResponse(
            HTTPResponseCodes::HTTP_CONFLICT,
            json_encode(array('msg' => $msg))
        );
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
        $classVars = self::getClass(
            $class,
            '',
            true
        );
        $vars = json_decode(
            file_get_contents('php://input')
        );
        $find = array();
        $class = new $class;
        foreach ($classVars['databaseFields'] as &$key) {
            $key = $class->key($key);
            if (isset($vars->$key)) {
                $find[$key] = $vars->$key;
            }
            unset($key);
        }
        return $find;
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
        $classname = strtolower($class);
        $classman = self::getClass($class)->getManager();
        $find = self::getsearchbody($classname);
        $states = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        switch ($classname) {
            case 'scheduledtask':
                $find['isActive'] = 1;
                break;
            case 'multicastsession':
            case 'snapinjob':
            case 'snapintask':
            case 'task':
                $find['stateID'] = $states;
        }
        self::$data = array();
        self::$data['count'] = 0;
        self::$data[$classname.'s'] = array();
        foreach ((array)$classman->find($find) as &$class) {
            self::$data[$classname.'s'][] = self::getter(
                $classname,
                $class
            );
            self::$data['count']++;
            unset($class);
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
        $classname = strtolower($class);
        $class = new $class($id);
        if (!$class->isValid()) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
        }
        $class->destroy();
        self::$data = '';
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
        self::$data['error'] = $message;
        self::printer(self::$data, $code);
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
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
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
        $message = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
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
     * This is a commonizing element so list/search/getinfo
     * will operate in the same fashion.
     *
     * @param string $classname The name of the class.
     * @param object $class     The class to work with.
     *
     * @return object|array
     */
    public static function getter($classname, $class, $item = '')
    {
        if (!$class instanceof $classname) {
            return;
        }
        switch ($classname) {
            case 'host':
                $pass = $class->get('ADPass');
                $passtest = self::aesdecrypt($pass);
                if ($test_base64 = base64_decode($passtest)) {
                    if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                        $pass = $test_base64;
                    } elseif (mb_detect_encoding($passtest, 'utf-8', true)) {
                        $pass = $passtest;
                    }
                }
                $productKey = $class->get('productKey');
                $productKeytest = self::aesdecrypt($productKey);
                if ($test_base64 = base64_decode($productKeytest)) {
                    if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                        $productKey = $test_base64;
                    } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
                        $productKey = $productKeytest;
                    }
                }
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
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
                        'pingstatus' => $class->getPingCodeStr(),
                        'pingstatuscode' => (int)$class->get('pingstatus'),
                        'pingstatustext' => socket_strerror((int)$class->get('pingstatus')),
                        'primac' => $class->get('mac')->__toString(),
                        'macs' => $class->getMyMacs()
                    )
                );
                break;
            case 'inventory':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'memory' => $class->getMem()
                    )
                );
                break;
            case 'group':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'hostcount' => $class->getHostCount()
                    )
                );
                break;
            case 'image':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'os' => $class->get('os')->get(),
                        'imagepartitiontype' => $class->get('imagepartitiontype')->get(),
                        'imagetype' => $class->get('imagetype')->get(),
                        'imagetypename' => $class->getImageType()->get('name'),
                        'imageparttypename' => $class->getImagePartitionType()->get(
                            'name'
                        ),
                        'osname' => $class->getOS()->get('name'),
                        'storagegroupname' => $class->getStorageGroup()->get('name')
                    )
                );
                break;
            case 'snapin':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'storagegroupname' => $class->getStorageGroup()->get('name')
                    )
                );
                break;
            case 'storagenode':
                $extra = array();
                if ($item == 'all') {
                    $extra = array(
                       'logfiles' => (
                           $class->get('online') ?
                            $class->get('logfiles') :
                            []
                       ),
                        'snapinfiles' => (
                            $class->get('online') ?
                            $class->get('snapinfiles') :
                            []
                        ),
                        'images' => (
                            $class->get('online') ?
                            $class->get('images') :
                            []
                        )
                    );
                } elseif (!empty($item)) {
                    $extra = array(
                       "$item" => (
                           $class->get('online') ?
                            $class->get($item) :
                            []
                       )
                    );
                }
                $data = FOGCore::fastmerge(
                    $class->get(),
                    $extra,
                    array(
                        'storagegroup' => self::getter(
                            'storagegroup',
                            $class->get('storagegroup')
                        ),
                        'clientload' => $class->getClientLoad(),
                        'online' => $class->get('online')
                    )
                );
                break;
            case 'storagegroup':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'totalsupportedclients' => $class->getTotalSupportedClients(),
                        'enablednodes' => $class->get('enablednodes'),
                        'allnodes' => $class->get('allnodes')
                    )
                );
                break;
            case 'task':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'image' => $class->get('image')->get(),
                        'host' => self::getter(
                            'host',
                            $class->get('host')
                        ),
                        'type' => $class->get('type')->get(),
                        'state' => $class->get('state')->get(),
                        'storagenode' => $class->get('storagenode')->get(),
                        'storagegroup' => $class->get('storagegroup')->get()
                    )
                );
                break;
            case 'plugin':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'location' => $class->getPath(),
                        'description' => $class->get('description'),
                        'icon' => $class->getIcon(),
                        'runinclude' => $class->getRuninclude(md5($class->get('name'))),
                        'hash' => md5($class->get('name'))
                    )
                );
                break;
            case 'imaginglog':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'host' => self::getter(
                            'host',
                            $class->get('host')
                        ),
                        'image' => (
                            $class->get('image')
                        )
                    )
                );
                unset($data['images']);
                break;
            case 'snapintask':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'snapin' => $class->get('snapin')->get(),
                        'snapinjob' => self::getter(
                            'snapinjob',
                            $class->get('snapinjob')
                        ),
                        'state' => $class->get('state')->get()
                    )
                );
                break;
            case 'snapinjob':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'host' => self::getter(
                            'host',
                            $class->get('host')
                        ),
                        'state' => $class->get('state')->get()
                    )
                );
                break;
            case 'usertracking':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'host' => self::getter(
                            'host',
                            $class->get('host')
                        )
                    )
                );
                break;
            case 'multicastsession':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'imageID' => $class->get('image'),
                        'image' => $class->get('imagename')->get(),
                        'state' => $class->get('state')->get()
                    )
                );
                unset($data['imagename']);
                break;
            case 'scheduledtask':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
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
                    )
                );
                break;
            case 'tasktype':
                $data = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'isSnapinTasking' => $class->isSnapinTasking()
                    )
                );
                break;
            default:
                $data = $class->get();
                break;
        }
        self::$HookManager
            ->processEvent(
                'API_GETTER',
                array(
                    'data' => &$data,
                    'classname' => &$classname,
                    'class' => &$class
                )
            );
        return $data;
    }
    /**
     * Normalizes the filter argument and vets its keys.
     *
     * The /names and /ids routes take their filter as a URL segment
     * ("[*:whereItems]"), and runMatches() hands matched segments over as
     * the raw strings they are. Both methods then went straight into
     * count($whereItems), which under PHP 8 is a TypeError -- so the
     * filtered form of these endpoints returned a 500 on any PHP 8 install
     * rather than a result. Parse the string into the array the rest of the
     * method already expects.
     *
     * Keys are checked here because making the string parse is what puts
     * them in reach: a key the class does not declare resolves to an empty
     * column identifier, the DB rejects the statement, and the caller is
     * told nothing useful. The offending key is named -- the caller is
     * authenticated, and an unexplained filter error is the thing being
     * fixed -- but never the SQL.
     *
     * @param string|array $whereItems The filter, as string or array.
     * @param string       $class      Class to vet the keys against.
     *
     * @return array The normalized filter.
     */
    public static function handleWhereItems($whereItems, $class = null)
    {
        // Anything already an array was built in PHP. It is left alone, and
        // deliberately not vetted: sendResponse() exits, so refusing a key
        // here would let a typo in a service kill the daemon rather than
        // return a bad result (cf. 2d199fa4b). Only the string form -- which
        // nothing but the router produces -- is checked below.
        if (!is_string($whereItems)) {
            return is_array($whereItems) ? $whereItems : [];
        }
        parse_str(urldecode($whereItems), $whereItems);
        foreach ($whereItems as $key => $val) {
            if (!empty($val) && false !== strpos($val, ',')) {
                $whereItems[$key] = explode(',', $val);
            }
        }
        if (!$class || count($whereItems) < 1) {
            return $whereItems;
        }
        $classVars = self::getClass($class, '', true);
        $valid = array_keys((array)$classVars['databaseFields']);
        $unknown = array_diff(array_keys($whereItems), $valid);
        if (count($unknown) > 0) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(
                    [
                        'error' => sprintf(
                            'Unknown filter field(s) for %s: %s',
                            strtolower($class),
                            implode(', ', $unknown)
                        ),
                        'valid' => $valid
                    ]
                )
            );
        }
        return $whereItems;
    }
    /**
     * Builds a parameterized WHERE clause for the given filter.
     *
     * names() and ids() each carried their own copy of this, both of which
     * interpolated the value straight into the SQL string inside single
     * quotes. That was survivable only because the filtered routes could
     * never actually run -- see handleWhereItems(). Making them work puts
     * request-supplied values on that path, so the values are bound rather
     * than pasted.
     *
     * @param array $classVars  The queried class's vars.
     * @param array $whereItems The normalized filter.
     * @param array $params     Filled with the bound parameters.
     *
     * @return string The WHERE clause, or '' when there is no filter.
     */
    private static function _buildWhere($classVars, $whereItems, &$params)
    {
        $params = [];
        if (count($whereItems) < 1) {
            return '';
        }

        // Two filters cannot be compiled into working SQL, and both must
        // match nothing rather than be dropped -- dropping one and letting
        // the rest of the WHERE run returns rows the caller never asked for.
        //
        //   - a key the class does not declare, which resolves to an empty
        //     column identifier the database rejects outright
        //   - a filter passed as an empty array, meaning "IN ()", which is a
        //     syntax error
        //
        // Request keys are already refused by handleWhereItems(), so an
        // unknown one reaching here came from PHP naming a field that does
        // not exist. Logged rather than raised: sendResponse() exits, and in
        // a service that turns a typo into a restart loop (cf. 2d199fa4b).
        // This matches _buildSql() on working-1.6.
        $unknown = array_diff(
            array_keys($whereItems),
            array_keys((array)$classVars['databaseFields'])
        );
        if (count($unknown) > 0) {
            self::error(
                sprintf(
                    'Route::_buildWhere: unknown filter field(s) for `%s`: %s',
                    $classVars['databaseTable'],
                    implode(', ', $unknown)
                )
            );
        }
        $emptyInSet = false;
        foreach ($whereItems as $field) {
            if (is_array($field) && count($field) < 1) {
                $emptyInSet = true;
                break;
            }
        }
        if (count($unknown) > 0 || $emptyInSet) {
            return ' WHERE 1=0';
        }

        $where = '';
        $idx = 0;
        foreach ($whereItems as $key => $field) {
            $where .= ('' === $where ? ' WHERE `' : ' AND `')
                . $classVars['databaseFields'][$key]
                . '`';
            if (is_array($field)) {
                $names = [];
                foreach (array_values($field) as $i => $val) {
                    $pname = 'where_' . $idx . '_' . $i;
                    $names[] = ':' . $pname;
                    $params[$pname] = $val;
                }
                $where .= ' IN (' . implode(',', $names) . ')';
            } else {
                $pname = 'where_' . $idx;
                $params[$pname] = $field;
                // A '%' still means a pattern, matching how the managers
                // treat one; anything else is compared literally.
                $where .= (false !== strpos((string)$field, '%') ? ' LIKE :' : ' = :')
                    . $pname;
            }
            ++$idx;
        }
        return $where;
    }
    /**
     * Returns only the ids and names of the class passed in.
     *
     * @param string $class      The class to get list of.
     * @param string $whereItems If we want to filter items.
     *
     * @return void
     */
    public static function names($class, $whereItems = [])
    {
        $data = [];
        $classname = strtolower($class);
        $classVars = self::getClass(
            $class,
            '',
            true
        );

        $whereItems = self::handleWhereItems($whereItems, $class);

        $sql = 'SELECT `'
            . $classVars['databaseFields']['id']
            . '`,`'
            . $classVars['databaseFields']['name']
            . '` FROM `'
            . $classVars['databaseTable']
            . '`';

        $sql .= self::_buildWhere($classVars, $whereItems, $params);
        $sql .= ' ORDER BY `'
            . (
                $classVars['databaseFields']['name'] ?:
                $classVars['databaseFields']['id']
            )
            . '` ASC';
        $vals = self::$DB->query($sql, [], $params)->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ($vals as &$val) {
            $data[] = [
                'id' => $val[$classVars['databaseFields']['id']],
                'name' => $val[$classVars['databaseFields']['name']]
            ];
            unset($val);
        }

        self::$data = $data;
    }
    /**
     * Returns only the ids of the class.
     *
     * @param string $class      The class to get list of.
     * @param array  $whereItems The items to filter.
     * @param string $getField   The field to get.
     *
     * @return void
     */
    public static function ids($class, $whereItems = [], $getField = 'id')
    {
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

        $whereItems = self::handleWhereItems($whereItems, $class);

        // getField is the other half of the URL ("/ids/id=1/name"), so an
        // unrecognised value lands the same empty column identifier as an
        // unknown filter key -- here in the SELECT rather than the WHERE.
        //
        // Answered rather than raised, and only when actually serving a
        // request: sendResponse() exits, and ids() is called from the
        // services and from the association helper in FOGController with a
        // getField held in a variable. Exiting there would turn a bad field
        // into a dead daemon, so off-request this only logs and leaves the
        // pre-existing behaviour (a rejected query) alone.
        if (!isset($classVars['databaseFields'][$getField])) {
            $msg = sprintf(
                'Route::ids: unknown field for %s: %s',
                $classname,
                $getField
            );
            if ('cli' === PHP_SAPI) {
                self::error($msg);
            } else {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    json_encode(
                        [
                            'error' => sprintf(
                                'Unknown field for %s: %s',
                                $classname,
                                $getField
                            ),
                            'valid' => array_keys(
                                (array)$classVars['databaseFields']
                            )
                        ]
                    )
                );
            }
        }

        $sql = 'SELECT `'
            . $classVars['databaseFields'][$getField]
            . '` FROM `'
            . $classVars['databaseTable']
            . '`';

        $sql .= self::_buildWhere($classVars, $whereItems, $params);
        $sql .= ' ORDER BY `'
            . (
                (isset($classVars['databaseFields']['name']) && $classVars['databaseFields']['name']) ?
                $classVars['databaseFields']['name'] :
                $classVars['databaseFields']['id']
            )
            . '` ASC';
        $vals = self::$DB->query($sql, [], $params)->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ($vals as &$val) {
            $data[] = $val[$classVars['databaseFields'][$getField]];
            unset($val);
        }
        self::$data = $data;
    }
    /**
     * Delete items in mass.
     *
     * @param string $class      The class we're to remove items.
     * @param array  $whereItems The items we're removing.
     *
     * @return void
     */
    public static function deletemass($class, $whereItems = [])
    {
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

        $sql = 'DELETE FROM `'
            . $classVars['databaseTable']
            . '`';

        if (count($whereItems) > 0) {
            $where = '';
            foreach ($whereItems as $key => &$field) {
                if (!$where) {
                    $where = ' WHERE `'
                        . $classVars['databaseFields'][$key]
                        . '`';
                } else {
                    $where .= ' AND `'
                        . $classVars['databaseFields'][$key]
                        . '`';
                }
                if (is_array($field)) {
                    $where .= " IN ('"
                        . implode("','", $field)
                        . "')";
                } else {
                    $where .= " = '"
                        . $field
                        . "'";
                }
            }
            $sql .= $where;
        }

        return self::$DB->query($sql);
    }
}
