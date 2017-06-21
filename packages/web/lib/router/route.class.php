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
        /**
         * Test our token.
         */
        self::_testToken();
        /**
         * Test our authentication.
         */
        self::_testAuth();
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
            rtrim(
                self::getSetting('FOG_WEB_ROOT'),
                '/'
            )
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
                array(self, 'status'),
                'status'
            )
            ->get(
                "${expandeda}/[current|active]",
                array(self, 'active'),
                'active'
            )
            ->get(
                "${expanded}/search/[*:item]",
                array(self, 'search'),
                'search'
            )
            ->get(
                "${expanded}/[list|all]?",
                array(self, 'listem'),
                'list'
            )
            ->get(
                "${expanded}/[i:id]",
                array(self, 'indiv'),
                'indiv'
            )
            ->put(
                "${expanded}/[i:id]/[update|edit]?",
                array(self, 'edit'),
                'update'
            )
            ->post(
                "${expandedt}/[i:id]/[task]",
                array(self, 'task'),
                'task'
            )
            ->post(
                "${expanded}/[create|new]?",
                array(self, 'create'),
                'create'
            )
            ->delete(
                "${expandedt}/[i:id]?/[cancel]",
                array(self, 'cancel'),
                'cancel'
            )
            ->delete(
                "${expanded}/[i:id]/[delete|remove]?",
                array(self, 'delete'),
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
        $auth = self::$FOGUser->passwordValidate(
            $_SERVER['PHP_AUTH_USER'],
            $_SERVER['PHP_AUTH_PW']
        );
        if (!$auth) {
            $usertoken = base64_decode(
                filter_input(INPUT_SERVER, 'HTTP_FOG_USER_TOKEN')
            );
            $pwtoken = self::getClass('User')
                ->set('token', $usertoken)
                ->load('token');
            if ($pwtoken->isValid() && $pwtoken->get('api')) {
                return;
            }
            $pwhash = self::getClass('User')
                ->set('password', $_SERVER['PHP_AUTH_PW'], true)
                ->load('password');
            if ($pwhash->isValid()
                && $pwhash->get('api')
                && $pwhash->get('name') == $_SERVER['PHP_AUTH_USER']
            ) {
                return;
            }
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
            HTTPResponseCodes::HTTP_SUCCESS
        );
    }
    /**
     * Presents the equivalent of a page's list all.
     *
     * @param string $class The class to work with.
     *
     * @return void
     */
    public static function listem($class)
    {
        $classname = strtolower($class);
        $classman = self::getClass($class)->getManager();
        self::$data = array();
        self::$data['count'] = 0;
        self::$data[$classname.'s'] = array();
        $find = self::getsearchbody($classname);
        foreach ($classman->find($find) as &$class) {
            self::$data[$classname.'s'][] = self::getter($classname, $class);
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
        $_REQUEST['crit'] = $item;
        $classman = self::getClass($class)->getManager();
        self::$data = array();
        self::$data['count'] = 0;
        self::$data[$classname.'s'] = array();
        foreach ($classman->search('', true) as &$class) {
            self::$data[$classname.'s'][] = self::getter($classname, $class);
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
    public static function indiv($class, $id)
    {
        $classname = strtolower($class);
        $class = new $class($id);
        if (!$class->isValid()) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
        }
        self::$data = array();
        self::$data = self::getter($classname, $class);
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
            if (count($vars->macs)) {
                $class
                    ->removeAddMAC($vars->macs)
                    ->addPriMAC(array_shift($vars->macs))
                    ->addAddMAC($vars->macs);
            }
            if (count($vars->snapins)) {
                $class
                    ->removeSnapin($class->get('snapins'))
                    ->addSnapin($vars->snapins);
            }
            if (count($vars->printers)) {
                $class
                    ->removePrinter($class->get('printers'))
                    ->addPrinter($vars->printers);
            }
            if (count($vars->modules)) {
                $class
                    ->removeModule($class->get('modules'))
                    ->addModule($vars->modules);
            }
            if (count($vars->groups)) {
                $class
                    ->removeGroup($class->get('groups'))
                    ->addGroup($vars->groups);
            }
            break;
        case 'group':
            if (count($vars->snapins)) {
                $class
                    ->removeSnapin(
                        self::getSubObjectIDs('Snapin')
                    )
                    ->addSnapin($vars->snapins);
            }
            if (count($vars->printers)) {
                $class
                    ->removePrinter(
                        self::getSubObjectIDs('Printer')
                    )
                    ->addPrinter($vars->printers);
            }
            if (count($vars->modules)) {
                $class
                    ->removeModule(
                        self::getSubObjectIDs('Module')
                    )
                    ->addModule($vars->modules);
            }
            if (count($vars->hosts)) {
                $class
                    ->removeHost(
                        $class->get('hosts')
                    )
                    ->addHost($vars->hosts);
            }
            if ($vars->imageID) {
                $class
                    ->addImage($vars->imageID);
            }
            break;
        case 'image':
        case 'snapin':
            if (count($vars->hosts)) {
                $class
                    ->removeHost(
                        $class->get('hosts')
                    )
                    ->addHost($vars->hosts);
            }
            if (count($vars->storagegroups)) {
                $class
                    ->removeGroup(
                        $class->get('storagegroups')
                    )
                    ->addGroup($vars->storagegroups);
            }
            break;
        case 'printer':
            if (count($vars->hosts)) {
                $class
                    ->removeHost(
                        $class->get('hosts')
                    )
                    ->addHost($vars->hosts);
            }
            break;
        case 'user':
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
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
            if (count($vars->snapins)) {
                $class
                    ->addSnapin($vars->snapins);
            }
            if (count($vars->printers)) {
                $class
                    ->addPrinter($vars->printers);
            }
            if (count($vars->modules)) {
                $class
                    ->addModule($vars->modules);
            }
            if (count($vars->groups)) {
                $class
                    ->addGroup($vars->groups);
            }
            break;
        case 'group':
            if (count($vars->snapins)) {
                $class
                    ->addSnapin($vars->snapins);
            }
            if (count($vars->printers)) {
                $class
                    ->addPrinter($vars->printers);
            }
            if (count($vars->modules)) {
                $class
                    ->addModule($vars->modules);
            }
            if (count($vars->hosts)) {
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
            if (count($vars->hosts)) {
                $class
                    ->addHost($vars->hosts);
            }
            if (count($vars->storagegroups)) {
                $class
                    ->addGroup($vars->storagegroups);
            }
            break;
        case 'printer':
            if (count($vars->hosts)) {
                $class
                    ->addHost($vars->hosts);
            }
            break;
        case 'user':
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
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
        foreach ($classman->find($find) as &$class) {
            self::$data[$classname.'s'][] = self::getter($classname, $class);
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
        switch ($classname) {
        case 'user':
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
            break;
        }
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
    public static function getter($classname, $class)
    {
        switch ($classname) {
        case 'user':
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
            break;
        case 'host':
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'ADPass' => (string)FOGCore::aesdecrypt(
                        $class->get('ADPass')
                    ),
                    'productKey' => (string)FOGCore::aesdecrypt(
                        $class->get('productKey')
                    ),
                    'primac' => $class->get('mac')->__toString(),
                    'imagename' => $class->getImageName(),
                    'hostscreen' => self::getter(
                        'hostscreensetting',
                        $class->get('hostscreen')
                    ),
                    'hostalo' => self::getter(
                        'hostautologout',
                        $class->get('hostalo')
                    ),
                    'inventory' => self::getter(
                        'inventory',
                        $class->get('inventory')
                    ),
                    'imagename' => $class->getImageName(),
                    'pingstatus' => $class->getPingCodeStr(),
                    'macs' => $class->getMyMacs()
                )
            );
            break;
        case 'image':
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'os' => self::getter('os', $class->get('os')),
                    'imagepartitiontype' => self::getter(
                        'imagepartitiontype',
                        $class->get('imagepartitiontype')
                    ),
                    'imagetype' => self::getter(
                        'imagetype',
                        $class->get('imagetype')
                    ),
                    'imagetypename' => $class->getImageType()->get('name'),
                    'imageparttypename' => $class->getImagePartitionType()->get(
                        'name'
                    ),
                    'osname' => $class->getOS()->get('name'),
                    'storagegroupname' => $class->getStorageGroup()->get('name'),
                    'hosts' => array_map(
                        'intval',
                        (array)$class->get('hosts')
                    ),
                    'storagegroups' => array_map(
                        'intval',
                        (array)$class->get('storagegroups')
                    )
                )
            );
            break;
        case 'snapin':
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'storagegroupname' => $class->getStorageGroup()->get('name'),
                    'hosts' => array_map(
                        'intval',
                        (array)$class->get('hosts')
                    ),
                    'storagegroups' => array_map(
                        'intval',
                        (array)$class->get('storagegroups')
                    )
                )
            );
            break;
        case 'storagenode':
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'storagegroup' => self::getter(
                        'storagegroup',
                        $class->get('storagegroup')
                    )
                )
            );
            break;
        case 'task':
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'image' => self::getter('image', $class->get('image')),
                    'host' => self::getter('host', $class->get('host')),
                    'type' => self::getter('tasktype', $class->get('type')),
                    'state' => self::getter('taskstate', $class->get('state')),
                    'storagenode' => self::getter(
                        'storagenode',
                        $class->get('storagenode')
                    ),
                    'storagegroup' => self::getter(
                        'storagegroup',
                        $class->get('storagegroup')
                    ),
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
}
