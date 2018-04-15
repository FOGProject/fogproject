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
    public static $matches = [];
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
    public static $validClasses = [
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
        'user',
        'usertracking',
    ];
    /**
     * Valid Tasking classes.
     *
     * @var array
     */
    public static $validTaskingClasses = [
        'group',
        'host',
        'multicastsession',
        'scheduledtask',
        'snapinjob',
        'snapintask',
        'task'
    ];
    /**
     * Valid active tasking classes.
     *
     * @var array
     */
    public static $validActiveTasks = [
        'multicastsession',
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
                )
            );
            exit;
        }
        $unauthqueries = [
            '/fog/system',
            '/fog/bandwidth',
            '/fog/storagegroupid',
            '/fog/storagenodeid'
        ];
        $requribase = dirname(self::$requesturi);
        if (!self::$FOGUser->isValid()
            && !in_array($requribase, $unauthqueries)
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
         * Ensure api has unlimited time.
         */
        ignore_user_abort(true);
        set_time_limit(0);
        /**
         * Define the event so plugins/hooks can modify what/when/where.
         */
        self::$HookManager
            ->processEvent(
                'API_VALID_CLASSES',
                ['validClasses' => &self::$validClasses]
            );
        self::$HookManager
            ->processEvent(
                'API_TASKING_CLASSES',
                ['validTaskingClasses' => &self::$validTaskingClasses]
            );
        self::$HookManager
            ->processEvent(
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
            "${expandeda}/[current|active]",
            [__CLASS__, 'active'],
            'active'
        )->get(
            "${expanded}/names",
            [__CLASS__, 'names'],
            'names'
        )->get(
            "${expanded}/ids",
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
            "${expanded}/[list|all]?",
            [__CLASS__, 'listem'],
            'list'
        )->get(
            "${expanded}/[i:id]",
            [__CLASS__, 'indiv'],
            'indiv'
        )->get(
            '/pendingmacs',
            [__CLASS__, 'pendingmacs'],
            'pendingmacs'
        )->put(
            "${expanded}/[i:id]/[update|edit]?",
            [__CLASS__, 'edit'],
            'update'
        )->post(
            "${expandedt}/[i:id]/[task]",
            [__CLASS__, 'task'],
            'task'
        )->post(
            "${expanded}/[create|new]?",
            [__CLASS__, 'create'],
            'create'
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
        $passtoken = trim($passtoken);
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
            $usertoken = trim($usertoken);
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
                HTTPResponseCodes::HTTP_UNAUTHORIZED,
                $usertoken
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
            'success'
        );
    }
    /**
     * Presents the equivalent of a page's list all.
     *
     * @param string $class      The class to work with.
     * @param mixed  $whereItems Any special things to search for.
     *
     * @return void
     */
    public static function listem(
        $class,
        $whereItems = false
    ) {
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        self::$data = $columns = [];
        $classname = strtolower($class);
        $classman = self::getClass("{$classname}manager");
        $table = $classman->getTable();
        $sqlstr = $classman->getQueryStr();
        $fltrstr = $classman->getFilterStr();
        $ttlstr = $classman->getTotalStr();
        $tmpcolumns = $classman->getColumns();

        $where = '';
        if (count($whereItems ?: []) > 0) {
            foreach ($whereItems as $key => $item) {
                if (!$where) {
                    $where = "`$key`";
                } else {
                    $where .= ' AND ' . $key;
                }
                if (is_array($item)) {
                    $where .= " IN ('"
                        . implode("','", $item)
                        . "')";
                } else if (is_string($item)) {
                    $where .= " = '$item'";
                }
            }
        }

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
                    'ADPass',
                    'ADPassLegacy',
                    'ADOU',
                    'ADDomain',
                    'useAD'
                ],
                $tmpcolumns
            );
            break;
        case 'group':
            break;
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
                    'formatter' => function ($d, $row) use ($classname) {
                        return '<a href="../management/index.php?node='
                            . $classname
                            . '&sub=edit&id='
                            . self::getClass($classname)
                            ->set('name', $d)
                            ->load('name')
                            ->get('id')
                            . '">'
                            . $d
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
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        if (self::validDate($d)) {
                            return self::niceDate($d)->format('Y-m-d H:i:s');
                        }
                        return _('No Data');
                    }
                ];
                break;
            case 'pingstatus':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        $socketstr = socket_strerror($d);
                        $labelType = 'danger';
                        if ($d == 0) {
                            $labelType = 'success';
                        } else if ($d == 6) {
                            $labelType = 'warning';
                        }
                        return '<span class="label label-'
                            . $labelType
                            . '">'
                            . _($socketstr)
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
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=group&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('Group', $d)->get('name')
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
                            return;
                        }
                        return '<a href="../management/index.php?node=host&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('Host', $d)->get('name')
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
                            return;
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
                                . $image->get('id')
                                . '">'
                                . $imageName
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
                    'dt' => 'hostLink',
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=snapin&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('Snapin', $d)->get('name')
                            . '</a>';
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
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=storagegroup&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('StorageGroup', $d)->get('name')
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
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=storagenode&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('StorageNode', $d)->get('name')
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
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=user&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('User', $d)->get('name')
                            . '</a>';
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
            break;
        case 'group':
            $columns[] = [
                'db' => 'gmMembers',
                'dt' => 'members',
                'removeFromQuery' => true
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
                    return self::getter(
                        'storagenode',
                        $StorageGroup->getMasterStorageNode()
                    );
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
                'dt' => 'online',
                'formatter' => function ($d, $row) {
                    return self::getClass('StorageNode', $d)->get('online');
                }
            ];
            $columns[] = [
                'db' => 'ngmID',
                'dt' => 'clientload',
                'formatter' => function ($d, $row) {
                    return self::getClass('StorageNode', $d)->getClientLoad();
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
            $pass_vars,
            $table,
            $tableID,
            $columns,
            $sqlstr,
            $fltrstr,
            $ttlstr,
            $where
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
        header('Content-type: application/json');
        if (empty(trim($limit))) {
            $limit = 0;
        }
        $item = trim($item);
        $data = [];
        $data['_query'] = $item;
        $data['_lang']['AllResults'] = _('See all results');
        foreach (self::$searchPages as &$search) {
            if ($search == 'task') {
                continue;
            }
            $data['_lang'][$search] = _($search);
            $data['_results'][$search] = self::allsearch(
                $search,
                $item,
                $limit,
                true
            );
            $items = self::allsearch(
                $search,
                $item,
                $limit
            );
            $data[$search] = [];
            foreach ((array)$items as &$obj) {
                $data[$search][] = [
                    'id' => $obj->get('id'),
                    'name' => $obj->get('name')
                ];
                unset($obj);
            }
            unset($items);
            unset($search);
        }
        self::$HookManager
            ->processEvent(
                'API_UNISEARCH_RESULTS',
                ['data' => &$data]
            );
        echo json_encode($data);
        unset($data);
        exit;
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
        self::$data = [];
        self::$data['_lang'] = $classname;
        self::$data['_count'] = 0;
        $items = self::allsearch(
            $classname,
            $item
        );
        foreach ((array)$items as &$obj) {
            if (false != stripos($obj->get('name'), '_api')) {
                continue;
            }
            self::$data[$classname.'s'][] = self::getter(
                $classname,
                $obj
            );
            self::$data['_count']++;
            unset($obj);
        }
        self::$HookManager
            ->processEvent(
                'API_MASSDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'classman' => &$classman
                ]
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
        self::$data = [];
        self::$data = self::getter(
            $classname,
            $class
        );
        self::$HookManager
            ->processEvent(
                'API_INDIVDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'class' => &$class
                ]
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
            Route::listem(
                'task',
                ['taskHostID' => $class->get('hosts')]
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
        $find = [];
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
        $states = self::getQueuedStates();
        $states[] = self::getProgressState();
        switch ($classname) {
        case 'scheduledtask':
            $find = ['stActive' => 1];
            break;
        case 'multicastsession':
            $find = ['msState' => $states];
            break;
        case 'snapinjob':
            $find = ['sjStateID' => $states];
            break;
        case 'snapintask':
            $find = ['stState' => $states];
            break;
        case 'task':
            $find = ['taskStateID' => $states];
            break;
        }
        self::listem($class, $find);
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
    public static function getter($classname, $class)
    {
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
                    'macs' => $class->getMyMacs()
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
                    'logfiles' => $class->get('logfiles'),
                    'snapinfiles' => $class->get('snapinfiles'),
                    'images' => $class->get('images'),
                    'storagegroup' => $class->get('storagegroup')->get()
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
                        $class->get('images')->isValid() ?
                        $class->get('images')->get() :
                        $class->get('image')
                    )
                ]
            );
            unset($data['images']);
            break;
        case 'snapintask':
            $data = FOGCore::fastmerge(
                $class->get(),
                [
                    'snapin' => $class->get('snapin')->get(),
                    'snapinjob' => self::getter(
                        'snapinjob',
                        $class->get('snapinjob')
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
                    )
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
                ['isSnapinTasking' => $class->isSnapinTasking()]
            );
            break;
        default:
            $data = $class->get();
            break;
        }
        self::$HookManager
            ->processEvent(
                'API_GETTER',
                [
                    'data' => &$data,
                    'classname' => &$classname,
                    'class' => &$class
                ]
            );
        return $data;
    }
    /**
     * Returns the current bandwidth.
     *
     * @param string $dev The device to get bandwidth from.
     *
     * @return mixed
     */
    public function bandwidth($dev)
    {
        if (!$dev) {
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
     *
     * @return void
     */
    public function ids($class, $whereItems = [])
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
            . '` FROM `'
            . $classVars['databaseTable']
            . '`';

        $sql = self::_buildSql($sql, $whereItems, $classVars);

        $vals = self::$DB->query($sql)->fetch('', 'fetch_all')->get();
        foreach ($vals as &$val) {
            $data[] = [
                'id' => $val[$classVars['databaseFields']['id']]
            ];
            unset($val);
        }
        self::$data = $data;
    }
    /**
     * Builds the sql query with the where.
     *
     * @param string $sql        The sql string we need to adjust.
     * @param mixed  $whereItems The where items to build up.
     * @param array  $classVars  The current class variables.
     *
     * @return string
     */
    private static function _buildSql($sql, $whereItems, $classVars)
    {
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

        return $sql;
    }
    /**
     * Returns only the ids and names of the class.
     *
     * @param string $class      The class to get list of.
     * @param string $whereItems If we want to filter items.
     *
     * @return mixed
     */
    public function names($class, $whereItems = [])
    {
        header('Content-type: application/json');
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

        $sql = self::_buildSql($sql, $whereItems, $classVars);
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
     * Allows joining items.
     *
     * @param string $class The class to join items to.
     *
     * @return void
     */
    public function joining($class)
    {
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
                [$classVars['databaseFields']['id'] => $vars->ids]
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
                    if (count($vars->macs)) {
                        $c->addAddMAC($vars->macs);
                    }
                    if (count($vars->snapins)) {
                        $c->addSnapin($vars->snapins);
                    }
                    if (count($vars->printers)) {
                        $c->addPrinter($vars->printers);
                    }
                    if (count($vars->modules)) {
                        $c->addModule($vars->modules);
                    }
                    if (count($vars->groups)) {
                        $c->addGroup($vars->groups);
                    }
                    break;
                case 'group':
                    if (count($vars->hosts)) {
                        $c->addHost($vars->hosts);
                    }
                    if (count($vars->snapins)) {
                        $c->addSnapin($vars->snapins);
                    }
                    if (count($vars->printers)) {
                        $c->addPrinter($vars->printers);
                    }
                    if (count($vars->modules)) {
                        $c->addModule($vars->modules);
                    }
                    if (count($vars->hosts)) {
                        $c->addHost($vars->hosts);
                    }
                    if ($vars->imageID) {
                        $c->addImage($vars->imageID);
                    }
                    break;
                case 'image':
                case 'snapin':
                    if (count($vars->hosts)) {
                        $c->addHost($vars->hosts);
                    }
                    if (count($vars->storagegroups)) {
                        $c->addGroup($vars->storagegroups);
                    }
                    break;
                case 'printer':
                    if (count($vars->hosts)) {
                        $c->addHost($vars->hosts);
                    }
                    break;
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
                $id = self::getSubObjectIDs(
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
                [$classVars['databaseFields']['id'] => $ids]
            );
            $classes = json_decode(
                Route::getData()
            );
            foreach ($classes->data as &$c) {
                $c = self::getClass($classname, $c->id);
                if (count($vars->hosts)) {
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
            ['hmPending' => [1]]
        );
    }
    /**
     * Presents the kernel listing from fogproject.org
     *
     * @return void
     */
    public static function availablekernels()
    {
        $jsonData = self::$FOGURLRequests->process(
            'https://fogproject.org/kernels/kernelupdate_datatables_fog2.php'
        );
        self::$data = json_decode(array_shift($jsonData));
    }
}
