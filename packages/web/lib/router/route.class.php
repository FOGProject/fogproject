<?php
/**
 * Creates our routes for api configuration.
 *
 * PHP Version 5
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
         * If API is not enabled redirect to home page.
         */
        if (!self::$_enabled) {
            header(
                sprintf(
                    'Location: %s://%s/fog/management/index.php',
                    self::$httpproto,
                    self::$httphost
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
        self::$router = new AltoRouter(
            array(),
            '/fog'
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
                "${expandeda}/[current|active]",
                array(__CLASS__, 'active'),
                'active'
            )
            ->get(
                "${expanded}/search/[*:item]",
                array(__CLASS__, 'search'),
                'search'
            )
            ->get(
                "${expanded}/[list|all]?",
                array(__CLASS__, 'listem'),
                'list'
            )
            ->get(
                "${expanded}/[details]/?[*:item]?",
                array(__CLASS__, 'listdetails'),
                'listdetails'
            )
            ->get(
                "${expanded}/[i:id]/?[*:item]?",
                array(__CLASS__, 'indiv'),
                'indiv'
            )
            ->get(
                "${expanded}/names/[*:whereItems]?",
                array(__CLASS__, 'names'),
                'names'
            )
            ->get(
                "${expanded}/ids/[*:whereItems]?/[*:getField]?",
                array(__CLASS__, 'ids'),
                'ids'
            )
            ->put(
                "${expanded}/[i:id]/[update|edit]?",
                array(__CLASS__, 'edit'),
                'update'
            )
            ->post(
                "${expandedt}/[i:id]/[task]",
                array(__CLASS__, 'task'),
                'task'
            )
            ->post(
                "${expanded}/[create|new]?",
                array(__CLASS__, 'create'),
                'create'
            )
            ->delete(
                "${expandedt}/[i:id]?/[cancel]",
                array(__CLASS__, 'cancel'),
                'cancel'
            )
            ->delete(
                "${expanded}/[i:id]/[delete|remove]?",
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
            call_user_func_array(
                self::$matches['target'],
                self::$matches['params']
            );
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
        $exists = self::getClass($classname)
            ->getManager()
            ->exists($vars->name);
        if (strtolower($class->get('name')) != $vars->name
            && $exists
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
                    ->removeModule($modulesToAdd)
                    ->addModule($modulesToRem);
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
                    ->addPrinter($vars->modules);
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
                $task->taskName,
                $task->shutdown,
                $task->debug,
                (
                    $task->deploySnapins === true ?
                    -1 :
                    (
                        (is_numeric($task->deploySnapins)
                        && $task->deploySnapins > 0)
                        || $task->deploySnapins == -1 ?
                        $task->deploySnapins :
                        false
                    )
                ),
                $class instanceof Group,
                $_SERVER['PHP_AUTH_USER'],
                $task->passreset,
                $task->sessionjoin,
                $task->wol
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
        if ($exists) {
            self::setErrorMessage(
                _('Already created'),
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        foreach ($classVars['databaseFields'] as &$key) {
            $key = $class->key($key);
            $val = $vars->$key;
            if ($key == 'id'
                || !$val
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
        foreach ($classVars['databaseFieldsRequired'] as &$key) {
            $key = $class->key($key);
            $val = $class->get($key);
            if (!is_numeric($val) && !$val) {
                self::setErrorMessage(
                    self::$foglang['RequiredDB'],
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
        switch ($classname) {
        case 'group':
            if (!$class->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            foreach (self::getClass('HostManager')
                ->find(array('id' => $class->get('hosts'))) as &$Host
            ) {
                if ($Host->get('task') instanceof Task) {
                    $Host->get('task')->cancel();
                }
                unset($Host);
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
                $find = self::getsearchbody($classname, $class);
                $find['stateID'] = $states;
                $ids = self::getSubObjectIDs(
                    $classname,
                    $find
                );
                $classman->cancel($ids);
            } else {
                if (in_array($class->get('stateID'), $states)) {
                    $class->cancel();
                }
            }
        }
    }
    /**
     * Get's the json body and sets our vars.
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
     * Get's current/active tasks.
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
     * will operate in the same fasion.
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
     * Returns only the ids and names of the class passed in.
     *
     * @param string $class      The class to get list of.
     * @param string $whereItems If we want to filter items.
     *
     * @return void
     */
    public function names($class, $whereItems = [])
    {
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
        $sql .= ' ORDER BY `'
            . (
                $classVars['databaseFields']['name'] ?:
                $classVars['databaseFields']['id']
            )
            . '` ASC';
        $vals = self::$DB->query($sql)->fetch('', 'fetch_all')->get();
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
    public function ids($class, $whereItems = [], $getField = 'id')
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

        $sql = 'SELECT `'
            . $classVars['databaseFields'][$getField]
            . '` FROM `'
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
        $sql .= ' ORDER BY `'
            . (
                $classVars['databaseFields']['name'] ?:
                $classVars['databaseFields']['id']
            )
            . '` ASC';
        $vals = self::$DB->query($sql)->fetch('', 'fetch_all')->get();
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
