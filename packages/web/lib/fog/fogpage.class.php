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
    use FOGPageRender;
    use FOGPagePost;
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
        'task',
        'role',
        'usergroup'
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
     * Per-request bulk cache of resolved association names for export, keyed
     * [childClass][label][parentId] => [name, ...]. Populated by
     * primeAssociationExport() so buildAssociationString() can emit a row's
     * associations without hydrating the object or running per-row queries.
     *
     * @var array
     */
    protected static $associationExportCache = [];
    /**
     * Tracks which [childClass][label] pairs have been bulk-primed for export.
     * A primed label is read from the cache (an absent parent simply has no
     * associations); a non-primed label falls back to the per-row get path.
     *
     * @var array
     */
    protected static $associationExportPrimed = [];
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
            'btn btn-tool',
            'data-lte-toggle="card-collapse"'
        );
        self::$FOGExpandBox = self::makeButton(
            '',
            '<i class="fa fa-plus"></i>',
            'btn btn-tool',
            'data-lte-toggle="card-maximize"'
        );
        self::$FOGCloseBox = self::makeButton(
            '',
            '<i class="fa fa-times"></i>',
            'btn btn-tool',
            'data-lte-toggle="card-remove"'
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
            'usergroup' => [
                _('User Groups'),
                'fa fa-address-book'
            ],
            'role' => [
                _('Roles'),
                'fa fa-key'
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
        $pluginSysOn = (bool)self::getSetting('FOG_PLUGINSYS_ENABLED');
        if ($pluginSysOn) {
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

        // Snapshot the full node list before permission filtering: the
        // unknown-node guard below must still recognize real-but-denied
        // nodes so they reach the dispatch gate for the proper deny
        // response instead of a blind redirect.
        $knownNodes = self::fastmerge(
            array_keys($menu),
            array_keys($hookMenu)
        );

        // Drop main-menu nodes the user lacks view permission for. This is
        // presentation only -- the dispatch gate in FOGPageManager::render()
        // is the actual enforcement.
        foreach (array_keys($menu) as $menuNode) {
            $perm = Authorization::resolvePagePermission($menuNode, '', false);
            if (!Authorization::can($perm)) {
                unset($menu[$menuNode]);
            }
        }
        foreach (array_keys($hookMenu) as $menuNode) {
            $perm = Authorization::resolvePagePermission($menuNode, '', false);
            if (!Authorization::can($perm)) {
                unset($hookMenu[$menuNode]);
            }
        }

        foreach ($hookMenu as $key => &$value) {
            if (array_key_exists($key, $menu)) {
                unset($hookMenu[$key]);
            }
        }

        // The PLUGIN OPTIONS sidebar section renders only when this is set,
        // so it has to mean "this user can see at least one plugin menu
        // entry" -- NOT "this user can administer the plugin system".
        //
        // It used to key on $menu['plugin'], the Plugin Management node,
        // which needs plugin.view: the right to install and remove plugins.
        // A user holding site.view could open ?node=site&sub=list by URL and
        // work in it, but the whole section that links there was hidden, so
        // every plugin page was unreachable by navigation for anyone who was
        // not a plugin administrator.
        //
        // Evaluated after the dedup above, which can empty $hookMenu, so an
        // empty PLUGIN OPTIONS header never renders. $hookMenu is already
        // permission-filtered, so this only ever reveals entries the user may
        // view; the dispatch gate in FOGPageManager::render() remains the
        // enforcement point either way.
        self::$pluginIsAvailable = $pluginSysOn && count($hookMenu ?: []) > 0;

        $knownNodes = self::fastmerge(
            $knownNodes,
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
            && !in_array($node, $knownNodes)
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
        // Purely presentational grouping: some top-level nodes are nested under
        // a synthetic "grouping label" parent that owns no node of its own. The
        // children stay real nodes -- permission filtering, routing and the
        // known-node guard are all handled upstream in buildMainMenuItems();
        // this only changes how the survivors are rendered/nested.
        $groups = self::_menuGroups();
        // child node => group key, limited to children still present (i.e. that
        // survived permission filtering) in this menu.
        $childGroup = [];
        foreach ($groups as $gkey => $g) {
            foreach ($g['children'] as $child) {
                if (array_key_exists($child, $menu)) {
                    $childGroup[$child] = $gkey;
                }
            }
        }
        $renderedGroups = [];
        ob_start();
        foreach ($menu as $link => $title) {
            if (!$node && 'home' == $link) {
                $node = $link;
            }
            // A grouped child renders the whole group at the position of its
            // first present member, then subsequent members are skipped.
            if (isset($childGroup[$link])) {
                $gkey = $childGroup[$link];
                if (isset($renderedGroups[$gkey])) {
                    continue;
                }
                $renderedGroups[$gkey] = true;
                self::_renderMenuGroup($groups[$gkey], $menu);
                continue;
            }
            self::_renderMenuNode($link, $title);
        }
        return ob_get_clean();
    }
    /**
     * Presentational menu groupings: a grouping-label parent that nests a set
     * of otherwise-independent top-level nodes. Keyed by a synthetic group key
     * (NOT a real node); 'children' are real node keys, in display order.
     *
     * @return array
     */
    private static function _menuGroups()
    {
        return [
            'useradmin' => [
                'title'    => _('User Administration'),
                'icon'     => 'fa fa-shield',
                'children' => ['user', 'usergroup', 'role'],
            ],
        ];
    }
    /**
     * Renders a grouping-label parent and nests its present children beneath it.
     *
     * @param array $group The group definition (title/icon/children).
     * @param array $menu  The already permission-filtered menu.
     *
     * @return void
     */
    private static function _renderMenuGroup(array $group, array $menu)
    {
        global $node;
        $children = array_values(
            array_filter(
                $group['children'],
                function ($child) use ($menu) {
                    return array_key_exists($child, $menu);
                }
            )
        );
        if (count($children) < 1) {
            return;
        }
        // Open (and mark active) the group whenever the current node is one of
        // its children so the treeview renders expanded on initial load.
        $groupActive = in_array($node, $children, true);
        echo '<li class="nav-item' . ($groupActive ? ' menu-open' : '') . '">';
        echo '<a class="nav-link' . ($groupActive ? ' active' : '') . '" href="#">';
        echo '<i class="nav-icon ' . $group['icon'] . '"></i>';
        echo '<p>' . $group['title'];
        echo '<i class="nav-arrow fa fa-angle-left"></i>';
        echo '</p>';
        echo '</a>';
        echo '<ul class="nav nav-treeview">';
        foreach ($children as $child) {
            self::_renderMenuNode($child, $menu[$child]);
        }
        echo '</ul>';
        echo '</li>';
    }
    /**
     * Renders a single top-level (or nested) menu node and its sub items.
     *
     * @param string $link  The node key.
     * @param array  $title [0] display title, [1] icon class.
     *
     * @return void
     */
    private static function _renderMenuNode($link, $title)
    {
        global $node;
        global $sub;
        $activelink = ($node == $link);
        $subItems = array_filter(
            self::_buildSubMenuItems($link)
        );
        $hasChildren = count($subItems ?: []) > 0;
        // AL4: parents with children get .nav-item (+ .menu-open when active
        // so the treeview JS renders them expanded on load); the link is a
        // plain .nav-link (active reflects the current node). Leaf items get
        // the .ajax-page-link so ADR-0004's chrome refresh handles the click.
        echo '<li class="nav-item'
            . ($hasChildren && $activelink ? ' menu-open' : '')
            . '">';
        echo '<a '
            . (
                !$hasChildren ?
                'class="nav-link ajax-page-link' . ($activelink ? ' active' : '') . '" ' :
                'class="nav-link' . ($activelink ? ' active' : '') . '" '
            )
            . 'href="'
            . (
                $hasChildren ?
                '#' :
                "../management/index.php?node=$link"
            )
            . '">';
        echo '<i class="nav-icon ' . $title[1] . '"></i>';
        echo '<p>' . $title[0];
        if ($hasChildren) {
            echo '<i class="nav-arrow fa fa-angle-left"></i>';
        }
        echo '</p>';
        echo '</a>';
        if ($hasChildren) {
            echo '<ul class="nav nav-treeview">';
            foreach ($subItems as $subItem => $text) {
                echo '<li class="nav-item">';
                echo '<a class="nav-link ajax-page-link'
                    . ($activelink && $sub == $subItem ? ' active' : '')
                    . '" '
                    . 'href="../management/index.php?node='
                    . $link
                    . '&sub='
                    . $subItem
                    . '">';
                echo '<i class="nav-icon fa fa-circle-o"></i>';
                echo '<p>' . $text . '</p>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</li>';
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
                    // Sits beside the two things it acts on: Secure Boot
                    // signing is a property of the kernel this server serves.
                    // Added here rather than in SubMenuData::subMenu(), which
                    // carries the same 'about' list but never runs -- that
                    // hook sets $active = false, and HookManager only forces
                    // active on files under plugins/.
                    'secureBoot' => _('Secure Boot'),
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
                $menu = [];
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
                    $item = _(ucwords(strtolower($report)));
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

        // Drop sub-menu links the user lacks permission for (add/import ->
        // create, multicast -> task, etc.). Presentation only -- dispatch
        // enforcement lives in FOGPageManager::render().
        foreach (array_keys($menu) as $subKey) {
            $perm = Authorization::resolvePagePermission($node, $subKey, false);
            if (!Authorization::can($perm)) {
                unset($menu[$subKey]);
            }
        }
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
                _('Index page of: %s%s'),
                get_class($this),
                (
                    count($args ?: []) ?
                    sprintf(_(', Arguments = %s'), implode(', ', $args)) :
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
     * Normalises a webroot into the bare path used to build URLs.
     *
     * GH-529: pages used to build server and storage-node URLs with a literal
     * '/fog/', so nothing reached a FOG installed anywhere else. The webroot
     * reaches us in every shape -- '/fog/', 'fog', '/fog', '' -- because it is
     * written by the installer, edited by hand in FOG Settings, and carried
     * per-node in ngmWebroot, so normalise rather than trust the stored form.
     *
     * Returns the bare form ('fog', 'apps/fog') because every caller supplies
     * its own surrounding slashes.
     *
     * Pass a storage node's own webroot when addressing that node -- a node
     * need not share this server's -- and omit it when addressing this server.
     *
     * @param string|null $webroot the node's webroot, or null for this server
     *
     * @return string
     */
    public static function webrootPath($webroot = null)
    {
        $webroot = trim((string)$webroot, '/');
        if ($webroot === '') {
            $webroot = trim((string)self::getSetting('FOG_WEB_ROOT'), '/');
        }
        return $webroot === '' ? 'fog' : $webroot;
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
                '',
                'btn-close',
                'type="button" data-bs-dismiss="alert" aria-label="' . _('Close') . '"'
            );
        }
        echo '<h4>'
            . $title
            . '</h4>';
        echo $body;
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
     * Makes the single pause/resume auto-refresh toggle button.
     *
     * These used to be two buttons with one always disabled, so every pane
     * permanently rendered a dead control. One button that relabels itself
     * means the only thing on screen is the action you can actually take.
     *
     * Both labels ride along as data attributes so gettext stays server-side
     * and the JS only has to swap text. The colour is fixed for the life of
     * the button -- state is carried by the label, not by a colour change --
     * so callers pass whatever class fits their footer (primary when it is
     * the rightmost right-side button, secondary when something else there
     * already holds primary). Wire it up with $.registerReloadToggle().
     *
     * @param string $id    The id of the button.
     * @param string $class The class to associate to the button.
     *
     * @return string
     */
    public static function makeReloadToggle($id, $class = 'btn btn-primary float-end')
    {
        $pause = _('Pause Reload');
        $resume = _('Resume Reload');
        return self::makeButton(
            $id,
            $pause,
            trim($class . ' reload-toggle'),
            'type="button" data-paused="0"'
            . ' data-pause-label="' . Initiator::e($pause) . '"'
            . ' data-resume-label="' . Initiator::e($resume) . '"'
        );
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
        $class = 'secondary',
        $props = ''
    ) {
        ob_start();
        $float = ('left' === $pull) ? 'float-start' : 'float-end';
        echo '<div class="btn-group '
            . $float
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
            . ' dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">';
        echo '<span class="visually-hidden">'
            . _('Toggle Dropdown')
            . '</span>';
        echo '</button>';
        echo '<ul class="dropdown-menu" role="menu">';
        foreach ($dropdownArray as &$dropdown) {
            $divider = isset($dropdown['divider']) ? $dropdown['divider']: '';
            if ($divider) {
                echo '<li><hr class="dropdown-divider"></li>';
            }
            $href = isset($dropdown['href']) ? $dropdown['href'] : '#';
            $did = isset($dropdown['id']) ? ' id="' . $dropdown['id'] . '"' : '';
            $dprops = isset($dropdown['props']) ? ' ' . $dropdown['props'] . ' ' : '';
            $dtext = isset($dropdown['text']) ? $dropdown['text'] : '';
            echo '<li>';
            echo '<a class="dropdown-item" href="'
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
     * @param string $size   Optional modal-dialog size class (e.g. modal-lg).
     *
     * @return string
     */
    public static function makeModal(
        $id,
        $header,
        $body,
        $footer,
        $class = '',
        $type = 'default',
        $size = ''
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
            . '" tabindex="-1">';
        echo '<div class="modal-dialog'
            . ($size ? ' ' . $size : '')
            . '">';
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
                // Tasks are cancelled per-pane, never deleted; the tabbed
                // task page hits sub=list via the no-sub default, so keep
                // the delete actionbox off it.
                if ($node != 'plugin' && $node != 'task') {
                    $actionbox .= self::makeButton(
                        'deleteSelected',
                        _('Delete selected'),
                        'btn btn-danger float-start'
                    );
                    $actionbox .= '<div class="btn-group float-end">';
                    if (method_exists($this, 'addModal')) {
                        if ($node == 'host') {
                            $actionbox .= self::makeButton(
                                'addSelectedToGroup',
                                _('Add selected to group'),
                                'btn btn-secondary'
                            );
                        }
                        $actionbox .= self::makeButton(
                            'createnew',
                            _('Add'),
                            'btn btn-primary float-end'
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
                                'btn btn-outline-secondary float-start',
                                'data-bs-dismiss="modal"'
                            )
                            . self::makeButton(
                                'send',
                                _('Create'),
                                'btn btn-primary float-end'
                            ),
                            '',
                            'primary',
                            'modal-lg'
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
                                'btn btn-outline-secondary float-start',
                                'data-bs-dismiss="modal"'
                            )
                            . self::makeButton(
                                'confirmGroupAdd',
                                _('Add'),
                                'btn btn-outline-secondary float-end'
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
                                'col-form-label',
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
                            'btn btn-outline-secondary float-start',
                            'data-bs-dismiss="modal"'
                        )
                        . self::makeButton(
                            'confirmDeleteModal',
                            _('Delete')
                            . ' {0} '
                            . _('{node}'),
                            'btn btn-outline-secondary float-end'
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
        // Object-scope boundary (optional, plugin-enforced): mass delete is
        // the one generic path that destroys objects by an id array rather
        // than a single URL id, so it cannot rely on the page-manager gate.
        // Airtight: deny the whole batch if any id is out of scope.
        Authorization::requirePageObjectScopeMass($this->node, (array)$remitems);
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

        $labelClass = 'col-sm-3 col-form-label';

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
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $this->childClass . ' ' . _('Active Directory');
            echo '</h4>';
            echo '</div>';
            echo self::makeFormTag(
                '',
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

            echo '  <div class="card-body">';
        }
        echo $rendered;
        if ($ownElement) {
            $buttons = self::makeButton(
                'ad-send',
                _('Update'),
                'btn btn-primary float-end'
            );
            $buttons .= self::makeButton(
                'ad-clear',
                _('Clear Fields'),
                'btn btn-danger float-start'
            );
            echo '</div>';
            echo '<div class="card-footer">';
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
            if (($_SESSION['allow_ajax_kdl'] ?? false)
                && ($_SESSION['dest-kernel-file'] ?? '')
                && ($_SESSION['tmp-kernel-file'] ?? '')
                && ($_SESSION['dl-kernel-file'] ?? '')
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
                    // The transfer's HTTP status was previously discarded, so a
                    // 404, a rate-limit reply or a proxy's error page was
                    // written to disk and treated as the kernel -- and an HTML
                    // error page can easily clear the size floor below. The
                    // status is only reachable through the callback argument;
                    // process() hands it (output, http_code, request).
                    // Redirects are followed (CURLOPT_FOLLOWLOCATION in
                    // _getOptions), so this is the final code, not the 302 that
                    // GitHub answers asset URLs with.
                    $httpCode = 0;
                    self::$FOGURLRequests->process(
                        $_SESSION['dl-kernel-file'],
                        'GET',
                        false,
                        false,
                        false,
                        function ($output, $info) use (&$httpCode) {
                            $httpCode = (int)$info;
                        },
                        $fh
                    );
                    if ($httpCode < 200 || $httpCode > 299) {
                        throw new Exception(
                            sprintf(
                                '%s: %s (HTTP %d)',
                                _('Error'),
                                _('Download Failed'),
                                $httpCode
                            )
                        );
                    }
                    if (!file_exists($_SESSION['tmp-kernel-file'])) {
                        throw new Exception(
                            _('Error: Failed to download kernel')
                        );
                    }
                    $filesize = self::getFilesize(
                        $_SESSION['tmp-kernel-file']
                    );
                    // Parenthesised: `!$filesize > 1048576` parsed as
                    // `(!$filesize) > 1048576`, i.e. a bool against an int,
                    // which is always false -- so a truncated or failed
                    // download sailed straight through to be signed and
                    // shipped to the TFTP server. The sprintf was also one
                    // argument long, swallowing the size it meant to report.
                    if ($filesize < 1048576) {
                        throw new Exception(
                            sprintf(
                                '%s: %s: %s - %s',
                                _('Error'),
                                _('Download Failed'),
                                _('filesize'),
                                $filesize
                            )
                        );
                    }
                    // Shape, not just size. Every FOS kernel asset -- bzImage,
                    // bzImage32 and arm_Image alike -- is built with
                    // CONFIG_EFI_STUB and is therefore a PE/COFF image starting
                    // "MZ"; on arm64 the boot header's first instruction is
                    // deliberately encoded so that it reads as MZ, for exactly
                    // this purpose. One check covers all three, and it is also
                    // the precondition sbsign imposes, so it doubles as "can
                    // this be signed at all" -- better to name the problem here
                    // than to fail inside the signing helper, or to hand Secure
                    // Boot clients something that was never a kernel.
                    if (self::readMagic($_SESSION['tmp-kernel-file'], 2) !== 'MZ') {
                        throw new Exception(
                            sprintf(
                                '%s: %s',
                                _('Error'),
                                _('Downloaded file is not a bootable kernel image')
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
                    // Sign before upload, not after: the TFTP target may be a
                    // remote storage node, and the key never leaves this host.
                    $resigned = self::secureBootSign($tmpfile);
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
                            'msg' => $resigned
                                ? _('File uploaded to storage node and '
                                    . 're-signed for Secure Boot!')
                                : _('File uploaded to storage node!'),
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
     * Reads the first $len bytes of a file, for magic-number checks.
     *
     * Returns '' rather than false on any failure so callers can compare
     * against an expected signature without separately handling unreadable or
     * short files -- '' matches nothing, which is the answer they want anyway.
     *
     * @param string $path file to read
     * @param int    $len  number of leading bytes wanted
     *
     * @return string
     */
    protected static function readMagic($path, $len)
    {
        $magic = @file_get_contents($path, false, null, 0, $len);
        return ($magic === false) ? '' : $magic;
    }
    /**
     * Removes abandoned kernel/initrd download temporaries from $dir.
     *
     * Every download now gets its own file, so nothing overwrites the previous
     * run's leftovers the way the old shared name did. An update abandoned
     * after the download step -- browser closed, session lost -- would
     * otherwise leave a full-size kernel behind permanently, and the Secure
     * Boot staging directory sits on the FOG install's own filesystem, not a
     * distro-swept /tmp. An hour is far past any legitimate download, and a
     * download still in flight keeps its mtime current, so this cannot reap a
     * file that is being written.
     *
     * @param string $dir    directory to sweep
     * @param string $prefix filename prefix identifying our temporaries
     *
     * @return void
     */
    protected static function purgeStaleDownloads($dir, $prefix)
    {
        $cutoff = time() - 3600;
        foreach ((array)glob($dir . DS . $prefix . '*') as $stale) {
            if (is_file($stale) && filemtime($stale) < $cutoff) {
                unlink($stale);
            }
        }
    }
    /**
     * Returns the Secure Boot staging directory, or an empty string when
     * kernel signing is not configured on this server.
     *
     * Signing runs as root through a sudo helper so the web server never needs
     * read access to the private key. That helper only ever touches this one
     * directory, which is why a kernel destined to be signed has to be
     * downloaded here rather than into the system temp directory.
     *
     * @return string
     */
    protected static function secureBootStagingDir()
    {
        // GH-850: the base path is installer-driven, so these must be derived
        // from FOG_BASE_DIR rather than written as /opt/fog literals. The
        // installer places both under $fogprogramdir; hardcoding the default
        // meant that on a server installed anywhere else this returned '' and
        // signing silently never happened -- leaving Secure Boot clients with
        // an unsigned kernel and nothing on the server to say why.
        $helper = FOG_BASE_DIR . DS . 'bin' . DS . 'fog-sign-kernel';
        $stagedir = FOG_BASE_DIR . DS . 'secureboot-staging';
        if (!is_executable($helper) || !is_dir($stagedir)) {
            return '';
        }
        return $stagedir;
    }
    /**
     * Signs a staged kernel for Secure Boot via the root helper.
     *
     * Does nothing when signing is not configured. When it is, a failure is
     * fatal: shipping an unsigned kernel to the TFTP server would stop every
     * Secure Boot client from booting, and it would do so silently, long after
     * whoever ran the update had walked away.
     *
     * @param string $tmpfile the staged kernel
     *
     * @throws Exception
     *
     * @return bool true if the kernel was actually signed, false if signing
     *              is not configured on this server -- lets the caller tell
     *              the admin which one happened rather than a message that
     *              is true either way.
     */
    protected static function secureBootSign($tmpfile)
    {
        $stagedir = self::secureBootStagingDir();
        if (!$stagedir || dirname($tmpfile) !== $stagedir) {
            return false;
        }
        // The helper's fixed target is <stagedir>/kernel and it takes no
        // arguments -- that is precisely the property that stops a compromised
        // web server naming its own key or its own file, so the shared name
        // cannot be made per-request. Instead each download keeps its own
        // private file (see _downloadPost) and borrows the shared name only for
        // the moment it is being signed, serialised here. That window is a
        // single sbsign inside a single request, which is short enough for a
        // lock to cover -- unlike the whole download, which spans three.
        //
        // Renames, not copies: same directory, so they are atomic and free, and
        // the helper still sees a real file it can readlink -f into place.
        $shared = $stagedir . DS . 'kernel';
        $lock = fopen($stagedir . DS . '.sign.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if ($lock !== false) {
                fclose($lock);
            }
            throw new Exception(
                _('Error: Could not lock the Secure Boot staging directory')
            );
        }
        $staged = false;
        try {
            // Overwrites any leftover from a run that died mid-sign, which is
            // what we want: ours is the only kernel anyone is waiting on.
            if (!rename($tmpfile, $shared)) {
                throw new Exception(
                    _('Error: Could not stage the kernel for signing')
                );
            }
            $staged = true;
            $output = array();
            $retVal = 1;
            // escapeshellarg because this is no longer a literal: FOG_BASE_DIR
            // is written by the installer from $fogprogramdir, which an admin
            // may set to a path containing a space. exec() hands the string to
            // a shell, so an unquoted path would split into two arguments and
            // the sudoers rule -- which matches the exact command -- would
            // refuse it.
            $helper = escapeshellarg(
                FOG_BASE_DIR . DS . 'bin' . DS . 'fog-sign-kernel'
            );
            exec("sudo -n {$helper} 2>&1", $output, $retVal);
            if ($retVal !== 0) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        _('Error: Failed to sign the kernel for Secure Boot'),
                        implode(' ', $output)
                    )
                );
            }
        } finally {
            // Hand the file back under the caller's own name whether signing
            // succeeded or not: the caller still owns it, and the shared name
            // has to be free for the next update either way. Guarded on
            // $staged so a failed rename cannot walk off with a leftover file
            // that was never ours.
            if ($staged && file_exists($shared)) {
                rename($shared, $tmpfile);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return true;
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
                    // Same discarded-status problem as kernelfetch(); see the
                    // note there. No magic-number check follows this one: an
                    // initrd is a compressed cpio, not a PE image, and it is
                    // never signed, so there is no equivalent single test that
                    // would not just be guessing at compression formats.
                    $httpCode = 0;
                    self::$FOGURLRequests->process(
                        $_SESSION['dl-initrd-file'],
                        'GET',
                        false,
                        false,
                        false,
                        function ($output, $info) use (&$httpCode) {
                            $httpCode = (int)$info;
                        },
                        $fh
                    );
                    if ($httpCode < 200 || $httpCode > 299) {
                        throw new Exception(
                            sprintf(
                                '%s: %s (HTTP %d)',
                                _('Error'),
                                _('Download Failed'),
                                $httpCode
                            )
                        );
                    }
                    if (!file_exists($_SESSION['tmp-initrd-file'])) {
                        throw new Exception(
                            _('Error: Failed to download initrd')
                        );
                    }
                    $filesize = self::getFilesize(
                        $_SESSION['tmp-initrd-file']
                    );
                    // Parenthesised: `!$filesize > 1048576` parsed as
                    // `(!$filesize) > 1048576`, i.e. a bool against an int,
                    // which is always false -- so a truncated or failed
                    // download sailed straight through to be signed and
                    // shipped to the TFTP server. The sprintf was also one
                    // argument long, swallowing the size it meant to report.
                    if ($filesize < 1048576) {
                        throw new Exception(
                            sprintf(
                                '%s: %s: %s - %s',
                                _('Error'),
                                _('Download Failed'),
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
                'col-form-label',
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
                'col-form-label',
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
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                'confirmDeleteModal',
                _('Delete'),
                'btn btn-outline-secondary float-end'
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
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                "confirm{$item}DeleteModal",
                _('Remove'),
                'btn btn-outline-secondary float-end'
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
            /**
             * Refuse before touching the record when there is no usable
             * session key.
             *
             * authorize() is reachable before login and resolves the host from
             * a request 'mac' alone, which is spoofable by design on an imaging
             * LAN. An absent sym_key decrypts to an empty string without
             * throwing, so a bare "mac=..." POST used to walk all the way to
             * the write below -- rotating sec_tok and blanking pub_key on a
             * host whose sec_tok happened to be empty (freshly registered, or
             * just after a task completed, which clears it). The caller learned
             * nothing, but the real client was stranded on a token the server
             * had thrown away. That is the same LAN-caller lockout Aisle
             * Research reported as 050 / 2.7.3, reached by a different route
             * than the pub_key clear that finding named.
             *
             * aesencrypt() only ever accepts a 256-bit key, so 64 hex
             * characters is the sole value any working client can have sent --
             * anything else already failed further down, just after the
             * destructive write instead of before it.
             */
            if (strlen($key) !== 64) {
                throw new Exception('#!ihc');
            }
            $secTok = (string)self::$Host->get('sec_tok');
            $prevTok = (string)self::$Host->get('prev_sec_tok');
            // hash_equals is hygiene, not the crux -- both operands are
            // strings, so there was no type-juggling bypass.
            $matchesCurrent = $secTok !== ''
                && hash_equals($secTok, (string)$token);
            /**
             * One generation of grace, and why it is needed.
             *
             * The rotation below is committed before the response carrying the
             * new token can reach the client, so any interruption in between
             * left the client holding a token the server had already discarded.
             * There was no way back from that: the client has no #!ist handler,
             * and the clear-pub_key "recovery" this code used to do never
             * worked, because it left sec_tok in place so the very next attempt
             * failed the same comparison. An administrator pressing Reset
             * Encryption Data was the only exit.
             *
             * Accepting the immediately superseded token closes that hole: a
             * client whose reply went missing re-presents it once and is handed
             * the current token again. The cost is that a token stays usable
             * one generation past its replacement, which is why it is retired
             * the moment the client proves it holds the current one.
             *
             * The empty-sec_tok test is not redundant. The grace token only
             * stands in for a current token that exists; without it a record
             * that somehow held a previous token and no current one would
             * skip the rotation below and hand the client an empty token.
             */
            $matchesPrev = $prevTok !== ''
                && $secTok !== ''
                && hash_equals($prevTok, (string)$token);
            // Do NOT clear pub_key here on a mismatch. That let any LAN caller
            // wipe a host's AES session key with no crypto material at all,
            // and it had no protocol purpose. Reported as Aisle 050 / 2.7.3.
            if ($secTok !== ''
                && !$matchesCurrent
                && !$matchesPrev
            ) {
                throw new Exception('#!ist');
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
                    );
                /**
                 * Do not rotate again for a client that is retrying on the
                 * superseded token: it never received the current one, so
                 * issuing a third would strand it exactly as before. Hand back
                 * what is already stored and let it catch up.
                 */
                if (!$matchesPrev) {
                    self::$Host
                        ->set('prev_sec_tok', $secTok)
                        ->set(
                            'sec_tok',
                            self::createSecToken()
                        );
                }
            } elseif ($matchesCurrent && $prevTok !== '') {
                // The client is demonstrably on the current token, so the
                // grace token has done its job and should stop being accepted.
                self::$Host->set('prev_sec_tok', '');
            }
            self::$Host->set('pub_key', $key);
            $vals['token'] = self::$Host->get('sec_tok');
            /**
             * Build the reply BEFORE committing. certEncrypt() can throw, and
             * when it did the rotated token was already in the database while
             * the client still held the old one -- the exact strand this
             * function now has to work to avoid. Nothing is written unless
             * there is something to send back.
             */
            if (self::$json === true) {
                $response = sprintf(
                    '#!en=%s',
                    self::certEncrypt(
                        json_encode($vals)
                    )
                );
            } else {
                $response = sprintf(
                    '#!en=%s',
                    self::certEncrypt(
                        "#!ok\n#token=" . self::$Host->get('sec_tok')
                    )
                );
            }
            self::$Host->save();
            echo $response;
        } catch (Exception $e) {
            /**
             * These must go out as HTTP 200 with the error in the BODY.
             *
             * This is a body-level protocol, not an HTTP-level one: the FOG
             * Client decides what to do next by reading the returned code
             * ('ih' -> re-register via /service/register.php, 'ihc' -> discard
             * the session key and re-handshake, 'ist' -> give up and report).
             * Zazzles' Communication.Post() calls HttpWebRequest::GetResponse(),
             * which throws a WebException on any non-2xx before the body is
             * ever read, so a 401 collapses every one of those codes into an
             * indistinguishable transport failure -- the client logs "Failed to
             * POST data / (401) Unauthorized", returns an empty Response, and
             * stops with "Failed to authenticate, will not run Module Looper."
             * Auto re-registration after a host is deleted stops working too,
             * because the 'ih' the client needs to see never reaches it.
             *
             * 84c678497 changed this to 401 as a tidy-up ("return 401 on auth
             * failure instead of the implicit 200"). It is correct as HTTP and
             * wrong as protocol: no deployed client reads the status, and
             * dev-branch (the 1.5.x line every field client is talking to)
             * still answers 200 here. The status code is part of the client
             * contract and cannot be changed without a coordinated client
             * release. Keep jsonSend for the shared exit path, but send 200.
             */
            if (self::$json === true) {
                if ($e->getMessage() == '#!ihc') {
                    die($e->getMessage());
                }
                $err = str_replace('#!', '', $e->getMessage());
                $this->jsonSend(
                    HTTPResponseCodes::HTTP_SUCCESS,
                    json_encode(['error' => $err])
                );
            }
            if ($e->getMessage() == '#!ist') {
                $this->jsonSend(
                    HTTPResponseCodes::HTTP_SUCCESS,
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
                    // Reset must leave nothing behind that authorize() would
                    // still accept, grace token included.
                    'prev_sec_tok' => '',
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
        // The dispatcher's CSRF gate keys on an Ajax/Post method-name suffix
        // (fogpagemanager.class.php), and "delete" has neither, so this
        // destructive handler was reachable by plain cross-site GET
        // navigation -- which the SameSite=Lax session cookie still rides
        // along on. The method assertion is the load-bearing half: on a GET,
        // CSRF::requireForStateChanging() returns early by design and would
        // never have fired. Every shipped caller already POSTs with a token
        // ($.registerGeneralTab in fog.common.js), so this is transparent to
        // the UI. Deliberately NOT calling checkauth() here -- the edit-page
        // delete modal carries no password field, so with the default
        // FOG_REAUTH_ON_DELETE=1 that would 401 every legitimate delete.
        // Reported by Aisle Research (064 / 3.25.1).
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            header('Content-type: application/json');
            http_response_code(HTTPResponseCodes::HTTP_METHOD_NOT_ALLOWED);
            echo json_encode(
                [
                    'error' => _('Method Not Allowed'),
                    'title' => _('Delete Fail')
                ]
            );
            exit;
        }
        self::checkAuthAndCSRF();
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
            [
                $this->childClass => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
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
                'col-sm-3 col-form-label',
                'import',
                _('Import CSV')
                . '<br/>('
                . _('Max Size')
                . ': '
                . ini_get('post_max_size')
                . ')'
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'import',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'file',
                    '',
                    'file',
                    'import',
                    '',
                    true
                )
            ) . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                'csvfiledisp',
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
                'col-sm-3 col-form-label',
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
            . '<span class="form-text" style="display:inline">'
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
            'btn btn-primary float-end'
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
            '',
            'import-form',
            Initiator::e($this->formAction),
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p class="form-text">';
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
        echo '<div class="card-footer">';
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
                // The bulk* keys let primeAssociationExport() load every host's
                // associations for a class in one query (parentkey IN (...))
                // instead of hydrating each host per row. orderkey preserves the
                // per-row ordering where it is meaningful (snapin sequence).
                $config = [
                    'groups' => [
                        'class' => 'Group',
                        'namefield' => 'name',
                        'get' => 'groups',
                        'apply' => 'addGroup',
                        'bulkclass' => 'GroupAssociation',
                        'parentkey' => 'hostID',
                        'childkey' => 'groupID',
                    ],
                    'snapins' => [
                        'class' => 'Snapin',
                        'namefield' => 'name',
                        'get' => 'snapins',
                        'apply' => 'addSnapin',
                        'bulkclass' => 'SnapinAssociation',
                        'parentkey' => 'hostID',
                        'childkey' => 'snapinID',
                        'orderkey' => 'sequence',
                    ],
                    'printers' => [
                        'class' => 'Printer',
                        'namefield' => 'name',
                        'get' => 'printers',
                        'apply' => 'addPrinter',
                        'bulkclass' => 'PrinterAssociation',
                        'parentkey' => 'hostID',
                        'childkey' => 'printerID',
                    ],
                    'modules' => [
                        'class' => 'Module',
                        'namefield' => 'name',
                        'get' => 'modules',
                        'apply' => 'addModule',
                        'bulkclass' => 'ModuleAssociation',
                        'parentkey' => 'hostID',
                        'childkey' => 'moduleID',
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
     * Returns the foreign-key column configuration for a class: a map of the
     * friendly field key (matching $databaseFields and the CSV header) to the
     * class its id references. This lets a plain numeric FK column be exported
     * as a name and re-imported by id-or-name on another server, mirroring how
     * the associations column achieves cross-server portability.
     *
     * Unlike the associations column these are the object's own scalar fields,
     * so the registry is core-only (no hook event) — no plugin currently adds
     * FK columns. Resolution reuses resolveAssociationIds() on import and
     * associationIdsToNames() on export.
     *
     * @param string $childClass the class to get FK columns for
     *
     * @return array friendlyKey => ['class' => ..., 'namefield' => 'name']
     */
    public static function getFkConfig($childClass)
    {
        switch ($childClass) {
            case 'Host':
                return [
                    'imageID' => ['class' => 'Image', 'namefield' => 'name'],
                ];
            case 'Image':
                return [
                    'osID' => ['class' => 'OS', 'namefield' => 'name'],
                    'imageTypeID' => [
                        'class' => 'ImageType',
                        'namefield' => 'name',
                    ],
                    'imagePartitionTypeID' => [
                        'class' => 'ImagePartitionType',
                        'namefield' => 'name',
                    ],
                ];
            case 'Storagenode':
                // The childClass for the "storagenode" node is
                // ucfirst('storagenode') = 'Storagenode'; the switch is
                // case-sensitive so this must NOT read 'StorageNode'.
                return [
                    'storagegroupID' => [
                        'class' => 'StorageGroup',
                        'namefield' => 'name',
                    ],
                ];
        }
        return [];
    }
    /**
     * Maps a single foreign-key id to its referenced object's name for export.
     * Empty or "0" means "no reference" and stays empty; an id that no longer
     * resolves falls back to the raw id so the value is never silently dropped.
     *
     * @param string $class     the class the id references
     * @param string $namefield the unique name field on that class
     * @param mixed  $id        the foreign-key id value
     *
     * @return string the name (or the raw id as a fallback)
     */
    protected static function fkIdToName($class, $namefield, $id)
    {
        $id = trim((string)$id);
        if ($id === '' || $id === '0') {
            return '';
        }
        $names = self::associationIdsToNames($class, $namefield, [$id]);
        return count($names) > 0 ? $names[0] : $id;
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
        $id = (string)$id;
        // Hydrated lazily: only the per-row fallback path and a registered
        // EXPORT_ASSOCIATIONS listener need the object. Primed labels never do.
        $item = null;
        $parts = [];
        foreach ($config as $label => $entry) {
            $names = [];
            if (!empty(self::$associationExportPrimed[$childClass][$label])) {
                $names = isset(
                    self::$associationExportCache[$childClass][$label][$id]
                ) ? self::$associationExportCache[$childClass][$label][$id] : [];
            } else {
                if ($item === null) {
                    $item = self::getClass($childClass, $id);
                }
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
        // Per-row extension point. Skip it entirely (and the object hydration it
        // requires) unless a listener is actually registered, so a fully primed
        // export stays free of per-row queries.
        if (self::hasHookListener('EXPORT_ASSOCIATIONS')) {
            if ($item === null) {
                $item = self::getClass($childClass, $id);
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
        }
        return implode(';', $parts);
    }
    /**
     * Returns whether a hook event has at least one registered listener. Lets
     * callers avoid expensive work (e.g. hydrating an object) just to fire an
     * event nothing listens to.
     *
     * @param string $event the event name to test
     *
     * @return bool
     */
    protected static function hasHookListener($event)
    {
        return isset(self::$HookManager->data[$event])
            && count((array)self::$HookManager->data[$event]) > 0;
    }
    /**
     * Bulk-loads every association name needed to export a set of rows, so that
     * buildAssociationString() can build each row's cell from cache rather than
     * hydrating the row's object and lazy-loading each relation (the N+1 the
     * per-row path incurs). One IN-query is run per association class plus the
     * already-cached id/name map per referenced class — constant work in the
     * row count instead of ~5 queries per row.
     *
     * Plugins that add labels via IMPORT_ASSOCIATIONS can opt into the same
     * batching by listening for EXPORT_ASSOCIATIONS_PRIME and calling
     * primeAssociationLabel(); any label left unprimed simply falls back to the
     * per-row get path, so this is purely an optimization.
     *
     * @param string $childClass the class being exported
     * @param array  $ids        the ids of the rows being exported
     *
     * @return void
     */
    public static function primeAssociationExport($childClass, array $ids)
    {
        self::$associationExportCache[$childClass] = [];
        self::$associationExportPrimed[$childClass] = [];
        $config = self::getAssociationConfig($childClass);
        if (empty($config) || count($ids) < 1) {
            return;
        }
        foreach ($config as $label => $entry) {
            if (empty($entry['bulkclass'])
                || empty($entry['parentkey'])
                || empty($entry['childkey'])
            ) {
                continue;
            }
            Route::listem(
                $entry['bulkclass'],
                [$entry['parentkey'] => $ids],
                true
            );
            $rows = json_decode(Route::getData());
            $rows = isset($rows->data) ? $rows->data : [];
            $orderkey = isset($entry['orderkey']) ? $entry['orderkey'] : '';
            $byParent = [];
            foreach ($rows as $r) {
                $pid = isset($r->{$entry['parentkey']})
                    ? (string)$r->{$entry['parentkey']} : '';
                $cid = isset($r->{$entry['childkey']})
                    ? (string)$r->{$entry['childkey']} : '';
                if ($pid === '' || $cid === '') {
                    continue;
                }
                $ord = ($orderkey !== '' && isset($r->{$orderkey}))
                    ? (int)$r->{$orderkey} : 0;
                $byParent[$pid][] = [$cid, $ord];
            }
            foreach ($byParent as $pid => $pairs) {
                if ($orderkey !== '') {
                    usort(
                        $pairs,
                        function ($a, $b) {
                            return $a[1] <=> $b[1];
                        }
                    );
                }
                $childIds = array_map(
                    function ($p) {
                        return $p[0];
                    },
                    $pairs
                );
                self::$associationExportCache[$childClass][$label][$pid] =
                    self::associationIdsToNames(
                        $entry['class'],
                        $entry['namefield'],
                        $childIds
                    );
            }
            self::$associationExportPrimed[$childClass][$label] = true;
        }
        self::$HookManager->processEvent(
            'EXPORT_ASSOCIATIONS_PRIME',
            [
                'childClass' => $childClass,
                'ids' => $ids,
            ]
        );
    }
    /**
     * Records the bulk-resolved names for one association label so the export
     * reads them from cache instead of the per-row get path. Plugins call this
     * from an EXPORT_ASSOCIATIONS_PRIME listener after computing, in one query,
     * a map of parent id => names for their label.
     *
     * @param string $childClass the class being exported
     * @param string $label      the association label being primed
     * @param array  $byParentId map of parent id => array of names
     *
     * @return void
     */
    public static function primeAssociationLabel(
        $childClass,
        $label,
        array $byParentId
    ) {
        foreach ($byParentId as $pid => $names) {
            self::$associationExportCache[$childClass][$label][(string)$pid] =
                array_values(
                    array_filter(
                        array_map('strval', (array)$names),
                        'strlen'
                    )
                );
        }
        self::$associationExportPrimed[$childClass][$label] = true;
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
            // Foreign-key columns whose value may be a name (id-or-name on
            // import) so the file is portable between servers.
            $fkConfig = self::getFkConfig($this->childClass);

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
                    $fkWarnings = [];
                    foreach ((array)$dbkeys as &$field) {
                        $lc = strtolower($field);
                        if (!array_key_exists($lc, $rowVals)) {
                            // Header file that omitted this column: keep default.
                            continue;
                        }
                        if (isset($fkConfig[$field])) {
                            // Foreign key: resolve by id first, then name. An
                            // empty value or "0" means "no reference" and is
                            // kept as-is; an unresolved name is lenient — keep
                            // the default and warn (the row still imports).
                            $raw = trim((string)$rowVals[$lc]);
                            if ($raw === '' || $raw === '0') {
                                $Item->set($field, $rowVals[$lc]);
                            } else {
                                $fkUnresolved = [];
                                $fkIds = self::resolveAssociationIds(
                                    $fkConfig[$field]['class'],
                                    $fkConfig[$field]['namefield'],
                                    [$raw],
                                    $fkUnresolved
                                );
                                if (count($fkIds) > 0) {
                                    $Item->set($field, $fkIds[0]);
                                } else {
                                    $fkWarnings[] = sprintf(
                                        _('%s value "%s" did not match any %s '
                                        . 'by id or name; left unset'),
                                        $field,
                                        $raw,
                                        $fkConfig[$field]['class']
                                    );
                                }
                            }
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
                        // An imported image with no storage-group association
                        // has no primary group. Manual creation always assigns
                        // one (addGroup + setPrimaryGroup); the importer did not,
                        // so guarantee it here by associating the lowest storage
                        // group as primary. Without this the image cannot be
                        // viewed afterwards (getStorageGroup has no primary to
                        // resolve).
                        if ($Item instanceof Image
                            && Route::getCount(
                                'imageassociation',
                                ['imageID' => $Item->get('id')]
                            ) < 1
                        ) {
                            $sgid = self::minId(
                                Route::getIds('storagegroup', false) ?: []
                            );
                            if ($sgid > 0) {
                                $Item->addGroup($sgid)->save();
                                Image::setPrimaryGroup($sgid, $Item->get('id'));
                                $Item->load();
                            }
                        }
                        // Surface any lenient foreign-key warnings for this row.
                        foreach ($fkWarnings as &$fkWarning) {
                            $uploadErrors .= sprintf(
                                '%s #%s: %s<br/>',
                                _('Row'),
                                $totalRows,
                                $fkWarning
                            );
                            unset($fkWarning);
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

        $labelClass = 'col-sm-3 col-form-label';

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
                    'action' . (int)$ondemand,
                    _('Action')
                ) => $actionSelector,
                self::makeLabel(
                    $labelClass,
                    'cronMin',
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
            '',
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
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        // Render does not need echo, it's rendering.
        $this->render(12);
        echo '</div>';
        if ($sub == 'list' || !trim($sub)) {
            // Maybe we should make this part a variable and call a method.
            // That method would allow plugins and hooks to generate/remove buttons
            // where/as necessary. As well as simplify our coding needs.
            // I forgot we have no need for "search" anymore?
            echo '<div class="card-footer">';
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
            echo '<div class="row mb-3">';
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
            echo '<th class="text-center">';
            echo $field;
            echo '</th>';
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
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header p-0 border-bottom-0">';
        echo '<ul class="nav nav-tabs" role="tablist">';
        foreach ($tabData as &$entry) {
            if (isset($entry['tabs'])) {
                $name = $entry['tabs']['name'];
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">';
                echo $name;
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
                    echo '<li>';
                    echo '<a class="dropdown-item'
                        . ($isActive ? ' active' : '')
                        . '" href="#'
                        . $ident
                        . '" data-bs-toggle="tab" role="tab" aria-selected="'
                        . ($isActive ? 'true' : 'false')
                        . '">'
                        . $name
                        . '</a>';
                    echo '</li>';
                    unset($tab);
                }
                echo '</ul>';
                echo '</li>';
            } else {
                $name = $entry['name'];
                $ident = $entry['id'];
                if (empty($activeId)) {
                    $activeId = $ident;
                }
                $isActive = ($activeId === $ident);
                echo '<li class="nav-item">';
                echo '<a class="nav-link'
                    . ($isActive ? ' active' : '')
                    . '" href="#'
                    . $ident
                    . '" data-bs-toggle="tab" role="tab" aria-selected="'
                    . ($isActive ? 'true' : 'false')
                    . '">'
                    . $name
                    . '</a>';
                echo '</li>';
            }
            unset($entry);
        }
        echo '</ul>';
        echo '</div>';
        echo '<div class="card-body">';
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
                        . '" class="tab-pane fade'
                        . ($isActive ? ' show active' : '')
                        . '" role="tabpanel">';
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
                    . '" class="tab-pane fade'
                    . ($isActive ? ' show active' : '')
                    . '" role="tabpanel">';
                if (is_callable($generator)) {
                    $generator();
                }
                echo '</div>';
            }
            unset($entry);
        }
        echo '</div>';
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
            . '"'
            . ($id !== '' && $id !== null ? ' for="' . $id . '"' : '')
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
            . ' data-bs-toggle="tooltip"'
            . ' data-bs-placement="left"'
            . ' data-bs-html="true"'
            . ' data-bs-trigger="click"'
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
        // The header row and the column set have to agree: DataTables walks
        // each <th>, looks up aoColumns[i] and raises error 18 "Incorrect
        // column count" for any header with no column behind it. Deriving the
        // headers from _buildExportColumns() rather than walking getColumns()
        // a second time means the two cannot drift, and a plugin that adds or
        // drops a column through {CLASS}_EXPORT_ITEMS moves the matching
        // header with it -- which is how the LDAP bind password stays out of
        // the export without leaving an orphaned <th> behind.
        $this->headerData = [];
        $this->attributes = [];

        list(, , $columns) = $this->_buildExportColumns();
        foreach ($columns as $column) {
            $this->headerData[] = $column['dt'];
            $this->attributes[] = [];
        }

        $this->title = _('Export '. ucfirst(strtolower($this->childClass)) . 's');

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('Click "CSV (All)" to export every matching item.');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p class="form-text">';
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
     * Build the shared export query pieces and column map used by
     * getExportList() (paged JSON for the on-screen table), exportAll()
     * (full CSV download) and export() (the header row).
     *
     * Prepends the primac column for hosts, appends the trailing associations
     * column where supported, and fires the *_EXPORT_ITEMS hook so plugins can
     * adjust the column set. Because export() takes its headers from here, a
     * plugin adding or removing a column through that hook moves the matching
     * <th> with it and the two cannot fall out of step.
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
        $fkConfig = self::getFkConfig($this->childClass);
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $column = [
                'db' => $real,
                'dt' => $common
            ];
            if (isset($fkConfig[$common])) {
                // Emit the referenced object's name (it re-imports by id or
                // name), mirroring the associations column for portability.
                $fk = $fkConfig[$common];
                $column['formatter'] = function ($d, $row) use ($fk) {
                    return self::fkIdToName($fk['class'], $fk['namefield'], $d);
                };
            }
            $columns[] = $column;
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
                // Bulk-load every row's associations once (constant queries)
                // before the formatter runs per row. dataOutput() invokes this
                // with the full result set just before formatting.
                'prime' => function ($rows) use ($childClass, $tableID) {
                    $ids = [];
                    foreach (($rows ?: []) as $r) {
                        if (isset($r[$tableID]) && $r[$tableID] !== '') {
                            $ids[] = $r[$tableID];
                        }
                    }
                    self::primeAssociationExport(
                        $childClass,
                        array_values(array_unique($ids))
                    );
                },
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
