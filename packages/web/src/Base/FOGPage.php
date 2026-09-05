<?php
/**
 * Presents many defaults for the pages and is
 * the calling point by all other page items.
 *
 * PHP version 7.4+
 *
 * @category FOGPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Auth\Authorization;
use FOG\Boot\WakeOnLan;
use FOG\Client\RegisterClient;
use FOG\Items\BootFile;
use FOG\Items\Group;
use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\Site;
use FOG\Items\Snapin;
use FOG\Managers\FileDeleteQueueManager;
use FOG\Managers\HostManager;
use FOG\Managers\PowerManagementManager;
use FOG\Pages\ReportManagement;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
     * Whether this page's grid lets you select and act on rows.
     *
     * The toolbar used to decide by NODE NAME -- a hardcoded list of
     * ['plugin', 'task', 'activity', 'audit'] that a read-only page had to
     * be added to, and which the next one silently was not: Agent Activity
     * shipped drawing a red "Delete selected" over a grid whose own JS sets
     * `select: false` and whose table has no delete route anywhere in FOG
     * (ADR 0021 Decision 8).
     *
     * A page says what it is instead of the toolbar guessing from its URL.
     * The pages that were in that list set this false, and so does any page
     * added later -- forgetting it now means the buttons appear, which is
     * visible, rather than a name missing from a list nobody reads.
     *
     * This is the PHP half. Select All / Deselect All are DataTables Buttons
     * and are dropped by registerTable() when a table passes
     * `select: false`, which is the same statement made in the layer that
     * owns those buttons.
     *
     * @var bool
     */
    public $selectable = true;
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
     * Which form control, if any, each info-card note mirrors.
     *
     * Keyed by the same label as $notes. A value is either a CSS selector for
     * the control, or ['sel' => selector, 'on' => label, 'off' => label] for a
     * checkbox. Purely presentational: renderInfoCard() emits them as data-
     * attributes and the shared JS repaints that note as the control changes,
     * so the card stops disagreeing with the form the moment you edit a field.
     * A note with no entry here simply stays at its server-rendered value.
     *
     * @var array
     */
    public $noteSources = [];
    /**
     * Pre-rendered action buttons for the info card, or ''.
     *
     * Sits to the right of the notes in the same card. Built by a page's
     * edit() -- renderQuickTaskActions() is the only builder today -- and
     * echoed by renderInfoCard(). A string rather than a spec array because
     * the only thing the renderer does with it is echo it, and the pages
     * that set it are already building markup with makeButton().
     *
     * @var string
     */
    public $noteActions = '';
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
        'software',
        'plugin',
        'printer',
        'task',
        'role',
        'site',
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
     * The entity this page is acting on -- an instance of $childClass,
     * assigned in _init().
     *
     * Declared because it was previously created on the fly: PHP 8.2
     * deprecates dynamic properties and PHP 9 makes them an error, and
     * every page construction was hitting that. Deliberately left without
     * a native type -- _init() calls unset($this->obj) when the id does
     * not resolve, and a typed property would then throw on read rather
     * than warning, which is a different behavior from the one this code
     * has always had.
     *
     * @var mixed
     */
    protected $obj;
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
     * Answers a request naming an object that could not be loaded.
     *
     * A browser gets exactly what it always got: a redirect to the node's list
     * page. An XHR gets a 404 and a JSON body naming the problem.
     *
     * The distinction matters because jQuery follows a redirect transparently,
     * so an AJAX caller never sees the 308 -- it sees the list page's HTML (or,
     * for the sub-endpoints that emit nothing before redirecting, an empty
     * body) arriving where it asked for JSON. A DataTable can then report only
     * "DataTables warning: table id=... - Ajax error", which says nothing about
     * what went wrong, and $.apiCall's error handler has no responseJSON to
     * raise a message from at all. The 'error' key is the shape
     * $.notifyFromAPI already reads, so every existing caller surfaces this as
     * an error toast without being changed.
     *
     * Deliberately keyed on self::$ajax (the X-Requested-With header jQuery
     * sets) rather than on a list of known JSON subs: a sub added later gets
     * the right behavior without anyone remembering to register it.
     *
     * @param string $node the node whose list page a browser is sent to
     * @param mixed  $id   the id that could not be resolved
     *
     * @return void
     */
    protected static function objectNotFound($node, $id)
    {
        if (self::$ajax) {
            header('Content-type: application/json');
            self::jsonSend(
                HTTPResponseCodes::HTTP_NOT_FOUND,
                json_encode(
                    [
                        'title' => _('Not Found'),
                        'error' => sprintf(
                            _('No %1$s exists with ID %2$s'),
                            $node,
                            (string)$id
                        )
                    ]
                )
            );
        }
        self::redirect("../management/index.php?node={$node}");
    }
    /**
     * Does this sub act on ONE object, named by an id in the URL?
     *
     * EXACT, not a substring, and that is the whole of this method. The
     * constructor asked `false !== stripos($sub, 'edit')`, which is true of
     * every sub whose name merely CONTAINS the word -- and the two the mass
     * edit feature added do:
     *
     *     stripos('masseditform', 'edit') === 4
     *     stripos('massedit', 'edit')     === 4
     *
     * Both act on a selection POSTed in the body and have no id by design, so
     * the guard fired on every use and answered 404 `No host exists with ID`
     * with nothing after "ID" -- the id it was complaining about did not
     * exist because there was never meant to be one. The modal that fetches
     * the form sat on "Loading, please wait..." for as long as it was left
     * open, because a 404 is not the shape its success handler reads.
     *
     * Mass edit therefore could not work at all on a running server, in
     * either half: the form fetch AND the apply were both refused before
     * either handler ran. Nothing in the suite could see it, because every
     * gate on that feature tests a handler or a builder directly and this
     * guard is upstream of both.
     *
     * A method rather than an inline comparison so the rule can be executed
     * by a test against the sub names that actually exist, rather than
     * grepped for. `sub=edit` is the only object sub in the tree -- 27 URLs
     * spell it, and nothing else containing "edit" is an object page.
     *
     * @param mixed $sub the sub from the request
     *
     * @return bool
     */
    protected static function subNeedsObjectID($sub)
    {
        return 'edit' === strtolower(trim((string)$sub));
    }
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
            '<i class="fas fa-minus"></i>',
            'btn btn-tool',
            'data-lte-toggle="card-collapse"'
        );
        self::$FOGExpandBox = self::makeButton(
            '',
            '<i class="fas fa-plus"></i>',
            'btn btn-tool',
            'data-lte-toggle="card-maximize"'
        );
        self::$FOGCloseBox = self::makeButton(
            '',
            '<i class="fas fa-xmark"></i>',
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
            && self::subNeedsObjectID($sub)
            && (!isset($id)
            || !is_numeric($id)
            || $id < 1)
        ) {
            self::objectNotFound($node, isset($id) ? $id : '');
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
            // Qualified at the point of instantiation, not on the property.
            // $childClass is also a LABEL -- it is lowercased into element
            // names, concatenated into titles, used as a hook payload key and
            // passed to Route::listem()/deletemass(), all of which want the
            // short name. `new $string` is the one use that resolves from the
            // global namespace, and core no longer lives there (ADR 0013 §2).
            $childClass = self::qualify($this->childClass);
            $this->obj = new $childClass($id);
            if (isset($id)) {
                if ($id === 0 || !is_numeric($id) || !$this->obj->isValid()) {
                    unset($this->obj);
                    self::objectNotFound($this->node, $id);
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
                'fas fa-gauge-high'
            ],
            'host' => [
                self::$foglang['Hosts'],
                'fas fa-desktop'
            ],
            'group' => [
                self::$foglang['Groups'],
                'fas fa-sitemap'
            ],
            'image' => [
                self::$foglang['Images'],
                'far fa-hard-drive'
            ],
            'snapin' => [
                self::$foglang['Snapins'],
                'fas fa-cube'
            ],
            'software' => [
                self::$foglang['Software'],
                'fas fa-box-open'
            ],
            'storagegroup' => [
                self::$foglang['Storagegroups'],
                'far fa-object-group'
            ],
            'storagenode' => [
                self::$foglang['Storagenodes'],
                'fas fa-server'
            ],
            'printer' => [
                self::$foglang['Printers'],
                'fas fa-print'
            ],
            'module' => [
                _('Modules'),
                'fas fa-sliders'
            ],
            'task' => [
                self::$foglang['Tasks'],
                'fas fa-bars-progress'
            ],
            'user' => [
                self::$foglang['Users'],
                'fas fa-users'
            ],
            'usergroup' => [
                _('User Groups'),
                'fas fa-people-group'
            ],
            'role' => [
                _('Roles'),
                'fas fa-user-shield'
            ],
            // Beside roles rather than beside hosts: a site says which
            // objects a user may reach, which is the same kind of answer a
            // role gives about which actions.
            'site' => [
                _('Sites'),
                'fas fa-building'
            ],
            'ipxe' => [
                _('iPXE Menu'),
                'fas fa-list-ol'
            ],
            'about' => [
                self::$foglang['FOG Configuration'],
                'fas fa-wrench'
            ],
            'apidocs' => [
                _('API Documentation'),
                'fas fa-code'
            ],
            'report' => [
                self::$foglang['Reports'],
                'fas fa-file-lines'
            ],
            // Directly below Reports, because that is where people look for
            // it -- but a node of its own, not a report. The `report`
            // permission node covers history, so one report.view grant
            // reads every administrative action; the activity viewer gets
            // its own gate precisely so it does not inherit that one.
            // See docs/adr/0023.
            //
            // Nested under the `logging` grouping label in _menuGroups(),
            // which renders at this position. The order HERE is still what
            // decides the order in the sidebar: a group draws at its first
            // present child and lists its children in the order that array
            // gives, so keep the two adjacent.
            'activity' => [
                _('Activity'),
                'fas fa-clock-rotate-left'
            ],
            // Next to Activity, and a different page on purpose. Activity is
            // the operational narrative; this is the record of who was
            // allowed to do what, refusals included, and it discloses
            // attempted usernames -- so it has its own node and is hidden
            // from anyone not granted it. See docs/adr/0021.
            //
            // Grouping the two under one label does not merge their gates.
            // _buildMenuStructure() nests only the children that survived
            // permission filtering, and a group with none left renders
            // nothing at all -- so an activity.view-only role still sees
            // Logging with one entry under it, not a door to the audit log.
            'audit' => [
                _('Audit Log'),
                'fas fa-clipboard-list'
            ],
            // Third under the Logging label. It was a tab on the About page,
            // which is where kernels, PXE menus and settings live -- a
            // different kind of question from "what has this server been
            // doing", which is what the two entries above answer. Its gate is
            // unchanged: Authorization::NODE_ALIASES maps it onto `settings`,
            // exactly what `about` maps to.
            'logviewer' => [
                _('Log Viewer'),
                'fas fa-file-lines'
            ],
            // Fourth under Logging. The audit log above holds these rows
            // too, but only as one flat install-wide grid -- this reads them
            // by host, which is the question anyone actually has about an
            // agent. Its own gate for the reason its coreRegistry() entry
            // gives: what a machine reported and who failed to sign in are
            // different disclosures.
            'agentactivity' => [
                _('Agent Activity'),
                'fas fa-satellite-dish'
            ],
            'service' => [
                self::$foglang['ClientSettings'],
                'fas fa-gears'
            ],
            'client' => [
                _('FOG Client'),
                'fas fa-cloud-arrow-down'
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
                    'fas fa-puzzle-piece'
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

        /*
         * NO KNOWN-NODE GUARD HERE. Dispatch owns the unknown-node case --
         * FOGPageManager::render() redirects a $node with no registered page
         * class, and redirect() exits, so nothing unknown ever reaches this
         * function.
         *
         * There used to be a second check here, comparing $node against the
         * sidebar's keys plus a hardcoded list of nodes that have no sidebar
         * entry. It was written when this ran from Page::__construct(), i.e.
         * BEFORE dispatch. The menu build moved to page-render time so that
         * AJAX requests stop building a menu they discard, which put it
         * AFTER dispatch -- and a guard that runs after the page has already
         * echoed itself into the output buffer cannot prevent anything. It
         * can only throw away a page that dispatch had accepted.
         *
         * Which it did. `impersonate` has no sidebar entry by design (it
         * lives under the logout control, as `logout` does), so clicking it
         * rendered the picker, discarded it, and redirected to the
         * dashboard: no status code, no message, nothing in any log, and on
         * screen indistinguishable from a link that does nothing.
         *
         * The deeper reason it was wrong is that the list was a SECOND,
         * hand-maintained answer to "which nodes exist". The first and only
         * authority is whether a *.page.php declares the node, which is
         * exactly what FOGPageManager::loadPageClasses() computes. Two
         * answers to one question drift, and this one drifted silently.
         *
         * See ADR 0034.
         */

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
                'icon'     => 'fas fa-shield',
                'children' => ['user', 'usergroup', 'role'],
            ],
            // "Where do I go to see what happened" is one question, and it
            // had two answers sitting next to each other with nothing saying
            // they were related. Both are read-only views of the event logs
            // and neither is a report, so Reports was never the right parent
            // -- ADR 0030 puts a name to the difference: a report is an
            // aggregation over a window, and these two are neither.
            //
            // THREE children. History is still not one of them -- ADR 0023
            // item 4 made History_Report a redirect into the Activity viewer,
            // keeping its URL alive for bookmarks -- so it is a legacy door
            // into `activity` rather than a destination to group. The third
            // is the log viewer, moved here off the About page; its own old
            // URL is a redirect for the same reason History_Report's is. A
            // group renders at the position of its first present child, and
            // `activity` already sits directly below Reports, so this changes
            // where nothing is: the two entries collapse in place.
            'logging' => [
                'title'    => _('Logging'),
                'icon'     => 'fas fa-scroll',
                'children' => ['activity', 'audit', 'logviewer', 'agentactivity'],
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
        echo '<i class="nav-arrow fas fa-angle-left"></i>';
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
            echo '<i class="nav-arrow fas fa-angle-left"></i>';
        }
        echo '</p>';
        echo '</a>';
        if ($hasChildren) {
            echo '<ul class="nav nav-treeview">';
            foreach ($subItems as $subItem => $text) {
                // A '#'-prefixed key is a section label, not a destination.
                // AdminLTE's own idiom for one inside a nav; rendered here
                // rather than as a disabled link so it is not focusable and
                // screen readers do not announce it as something to click.
                if (0 === strpos((string) $subItem, '#')) {
                    echo '<li class="nav-header">' . $text . '</li>';
                    continue;
                }
                echo '<li class="nav-item">';
                echo '<a class="nav-link ajax-page-link'
                    . ($activelink && $sub == $subItem ? ' active' : '')
                    . '" '
                    . 'href="../management/index.php?node='
                    . $link
                    . '&sub='
                    . $subItem
                    . '">';
                echo '<i class="nav-icon far fa-circle"></i>';
                echo '<p>' . $text . '</p>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</li>';
    }
    /**
     * Drop section labels that have nothing left under them.
     *
     * A '#'-prefixed key is a heading rather than a destination, and it
     * must go with its contents. This is not cosmetic: the report menu
     * resolves a permission PER REPORT (Authorization::_reportNode), so a
     * role holding only host.view keeps Fleet and Hardware and loses the
     * other five -- and a bare "Reports" heading over an empty gap reads as
     * a rendering fault rather than as a permission working.
     *
     * A label's span runs to the NEXT label, and only a real report link
     * keeps it alive. Both halves matter: "Import Reports" sits after the
     * last group, so counting any surviving key would keep an empty
     * section, and a label immediately followed by another label has an
     * empty span rather than borrowing the next one's contents.
     *
     * Public and pure so it can be exercised with a canned menu. It was
     * inline, where the only thing a test could do was assert that the
     * source still contained it -- which passes with the whole block
     * disabled.
     *
     * @param array $menu link => label, in render order
     *
     * @return array the same menu without its empty sections
     */
    public static function pruneEmptyMenuSections(array $menu)
    {
        $keys = array_keys($menu);
        $count = count($keys);
        foreach ($keys as $i => $key) {
            if (0 !== strpos((string) $key, '#')) {
                continue;
            }
            $kept = false;
            for ($j = $i + 1; $j < $count; $j++) {
                if (0 === strpos((string) $keys[$j], '#')) {
                    break;
                }
                if (0 === strpos((string) $keys[$j], 'file&f=')) {
                    $kept = true;
                    break;
                }
            }
            if (!$kept) {
                unset($menu[$key]);
            }
        }

        return $menu;
    }

    /**
     * The "List All X" / "Create New X" pair for one node, written out.
     *
     * GH-435. These used to be built by sprintf()ing a translated noun into a
     * translated format string -- `List All %s` and `Create New %s` -- and
     * that cannot be translated correctly, in any language that inflects.
     *
     * French was the report: `Creer un nouveau %s` is masculine, so
     * `machine` and `image` (both feminine) need `une nouvelle`, and
     * `utilisateur` needs `nouvel` before its vowel. One format string cannot
     * be all three. `Lister tous les %s` has the same problem in the plural.
     * German inflects the adjective the same way -- `Neue %s erstellen`
     * should be `Neuen Benutzer erstellen` for a masculine noun.
     *
     * The plural was broken independently of gender: the list label appended
     * a literal `s` to the ALREADY TRANSLATED noun, which is an English rule
     * applied to a French, German or Japanese word. Japanese marks no plural
     * at all, German `Rechner` is its own plural, and French nouns ending in
     * -al take -aux.
     *
     * Whole phrases fix both at once, and cost nothing anywhere else: each is
     * an ordinary literal xgettext extracts, and a translator sees the entire
     * sentence rather than a fragment with a hole in it. The codebase already
     * writes them this way where the string is not composed -- ImageManagement
     * has _('Create New Image'), UserManagement has _('Create New User').
     *
     * Nodes NOT listed here fall back to the composed form, which is what
     * plugins get: a plugin's node name is not knowable from here, and a
     * plugin can ship its own catalog. The fallback is no worse for them than
     * it was before this change.
     *
     * @param string $node lowercase node name
     *
     * @return array empty when the node has no written-out pair
     */
    private static function _nodeMenuStrings($node)
    {
        switch ($node) {
            case 'group':
                return ['list' => _('List All Groups'),
                        'add' => _('Create New Group')];
            case 'host':
                // host and image reach here too. The switch below only INSERTS
                // extra entries for them (Pending Hosts, Multicast Image); it
                // never replaces the pair built here, unlike task/report/plugin
                // which assign over it. They are also the two the GH-435 reporter
                // actually cited -- machine and image are both feminine, so
                // `Creer un nouveau %s` was wrong for exactly them.
                return ['list' => _('List All Hosts'),
                        'add' => _('Create New Host')];
            case 'image':
                return ['list' => _('List All Images'),
                        'add' => _('Create New Image')];
            case 'ipxe':
                return ['list' => _('List All Ipxe Menus'),
                        'add' => _('Create New Ipxe Menu')];
            case 'module':
                return ['list' => _('List All Modules'),
                        'add' => _('Create New Module')];
            case 'printer':
                return ['list' => _('List All Printers'),
                        'add' => _('Create New Printer')];
            case 'role':
                return ['list' => _('List All Roles'),
                        'add' => _('Create New Role')];
            case 'site':
                return ['list' => _('List All Sites'),
                        'add' => _('Create New Site')];
            case 'snapin':
                return ['list' => _('List All Snapins'),
                        'add' => _('Create New Snapin')];
            case 'software':
                return ['list' => _('List All Software'),
                        'add' => _('Create New Software')];
            case 'storagegroup':
                return ['list' => _('List All Storage Groups'),
                        'add' => _('Create New Storage Group')];
            case 'storagenode':
                return ['list' => _('List All Storage Nodes'),
                        'add' => _('Create New Storage Node')];
            case 'user':
                return ['list' => _('List All Users'),
                        'add' => _('Create New User')];
            case 'usergroup':
                return ['list' => _('List All User Groups'),
                        'add' => _('Create New User Group')];
        }
        return [];
    }

    /**
     * sprintf() for a format string that came out of a translation catalog.
     *
     * The catalog is edited by translators, so this format string is not under
     * the codebase's control -- and a bad one fails DIFFERENTLY on the two PHP
     * versions this project supports, which is why both arms below are needed.
     * Measured, not assumed:
     *
     *   PHP 8.3   sprintf('Lister 100% des %s', $n)  ArgumentCountError
     *             sprintf('List %q of %s', $n)       ValueError
     *   PHP 7.4   both of the above                  warning, returns false
     *
     * So on 8 an uncaught one takes the whole navigation menu out with a 500
     * on every page, and on 7.4 -- the supported floor -- nothing throws at
     * all and `false` flows on to be rendered as an empty menu label. A
     * Throwable catch alone would silently leave the 7.4 half broken.
     *
     * Not hypothetical for these two strings specifically: es_ES already ships
     * `Crear nuevo grupo` for `Create New %s`, having dropped the placeholder
     * and hardcoded one entity's name. A catalog that can lose a specifier can
     * just as easily gain a stray one.
     *
     * Falling back to the untranslated argument keeps the menu rendering. It
     * is not a good label, but it is a legible one, and it degrades in the one
     * language whose catalog is at fault rather than everywhere. The 7.4
     * warning is deliberately not suppressed -- it is the only record that the
     * catalog needs fixing.
     *
     * @param string $format translated format string
     * @param string $value  already-translated noun to substitute
     *
     * @return string
     */
    private static function _composeMenuLabel($format, $value)
    {
        try {
            $out = sprintf((string)$format, $value);
        } catch (\Throwable $e) {
            return (string)$value;
        }
        // The cast is the 7.4 arm, not decoration: there sprintf() returns
        // FALSE rather than throwing, and (string)false is ''. Comparing to ''
        // rather than to false covers both that and a catalog entry that is
        // simply empty, and phpstan -- whose sprintf() stub returns string at
        // every version in the configured 7.4-8.3 range -- can typecheck it.
        return '' === (string)$out ? (string)$value : (string)$out;
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
        $menu = self::_nodeMenuStrings($node);
        if (!count($menu)) {
            $menu = [
                /**
                 * No _() around the sprintf: $refNode was already translated
                 * on its own above, and a msgid built at runtime can never
                 * match the literal xgettext extracted -- so the outer call
                 * was a guaranteed miss that returned its own argument.
                 * Dropping it changes nothing at runtime and stops the line
                 * claiming to be translatable.
                 */
                'list' => self::_composeMenuLabel(
                    self::$foglang['ListAll'],
                    sprintf('%ss', $refNode)
                ),
                'add' => self::_composeMenuLabel(
                    self::$foglang['CreateNew'],
                    $refNode
                )
            ];
        }
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
                // A single read-only page, not a managed entity. Without this it
                // takes the default list/add pair above and advertises "List All
                // Apidocss" and "Create New Apidocs" -- two subs that do not
                // exist, on a node with nothing to list or create.
            case 'apidocs':
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
                    // Beside Secure Boot because they answer the same kind of
                    // question -- what does this server trust, and can anything
                    // it should not read reach the keys.
                    'certificates' => _('Certificates'),
                    'pxemenu' => self::$foglang['PXEBootMenu'],
                    'maclist' => self::$foglang['MACAddrList'],
                    // Beside FOG Settings, which is where the server-wide
                    // fog-api-token lives -- so both halves of API
                    // credentials are found in one place.
                    //
                    // Here as well as in SubMenuData::subMenu() for the
                    // reason spelled out against secureBoot above: that hook
                    // carries the same 'about' list and never runs, so an
                    // entry added only there is invisible. Both copies are
                    // kept in step so the dead one does not become a
                    // different menu that somebody later revives.
                    'apitokens' => _('API Tokens'),
                    'settings' => self::$foglang['FOGSettings'],
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
                // Architecture spans both tables, so it has no home on a
                // single image's tabs or a single host's. It hangs here
                // rather than under Host Management because the question it
                // answers -- "can this image go to that machine" -- is asked
                // when choosing an image. See schema steps 369/370.
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'architectures',
                    _('Architectures')
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
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'pendingAgents',
                    _('Pending Agents')
                );
                self::arrayInsertBefore(
                    'export',
                    $menu,
                    'agentTokens',
                    _('Agent Tokens')
                );
                break;
            case 'report':
                // Two kinds of screen under one menu, labeled as two.
                // Reports are aggregations over a window; Lists are the row
                // dumps, several of which are the right answer to "give me
                // the rows" and are not going anywhere. See ADR 0030 and
                // ReportManagement::AGGREGATIONS.
                //
                // A HEADER IS A KEY STARTING WITH '#', which is a sub name
                // no report can have -- `f` is base64 and every other sub is
                // alphanumeric. Keeping the array `link => label` matters:
                // SUB_MENULINK_DATA hands it to plugins below, and they
                // iterate it expecting strings.
                $reportlink = "file&f=";
                $menu = [];
                $groups = [
                    '#reports' => self::$foglang['Reports'],
                    '#lists' => _('Lists')
                ];
                foreach (ReportManagement::groupedReports() as $key => $set) {
                    if ([] === $set) {
                        continue;
                    }
                    $menu['#' . $key] = $groups['#' . $key];
                    foreach ($set as $report) {
                        $menu[
                            sprintf(
                                '%s%s',
                                $reportlink,
                                base64_encode($report)
                            )
                        ] = ReportManagement::titleFor($report);
                    }
                }
                // No 'Import Reports' entry. The page behind it rendered a
                // file-upload form with no POST handler behind it -- FOG has
                // never had one, in 1.5 or 1.6 -- so the button ran, answered
                // 200 with the form's own HTML, and imported nothing.
                //
                // Not reinstated, because a report is PHP that this server
                // executes: an upload endpoint for one is an arbitrary-code
                // door, and FOG already has exactly one of those, built with
                // the gate such a thing needs. PluginManagement's archive
                // upload is off unless FOG_PLUGIN_UI_INSTALL_ENABLED is set
                // AND root has made FOG_PLUGIN_DIR writable, stages outside
                // the autoload path, and shows the admin the manifest before
                // anything is installed. Under ADR 0035 a custom report is
                // <plugin>/src/Reports/<Class>.php, so that route already
                // delivers one -- through the door with the locks on it.
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

        // A node the registry knows, that declares no `create` action, has
        // nothing to create -- so it must not advertise one.
        //
        // The permission filter below CANNOT catch this, which is the whole
        // reason this block exists separately. A '*' holder passes every
        // permission string handed to can(), including one naming an action
        // the node never declared, so `activity.create` and `audit.create`
        // both sailed through and put "Create New Activity" and "Create New
        // Audit" in the sidebar of two read-only log viewers. `sub=add`
        // there resolves to index() like any unknown sub, so the link did
        // not even go where it said.
        //
        // Derived from the registry rather than added to the hand-kept case
        // list above, because that list is where this went wrong: it already
        // carries home, client, schema, service, hwinfo and apidocs for
        // exactly this reason, and the next read-only node would have been
        // the seventh thing somebody had to remember. A node absent from the
        // registry is left alone -- that is a plugin page nothing has
        // claimed, and its menu is not ours to trim.
        $registry = Authorization::registry();
        if (isset($registry[$node])
            && !in_array('create', (array) $registry[$node], true)
        ) {
            unset($menu['add'], $menu['import']);
        }

        // Drop sub-menu links the user lacks permission for (add/import ->
        // create, multicast -> task, etc.). Presentation only -- dispatch
        // enforcement lives in FOGPageManager::render().
        foreach (array_keys($menu) as $subKey) {
            // A '#' key is a section label with no destination, so there is
            // no permission to resolve for it. Left in here and pruned
            // below instead, once it is known whether anything survived
            // under it.
            if (0 === strpos((string) $subKey, '#')) {
                continue;
            }
            $perm = Authorization::resolvePagePermission($node, $subKey, false);
            if (!Authorization::can($perm)) {
                unset($menu[$subKey]);
            }
        }

        $menu = self::pruneEmptyMenuSections($menu);

        // A lone "List All X" is not a sub-menu. It expands the parent to
        // offer one child that goes where the parent already goes, so it
        // costs a click and a chevron to arrive at the same page -- "Audit
        // Log -> List All Audits". _renderMenuNode() turns a childless node
        // into a direct link, which is how Tasks has always behaved.
        //
        // Derived rather than added to the hand-kept case list above,
        // because that list is where this keeps going wrong. It already
        // carries home, client, schema, service, hwinfo, apidocs and task
        // for related reasons, and the registry block above had to be
        // written separately for the same failure one step earlier. The
        // condition is the honest one: `list` is the DEFAULT sub every node
        // gets, so a node left holding only that has nothing to navigate.
        //
        // Applies after the two filters on purpose. A node whose `add` was
        // stripped for lacking `create` (the read-only log viewers) and one
        // whose `add` was stripped because THIS user may not create are the
        // same situation from the menu's point of view, and both should
        // collapse. Runs after the plugin hooks too, so a sub a plugin adds
        // keeps the node expanded.
        if (['list'] === array_keys($menu)) {
            $menu = [];
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
                // Initialized to match indexDivDisplay()'s parameter default.
                // It was passed by reference while undeclared in this scope;
                // nothing here reads it back, so the key is kept for the
                // listeners that expect it rather than being made meaningful.
                $delNeeded = false;
                self::$HookManager->processEvent(
                    'AJAX_DATA_DISPLAY_CHANGE',
                    [
                        'data' => &$data,
                        'childClass' => &$this->childClass,
                        'main' => &$this,
                        'delNeeded' => &$delNeeded
                    ]
                );
                // Core's site boundary for the listed page. Re-lists with
                // the in-scope ids rather than dropping rows from the
                // payload above, so the row counts describe what the user
                // can actually see -- a filtered payload with the unscoped
                // totals still tells them how much exists outside their
                // scope. null means no boundary applies; an empty array is
                // a real deny-all and must still narrow the list.
                $scopeIDs = Authorization::scopedObjectIDs($this->node);
                if (null !== $scopeIDs) {
                    Route::listem(
                        $this->childClass,
                        ['id' => $scopeIDs ?: [0]]
                    );
                    $data = Route::getData();
                }
                echo $data;
                exit;
            }
            if ($node == 'ipxe') {
                $this->title = _('All Boot Menu Items');
            } else {
                $this->title = sprintf(_('All %s'), $this->childClass . 's');
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
                self::shortName($this),
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
     * Normalizes a webroot into the bare path used to build URLs.
     *
     * GH-529: pages used to build server and storage-node URLs with a literal
     * '/fog/', so nothing reached a FOG installed anywhere else. The webroot
     * reaches us in every shape -- '/fog/', 'fog', '/fog', '' -- because it is
     * written by the installer, edited by hand in FOG Settings, and carried
     * per-node in ngmWebroot, so normalize rather than trust the stored form.
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
     * and the JS only has to swap text. The color is fixed for the life of
     * the button -- state is carried by the label, not by a color change --
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
            . ' data-pause-label="' . \Initiator::e($pause) . '"'
            . ' data-resume-label="' . \Initiator::e($resume) . '"'
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
                // Tasks are canceled per-pane, never deleted; the tabbed
                // task page hits sub=list via the no-sub default, so keep
                // the delete actionbox off it. Activity, the audit log and
                // agent activity are read-only views of the event logs --
                // ?node=X&sub=list resolves to index() like any unknown sub
                // does, and without this they would draw a "Delete selected"
                // none of them implements. For the audit trail there is
                // nothing to implement it WITH: auditlog and auditchange
                // have no delete route anywhere in FOG (ADR 0021
                // Decision 8).
                //
                // Read from the page, not from its node name. The list this
                // replaces had to be edited every time a read-only page was
                // added and was not when Agent Activity arrived, so that
                // page shipped a red Delete selected it could not honor.
                if ($this->selectable) {
                    $actionbox .= self::makeButton(
                        'deleteSelected',
                        _('Delete selected'),
                        'btn btn-danger float-start'
                    );
                    // Same seam as queueTaskActions() below: a page that can
                    // edit its selection in bulk says so by defining the
                    // method, and the toolbar does not learn a second node
                    // name to do it.
                    //
                    // It sits on the LEFT, beside Delete selected, because
                    // that is what it is: an action ON the rows that are
                    // already ticked. The right-hand group is the opposite
                    // half of the toolbar -- Queue Task, Add to group, Add
                    // all bring something new into existence. Two floated
                    // buttons stack in emission order, so emitting it after
                    // Delete puts it to Delete's right; ms-2 is the gap the
                    // btn-group gives its own children for free.
                    if (method_exists($this, 'massEditActions')) {
                        $mass = $this->massEditActions();
                        $actionbox .= $mass['button'];
                        $modals .= $mass['modal'];
                    }
                    $actionbox .= '<div class="btn-group float-end">';
                    // Picked up the same way addModal() is, so the toolbar
                    // stays generic: a page that can task its selection says
                    // so by defining the method. The button is emitted first
                    // because this is a btn-group -- a flex container, where
                    // emission order IS left-to-right and the float classes
                    // do nothing -- so the primary "Add" stays rightmost.
                    if (method_exists($this, 'queueTaskActions')) {
                        $queue = $this->queueTaskActions();
                        $actionbox .= $queue['button'];
                        $modals .= $queue['modal'];
                        // Hidden data, not markup: the one-click task
                        // buttons are drawn by the browser into the grid's
                        // own button bar, and this is what tells it which
                        // ones to draw. Empty when the page offers none.
                        $modals .= $queue['quick'] ?? '';
                    }
                    if (method_exists($this, 'addModal')) {
                        if ($node == 'host') {
                            $actionbox .= self::makeButton(
                                'addSelectedToGroup',
                                // Not "Add selected to group" any more: the
                                // modal behind it removes as well now (ADR
                                // 0038 Decision 16a requirement 2). The id is
                                // unchanged, because it is what
                                // fog.host.list.js addresses the button by.
                                _('Edit groups'),
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
                            _('Group Membership'),
                            '<select id="groupSelect" class="" '
                            . 'name="" multiple="multiple">'
                            . '</select>'
                            . '<p class="form-text mt-2 mb-0">'
                            . _(
                                'A name that is not already a group is'
                                . ' created when you add. Remove only works'
                                . ' on groups that exist.'
                            )
                            . '</p>',
                            self::makeButton(
                                'closeGroupModal',
                                _('Cancel'),
                                'btn btn-outline-secondary float-start',
                                'data-bs-dismiss="modal"'
                            )
                            // Add is emitted FIRST of the two float-end
                            // buttons and so renders rightmost: floated
                            // elements stack against the edge in emission
                            // order, which is the same thing that decided
                            // where the mass edit button sits. Reads
                            // "Remove | Add" left to right, with the
                            // non-destructive one under the cursor's usual
                            // resting place.
                            . self::makeButton(
                                'confirmGroupAdd',
                                _('Add'),
                                'btn btn-primary float-end ms-2'
                            )
                            . self::makeButton(
                                'confirmGroupRemove',
                                _('Remove'),
                                'btn btn-outline-danger float-end'
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
                            // $id_field was never declared in this scope, so
                            // isset($rowData[$id_field]) read $rowData[''] and
                            // was always false -- the whole expression has only
                            // ever been the 'id' test below. Kept as it behaved.
                            . (
                                isset($rowData['id']) ?
                                'id="' . $rowData['id'] . '"' :
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
        } catch (\Exception $e) {
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
            $this->dataReplace[] = \Initiator::e($val);
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
                        return \Initiator::e((string)$value);
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
                $items = Route::getList(
                    $this->childClass,
                    $where
                );
                foreach ($items as $item) {
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
                            self::storageNow(),
                            self::getQueuedState(),
                            self::$FOGUser->get('name'),
                            $storagegroupID
                        ];
                    }
                }
                (new FileDeleteQueueManager())
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
        } catch (\Exception $e) {
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
                $ADDomain = \Initiator::e($this->obj->get('ADDomain'));
            }
            if (empty($ADOU)) {
                $ADOU = trim($this->obj->get('ADOU'));
                $ADOU = str_replace(';', '', \Initiator::e($ADOU));
            }
            if (empty($ADUser)) {
                $ADUser = \Initiator::e($this->obj->get('ADUser'));
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
                    \Initiator::e(self::getSetting('FOG_AD_DEFAULT_OU'))
                )
            )
        );
        $ADOU = trim($ADOU);
        $ADOU = str_replace(';', '', \Initiator::e($ADOU));
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
                    . \Initiator::e($ou)
                    . '"'
                    . ($optFound == $ou ? ' selected' : '')
                    . '>'
                    . \Initiator::e($ou)
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
        // Build the buttons BEFORE the hook fires. They used to be created
        // after it and assigned with '=', so the listener was handed an
        // undefined variable by reference and anything it contributed was
        // unconditionally overwritten before the footer was echoed. Compare
        // IMPORT_FIELDS below, which has always done it in this order.
        $buttons = '';
        if ($ownElement) {
            $buttons = self::makeButton(
                'ad-send',
                _('Update'),
                'btn btn-primary float-end'
            )
            . self::makeButton(
                'ad-clear',
                _('Clear Fields'),
                'btn btn-danger float-start'
            );
        }
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
                    \Initiator::e($this->obj->get('id'))
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
                        throw new \Exception(_('Dot in Filename not allowed!'));
                    }
                    $dlUrl = $_SESSION['dl-kernel-file'];
                    if (!(0 === stripos($dlUrl, 'https://fogproject.org/') ||
                        0 === stripos($dlUrl, 'https://github.com/FOGProject/'))
                    ) {
                        throw new \Exception(_('Specified download URL not allowed!'));
                    }
                    $fh = fopen(
                        $_SESSION['tmp-kernel-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new \Exception(
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
                        throw new \Exception(
                            sprintf(
                                '%s: %s (HTTP %d)',
                                _('Error'),
                                _('Download Failed'),
                                $httpCode
                            )
                        );
                    }
                    if (!file_exists($_SESSION['tmp-kernel-file'])) {
                        throw new \Exception(
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
                        throw new \Exception(
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
                        throw new \Exception(
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
                        self::formatTime('now', 'Ymd_His')
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
                        throw new \Exception(_('Unable to connect to ssh'));
                    }
                    if (!self::$FOGSSH->exists($backuppath)) {
                        self::$FOGSSH->sftp_mkdir($backuppath);
                    }
                    if (self::$FOGSSH->exists($orig)) {
                        self::$FOGSSH->sftp_rename($orig, $backupfile);
                    }
                    self::$FOGSSH->put($tmpfile, $orig);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    /**
                     * Quoted, because $br_ver and $tg_ver come from the POST
                     * body and this string is run as a command on the storage
                     * node. Unquoted, a value carrying a space or a shell
                     * metacharacter is read as further commands.
                     */
                    $stampFailed = false;
                    $br_cmd = sprintf(
                        'attr -s version -V %s %s',
                        escapeshellarg((string)$br_ver),
                        escapeshellarg($orig)
                    );
                    $tg_cmd = sprintf(
                        'attr -s tag_name -V %s %s',
                        escapeshellarg((string)$tg_ver),
                        escapeshellarg($orig)
                    );
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
                        $stampFailed = true;
                        error_log(_('Error on ssh command setting version'). ' ' . $br_cmd);
                        error_log(_('Error'). ': ' . $error_br_t);
                    }
                    if ($error_tg_t) {
                        $stampFailed = true;
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
                    /**
                     * A failed stamp used to be logged and nothing else, so
                     * the file simply reported an unknown version later with
                     * no record of why. Say it here, where the admin is.
                     */
                    $msgText = $resigned
                        ? _('File uploaded to storage node and '
                            . 're-signed for Secure Boot!')
                        : _('File uploaded to storage node!');
                    if ($stampFailed) {
                        $msgText .= ' ' . _('The version and release could '
                            . 'not be recorded on the file, so it will '
                            . 'report as unknown. See the PHP error log.');
                    }
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => $msgText,
                            'title' => _('Update Kernel Success')
                        ]
                    ));
                }
            }
        } catch (\Exception $e) {
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
        // the moment it is being signed, serialized here. That window is a
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
            throw new \Exception(
                _('Error: Could not lock the Secure Boot staging directory')
            );
        }
        $staged = false;
        try {
            // Overwrites any leftover from a run that died mid-sign, which is
            // what we want: ours is the only kernel anyone is waiting on.
            if (!rename($tmpfile, $shared)) {
                throw new \Exception(
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
                throw new \Exception(
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
                        throw new \Exception(_('Specified download URL not allowed!'));
                    }
                    $fh = fopen(
                        $_SESSION['tmp-initrd-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new \Exception(
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
                        throw new \Exception(
                            sprintf(
                                '%s: %s (HTTP %d)',
                                _('Error'),
                                _('Download Failed'),
                                $httpCode
                            )
                        );
                    }
                    if (!file_exists($_SESSION['tmp-initrd-file'])) {
                        throw new \Exception(
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
                        throw new \Exception(
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
                        self::formatTime('now', 'Ymd_His')
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
                        throw new \Exception(_('Unable to connect to SSH'));
                    }
                    if (!self::$FOGSSH->exists($backuppath)) {
                        self::$FOGSSH->sftp_mkdir($backuppath);
                    }
                    if (self::$FOGSSH->exists($orig)) {
                        self::$FOGSSH->sftp_rename($orig, $backupfile);
                    }
                    self::$FOGSSH->put($tmpfile, $orig);
                    self::$FOGSSH->sftp_chmod($orig, 0644);
                    /**
                     * Quoted, because $br_ver and $tg_ver come from the POST
                     * body and this string is run as a command on the storage
                     * node. Unquoted, a value carrying a space or a shell
                     * metacharacter is read as further commands.
                     */
                    $stampFailed = false;
                    $br_cmd = sprintf(
                        'attr -s version -V %s %s',
                        escapeshellarg((string)$br_ver),
                        escapeshellarg($orig)
                    );
                    $tg_cmd = sprintf(
                        'attr -s tag_name -V %s %s',
                        escapeshellarg((string)$tg_ver),
                        escapeshellarg($orig)
                    );
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
                        $stampFailed = true;
                        error_log(_('Error on ssh command setting version'). ' ' . $br_cmd);
                        error_log(_('Error'). ': ' . $error_br_t);
                    }
                    if ($error_tg_t) {
                        $stampFailed = true;
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
                    $msgText = _('File uploaded to storage node!');
                    if ($stampFailed) {
                        $msgText .= ' ' . _('The version and release could '
                            . 'not be recorded on the file, so it will '
                            . 'report as unknown. See the PHP error log.');
                    }
                    $this->jsonSend($code, json_encode(
                        [
                            'msg' => $msgText,
                            'title' => _('Update Initrd Success')
                        ]
                    ));
                }
            }
        } catch (\Exception $e) {
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
            . \Initiator::e($this->obj->get('name')),
            sprintf(_('Confirm you would like to delete this %s'), $node)
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
            sprintf(_('Remove %s Associations'), $item),
            sprintf(
                _('Please confirm you would like to dissociate the selected %s'),
                $item . 's'
            ),
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
                throw new \Exception('#!ihc');
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
                throw new \Exception('#!ist');
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
        } catch (\Exception $e) {
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
        (new RegisterClient())->json();
        ob_end_clean();
        try {
            // The legacy client has no module for `software`: the agent
            // takes it through /agent/v1 (Agent\State). Left in this list
            // the default branch below resolves the key to Items\Software,
            // which has no json(), and every legacy check-in fatals.
            $igMods = [
                'dircleanup',
                'usercleanup',
                'clientupdater',
                'hostregister',
                'software',
            ];
            $globalModules = array_diff(
                self::getGlobalModuleStatus(false, true),
                $igMods
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
            // RESOLVED, not host-direct -- see ServiceModule. This is the
            // list the client is told to run, so a group grant has to be in
            // it.
            $hostModules = Route::getIds(
                'module',
                ['id' => self::$Host->resolvedModules()],
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
        } catch (\Exception $e) {
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
            $hosts = (new Group($groupid))
                ->get('hosts');
        }
        (new HostManager())
            ->update(
                ['id' => $hosts],
                '',
                [
                    'pub_key' => '',
                    'sec_tok' => '',
                    // Reset must leave nothing behind that authorize() would
                    // still accept, grace token included.
                    'prev_sec_tok' => '',
                    // GH-1245: no expiry, not an expiry in the year zero.
                    'sec_time' => null
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
        $hosts = (new Group($groupid))
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
                throw new \Exception(_('Unable to remove protected items'));
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
                        throw new \Exception(_('Failed to remove hosts'));
                    }
                }
            }
            if ($this->obj instanceof Image || $this->obj instanceof Snapin) {
                if (isset($_POST['andFile'])) {
                    if (!$this->obj->deleteFile()) {
                        throw new \Exception(_('Unable to delete file data'));
                    }
                }
            }
            if (!$this->obj->destroy()) {
                $serverFault = true;
                throw new \Exception(
                    _('Failed to remove')
                    . ': '
                    . \Initiator::e($this->obj->get('name'))
                );
            }
            $hook = "{$ucnode}_DELETE_SUCCESS";
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Successfully deleted')
                    . ': '
                    . \Initiator::e($this->obj->get('name')),
                    'title' => _('Delete Success')
                ]
            );
        } catch (\Exception $e) {
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
        (new WakeOnLan(implode('|', $macs)))->send();
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
            \Initiator::e($this->formAction),
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
                    // Single-valued, and the only entry here that is: a
                    // host has one site, so import replaces rather than
                    // adds. Host has no `site` field to name in 'get' /
                    // 'apply' -- the membership lives in its own table
                    // rather than on the host row -- so both go through
                    // Site's statics.
                    //
                    // Restored here after the Site plugin was retired into
                    // core: its addsiteimport.hook.php registered this
                    // label and did not come across, so between then and
                    // now, exporting a host and re-importing it silently
                    // dropped which site it was in.
                    'site' => [
                        'class' => 'Site',
                        'namefield' => 'name',
                        'get' => [Site::class, 'hostSiteNames'],
                        'apply' => [Site::class, 'applyHostSite'],
                        'bulkclass' => 'SiteHostMember',
                        'parentkey' => 'hostID',
                        'childkey' => 'siteID',
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
        $items = Route::getList($class);
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
            } catch (\Exception $e) {
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
            $rows = Route::getList(
                $entry['bulkclass'],
                [$entry['parentkey'] => $ids]
            );
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
     * cell must be non-empty, unique, and a recognized column token.
     * Recognized tokens are the class field keys plus 'primac' (hosts) and
     * 'associations' (where supported).
     *
     * Returns true when the row is a header; $map is filled with
     * lowercased-token => column-index for recognized tokens, and $unknown
     * collects any header cells that were not recognized (only meaningful
     * when $force is true, since auto-detect rejects unknown tokens).
     *
     * @param mixed $row         the first CSV row
     * @param array $validTokens recognized tokens, lowercased
     * @param bool  $force       treat the row as a header unconditionally
     * @param array $map         out: token => column index
     * @param array $unknown     out: unrecognized header cells
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
            $childClass = self::qualify($this->childClass);
            $Item = new $childClass();
            $mime = $_FILES['file']['type'];
            if (!in_array($mime, $mimes)) {
                if ($ext !== 'csv') {
                    self::redirect($this->formAction);
                }
            }
            if ($_FILES['file']['error'] > 0) {
                $serverFault = true;
                throw new \Exception($_FILES['file']['error']);
            }
            $tmpf = pathinfo($_FILES['file']['tmp_name']);
            $file = sprintf(
                '%s%s%s',
                $tmpf['dirname'],
                DS,
                $tmpf['basename']
            );
            if (!file_exists($file)) {
                throw new \Exception(_('Could not find temp filename'));
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

            // Recognized header tokens (lowercased) for this class: the field
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
                            throw new \Exception(
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
                    throw new \Exception(
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
                        (new HostManager())
                            ->getHostByMacAddresses($macs);
                        if (self::$Host->isValid()) {
                            throw new \Exception(
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
                        throw new \Exception(
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
                        // viewed afterward (getStorageGroup has no primary to
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
                        $childClass = self::qualify($this->childClass);
                        $Item = new $childClass();
                    } else {
                        $numFailed++;
                    }
                } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
                '<option value="%s"%s data-label="%s">%s</option>',
                (
                    $useidsel ?
                    \Initiator::e($id) :
                    \Initiator::e($item)
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
                // Bare label, without the " - (id)" the picker adds below,
                // for anything echoing the choice rather than offering it.
                \Initiator::e($item),
                (
                    $addidtodisplay ?
                    \Initiator::e($item) . ' - (' . \Initiator::e($id) . ')' :
                    \Initiator::e($item)
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

        $actionSelector = (new PowerManagementManager())->getActionSelect(
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
                \Initiator::e($this->obj->get('id'))
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
     * @param int|object|bool $obj The object to pass in, -1 = current node + id.
     *                            Anything falsy means there is no object, which
     *                            skips the hook and plugin-injection blocks --
     *                            that is what a report passes, having no entity
     *                            for a plugin tab to attach to. false is the
     *                            value callers actually use for that, so it is
     *                            in the type: three pages passed it against a
     *                            declared int|object and two of them carried a
     *                            baselined error for saying so.
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
     * What a file in the FOS boot directory is FOR.
     *
     * A FOS Kernel boots the imaging environment; a FOS Init is the initramfs
     * it boots with; a Boot Payload is something the boot menu can chain but
     * which does not boot FOS (memdisk, memtest.bin, grub.exe, refind*.efi);
     * anything else sharing that directory is Unclassified.
     */
    const BOOT_ROLE_KERNEL = 'kernel';
    const BOOT_ROLE_INIT = 'init';
    const BOOT_ROLE_PAYLOAD = 'payload';
    const BOOT_ROLE_OTHER = 'unclassified';
    /**
     * Every bootFile row, keyed by filename, loaded at most once per request.
     *
     * bootFileInfo() is called once per file in the boot directory, and a
     * listing walks the whole directory -- so a lookup per file is a query
     * per file, twice over on a host form that asks for kernels and then for
     * inits. One query answers all of them.
     *
     * null means "not loaded yet", which is not the same as an empty map: a
     * server with no rows yet must not be re-queried once per file.
     *
     * @var array|null
     */
    private static $_bootFileRows = null;
    /**
     * Picker value meaning "I will type the name myself".
     *
     * Not a filename any filesystem would produce, and matched literally by
     * fog.common.js -- change it in both places or in neither.
     */
    const BOOT_MANUAL_VALUE = '__fog_manual__';
    /**
     * Decides what a boot directory file is, by reading it.
     *
     * Deciding by NAME is what put memdisk, memtest.bin and grub.exe in the
     * Host Kernel dropdown: there was no positive test for a kernel, only
     * "an init looks like this, so everything else is a kernel". A blacklist
     * of extensions cannot be completed either -- an old backup script
     * leaving refind.efi.new behind defeats it -- and a hand-compiled kernel
     * under any name has to keep working.
     *
     * So read the file instead. The tests are exact and cost one 4KiB read
     * rather than a scan of a 50MB image:
     *
     * - x86/x86_64: 'HdrS', the Linux setup header's own magic, at 0x202.
     * - arm64: 'ARMd', the Image header's magic, at 0x38.
     *
     * grub.exe and memdisk are PE binaries too, so a PE check alone could not
     * separate them from an EFI-stub kernel; these two can.
     *
     * Note what is deliberately NOT excluded by name: only FOG's own web
     * assets that share this directory, and the .unsigned working files
     * _resignKernels() leaves behind. Everything else that is neither kernel
     * nor init is a payload, because memtest.bin and memdisk are raw images
     * with no magic to match on and FOG_MEMTEST_KERNEL legitimately points at
     * them.
     *
     * @param string $path full path to the file
     *
     * @return string one of the BOOT_ROLE_* constants
     */
    public static function bootFileRole($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::BOOT_ROLE_OTHER;
        }
        $name = basename($path);
        if (preg_match('/\.(unsigned|php|png|jpe?g|gif|svg|css|js|conf)$/i', $name)) {
            return self::BOOT_ROLE_OTHER;
        }
        $head = self::readMagic($path, 4096);
        if ($head === '') {
            return self::BOOT_ROLE_OTHER;
        }
        if (self::_looksLikeInit($head)) {
            return self::BOOT_ROLE_INIT;
        }
        if (self::_looksLikeKernel($head)) {
            return self::BOOT_ROLE_KERNEL;
        }
        /**
         * A payload is something the boot menu can CHAIN. Plain text is not,
         * whatever it is called. The extension list above catches
         * refind.conf, but a real server had refind.conf.new left by an old
         * backup script -- the same shape as the refind.efi.new that
         * started all this -- and a config file offered as a bootable
         * payload is the same class of wrong, just quieter.
         */
        if (self::_looksLikeText($head)) {
            return self::BOOT_ROLE_OTHER;
        }

        return self::BOOT_ROLE_PAYLOAD;
    }
    /**
     * True when $head is plainly text rather than an image of any kind.
     *
     * A NUL byte settles it immediately: no text file has one, and every
     * boot payload does within the first few hundred bytes. The printable
     * test then covers a short file that happens to have no NUL yet.
     *
     * @param string $head leading bytes of the file
     *
     * @return bool
     */
    private static function _looksLikeText($head)
    {
        if (false !== strpos($head, "\0")) {
            return false;
        }

        return 0 === preg_match('/[^\x09\x0a\x0d\x20-\x7e]/', $head);
    }
    /**
     * True when $head carries a Linux kernel image's own header magic.
     *
     * @param string $head leading bytes of the file
     *
     * @return bool
     */
    private static function _looksLikeKernel($head)
    {
        /**
         * arm64 first: an Image has no setup header at all. Its header magic
         * is 0x644d5241 at 0x38, the bytes 'ARMd'. An arm64 kernel is also a
         * PE image when built with EFI stub support, so this is checked in
         * its own right rather than inferred from MZ.
         */
        if (substr($head, 0x38, 4) === 'ARMd') {
            return true;
        }
        // x86/x86_64: the setup header's own magic, four bytes at 0x202.
        if (substr($head, 0x202, 4) !== 'HdrS') {
            return false;
        }
        /**
         * HdrS is necessary and NOT sufficient, which the first version of
         * this got wrong. Measured against a real service/ipxe:
         *
         *   file          MZ   HdrS  protocol  PE header
         *   bzImage       yes  yes   0x020f    yes
         *   bzImage32     yes  yes   0x020f    yes
         *   grub.exe      yes  yes   0x0203    NO
         *   memdisk       NO   yes   0x0203    NO
         *
         * grub4dos and syslinux's memdisk are built to be loaded the way a
         * kernel is loaded, so they carry a setup header and even a version
         * banner -- memdisk reports "MEMDISK 3.86 2010-04-01" and grub.exe
         * reports "2.6.13.1 (mdv@localhost)". On HdrS alone both were
         * classified as FOS Kernels, which is most of the bug this whole
         * change set is about.
         *
         * Two independent things separate them, and either is enough:
         *
         * A GENUINE PE header. Every FOS kernel is an EFI-stub image on all
         * three architectures -- kernelfetch() already refuses a download
         * that is not MZ for that reason -- and neither grub.exe nor memdisk
         * has one. grub.exe is MZ with no PE at e_lfanew; memdisk is not
         * even MZ.
         *
         * Or a BOOT PROTOCOL from this decade. The field at 0x206 is 0x020f
         * on the real kernels and 0x0203 on both impostors, which is a
         * 2003-era protocol no FOS kernel has ever spoken. 0x020c is Linux
         * 3.8, comfortably below the oldest kernel FOS has shipped.
         *
         * OR rather than AND, so a hand-compiled kernel built without
         * CONFIG_EFI_STUB is still recognized on its protocol version.
         */
        if (self::_hasPeHeader($head)) {
            return true;
        }
        $proto = @unpack('v', substr($head, 0x206, 2));

        return is_array($proto)
            && !empty($proto[1])
            && (int)$proto[1] >= 0x020c;
    }
    /**
     * True when $head is a real PE image, not merely an MZ stub.
     *
     * MZ on its own means almost nothing -- every DOS-era executable starts
     * with it, grub.exe included. The PE signature sits at the offset the
     * DOS header records at 0x3c, and that indirection is the part a check
     * for 'MZ' misses.
     *
     * @param string $head leading bytes of the file
     *
     * @return bool
     */
    private static function _hasPeHeader($head)
    {
        if (0 !== strpos($head, 'MZ')) {
            return false;
        }
        $at = @unpack('V', substr($head, 0x3c, 4));
        if (!is_array($at) || empty($at[1])) {
            return false;
        }
        $at = (int)$at[1];
        // Inside what was read, and past the DOS header it points from.
        if ($at < 4 || $at > strlen($head) - 4) {
            return false;
        }

        return substr($head, $at, 4) === "PE\0\0";
    }
    /**
     * True when $head carries an initramfs archive or compression magic.
     *
     * FOS ships init.xz and arm_init.cpio.gz, but a hand-built initramfs may
     * use any compressor the kernel can unpack, so accept those too rather
     * than pinning the two names FOG happens to download.
     *
     * @param string $head leading bytes of the file
     *
     * @return bool
     */
    private static function _looksLikeInit($head)
    {
        $magics = [
            "\xfd" . '7zXZ' . "\x00",
            "\x1f\x8b",
            "\x28\xb5\x2f\xfd",
            "\x04\x22\x4d\x18",
            'BZh',
            "\x5d\x00\x00",
            "\x89" . 'LZO',
            '070701',
            '070702',
            '070707',
            "\xc7\x71",
        ];
        foreach ($magics as $magic) {
            if (0 === strpos($head, $magic)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Reads a kernel image's own version banner, or '' when it has none.
     *
     * The x86 setup header records where its version string lives: a 16-bit
     * offset at 0x20e, relative to 0x200. That is exact, so there is no
     * scanning and no guessing.
     *
     * arm64 Image has no equivalent field, so this answers '' there. A caller
     * displaying this must say the version is unavailable rather than
     * inventing one; reporting a wrong version is worse than reporting none.
     *
     * @param string $path full path to the file
     *
     * @return string e.g. '6.6.30 (fos@buildroot) #1 SMP', or ''
     */
    public static function bootFileKernelVersion($path)
    {
        $head = self::readMagic($path, 4096);
        if (substr($head, 0x202, 4) !== 'HdrS') {
            return '';
        }
        $at = @unpack('v', substr($head, 0x20e, 2));
        if (!is_array($at) || empty($at[1])) {
            return '';
        }
        $at = $at[1] + 0x200;
        $bytes = self::readMagic($path, $at + 512);
        if (strlen($bytes) <= $at) {
            return '';
        }
        $parts = explode("\x00", substr($bytes, $at, 512), 2);
        $ver = trim($parts[0]);
        /**
         * Refuse anything not plainly printable. A bad offset lands in the
         * middle of the compressed image, and rendering that as a version
         * would be worse than saying nothing.
         */
        if ($ver === '' || preg_match('/[^\x20-\x7e]/', $ver)) {
            return '';
        }
        return $ver;
    }
    /**
     * Reads one extended attribute, and says why when it cannot.
     *
     * PHP has no xattr reader. The PECL extension is absent on every server
     * this runs on and this codebase has never used it, so the FOS release
     * tag -- which exists only as an xattr -- can be reached no other way
     * than by running `attr`.
     *
     * That is exactly the call that fails invisibly today. status/kernelvers
     * runs `attr -g` through shell_exec, discards stderr, and renders an
     * empty result as `Unknown`, so at least seven different causes arrive
     * looking identical: no attr binary, SELinux refusing httpd_t the exec,
     * a mount without user_xattr, the attribute genuinely never set, a
     * permissions failure, disabled shell functions, and a parse artifact
     * from omitting -q. An admin cannot act on any of them.
     *
     * So this captures stderr and the exit status and answers with a reason.
     * -q is passed, which the old call site omitted: without it `attr -g`
     * prints a header line before the value and `tail -n1` has to guess.
     *
     * @param string $path full path to the file
     * @param string $attr attribute name, e.g. 'tag_name'
     *
     * @return array ['value' => string, 'reason' => string]
     */
    public static function bootFileXattr($path, $attr)
    {
        $none = function ($why) {
            return ['value' => '', 'reason' => $why];
        };
        if (!is_file($path) || !is_readable($path)) {
            return $none(_('the file cannot be read'));
        }
        if (!function_exists('proc_open')) {
            return $none(_('the web server may not run external commands'));
        }
        $cmd = sprintf(
            'attr -q -g %s %s',
            escapeshellarg($attr),
            escapeshellarg($path)
        );
        $pipes = [];
        $proc = @proc_open(
            $cmd,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($proc)) {
            return $none(_('the web server may not run external commands'));
        }
        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $value = trim(trim(trim($out), '"'));
        if ($value !== '') {
            return ['value' => $value, 'reason' => ''];
        }
        /**
         * Every branch below is a DIFFERENT operational problem with a
         * different fix, which is the whole reason for reading stderr rather
         * than treating empty output as one answer.
         */
        if (127 === $code || false !== stripos($err, 'not found')) {
            return $none(_('attr is not installed on this server'));
        }
        if (false !== stripos($err, 'not supported')) {
            return $none(_('this filesystem does not carry extended attributes'));
        }
        if (false !== stripos($err, 'permission')) {
            return $none(_('the web server may not read this file'));
        }
        if (false !== stripos($err, 'no such attribute')
            || 0 === $code
        ) {
            return $none(_('not recorded on this file'));
        }

        return $none(
            $err !== ''
            ? trim($err)
            : _('attr could not be run -- SELinux may be denying it')
        );
    }
    /**
     * Everything known about one boot directory file, cached.
     *
     * The filesystem is the inventory: existence, size and mtime are read
     * here, live, every time. The bootFile row is consulted for what reading
     * the directory cannot answer, and is rewritten whenever the stat has
     * moved -- so a kernel copied in by hand is picked up on the next
     * listing and one deleted by hand simply stops appearing.
     *
     * Two of the three cached values are caches in the ordinary sense: role
     * and version come out of the file's own bytes and could be re-read at
     * any time. The release tag is not. It may be permanently unreadable on
     * this server (see bootFileXattr), so it is stored the first time it can
     * be read at all and served from the row afterward -- a stored tag is
     * never discarded because a later read failed.
     *
     * @param string $path full path to the file
     *
     * @return array
     */
    public static function bootFileInfo($path)
    {
        $name = basename($path);
        $stat = @stat($path);
        if ($stat === false) {
            return [
                'name' => $name,
                'exists' => false,
                'role' => self::BOOT_ROLE_OTHER,
                'size' => 0,
                'mtime' => 0,
                'checksum' => '',
                'kernelVersion' => '',
                'releaseTag' => '',
                'tagReason' => _('the file cannot be read'),
                'pinned' => false
            ];
        }
        $size = (int)$stat['size'];
        /**
         * mtime, not ctime. The old panel used ctime and reported every
         * file's "Installed Date" as the date of the last install, because
         * restorePreservedCustomizations() chowns the whole directory on
         * every run and that moves ctime on files it did not touch.
         */
        $mtime = (int)$stat['mtime'];

        $row = self::_bootFileRow($name);
        $fresh = (
            $row
            && (int)$row->get('size') === $size
            && (int)self::_stampToTime($row->get('mtime')) === $mtime
        );
        if ($fresh) {
            $tagValue = trim((string)$row->get('releaseTag'));
            $tagReason = '';
            if ('' === $tagValue) {
                /**
                 * No stored tag, so ask again rather than reporting a
                 * remembered failure. Two reasons, and both matter:
                 *
                 * the REASON is current-state information -- "attr is not
                 * installed on this server" stops being true the moment
                 * somebody installs it, and telling an admin a stale cause
                 * is how this panel became useless in the first place;
                 *
                 * and it self-heals. A server that could not read the tag
                 * yesterday and can today picks it up on the next listing
                 * without waiting for the file to change.
                 *
                 * This costs one attr call per file that has no tag, and
                 * nothing at all for a file that has one -- which is the
                 * common case on any server where the read works.
                 */
                $again = self::bootFileXattr($path, 'tag_name');
                if ('' !== $again['value']) {
                    $tagValue = $again['value'];
                    self::_bootFileStore($row, ['releaseTag' => $tagValue]);
                } else {
                    $tagReason = $again['reason'];
                }
            }

            return [
                'name' => $name,
                'exists' => true,
                'role' => (string)$row->get('role'),
                'size' => $size,
                'mtime' => $mtime,
                'checksum' => (string)$row->get('checksum'),
                'kernelVersion' => (string)$row->get('kernelVersion'),
                'releaseTag' => $tagValue,
                'tagReason' => $tagReason,
                'pinned' => (bool)(int)$row->get('pinned')
            ];
        }

        $role = self::bootFileRole($path);
        $version = self::bootFileKernelVersion($path);
        $tag = self::bootFileXattr($path, 'tag_name');
        /**
         * An init records its Buildroot version under `version` where a
         * kernel records the kernel version, and a kernel's own banner is
         * more trustworthy than the xattr -- an in-place overwrite leaves
         * FOG's old xattrs on the admin's file, so the xattr can be
         * confidently wrong where the bytes cannot.
         */
        if ('' === $version) {
            $version = self::bootFileXattr($path, 'version')['value'];
        }
        $keptTag = $tag['value'];
        if ('' === $keptTag && $row) {
            $keptTag = (string)$row->get('releaseTag');
        }
        $checksum = (string)@hash_file('sha256', $path);

        self::_bootFileStore(
            $row,
            [
                'name' => $name,
                'size' => $size,
                /**
                 * Written and read back in UTC, explicitly. These two
                 * columns are a cache KEY, not a display value: they are
                 * only ever compared against a fresh stat, so the write and
                 * the read have to agree with each other whatever the
                 * server's zone and the viewer's zone happen to be. Format
                 * through the display zone and parse back through the
                 * default one, and the comparison fails on any server where
                 * those differ -- which would not break anything visibly, it
                 * would just silently re-read every file on every render and
                 * make the cache pointless. The date a human sees is
                 * formatted from the live stat, not from here.
                 */
                'mtime' => gmdate('Y-m-d H:i:s', $mtime),
                'checksum' => $checksum,
                'role' => $role,
                'kernelVersion' => $version,
                'releaseTag' => $keptTag,
                'inspected' => gmdate('Y-m-d H:i:s')
            ]
        );

        return [
            'name' => $name,
            'exists' => true,
            'role' => $role,
            'size' => $size,
            'mtime' => $mtime,
            'checksum' => $checksum,
            'kernelVersion' => $version,
            'releaseTag' => $keptTag,
            'tagReason' => ('' === $keptTag ? $tag['reason'] : ''),
            'pinned' => (bool)($row ? (int)$row->get('pinned') : 0)
        ];
    }
    /**
     * Loads the bootFile row for $name, or null.
     *
     * Answers null rather than throwing when the record store cannot be
     * reached: the records are an accelerator and a place to keep an admin's
     * pin, not the inventory, so a listing must still render without them.
     *
     * @param string $name the filename
     *
     * @return \FOG\Items\BootFile|null
     */
    private static function _bootFileRow($name)
    {
        if (null === self::$_bootFileRows) {
            self::$_bootFileRows = [];
            try {
                /**
                 * Route::getIds(), which is how every other list-all in
                 * this class is done. The first version called
                 * BootFileManager->find(), a 1.5 method that does not exist
                 * on the 1.6 manager, so it threw "undefined method" into
                 * the catch below on every request and the map was never
                 * populated. The cost of that was not one failed query: it
                 * was every file in the boot directory re-read, re-hashed
                 * (264MB on a stock install) and re-saved on every host
                 * and group page, ~1.8s before the first byte.
                 *
                 * `false` for the filter, like HookManager's own lookup:
                 * an empty array asks the request body for a filter, and a
                 * host form POSTs a `name` field, which would filter the
                 * rows down to the one boot file called after the host.
                 */
                $ids = Route::getIds('bootfile', false);
                foreach ((array)$ids as $id) {
                    $row = new \FOG\Items\BootFile((int)$id);
                    if ($row->isValid()) {
                        self::$_bootFileRows[(string)$row->get('name')] = $row;
                    }
                }
            } catch (\Throwable $e) {
                // Left as an empty map, deliberately: the records are an
                // accelerator, and an unreachable store must cost one failed
                // query per request rather than one per file.
                self::$_bootFileRows = [];
            }
        }

        return self::$_bootFileRows[$name] ?? null;
    }
    /**
     * Writes what was just read about a file back to its record.
     *
     * Deliberately silent on failure. Rendering a list of kernels is not the
     * moment to fail a page because a cache write did not land, and the next
     * listing will simply read the file again.
     *
     * @param mixed $row  existing record or null
     * @param array $data field values to store
     *
     * @return void
     */
    private static function _bootFileStore($row, array $data)
    {
        try {
            $obj = $row ?: new BootFile();
            foreach ($data as $field => $value) {
                $obj->set($field, $value);
            }
            $obj->save();
            // Keep the request's map in step, so a second listing in the same
            // request sees what the first one just inspected rather than
            // inspecting it again.
            if (null !== self::$_bootFileRows && !empty($data['name'])) {
                self::$_bootFileRows[(string)$data['name']] = $obj;
            }
        } catch (\Throwable $e) {
            return;
        }
    }
    /**
     * Turns a stored datetime into a unix timestamp, or 0.
     *
     * @param mixed $stamp the stored value
     *
     * @return int
     */
    private static function _stampToTime($stamp)
    {
        $stamp = trim((string)$stamp);
        // validDate() rather than testing for a zero-date literal: it already
        // knows both spellings of one, and there is meant to be exactly one
        // definition of what an empty date is.
        if ('' === $stamp || !self::validDate($stamp)) {
            return 0;
        }
        // ' UTC' because _bootFileStore() writes gmdate(). See the note
        // there: these two have to agree with each other, not with a zone.
        $time = strtotime($stamp . ' UTC');

        return (false === $time) ? 0 : (int)$time;
    }
    /**
     * Marks a boot file as one no pruner may remove, or unmarks it.
     *
     * The one fact about these files that cannot be read off the disk: it is
     * an intention, not a property. Stored on the record, and reported by
     * bootFileInfo() so the listing can show it.
     *
     * @param string $name filename in the boot directory
     * @param bool   $keep true to keep, false to stop keeping
     *
     * @return bool whether the record was written
     */
    public static function bootFileSetPinned($name, $keep)
    {
        $name = basename(trim((string)$name));
        if ('' === $name) {
            return false;
        }
        /**
         * The copy first, then the flag.
         *
         * The copy is what actually protects the file, so a flag written
         * without one would promise something nothing delivers -- and on the
         * next upgrade the file would be gone with the record still saying
         * it was kept. Unpinning goes the same way round for the same
         * reason: remove the copy, then say it is no longer kept.
         */
        if (!self::_bootFileKeepCopy($name, $keep)) {
            return false;
        }
        try {
            $row = self::_bootFileRow($name) ?: new BootFile();
            $row->set('name', $name)
                ->set('pinned', $keep ? 1 : 0);
            $row->save();
            if (null !== self::$_bootFileRows) {
                self::$_bootFileRows[$name] = $row;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }
    /**
     * Puts a copy of a boot file where an upgrade will find it, or removes it.
     *
     * `customizations/kernel-backups/keep/` -- and the copy is the EFFECT of
     * the pin, not a record of it. `bfPinned` holds the judgment, per ADR
     * 0042; this is what the judgment does.
     *
     * That distinction is what keeps it on the right side of the same ADR's
     * no-manifest rule. The pruner and the restore are shell functions running
     * while the web root is being rebuilt, with no database in reach, so the
     * alternative was a manifest for them to read -- and a manifest is data
     * ABOUT files, which can drift from them. A copy is a second set of the
     * same bytes: it needs no parsing and cannot drift, which is the same
     * reasoning restorekernel.sh gives for using xattrs instead of a manifest.
     *
     * It also earns its space: the web root is deleted and rebuilt on every
     * install, and a per-release sibling is deliberately not part of a
     * generation, so without this a kept sibling survives exactly until the
     * next upgrade.
     *
     * Known limit, inherited rather than introduced: the source path is the
     * local one, the same path the listing reads. On a deployment where
     * FOG_TFTP_HOST is another machine the listing is already reading a
     * different disk from the one kernelfetch() writes, and this follows the
     * listing -- what the admin clicked Keep on is what they were shown.
     *
     * @param string $name filename in the boot directory
     * @param bool   $keep true to place the copy, false to remove it
     *
     * @return bool
     */
    private static function _bootFileKeepCopy($name, $keep)
    {
        if (!defined('FOG_BASE_DIR')) {
            // No installer-written paths file, so there is no customizations
            // tree to copy into. Nothing to do rather than a failure: a
            // source checkout has no boot directory to protect either.
            return true;
        }
        $keepDir = FOG_BASE_DIR . DS . 'customizations' . DS
            . 'kernel-backups' . DS . 'keep';
        $target = $keepDir . DS . $name;
        if (!$keep) {
            if (!is_file($target)) {
                return true;
            }

            return @unlink($target);
        }
        if (!is_dir($keepDir)) {
            // Created by the installer, deliberately not here: it is created
            // once, owned by the service user and group-writable to the web
            // user, and a directory the web tier makes for itself would own
            // it instead.
            return false;
        }
        $dir = trim((string)self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
        $source = $dir . DIRECTORY_SEPARATOR . $name;
        if ('' === $dir || !is_file($source)) {
            return false;
        }
        if (is_file($target) && self::_sameFile($source, $target)) {
            return true;
        }

        return @copy($source, $target);
    }
    /**
     * Whether two paths hold the same bytes.
     *
     * Size first, because it settles almost every case without reading
     * either file, and these are kernels.
     *
     * @param string $a first path
     * @param string $b second path
     *
     * @return bool
     */
    private static function _sameFile($a, $b)
    {
        if (filesize($a) !== filesize($b)) {
            return false;
        }

        return hash_file('sha256', $a) === hash_file('sha256', $b);
    }
    /**
     * Removes a boot file, and the record that described it.
     *
     * Over the same SSH/SFTP connection kernelfetch() uses to put files
     * there, not with unlink(). That is where the writes go, FOG_TFTP_HOST
     * may not be this machine, and using the write path for the delete means
     * the two cannot disagree about which directory is real. It also means
     * a server with no SSH credentials configured cannot delete -- which is
     * the same server that cannot download a kernel either, so it is one
     * missing configuration rather than a new one.
     *
     * The record goes too. Leaving it would strand a row describing a file
     * that is gone; the listing starts from the directory, so nothing would
     * ever read it again.
     *
     * @param string $name filename in the boot directory
     *
     * @throws \Exception when the file cannot be removed
     *
     * @return void
     */
    public static function bootFileRemove($name)
    {
        $name = basename(trim((string)$name));
        $dir = trim((string)self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
        if ('' === $name || '' === $dir) {
            throw new \Exception(_('No such file in the boot directory.'));
        }
        $target = sprintf('/%s/%s', trim($dir, '/'), $name);
        $keys = [
            'FOG_TFTP_FTP_PASSWORD',
            'FOG_TFTP_FTP_USERNAME',
            'FOG_TFTP_HOST'
        ];
        list($pass, $user, $host) = self::getSetting($keys);
        self::$FOGSSH->username = $user;
        self::$FOGSSH->password = $pass;
        self::$FOGSSH->host = $host;
        if (!self::$FOGSSH->connect()) {
            throw new \Exception(_('Unable to connect to ssh'));
        }
        $gone = self::$FOGSSH->delete($target);
        self::$FOGSSH->disconnect();
        if (!$gone) {
            throw new \Exception(
                sprintf(_('%s could not be removed.'), $name)
            );
        }
        try {
            $row = self::_bootFileRow($name);
            if ($row) {
                $row->destroy();
            }
            if (null !== self::$_bootFileRows) {
                unset(self::$_bootFileRows[$name]);
            }
        } catch (\Throwable $e) {
            // The file is gone, which is what was asked. A stranded record
            // is never read again -- listings start from the directory.
            return;
        }
    }
    /**
     * Lists the boot directory files holding the role $type, newest first.
     *
     * Kernels and inits are files on disk, not database records, so there is
     * nothing for buildSelectBox() to enumerate. Reading the directory is the
     * only way to know what an admin can legitimately choose -- and since the
     * installer now leaves the outgoing kernel behind as bzImage.<release>
     * on every update, that directory is exactly the list of "current, or any
     * version still on this server, or anything I put here myself".
     *
     * The role is decided by bootFileRole(), so a field asking for a kernel
     * is offered kernels only. One list serving both the Host Kernel field
     * and FOG_MEMTEST_KERNEL is what put memdisk in the kernel dropdown.
     *
     * @param string $type 'kernel', 'init' or 'payload'
     *
     * @return array filenames
     */
    public static function kernelFileList($type = 'kernel')
    {
        /**
         * An empty list is a legitimate answer, and kernelFileSelect() turns
         * it into a plain text input -- so a server whose boot directory has
         * moved stays editable. A settings read that cannot answer at all is
         * the same situation from the caller's point of view, and one field
         * out of the thirty on a mass edit form must not take the form down
         * with it.
         */
        try {
            $dir = trim((string)self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
        } catch (\Throwable $e) {
            return [];
        }
        if (empty($dir) || !is_dir($dir) || !is_readable($dir)) {
            return [];
        }
        $files = @scandir($dir);
        if ($files === false) {
            return [];
        }
        switch ($type) {
            case 'init':
                $want = self::BOOT_ROLE_INIT;
                break;
            case 'payload':
                $want = self::BOOT_ROLE_PAYLOAD;
                break;
            default:
                $want = self::BOOT_ROLE_KERNEL;
                break;
        }
        $out = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            /**
             * bootFileInfo(), not bootFileRole(): the role is the same answer
             * either way, but going through the record means a render costs a
             * stat and one query rather than a 4KiB read of every file in the
             * directory, on every host form, group form, mass edit modal and
             * settings page.
             */
            if (self::bootFileInfo($path)['role'] === $want) {
                $out[] = $file;
            }
        }
        /**
         * Plain names first, then their versioned siblings, so bzImage sits
         * above bzImage.20260806-111046 instead of being sorted into the
         * middle of them.
         */
        usort(
            $out,
            function ($a, $b) {
                $adot = substr_count($a, '.');
                $bdot = substr_count($b, '.');
                if ($adot !== $bdot) {
                    return $adot - $bdot;
                }
                return strnatcasecmp($b, $a);
            }
        );

        return $out;
    }
    /**
     * Builds a select of the kernel/init files present on disk.
     *
     * Falls back to a plain text input when the directory cannot be read, so
     * a server whose kernel directory has moved is still editable rather than
     * presenting an empty, unusable dropdown.
     *
     * @param string $name    field name/id
     * @param string $current the currently stored value
     * @param string $type    'kernel' or 'init'
     * @param string $class   css classes for the element
     *
     * @return string
     */
    public static function kernelFileSelect(
        $name,
        $current = '',
        $type = 'kernel',
        $class = 'form-control',
        $id = '',
        $blankLabel = ''
    ) {
        $current = trim((string)$current);
        if ($id === '') {
            $id = $name;
        }
        $files = self::kernelFileList($type);
        if (count($files) < 1) {
            return self::makeInput(
                $class,
                $name,
                $type === 'init' ? 'customInit.xz' : 'bzImage_Custom',
                'text',
                $id,
                $current
            );
        }
        /**
         * A stored value naming a file that is not on disk -- or one the
         * classifier does not recognize as this role -- must still appear and
         * must still be what the form posts. Dropping it would silently
         * rewrite the host's kernel to the default the moment anyone opened
         * the form. It is shown in the manual box below, with a note, rather
         * than as an option that pretends the file is there.
         */
        $manualValue = ($current !== '' && !in_array($current, $files, true));
        /**
         * The blank option is load-bearing, not filler. On a host or group an
         * empty kernel/init means "inherit the global default", so it must be
         * present, must be first, and must never be pre-selected -- otherwise
         * simply opening the form and saving would pin every inheriting host
         * to a specific kernel. Callers pass a label saying so, because
         * "Please select an option" reads as though a choice is required.
         */
        $opts = '<option value="">- '
            . (
                $blankLabel !== '' ?
                \Initiator::e($blankLabel) :
                self::$foglang['PleaseSelect']
            )
            . ' -</option>';
        foreach ($files as $file) {
            $opts .= '<option value="'
                . \Initiator::e($file)
                . '"'
                . ($file === $current ? ' selected' : '')
                . '>'
                . \Initiator::e($file)
                . '</option>';
        }
        /**
         * The typed failsafe. A dropdown can only offer what the classifier
         * recognized, and an admin running a kernel it does not recognize --
         * or about to copy one in -- still has to be able to name it. This is
         * also the escape hatch if the classifier is ever wrong.
         *
         * The TEXT INPUT carries the field name and is what posts, always;
         * the select has no name and is a picker that writes into it. So with
         * no JavaScript the field degrades to the free-text box it was before
         * the dropdowns landed, rather than to nothing.
         */
        $opts .= '<option value="' . self::BOOT_MANUAL_VALUE . '"'
            . ($manualValue ? ' selected' : '')
            . '>- '
            . _('Enter a name manually')
            . ' -</option>';

        return '<div class="fog-bootfile">'
            . '<select class="'
            . $class
            . ' fog-select2 fog-bootfile-picker" data-target="'
            . \Initiator::e($id)
            . '" id="'
            . \Initiator::e($id)
            . '-picker" autocomplete="off">'
            . $opts
            . '</select>'
            . '<input type="text" class="'
            . $class
            . ' fog-bootfile-value mt-1'
            . ($manualValue ? '' : ' d-none')
            . '" name="'
            . \Initiator::e($name)
            . '" id="'
            . \Initiator::e($id)
            . '" value="'
            . \Initiator::e($current)
            . '" placeholder="'
            . ($type === 'init' ? 'customInit.xz' : 'bzImage_Custom')
            . '" autocomplete="off">'
            . (
                $manualValue ?
                '<small class="form-text text-warning fog-bootfile-note">'
                . _('This name is not a recognized file in the boot directory.')
                . '</small>' :
                ''
            )
            . '</div>';
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
            . 'value="' . \Initiator::e($value) . '" '
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
            . \Initiator::e($value)
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

        $this->title = sprintf(
            _('Export %s'),
            ucfirst(strtolower($this->childClass)) . 's'
        );

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
        /**
         * Secrets never leave through the export, for the same reason they
         * never leave through the API.
         *
         * The export posts to ?node=<x>&sub=getExportList -- the MANAGEMENT
         * route -- so Route's emitter stripping never saw it, and the CSV
         * shipped every user's API token and password hash, every host token,
         * and a storage node's FTP password and key in the clear. That last
         * pair is the GHSA-2hqx-5ffg-w4c3 credential class: holding it is
         * holding the node. `visible: false` on the DataTables column hides
         * the value on screen and does nothing to the payload.
         *
         * Same defect shape as GH-1323 -- one emitter stripped and its
         * sibling did not -- so the fix is the same: strip AT the emitter,
         * from the one list. unfilterableFields() unions both sensitive tiers
         * and is fed by API_SENSITIVE_FIELDS, so a plugin's own secrets are
         * covered here too and the two emitters cannot drift apart.
         */
        $secretColumns = Route::unfilterableFields($this->childClass);
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
            if (in_array($common, $secretColumns, true)) {
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
     * are honored because the request is passed straight to
     * FOGManagerController::simple(); forcing length=-1 drops the SQL LIMIT.
     *
     * The header row uses the friendly column keys, which are exactly the
     * tokens the CSV importer recognizes, so the file round-trips through
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
