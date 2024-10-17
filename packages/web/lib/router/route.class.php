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
    public static $data = [];
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
     * Runs the matches
     *
     * @return void
     */
    public static function runMatches()
    {
        if (self::$matches
            && is_callable(self::$matches['target'])
        ) {
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
        $usertoken = trim($usertoken);
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
        self::$data = [
            'version' => FOG_VERSION,
            'msg' => _('success')
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
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }
            $whereItems = self::handleWhereItems($whereItems);
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
                    case 'completedTime':
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
                                $socketstr = socket_strerror((int)$d);
                                $labelType = 'danger';
                                if ($d == 0) {
                                    $labelType = 'success';
                                } elseif ($d == 6) {
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
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return;
                                }
                                return '<a href="../management/index.php?node=group&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . self::getClass('group', $d)->get('name')
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
                                    . self::getClass('host', $d)->get('name')
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
                                        . $d
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
                            'dt' => 'snapinLink',
                            'formatter' => function ($d, $row) use ($tmpcolumns) {
                                if (!$d) {
                                    return;
                                }
                                return '<a href="../management/index.php?node=snapin&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . self::getClass('Snapin', $d)->get('name')
                                    . ' - ('. $d .')'
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
                                    return;
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
                                    return;
                                }
                                return '<a href="../management/index.php?node=storagegroup&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . self::getClass('storagegroup', $d)->get('name')
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
                                    return;
                                }
                                return '<a href="../management/index.php?node=storagenode&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . self::getClass('storagenode', $d)->get('name')
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
                                    return;
                                }
                                return '<a href="../management/index.php?node=user&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . self::getClass('user', $d)->get('name')
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
                                return;
                            }
                            return '<a href="../management/index.php?node=host&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . $row['hostName']
                                . '</a>';
                        }
                    ];
                    break;
                case 'scheduledtask':
                    $columns[] = [
                        'db' => 'stGroupHostID',
                        'dt' => 'hostLink',
                        'formatter' => function ($d, $row) {
                            if ($row['stIsGroup']) {
                                $groupName = self::getClass('Group', $d)->get('name');
                                return '<a href="'
                                    . '../management/index.php?node=group&sub=edit&id='
                                    . $d
                                    . '">'
                                    . _('Group')
                                    . ': '
                                    . $groupName
                                    . '</a>';
                            } else {
                                $hostName = self::getClass('Host', $d)->get('name');
                                return '<a href="'
                                    . '../management/index.php?node=host&sub=edit&id='
                                    . $d
                                    . '">'
                                    . _('Host')
                                    . ': '
                                    . $hostName
                                    . '</a>';
                            }
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
                                    $columns[] = [
                                        'dt' => 'starttime',
                                        'formatter' => function (&$d, &$row) {
                                            return self::niceDate($row['stDateTime']);
                                        }
                                    ];
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
                                . $tmphost->get('name')
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
                                return;
                            }
                            return '<a href="../management/index.php?node=snapin&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . self::getClass('Snapin', $d)->get('name')
                                . ' - ('. $d .')'
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
                        'db' => 'utHostID',
                        'dt' => 'hostname',
                        'formatter' => function ($d, $row) {
                            return self::getClass('Host', $d)->get('name');
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
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
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
                switch ($search) {
                    case 'host':
                        $j = "LEFT OUTER JOIN `hostMAC`
                        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`";
                        $w = " OR `hostMAC`.`hmMAC` LIKE :item";
                        $g = "GROUP BY `hosts`.`hostName`";
                        break;
                    default:
                        $j = '';
                        $w = '';
                        $g = '';
                }
                $sql = "SELECT `{$classVars['databaseFields']['id']}`,"
                    . "`{$classVars['databaseFields']['name']}`
                    FROM `{$classVars['databaseTable']}`
                {$j}
                WHERE `{$classVars['databaseFields']['id']}` LIKE :item
                OR `{$classVars['databaseFields']['name']}` LIKE :item
                ${w}
                ${g}";
                if ($limit > 0) {
                    $sql .= " LIMIT $limit";
                }
                $vals = self::$DB->query(
                    $sql,
                    [],
                    ['item' => '%'.$item.'%']
                )->fetch(
                    PDO::FETCH_ASSOC,
                    'fetch_all'
                )->get();
                foreach ($vals as &$val) {
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
                    unset($val);
                }
                if (array_search($search, $data)) {
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
                        Route::ids('snapin', false);
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
                        Route::ids('printer', false);
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
                        Route::ids('module', false);
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
        Route::ids('tasktype', false);
        $tids = json_decode(
            Route::getData(),
            true
        );
        $task = json_decode(
            file_get_contents('php://input')
        );
        Route::indiv('tasktype', $task->taskTypeID);
        $TaskType = json_decode(Route::getData());
        try {
            $deploySnapins = false;
            if (isset($task->deploySnapins)) {
                $deploySnapins = $task->deploySnapins;
                if (
                    !is_numeric($deploySnapins) || (
                        $deploySnapins < 0 && $deploySnapins != -1
                    )
                ) {
                    $deploySnapins = false;
                }
            }
            $class->createImagePackage(
                $TaskType,
                ($task->taskName ?? ''),
                ($task->shutdown ?? false),
                ($task->debug ?? false),
                (($deploySnapins) === true ? -1 : $deploySnapins),
                $class instanceof Group,
                $_SERVER['PHP_AUTH_USER'] ?? 'API',
                $task->passreset ?? '',
                $task->sessionjoin ?? '',
                $task->wol ?? 1
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
                    if (isset($vars->macs)) {
                        $class
                            ->removeMAC($vars->macs)
                            ->addPriMAC(array_shift($vars->macs))
                            ->addMAC($vars->macs);
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
     * Get's the json body and sets our vars.
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

            return $find;
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
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
            self::count($classname, $whereItems);
            $count = json_decode(Route::getData());
            if (!$count->total) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            $sql = 'DELETE FROM `'
                . $classVars['databaseTable']
                . '` WHERE `'
                . $classVars['databaseFields']['id']
                . '` = :id';

            return self::$DB->query($sql, [], $whereItems);
            self::$data = '';
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
                                $class->get('images')->isValid() ?
                                $class->get('images')->get() :
                                $class->get('image')
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
        }
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

            $sql = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );

            $vals = self::$DB->query($sql)->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
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
            switch ($classname) {
                case 'host':
                    Route::ids(
                        'snapinjob',
                        ['hostID' => $itemIDs]
                    );
                    $snapinjobIDs = ['jobID' => json_decode(Route::getData(), true)];
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
                    Route::ids(
                        'snapintask',
                        $findWhere,
                        'jobID'
                    );
                    $snapinjobIDs = json_decode(Route::getData(), true);
                    $removeItems = [
                        'snapinassociation' => $findWhere,
                        'snapingroupassociation' => $findWhere
                    ];
                    $queuedStates = self::getQueuedStates();
                    $queuedStates[] = self::getProgressState();
                    Route::ids(
                        'snapinjob',
                        [
                            'id' => $snapinjobIDs,
                            'stateID' => $queuedStates
                        ]
                    );
                    $snapinjobIDs = json_decode(Route::getData(), true);
                    foreach ((array)$snapinjobIDs as &$sjID) {
                        Route::count(
                            'snapintask',
                            ['jobID' => $sjID]
                        );
                        $jobCount = json_decode(Route::getData());
                        if ($jobCount->total) {
                            continue;
                        }
                        $sjIDs[] = $sjID;
                        unset($sjID);
                    }
                    if (count($sjIDs ?: [])) {
                        self::getClass('SnapinJobManager')->cancel($sjIDs);
                    }
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

            $sql = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );

            return self::$DB->query($sql);
        } catch (Exception $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_NOT_ACCEPTABLE,
                $e->getMessage()
            );
        }
    }
    /**
     * Builds the sql query with the where.
     *
     * @param string $sql        The sql string we need to adjust.
     * @param array  $classVars  The current class variables.
     * @param mixed  $whereItems The where items to build up.
     * @param bool   $retWhere   Only return where element.
     * @param string $orderby    How to order the returned values.
     *
     * @return string
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
            if (count($whereItems ?: []) > 0) {
                $where = '';
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
                        $where .= " IN ('"
                            . implode("','", $field)
                            . "')";
                    } else {
                        $field = str_replace(
                            ['+', '*'],
                            '%',
                            $field
                        );
                        $oper = false !== strpos($field, '%') ? 'LIKE' : '=';
                        $where .= " $oper '"
                            . $field
                            . "'";
                    }
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

            return $sql;
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

            $sql = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );
            $vals = self::$DB->query($sql)->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
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
                                if (isset($vars->hosts)) {
                                    $c->addHost($vars->hosts);
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
                        Route::ids(
                            $classname,
                            ['name' => $name]
                        );
                        $id = json_decode(
                            Route::getData(),
                            true
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
    public static function kernelOrInitJson($data, $type)
    {
        if ($type != 'kernel' && $type != 'initrd') {
            return [];
        }
        foreach ($data as &$release) {
            if ($type == 'kernel') {
                $patt = '/Linux kernel (.*)?/';
            }
            if ($type == 'initrd') {
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
                            $k_hint = ' (FOG '. explode(' ', $release->name) [1].')';
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
