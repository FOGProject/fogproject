<?php
/**
 * Presents many defaults for the pages and is
 * the calling point by all other page items.
 *
 * PHP version 5
 *
 * @category FOGPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents many defaults for the pages and is
 * the calling point by all other page items.
 *
 * @category FOGPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGPage extends FOGBase
{
    /**
     * Name of the page
     *
     * @var string
     */
    public $name = '';
    /**
     * Node of the page
     *
     * @var string
     */
    public $node = '';
    /**
     * ID of the page
     *
     * @var string
     */
    public $id = 'id';
    /**
     * Title for segment
     *
     * @var string
     */
    public $title;
    /**
     * The menu (always display)
     *
     * @var array
     */
    public $menu = [];
    /**
     * The submenu (Object displayed menus)
     *
     * @var array
     */
    public $subMenu = [];
    /**
     * Additional notes for object
     *
     * @var array
     */
    public $notes = [];
    /**
     * Table header data
     *
     * @var array
     */
    public $headerData = [];
    /**
     * Table data
     *
     * @var array
     */
    public $data = [];
    /**
     * Table atts
     *
     * @var array
     */
    public $atts = [];
    /**
     * Attributes such as class, id, etc...
     *
     * @var array
     */
    public $attributes = [];
    /**
     * Pages that contain objects
     *
     * @var array
     */
    public $PagesWithObjects = [
        'user',
        'host',
        'group',
        'image',
        'module',
        'ipxe',
        'storagenode',
        'storagegroup',
        'snapin',
        'plugin',
        'printer',
        'task'
    ];
    /**
     * The items table
     *
     * @var string
     */
    protected $databaseTable = '';
    /**
     * The items table field and common names
     *
     * @var array
     */
    protected $databaseFields = [];
    /**
     * The items required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [];
    /**
     * The items additional fields
     *
     * @var array
     */
    protected $additionalFields = [];
    /**
     * The forms action placeholder
     *
     * @var string
     */
    public $formAction = '';
    /**
     * The forms method/action
     *
     * @var string
     */
    protected $formPostAction = '';
    /**
     * The items caller class
     *
     * @var string
     */
    protected $childClass = '';
    /**
     * The report place holder
     *
     * @var string
     */
    protected $reportString = '';
    /**
     * Is the title enabled
     *
     * @var bool
     */
    protected $titleEnabled = true;
    /**
     * The request
     *
     * @var array
     */
    protected $request = [];
    /**
     * CSV Place holder
     *
     * @var string
     */
    protected static $csvfile = '';
    /**
     * Inventory csv head
     *
     * @var string
     */
    protected static $inventoryCsvHead = '';
    /**
     * Per-request cache of association configs, keyed by child class.
     *
     * @var array
     */
    protected static $associationConfigs = [];
    /**
     * Per-request cache of id/name lookup maps, keyed by "class|namefield".
     *
     * @var array
     */
    protected static $associationMaps = [];
    /**
     * Holder for lambda function
     */
    protected static $returnData;
    /**
     * Collapse box display.
     *
     * @var string
     */
    protected static $FOGCollapseBox;
    /**
     * Expand box display.
     *
     * @var string
     */
    protected static $FOGExpandBox;
    /**
     * Close box display.
     *
     * @var string
     */
    protected static $FOGCloseBox;
    protected $templates;

    /**
     * Initializes the page class
     *
     * @param mixed $name name of the page to initialize
     *
     * @return void
     */
    public function __construct($name = '')
    {
        parent::__construct();
        self::$FOGCollapseBox = self::makeButton(
            '',
            '<i class="fa fa-minus"></i>',
            'btn btn-box-tool',
            'data-widget="collapse"'
        );
        self::$FOGExpandBox = self::makeButton(
            '',
            '<i class="fa fa-plus"></i>',
            'btn btn-box-tool',
            'data-widget="expand"'
        );
        self::$FOGCloseBox = self::makeButton(
            '',
            '<i class="fa fa-times"></i>',
            'btn btn-box-tool',
            'data-widget="remove"'
        );
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            ['PagesWithObjects' => &$this->PagesWithObjects]
        );

        global $node;
        global $type;
        global $sub;
        global $tab;
        global $id;
        if ($node == 'report') {
            $f = filter_input(INPUT_GET, 'f');
        }
        if ($node !== 'service'
            && false !== stripos($sub, 'edit')
            && (!isset($id)
            || !is_numeric($id)
            || $id < 1)
        ) {
            self::redirect(
                "../management/index.php?node=$node"
            );
            exit;
        }
        $subs = [
            'configure',
            'authorize',
            'requestClientInfo'
        ];
        if (!$sub) {
            $sub = 'list';
        }
        if (in_array($sub, $subs)) {
            return $this->{$sub}();
        }
        $this->childClass = ucfirst($this->node);
        if ($this->node == 'ipxe') {
            $this->childClass = 'PXEMenuOptions';
        }
        if (!empty($name)) {
            $this->name = $name;
        }
        $this->title = $this->name;
        if (in_array($this->node, $this->PagesWithObjects)) {
            $classVars = self::getClass(
                $this->childClass,
                '',
                true
            );
            $this->databaseTable
                = $classVars['databaseTable'];
            $this->databaseFields
                = $classVars['databaseFields'];
            $this->databaseFieldsRequired
                = $classVars['databaseFieldsRequired'];
            $this->databaseFieldClassRelationships
                = $classVars['databaseFieldClassRelationships'];
            $this->additionalFields
                = $classVars['additionalFields'];
            unset($classVars);
            $this->obj = new $this->childClass($id);
            if (isset($id)) {
                if ($id === 0 || !is_numeric($id) || !$this->obj->isValid()) {
                    unset($this->obj);
                    self::redirect("../management/index.php?node={$this->node}");
                }
            }
        }
        self::$HookManager->processEvent(
            'SEARCH_PAGES',
            ['searchPages' => &self::$searchPages]
        );
        /**
         * This builds our form action dynamically.
         */
        $data = [];
        $nodestr = $substr = $idstr = $typestr = $tabstr = false;
        $formstr = '../management/index.php?';
        if ($node) {
            $data['node'] = $node;
        }
        if (isset($sub) && $sub) {
            $data['sub'] = $sub;
        }
        if (isset($id) && $id) {
            $data['id'] = $id;
        }
        if (isset($type) && $type) {
            $data['type'] = $type;
        }
        if (isset($f) && $f) {
            $data['f'] = $f;
        }
        if (isset($tab) && $tab) {
            $tabstr = "#". rawurlencode($tab);
        }
        if (count($data ?: []) > 0) {
            $formstr .= http_build_query($data);
        }
        if (isset($tabstr) && $tabstr) {
            $formstr .= $tabstr;
        }
        $this->formAction = $formstr;
    }
    /**
     * Creates the main menu items.
     *
     * @param array $main     Items to set.
     * @param array $hookMain Hook items to set.
     *
     * @return string
     */
    public static function buildMainMenuItems(&$main = '', &$hookMain = '')
    {
        global $node;
        global $sub;
        if (!self::$FOGUser->isValid() || strtolower($node) == 'schema') {
            return '';
        }
        $menu = [
            'home' => [
                self::$foglang['Dashboard'],
                'fa fa-dashboard'
            ],
            'host' => [
                self::$foglang['Hosts'],
                'fa fa-desktop'
            ],
            'group' => [
                self::$foglang['Groups'],
                'fa fa-sitemap'
            ],
            'image' => [
                self::$foglang['Images'],
                'fa fa-hdd-o'
            ],
            'snapin' => [
                self::$foglang['Snapins'],
                'fa fa-cube'
            ],
            'storagegroup' => [
                self::$foglang['Storagegroups'],
                'fa fa-object-group'
            ],
            'storagenode' => [
                self::$foglang['Storagenodes'],
                'fa fa-archive'
            ],
            'printer' => [
                self::$foglang['Printers'],
                'fa fa-print'
            ],
            'module' => [
                _('Modules'),
                'fa fa-cogs'
            ],
            'task' => [
                self::$foglang['Tasks'],
                'fa fa-tasks'
            ],
            'user' => [
                self::$foglang['Users'],
                'fa fa-users'
            ],
            'ipxe' => [
                _('iPXE Menu'),
                'fa fa-bars'
            ],
            'about' => [
                self::$foglang['FOG Configuration'],
                'fa fa-wrench'
            ],
            'report' => [
                self::$foglang['Reports'],
                'fa fa-file-text'
            ],
            'service' => [
                self::$foglang['ClientSettings'],
                'fa fa-cogs'
            ],
            'client' => [
                _('FOG Client'),
                'fa fa-cloud-download'
            ]
        ];
        if (self::getSetting('FOG_PLUGINSYS_ENABLED')) {
            self::arrayInsertAfter(
                'client',
                $menu,
                'plugin',
                [
                    self::$foglang['Plugins'],
                    'fa fa-puzzle-piece'
                ]
            );
        }
        $menu = array_unique(
            array_filter($menu),
            SORT_REGULAR
        );

        $hookMenu = [];

        self::$HookManager->processEvent(
            'MAIN_MENU_DATA',
            [
                'main' => &$menu,
                'hook_main' => &$hookMenu
            ]
        );

        @natcasesort($hookMenu);

        self::$HookManager->processEvent(
            'DELETE_MENU_DATA',
            [
                'main' => &$menu,
                'hook_main' => &$hookMenu
            ]
        );

        if (isset($menu['plugin']) && $menu['plugin']) {
            self::$pluginIsAvailable = true;
        }

        foreach ($hookMenu as $key => &$value) {
            if (array_key_exists($key, $menu)) {
                unset($hookMenu[$key]);
            }
        }

        if (count($menu ?: []) > 0) {
            $links = array_keys($menu);
        }
        if (count($hookMenu ?: []) > 0) {
            $links = self::fastmerge(
                $links,
                array_keys($hookMenu)
            );
        }

        $links = self::fastmerge(
            (array)$links,
            [
                'home',
                'logout',
                'hwinfo',
                'client',
                'schema',
                'ipxe'
            ]
        );

        if ($node
            && !in_array($node, $links)
        ) {
            self::redirect('../management/index.php');
        }

        $main = self::_buildMenuStructure($menu);
        $hookMain = self::_buildMenuStructure($hookMenu);
        return $main;
    }
    /**
     * Builds the menu structure.
     *
     * @param array $menu The links to build upon.
     *
     * @return string
     */
    private static function _buildMenuStructure($menu)
    {
        if (count($menu ?: []) < 1) {
            return '';
        }
        global $node;
        global $sub;
        ob_start();
        $links = $subs = [];
        foreach ($menu as $link => &$title) {
            $links[] = $link;
            if (!$node && 'home' == $link) {
                $node = $link;
            }
            $activelink = ($node == $link);
            $subItems = array_filter(
                self::_buildSubMenuItems($link)
            );
            echo '<li class="'
                . (
                    count($subItems ?: []) > 0 ?
                    'treeview' :
                    ''
                )
                . (
                    $activelink ?
                    (
                        count($subItems ?: []) > 0 ?
                        ' ' :
                        ''
                    ) . 'active' :
                    ''
                )
                . '">';
            echo '<a '
                // Only make the page an AJAX link if it doesn't have children.
                . (
                    count($subItems ?: []) == 0 ?
                    'class="ajax-page-link" ' :
                    ''
                )
                . ' href="'
                . (
                    count($subItems ?: []) > 0 ?
                    '#' :
                    "../management/index.php?node=$link"
                )
                . '">';
            echo '<i class="' . $title[1] . '"></i> ';
            echo '<span>' . $title[0] . '</span>';
            if (count($subItems ?: []) > 0) {
                echo '<span class="pull-right-container">';
                echo '<i class="fa fa-angle-left pull-right"></i>';
                echo '</span>';
            }
            echo '</a>';
            if (count($subItems ?: []) > 0) {
                echo '<ul class="treeview-menu">';
                $subs[$link] = [];
                foreach ($subItems as $subItem => $text) {
                    $subs[$link][] = $subItem;
                    echo '<li class="'
                        . (
                            $activelink && $sub == $subItem ?
                            'active' :
                            ''
                        )
                        . '">';
                    echo '<a class="ajax-page-link" '
                        . 'href="../management/index.php?node='
                        . $link
                        . '&sub='
                        . $subItem
                        . '">';
                    echo '<i class="fa fa-circle-o"></i>';
                    echo $text;
                    echo '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</li>';
            unset($title);
        }
        return ob_get_clean();
    }
    /**
     * Creates the sub menu items.
     *
     * @param string $refNode The node to "append"
     *
     * @return array
     */
    private static function _buildSubMenuItems($refNode = '')
    {
        $node = strtolower($refNode);
        $refNode = ucfirst($refNode);
        $refNode = _($refNode);
        $menu = [];
        $menu = [
            'list' => sprintf(
                self::$foglang['ListAll'],
                _(
                    sprintf(
                        '%ss',
                        $refNode
                    )
                )
            ),
            'add' => sprintf(
                self::$foglang['CreateNew'],
                $refNode
            )
        ];
        if (isset(self::$foglang[$refNode])) {
            $menu['export'] = self::$foglang['Export'] . ' ' . self::$foglang[$refNode];
            $menu['import'] = self::$foglang['Import'] . ' ' . self::$foglang[$refNode];
        }
        switch ($node) {
            case 'home':
            case 'client':
            case 'schema':
            case 'service':
            case 'hwinfo':
                $menu = [];
                break;
            case 'about':
                $menu = [
                    'home' => self::$foglang['Home'],
                    'license' => self::$foglang['License'],
                    'kernel' => self::$foglang['KernelUpdate'],
                    'initrd' => self::$foglang['InitrdUpdate'],
                    'pxemenu' => self::$foglang['PXEBootMenu'],
                    'maclist' => self::$foglang['MACAddrList'],
                    'settings' => self::$foglang['FOGSettings'],
                    'logviewer' => self::$foglang['LogViewer'],
                    'config' => self::$foglang['ConfigSave']
                ];
                break;
            case 'plugin':
                $menu = [
                    'list' => _('List Available Plugins'),
                    'import' => _('Import a new Plugin')
                ];
                break;
            case 'task':
                $menu = [
                    'active' => self::$foglang['ActiveTasks'],
                    'activemulticast' => self::$foglang['ActiveMCTasks'],
                    'activesnapins' => self::$foglang['ActiveSnapins'],
                    'activescheduled' => self::$foglang['ScheduledTasks'],
                    'activescheduleddels' => _('Queued Path Deletions')
                ];
                break;
            case 'image':
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'multicast',
                    _('Multicast Image')
                );
                break;
            case 'host':
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'pending',
                    _('Pending Hosts')
                );
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'pendingMacs',
                    _('Pending MACs')
                );
                break;
            case 'report':
                $reportlink = "file&f=";
                $menu = [];
                foreach (ReportManagement::loadCustomReports() as &$report) {
                    $item = ucwords(strtolower($report));
                    $menu[
                        sprintf(
                            '%s%s',
                            $reportlink,
                            base64_encode($report)
                        )
                    ] = $item;
                    unset($report, $item);
                }
                $menu['upload'] = _('Import Reports');
        }

        $menu = array_filter($menu);

        self::$HookManager->processEvent(
            'SUB_MENULINK_DATA',
            [
                'menu' => &$menu,
                'node' => &$node,
                'refNode' => &$refNode
            ]
        );

        self::$HookManager->processEvent(
            'DELETE_MENULINK_DATA',
            [
                'menu' => &$menu,
                'node' => &$node,
                'refNode' => &$refNode
            ]
        );
        return $menu;
    }

    /**
     * Page default index
     *
     * @return void
     */
    public function index(...$args)
    {
        global $node;
        global $sub;
        if (false === self::$showhtml) {
            return;
        }
        // This is where list/search kind of happens.
        if (in_array($this->node, self::$searchPages)) {
            if (self::$ajax) {
                header('Content-Type: application/json');
                Route::listem($this->childClass);
                $data = Route::getData();
                self::$HookManager->processEvent(
                    'AJAX_DATA_DISPLAY_CHANGE',
                    [
                        'data' => &$data,
                        'childClass' => &$this->childClass,
                        'main' => &$this,
                        'delNeeded' => &$delNeeded
                    ]
                );
                echo $data;
                exit;
            }
            if ($node == 'ipxe') {
                $this->title = _('All Boot Menu Items');
            } else {
                $this->title = _('All ' . $this->childClass . 's');
            }
            $this->indexDivDisplay();
        } else {
            $vals = function ($value, $key) {
                return sprintf(
                    '%s : %s',
                    $key,
                    $value
                );
            };
            if (count($args ?: []) > 0) {
                array_walk($args, $vals);
            }
            printf(
                'Index page of: %s%s',
                get_class($this),
                (
                    count($args ?: []) ?
                    sprintf(', Arguments = %s', implode(', ', $args)) :
                    ''
                )
            );
        }
    }
    /**
     * Set's value to key
     *
     * @param string $key   the key to set
     * @param mixed  $value the value to set
     *
     * @return object
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
    /**
     * Gets the value in the key
     *
     * @param string $key the key to get
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->data[$key];
    }
    /**
     * Return the information
     *
     * @return string
     */
    public function __toString()
    {
        return $this->process();
    }
    /**
     * Print the information
     *
     * @param int    $colsize    Col size
     * @param string $tableId    The table id.
     * @param string $buttons    Any buttons to pass in.
     * @param string $tableClass The class for the table css.
     * @param bool   $serverSide Is the table to be server side or not.
     *
     * @return void
     */
    public function render(
        $colsize = 12,
        $tableId = 'dataTable',
        $buttons = '',
        $tableClass = 'display table table-bordered table-striped',
        $serverSide = true
    ) {
        echo $this->process(
            $colsize,
            $tableId,
            $buttons,
            $tableClass,
            $serverSide
        );
    }
    /**
     * Makes the action url update with the tab.
     *
     * @param string $tab What tab to associate this with.
     * @param int    $id  The id, if required.
     *
     * @return string
     */
    public static function makeTabUpdateURL($tab, $id = -1)
    {
        global $node;
        global $sub;
        return "../management/index.php?node=$node"
            . "&sub=$sub"
            . ($id > 0 ? "&id=$id" : '')
            . "&tab=$tab";
    }
    /**
     * Displays an alert for the user.
     *
     * @param string $title       The title of the alert.
     * @param string $body        The body of the alert.
     * @param string $type        The type of alert.
     * @param bool   $dismissable Allow the alert to be dismissed.
     * @param bool   $isCallout   Is the alert calling out something?
     *
     * @return void
     */
    public static function displayAlert(
        $title,
        $body,
        $type,
        $dismissable = true,
        $isCallout = false
    ) {
        echo '<div class="box-body">';
        echo '<div class="';
        echo(
            $isCallout ?
            'callout callout-' :
            'alert alert-'
        );
        echo $type;
        if ($dismissable) {
            echo ' alert-dismissible';
        }
        echo '">';
        if ($dismissable) {
            echo self::makeButton(
                '',
                'x',
                'close',
                'data-dismiss="alert" aria-hidden="true"'
            );
        }
        echo '<h4>'
            . $title
            . '</h4>';
        echo $body;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Makes a button element for us.
     *
     * @param string $id    The id of the button
     * @param string $text  The text for the button.
     * @param string $class The class to associated to the button.
     * @param string $props Any additional properies to append to the button.
     *
     * @return string
     */
    public static function makeButton($id, $text, $class, $props = '')
    {
        ob_start();
        echo '<button';
        if ($id) {
            echo ' id="'
                . $id
                . '"';
        }
        if ($class) {
            echo ' class="'
                . $class
                . '"';
        }
        if ($props) {
            echo " $props";
        }
        echo '>';
        echo $text;
        echo '</button>';
        return ob_get_clean();
    }
    /**
     * Helps make a split button.
     *
     * @param string $id            The id of the main button
     * @param string $text          The text for dropdown button.
     * @param array  $dropdownArray The dropdown items. This item is in order of:
     *                              [
     *                              [
     *                              'id' => 'someID',
     *                              'text' => 'SomeButtonText',
     *                              'props' => 'action="SomeAction" method="post"'
     *                              ],
     *                              [
     *                              'divider' => true,
     *                              'id' => 'idAfterDivider',
     *                              'text' => 'textAfterDivider'
     *                              ]
     *                              ]
     * @param string $pull          Pull the button group.
     * @param string $class         The class to give.
     * @param string $props         Properties for the base button.
     *
     * @return string
     */
    public static function makeSplitButton(
        $id,
        $text,
        $dropdownArray,
        $pull = 'right',
        $class = 'default',
        $props = ''
    ) {
        ob_start();
        echo '<div class="btn-group pull-'
            . $pull
            . '">';
        echo '<button type="button" class="btn btn-'
            . $class
            . '"'
            . ($id ? ' id="' . $id . '"' : '')
            . ($props ? ' ' . $props : '')
            . '>';
        echo $text;
        echo '</button>';
        echo '<button type="button" class="btn btn-'
            . $class
            . ' dropdown-toggle" data-toggle="dropdown">';
        echo '<span class="caret"></span>';
        echo '<span class="sr-only">'
            . _('Toggle Dropdown')
            . '</span>';
        echo '</button>';
        echo '<ul class="dropdown-menu" role="menu">';
        foreach ($dropdownArray as &$dropdown) {
            $divider = isset($dropdown['divider']) ? $dropdown['divider']: '';
            if ($divider) {
                echo '<li class="divider"></li>';
            }
            $href = isset($dropdown['href']) ? $dropdown['href'] : '#';
            $did = isset($dropdown['id']) ? ' id="' . $dropdown['id'] . '"' : '';
            $dprops = isset($dropdown['props']) ? ' ' . $dropdown['props'] . ' ' : '';
            $dtext = isset($dropdown['text']) ? $dropdown['text'] : '';
            echo '<li>';
            echo '<a href="'
                . $href
                . '"'
                . $did
                . $dprops
                . '>'
                . $dtext
                . '</a>';
            echo '</li>';
            unset($dropdown);
        }
        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
    }
    /**
     * Makes a modal for us.
     *
     * @param string $id     The id of the modal.
     * @param string $header The header of the modal.
     * @param string $body   The body of the modal.
     * @param string $footer The footer of the modal.
     * @param string $class  The class to assign the modal.
     * @param string $type   The type of the modal.
     *
     * @return string
     */
    public static function makeModal(
        $id,
        $header,
        $body,
        $footer,
        $class = '',
        $type = 'default'
    ) {
        ob_start();
        echo '<div class="modal modal-'
            . $type
            . ' fade'
            . (
                $class ?
                ' '. $class :
                ''
            )
            . '" style="display: none;" id="'
            . $id
            . '">';
        echo '<div class="modal-dialog">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo $header;
        echo '</div>';
        echo '<div class="modal-body">';
        echo $body;
        echo '</div>';
        echo '<div class="modal-footer">';
        echo $footer;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }
    /**
     * Process the information
     *
     * @param int    $colsize    Column Size.
     * @param string $tableId    The id to give the table.
     * @param string $buttons    Buttons to append to the table.
     * @param string $tableClass Class to associate with the table.
     * @param bool   $serverSide Is this table a serverSide processing table.
     *
     * @return string
     */
    public function process(
        $colsize = 12,
        $tableId = 'dataTable',
        $buttons = '',
        $tableClass = '',
        $serverSide = true
    ) {
        try {
            unset($actionbox);
            global $sub;
            global $node;
            $actionbox = '';
            $modals = '';
            if ($sub == 'list') {
                if ($node != 'plugin') {
                    $actionbox .= self::makeButton(
                        'deleteSelected',
                        _('Delete selected'),
                        'btn btn-danger pull-left'
                    );
                    $actionbox .= '<div class="btn-group pull-right">';
                    if (method_exists($this, 'addModal')) {
                        if ($node == 'host') {
                            $actionbox .= self::makeButton(
                                'addSelectedToGroup',
                                _('Add selected to group'),
                                'btn btn-default'
                            );
                        }
                        $actionbox .= self::makeButton(
                            'createnew',
                            _('Add'),
                            'btn btn-primary pull-right'
                        );
                        ob_start();
                        $this->addModal();
                        $add = ob_get_clean();
                        $modals .= self::makeModal(
                            'createnewModal',
                            _('Create New') . ' ' . ucfirst(_($node)),
                            $add,
                            self::makeButton(
                                'closecreateModal',
                                _('Cancel'),
                                'btn btn-outline pull-left',
                                'data-dismiss="modal"'
                            )
                            . self::makeButton(
                                'send',
                                _('Create'),
                                'btn btn-primary pull-right'
                            ),
                            '',
                            'primary'
                        );
                    }
                    if ($node == 'host') {
                        $modals .= self::makeModal(
                            'addToGroupModal',
                            _('Add To Group(s)'),
                            '<select id="groupSelect" class="" '
                            . 'name="" multiple="multiple">'
                            . '</select>',
                            self::makeButton(
                                'closeGroupModal',
                                _('Cancel'),
                                'btn btn-outline pull-left',
                                'data-dismiss="modal"'
                            )
                            . self::makeButton(
                                'confirmGroupAdd',
                                _('Add'),
                                'btn btn-outline pull-right'
                            ),
                            '',
                            'info'
                        );
                    }
                    $actionbox .= '</div>';
                    $modals .= self::makeModal(
                        'deleteModal',
                        _('Confirm password'),
                        '<div class="input-group">'
                        . self::makeInput(
                            'form-control',
                            'deletePW',
                            _('Password'),
                            'password',
                            'deletePassword'
                        )
                        . '</div>'
                        . '<br/>'
                        . (
                            in_array($node, ['snapin', 'image', 'group']) ?
                            self::makeLabel(
                                'control-label',
                                (
                                    in_array($node, ['snapin', 'image']) ?
                                    'andFile' : 'andHosts'
                                ),
                                self::makeInput(
                                    '',
                                    (
                                        in_array($node, ['snapin', 'image']) ?
                                        'andFile' :
                                        'andHosts'
                                    ),
                                    '',
                                    'checkbox',
                                    (
                                        in_array($node, ['snapin', 'image']) ?
                                        'andFile' :
                                        'andHosts'
                                    )
                                )
                                . ' '
                                . (
                                    in_array($node, ['snapin', 'image']) ?
                                    _('Remove associated files') :
                                    _('Delete associated hosts')
                                )
                            ) :
                            ''
                        ),
                        self::makeButton(
                            'closeDeleteModal',
                            _('Cancel'),
                            'btn btn-outline pull-left',
                            'data-dismiss="modal"'
                        )
                        . self::makeButton(
                            'confirmDeleteModal',
                            _('Delete')
                            . ' {0} '
                            . _('{node}'),
                            'btn btn-outline pull-right'
                        ),
                        '',
                        'danger'
                    );
                }
            }
            $actionbox .= $buttons;
            self::$HookManager->processEvent(
                'ACTIONBOX',
                ['actionbox' => &$actionbox]
            );
            if (strlen($actionbox) > 0) {
                $actionbox = '<div class="btn-actionbox">'
                    . $actionbox
                    . '</div>';
            }
            if (in_array($node, ['task'])
                && (!$sub || $sub == 'list')
            ) {
                self::redirect("../management/index.php?node=$node&sub=active");
            }
            ob_start();
            echo '<table id="'
                . $tableId
                . '" class="'
                . $tableClass
                . '">';
            if (isset($this->data['error']) && $this->data['error']) {
                echo '<thead><tr class="header"></tr></thead>';
                echo '<tbody>';
                $tablestr = '<tr><td colspan="'
                    . count($this->headerData ?: [])
                    . '">';
                $tablestr .= (
                    is_array($this->data['error']) ?
                    '<p>'
                    . implode('</p><p>', $this->data['error'])
                    : $this->data['error']
                );
                $tablestr .= '</td></tr>';
                echo $tablestr;
                echo '</tbody>';
            } else {
                if (count($this->headerData ?: []) > 0) {
                    echo '<thead>';
                    echo $this->buildHeaderRow();
                    echo '</thead>';
                } else {
                    echo '<thead>';
                    echo '</thead>';
                }
                if ($serverSide || count($this->data ?: []) < 1) {
                    echo '<tbody></tbody>';
                } else {
                    echo '<tbody>';
                    $tablestr = '';
                    foreach ($this->data as &$rowData) {
                        $tablestr .= '<tr class="'
                            . strtolower($node)
                            . '" '
                            . (
                                isset($rowData['id']) || isset($rowData[$id_field]) ?
                                'id="'
                                . (
                                    isset($rowData['id']) ?
                                    $rowData['id'] . '"' :
                                    $rowData[$id_field] . '"'
                                ) :
                                ''
                            )
                            . '>';
                        $tablestr .= $this->buildRow($rowData);
                        $tablestr .= '</tr>';
                        unset($rowData);
                    }
                    echo $tablestr;
                    echo '</tbody>';
                }
            }
            echo '</table>';
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return ob_get_clean()
            . $actionbox
            . $modals;
    }
    /**
     * Sets the attributes
     *
     * @return void
     */
    private function _setAtts()
    {
        foreach ((array)$this->attributes as $index => &$attribute) {
            foreach ((array)$attribute as $name => &$val) {
                $this->atts[$index] = sprintf(
                    '%s %s="%s" ',
                    isset($this->atts[$index]) ? $this->atts[$index] : '',
                    $name,
                    $val
                );
                unset($val);
            }
            unset($attribute);
        }
    }
    /**
     * Builds the header row
     *
     * @return string
     */
    public function buildHeaderRow()
    {
        unset($this->atts);
        $this->_setAtts();
        if (count($this->headerData ?: []) < 1) {
            return '';
        }
        ob_start();
        echo '<tr class="header">';
        foreach ($this->headerData as $index => &$content) {
            echo '<th'
                . (
                    isset($this->atts[$index]) &&  $this->atts[$index] ?
                    ' '
                    . $this->atts[$index]
                    . ' ' :
                    ' '
                )
                . 'data-column="'
                . $index
                . '">';
            echo $content;
            echo '</th>';
            unset($content);
        }
        echo '</tr>';
        return ob_get_clean();
    }
    /**
     * Replaces the data for templated information
     *
     * @param mixed $data the data to replace
     *
     * @return void
     */
    private function _replaceNeeds($data)
    {
        unset(
            $this->dataFind,
            $this->dataReplace
        );
        global $node;
        global $sub;
        global $tab;
        $urlvars = [
            'node' => $node,
            'sub' => $sub,
            'tab' => $tab
        ];
        $arrayReplace = self::fastmerge(
            $urlvars,
            (array)$data
        );
        foreach ((array)$arrayReplace as $name => &$val) {
            $this->dataFind[] = sprintf(
                '${%s}',
                $name
            );
            $val = trim($val);
            $this->dataReplace[] = Initiator::e($val);
            unset($val);
        }
    }
    /**
     * Builds the row data
     *
     * @param mixed $data the data to build off
     *
     * @return string
     */
    public function buildRow($data)
    {
        unset($this->atts);
        $this->_setAtts();
        $this->_replaceNeeds($data);
        ob_start();
        foreach ((array)$this->templates as $index => &$template) {
            echo '<td'
                . (
                    $this->atts[$index] ?
                    ' ' . $this->atts[$index] . ' ' :
                    ''
                )
                . '>';
            $escapedReplace = array_map(
                function ($value) {
                    if (is_scalar($value) || $value === null) {
                        return Initiator::e((string)$value);
                    }
                    return '';
                },
                $this->dataReplace
            );
            echo str_replace(
                $this->dataFind,
                $escapedReplace,
                $template
            );
            echo '</td>';
            unset($template);
        }
        return ob_get_clean();
    }
    /**
     * Emits a JSON response body and terminates the request.
     *
     * This is the universal terminal shared by every AJAX endpoint:
     * set the HTTP status, echo the (already-encoded) body, and exit.
     * Callers that fire a hook must do so before calling this; use
     * jsonHookResponse() when a result hook needs to mutate the body.
     *
     * @param int    $code The HTTP status code to send.
     * @param string $body The response body (already JSON-encoded).
     *
     * @return void
     */
    protected static function jsonSend($code, $body)
    {
        http_response_code($code);
        echo $body;
        exit;
    }
    /**
     * Fires a result hook then emits the JSON response.
     *
     * Preserves the existing per-method hook contract exactly: the
     * caller passes the same by-reference argument array it always
     * has (including 'code' and 'msg'), so plugins registered on
     * $hook can still mutate the status code and body. The (possibly
     * mutated) values are read back from that same array after the
     * event fires. PHP preserves the member references when the array
     * is passed by value, so $args['code']/$args['msg'] resolve to the
     * caller's $code/$msg exactly as the inline code did.
     *
     * @param array  $args The by-reference hook argument array; must
     *                      contain 'code' and 'msg' keys.
     * @param string $hook The hook event name to fire.
     *
     * @return void
     */
    protected function jsonHookResponse(array $args, $hook)
    {
        self::$HookManager->processEvent($hook, $args);
        $this->jsonSend($args['code'], $args['msg']);
    }
    /**
     * Shared scaffold for the create (addPost) AJAX handlers.
     *
     * Owns the boilerplate every create endpoint repeated verbatim:
     * the auth/CSRF gate, the JSON content-type header, the
     * "<BASE>_POST" pre-event, the $serverFault flag, the try/catch
     * that turns a thrown Exception into the proper HTTP status, the
     * success/fail hook names and JSON body, and the terminal
     * jsonHookResponse() that fires the result hook and emits the
     * response.
     *
     * The page-specific part — reading $_POST, validating, building
     * and saving the entity — lives in the $build closure. The closure
     * receives $serverFault by reference (set it true before throwing
     * to signal an HTTP 500 rather than a 400) and must return the
     * saved entity, which is handed to the result hook under
     * $entityKey so listeners registered on "<BASE>_SUCCESS" /
     * "<BASE>_FAIL" still see it exactly as before.
     *
     * @param string   $entityKey    Payload key for the entity (e.g. 'Group').
     * @param string   $hookBase     Hook prefix (e.g. 'GROUP_ADD'); the
     *                               _POST/_SUCCESS/_FAIL events derive from it.
     * @param string   $successMsg   Translated success message body.
     * @param string   $successTitle Translated success title.
     * @param string   $failTitle    Translated failure title.
     * @param callable $build        Closure(&$serverFault): returns the entity.
     *
     * @return void
     */
    protected function handleAddPost(
        $entityKey,
        $hookBase,
        $successMsg,
        $successTitle,
        $failTitle,
        callable $build
    ) {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent($hookBase . '_POST');
        $serverFault = false;
        $Entity = null;
        try {
            $Entity = $build($serverFault);
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = $hookBase . '_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => $successMsg,
                    'title' => $successTitle
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = $hookBase . '_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $failTitle
                ]
            );
        }
        $this->jsonHookResponse(
            [
                $entityKey => &$Entity,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Shared scaffold for the update (editPost) AJAX handlers.
     *
     * The edit counterpart of handleAddPost(). It owns the same
     * boilerplate, with three differences inherent to editing an
     * existing entity: the "<BASE>_POST" pre-event carries the loaded
     * entity ([$entityKey => &$this->obj]) so listeners can inspect or
     * replace it; the success status is HTTP 202 Accepted rather than
     * 201 Created; and the entity is the page's own $this->obj, so the
     * $build closure mutates it in place and need not return anything.
     *
     * The page-specific part — reading $_POST, applying changes to
     * $this->obj, and saving — lives in the $build closure. The closure
     * receives $serverFault by reference (set it true before throwing to
     * signal an HTTP 500 rather than a 400). $this->obj is handed to the
     * result hook under $entityKey so listeners on "<BASE>_SUCCESS" /
     * "<BASE>_FAIL" still see it exactly as before.
     *
     * @param string   $entityKey    Payload key for the entity (e.g. 'Group').
     * @param string   $hookBase     Hook prefix (e.g. 'GROUP_EDIT'); the
     *                               _POST/_SUCCESS/_FAIL events derive from it.
     * @param string   $successMsg   Translated success message body.
     * @param string   $successTitle Translated success title.
     * @param string   $failTitle    Translated failure title.
     * @param callable $build        Closure(&$serverFault): mutates $this->obj.
     *
     * @return void
     */
    protected function handleEditPost(
        $entityKey,
        $hookBase,
        $successMsg,
        $successTitle,
        $failTitle,
        callable $build
    ) {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            $hookBase . '_POST',
            [$entityKey => &$this->obj]
        );
        $serverFault = false;
        try {
            $build($serverFault);
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = $hookBase . '_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => $successMsg,
                    'title' => $successTitle
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = $hookBase . '_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $failTitle
                ]
            );
        }
        $this->jsonHookResponse(
            [
                $entityKey => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Actually performs the deletion of selected items.
     *
     * @return void
     */
    public function deletemulti()
    {
        header('Content-type: application/json');
        self::checkauth();
        $remitems = filter_input_array(
            INPUT_POST,
            [
                'remitems' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $remitems = $remitems['remitems'];
        $andfiles = isset($_POST['andFile']) && $_POST['andFile'] == 1;
        $andhosts = isset($_POST['andHosts']) && $_POST['andHosts'] == 1;
        self::$HookManager->processEvent(
            'MULTI_REMOVE',
            ['removing' => &$remitems]
        );
        $serverFault = false;
        try {
            $where = ['id' => $remitems];
            if ($andfiles && in_array($this->childClass, ['Snapin', 'Image', 'snapin', 'image'])) {
                switch ($this->childClass) {
                    case 'Snapin':
                    case 'snapin':
                        $groupassoc = 'snapingroupassociation';
                        $pathKey = 'file';
                        break;
                    case 'Image':
                    case 'image':
                        $groupassoc = 'imageassociation';
                        $pathKey = 'path';
                        break;
                }
                $insert_fields = [
                    'path',
                    'pathtype',
                    'createdTime',
                    'stateID',
                    'createdBy',
                    'storagegroupID'
                ];
                $insert_values = [];
                Route::listem(
                    $this->childClass,
                    $where
                );
                $items = json_decode(Route::getData());
                foreach ($items->data as $item) {
                    $storagegroups[$item->$pathKey] = Route::getIds(
                        $groupassoc,
                        [strtolower($this->childClass).'ID' => $item->id],
                        'storagegroupID'
                    );
                }
                foreach ($storagegroups as $pathItem => $storagegroupIDs) {
                    foreach ($storagegroupIDs as $storagegroupID) {
                        $insert_values[] = [
                            $pathItem,
                            $this->childClass,
                            self::formatTime('now', 'Y-m-d H:i:s'),
                            self::getQueuedState(),
                            self::$FOGUser->get('name'),
                            $storagegroupID
                        ];
                    }
                }
                self::getClass('filedeletequeuemanager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
            Route::deletemass($this->childClass, $where);
            $msg = json_encode(
                [
                    'msg' => _('Successfully deleted'),
                    'title' => _('Delete Success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
        } catch (Exception $e) {
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Remove Fail')
                ]
            );
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Displays the AD options
     *
     * @param mixed  $useAD      whether to use ad or not
     * @param string $ADDomain   the domain to select
     * @param string $ADOU       the ou to select
     * @param string $ADUser     the user to use
     * @param string $ADPass     the password
     * @param mixed  $ownElement do we need to be our own container
     * @param mixed  $retFields  return just the fields?
     *
     * @return void|array
     */
    public function adFieldsToDisplay(
        $useAD = '',
        $ADDomain = '',
        $ADOU = '',
        $ADUser = '',
        $ADPass = '',
        $ownElement = true,
        $retFields = false,
        $groupShared = false
    ) {
        global $node;
        global $sub;
        if ($this->obj->isValid()) {
            if (empty($useAD)) {
                $useAD = $this->obj->get('useAD');
            }
            if (empty($ADDomain)) {
                $ADDomain = Initiator::e($this->obj->get('ADDomain'));
            }
            if (empty($ADOU)) {
                $ADOU = trim($this->obj->get('ADOU'));
                $ADOU = str_replace(';', '', Initiator::e($ADOU));
            }
            if (empty($ADUser)) {
                $ADUser = Initiator::e($this->obj->get('ADUser'));
            }
            if (empty($ADPass)) {
                $ADPass = (
                    $this->obj->get('ADPass') ?
                    '********************************' :
                    ''
                );
            }
        }
        $OUs = array_unique(
            array_filter(
                explode(
                    '|',
                    Initiator::e(self::getSetting('FOG_AD_DEFAULT_OU'))
                )
            )
        );
        $ADOU = trim($ADOU);
        $ADOU = str_replace(';', '', Initiator::e($ADOU));
        $optFound = $ADOU;
        if (count($OUs ?: []) > 1) {
            ob_start();
            echo '<select class="form-control" id="adOU" name="ou">';
            echo '<option value="">- '
                . _('Please select an option')
                . ' -</option>';
            foreach ($OUs as &$OU) {
                $OU = trim($OU);
                $ou = str_replace(';', '', $OU);
                if (!$optFound && $ou === $ADOU) {
                    $optFound = $ou;
                }
                if (!$optFound && false !== strpos($OU, ';')) {
                    $optFound = $ou;
                }
                echo '<option value="'
                    . Initiator::e($ou)
                    . '"'
                    . ($optFound == $ou ? ' selected' : '')
                    . '>'
                    . Initiator::e($ou)
                    . '</option>';
                unset($OU);
            }
            echo '</select>';
            $OUOptions = ob_get_clean();
        } else {
            $OUOptions = self::makeInput(
                'form-control adou-input',
                'ou',
                'ou=computers,dc=example,dc=com',
                'text',
                'adOU',
                $ADOU
            );
        }

        $labelClass = 'col-sm-3 control-label';

        if ($groupShared) {
            // Group mode: tri-state so "no change" leaves each host's join
            // state alone (a plain checkbox could only force on/off for all).
            $selVal = ($useAD === '1' || $useAD === 1) ? '1'
                : (($useAD === '0' || $useAD === 0) ? '0' : '');
            $adEnabledControl = '<select class="form-control" id="adEnabled" '
                . 'name="adstate">'
                . '<option value=""' . ($selVal === '' ? ' selected' : '') . '>'
                . _('No change') . '</option>'
                . '<option value="1"' . ($selVal === '1' ? ' selected' : '') . '>'
                . _('Enable on all hosts') . '</option>'
                . '<option value="0"' . ($selVal === '0' ? ' selected' : '') . '>'
                . _('Disable on all hosts') . '</option>'
                . '</select>';
            $adEnabledLabel = _('Domain Joining');
        } else {
            $adEnabledControl = self::makeInput(
                '',
                'domain',
                '',
                'checkbox',
                'adEnabled',
                '',
                false,
                false,
                -1,
                -1,
                $useAD ? 'checked' : ''
            );
            $adEnabledLabel = _('Enable Domain Joining');
        }
        $fields = [
            self::makeLabel(
                $labelClass,
                'adEnabled',
                $adEnabledLabel
            ) => $adEnabledControl,
            self::makeLabel(
                $labelClass,
                'adDomain',
                _('Domain Name')
            ) => self::makeInput(
                'form-control',
                'domainname',
                'example.com',
                'text',
                'adDomain',
                $ADDomain
            ),
            self::makeLabel(
                $labelClass,
                'adOU',
                _('Organizational Unit')
                . '<br/>('
                . _('blank for default')
                . ')'
            ) => $OUOptions,
            self::makeLabel(
                $labelClass,
                'adUsername',
                _('Domain Username')
            ) => self::makeInput(
                'form-control',
                'domainuser',
                'administrator',
                'text',
                'adUsername',
                $ADUser
            ),
            self::makeLabel(
                $labelClass,
                'adPassword',
                _('Domain Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control',
                'domainpassword',
                'password',
                'password',
                'adPassword',
                $ADPass
            )
            . '</div>'
        ];
        if ($retFields) {
            return $fields;
        }
        $ucclass = strtoupper($this->childClass);
        self::$HookManager->processEvent(
            "{$ucclass}_EDIT_AD_FIELDS",
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'obj' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        if ($ownElement) {
            echo '<div class="box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo $this->childClass . ' ' . _('Active Directory');
            echo '</h4>';
            echo '</div>';
            echo self::makeFormTag(
                'form-horizontal',
                'active-directory-form',
                self::makeTabUpdateURL(
                    $node . '-active-directory',
                    Initiator::e($this->obj->get('id'))
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo '<div id="'
                . $node
                . '-active-directory" class="">';

            echo '  <div class="box-body">';
        }
        echo $rendered;
        if ($ownElement) {
            $buttons = self::makeButton(
                'ad-send',
                _('Update'),
                'btn btn-primary pull-right'
            );
            $buttons .= self::makeButton(
                'ad-clear',
                _('Clear Fields'),
                'btn btn-danger pull-left'
            );
            echo '</div>';
            echo '<div class="box-footer with-border">';
            echo $buttons;
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
    }
    /**
     * Get's the adinformation from ajax
     *
     * @return void
     */
    public function adInfo()
    {
        if (!self::$ajax) {
            return;
        }
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $names = [
            'FOG_AD_DEFAULT_DOMAINNAME',
            'FOG_AD_DEFAULT_OU',
            'FOG_AD_DEFAULT_PASSWORD',
            'FOG_AD_DEFAULT_USER',
        ];
        self::$HookManager->processEvent(
            'DEFAULT_AD_INFORMATION',
            ['names' => &$names]
        );
        list(
            $domainname,
            $ou,
            $password,
            $user
        ) = self::getSetting($names);
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'domainname' => $domainname,
                'ou' => $ou,
                'domainpass' => $password,
                'domainuser' => $user,
            ]
        ));
    }
    /**
     * Fetches the kernels
     *
     * @return mixed
     */
    public function kernelfetch()
    {
        header('Content-type: application/json');
        try {
            $msg = filter_input(INPUT_POST, 'msg');
            $br_ver = filter_input(INPUT_POST, 'buildroot');
            $tg_ver = filter_input(INPUT_POST, 'tag_name');
            if ($_SESSION['allow_ajax_kdl']
                && $_SESSION['dest-kernel-file']
                && $_SESSION['tmp-kernel-file']
                && $_SESSION['dl-kernel-file']
            ) {
                if ($msg == 'dl') {
                    $destFilename = $_SESSION['dest-kernel-file'];
                    if (preg_match('/\./', $destFilename)) {
                        throw new Exception(_('Dot in Filename not allowed!'));
                    }
                    $dlUrl = $_SESSION['dl-kernel-file'];
                    if (!(0 === stripos($dlUrl, 'https://fogproject.org/') ||
                        0 === stripos($dlUrl, 'https://github.com/FOGProject/'))
                    ) {
                        throw new Exception(_('Specified download URL not allowed!'));
                    }
                    $fh = fopen(
                        $_SESSION['tmp-kernel-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new Exception(
                            _('Error: Failed to open temp file')
                        );
                    }
                    self::$FOGURLRequests->process(
                        $_SESSION['dl-kernel-file'],
                        'GET',
                        false,
                        false,
                        false,
                        false,
                        $fh
                    );
                    if (!file_exists($_SESSION['tmp-kernel-file'])) {
                        throw new Exception(
                            _('Error: Failed to download kernel')
                        );
                    }
                    $filesize = self::getFilesize(
                        $_SESSION['tmp-kernel-file']
                    );
                    if (!$filesize >  1048576) {
                        throw new Exception(
                            sprintf(
                                '%s: %s: %s - %s',
                                _('Error'),
                                _('Download Failed'),
                                _('Failed'),
                                _('filesize'),
                                $filesize
                            )
                        );
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => _('File downloaded!'),
                            'title' => _('Download Complete')
                        ]
                    ));
                } elseif ($msg == 'tftp') {
                    $destfile = $_SESSION['dest-kernel-file'];
                    $tmpfile = $_SESSION['tmp-kernel-file'];
                    unset(
                        $_SESSION['dest-kernel-file'],
                        $_SESSION['tmp-kernel-file'],
                        $_SESSION['dl-kernel-file']
                    );
                    $orig = sprintf(
                        '/%s/%s',
                        trim(self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'), '/'),
                        $destfile
                    );
                    $backuppath = sprintf(
                        '/%s/backup/',
                        dirname($orig)
                    );
                    $backupfile = sprintf(
                        '%s%s_%s',
                        $backuppath,
                        $destfile,
                        self::formatTime('', 'Ymd_His')
                    );
                    $keys = [
                        'FOG_TFTP_FTP_PASSWORD',
                        'FOG_TFTP_FTP_USERNAME',
                        'FOG_TFTP_HOST'
                    ];
                    list(
                        $tftpPass,
                        $tftpUser,
                        $tftpHost
                    ) = self::getSetting($keys);
                    self::$FOGSSH->username = $tftpUser;
                    self::$FOGSSH->password = $tftpPass;
                    self::$FOGSSH->host = $tftpHost;
                    if (!self::$FOGSSH->connect()) {
                        throw new Exception(_('Unable to connect to ssh'));
                    }
                    if (!self::$FOGSSH->exists($backuppath)) {
                        self::$FOGSSH->sftp_mkdir($backuppath);
                    }
                    if (self::$FOGSSH->exists($orig)) {
                        self::$FOGSSH->sftp_rename($orig, $backupfile);
                    }
                    self::$FOGSSH->put($tmpfile, $orig);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    $br_cmd = "attr -s version -V $br_ver $orig";
                    $tg_cmd = "attr -s tag_name -V $tg_ver $orig";
                    $output_br = self::$FOGSSH->exec($br_cmd);
                    $output_tg = self::$FOGSSH->exec($tg_cmd);
                    $error_br = self::$FOGSSH->fetch_stream($output_br, SSH2_STREAM_STDERR);
                    $error_tg = self::$FOGSSH->fetch_stream($output_tg, SSH2_STREAM_STDERR);
                    stream_set_blocking($output_br, true);
                    stream_set_blocking($output_tg, true);
                    stream_set_blocking($error_br, true);
                    stream_set_blocking($error_tg, true);
                    $error_br_t = stream_get_contents($error_br);
                    $error_tg_t = stream_get_contents($error_tg);
                    if ($error_br_t) {
                        error_log(_('Error on ssh command setting version'). ' ' . $br_cmd);
                        error_log(_('Error'). ': ' . $error_br_t);
                    }
                    if ($error_tg_t) {
                        error_log(_('Error on ssh command setting tag_name'). ' ' . $tg_cmd);
                        error_log(_('Error'). ': ' . $error_tg_t);
                    }
                    fclose($output_br);
                    fclose($output_tg);
                    fclose($error_br);
                    fclose($error_tg);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    self::$FOGSSH->disconnect();
                    if (file_exists($tmpfile)) {
                        unlink($tmpfile);
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => _('File uploaded to storage node!'),
                            'title' => _('Update Kernel Success')
                        ]
                    ));
                }
            }
        } catch (Exception $e) {
            $this->jsonSend(HTTPResponseCodes::HTTP_BAD_REQUEST, json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Kernel Update Fail')
                ]
            ));
        }
    }
    /**
     * Fetches the initrds
     *
     * @return mixed
     */
    public function initrdfetch()
    {
        header('Content-type: application/json');
        try {
            $msg = filter_input(INPUT_POST, 'msg');
            $br_ver = filter_input(INPUT_POST, 'buildroot');
            $tg_ver = filter_input(INPUT_POST, 'tag_name');
            if ($_SESSION['allow_ajax_idl']
                && $_SESSION['dest-initrd-file']
                && $_SESSION['tmp-initrd-file']
                && $_SESSION['dl-initrd-file']
            ) {
                if ($msg == 'dl') {
                    $destFilename = $_SESSION['dest-initrd-file'];
                    $dlUrl = $_SESSION['dl-initrd-file'];
                    if (!(0 === stripos($dlUrl, 'https://fogproject.org/') ||
                        0 === stripos($dlUrl, 'https://github.com/FOGProject/'))
                    ) {
                        throw new Exception(_('Specified download URL not allowed!'));
                    }
                    $fh = fopen(
                        $_SESSION['tmp-initrd-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new Exception(
                            _('Error: Failed to open temp file')
                        );
                    }
                    self::$FOGURLRequests->process(
                        $_SESSION['dl-initrd-file'],
                        'GET',
                        false,
                        false,
                        false,
                        false,
                        $fh
                    );
                    if (!file_exists($_SESSION['tmp-initrd-file'])) {
                        throw new Exception(
                            _('Error: Failed to download initrd')
                        );
                    }
                    $filesize = self::getFilesize(
                        $_SESSION['tmp-initrd-file']
                    );
                    if (!$filesize >  1048576) {
                        throw new Exception(
                            sprintf(
                                '%s: %s: %s - %s',
                                _('Error'),
                                _('Download Failed'),
                                _('Failed'),
                                _('filesize'),
                                $filesize
                            )
                        );
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => _('File downloaded!'),
                            'title' => _('Download Complete')
                        ]
                    ));
                } elseif ($msg == 'tftp') {
                    $destfile = $_SESSION['dest-initrd-file'];
                    $tmpfile = $_SESSION['tmp-initrd-file'];
                    unset(
                        $_SESSION['dest-initrd-file'],
                        $_SESSION['tmp-initrd-file'],
                        $_SESSION['dl-initrd-file']
                    );
                    $orig = sprintf(
                        '/%s/%s',
                        trim(self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'), '/'),
                        $destfile
                    );
                    $backuppath = sprintf(
                        '/%s/backup/',
                        dirname($orig)
                    );
                    $backupfile = sprintf(
                        '%s%s_%s',
                        $backuppath,
                        $destfile,
                        self::formatTime('', 'Ymd_His')
                    );
                    $keys = [
                        'FOG_TFTP_FTP_PASSWORD',
                        'FOG_TFTP_FTP_USERNAME',
                        'FOG_TFTP_HOST'
                    ];
                    list(
                        $tftpPass,
                        $tftpUser,
                        $tftpHost
                    ) = self::getSetting($keys);
                    self::$FOGSSH->username = $tftpUser;
                    self::$FOGSSH->password = $tftpPass;
                    self::$FOGSSH->host = $tftpHost;
                    if (!self::$FOGSSH->connect()) {
                        throw new Exception(_('Unable to connect to SSH'));
                    }
                    if (!self::$FOGSSH->exists($backuppath)) {
                        self::$FOGSSH->sftp_mkdir($backuppath);
                    }
                    if (self::$FOGSSH->exists($orig)) {
                        self::$FOGSSH->sftp_rename($orig, $backupfile);
                    }
                    self::$FOGSSH->put($tmpfile, $orig);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    $br_cmd = "attr -s version -V $br_ver $orig";
                    $tg_cmd = "attr -s tag_name -V $tg_ver $orig";
                    $output_br = self::$FOGSSH->exec($br_cmd);
                    $output_tg = self::$FOGSSH->exec($tg_cmd);
                    $error_br = self::$FOGSSH->fetch_stream($output_br, SSH2_STREAM_STDERR);
                    $error_tg = self::$FOGSSH->fetch_stream($output_tg, SSH2_STREAM_STDERR);
                    stream_set_blocking($output_br, true);
                    stream_set_blocking($output_tg, true);
                    stream_set_blocking($error_br, true);
                    stream_set_blocking($error_tg, true);
                    $error_br_t = stream_get_contents($error_br);
                    $error_tg_t = stream_get_contents($error_tg);
                    if ($error_br_t) {
                        error_log(_('Error on ssh command setting version'). ' ' . $br_cmd);
                        error_log(_('Error'). ': ' . $error_br_t);
                    }
                    if ($error_tg_t) {
                        error_log(_('Error on ssh command setting tag_name'). ' ' . $tg_cmd);
                        error_log(_('Error'). ': ' . $error_tg_t);
                    }
                    fclose($output_br);
                    fclose($output_tg);
                    fclose($error_br);
                    fclose($error_tg);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    self::$FOGSSH->disconnect();
                    if (file_exists($tmpfile)) {
                        unlink($tmpfile);
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => _('File uploaded to storage node!'),
                            'title' => _('Update Initrd Success')
                        ]
                    ));
                }
            }
        } catch (Exception $e) {
            $this->jsonSend(HTTPResponseCodes::HTTP_BAD_REQUEST, json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Initrd Update Fail')
                ]
            ));
        }
    }
    /**
     * Presents the delete modal.
     *
     * @return string
     */
    protected function deleteModal()
    {
        global $node;
        $extra = '';
        if ($this->obj instanceof Group) {
            $extra .= '<br/>';
            $extra .= self::makeLabel(
                'control-label',
                'andHosts',
                self::makeInput(
                    '',
                    'andHosts',
                    '',
                    'checkbox',
                    'andHosts'
                )
                . ' '
                . _('Delete associated hosts')
            );
        } elseif ($this->obj instanceof Image || $this->obj instanceof Snapin) {
            $extra .= '<br/>';
            $extra .= self::makeLabel(
                'control-label',
                'andFile',
                self::makeInput(
                    '',
                    'andFile',
                    '',
                    'checkbox',
                    'andFile'
                )
                . ' '
                . _('Remove file data')
            );
        }
        return self::makeModal(
            'deleteModal',
            _('Delete')
            . ': '
            . Initiator::e($this->obj->get('name')),
            _("Confirm you would like to delete this $node")
            . $extra,
            self::makeButton(
                'closeDeleteModal',
                _('Cancel'),
                'btn btn-outline pull-left',
                'data-dismiss="modal"'
            )
            . self::makeButton(
                'confirmDeleteModal',
                _('Delete'),
                'btn btn-outline pull-right'
            ),
            '',
            'danger'
        );
    }
    /**
     * Presents the assoc delete modal.
     *
     * @param string $item The item we're working with.
     *
     * @return string
     */
    protected function assocDelModal($item = '')
    {
        return self::makeModal(
            "{$item}DelModal",
            _("Remove $item Associations"),
            _("Please confirm you would like to dissociate the selected {$item}s"),
            self::makeButton(
                "close{$item}DeleteModal",
                _('Cancel'),
                'btn btn-outline pull-left',
                'data-dismiss="modal"'
            )
            . self::makeButton(
                "confirm{$item}DeleteModal",
                _('Remove'),
                'btn btn-outline pull-right'
            ),
            '',
            'warning'
        );
    }
    /**
     * Renders a standard association tab: a primary box containing the
     * add/remove buttons, the server-side list table, and the dissociate
     * confirmation modal.
     *
     * @param string $tabSlug   node-sub slug (e.g. 'host-group') driving the
     *                          button ids, table id, and tab update URL
     * @param string $boxTitle  translated box title (e.g. _('Host Group Associations'))
     * @param string $colHeader translated first-column header (e.g. _('Group Name'))
     * @param string $delItem   singular item name passed to assocDelModal (e.g. 'group')
     * @param string $sendClass css class for the "Add selected" button (some tabs
     *                          use 'btn btn-success pull-right' instead of primary)
     * @param string $helpBlock optional translated help text rendered as a
     *                          help-block in the box header (already escaped/safe)
     *
     * @return void
     */
    protected function renderAssocTab(
        $tabSlug,
        $boxTitle,
        $colHeader,
        $delItem,
        $sendClass = 'btn btn-primary pull-right',
        $helpBlock = ''
    ) {
        $this->headerData = [
            $colHeader,
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                $tabSlug,
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            "$tabSlug-send",
            _('Add selected'),
            $sendClass,
            $props
        );
        $buttons .= self::makeButton(
            "$tabSlug-remove",
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $boxTitle;
        echo '</h4>';
        if ($helpBlock !== '') {
            echo '<p class="help-block">';
            echo $helpBlock;
            echo '</p>';
        }
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, "$tabSlug-table", $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal($delItem);
        echo '</div>';
        echo '</div>';
    }
    /**
     * Handles a standard association add/remove POST: reads the additems /
     * remitems arrays and dispatches them to the object's add/remove methods.
     * When $orderMethod is supplied, also honours a snapinorder array (used by
     * the group/host snapin tabs to persist execution order).
     *
     * @param string $addMethod    obj method to add associations (e.g. 'addGroup')
     * @param string $removeMethod obj method to remove associations (e.g. 'removeGroup')
     * @param string $orderMethod  obj method to set ordering from the snapinorder
     *                             POST array, or null when the tab has no ordering
     *
     * @return void
     */
    protected function assocPost($addMethod, $removeMethod, $orderMethod = null)
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['confirmadd'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $items = $items['additems'];
            if (count($items ?: []) > 0) {
                $this->obj->{$addMethod}($items);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $items = $items['remitems'];
            if (count($items ?: []) > 0) {
                $this->obj->{$removeMethod}($items);
            }
        }
        if ($orderMethod !== null && isset($_POST['snapinorder'])) {
            $order = filter_input_array(
                INPUT_POST,
                [
                    'snapinorder' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $order = $order['snapinorder'];
            if (count($order ?: []) > 0) {
                $this->obj->{$orderMethod}($order);
            }
        }
    }
    /**
     * Renders a simple display tab: a box-primary panel with a title and a
     * single DataTable. Shared by the group/host history tabs whose only
     * differences are the column set, the title text and the table id.
     *
     * @param array  $headerData The column headers (already translated).
     * @param array  $attributes The per-column attribute arrays.
     * @param string $title      The box title (already translated).
     * @param string $tableId    The DataTable element id.
     *
     * @return void
     */
    protected function renderHistoryTab(array $headerData, array $attributes, $title, $tableId)
    {
        $this->headerData = $headerData;
        $this->attributes = $attributes;
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, $tableId);
        echo '</div>';
        echo '</div>';
    }
    /**
     * Streams a Login/Image history datatable payload as JSON.
     *
     * Shared by the host and group Login/Image history AJAX endpoints, which
     * differ only in the host scope (a single host id for a host, the member
     * host ids for a group) and the route resource to list.
     *
     * @param mixed  $scope the hostID scope (int for a host, array for a group)
     * @param string $route the Route resource to list (e.g. 'usertracking')
     *
     * @return void
     */
    protected function renderHistoryData($scope, $route)
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        Route::listem(
            $route,
            ['hostID' => $scope]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Streams the snapin-task history datatable payload as JSON.
     *
     * Shared by the host and group Snapin history AJAX endpoints; differs only
     * in the host scope. Returns an empty datatable payload (rather than an
     * unscoped lookup) when the scope has no snapin jobs.
     *
     * @param mixed $scope the hostID scope (int for a host, array for a group)
     *
     * @return void
     */
    protected function renderSnapinHistoryData($scope)
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $checkStates = [
            self::getCancelledState(),
            self::getCompleteState()
        ];

        $snapinJobs = Route::getIds(
            'snapinjob',
            ['hostID' => $scope]
        );
        $snapinJobs = array_filter(
            array_map('intval', (array)$snapinJobs),
            function ($id) {
                return $id > 0;
            }
        );

        // If there are no jobs in scope, return an empty datatable payload and
        // avoid an unscoped snapintask lookup.
        if (count($snapinJobs) < 1) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'draw' => (int)filter_input(INPUT_POST, 'draw') ?: 0,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    '_lang' => 'snapintask'
                ]
            ));
        }

        Route::listem(
            'snapintask',
            [
                'jobID' => $snapinJobs,
                'stateID' => $checkStates
            ]
        );

        echo Route::getData();
        exit;
    }
    /**
     * Builds a standard association list table via getItemsList using a LEFT
     * OUTER JOIN that flags which rows are already associated with the current
     * object.
     *
     * @param string $itemType     item class to list (e.g. 'group')
     * @param string $listType     list/association type key (e.g. 'groupassociation')
     * @param string $assocTable   join table (e.g. 'groupMembers')
     * @param string $itemKey      listed item's key column (e.g. '`groups`.`groupID`')
     * @param string $assocItemKey join table's item column (e.g. '`groupMembers`.`gmGroupID`')
     * @param string $ownerKey     join table's owner column (e.g. '`groupMembers`.`gmHostID`')
     * @param array  $columns      extra association column definition(s)
     *
     * @return void
     */
    protected function assocItemsList(
        $itemType,
        $listType,
        $assocTable,
        $itemKey,
        $assocItemKey,
        $ownerKey,
        array $columns
    ) {
        $join = [
            "LEFT OUTER JOIN `$assocTable` ON "
            . "$itemKey = $assocItemKey "
            . "AND $ownerKey = '" . $this->obj->get('id') . "'"
        ];
        return $this->obj->getItemsList(
            $itemType,
            $listType,
            $join,
            '',
            $columns
        );
    }
    /**
     * Sends the new client the configuration options
     *
     * @return void
     */
    public function configure()
    {
        $keys = [
            'FOG_CLIENT_CHECKIN_TIME',
            'FOG_CLIENT_MAXSIZE',
            'FOG_GRACE_TIMEOUT',
            'FOG_TASK_FORCE_REBOOT'
        ];
        $Services = self::getSetting($keys);
        printf(
            "#!ok\n"
            . "#sleep=%d\n"
            . "#maxsize=%d\n"
            . "#promptTime=%d\n"
            . "#force=%s",
            array_shift($Services) + mt_rand(1, 91),
            array_shift($Services),
            array_shift($Services),
            array_shift($Services)
        );
        exit;
    }
    /**
     * Authorizes the client with the server
     *
     * @return void
     */
    public function authorize()
    {
        try {
            self::getHostItem(true);
            $sym_key = filter_input(INPUT_POST, 'sym_key');
            if (!$sym_key) {
                $sym_key = filter_input(INPUT_GET, 'sym_key');
            }
            $token = filter_input(INPUT_POST, 'token');
            if (!$token) {
                $token = filter_input(INPUT_GET, 'token');
            }
            $data = array_values(
                array_map(
                    'bin2hex',
                    self::certDecrypt(
                        [
                            $sym_key,
                            $token
                        ]
                    )
                )
            );
            $key = $data[0];
            $token = $data[1];
            if (self::$Host->get('sec_tok')
                && $token !== self::$Host->get('sec_tok')
            ) {
                self::$Host
                    ->set(
                        'pub_key',
                        null
                    )->save()->load();
                throw new Exception('#!ist');
            }
            if (self::$Host->get('sec_tok')
                && !$key
            ) {
                throw new Exception('#!ihc');
            }
            $expire = self::niceDate(self::$Host->get('sec_time'));
            if (self::niceDate() > $expire
                || !trim(self::$Host->get('pub_key'))
            ) {
                self::$Host
                    ->set(
                        'sec_time',
                        self::niceDate()
                        ->modify('+30 minutes')
                        ->format('Y-m-d H:i:s')
                    )
                    ->set(
                        'sec_tok',
                        self::createSecToken()
                    );
            }
            self::$Host
                ->set('pub_key', $key)
                ->save();
            $vals['token'] = self::$Host->get('sec_tok');
            if (self::$json === true) {
                printf(
                    '#!en=%s',
                    self::certEncrypt(
                        json_encode($vals)
                    )
                );
                exit;
            }
            printf(
                '#!en=%s',
                self::certEncrypt(
                    "#!ok\n#token=" . self::$Host->get('sec_tok')
                )
            );
        } catch (Exception $e) {
            if (self::$json === true) {
                if ($e->getMessage() == '#!ihc') {
                    die($e->getMessage());
                }
                $err = str_replace('#!', '', $e->getMessage());
                $this->jsonSend(
                    HTTPResponseCodes::HTTP_UNAUTHORIZED,
                    json_encode(['error' => $err])
                );
            }
            if ($e->getMessage() == '#!ist') {
                $this->jsonSend(
                    HTTPResponseCodes::HTTP_UNAUTHORIZED,
                    json_encode(['error' => 'ist'])
                );
            }
            echo  $e->getMessage();
        }
        exit;
    }
    /**
     * Used by the new client and collects
     * all the information at once. This
     * allows the client to do much less polls
     * to the server.
     *
     * @return void
     */
    public function requestClientInfo()
    {
        if (isset($_POST['configure'])
            || isset($_GET['configure'])
        ) {
            $keys = [
                'FOG_CLIENT_BANNER_IMAGE',
                'FOG_CLIENT_BANNER_SHA',
                'FOG_CLIENT_CHECKIN_TIME',
                'FOG_CLIENT_MAXSIZE',
                'FOG_COMPANY_COLOR',
                'FOG_COMPANY_NAME',
                'FOG_GRACE_TIMEOUT',
                'FOG_TASK_FORCE_REBOOT'
            ];
            list(
                $bannerimg,
                $bannersha,
                $checkin,
                $maxsize,
                $pcolor,
                $coname,
                $timeout,
                $freboot
            ) = self::getSetting($keys);
            $vals = [
                'sleep' => $checkin + mt_rand(1, 91),
                'maxsize' => $maxsize,
                'promptTime' => $timeout,
                'force' => (bool)$freboot,
                'bannerURL' => (
                    $bannerimg ?
                    sprintf(
                        '/management/other/%s',
                        $bannerimg
                    ) :
                    ''
                ),
                'bannerHash' => strtoupper($bannersha),
                'color' => "#$pcolor",
                'company' => $coname
            ];
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($vals));
        }
        if (isset($_POST['authorize'])
            || isset($_GET['authorize'])
        ) {
            $this->authorize(true);
        }
        // Handles adding additional system macs for us.
        ob_start();
        self::getClass('RegisterClient')->json();
        ob_end_clean();
        try {
            $igMods = [
                'dircleanup',
                'usercleanup',
                'clientupdater',
                'hostregister',
            ];
            $globalModules = array_diff(
                self::getGlobalModuleStatus(false, true),
                [
                    'dircleanup',
                    'usercleanup',
                    'clientupdater',
                    'hostregister'
                ]
            );
            $globalInfo = self::getGlobalModuleStatus();
            $globalDisabled = [];
            foreach ((array)$globalInfo as $key => $en) {
                if (in_array($key, $igMods)) {
                    continue;
                }
                if (!$en) {
                    $globalDisabled[] = $key;
                }
            }
            self::getHostItem(
                true,
                false,
                false,
                false,
                self::$newService || self::$json
            );
            $hostModules = Route::getIds(
                'module',
                ['id' => self::$Host->get('modules')],
                'shortName'
            );
            $hostEnabled = array_diff(
                (array)$hostModules,
                (array)$igMods
            );
            $hostDisabled = array_diff(
                (array)$globalModules,
                (array)$hostEnabled
            );
            $array = [];
            foreach ($globalModules as $index => $key) {
                switch ($key) {
                    case 'greenfog':
                        $class='GF';
                        continue 2;
                    case 'powermanagement':
                        $class='PM';
                        break;
                    case 'printermanager':
                        $class='PrinterClient';
                        break;
                    case 'taskreboot':
                        $class='Jobs';
                        break;
                    case 'usertracker':
                        $class='UserTrack';
                        break;
                    default:
                        $class=$key;
                }
                $disabled = in_array(
                    $key,
                    self::fastmerge(
                        (array)$globalDisabled,
                        (array)$hostDisabled
                    )
                );
                if ($disabled) {
                    if (in_array($key, $globalDisabled)) {
                        $array[$key]['error'] = 'ng';
                    } elseif (in_array($key, $hostDisabled)) {
                        $array[$key]['error'] = 'nh';
                    }
                } else {
                    $array[$key] = self::getClass(
                        $class,
                        true,
                        false,
                        false,
                        false,
                        self::$newService || self::$json
                    )->json();
                }
                unset($key);
            }
            //echo json_encode($array, JSON_UNESCAPED_UNICODE);
            self::$HookManager->processEvent(
                'REQUEST_CLIENT_INFO',
                [
                    'repFields' => &$array,
                    'Host' => self::$Host
                ]
            );
            $this->sendData(
                json_encode(
                    $array,
                    JSON_UNESCAPED_UNICODE
                ),
                true,
                $array
            );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }
    /**
     * Clears the Host's AES information. Used
     * by the button to clear fields and reset
     * encryption as well
     *
     * @return void
     */
    public function clearAES()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        global $groupid;
        global $id;
        if (!(is_numeric($groupid) || is_numeric($id))) {
            return;
        }
        if ($id < 1 && $groupid < 1) {
            return;
        }
        if ($groupid < 1) {
            $hosts = $id;
        } else {
            $hosts = self::getClass('Group', $groupid)
                ->get('hosts');
        }
        self::getClass('HostManager')
            ->update(
                ['id' => $hosts],
                '',
                [
                    'pub_key' => '',
                    'sec_tok' => '',
                    'sec_time' => '0000-00-00 00:00:00'
                ]
            );
        $this->jsonSend(HTTPResponseCodes::HTTP_ACCEPTED, json_encode(
            [
                'msg' => _('Encryption Data Reset'),
                'title' => _('Reset Encryption Success')
            ]
        ));
    }
    /**
     * Clears group Powermanagement tasks
     *
     * @return void
     */
    public function clearPMTasks()
    {
        self::checkAuthAndCSRF();
        global $groupid;
        if (!is_numeric($groupid)) {
            return;
        }
        if ($groupid < 1) {
            return;
        }
        $hosts = self::getClass('Group', $groupid)
            ->get('hosts');
        if (count($hosts ?: [])) {
            Route::deletemass(
                'powermanagement',
                ['hostID' => $hosts]
            );
        }
    }
    /**
     * Perform the actual delete
     *
     * @return void
     */
    public function delete()
    {
        global $node;
        header('Content-type: application/json');
        $ucnode = strtoupper($node);
        self::$HookManager->processEvent(
            "{$ucnode}_DELETE_POST",
            [$this->childClass => &$this->obj]
        );

        $serverFault = false;
        try {
            if ($this->obj->get('protected')) {
                throw new Exception(_('Unable to remove protected items'));
            }
            if ($this->obj instanceof Group) {
                if (isset($_POST['andHosts'])) {
                    $del = ['id' => $this->obj->get('hosts')];
                    Route::deletemass(
                        'host',
                        $del
                    );
                    $hcount = Route::getCount(
                        'host',
                        $del
                    );
                    if ($hcount) {
                        $serverFault = true;
                        throw new Exception(_('Failed to remove hosts'));
                    }
                }
            }
            if ($this->obj instanceof Image || $this->obj instanceof Snapin) {
                if (isset($_POST['andFile'])) {
                    if (!$this->obj->deleteFile()) {
                        throw new Exception(_('Unable to delete file data'));
                    }
                }
            }
            if (!$this->obj->destroy()) {
                $serverFault = true;
                throw new Exception(
                    _('Failed to remove')
                    . ': '
                    . Initiator::e($this->obj->get('name'))
                );
            }
            $hook = "{$ucnode}_DELETE_SUCCESS";
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Successfully deleted')
                    . ': '
                    . Initiator::e($this->obj->get('name')),
                    'title' => _('Delete Success')
                ]
            );
        } catch (Exception $e) {
            $hook = "{$ucnode}_DELETE_FAIL";
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Delete Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [$this->childClass => &$this->obj],
            $hook
        );
    }
    /**
     * Perform wakeup stuff
     *
     * @return void
     */
    public function wakeEmUp()
    {
        // This is an inter-node relay (master -> storage nodes) that runs
        // without a user session, so it is gated by the shared node secret
        // rather than checkAuthAndCSRF. No browser flow calls it directly.
        $provided = $_SERVER['HTTP_X_FOG_NODE_SECRET'] ?? null;
        $secret = self::getSetting('FOG_NODE_SECRET');
        if (empty($secret)
            || !is_string($provided)
            || !hash_equals($secret, $provided)
        ) {
            http_response_code(403);
            return;
        }
        $mac = filter_input(INPUT_POST, 'mac');
        if (!$mac) {
            $mac = filter_input(INPUT_GET, 'mac');
        }
        $macs = self::parseMacList($mac);
        if (count($macs ?: []) < 1) {
            return;
        }
        self::getClass('WakeOnLan', implode('|', $macs))->send();
    }
    /**
     * Presents the importer elements
     *
     * @return void
     */
    public function import()
    {
        $this->title = _('Import')
            . ' '
            . $this->childClass
            . ' '
            . _('List');

        $fields = [
            self::makeLabel(
                'col-sm-3 control-label',
                'import',
                _('Import CSV')
                . '<br/>('
                . _('Max Size')
                . ': '
                . ini_get('post_max_size')
                . ')'
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                'import',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'file',
                    '',
                    'file',
                    'import',
                    '',
                    true
                ) . '</span>'
            ) . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                '',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>',
            self::makeLabel(
                'col-sm-3 control-label',
                'csvheader',
                _('First row is a header')
            ) => self::makeInput(
                '',
                'csvheader',
                '',
                'checkbox',
                'csvheader'
            )
            . ' '
            . '<span class="help-block" style="display:inline">'
            . _(
                'Tick if the file\'s first row names the columns. '
                . 'Header rows are auto-detected even when unticked; '
                . 'leave the file header-less to import by column order.'
            )
            . '</span>'
        ];
        $buttons = self::makeButton(
            'import-send',
            _('Import'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'IMPORT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'obj' => self::getClass($this->childClass)
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'import-form',
            Initiator::e($this->formAction),
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _('This page allows you to upload a CSV file into fog.');
        echo ' ';
        echo _('This should ease migration or mass import new items.');
        echo ' ';
        echo _('It will operate based on the fields the area typcially requires.');
        if (count(self::getAssociationConfig($this->childClass)) > 0) {
            echo ' ';
            echo _(
                'An optional last column may list associations to create, '
                . 'using the format "label:value|value;label:value". Values '
                . 'may be ids or names; unknown references are skipped with a '
                . 'warning rather than failing the row.'
            );
            echo ' ';
            echo sprintf(
                _('Supported here: %s.'),
                implode(
                    ', ',
                    array_keys(self::getAssociationConfig($this->childClass))
                )
            );
        }
        echo '</p>';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Returns the association configuration for a given child class.
     *
     * The configuration is keyed by the label used inside the trailing
     * "associations" CSV column. Each entry describes how to resolve a
     * reference and how to apply/read it:
     *   'class'     => class used to resolve referenced items (id/name)
     *   'namefield' => the unique name field on that class (name lookups)
     *   'get'       => item property holding associated ids (export); may
     *                  instead be a callable fn($item) returning names
     *   'apply'     => method on the item that adds an array of ids (import);
     *                  may instead be a callable fn($item, array $ids)
     *
     * Plugins may add or alter entries by registering on the
     * IMPORT_ASSOCIATIONS event. The result is cached per request so the
     * hook only fires once per class.
     *
     * @param string $childClass the class to get associations for
     *
     * @return array
     */
    public static function getAssociationConfig($childClass)
    {
        if (isset(self::$associationConfigs[$childClass])) {
            return self::$associationConfigs[$childClass];
        }
        $config = [];
        switch ($childClass) {
            case 'Host':
                $config = [
                    'groups' => [
                        'class' => 'Group',
                        'namefield' => 'name',
                        'get' => 'groups',
                        'apply' => 'addGroup',
                    ],
                    'snapins' => [
                        'class' => 'Snapin',
                        'namefield' => 'name',
                        'get' => 'snapins',
                        'apply' => 'addSnapin',
                    ],
                    'printers' => [
                        'class' => 'Printer',
                        'namefield' => 'name',
                        'get' => 'printers',
                        'apply' => 'addPrinter',
                    ],
                    'modules' => [
                        'class' => 'Module',
                        'namefield' => 'name',
                        'get' => 'modules',
                        'apply' => 'addModule',
                    ],
                ];
                break;
            case 'Group':
            case 'Printer':
                // Membership/assignment: the hosts belonging to (or assigned
                // to) this item. Hosts must already exist; resolve by id/name.
                $config = [
                    'hosts' => [
                        'class' => 'Host',
                        'namefield' => 'name',
                        'get' => 'hosts',
                        'apply' => 'addHost',
                    ],
                ];
                break;
            case 'Image':
            case 'Snapin':
                $config = [
                    'storagegroups' => [
                        'class' => 'StorageGroup',
                        'namefield' => 'name',
                        'get' => 'storagegroups',
                        'apply' => 'addGroup',
                    ],
                ];
                break;
        }
        self::$HookManager->processEvent(
            'IMPORT_ASSOCIATIONS',
            [
                'childClass' => $childClass,
                'config' => &$config,
            ]
        );
        self::$associationConfigs[$childClass] = $config;
        return $config;
    }
    /**
     * Builds (and caches) the id/name lookup maps for a class.
     *
     * @param string $class     the class to look up
     * @param string $namefield the unique name field on that class
     *
     * @return array [$idSet, $nameToId, $idToName]
     */
    protected static function associationMaps($class, $namefield)
    {
        $key = $class . '|' . $namefield;
        if (isset(self::$associationMaps[$key])) {
            return self::$associationMaps[$key];
        }
        $idSet = [];
        $nameToId = [];
        $idToName = [];
        Route::listem($class, false, true);
        $items = json_decode(Route::getData());
        $items = isset($items->data) ? $items->data : [];
        foreach ($items as &$it) {
            if (!isset($it->id)) {
                continue;
            }
            $id = (string)$it->id;
            $idSet[$id] = true;
            $name = isset($it->{$namefield}) ? (string)$it->{$namefield} : '';
            $idToName[$id] = $name;
            if ($name !== '') {
                $nameToId[strtolower($name)] = (int)$it->id;
            }
            unset($it);
        }
        self::$associationMaps[$key] = [$idSet, $nameToId, $idToName];
        return self::$associationMaps[$key];
    }
    /**
     * Resolves a list of reference tokens to ids, accepting either a numeric
     * id or a (case-insensitive) name. Anything that cannot be resolved is
     * appended to $unresolved so the caller can warn-and-skip.
     *
     * @param string $class      the class to resolve against
     * @param string $namefield  the unique name field on that class
     * @param array  $tokens     the raw tokens from the CSV cell
     * @param array  $unresolved collects tokens that could not be resolved
     *
     * @return array the resolved, de-duplicated ids
     */
    public static function resolveAssociationIds(
        $class,
        $namefield,
        array $tokens,
        array &$unresolved
    ) {
        list($idSet, $nameToId) = self::associationMaps($class, $namefield);
        $ids = [];
        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }
            if (ctype_digit($token) && isset($idSet[$token])) {
                $ids[] = (int)$token;
                continue;
            }
            $nameKey = strtolower($token);
            if (isset($nameToId[$nameKey])) {
                $ids[] = $nameToId[$nameKey];
                continue;
            }
            $unresolved[] = $token;
        }
        return array_values(array_unique($ids));
    }
    /**
     * Maps a list of ids back to names for the given class, dropping any
     * ids that no longer resolve.
     *
     * @param string $class     the class to resolve against
     * @param string $namefield the unique name field on that class
     * @param array  $ids       the ids to map
     *
     * @return array the resolved names
     */
    protected static function associationIdsToNames($class, $namefield, array $ids)
    {
        list(, , $idToName) = self::associationMaps($class, $namefield);
        $names = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if (isset($idToName[$id]) && $idToName[$id] !== '') {
                $names[] = $idToName[$id];
            }
        }
        return $names;
    }
    /**
     * Escapes the structural delimiters in an association value so a name
     * containing ';', ':' or '|' survives the cell format. The escape
     * character is the backslash; it is doubled first so a literal backslash
     * round-trips. Backslashes pass through fputcsv()/fgetcsv() intact, so the
     * escaped value survives both export paths and re-imports unchanged.
     *
     * @param string $value the raw value (an object name)
     *
     * @return string
     */
    public static function escapeAssociationValue($value)
    {
        return str_replace(
            ['\\', ';', ':', '|'],
            ['\\\\', '\\;', '\\:', '\\|'],
            (string)$value
        );
    }
    /**
     * Reverses escapeAssociationValue(): a backslash makes the next character
     * literal, so "\;" becomes ';' and "\\" becomes '\'. A trailing lone
     * backslash is treated as a literal backslash.
     *
     * @param string $token a single escaped token
     *
     * @return string
     */
    protected static function unescapeAssociationValue($token)
    {
        $out = '';
        $len = strlen($token);
        for ($i = 0; $i < $len; $i++) {
            $ch = $token[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $out .= $token[$i + 1];
                $i++;
                continue;
            }
            $out .= $ch;
        }
        return $out;
    }
    /**
     * Splits a string on an unescaped single-character delimiter, leaving any
     * escape sequences intact for a later pass. Walking character-by-character
     * (rather than a regex lookbehind) keeps "\\" — an escaped backslash —
     * from being mistaken for a backslash that escapes the next delimiter.
     *
     * @param string $string the string to split
     * @param string $delim  a single delimiter character
     *
     * @return array the raw (still-escaped) pieces
     */
    private static function splitOnUnescaped($string, $delim)
    {
        $pieces = [];
        $current = '';
        $len = strlen($string);
        for ($i = 0; $i < $len; $i++) {
            $ch = $string[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                // Preserve the escape sequence verbatim for the leaf-level
                // unescape; this level only splits on bare delimiters.
                $current .= $ch . $string[$i + 1];
                $i++;
                continue;
            }
            if ($ch === $delim) {
                $pieces[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $pieces[] = $current;
        return $pieces;
    }
    /**
     * Parses a trailing "associations" CSV cell into a label => tokens map.
     *
     * Format: "groups:1|Lab B;snapins:7zip|5;printers:1"
     *   ';' separates association types, ':' separates the label from its
     *   values, '|' separates individual values (matching the MAC delimiter).
     *   A value may escape any of those delimiters with a backslash (e.g.
     *   "groups:Lab A\|Lab B" is the single name "Lab A|Lab B").
     *
     * @param string $cell the raw cell value
     *
     * @return array
     */
    public static function parseAssociationCell($cell)
    {
        $result = [];
        $cell = trim((string)$cell);
        if ($cell === '') {
            return $result;
        }
        foreach (self::splitOnUnescaped($cell, ';') as $segment) {
            $segment = trim($segment);
            if ($segment === '' || strpos($segment, ':') === false) {
                continue;
            }
            // The label never contains a colon, so the first colon is the
            // label/value boundary; escaped colons in values sit after it and
            // are preserved by the limit-2 explode.
            list($label, $values) = explode(':', $segment, 2);
            $label = strtolower(trim($label));
            if ($label === '') {
                continue;
            }
            $tokens = [];
            foreach (self::splitOnUnescaped($values, '|') as $token) {
                $token = trim(self::unescapeAssociationValue($token));
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
            if (count($tokens) > 0) {
                $result[$label] = $tokens;
            }
        }
        return $result;
    }
    /**
     * Applies the associations described by a CSV cell to an item. Unresolved
     * references and unknown labels are collected in $warnings and skipped;
     * the item itself is never failed by this method.
     *
     * @param array  $config   the association config for the item's class
     * @param object $item     the (already saved) item to associate
     * @param string $cell     the raw associations cell value
     * @param array  $warnings collects human readable warnings
     *
     * @return bool whether any association was applied (a save is needed)
     */
    public static function applyAssociations(
        array $config,
        $item,
        $cell,
        array &$warnings
    ) {
        if (empty($config)) {
            return false;
        }
        $parsed = self::parseAssociationCell($cell);
        if (empty($parsed)) {
            return false;
        }
        $applied = false;
        foreach ($parsed as $label => $tokens) {
            if (!isset($config[$label])) {
                $warnings[] = sprintf(
                    _('Skipped unknown association type "%s"'),
                    $label
                );
                continue;
            }
            $entry = $config[$label];
            $unresolved = [];
            $ids = self::resolveAssociationIds(
                $entry['class'],
                $entry['namefield'],
                $tokens,
                $unresolved
            );
            if (count($unresolved) > 0) {
                $warnings[] = sprintf(
                    _('Skipped unknown %s: %s'),
                    $label,
                    implode(', ', $unresolved)
                );
            }
            if (count($ids) < 1) {
                continue;
            }
            try {
                if (isset($entry['apply']) && is_callable($entry['apply'])) {
                    call_user_func($entry['apply'], $item, $ids);
                } elseif (isset($entry['apply'])) {
                    $item->{$entry['apply']}($ids);
                }
                $applied = true;
            } catch (Exception $e) {
                // Stay lenient: a failed association must not fail the row.
                $warnings[] = sprintf(
                    _('Could not apply %s: %s'),
                    $label,
                    $e->getMessage()
                );
            }
        }
        return $applied;
    }
    /**
     * Builds the trailing "associations" cell for an item on export. Values
     * are emitted as names for portability across servers, so that an export
     * from one FOG server re-imports cleanly on another (unresolved entries
     * simply warn-and-skip on import).
     *
     * @param string $childClass the class being exported
     * @param int    $id         the id of the row being exported
     *
     * @return string
     */
    public static function buildAssociationString($childClass, $id)
    {
        $config = self::getAssociationConfig($childClass);
        if (empty($config) || empty($id)) {
            return '';
        }
        $item = self::getClass($childClass, $id);
        $parts = [];
        foreach ($config as $label => $entry) {
            $names = [];
            if (isset($entry['get']) && is_callable($entry['get'])) {
                $names = (array)call_user_func($entry['get'], $item);
            } elseif (isset($entry['get'])) {
                $ids = (array)$item->get($entry['get']);
                $names = self::associationIdsToNames(
                    $entry['class'],
                    $entry['namefield'],
                    $ids
                );
            }
            $names = array_values(
                array_filter(
                    array_map('strval', $names),
                    'strlen'
                )
            );
            if (count($names) > 0) {
                $escaped = [];
                foreach ($names as $name) {
                    $escaped[] = self::escapeAssociationValue($name);
                }
                $parts[] = $label . ':' . implode('|', $escaped);
            }
        }
        self::$HookManager->processEvent(
            'EXPORT_ASSOCIATIONS',
            [
                'childClass' => $childClass,
                'id' => $id,
                'item' => $item,
                'parts' => &$parts,
            ]
        );
        return implode(';', $parts);
    }
    /**
     * Interprets the first CSV row as a header when appropriate.
     *
     * When $force is true (the user ticked "First row is a header") the row
     * is always treated as a header. Otherwise it is auto-detected: every
     * cell must be non-empty, unique, and a recognised column token.
     * Recognised tokens are the class field keys plus 'primac' (hosts) and
     * 'associations' (where supported).
     *
     * Returns true when the row is a header; $map is filled with
     * lowercased-token => column-index for recognised tokens, and $unknown
     * collects any header cells that were not recognised (only meaningful
     * when $force is true, since auto-detect rejects unknown tokens).
     *
     * @param mixed $row         the first CSV row
     * @param array $validTokens recognised tokens, lowercased
     * @param bool  $force       treat the row as a header unconditionally
     * @param array $map         out: token => column index
     * @param array $unknown     out: unrecognised header cells
     *
     * @return bool
     */
    protected static function parseCsvHeader(
        $row,
        array $validTokens,
        $force,
        array &$map,
        array &$unknown
    ) {
        $map = [];
        $unknown = [];
        if (!is_array($row)) {
            return false;
        }
        $cells = array_map(
            function ($c) {
                return strtolower(trim((string)$c));
            },
            $row
        );
        if (!$force) {
            // Auto-detect: bail unless the whole row looks like column names.
            if (in_array('', $cells, true)
                || count(array_unique($cells)) !== count($cells)
            ) {
                return false;
            }
            foreach ($cells as $cell) {
                if (!in_array($cell, $validTokens, true)) {
                    return false;
                }
            }
        }
        foreach ($cells as $idx => $cell) {
            if ($cell === '') {
                continue;
            }
            if (in_array($cell, $validTokens, true)) {
                // First occurrence wins if a token is duplicated.
                if (!array_key_exists($cell, $map)) {
                    $map[$cell] = $idx;
                }
            } else {
                $unknown[] = $cell;
            }
        }
        return true;
    }
    /**
     * Perform the import based on the uploaded file
     *
     * @return void
     */
    public function importPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'IMPORT_POST'
        );
        $serverFault = false;
        try {
            $mimes = [
                'text/csv',
                'text/anytext',
                'text/comma-separated-values',
                'application/csv',
                'application/excel',
                'application/vnd.msexcel',
                'application/vnd.ms-excel',
            ];
            $fileinfo = pathinfo($_FILES['file']['name']);
            $ext = $fileinfo['extension'];
            $Item = new $this->childClass();
            $mime = $_FILES['file']['type'];
            if (!in_array($mime, $mimes)) {
                if ($ext !== 'csv') {
                    self::redirect($this->formAction);
                }
            }
            if ($_FILES['file']['error'] > 0) {
                $serverFault = true;
                throw new Exception($_FILES['file']['error']);
            }
            $tmpf = pathinfo($_FILES['file']['tmp_name']);
            $file = sprintf(
                '%s%s%s',
                $tmpf['dirname'],
                DS,
                $tmpf['basename']
            );
            if (!file_exists($file)) {
                throw new Exception(_('Could not find temp filename'));
            }
            $numSuccess = $numFailed = $numAlreadExist = 0;
            $uploadErrors = '';
            $fh = fopen($file, 'rb');
            self::arrayRemove(
                'id',
                $this->databaseFields
            );
            $comma_count = count(array_keys($this->databaseFields) ?: []);
            $iterator = 0;
            $isHost = $Item instanceof Host;
            if ($isHost) {
                $comma_count++;
                $iterator = 1;
            }
            // The optional, trailing "associations" column lives in the slot
            // right after every regular field, regardless of class. When the
            // class supports associations we allow that one extra column.
            $assocConfig = self::getAssociationConfig($this->childClass);
            $hasAssoc = count($assocConfig) > 0;
            $assocIndex = $comma_count;
            $maxCols = $comma_count + ($hasAssoc ? 1 : 0);
            $dbkeys = array_keys($this->databaseFields);

            // Recognised header tokens (lowercased) for this class: the field
            // keys, plus 'primac' for hosts and 'associations' where supported.
            $headerTokens = array_map('strtolower', $dbkeys);
            if ($isHost) {
                $headerTokens[] = 'primac';
            }
            if ($hasAssoc) {
                $headerTokens[] = 'associations';
            }

            // Optional header row: forced via the form checkbox, otherwise
            // auto-detected. When present, columns are mapped by name (any
            // order, partial sets allowed); otherwise the legacy positional
            // order is used.
            $forceHeader = (bool)filter_input(INPUT_POST, 'csvheader');
            $headerMode = false;
            $headerMap = [];
            $headerCols = 0;
            $firstRow = fgetcsv($fh, 1000, ',');
            if ($firstRow !== false) {
                $unknownHeaders = [];
                if (self::parseCsvHeader(
                    $firstRow,
                    $headerTokens,
                    $forceHeader,
                    $headerMap,
                    $unknownHeaders
                )) {
                    $headerMode = true;
                    $headerCols = count($firstRow);
                    if (count($unknownHeaders) > 0) {
                        $uploadErrors .= sprintf(
                            '%s: %s<br/>',
                            _('Ignored unknown header columns'),
                            implode(', ', $unknownHeaders)
                        );
                    }
                    // Required identity columns must be present.
                    $required = ['name'];
                    if ($isHost) {
                        $required[] = 'primac';
                    }
                    foreach ($required as $req) {
                        if (!array_key_exists($req, $headerMap)) {
                            throw new Exception(
                                sprintf(
                                    _('Header is missing the required "%s" column'),
                                    $req
                                )
                            );
                        }
                    }
                }
            }

            $ItemMan = $Item->getManager();
            $modules = Route::getIds(
                'module',
                ['isDefault' => [1]]
            );
            $totalRows = 0;
            // When the first row was a header we consumed it above; otherwise
            // it is the first data row and must still be processed.
            $data = $headerMode ? fgetcsv($fh, 1000, ',') : $firstRow;
            while ($data !== false) {
                $importCount = count($data ?: []);
                if ($importCount > 0
                    && (
                        $headerMode ?
                        $importCount > $headerCols :
                        $importCount > $maxCols
                    )
                ) {
                    throw new Exception(
                        _('Invalid data being parsed')
                    );
                }
                try {
                    // Resolve each field's value by name, unifying the header
                    // and positional paths. Fields absent from a header file
                    // are simply left at their defaults.
                    $rowVals = [];
                    if ($headerMode) {
                        foreach ($headerMap as $name => $colIdx) {
                            $rowVals[$name] = isset($data[$colIdx]) ?
                                $data[$colIdx] :
                                '';
                        }
                    } else {
                        if ($isHost) {
                            $rowVals['primac'] = isset($data[0]) ? $data[0] : '';
                        }
                        foreach ($dbkeys as $i => $f) {
                            $idx = $i + $iterator;
                            $rowVals[strtolower($f)] = isset($data[$idx]) ?
                                $data[$idx] :
                                '';
                        }
                        if ($hasAssoc) {
                            $rowVals['associations'] = isset($data[$assocIndex]) ?
                                $data[$assocIndex] :
                                '';
                        }
                    }

                    if ($isHost) {
                        $macs = self::parseMacList($rowVals['primac']);
                        self::$Host = $Item;
                        self::getClass('HostManager')
                            ->getHostByMacAddresses($macs);
                        if (self::$Host->isValid()) {
                            throw new Exception(
                                _('One or more macs are associated with a host')
                            );
                        }
                        $primac = array_shift($macs);
                        if (array_key_exists('productkey', $rowVals)) {
                            $pk = $rowVals['productkey'];
                            $test_encryption = self::aesdecrypt($pk);
                            $test_base64 = mb_detect_encoding(
                                $test_encryption,
                                'utf-8',
                                true
                            );
                            if ($test_base64 = base64_decode($pk)) {
                                if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                                    $rowVals['productkey'] = $test_base64;
                                }
                            } elseif ($test_base64) {
                                $rowVals['productkey'] = $test_encryption;
                            }
                        }
                    }
                    if ($ItemMan->exists($rowVals['name'])) {
                        throw new Exception(
                            _('This host already exists')
                        );
                    }
                    foreach ((array)$dbkeys as &$field) {
                        $lc = strtolower($field);
                        if (!array_key_exists($lc, $rowVals)) {
                            // Header file that omitted this column: keep default.
                            continue;
                        }
                        if ($field == 'password') {
                            $Item->set($field, $rowVals[$lc], true);
                        } else {
                            $Item->set($field, $rowVals[$lc]);
                        }
                        unset($field);
                    }
                    if ($isHost) {
                        $Item
                            ->set('modules', $modules)
                            ->addPriMAC($primac)
                            ->addMAC($macs);
                    }
                    if ($Item->save()) {
                        $Item->load();
                        $totalRows++;
                        // Apply any associations (lenient: warn and skip
                        // unresolved references rather than failing the row).
                        // The item already has an id at this point.
                        if ($hasAssoc
                            && isset($rowVals['associations'])
                            && trim((string)$rowVals['associations']) !== ''
                        ) {
                            $assocWarnings = [];
                            $applied = self::applyAssociations(
                                $assocConfig,
                                $Item,
                                $rowVals['associations'],
                                $assocWarnings
                            );
                            if ($applied) {
                                $Item->save();
                                $Item->load();
                            }
                            foreach ($assocWarnings as &$assocWarning) {
                                $uploadErrors .= sprintf(
                                    '%s #%s: %s<br/>',
                                    _('Row'),
                                    $totalRows,
                                    $assocWarning
                                );
                                unset($assocWarning);
                            }
                        }
                        $itemCap = strtoupper($this->childClass);
                        $event = sprintf(
                            '%s_IMPORT',
                            $itemCap
                        );
                        $arr = [
                            'data' => &$data,
                            $this->childClass => &$Item
                        ];
                        self::$HookManager->processEvent(
                            $event,
                            $arr
                        );
                        $numSuccess++;
                        $Item = new $this->childClass();
                    } else {
                        $numFailed++;
                    }
                } catch (Exception $e) {
                    $numFailed++;
                    $uploadErrors .= sprintf(
                        '%s #%s: %s<br/>',
                        _('Row'),
                        $totalRows,
                        $e->getMessage()
                    );
                }
                $data = fgetcsv($fh, 1000, ',');
            }
            fclose($fh);
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'IMPORT_SUCCESS';
            // Rows can succeed while still carrying association warnings, so
            // surface $uploadErrors whenever it has content, not just when a
            // whole row failed.
            $hasWarnings = trim($uploadErrors) !== '';
            $msg = json_encode(
                [
                    $hasWarnings ? 'warning' : 'msg' => (
                        $hasWarnings ?
                        $uploadErrors :
                        _('All items imported successfully')
                    ),
                    'title' => (
                        $numFailed > 0 ?
                        _('Import Partially Succeeded') :
                        (
                            $hasWarnings ?
                            _('Import Succeeded With Warnings') :
                            _('Import Succeeded')
                        )
                    )
                ]
            );
        } catch (Exception $e) {
            $error = $e->getMessage();
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'IMPORT_FAILED';
            $msg = json_encode(
                [
                    'error' => $error,
                    'title' => _('Import Failed')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Build select form in generic form.
     *
     * @param string $name           The name of the select item.
     * @param array  $items          The items to generate.
     * @param string $selected       The item to select.
     * @param bool   $useidsel       Use id of array as selector/value.
     * @param string $addClass       Add additional Classes.
     * @param bool   $addidtodisplay Add the id to the display.
     *
     * @return string
     */
    public static function selectForm(
        $name,
        $items = [],
        $selected = '',
        $useidsel = false,
        $addClass = '',
        $addidtodisplay = false
    ) {
        ob_start();
        printf(
            '<select class="form-control'
            . (
                $addClass ?
                " $addClass" :
                ''
            )
            . '" id="%s" name="%s">'
            . '<option value="">- %s -</option>',
            $name,
            $name,
            _('Please select an option')
        );
        foreach ($items as $id => &$item) {
            printf(
                '<option value="%s"%s>%s</option>',
                (
                    $useidsel ?
                    Initiator::e($id) :
                    Initiator::e($item)
                ),
                (
                    $useidsel ? (
                        $id == $selected ?
                        ' selected' :
                        ''
                    ) : (
                        $item == $selected ?
                        ' selected' :
                        ''
                    )
                ),
                (
                    $addidtodisplay ?
                    Initiator::e($item) . ' - (' . Initiator::e($id) . ')' :
                    Initiator::e($item)
                )
            );
            unset($item);
        }
        echo '</select>';
        return ob_get_clean();
    }
    /**
     * Displays "add" powermanagement item
     *
     * @param bool $ondemand Whether this is a cron or ondemand task.
     *
     * @return void
     */
    public function newPMDisplay($ondemand = false)
    {
        global $node;

        $action = filter_input(INPUT_POST, 'action');

        $labelClass = 'col-sm-3 control-label';

        $actionSelector = self::getClass('PowerManagementManager')->getActionSelect(
            $action,
            false,
            'action'
            . (int)$ondemand
        );

        if ($ondemand) {
            // New data
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'action' . (int)$ondemand,
                    _('Action')
                ) => $actionSelector
            ];

            self::$HookManager->processEvent(
                sprintf(
                    '%s_POWERMANAGEMENT_ONDEMAND_FIELDS',
                    strtoupper($this->node)
                ),
                [
                    'fields' => &$fields,
                    'obj' => $this->obj
                ]
            );
        } else {
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'action',
                    _('Action')
                ) => $actionSelector,
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Schedule Power')
                ) => '<div class="fogcron"></div><br/>'
                . self::makeInput(
                    'col-sm-2 croninput cronmin',
                    'scheduleCronMin',
                    _('min'),
                    'text',
                    'cronMin'
                )
                . self::makeInput(
                    'col-sm-2 croninput cronhour',
                    'scheduleCronHour',
                    _('hour'),
                    'text',
                    'cronHour'
                )
                . self::makeInput(
                    'col-sm-2 croninput crondom',
                    'scheduleCronDOM',
                    _('day'),
                    'text',
                    'cronDom'
                )
                . self::makeInput(
                    'col-sm-2 croninput cronmonth',
                    'scheduleCronMonth',
                    _('month'),
                    'text',
                    'cronMonth'
                )
                . self::makeInput(
                    'col-sm-2 croninput crondow',
                    'scheduleCronDOW',
                    _('weekday'),
                    'text',
                    'cronDow'
                ),
            ];

            self::$HookManager->processEvent(
                sprintf('%s_POWERMANAGEMENT_CRON_FIELDS', strtoupper($this->node)),
                [
                    'fields' => &$fields,
                    'obj' => $this->obj
                ]
            );
        }
        $rendered = self::formFields($fields);
        unset($fields);

        ob_start();
        echo self::makeFormTag(
            'form-horizontal',
            $node
            . '-powermanagement-'
            . ($ondemand ? 'instant' : 'cron')
            . '-form',
            self::makeTabUpdateURL(
                $node . '-powermanagement',
                Initiator::e($this->obj->get('id'))
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo self::makeInput(
            '',
            'pmadd' . ($ondemand ? 'od' : ''),
            '',
            'hidden',
            '',
            '1'
        );
        echo '</form>';
        return ob_get_clean();
    }
    /**
     * Index page is already common, but other pages
     * might want to do similar after minor changes. This allows
     * it to happen.
     *
     * @param bool        $delNeeded If we need to be able to delete items.
     * @param bool|string $storage   If storage, set node or group.
     * @param bool        $actionbox If we need to label as action box.
     *
     * @return void
     */
    public function indexDivDisplay(
        $delNeeded = false,
        $storage = false,
        $actionbox = false
    ) {
        global $node;
        global $sub;
        ob_start();
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        // Render does not need echo, it's rendering.
        $this->render(12);
        echo '</div>';
        if ($sub == 'list' || !trim($sub)) {
            // Maybe we should make this part a variable and call a method.
            // That method would allow plugins and hooks to generate/remove buttons
            // where/as necessary. As well as simplify our coding needs.
            // I forgot we have no need for "search" anymore?
            echo '<div class="box-footer with-border">';
            if ($node == 'host') {
                // Some generalized button generator code here.
            } else {
                // Some generalized button generator code here.
            }
            // Hook -> process event.
            echo '</div>';
        }
        echo '</div>';
        $items = ob_get_clean();

        // This is where the index div displays, as you know.
        //
        // From the point where list/search table displays comes from the render(12)
        // buttons are built into the "process" which render calls and echos.
        self::$HookManager->processEvent(
            'INDEX_DIV_DISPLAY_CHANGE',
            [
                'items' => &$items,
                'childClass' => &$this->childClass,
                'main' => &$this,
                'delNeeded' => &$delNeeded
            ]
        );
        echo $items;
    }
    /**
     * Build our form elements.
     *
     * @param mixed $fields The fields to use to generate our forms.
     *
     * @return string
     */
    public static function formFields($fields)
    {
        ob_start();
        foreach ($fields as $field => &$input) {
            echo '<div class="form-group">';
            echo $field;
            echo '<div class="col-sm-9">';
            echo $input;
            echo '</div>';
            echo '</div>';
            unset($field, $input);
        }
        return ob_get_clean();
    }
    /**
     * Build a striped table.
     *
     * @param array $fields The fields to build the array from.
     *
     * @return string
     */
    public static function stripedTable($fields)
    {
        ob_start();
        foreach ($fields as $field => &$input) {
            echo '<tr>';
            echo '<th><center>';
            echo $field;
            echo '</center></th>';
            echo '<th>';
            echo $input;
            echo '</th>';
            echo '</tr>';
            unset($field, $input);
        }
        return ob_get_clean();
    }
    /**
     * Build our nav-tabs elements.
     *
     * @param mixed      $tabData The tabs we are going to build out.
     * @param int|object $obj     The object to pass in, -1 = current node + id.
     *
     * @return string
     */
    public static function tabFields($tabData, $obj = -1)
    {
        // Allow commonized tab data hooks.
        global $node;
        global $id;
        // Set the obj to the current node and id field if
        // -1 is the value
        if ($obj === -1) {
            $obj = self::getClass($node, $id);
        }
        if ($obj) {
            self::$HookManager->processEvent(
                'TABDATA_HOOK',
                [
                    'tabData' => &$tabData,
                    'obj' => &$obj
                ]
            );
            if (!$obj->pluginsTabData) {
                $obj->pluginsTabData = [];
            }
            self::$HookManager->processEvent(
                'PLUGINS_INJECT_TABDATA',
                [
                    'pluginsTabData' => &$obj->pluginsTabData,
                    'obj' => &$obj
                ]
            );

            if (count($obj->pluginsTabData ?: [])) {
                $tabData[] = [
                    'tabs' => [
                        'name' => _('Plugins'),
                        'tabData' => $obj->pluginsTabData
                    ]
                ];
            }
        }

        ob_start();
        $activeId = '';
        $dropdown = false;
        echo '<div class="nav-tabs-custom">';
        echo '<ul class="nav nav-tabs">';
        foreach ($tabData as &$entry) {
            if (isset($entry['tabs'])) {
                $name = $entry['tabs']['name'];
                echo '<li class="dropdown">';
                echo '<a class="dropdown-toggle" data-toggle="dropdown" href="#">';
                echo $name;
                echo '<span class="caret"></span>';
                echo '</a>';
                echo '<ul class="dropdown-menu">';
                $tabs = $entry['tabs']['tabData'];
                foreach ($tabs as &$tab) {
                    $name = $tab['name'];
                    $ident = $tab['id'];
                    if (empty($activeId)) {
                        $activeId = $ident;
                    }
                    $isActive = ($activeId === $ident);
                    echo '<li class="'
                        . (
                            $isActive ?
                            'active' :
                            ''
                        )
                        . '">';
                    echo '<a href="#'
                        . $ident
                        . '" data-toggle="tab" ariaexpanded="true">'
                        . $name
                        . '</a>';
                    echo '</li>';
                    unset($tab);
                }
                echo '</ul>';
            } else {
                $name = $entry['name'];
                $ident = $entry['id'];
                if (empty($activeId)) {
                    $activeId = $ident;
                }
                $isActive = ($activeId === $ident);
                echo '<li class="'
                    . (
                        $isActive ?
                        'active' :
                        ''
                    )
                    . '">';
                echo '<a href="#'
                    . $ident
                    . '" data-toggle="tab" ariaexpanded="true">'
                    . $name
                    . '</a>';
                echo '</li>';
            }
            unset($entry);
        }
        echo '</ul>';
        echo '<div class="tab-content">';
        foreach ($tabData as &$entry) {
            if (isset($entry['tabs'])) {
                $tabs = $entry['tabs']['tabData'];
                foreach ($tabs as &$tab) {
                    $generator = $tab['generator'];
                    $name = $tab['name'];
                    $ident = $tab['id'];
                    $isActive = ($activeId === $ident);
                    echo '<div id="'
                        . $ident
                        . '" class="tab-pane '
                        . (
                            $isActive ?
                            'active' :
                            ''
                        )
                        . '">';
                    if (is_callable($generator)) {
                        $generator();
                    }
                    echo '</div>';
                    unset($tab);
                }
            } else {
                $generator = $entry['generator'];
                $name = $entry['name'];
                $ident = $entry['id'];
                $isActive = ($activeId === $ident);
                echo '<div id="'
                    . $ident
                    . '" class="tab-pane '
                    . (
                        $isActive ?
                        'active' :
                        ''
                    )
                    . '">';
                if (is_callable($generator)) {
                    $generator();
                }
                echo '</div>';
            }
            unset($entry);
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }
    /**
     * Shared scaffold for the standard edit() tab pages.
     *
     * Sets the canonical "Edit: <name> ID: <id>" page title from the
     * loaded $this->obj and echoes the assembled tab markup. The page's
     * own edit() keeps building its page-specific $tabData entries and
     * hands the finished array here; the title and the tabFields() echo
     * are the only shared bookends, so they live here.
     *
     * The $obj argument is passed straight through to tabFields() with
     * the same -1 default, preserving its three behaviours: -1 rebuilds
     * the entity from the node/id globals, an explicit object uses it as
     * given, and false skips the TABDATA/plugin hook injection. The page
     * title always derives from $this->obj regardless of $obj.
     *
     * @param array $tabData The page's assembled tab definitions.
     * @param mixed $obj     tabFields() obj arg: -1 (default), an entity,
     *                       or false. Does not affect the title.
     *
     * @return void
     */
    protected function renderEditTabs(array $tabData, $obj = -1)
    {
        $this->title = sprintf(
            '%s: %s %s: %s',
            _('Edit'),
            $this->obj->get('name'),
            _('ID'),
            $this->obj->get('id')
        );
        echo self::tabFields($tabData, $obj);
    }
    /**
     * Function passes so we can have
     * a paged version of universal searching.
     *
     * @return string
     */
    public function unisearch()
    {
        header('Content-type: application/json');
        $search = filter_input(INPUT_POST, 'search');
        if (!$search) {
            $search = filter_input(INPUT_GET, 'search');
        }
        Route::unisearch($search, 5);
    }
    /**
     * Makes a label element.
     *
     * @param string $class The class to give the label.
     * @param string $id    The "fog" identifier.
     * @param string $str   What the label displays as its string.
     * @param string $extra Any extra attributes to append.
     *
     * @return string
     */
    public static function makeLabel(
        $class,
        $id,
        $str,
        $extra = ''
    ) {
        return '<label class="'
            . $class
            . '" for="'
            . $id
            . '"'
            . ($extra ? " $extra" : '')
            . '>'
            . $str
            . '</label>';
    }
    /**
     * Makes an input element.
     *
     * @param string $class        The class to give this input.
     * @param string $name         The name to give this input.
     * @param string $placeholder  A placeholder limit.
     * @param string $type         The type for this input.
     * @param string $id           The id to give this input.
     * @param mixed  $value        The value to assign to this input.
     * @param bool   $required     Is this input required.
     * @param bool   $autocomplete If autoomplete should be on or off.
     * @param int    $minlength    Minimum length of field if required.
     * @param int    $maxlength    Maximum length of field if required.
     * @param string $extra        Any extra attributes to add.
     * @param bool   $readonly     Is this input to be readonly.
     * @param bool   $disabled     Is this input to be disabled.
     *
     * @return string
     */
    public static function makeInput(
        $class,
        $name,
        $placeholder = '',
        $type = 'text',
        $id = '',
        $value = '',
        $required = false,
        $autocomplete = false,
        $minlength = -1,
        $maxlength = -1,
        $extra = '',
        $readonly = false,
        $disabled = false
    ) {
        if (!$id) {
            $id = $name;
        }
        return '<input class="' . $class . '" '
            . 'name="' . $name . '" '
            . 'placeholder="' . $placeholder . '" '
            . 'type="' . $type . '" '
            . 'id="' . $id . '" '
            . 'value="' . Initiator::e($value) . '" '
            . ($required ? 'required ' : '')
            . ($readonly ? 'readonly ' : '')
            . ($disabled ? 'disabled ' : '')
            . 'autocomplete="' . ($autocomplete ? 'on' : 'off') . '"'
            . ($minlength > 0 ? ' minlength="' . $minlength . '"' : '')
            . ($maxlength > 0 ? ' maxlength="' . $maxlength . '"' : '')
            . ($extra ? " $extra" : '')
            . '/>';
    }
    /**
     * Makes information tooltip element.
     *
     * @param string $class     The class to associate with.
     * @param string $id        The id to associate with.
     * @param string $title     The data to present in the tooltip.
     * @param string $extra     Any extra attributes to add.
     */
    public static function makeInfoTooltip(
        $class,
        $id,
        $title,
        $extra = ''
    ) {
        return '<i class="' . $class. '" id="' . $id . '"'
            . ' data-toggle="tooltip"'
            . ' data-placement="left"'
            . ' data-html="true"'
            . ' data-trigger="click"'
            //. ' style="size:+3; color:#337ab7;"'
            . " title='$title'"
            . ($extra ? " $extra" : '')
            . '></i>';
    }
    /**
     * Renders the standard create-form scaffold shared by the add() pages:
     * a CSRF-protected horizontal form wrapping a box-solid that holds one or
     * more titled box-primary sections, with the action buttons in the footer.
     *
     * @param string $idBase   Id prefix; yields form id "<idBase>-create-form"
     *                         and container id "<idBase>-create".
     * @param array  $sections List of [title, body] pairs; each renders as a
     *                         box-primary (one for most pages, two for hosts).
     * @param string $buttons  Pre-rendered footer buttons.
     * @param string $enctype  Form enctype (default urlencoded; snapin uses
     *                         multipart/form-data).
     *
     * @return void
     */
    protected function renderCreateForm(
        $idBase,
        array $sections,
        $buttons,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        echo self::makeFormTag(
            'form-horizontal',
            $idBase . '-create-form',
            $this->formAction,
            'post',
            $enctype,
            true
        );
        echo '<div class="box box-solid" id="' . $idBase . '-create">';
        echo '<div class="box-body">';
        foreach ($sections as $section) {
            echo '<div class="box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo $section[0];
            echo '</h4>';
            echo '</div>';
            echo '<div class="box-body">';
            echo $section[1];
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Renders the standard "Create New X" page form.
     *
     * Wraps the near-identical add() body shared by nearly every management
     * page: set the page title, build the create-form fields via _addFields(),
     * add the uniform Create button, fire the page's *_ADD_FIELDS hook (with
     * the fields, buttons, and the entity class in the payload), then hand the
     * single titled section off to renderCreateForm().
     *
     * The section title shown above the fields is the same text as the page
     * title, matching every page that used this template.
     *
     * @param string      $idBase      renderCreateForm id base (e.g. 'group')
     * @param string      $title       page + section title (already _()'d)
     * @param string      $hookEvent   the *_ADD_FIELDS event name to fire
     * @param string      $entityKey   payload key for the entity class
     * @param string|null $entityClass class to instantiate (defaults to key)
     * @param string      $enctype     form enctype, default urlencoded
     *
     * @return void
     */
    protected function renderAddForm(
        $idBase,
        $title,
        $hookEvent,
        $entityKey,
        $entityClass = null,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        $this->title = $title;

        $fields = $this->_addFields();

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            $hookEvent,
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                $entityKey => self::getClass($entityClass ?? $entityKey)
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderCreateForm(
            $idBase,
            [[$title, $rendered]],
            $buttons,
            $enctype
        );
    }
    /**
     * Renders the standard create form fragment used inside the "add" modal.
     *
     * Wraps the near-identical addModal() body shared by nearly every
     * management page: build the create-form fields via _addFields(), fire the
     * page's *_ADD_FIELDS hook (fields + entity class, no buttons), then echo a
     * bare form tag, the rendered fields, and the closing tag.
     *
     * @param string      $node        URL node for the form action target
     * @param string      $hookEvent   the *_ADD_FIELDS event name to fire
     * @param string      $entityKey   payload key for the entity class
     * @param string|null $entityClass class to instantiate (defaults to key)
     * @param string      $enctype     form enctype, default urlencoded
     *
     * @return void
     */
    protected function renderAddModalForm(
        $node,
        $hookEvent,
        $entityKey,
        $entityClass = null,
        $enctype = 'application/x-www-form-urlencoded'
    ) {
        $fields = $this->_addFields();

        self::$HookManager->processEvent(
            $hookEvent,
            [
                'fields' => &$fields,
                $entityKey => self::getClass($entityClass ?? $entityKey)
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=' . $node . '&sub=add',
            'post',
            $enctype,
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Builds the schedule-type form fields shared by the host/group deploy()
     * create-task forms: the always-present "Schedule Immediately" radio plus,
     * unless this is a debug or password-reset task, the "Schedule Later"
     * (single) and "Schedule Crontab Style" (cron) inputs.
     *
     * @param string $labelClass The label class used by the deploy form.
     * @param bool   $isdebug    Whether this is a debug-session task.
     * @param int    $type       The task type id (to suppress for resets).
     *
     * @return array The field fragment to fastmerge onto the form fields.
     */
    protected function scheduleTypeFields($labelClass, $isdebug, $type)
    {
        $fields = [
            self::makeLabel(
                $labelClass,
                'instant',
                _('Schedule Immediately')
            ) => self::makeInput(
                'instant',
                'scheduleType',
                '',
                'radio',
                'instant',
                'instant',
                false,
                false,
                -1,
                -1,
                ' checked'
            )
        ];
        if (!$isdebug
            && TaskType::PASSWORD_RESET != $type
        ) {
            $fields = self::fastmerge(
                $fields,
                [
                    '<div class="hideFromDebug">'
                    . self::makeLabel(
                        $labelClass,
                        'delayed',
                        _('Schedule Later')
                    ) => self::makeInput(
                        'delayed',
                        'scheduleType',
                        '',
                        'radio',
                        'delayed',
                        'single'
                    )
                    . '</div>',
                    '<div class="delayedinput hidden">'
                    . self::makeLabel(
                        $labelClass,
                        'delayedinput',
                        _('Start Time')
                    ) => self::makeInput(
                        'form-control',
                        'scheduleSingleTime',
                        self::niceDate()->format('Y-m-d H:i:s'),
                        'text',
                        'delayedinput',
                        ''
                    )
                    . '</div>',
                    '<div class="hideFromDebug">'
                    . self::makeLabel(
                        $labelClass,
                        'cron',
                        _('Schedule Crontab Style')
                    ) => self::makeInput(
                        'croninput',
                        'scheduleType',
                        '',
                        'radio',
                        'cron',
                        'cron'
                    )
                    . '</div>',
                    '<div class="croninput hidden">'
                    . self::makeLabel(
                        $labelClass,
                        '',
                        _('Cron Entry')
                    ) => '<div class="croninput fogcron hidden"></div><br/>'
                    . self::makeInput(
                        'col-sm-2 croninput cronmin hidden',
                        'scheduleCronMin',
                        _('min'),
                        'text',
                        'cronMin'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput cronhour hidden',
                        'scheduleCronHour',
                        _('hour'),
                        'text',
                        'cronHour'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput crondom hidden',
                        'scheduleCronDOM',
                        _('day'),
                        'text',
                        'cronDom'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput cronmonth hidden',
                        'scheduleCronMonth',
                        _('month'),
                        'text',
                        'cronMonth'
                    )
                    . self::makeInput(
                        'col-sm-2 croninput crondow hidden',
                        'scheduleCronDOW',
                        _('weekday'),
                        'text',
                        'cronDow'
                    )
                    . '</div>'
                ]
            );
        }
        return $fields;
    }
    /**
     * Validates the posted schedule type for the host/group deploy() create-task
     * handlers and resolves its parameters. Honors the SCHEDULE_TYPES hook,
     * rejects an unknown type, a past single-run time, or an invalid cron field.
     *
     * @throws Exception If the schedule type or any cron/time field is invalid.
     *
     * @return array Keyed: scheduleType, scheduleDeployTime (single), and
     *               min/hour/dom/month/dow (cron); unused entries are null.
     */
    protected function validateScheduleType()
    {
        $scheduleType = strtolower(
            filter_input(INPUT_POST, 'scheduleType')
        );
        $scheduleTypes = [
            'cron',
            'instant',
            'single'
        ];
        self::$HookManager->processEvent(
            'SCHEDULE_TYPES',
            ['scheduleTypes' => &$scheduleTypes]
        );
        foreach ($scheduleTypes as $ind => &$val) {
            $scheduleTypes[$ind] = trim(
                strtolower(
                    $val
                )
            );
            unset($val);
        }
        if (!in_array($scheduleType, $scheduleTypes)) {
            throw new Exception(_('Invalid scheduling type'));
        }
        $schedule = [
            'scheduleType' => $scheduleType,
            'scheduleDeployTime' => null,
            'min' => null,
            'hour' => null,
            'dom' => null,
            'month' => null,
            'dow' => null
        ];
        // Schedule Delayed/Cron checks.
        switch ($scheduleType) {
            case 'single':
                $scheduleDeployTime = self::niceDate(
                    filter_input(INPUT_POST, 'scheduleSingleTime')
                );
                if ($scheduleDeployTime < self::niceDate()) {
                    throw new Exception(_('Scheduled time is in the past'));
                }
                $schedule['scheduleDeployTime'] = $scheduleDeployTime;
                break;
            case 'cron':
                $min = strval(
                    filter_input(INPUT_POST, 'scheduleCronMin')
                );
                $hour = strval(
                    filter_input(INPUT_POST, 'scheduleCronHour')
                );
                $dom = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOM')
                );
                $month = strval(
                    filter_input(INPUT_POST, 'scheduleCronMonth')
                );
                $dow = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOW')
                );
                $tmin = FOGCron::checkMinutesField($min);
                $thour = FOGCron::checkHoursField($hour);
                $tdom = FOGCron::checkDOMField($dom);
                $tmonth = FOGCron::checkMonthField($month);
                $tdow = FOGCron::checkDOWField($dow);
                if (!$tmin) {
                    throw new Exception(_('Minutes field is invalid'));
                }
                if (!$thour) {
                    throw new Exception(_('Hours field is invalid'));
                }
                if (!$tdom) {
                    throw new Exception(_('Day of Month field is invalid'));
                }
                if (!$tmonth) {
                    throw new Exception(_('Month field is invalid'));
                }
                if (!$tdow) {
                    throw new Exception(_('Day of Week field is invalid'));
                }
                $schedule['min'] = $min;
                $schedule['hour'] = $hour;
                $schedule['dom'] = $dom;
                $schedule['month'] = $month;
                $schedule['dow'] = $dow;
        }
        return $schedule;
    }
    /**
     * Makes the opening form tag.
     *
     * @param string $class      The class to associate this form with.
     * @param string $id         The id to associate this form with.
     * @param string $action     The action (where is the form being submitted to).
     * @param string $method     The method to submit this port.
     * @param string $enctype    Encoding type the form is working with.
     * @param bool   $novalidate Should we stop natural validation.
     * @param string $extra      Any extra attributes to add.
     *
     * @return string
     */
    public static function makeFormTag(
        $class,
        $id,
        $action,
        $method = 'post',
        $enctype = 'application/x-www-form-urlencoded',
        $novalidate = false,
        $extra = ''
    ) {
        return '<form class="' . $class . '" '
            . 'id="' . $id . '" '
            . 'action="' . $action . '" '
            . 'method="' . $method . '" '
            . 'enctype="' . $enctype . '" '
            . ($novalidate ? 'novalidate' : '')
            . ($extra ? " $extra" : '')
            . '>';
    }
    /**
     * Makes textarea element.
     *
     * @param string $class        The class to give this input.
     * @param string $name         The name to give this input.
     * @param string $placeholder  A placeholder limit.
     * @param string $id           The id to give this input.
     * @param mixed  $value        The value to assign to this input.
     * @param bool   $required     Is this input required.
     * @param bool   $autocomplete If autoomplete should be on or off.
     * @param string $extra        Any extra attributes to add.
     * @param bool   $readonly     Is this input to be readonly.
     * @param bool   $disabled     Is this input to be disabled.
     *
     * @return string
     */
    public static function makeTextarea(
        $class,
        $name,
        $placeholder = '',
        $id = '',
        $value = '',
        $required = false,
        $autocomplete = false,
        $extra = '',
        $readonly = false,
        $disabled = false
    ) {
        if (!$id) {
            $id = $name;
        }
        return '<textarea class="' . $class . '" '
            . 'name="' . $name . '" '
            . 'placeholder="' . $placeholder . '" '
            . 'id="' . $id . '" '
            . 'style="resize:vertical;min-height:50px;" '
            . ($required ? 'required ' : '')
            . ($readonly ? 'readonly ' : '')
            . ($disabled ? 'disabled ' : '')
            . 'autocomplete="' . ($autocomplete ? 'on' : 'off') . '"'
            . ($extra ? " $extra" : '')
            . '>'
            . Initiator::e($value)
            . '</textarea>';
    }
    /**
     * Gets our special cron types and values.
     *
     * @return void
     */
    public function getSpecialCrons()
    {
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'types' => [
                    _('Select a cron type'),
                    _('Yearly') . '/' . _('Annually'),
                    _('Monthly'),
                    _('Weekly'),
                    _('Daily') . '/' . _('Midnight'),
                    _('Hourly')
                ],
                'values' => [
                    '',
                    'yearly',
                    'monthly',
                    'weekly',
                    'daily',
                    'hourly'
                ],
                'actiontypes' => [
                    _('Shutdown'),
                    _('Reboot'),
                    _('Wake On Lan')
                ],
                'actionvalues' => [
                    'shutdown',
                    'reboot',
                    'wol'
                ]
            ]
        ));
    }
    /**
     * Returns the kernels.
     *
     * @return void
     */
    public function getKernels()
    {
        header('Content-type: application/json');
        Route::availablekernels();
        echo Route::getData();
        exit;
    }
    /**
     * Returns the initrds.
     *
     * @return void
     */
    public function getInitrds()
    {
        header('Content-type: application/json');
        Route::availableinitrds();
        echo Route::getData();
        exit;
    }
    /**
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->attributes = [];

        $obj = self::getClass($this->childClass . 'Manager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                if ($this->childClass == 'Host') {
                    $this->headerData[] = 'primac';
                }
                continue;
            }
            $this->headerData[] = $common;
            $this->attributes[] = [];
            unset($real);
        }

        // Trailing associations column (groups, snapins, etc.) when supported.
        if (count(self::getAssociationConfig($this->childClass)) > 0) {
            $this->headerData[] = 'associations';
            $this->attributes[] = [];
        }

        $this->title = _('Export '. ucfirst(strtolower($this->childClass)) . 's');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Click "CSV (All)" to export every matching item.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'The "CSV (All)" button exports every item that matches the current '
            . 'search to a CSV file (the whole list, not just what is on screen). '
            . 'The Copy, Excel and Print buttons act only on the rows currently '
            . 'loaded in the table.'
        );
        echo '</p>';
        $this->render(12, strtolower($this->childClass).'-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Build the shared export query pieces and column map used by both
     * getExportList() (paged JSON for the on-screen table) and exportAll()
     * (full CSV download).
     *
     * Prepends the primac column for hosts, appends the trailing associations
     * column where supported, and fires the *_EXPORT_ITEMS hook so plugins can
     * adjust the column set.
     *
     * @return array [$table, $tableID, $columns, $sqlstr, $filterstr, $totalstr]
     */
    private function _buildExportColumns()
    {
        $obj = self::getClass($this->childClass.'Manager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $columns = [];
        $tableID = '';
        if ($this->childClass == 'Host') {
            $columns[] = [
                'db' => 'hmMAC',
                'dt' => 'primac'
            ];
        }
        // Setup our columns for the CSV.
        // Automatically removes the id column.
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        // Trailing associations column. It is computed per row from the item's
        // id (reusing the real id column for the query) and emitted as names
        // for cross-server portability. The id column must stay in the query
        // (no removeFromQuery) so the formatter receives the id value.
        if (count(self::getAssociationConfig($this->childClass)) > 0) {
            $childClass = $this->childClass;
            $columns[] = [
                'db' => $tableID,
                'dt' => 'associations',
                'formatter' => function ($d, $row) use ($childClass) {
                    return self::buildAssociationString($childClass, $d);
                }
            ];
        }
        self::$HookManager->processEvent(
            strtoupper($this->childClass).'_EXPORT_ITEMS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        return [$table, $tableID, $columns, $sqlstr, $filterstr, $totalstr];
    }
    /**
     * Present the export list (paged JSON for the on-screen table).
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $pass_vars = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        list($table, $tableID, $columns, $sqlstr, $filterstr, $totalstr)
            = $this->_buildExportColumns();
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        ));
    }
    /**
     * Stream the full export as a CSV download.
     *
     * Unlike getExportList(), which pages for the on-screen table, this
     * replays the DataTables request (sent on the query string) with no row
     * limit, so every matching record is written. The active search and sort
     * are honoured because the request is passed straight to
     * FOGManagerController::simple(); forcing length=-1 drops the SQL LIMIT.
     *
     * The header row uses the friendly column keys, which are exactly the
     * tokens the CSV importer recognises, so the file round-trips through
     * import unchanged.
     *
     * @return void
     */
    public function exportAll()
    {
        list($table, $tableID, $columns, $sqlstr, $filterstr, $totalstr)
            = $this->_buildExportColumns();
        // The client sends its normal DataTables params (columns/search/order)
        // on the query string; force a full, unpaged result set.
        $request = $_GET;
        $request['start'] = 0;
        $request['length'] = -1;
        $result = FOGManagerController::simple(
            $request,
            $table,
            $tableID,
            $columns,
            $sqlstr,
            $filterstr,
            $totalstr
        );
        $headers = FOGManagerController::pluck($columns, 'dt');
        $filename = strtolower($this->childClass).'s-'.date('Y-m-d').'.csv';
        // Drop the output-sanitising buffer so the CSV is written verbatim.
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: private');
        $fh = fopen('php://output', 'w');
        // UTF-8 BOM so spreadsheet apps read accented characters correctly.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers);
        foreach (($result['data'] ?: []) as $row) {
            $line = [];
            foreach ($headers as $key) {
                $line[] = isset($row[$key]) ? $row[$key] : '';
            }
            fputcsv($fh, $line);
        }
        fclose($fh);
        exit;
    }
}
