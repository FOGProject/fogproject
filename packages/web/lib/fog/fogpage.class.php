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
            $tabstr = "#$tab";
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
                echo Route::getData();
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
            $this->dataReplace[] = $val;
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
            echo str_replace(
                $this->dataFind,
                $this->dataReplace,
                $template
            );
            echo '</td>';
            unset($template);
        }
        return ob_get_clean();
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
                    Route::ids(
                        $groupassoc,
                        [strtolower($this->childClass).'ID' => $item->id],
                        'storagegroupID'
                    );
                    $storagegroups[$item->$pathKey] = json_decode(Route::getData());
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
        http_response_code($code);
        echo $msg;
        exit;
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
        $retFields = false
    ) {
        global $node;
        global $sub;
        if ($this->obj->isValid()) {
            if (empty($useAD)) {
                $useAD = $this->obj->get('useAD');
            }
            if (empty($ADDomain)) {
                $ADDomain = $this->obj->get('ADDomain');
            }
            if (empty($ADOU)) {
                $ADOU = trim($this->obj->get('ADOU'));
                $ADOU = str_replace(';', '', $ADOU);
            }
            if (empty($ADUser)) {
                $ADUser = $this->obj->get('ADUser');
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
                    self::getSetting('FOG_AD_DEFAULT_OU')
                )
            )
        );
        $ADOU = trim($ADOU);
        $ADOU = str_replace(';', '', $ADOU);
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
                    . $ou
                    . '"'
                    . ($optFound == $ou ? ' selected' : '')
                    . '>'
                    . $ou
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

        $fields = [
            self::makeLabel(
                $labelClass,
                'adEnabled',
                _('Enable Domain Joining')
            ) => self::makeInput(
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
            ),
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
                    $this->obj->get('id')
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
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(
            [
                'domainname' => $domainname,
                'ou' => $ou,
                'domainpass' => $password,
                'domainuser' => $user,
            ]
        );
        exit;
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
                    http_response_code($code);
                    echo json_encode(
                        [
                            'msg' => _('File downloaded!'),
                            'title' => _('Download Complete')
                        ]
                    );
                    exit;
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
                    self::$FOGSSH->sftp_chmod($orig, 0655);
                    self::$FOGSSH->disconnect();
                    if (file_exists($tmpfile)) {
                        unlink($tmpfile);
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    http_response_code($code);
                    echo json_encode(
                        [
                            'msg' => _('File uploaded to storage node!'),
                            'title' => _('Update Kernel Success')
                        ]
                    );
                    exit;
                }
            }
        } catch (Exception $e) {
            http_response_code(HTTPResponseCodes::HTTP_BAD_REQUEST);
            echo json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Kernel Update Fail')
                ]
            );
            exit;
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
                    http_response_code($code);
                    echo json_encode(
                        [
                            'msg' => _('File downloaded!'),
                            'title' => _('Download Complete')
                        ]
                    );
                    exit;
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
                    self::$FOGSSH->sftp_chmod($orig, 0655);
                    self::$FOGSSH->disconnect();
                    if (file_exists($tmpfile)) {
                        unlink($tmpfile);
                    }
                    $code = HTTPResponseCodes::HTTP_SUCCESS;
                    http_response_code($code);
                    echo json_encode(
                        [
                            'msg' => _('File uploaded to storage node!'),
                            'title' => _('Update Initrd Success')
                        ]
                    );
                    exit;
                }
            }
        } catch (Exception $e) {
            http_response_code(HTTPResponseCodes::HTTP_BAD_REQUEST);
            echo json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Initrd Update Fail')
                ]
            );
            exit;
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
            . $this->obj->get('name'),
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
                echo json_encode(
                    ['error' => $err]
                );
                exit;
            }
            if ($e->getMessage() == '#!ist') {
                echo json_encode(
                    ['error' => 'ist']
                );
                exit;
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
            echo json_encode($vals);
            exit;
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
            Route::ids(
                'module',
                ['id' => self::$Host->get('modules')],
                'shortName'
            );
            $hostModules = json_decode(Route::getData(), true);
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
        http_response_code(HTTPResponseCodes::HTTP_ACCEPTED);
        echo json_encode(
            [
                'msg' => _('Encryption Data Reset'),
                'title' => _('Reset Encryption Success')
            ]
        );
        exit;
    }
    /**
     * Clears group Powermanagement tasks
     *
     * @return void
     */
    public function clearPMTasks()
    {
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
                    Route::count(
                        'host',
                        $del
                    );
                    $hcount = json_decode(Route::getData());
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
                    . $this->obj->get('name')
                );
            }
            $hook = "{$ucnode}_DELETE_SUCCESS";
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Successfully deleted')
                    . ': '
                    . $this->obj->get('name'),
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
        self::$HookManager->processEvent(
            $hook,
            [$this->childClass => &$this->obj]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Perform wakeup stuff
     *
     * @return void
     */
    public function wakeEmUp()
    {
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
            . '</div>'
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
            $this->formAction,
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
     * Perform the import based on the uploaded file
     *
     * @return void
     */
    public function importPost()
    {
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
            $fh = fopen($file, 'rb');
            self::arrayRemove(
                'id',
                $this->databaseFields
            );
            $comma_count = count(array_keys($this->databaseFields) ?: []);
            $iterator = 0;
            if ($Item instanceof Host) {
                $comma_count++;
                $iterator = 1;
            }
            $ItemMan = $Item->getManager();
            Route::ids(
                'module',
                ['isDefault' => [1]]
            );
            $modules = json_decode(Route::getData(), true);
            $totalRows = 0;
            while (($data = fgetcsv($fh, 1000, ',')) !== false) {
                $importCount = count($data ?: []);
                if ($importCount > 0
                    && $importCount > $comma_count
                ) {
                    throw new Exception(
                        _('Invalid data being parsed')
                    );
                }
                try {
                    $dbkeys = array_keys($this->databaseFields);
                    if ($Item instanceof Host) {
                        $macs = self::parseMacList($data[0]);
                        self::$Host = $Item;
                        self::getClass('HostManager')
                            ->getHostByMacAddresses($macs);
                        if (self::$Host->isValid()) {
                            throw new Exception(
                                _('One or more macs are associated with a host')
                            );
                        }
                        $primac = array_shift($macs);
                        $index = array_search('productKey', $dbkeys) + 1;
                        $test_encryption = self::aesdecrypt($data[$index]);
                        $test_base64 = mb_detect_encoding(
                            $test_encryption,
                            'utf-8',
                            true
                        );
                        if ($test_base64 = base64_decode($data[$index])) {
                            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                                $data[$index] = $test_base64;
                            }
                        } elseif ($test_base64) {
                            $data[$index] = $test_encryption;
                        }
                    }
                    if ($ItemMan->exists($data[$iterator])) {
                        throw new Exception(
                            _('This host already exists')
                        );
                    }
                    foreach ((array)$dbkeys as $ind => &$field) {
                        $ind += $iterator;
                        if ($field == 'password') {
                            $Item->set($field, $data[$ind], true);
                        } else {
                            $Item->set($field, $data[$ind]);
                        }
                        unset($field);
                    }
                    if ($Item instanceof Host) {
                        $Item
                            ->set('modules', $modules)
                            ->addPriMAC($primac)
                            ->addMAC($macs);
                    }
                    if ($Item->save()) {
                        $Item->load();
                        $totalRows++;
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
            }
            fclose($fh);
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'IMPORT_SUCCESS';
            $msg = json_encode(
                [
                    $numFailed > 0 ? 'warning' : 'msg' => (
                        $numFailed > 0 ?
                        $uploadErrors :
                        _('All items imported successfully')
                    ),
                    'title' => (
                        $numFailed > 0 ?
                        _('Import Partially Succeeded') :
                        _('Import Succeeded')
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
        http_response_code($code);
        echo $msg;
        exit;
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
                    $id :
                    $item
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
                ($addidtodisplay ? $item . ' - (' . $id . ')' : $item)
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
                $this->obj->get('id')
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
            if ($obj->pluginsTabData) {
                self::$HookManager->processEvent(
                    'PLUGINS_INJECT_TABDATA',
                    [
                        'pluginsTabData' => &$obj->pluginsTabData,
                        'obj' => &$obj
                    ]
                );
            }

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
            . 'value="' . filter_var($value, FILTER_SANITIZE_STRING) . '" '
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
            . $value
            . '</textarea>';
    }
    /**
     * Gets our special cron types and values.
     *
     * @return void
     */
    public function getSpecialCrons()
    {
        echo json_encode(
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
        );
        exit;
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

        $this->title = _('Export '. ucfirst(strtolower($this->childClass)) . 's');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched '
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, strtolower($this->childClass).'-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the export list.
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $obj = self::getClass($this->childClass.'Manager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
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
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        );
        exit;
    }
}
