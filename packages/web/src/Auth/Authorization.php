<?php
/**
 * Authorization service — native role-based permission checks.
 *
 * PHP version 7.4+
 *
 * @category Authorization
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Auth;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

/**
 * Authorization service — native role-based permission checks.
 *
 * Permissions are strings of the form '<node>.<action>' (e.g. 'host.edit'),
 * a node wildcard ('host.*'), or the global wildcard '*'. A user's
 * permissions are the union across all roles assigned to them.
 *
 * Access is deny-by-default: a user holds exactly what their roles grant,
 * and an account with no role can do nothing. Earlier 1.6 betas treated
 * "no role" as an implicit administrator so that adopting RBAC could not
 * lock an install out; schema step 316 converted those accounts to
 * explicit roles and the fallback was removed.
 *
 * @category Authorization
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Authorization extends FOGBase
{
    /**
     * Page nodes that are never permission-checked (pre-auth surfaces,
     * client/service endpoints, the dashboard, and the schema updater
     * which carries its own install-token gate).
     *
     * @var array
     */
    const EXEMPT_NODES = [
        'home',
        'client',
        'schema',
        'hwinfo',
        'logout',
        'login',
        // Impersonation carries its own gate, the same way `schema` does.
        //
        // Two reasons it cannot be an ordinary registry node. LEAVING must
        // never be permission-checked at all: impersonate a user holding no
        // roles and a gated exit path traps the administrator as them, with
        // no way back short of clearing a cookie. And ENTERING is not
        // answered by a role -- Identity::mayImpersonate() runs two subset
        // tests that a permission string cannot express. The page checks
        // Identity::PERMISSION itself before starting, so the capability is
        // still grantable and withholdable; it is the exit that is
        // structurally unreachable by a check.
        'impersonate'
    ];
    /**
     * Page node => registry node aliases.
     *
     * @var array
     */
    const NODE_ALIASES = [
        'about' => 'settings',
        // The API reference lays out every class and field the API exposes,
        // which is the same class of server information the settings pages
        // carry, so it takes the same gate rather than a node of its own.
        'apidocs' => 'settings',
        // The log viewer moved out of the About page into its own node so it
        // could sit under Logging with Activity and the Audit Log. It maps
        // onto the SAME gate `about` does, deliberately: relocating a page in
        // the sidebar must not change who can open it, and a node of its own
        // would have been a widening or a narrowing depending on who holds
        // what. Compare `audit`, which DOES have its own node -- that was a
        // deliberate access decision (ADR 0021), and this is not one.
        'logviewer' => 'settings'
    ];
    /**
     * Report class => registry node, for reports whose data is not
     * "a report" in the permission sense.
     *
     * Every report is one page node (`report`) selected by a base64 `f`
     * parameter, so without this they necessarily share one gate -- which
     * is how a helpdesk grant for an imaging report also handed over a
     * movement log for every named employee (ADR 0023).
     *
     * Keys are the decoded report name, lowercased and underscored, which
     * is what FOGPageManager::loadPageClasses() turns `f` into. A report
     * absent from here keeps the `report` node, so an uploaded custom
     * report behaves exactly as it always has.
     *
     * @var array
     */
    const REPORT_NODES = [
        'hosts_and_users' => 'usertracking',
        // Run History reads the five work-item tables through
        // ActivityWindow (ADR 0022 decision 4). It is task activity, and
        // Task Management's own log pane is gated on task.view, so a
        // report.view grant reading the same rows through a different
        // screen is the defect ADR 0023 opens with. Narrows against the
        // default `report` node; nothing anyone holds gets wider.
        'run_history' => 'task',
        // Imaging Report reads `taskLog` -- the same rows Task Management's
        // log pane shows -- so it lands on the same node as Run History for
        // the same reason (ADR 0030 decision 4).
        'imaging_report' => 'task',
        // Snapin Report reads `snapinTasks`, which Snapin Management gates
        // on snapin.view. Same reasoning as the two above (ADR 0030
        // decision 4).
        'snapin_report' => 'snapin',
        // Software Report reads `softwareStatus`, which Software Management
        // gates on software.view. Same reasoning.
        'software_report' => 'software',
        // Fleet Report reads `hosts` and `inventory`, both gated on
        // host.view everywhere else. Same reasoning (ADR 0030 decision 4).
        'fleet_report' => 'host',
        // Hardware Report reads `inventory`, and Authorization already maps
        // the `inventory` node onto `host`. Same reasoning (ADR 0030
        // decision 4).
        'hardware_report' => 'host',
        // Installed Software reads `hostSoftware` (design 0006), which is
        // host data -- the same table the host's own Installed Software tab
        // reads, gated on host.view. Same reasoning.
        'installed_software' => 'host',
        'directory_membership' => 'host',
        'printer_deployment' => 'host',
        'user_sessions' => 'host',
        // Storage Report reads `images`, `imageGroupAssoc`, `nfsGroups` and
        // `nfsGroupMembers`. Group and node names are the part not already
        // reachable from a narrower screen, so it lands on storagenode.
        // Same reasoning (ADR 0030 decision 4).
        'storage_report' => 'storagenode',
        // Audit Report reads `auditLog`. Not a narrowing of convenience:
        // an audit row necessarily discloses attempted usernames, which is
        // why ADR 0021 gave audit.view its own permission. Serving the same
        // rows under report.view would hand that to every report holder.
        'audit_report' => 'audit'
    ];
    /**
     * Exact sub overrides that the naming conventions would misresolve.
     * Shape: node => [sub => permission]. A permission is either a full
     * permission string or ['GET' => perm, 'POST' => perm] when the same
     * sub reads on GET but mutates (task cancel) on POST.
     *
     * @var array
     */
    const SUB_OVERRIDES = [
        'task' => [
            'active' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activemulticast' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activesnapins' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activescheduled' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activescheduleddels' => ['GET' => 'task.view', 'POST' => 'task.task']
        ],
        'host' => [
            // Bulk group membership from the host list. group.EDIT, not
            // group.create: adding existing hosts to existing groups is an
            // edit to a group, and requiring the permission to mint groups
            // for it means an operator who may label hosts must also be able
            // to create them -- which is backwards once a group is the
            // labeling primitive (ADR 0038 decisions 16 and 16a.6).
            //
            // The route-level permission is the FLOOR, i.e. what every call
            // needs. The privileged case -- groups_new[] naming a group that
            // does not exist yet -- is checked inside the handler against
            // group.create, because "am I creating a group" is a property of
            // the request body rather than of the route. Same reasoning, and
            // the same shape, as savedfilters' global case below.
            'savegroup' => 'group.edit',
            // The Login History tab. _subToAction() reads the 'get' prefix
            // and answers host.view, which is the grant nearly every
            // operator holds -- so per-host login records for named people
            // sat behind it. The tab itself is hidden to match.
            'getloginhist' => 'usertracking.view'
        ],
        'group' => [
            'getloginhist' => 'usertracking.view'
        ],
        // Uploading a plugin archive introduces new executable code to the
        // server; activating one that is already on disk does not. Without
        // these both would land on plugin.edit, because _subToAction() has no
        // reason to know the difference -- 'install' matches none of its
        // prefixes and a POST falls through to 'edit'. A role that may switch
        // plugins on must not thereby be able to add one (ADR 0009).
        'plugin' => [
            'installarchive' => 'plugin.install',
            'installarchivecommit' => 'plugin.install'
        ],
        'image' => [
            'sessioncreate' => 'image.task',
            'sessioncancel' => 'image.task',
            'sessioncreatemodal' => 'image.task'
        ],
        // The Bearer token card on the user's API tab. Gated on apitoken.*
        // rather than the user.edit that reaches the page, for the same
        // reason the central pane is: "can edit users" and "can revoke every
        // credential this account holds" are different powers. Without
        // these, _subToAction() reads the sub's prefix and lands them on
        // user.view/user.edit, so anyone who could open the page could
        // revoke the tokens on it.
        // Keyed by the ALIASED node, so these are the FOG Configuration page's
        // subs -- `about` resolves to `settings` above.
        'settings' => [
            // Reading the certificate hierarchy is reading server
            // configuration, so the GET stays where the rest of the page is.
            // The POST is not: it imports a root CA into this host's trust
            // store and writes preferences into a file root SOURCES AS SHELL
            // on the next installer run. "May change a setting" is not
            // self-evidently "may decide what this server trusts", and SIX
            // page nodes already map onto settings.edit (GH-1121).
            'certificates' => ['GET' => 'settings.view', 'POST' => 'system.pki'],
            /**
             * The Local files tab's actions. Viewing the tab is reading
             * server configuration and stays on settings.view through
             * _subToAction(); acting on a file is not, so each says so.
             *
             * Node-scoped, NOT in GLOBAL_SUB_OVERRIDES. The download subs
             * are global only because the download JS requests them with no
             * node at all and they dispatch under the exempt 'home' node --
             * a shape worth not repeating, so these post with node=about and
             * resolve here.
             *
             * Three subs rather than one endpoint taking an action
             * parameter, for the reason the API token grid gives: separate
             * subs CAN each carry their own permission later. Deleting a
             * bootable kernel and pointing a default at one are not
             * self-evidently the same power, even though both need
             * settings.edit today.
             */
            'bootfilekeep' => 'settings.edit',
            'bootfiledefault' => 'settings.edit',
            'bootfiledelete' => 'settings.edit'
        ],
        'user' => [
            'userapitokenlist' => 'apitoken.view',
            'userapitokenenable' => 'apitoken.edit',
            'userapitokendelete' => 'apitoken.delete',
            'issueapitoken' => 'apitoken.create'
        ]
    ];
    /**
     * Node-independent sub overrides, checked before the exempt-node
     * bail-out: kernelfetch/initrdfetch live on the FOGPage base class and
     * the download JS requests them with no node at all, so they dispatch
     * under the exempt 'home' node and node-scoped overrides never fire.
     *
     * @var array
     */
    const GLOBAL_SUB_OVERRIDES = [
        'kernelfetch' => 'settings.edit',
        'initrdfetch' => 'settings.edit',
        // The API token pane lives under FOG Configuration for where an
        // administrator looks, not for who may see it: it is gated on
        // apitoken.*, not on that node's own permission. Overridden here
        // rather than by moving the page, because the alternative is
        // settings.edit deciding who can read a census of every credential
        // on the server -- and SIX page nodes already map onto that one
        // permission.
        //
        // apitokens resolves to view for BOTH verbs; a global override
        // cannot vary by method. The grid's own actions are separate subs
        // precisely so each CAN carry its own permission, rather than one
        // POST endpoint deciding internally which of three grants it needs.
        'apitokens' => 'apitoken.view',
        'apitokenlist' => 'apitoken.view',
        'apitokenenable' => 'apitoken.edit',
        'apitokendelete' => 'apitoken.delete',
        'issueapitokenfor' => 'apitoken.create'
    ];
    /**
     * API route name => permission action, for the class-parameterized
     * routes (Route::$validClasses expansions). The route names already
     * encode the HTTP method, so no method split is needed here.
     *
     * @var array
     */
    const API_ROUTE_ACTIONS = [
        'list' => 'view',
        'indiv' => 'view',
        'search' => 'view',
        'count' => 'view',
        'names' => 'view',
        'ids' => 'view',
        'active' => 'view',
        'create' => 'create',
        'join' => 'create',
        'update' => 'edit',
        'delete' => 'delete',
        'task' => 'task',
        'cancel' => 'task'
    ];
    /**
     * Fixed (non class-parameterized) API route name => full permission.
     * null = no check beyond the authentication the Route constructor
     * already enforces (or none at all for the unauthenticated routes:
     * status, bandwidth). whoami only echoes .fogsettings server facts.
     *
     * @var array
     */
    const API_ROUTE_PERMISSIONS = [
        'status' => null,
        'bandwidth' => null,
        'whoami' => null,
        'unisearch' => null,
        // A user's own preferences. null is "authenticated is enough", the
        // same as whoami above, and for the same reason: neither route can
        // address anything but the caller. The user id comes from the
        // session and the path has no place to put a different one, so there
        // is no object here for an object permission to be about.
        'userprefs' => null,
        'userpref' => null,
        // Saved grid filters. null for the same reason as userpref: the
        // acting user comes from the session and the path has no place to put
        // a different one, so every read and write is already scoped to the
        // caller by the manager.
        //
        // The privileged case -- saving a filter for EVERYONE -- is checked
        // inside the handler against savedfilter.*, because "global" is a
        // property of the request body rather than of the route. A single
        // route-level permission would have to be the strictest of the two
        // cases, which would take private filters away from everybody.
        'savedfilters' => null,
        'savedfilter' => null,
        // Gated per section inside the handler against user.view,
        // usergroup.view and role.view, so it can disclose nothing the caller
        // could not already list.
        'savedfiltertargets' => null,
        // The API description, and its swagger.json alias. Public for the
        // same reason status/info are: a client should be able to discover
        // what it is talking to before it has credentials. Both expose only
        // the shape of the API -- class names, field names, types, all
        // already public in the source -- and no data. Declared here rather
        // than left to the unknown-route fallback so the intent is recorded.
        'openapi' => null,
        'openapiSwaggerAlias' => null,
        // fog-agent enrollment: public by construction (the caller is asking
        // for its credential), and it decides nothing an admin did not
        // approve -- see FOG\Agent\Enrollment. The admin side reads and
        // edits hosts, so it carries the host permissions.
        'agentenroll' => null,
        'agentpoll' => null,                 // fog-agent: gated by the client certificate in Route, not by a token
        'agentrenew' => null,                // fog-agent: same gate
        'agentresult' => null,               // fog-agent: same gate
        'agentpayload' => null,              // fog-agent: same gate
        'agentenrollments' => 'host.view',
        'agentenrollmentdecide' => 'host.edit',
        // Tokens approve machines the admin has not seen, which creates
        // hosts; minting one is host.create and pulling one is host.delete.
        'agenttokens' => 'host.view',
        'agenttokenmint' => 'host.create',
        'agenttokenrevoke' => 'host.delete',
        'export' => 'system.export',
        'kernelUpdate' => 'settings.view',
        'initrdUpdate' => 'settings.view',
        'logfiles' => 'settings.view',
        'pendingmacs' => 'host.view',
        'snapinCreateWithFile' => 'snapin.create',
        'uploadSnapinFiles' => 'snapin.create',
        // The node the web Install button checks, rather than plugin.edit:
        // installing runs a plugin's schema migrations, which is a
        // different authority from editing its row.
        'pluginInstall' => 'plugin.install',
        'settingsCacheView' => 'settings.view',
        'settingsCacheFlush' => 'settings.edit',
        'settingsCacheRefresh' => 'settings.edit'
    ];
    /**
     * API model class => registry node. Sub-entities check their parent
     * entity's permission (a host's power management schedule is host
     * data, an imaging log is report data, ...). An action absent from
     * the registry node (e.g. task.delete for lookup-table writes) is
     * still a valid permission string but only the global '*' grants it,
     * which deliberately reserves those writes for full administrators.
     *
     * @var array
     */
    const API_CLASS_ENTITIES = [
        // Architectures sit under Image Management, alongside the other two
        // image lookup tables (imagetype, imagepartitiontype) and os, and are
        // reached through the same Architectures page -- so they are governed
        // by the same node rather than by one of their own. A node exists to
        // be granted separately; nobody grants "may edit the list of CPU
        // architectures" without also granting image management.
        'architecture' => 'image',
        'filedeletequeue' => 'task',
        'group' => 'group',
        'groupassociation' => 'group',
        'history' => 'report',
        'hookevent' => 'settings',
        'host' => 'host',
        'hostautologout' => 'host',
        'hostscreensetting' => 'host',
        'image' => 'image',
        'imageassociation' => 'image',
        'imagepartitiontype' => 'image',
        'imagetype' => 'image',
        'inventory' => 'host',
        'ipxe' => 'ipxe',
        'keysequence' => 'ipxe',
        'macaddressassociation' => 'host',
        'module' => 'module',
        'moduleassociation' => 'module',
        'multicastsession' => 'task',
        'multicastsessionassociation' => 'task',
        'nodefailure' => 'task',
        'notifyevent' => 'settings',
        'os' => 'image',
        'oui' => 'settings',
        'plugin' => 'plugin',
        'powermanagement' => 'host',
        'printer' => 'printer',
        'printerassociation' => 'printer',
        'pxemenuoptions' => 'ipxe',
        'role' => 'role',
        'rolepermission' => 'role',
        'roleuserassociation' => 'role',
        'scheduledtask' => 'task',
        'setting' => 'settings',
        'site' => 'site',
        'snapin' => 'snapin',
        'snapinassociation' => 'snapin',
        'snapingroupassociation' => 'snapin',
        'snapinjob' => 'task',
        'snapintask' => 'task',
        // The agent's reported facts about one host, gated by the host node
        // they belong to -- the same call as 'inventory' above, and for the
        // same reason: they are host detail, not a feature of their own.
        'hostsoftware' => 'host',
        'hostdirectory' => 'host',
        'hostprinter' => 'host',
        'hostspooler' => 'host',
        'hostnetwork' => 'host',
        'hostusersession' => 'host',
        // A wake relay names two hosts and belongs to neither more than
        // the other. Gated on the host node all the same: seeing that a
        // machine was asked to wake another is seeing host detail, and
        // ordering the wake goes through the host's own page.
        'agentwake' => 'host',
        'hostfactstate' => 'host',
        'software' => 'software',
        'softwareassociation' => 'software',
        'groupsoftwareassociation' => 'software',
        'softwarestatus' => 'software',
        'storagegroup' => 'storagegroup',
        'storagenode' => 'storagenode',
        'task' => 'task',
        'tasklog' => 'task',
        'taskstate' => 'task',
        'tasktype' => 'task',
        'user' => 'user',
        'usergroup' => 'usergroup',
        'usergroupmember' => 'usergroup',
        'roleusergroupassociation' => 'usergroup',
        'usertracking' => 'usertracking'
    ];
    /**
     * Per-user permission cache for this request.
     * userID => array of permission strings.
     *
     * @var array
     */
    private static $_permCache = [];
    /**
     * Per-node [table, idColumn] cache for the plugin object-scope check.
     * node => [table, idCol], or node => null when the node has no model.
     *
     * @var array
     */
    private static $_scopeClassVars = [];
    /**
     * Reentrancy guard for the plugin object-scope events. True while one
     * is being dispatched; see _pluginScopeApplies().
     *
     * @var bool
     */
    private static $_inPluginScope = false;
    /**
     * Memoized permission registry.
     *
     * @var array|null
     */
    private static $_registry = null;
    /**
     * Page nodes already reported as unregistered this request, so the
     * diagnostic in resolvePagePermission() is logged once per node rather
     * than once per call. buildMainMenuItems() resolves a permission for
     * every menu entry on every page load, so an unbounded log line there
     * would fill the error log with duplicates of a single misconfiguration.
     *
     * @var array
     */
    private static $_unmappedLogged = [];
    /**
     * Permissions declared by plugin-contributed routes, name => permission.
     *
     * A null value means "no permission check", and Route only ever passes
     * null for a route it also declared public -- an unauthenticated caller
     * has no permissions to check, so demanding one would deny every request
     * including the ones the route exists to serve.
     *
     * Populated in Route::defineRoutes(), which is the one place a route and
     * its permission are decided together. A route absent from here reaches
     * the unmapped branch of resolveApiPermission() and is denied.
     *
     * @var array
     */
    private static $_declaredRoutePermissions = [];
    /**
     * Exempt nodes after the plugin hook, or null before it has fired.
     *
     * @var array|null
     */
    private static $_exemptNodes = null;
    /**
     * The permission registry: registry node => list of valid actions.
     *
     * Plugins can add their own nodes/actions through the
     * PERMISSION_REGISTRY_DATA hook event; registered entries appear in
     * the role edit permission matrix. A page node absent from the
     * registry is not permission-checked at all (compatibility stance for
     * plugin pages that predate this system).
     *
     * @return array
     */
    public static function registry()
    {
        if (null !== self::$_registry) {
            return self::$_registry;
        }
        $registry = self::coreRegistry();
        // Memoized with the CORE registry BEFORE the event fires, and again
        // with the plugin-augmented one after. can() now consults this on
        // every call, and processEvent() reaches Route::getIds('hookevent'),
        // so firing the event can re-enter anything that asks a permission
        // question -- which without the pre-set would arrive here with
        // $_registry still null and fire the event again, forever. Same
        // construction, and the same reasoning, as
        // Route::sensitiveFieldMap(). A re-entrant caller sees the core
        // registry, never a smaller one, so it can only miss a PLUGIN node
        // for the duration of that one nested call -- and a node missing
        // from the registry is left alone by can()'s check rather than
        // denied.
        self::$_registry = $registry;
        self::$HookManager->processEvent(
            'PERMISSION_REGISTRY_DATA',
            ['registry' => &$registry]
        );
        return self::$_registry = $registry;
    }
    /**
     * The nodes core itself owns, before any plugin has contributed.
     *
     * Split out of registry() so purgePermissions() has something to test
     * against that a plugin cannot influence. Deliberately NOT the
     * post-hook result: a plugin that registered a node must not thereby
     * be able to make that node unpurgeable, which is exactly backwards.
     *
     * @return array node => list of valid actions
     */
    public static function coreRegistry()
    {
        return [
            'host' => ['view', 'create', 'edit', 'delete', 'task'],
            'group' => ['view', 'create', 'edit', 'delete', 'task'],
            'image' => ['view', 'create', 'edit', 'delete', 'task'],
            'snapin' => ['view', 'create', 'edit', 'delete'],
            'software' => ['view', 'create', 'edit', 'delete'],
            'printer' => ['view', 'create', 'edit', 'delete'],
            'module' => ['view', 'create', 'edit', 'delete'],
            'user' => ['view', 'create', 'edit', 'delete'],
            // API tokens are their own node rather than an extension of
            // user.*, because "can edit users" and "can inventory every API
            // credential on this server" are different powers and only one
            // of them is a credential census.
            //
            // The four actions are deliberately separate, and delete is the
            // one to hand out narrowly: DISABLE IS REVERSIBLE AND DELETE IS
            // NOT. A mass-disable is downtime somebody undoes with one
            // click; a mass-delete means every integration on the estate
            // needs a fresh token minted and redeployed to wherever it is
            // configured, and nothing can bring the old ones back. Same
            // grant for both would price them the same.
            //
            // create is issuing on behalf of another user -- see
            // FOGConfigurationPage::apitokens(), which hands the plaintext
            // to the issuer rather than the owner, which is why it is not
            // folded into edit.
            'apitoken' => ['view', 'create', 'edit', 'delete'],
            'usergroup' => ['view', 'create', 'edit', 'delete'],
            // GLOBAL saved grid filters only.
            //
            // The node governs the shared namespace, not filters in general.
            // Anybody signed in may create, rename, delete and SHARE their
            // own filters without holding anything here: those reach only the
            // people they name, the route derives the owner from the session,
            // and requiring a grant would silently remove the feature from
            // everyone on upgrade until roles were edited.
            //
            // A global filter is different in kind: it appears in every
            // user's picker on that grid, so somebody has to be trusted with
            // it. Split three ways for the same reason apitoken is --
            // removing a filter the whole site has built a habit around is a
            // bigger act than adding one, and 'view' is absent because
            // everyone can already see a global filter by definition.
            'savedfilter' => ['create', 'edit', 'delete'],
            'role' => ['view', 'create', 'edit', 'delete'],
            // Sites came in from the site plugin. The node keeps the name
            // the plugin registered so grants written against it survive
            // the move -- a rename here would silently drop every existing
            // site.* permission on upgrade.
            'site' => ['view', 'create', 'edit', 'delete'],
            'storagenode' => ['view', 'create', 'edit', 'delete'],
            'storagegroup' => ['view', 'create', 'edit', 'delete'],
            'ipxe' => ['view', 'create', 'edit', 'delete'],
            // create/edit/delete are here because they are REACHABLE, not
            // because anything new was opened up. Eleven classes map onto
            // this node -- task, tasklog, taskstate, tasktype, snapinjob,
            // snapintask, scheduledtask, multicastsession and friends -- and
            // the generic CRUD routes have always answered for all of them.
            // Undeclared, they could be performed only by a holder of '*'
            // and granted to nobody, because assertCanGrant() refuses a
            // permission the registry cannot name. Declaring them takes
            // nothing away and makes them delegable.
            'task' => ['view', 'create', 'edit', 'delete', 'task'],
            'service' => ['view', 'edit'],
            // Same reason: hookevent, notifyevent, oui and setting all map
            // here and all four take create/join/delete.
            'settings' => ['view', 'create', 'edit', 'delete'],
            // history is the one report-entity class that is also a
            // writable table.
            'report' => ['view', 'create', 'edit', 'delete'],
            // User tracking is a movement log for named people, not a
            // report about equipment, and it is split out of `report` for
            // that reason alone (ADR 0023). Everything that reads it -- the
            // Hosts And Users report, the Login History tabs on host and
            // group, the REST class -- resolves here. No `create`: rows come
            // from the fog-client's own endpoint, which is node `client` and
            // permission-exempt, so nothing legitimate POSTs one.
            'usertracking' => ['view'],
            // The activity viewer. A node of its own rather than an alias
            // onto 'report': aliasing would hand every existing report.view
            // holder the log viewer as a side effect of an upgrade, which is
            // a widening nobody asked for. New node, nobody holds it, only
            // '*' works until an administrator grants it -- deny by default,
            // the same stance the rest of this registry takes.
            'activity' => ['view'],
            // The audit trail. `manage` is separate from `view` because the
            // two are different powers: reading who did what, and changing
            // how long that record is kept. ADR 0021 Decision 9 rejected
            // gating retention on `settings.edit` -- SIX page nodes map onto
            // that one permission (about, apidocs, hookevent, notifyevent,
            // oui, setting), so "may shorten the audit window" and "may edit
            // the OUI table" would have been the same grant. Grant `view`
            // narrowly: an audit row necessarily discloses attempted
            // usernames.
            'audit' => ['view', 'manage'],
            // The agent's half of that trail, read by host. Its OWN node
            // rather than an alias onto `audit`: ADR 0021 made audit.view
            // narrow because the audit log discloses attempted usernames and
            // refusals, and agent rows are enrollments, inventories and task
            // results a machine reported -- a different disclosure with a
            // different audience. Aliasing would force anyone who may see
            // what an agent did to also see every failed sign-in.
            //
            // `view` only. Nothing here writes: auditlog has no create,
            // update or delete route anywhere in FOG (ADR 0021 Decision 8).
            'agentactivity' => ['view'],
            // The log viewer. Third sibling under Logging, and the one that
            // shipped without an entry here when it moved to its own node
            // (#1507) -- so it fell into "a node absent from the registry is
            // left alone" above, and a '*' holder saw "Create New Logviewer"
            // and "List All Logviewers" in the sidebar for a page that only
            // ever implements index(). No `manage`: unlike audit, there is no
            // retention window or other setting to gate separately from
            // reading it.
            'logviewer' => ['view'],
            // `install` is uploading an archive -- new executable code on
            // the server, deliberately its own permission (ADR 0009).
            // create/delete are the plugin ROW, which is how one is switched
            // on and off; they were routable and undeclared, which had the
            // effect of making the lesser power the harder one to grant.
            'plugin' => ['view', 'create', 'edit', 'delete', 'install'],
            // The whole-database dump, split out of settings.edit (GH-1410).
            // It is not a settings read: the dump is every row of all 70
            // tables, so it hands over users.uPass and uAPIToken,
            // apiTokens.atHash, userAuths' hashes, every host's
            // hostSecToken and hostADPass, nfsGroupMembers' credentials and
            // every globalSettings value -- FOG_NODE_API_KEY and the LDAP
            // bind password included. It is the one route that bypasses the
            // API's own data protection: unfilterableFields() refuses
            // token/password filters, maskSensitiveSetting() strips values
            // by name, and Route::$sensitiveSettings says of
            // FOG_NODE_API_KEY that "it is a shared HMAC secret, so it must
            // not be readable over REST". This returns it in the clear.
            //
            // So it is a credential multiplier rather than a data read:
            // compromise of one token yields every credential in the
            // deployment, plus persistence that survives rotating the token
            // that got in. "May change a setting" is not self-evidently
            // "may take a copy of every secret in the estate", and SIX page
            // nodes already map onto settings.edit.
            //
            // Deny by default, exactly as `activity` and `audit.manage`
            // were introduced: no schema step seeds it, so only a holder of
            // '*' has it until an administrator grants it. A role holding
            // settings.edit and nothing else LOSES the export on upgrade,
            // which is the point of the change.
            //
            // It also fixes the audit trail as a side effect: _auditGate()
            // records the permission string, so the row now says the whole
            // database left the server instead of saying settings.edit.
            //
            // `pki` joins it for the same reason and arrives the same way.
            // It decides what certificate authorities this host trusts --
            // an imported root reaches the OS trust store, so every
            // server-side HTTPS call on the box accepts anything it signs --
            // and it writes the three install preferences that decide
            // whether FOG re-issues the admin's web certificate. Deny by
            // default: no schema step seeds it, so until an administrator
            // grants it only a holder of '*' has it, and a role holding
            // settings.edit and nothing else LOSES nothing it ever had,
            // because the page could not do any of this before.
            'system' => ['export', 'pki'],
            // Impersonation (ADR 0033). ONE action, and it is `start` --
            // there is deliberately no `impersonate.end`, because ending is
            // never checked against anything. A user holding no roles is a
            // legitimate target, and a gated exit would trap the
            // administrator inside them.
            //
            // Deny by default, the same way `system.export` and
            // `audit.manage` arrived: no schema step seeds it, so until an
            // administrator grants it only a holder of '*' can impersonate
            // anybody. Being able to administer users is NOT the same power
            // as being able to become one, and an install that never wants
            // this gets it by doing nothing.
            //
            // Holding it is necessary and nowhere near sufficient:
            // Identity::refusalReason() still requires the target's
            // permissions AND their sites to nest inside the holder's.
            'impersonate' => ['start']
        ];
    }
    /**
     * Is this registry node owned by core rather than by a plugin?
     *
     * @param string $node the registry node (e.g. 'host')
     *
     * @return bool
     */
    public static function isCoreOwnedNode($node)
    {
        $node = strtolower(trim((string)$node));
        if ('' === $node) {
            return false;
        }
        return array_key_exists($node, self::coreRegistry());
    }
    /**
     * Get the union of a user's permissions across all assigned roles.
     *
     * @param int $userID the user id (defaults to the current user)
     *
     * @return array permission strings; empty = no access.
     */
    public static function getPermissions($userID = null)
    {
        if (null === $userID) {
            $userID = (
                self::$FOGUser && self::$FOGUser->isValid() ?
                (int)self::$FOGUser->get('id') :
                0
            );
        }
        $userID = (int)$userID;
        if ($userID < 1) {
            // No identity: grant nothing. Authentication gates should have
            // already rejected the request before any permission check.
            return [];
        }
        if (array_key_exists($userID, self::$_permCache)) {
            return self::$_permCache[$userID];
        }
        // Effective permissions are the union of roles assigned directly to
        // the user and roles assigned to any group the user belongs to. The
        // LEFT JOIN on rolePermissions keeps a row for a role that grants
        // nothing (rpName NULL) rather than dropping the assignment; the
        // group arm's inner JOIN yields a row only once a role actually
        // reaches the user through a group, so a role-less group confers
        // nothing. Either way an empty result means no access.
        $sql = 'SELECT `rpName` '
            . 'FROM `roleUserAssoc` '
            . 'LEFT JOIN `rolePermissions` ON `rpRoleID` = `ruaRoleID` '
            . 'WHERE `ruaUserID` = :userid '
            . 'UNION '
            . 'SELECT `rpName` '
            . 'FROM `userGroupMembers` '
            . 'JOIN `roleUserGroupAssoc` ON `rugGroupID` = `ugmGroupID` '
            . 'LEFT JOIN `rolePermissions` ON `rpRoleID` = `rugRoleID` '
            . 'WHERE `ugmUserID` = :usergroupid';
        $rows = self::$DB
            ->query(
                $sql,
                [],
                ['userid' => $userID, 'usergroupid' => $userID]
            )
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        if (!is_array($rows) || count($rows) < 1) {
            // Zero role assignments, direct or group-sourced, grants
            // nothing. This used to mean "implicit administrator" so that
            // adopting RBAC could not lock an install out before any role
            // was assigned; schema step 316 turned every account that
            // relied on that into an explicit role, so the fallback has
            // done its job and is gone.
            //
            // Deny is what makes the rest of the system trustworthy: while
            // the fallback existed, removing a user's last role promoted
            // them instead of restricting them, and every guard against
            // that had to be remembered by each caller rather than being a
            // property of the resolver.
            return self::$_permCache[$userID] = [];
        }
        $perms = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['rpName'] ?? ''));
            if ('' !== $name) {
                $perms[] = $name;
            }
        }
        return self::$_permCache[$userID] = array_values(
            array_unique($perms)
        );
    }
    /**
     * Does the user hold the given permission?
     *
     * @param string|null $perm   permission string ('host.edit'); null or
     *                            empty = nothing required (allowed)
     * @param int|null    $userID the user id (defaults to current user)
     *
     * @return bool
     */
    public static function can($perm, $userID = null)
    {
        if (null === $perm || '' === $perm) {
            return true;
        }
        $node = strstr($perm, '.', true);
        // A permission naming an action its node does not declare is not a
        // permission. It used to be answered TRUE for a holder of '*', which
        // is how "Create New Audit" reached the sidebar: audit declares
        // ['view', 'manage'], the menu builder asked can('audit.create'),
        // and an administrator was waved through to a sub the page does not
        // implement. Nobody else could ever have satisfied it -- the string
        // is ungrantable, because assertCanGrant() checks this same registry
        // -- so the old answer made '*' mean "yes" rather than "holds every
        // permission there is".
        //
        // Checked BEFORE '*' deliberately, and it is the only check that
        // runs ahead of it.
        //
        // A node absent from the registry is left alone rather than denied.
        // That is not laxity: resolvePagePermission() and
        // resolveApiPermission() already answer 'unmapped.<node>' for
        // anything unclaimed, which is ungrantable by construction and
        // therefore already administrator-only. Denying here as well would
        // lock a plugin page out of its own author's hands.
        //
        // tests/permission-actions-declared.test.php holds the other end:
        // every routable operation must resolve to a declared action, so a
        // new class or route cannot quietly become administrator-only again.
        if (false !== $node) {
            $registry = self::registry();
            if (isset($registry[$node])
                && !in_array(
                    substr($perm, strlen($node) + 1),
                    (array)$registry[$node],
                    true
                )
            ) {
                return false;
            }
        }
        $perms = self::getPermissions($userID);
        if (in_array('*', $perms, true)
            || in_array($perm, $perms, true)
        ) {
            return true;
        }
        return false !== $node
            && in_array("{$node}.*", $perms, true);
    }
    /**
     * Resolve a management page request to a required permission.
     *
     * Resolution order: node-independent sub override; exempt node ->
     * null; node alias; unregistered node -> 'unmapped.<node>' (deny to
     * all but '*'); explicit sub override; naming convention on the base
     * sub; fallback GET -> view, POST -> edit (a convention miss can
     * never let a view-only user write).
     *
     * @param string    $node   the page node (base value, e.g. 'host')
     * @param string    $sub    the base sub (without Ajax/Post suffix)
     * @param bool|null $isPost is this a state-changing POST request
     *                          (defaults to the actual request method)
     *
     * @return string|null the required permission, or null = no check
     */
    public static function resolvePagePermission($node, $sub, $isPost = null)
    {
        if (null === $isPost) {
            $isPost = 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '');
        }
        // Kept before the lowercasing below: the report selector is base64
        // and base64 is case-sensitive.
        $rawSub = (string)$sub;
        $node = strtolower(trim((string)$node));
        $sub = strtolower(trim((string)$sub));
        $globals = self::GLOBAL_SUB_OVERRIDES;
        if (isset($globals[$sub])) {
            return $globals[$sub];
        }
        if ('' === $node || in_array($node, self::exemptNodes(), true)) {
            return null;
        }
        $aliases = self::NODE_ALIASES;
        if (isset($aliases[$node])) {
            $node = $aliases[$node];
        }
        if ('report' === $node) {
            $node = self::_reportNode($rawSub);
        }
        $registry = self::registry();
        if (!isset($registry[$node])) {
            // A page node nothing claims. This used to return null, which
            // means "no check", so a plugin page whose author never fired
            // PERMISSION_REGISTRY_DATA was reachable by every authenticated
            // user at every verb -- view, edit and delete alike -- while the
            // plugin's REST classes covering the same data were already
            // denied. resolveApiPermission() closed that hole on the API
            // side; this is the page side of the same stance.
            //
            // Deny by requiring a permission no role can be granted: the
            // registry has no 'unmapped' node, so assertCanGrant() refuses
            // to issue it and only a holder of '*' satisfies it. An
            // administrator keeps working, a restricted role loses the free
            // pass, and the log line names the node so the plugin author
            // knows exactly what to register.
            //
            // Nothing on a stock install lands here: every core page node is
            // in the registry, EXEMPT_NODES or NODE_ALIASES, and all 14
            // shipped plugins register their node.
            //
            // The menu build resolves a permission per entry per page load,
            // so the diagnostic is emitted once per node per request.
            if (!isset(self::$_unmappedLogged[$node])) {
                self::$_unmappedLogged[$node] = true;
                error_log(
                    sprintf(
                        '%s: %s. %s',
                        _('Page node is not registered as a permission node'),
                        $node,
                        _('Only administrators may use it until the plugin '
                            . 'registers a matching permission node.')
                    )
                );
            }
            return 'unmapped.' . $node;
        }
        $overrides = self::SUB_OVERRIDES;
        if (isset($overrides[$node][$sub])) {
            $override = $overrides[$node][$sub];
            if (is_array($override)) {
                return $override[$isPost ? 'POST' : 'GET'];
            }
            return $override;
        }
        return "{$node}." . self::_subToAction($sub, $isPost);
    }
    /**
     * Which registry node gates the report this request selected.
     *
     * `f` arrives two ways and both have to work: in the query string for a
     * real request (`?node=report&sub=file&f=...`, and the report grids
     * append it to their AJAX URL too), and folded into the sub itself for
     * the sidebar, which builds keys of the form `file&f=<base64>` and asks
     * for a permission per entry so it can hide the ones the user lacks.
     *
     * @param string $rawSub the sub exactly as passed, before lowercasing
     *
     * @return string the registry node, 'report' when nothing maps
     */
    private static function _reportNode($rawSub)
    {
        $f = '';
        if (preg_match('#(?:^|[?&])f=([A-Za-z0-9+/=]+)#', $rawSub, $m)) {
            $f = $m[1];
        }
        if ('' === $f) {
            $f = (string)filter_input(INPUT_GET, 'f');
        }
        if ('' === $f) {
            return 'report';
        }
        // Strict decode: a value that is not base64 at all selects no
        // report, and must not be allowed to resolve to one by accident.
        $name = base64_decode($f, true);
        if (false === $name) {
            return 'report';
        }
        $name = str_replace(' ', '_', strtolower(trim($name)));
        $map = self::REPORT_NODES;

        return $map[$name] ?? 'report';
    }
    /**
     * Map a base sub name to an action by naming convention.
     *
     * @param string $sub    the lowercased base sub
     * @param bool   $isPost is this a state-changing POST request
     *
     * @return string one of view|create|edit|delete|task
     */
    private static function _subToAction($sub, $isPost)
    {
        foreach (['add', 'import', 'create', 'upload'] as $prefix) {
            if (0 === strpos($sub, $prefix)) {
                return 'create';
            }
        }
        if (0 === strpos($sub, 'delete')) {
            return 'delete';
        }
        foreach (['deploy', 'multicast', 'task'] as $prefix) {
            if (0 === strpos($sub, $prefix)) {
                return 'task';
            }
        }
        if (in_array($sub, ['wakeemup', 'clearpmtasks'], true)) {
            return 'task';
        }
        if ('' === $sub
            || in_array($sub, ['list', 'search', 'membership'], true)
            || 0 === strpos($sub, 'export')
            || 0 === strpos($sub, 'get')
        ) {
            return 'view';
        }
        return $isPost ? 'edit' : 'view';
    }
    /**
     * Records a guard refusing an operation.
     *
     * The three assert* guards below are not permission checks -- they are
     * the standing invariants that stop FOG being locked out of itself and
     * stop a role granting a permission that does not exist. A refusal
     * throws, the caller turns it into an error message, and until now
     * nothing anywhere recorded that somebody tried.
     *
     * These are the rows most worth having (ADR 0021 merge 5): an attempt to
     * delete the last administrator is either a mistake worth knowing about
     * or an attack, and neither leaves any other trace.
     *
     * The reason IS recorded here, unlike a failed login. There is no
     * enumeration risk in "this would leave no administrator" -- the caller
     * is already authenticated and is being told the reason on screen.
     * Untranslated, because alText is machine detail; the sentence a person
     * reads is the exception's, built in their own locale.
     *
     * @param string $type    the event type
     * @param string $why     untranslated machine detail
     * @param string $subject the class involved, if any
     * @param array  $ids     the rows involved, if any
     *
     * @return void
     */
    private static function _auditRefusal(
        $type,
        $why,
        $subject = '',
        $ids = []
    ) {
        Audit::record(
            [
                'type' => $type,
                'outcome' => Audit::DENIED,
                'subjectType' => $subject,
                // One id means one subject; several mean the count is the
                // fact, and the ids belong in the change rows a refused
                // operation never gets to write.
                'subjectID' => 1 === count((array)$ids)
                    ? (int)reset($ids)
                    : 0,
                'affectedCount' => count((array)$ids),
                'text' => (string)$why,
                'renderable' => 1
            ]
        );
    }
    /**
     * Writes the audit header for one authorization decision.
     *
     * THIS is the audit seam, and the reason it is not FOGController::save()
     * is worth keeping next to the code (ADR 0021 Decision 2). save() audits
     * by side effect rather than by intent: a denial never reaches it,
     * because the save never happens; one UI action is a dozen save() calls
     * across associations, so "the admin edited a host" becomes fourteen
     * rows with no way to tell they were one operation; and forty call sites
     * use FOGManagerController::update(), which writes bulk SQL and never
     * builds an object at all.
     *
     * WHAT IS NOT RECORDED, and why each is deliberate:
     *
     * - An ALLOWED read. Decision 12 keeps read auditing out of scope: it is
     *   a different feature with a different volume profile, and it is what
     *   turned `history` into the firehose that UNIQUE (hText, hTime) was
     *   invented to survive. A DENIED read IS recorded -- somebody being
     *   turned away from something is the row most worth having.
     * - A node with no permission at all ($perm null: home, client, schema,
     *   login). There was no decision to record.
     *
     * @param string $perm    the resolved permission, or null
     * @param string $outcome Audit::ALLOWED or Audit::DENIED
     * @param string $surface 'page' or 'api'
     * @param string $subject the node or class the request addressed
     * @param int    $id      the object id, when the route carries one
     *
     * @return void
     */
    private static function _auditGate(
        $perm,
        $outcome,
        $surface,
        $subject,
        $id = 0
    ) {
        if (null === $perm || '' === (string)$perm) {
            return;
        }
        $action = (string)substr((string)$perm, (int)strrpos((string)$perm, '.') + 1);
        if (Audit::ALLOWED === $outcome && 'view' === $action) {
            return;
        }
        Audit::record(
            [
                'type' => 'access.' . $surface,
                'outcome' => $outcome,
                'permission' => (string)$perm,
                'subjectType' => strtolower((string)$subject),
                'subjectID' => (int)$id,
                'renderable' => 1
            ]
        );
    }
    /**
     * Enforce the permission for a management page request. Returns
     * silently when allowed; otherwise responds 403 JSON (AJAX) or
     * queues a flash message and redirects home (full page), and exits.
     *
     * @param string $node the page node (base value)
     * @param string $sub  the base sub (without Ajax/Post suffix)
     *
     * @return void
     */
    /**
     * Page nodes reachable while impersonating even though they resolve to
     * no permission at all.
     *
     * All four are EXEMPT_NODES, so `can()` waves them through and the
     * read-only gate below would otherwise refuse them for having no
     * `.view` to check. Each is here for a stated reason:
     *
     *   home       the dashboard, and the place a denial redirects TO --
     *              refusing it would loop.
     *   logout     the way out. Never gated, for the same reason `end` is
     *              not.
     *   impersonate ending, and starting the next one.
     *   hwinfo     a read-only hardware page any signed-in user can open.
     *              Refusing it would answer the question impersonation was
     *              asked -- "what does this person see" -- wrongly.
     *
     * `schema` and `client` are EXEMPT_NODES and deliberately absent.
     * A schema deploy rewrites the whole database and must never run behind
     * a mask; `client` is a fog-client endpoint no browser session wants.
     *
     * @var array
     */
    const IMPERSONATION_ALLOWED_PAGE_NODES = [
        'home',
        'logout',
        'impersonate',
        'hwinfo'
    ];
    /**
     * API routes reachable while impersonating, by HTTP method.
     *
     * Every route here resolves to a null permission (see
     * API_ROUTE_PERMISSIONS), so the `.view` rule cannot speak for them and
     * they have to be named. The METHOD is part of the entry rather than the
     * route alone, and that is the whole of the difference between reading a
     * saved grid layout and writing one:
     *
     *   userpref     read AND write, because the impersonated user's own
     *                preferences are the one thing a span is FOR. The
     *                handler takes its user id from the session, which is
     *                the mask, so a write lands on the target -- which is
     *                the point, and is why nothing here has to pass an id.
     *   savedfilters GET only. A saved filter can be shared with named
     *                people or made global, which is an outward act rather
     *                than a view preference.
     *   unisearch    POST, because the sidebar search posts. It reads.
     *
     * @var array
     */
    const IMPERSONATION_ALLOWED_API_ROUTES = [
        'userprefs' => ['GET'],
        'userpref' => ['GET', 'POST', 'PUT', 'DELETE'],
        'savedfilters' => ['GET'],
        'savedfilter' => ['GET'],
        'savedfiltertargets' => ['GET'],
        'whoami' => ['GET'],
        'status' => ['GET'],
        'bandwidth' => ['GET'],
        'unisearch' => ['GET', 'POST'],
        'openapi' => ['GET'],
        'openapiSwaggerAlias' => ['GET']
    ];
    /**
     * Refuse anything a span is not allowed to do.
     *
     * AN ALLOWLIST, NOT A LIST OF FORBIDDEN OPERATIONS, and that is the
     * design decision most worth defending here. The obvious shape is to
     * refuse the dangerous four -- password change, API token creation, role
     * assignment, auth source change -- because each turns a temporary view
     * into permanent account takeover. But this repository already has the
     * cautionary tale on file: ADR 0021 records `storagenode.pass` leaking
     * because the secrets registry enumerated fields per route, and the
     * commit message names the lesson exactly -- "naming them per route is
     * what hid this". A refusal list has to be re-audited every time
     * somebody adds a route; an allowlist does not.
     *
     * It also closes something a refusal list leaves open and cannot see:
     * FOGController::save() auto-fills `createdBy` from self::$FOGUser,
     * which IS the mask, so an ordinary create performed while impersonating
     * would stamp the target's name onto the row itself. That is a second
     * attribution forgery, in a column no audit change repairs, and no list
     * of four credential operations would ever have caught it.
     *
     * The rule is therefore: a resolved `.view`, or a named entry. Everything
     * else is refused, including the null-permission exempt nodes that
     * `can()` waves through -- which is how `schema` is kept out.
     *
     * Answers the DECISION only; it does not ask whether a span is open, so
     * a caller must check Identity::isImpersonating() first. Public so the
     * gate test can drive every arm of it directly rather than inferring it
     * from a whole request.
     *
     * @param string|null $perm    the resolved permission, null when exempt
     * @param string      $surface 'page' or 'api'
     * @param string      $context the page node, or the matched route name
     * @param string      $method  the HTTP method, for the API allowlist
     *
     * @return bool
     */
    public static function impersonationPermits(
        $perm,
        $surface,
        $context,
        $method = ''
    ) {
        $perm = (string)$perm;
        if ('page' === $surface) {
            $node = strtolower(trim((string)$context));
            if (in_array($node, self::IMPERSONATION_ALLOWED_PAGE_NODES, true)) {
                return true;
            }
        } else {
            $allowed = self::IMPERSONATION_ALLOWED_API_ROUTES;
            $route = (string)$context;
            $method = strtoupper(trim((string)$method)) ?: 'GET';
            if (isset($allowed[$route])
                && in_array($method, $allowed[$route], true)
            ) {
                return true;
            }
        }
        // '.view' and nothing else. Checked on the SUFFIX rather than by
        // splitting, so a plugin node with a dot in it cannot slip past.
        return '' !== $perm && '.view' === substr($perm, -5);
    }
    /**
     * The refusal half of impersonationPermits().
     *
     * Split from the decision deliberately: everything below this line
     * either exits or redirects, so a gate fused to its own response can
     * only be tested by driving a whole request. The decision is the part
     * that has to be provably right, so it is the part that is callable.
     *
     * @param string|null $perm    the resolved permission, null when exempt
     * @param string      $surface 'page' or 'api'
     * @param string      $context the page node, or the matched route name
     * @param string      $method  the HTTP method, for the API allowlist
     *
     * @return void
     */
    private static function _assertImpersonationPermits(
        $perm,
        $surface,
        $context,
        $method = ''
    ) {
        if (!Identity::isImpersonating()) {
            return;
        }
        if (self::impersonationPermits($perm, $surface, $context, $method)) {
            return;
        }
        $perm = (string)$perm;
        self::_auditGate(
            $perm,
            Audit::DENIED,
            $surface,
            $context,
            0
        );
        $message = _('Impersonation is read-only. End it to do this as '
            . 'yourself.');
        if ('api' === $surface) {
            Route::sendResponse(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(['error' => $message])
            );

            return;
        }
        if (self::$ajax) {
            http_response_code(HTTPResponseCodes::HTTP_FORBIDDEN);
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            exit;
        }
        self::setMessage($message, _('Impersonating'), 'warning');
        self::redirect('?node=home');
    }
    public static function requirePagePermission($node, $sub)
    {
        $perm = self::resolvePagePermission($node, $sub);
        // BEFORE can(), because the mask's own permissions are not the
        // question: a span is read-only whatever the impersonated user is
        // allowed to do, and running this first means a refusal is recorded
        // as an impersonation refusal rather than as a permission one.
        self::_assertImpersonationPermits($perm, 'page', $node);
        // The API arm below has always passed the id its route carried; this
        // one passed nothing, so every page-surface header recorded
        // subjectID 0 -- "somebody exercised host.delete", with no way to
        // tell which host, on the one surface most page mutations use.
        // Scalar only: a mass operation posts id[] and (int) on an array is
        // 1, which would name an object that was never touched. A bulk
        // action is left at 0, which is at least honest.
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
            ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (self::can($perm)) {
            self::_auditGate($perm, Audit::ALLOWED, 'page', $node, (int)$id);
            return;
        }
        // BEFORE the response below, all three arms of which exit.
        self::_auditGate($perm, Audit::DENIED, 'page', $node, (int)$id);
        if (self::$ajax) {
            http_response_code(HTTPResponseCodes::HTTP_FORBIDDEN);
            header('Content-Type: application/json');
            echo json_encode(
                ['error' => _('You do not have permission to perform this action.')]
            );
            exit;
        }
        self::setMessage(
            sprintf(
                _('You do not have permission to access this page (%s required).'),
                $perm
            ),
            _('Permission denied'),
            'warning'
        );
        self::redirect('?node=home');
    }
    /**
     * Resolve an API request to a required permission string.
     *
     * @param string $routeName the matched route name
     * @param string $class     the model class url parameter, if any
     *
     * @return string|null the required permission, null = no check
     */
    /**
     * Page nodes that are never permission-checked, core's plus any a plugin
     * has contributed.
     *
     * Closes ADR 0009's second gap. EXEMPT_NODES is a const, so a plugin
     * could not append to it, and every page a plugin adds is therefore
     * permission-checked -- correct for a settings page, impossible for the
     * one page an authentication provider needs: the surface a visitor
     * reaches BEFORE they have a session.
     *
     * The const stays exactly as it was, so "what does core exempt" is still
     * answerable by reading one array. Plugin entries are merged on top here.
     *
     * A plugin may only exempt a node nothing else owns. Exempting a node
     * that is in the permission registry -- core's or another plugin's --
     * is refused, because the two are contradictory instructions about the
     * same node and the permissive one would win. That check is what stops
     * this being a way to turn the gate off on 'host'.
     *
     * @return array
     */
    public static function exemptNodes()
    {
        if (null !== self::$_exemptNodes) {
            return self::$_exemptNodes;
        }
        $nodes = self::EXEMPT_NODES;
        $extra = [];
        if (self::$HookManager) {
            self::$HookManager->processEvent(
                'PAGE_EXEMPT_NODES',
                ['nodes' => &$extra]
            );
        }
        $registry = self::registry();
        foreach ((array)$extra as $node) {
            $node = strtolower(trim((string)$node));
            if ('' === $node || in_array($node, $nodes, true)) {
                continue;
            }
            if (isset($registry[$node]) || isset(self::NODE_ALIASES[$node])) {
                error_log(
                    sprintf(
                        'FOG exempt node: refusing "%s" -- it is a registered '
                        . 'permission node, so exempting it would silently '
                        . 'turn off a check something else asked for. Use a '
                        . 'node of its own for the pre-authentication page.',
                        $node
                    )
                );
                continue;
            }
            $nodes[] = $node;
        }
        return self::$_exemptNodes = array_values(array_unique($nodes));
    }
    /**
     * Record the permission a plugin-contributed route requires.
     *
     * Called by Route::defineRoutes() for each validated plugin route. A
     * route that declared nothing is simply never registered here, which is
     * what makes "declares nothing" mean "denied" rather than "allowed".
     *
     * @param string      $routeName  The prefixed route name Route registered.
     * @param string|null $permission The permission, or null for a public route.
     *
     * @return void
     */
    public static function declareRoutePermission($routeName, $permission)
    {
        $routeName = (string)$routeName;
        if ('' === $routeName
            || array_key_exists($routeName, self::API_ROUTE_PERMISSIONS)
            || isset(self::API_ROUTE_ACTIONS[$routeName])
        ) {
            // Never let a declaration land on a core route's name.
            return;
        }
        if (null !== $permission
            && (!is_string($permission) || '' === $permission)
        ) {
            return;
        }
        self::$_declaredRoutePermissions[$routeName] = $permission;
    }
    public static function resolveApiPermission($routeName, $class = '')
    {
        $routeName = (string)$routeName;
        if (array_key_exists($routeName, self::API_ROUTE_PERMISSIONS)) {
            return self::API_ROUTE_PERMISSIONS[$routeName];
        }
        // Core's table is consulted first on purpose: a plugin cannot
        // redeclare the permission of a core route even if it manages to
        // register a route under the same name. Route stamps its own prefix
        // on plugin names so that should be impossible, and this makes it
        // impossible twice.
        if (array_key_exists($routeName, self::$_declaredRoutePermissions)) {
            return self::$_declaredRoutePermissions[$routeName];
        }
        if (!isset(self::API_ROUTE_ACTIONS[$routeName])) {
            // A route name nothing claims. This used to return null, which
            // means "no check", on the grounds that it matched the
            // unregistered-page compatibility stance -- but that stance was
            // tightened below and in resolvePagePermission(), leaving this
            // the last allow-by-default in the file.
            //
            // It was very nearly harmless while it lasted, because nothing
            // could add a route: defineRoutes() is core-only and all 29 of
            // its route names are listed in API_ROUTE_ACTIONS or
            // API_ROUTE_PERMISSIONS, so this branch was unreachable on a
            // stock install. The plugin route seam is what makes it
            // reachable, and an "open unless declared" default is exactly
            // the wrong way round for a seam whose whole job is letting
            // third-party code answer a URL.
            //
            // Deny the same way the class branch below does: require a
            // permission no role can be granted, so an administrator keeps
            // working and a restricted role does not get a free pass. A
            // plugin route declares its permission when it registers (see
            // Route::pluginRoutes), and that declaration is what lands in
            // API_ROUTE_PERMISSIONS' plugin-supplied counterpart, so a
            // route reaching here declared nothing.
            if (!isset(self::$_unmappedLogged['route:' . $routeName])) {
                self::$_unmappedLogged['route:' . $routeName] = true;
                error_log(
                    sprintf(
                        '%s: %s. %s',
                        _('API route is not mapped to a permission'),
                        $routeName,
                        _('Only administrators may use it until the plugin '
                            . 'declares a permission for the route.')
                    )
                );
            }
            return 'unmapped.route.' . $routeName;
        }
        $class = strtolower(trim((string)$class));
        $entity = self::API_CLASS_ENTITIES[$class] ?? self::_pluginEntity($class);
        if ('' === $entity) {
            // A class nothing claims. Previously this returned null, which
            // meant allow, so every plugin-added REST class was reachable
            // by any authenticated user regardless of role -- a role
            // granting nothing could still create and delete Site and
            // Location objects.
            //
            // Deny by requiring a permission no role can be granted: the
            // registry has no 'unmapped' node, so assertCanGrant() will not
            // issue it and only a holder of '*' satisfies it. That keeps a
            // third-party plugin working for administrators instead of
            // breaking it outright, while restricted roles lose the free
            // pass. The log line names the class so the fix is obvious.
            error_log(
                sprintf(
                    '%s: %s. %s',
                    _('API class is not mapped to a permission node'),
                    $class,
                    _('Only administrators may use it until the plugin '
                        . 'registers a matching permission node.')
                )
            );
            return 'unmapped.' . $class;
        }
        return $entity
            . '.'
            . self::API_ROUTE_ACTIONS[$routeName];
    }
    /**
     * The permission node owning a plugin-added API class, '' if none.
     *
     * Plugins already declare their node in PERMISSION_REGISTRY_DATA and
     * name their API classes after it -- either the node itself ('site') or
     * the node followed by an association suffix ('sitehostassociation'),
     * exactly as core does for 'groupassociation' => 'group'. Deriving the
     * mapping from what plugins already register means no plugin has to be
     * edited and no second registration can drift out of step with the
     * first.
     *
     * Longest match wins, so a node cannot shadow a more specific one, and
     * the association suffix is required rather than accepting any prefix
     * -- without it a node like 'site' would claim any class merely
     * starting with those letters.
     *
     * @param string $class the lowercased API class name
     *
     * @return string
     */
    private static function _pluginEntity($class)
    {
        $best = '';
        foreach (array_keys((array)self::registry()) as $node) {
            $node = strtolower((string)$node);
            if ('' === $node || strlen($node) <= strlen($best)) {
                continue;
            }
            if ($class === $node
                || (0 === strpos($class, $node)
                    && 'association' === substr($class, -11))
            ) {
                $best = $node;
            }
        }
        return $best;
    }
    /**
     * Enforce a permission for an API request. Returns silently when
     * allowed; otherwise sends 403 and exits.
     *
     * @param string|null $perm the required permission (null = allowed)
     *
     * @return void
     */
    public static function requireApiPermission(
        $perm,
        $class = '',
        $id = 0,
        $routeName = ''
    ) {
        // The route NAME, not just the permission: the routes a span may
        // still write -- the impersonated user's own preferences -- resolve
        // to a null permission, so there is nothing in $perm to tell them
        // apart from the exempt nodes that must be refused. Defaulted so
        // third-party callers keep working; core passes it.
        self::_assertImpersonationPermits(
            $perm,
            'api',
            $routeName,
            (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
        );
        if (self::can($perm)) {
            self::_auditGate($perm, Audit::ALLOWED, 'api', $class, $id);
            return;
        }
        self::_auditGate($perm, Audit::DENIED, 'api', $class, $id);
        Route::sendResponse(
            HTTPResponseCodes::HTTP_FORBIDDEN,
            json_encode(
                [
                    'error' => _('You do not have permission to perform'
                        . ' this action.')
                ]
            )
        );
    }
    /**
     * Is the user unrestricted by object scope? Holders of the global '*'
     * see every object; object boundaries never apply to them.
     *
     * @param int|null $userID the user id (defaults to current user)
     *
     * @return bool
     */
    private static function _isUnrestricted($userID = null)
    {
        return in_array('*', self::getPermissions($userID), true);
    }
    /**
     * Is this a declared machine request that carries no principal?
     *
     * Site scope answers "which objects may THIS USER see". On an entry
     * point that has no user and cannot acquire one, deny-all is not a
     * conservative reading of that question -- it is a confident answer to
     * a different one, and it is what broke every machine path when the
     * boundary moved into the query (46fc53a20, "Enforce the site boundary
     * in the query, not on the rows"). A PXE client's boot script lost its
     * host identity AND its task on any server with a site configured: no
     * `set hostname`, no `set imageID`, a menu banner reading "Host is
     * registered as" with nothing after it, and a scheduled task that
     * simply never ran. The FOS progress and hostinfo endpoints, and the
     * task scheduler's expired-checkin sweep, went the same way.
     *
     * Three conditions, and the first is the one that matters.
     *
     * FOG_MACHINE_REQUEST is DECLARED by the entry point, one line at the
     * top of each of the 44 files under service/ and once in
     * packages/service/lib/service_lib.php for the ten daemons, exactly
     * like FOG_WANTS_SESSION in management/index.php. It is deliberately
     * not inferred from "no principal is bound", because those two are not
     * the same statement: a machine entry point HAS no user, while a route
     * that merely failed to authenticate one is a bug. Keying on absence
     * would hand that bug the exemption as well, so a route that ever lost
     * its 401 would silently lose site scoping in the same stroke --
     * tests/route-read-path-guards.test.php section (j) exists to catch
     * exactly that, and it still does. A new file under service/ that
     * forgets the declaration fails the safe way: deny-all, visibly
     * broken, rather than quietly unbounded.
     *
     * Nothing outside those files can reach this. The UI binds $FOGUser
     * from the session (LoadGlobals) and every REST arm binds it from the
     * token it accepted (Route::_apiAuth and the fog-user-token path), and
     * neither declares the constant.
     *
     * The remaining two conditions keep an authenticated caller bounded
     * even on a machine entry point -- fog-client endpoints can carry a
     * user -- and keep a caller that named a user id explicitly bounded,
     * because it is asking about THAT user.
     *
     * @param int|null $userID the user id the caller supplied, if any
     *
     * @return bool
     */
    private static function _hasNoPrincipal($userID = null)
    {
        return defined('FOG_MACHINE_REQUEST')
            && null === $userID
            && !(self::$FOGUser && self::$FOGUser->isValid());
    }
    /**
     * Public view of _isUnrestricted for scope-enforcing plugins (Site) that
     * must let global '*' holders bypass list filtering.
     *
     * @param int|null $userID the user id (defaults to current user)
     *
     * @return bool
     */
    public static function isUnrestricted($userID = null)
    {
        return self::_isUnrestricted($userID);
    }
    /**
     * Is a specific object within the acting user's object scope?
     *
     * Object scope is a boundary layered on top of the verb permission:
     * the permission says what you may do, this says which objects you may
     * do it to. Core owns it, and SiteScope answers it -- sites moved out
     * of the site plugin and into core, so this is no longer inert on a
     * stock install.
     *
     * It still stays out of the way on an install that has not configured
     * sites. SiteScope allows everything when no sites exist, so a server
     * that has never used them, or one running new code against a schema
     * that has not been deployed yet, behaves exactly as before.
     *
     * OBJECT_SCOPE_CHECK is still fired, and still first. Third-party
     * listeners keep the contract they were written against: flip
     * 'allowed' to false to deny an object core would have allowed. What
     * changed is that the core site decision is combined with AND
     * afterward, so a listener can deny past core but cannot GRANT past
     * it -- for a security boundary the composition has to be deny-wins,
     * or a plugin could hand out another site's hosts by setting a flag.
     *
     * Unrestricted users (global '*' holders) and requests with
     * no concrete single-object id (id < 1 — list, create, mass op) always
     * pass; scope is only meaningful for one existing object. The '*'
     * short circuit is deliberately ahead of everything else: a full
     * administrator is never subject to a site boundary, and that is
     * structural here rather than a rule SiteScope has to remember.
     *
     * @param string   $node   the page/api node (e.g. 'host')
     * @param int      $id     the target object id
     * @param int|null $userID acting user (defaults to current user)
     *
     * @return bool
     */
    public static function objectInScope($node, $id, $userID = null)
    {
        $id = (int)$id;
        if ($id < 1) {
            return true;
        }
        if (self::_isUnrestricted($userID)) {
            return true;
        }
        // No user to bound -- see _hasNoPrincipal(). Stated here as well as
        // in _boundedUserID() because the single-object and list paths have
        // to give the same answer: an object hidden from every list but
        // still served through GET /<class>/<id>, or the reverse, is two
        // statements of who may see what.
        if (self::_hasNoPrincipal($userID)) {
            return true;
        }
        if (null === $userID) {
            $userID = (
                self::$FOGUser && self::$FOGUser->isValid() ?
                (int)self::$FOGUser->get('id') :
                0
            );
        }
        $node = strtolower(trim((string)$node));
        $allowed = true;
        self::$HookManager->processEvent(
            'OBJECT_SCOPE_CHECK',
            [
                'node' => $node,
                'id' => $id,
                'userID' => (int)$userID,
                'allowed' => &$allowed
            ]
        );
        // Core's site boundary, applied after the event and combined with
        // AND. Skipped when a listener has already denied -- deny is deny,
        // and there is no reason to spend the query.
        if ($allowed && !SiteScope::inScope($node, $id, (int)$userID)) {
            $allowed = false;
        }
        // The boundary the LIST routes carry, asked about this one object.
        // A plugin that narrows lists through API_SCOPE_WHERE/API_SCOPE_IDS
        // would otherwise hide an object from every list and still serve it
        // through GET /<class>/<id> -- two statements of who may see what,
        // and the one nobody looks at is the one that stays wrong.
        if ($allowed
            && !self::_objectPassesPluginScope($node, $id, (int)$userID)
        ) {
            $allowed = false;
        }
        return (bool)$allowed;
    }
    /**
     * The object ids a user may see for a listed node, or null when the
     * list needs no narrowing at all.
     *
     * The list counterpart of objectInScope(), and the distinction the
     * return type carries is the whole point: null means "no boundary
     * applies -- leave the list alone", while an ARRAY narrows the list,
     * and an EMPTY array is a real answer meaning the user may see
     * nothing. Collapsing those two onto "empty" is the mistake that
     * silently shows every host to a user with no site.
     *
     * null is returned for an unrestricted '*' holder, a node that is not
     * site-scoped, an install with no sites configured, and a member of a
     * catch-all site -- the same short circuits objectInScope() applies,
     * so single objects and lists cannot disagree about who is scoped.
     *
     * A plugin's own boundary is composed in afterward, by INTERSECTION:
     * either side may narrow, neither may widen. See _pluginScopeIDs().
     *
     * @param string   $node   the node being listed
     * @param int|null $userID acting user (defaults to current user)
     *
     * @return array|null ids to narrow to, or null for no restriction
     */
    public static function scopedObjectIDs($node, $userID = null)
    {
        // Core's own answer. _boundedUserID() returns null for every reason
        // CORE has not to narrow -- '*' holder, unscoped node, no sites,
        // catch-all member -- and all but the first of those are statements
        // about SITES, so none of them may suppress a plugin's boundary on
        // some other dimension. The '*' exemption is shared, and lives in
        // _pluginScopeApplies() so both sides state it once.
        $bounded = self::_boundedUserID($node, $userID);
        $coreIDs = (
            null === $bounded ?
            null :
            SiteScope::allInScopeIDs($node, $bounded)
        );
        return self::_composeScopeIDs(
            $coreIDs,
            self::_pluginScopeIDs($node, $userID)
        );
    }
    /**
     * The same boundary as scopedObjectIDs(), as a SQL WHERE fragment.
     *
     * For callers that can push the boundary into the query instead of
     * filtering rows the database has already chosen. A paginated list must:
     * the LIMIT is applied before any post-filter runs, so filtering
     * afterward empties pages while later pages still hold rows the user may
     * see, and leaves the counts describing objects they may not.
     *
     * Tri-state, and the falsy value is the permissive one on purpose. `null`
     * -- the only falsy return -- means no boundary applies. A user who
     * reaches nothing gets '1=0', which is truthy, so the natural
     * `if (!$where) { skip }` skips only when skipping is right. See
     * SiteScope::inScopeWhere().
     *
     * The decision of WHETHER a boundary applies is shared with
     * scopedObjectIDs() rather than restated here, and the membership rule
     * itself is shared inside SiteScope. Neither can drift from the other.
     *
     * A plugin's own boundary is composed in afterward with AND: either
     * side may narrow, neither may widen. See _pluginScopeWhere().
     *
     * @param string   $node   the node being listed
     * @param string   $idExpr the object-id column, quoted and qualified
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return string|null the WHERE fragment, or null for no boundary
     */
    public static function scopedObjectWhere($node, $idExpr, $userID = null)
    {
        // See scopedObjectIDs() for why core's short circuits are not the
        // plugin's.
        $bounded = self::_boundedUserID($node, $userID);
        $coreWhere = (
            null === $bounded ?
            null :
            SiteScope::inScopeWhere($node, $idExpr, $bounded)
        );
        $pluginWhere = self::_pluginScopeWhere($node, $idExpr, $userID);
        if (null === $pluginWhere) {
            // A listener that knows only API_SCOPE_IDS still bounds this
            // QUERY. The ids are turned into an IN () here rather than
            // handed back for the caller to filter rows with, because a
            // boundary applied to rows the database has already LIMITed
            // empties pages while later pages still hold rows the caller
            // may see, and leaves the counts describing objects they may
            // not -- ADR 0019, and the defect that ADR was written for.
            //
            // Safe to interpolate: _pluginScopeIDs() has already put every
            // element through intval.
            $pluginIDs = self::_pluginScopeIDs($node, $userID);
            if (null !== $pluginIDs) {
                $pluginWhere = (
                    count($pluginIDs) ?
                    sprintf(
                        '%s IN (%s)',
                        $idExpr,
                        implode(',', $pluginIDs)
                    ) :
                    // Deny-all as SQL, never ''. An empty fragment reads as
                    // silence one level up and would show every row on the
                    // server -- the tri-state trap SiteScope documents.
                    '1=0'
                );
            }
        }
        return self::_andScopeFragment($coreWhere, $pluginWhere);
    }
    /**
     * Does a site boundary apply, and to whom?
     *
     * The shared front half of scopedObjectIDs() and scopedObjectWhere() --
     * everything up to the membership lookup itself. Two copies of this
     * ladder would be two chances to answer "is this user bounded?"
     * differently, in the one place where the two answers must agree.
     *
     * @param string   $node   the node being listed
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return int|null the acting user id when a boundary applies, null when
     *                  none does
     */
    private static function _boundedUserID($node, $userID = null)
    {
        if (self::_isUnrestricted($userID)) {
            return null;
        }
        // No user to bound -- see _hasNoPrincipal().
        if (self::_hasNoPrincipal($userID)) {
            return null;
        }
        if (null === $userID) {
            $userID = (
                self::$FOGUser && self::$FOGUser->isValid() ?
                (int)self::$FOGUser->get('id') :
                0
            );
        }
        $userID = (int)$userID;
        $node = strtolower(trim((string)$node));
        if (!SiteScope::isScopedNode($node)) {
            return null;
        }
        // Same two short circuits as the single-object path: a server with
        // no sites behaves as it always did, and a catch-all member is not
        // narrowed to an enumerated list (which would stop covering
        // objects created after the catch-all was made).
        if (!SiteScope::sitesInUse() || SiteScope::isUnscoped($userID)) {
            return null;
        }
        return $userID;
    }
    /**
     * Does a PLUGIN-supplied object boundary apply to this caller at all?
     *
     * One rule, and it is the one objectInScope() already makes structural
     * for core's own boundary: a global '*' holder is never narrowed.
     * Stated here rather than left to each listener because the
     * single-object and list paths have to agree -- an administrator who
     * reached an object through GET /host/5 but could not see it in the
     * list would be looking at two different statements of who may see
     * what.
     *
     * Every OTHER reason core declines to narrow -- the node is not
     * site-scoped, no sites exist, the user is in a catch-all site -- is a
     * fact about SITES and says nothing about whatever dimension a plugin
     * scopes on, so none of them appears here.
     *
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return bool
     */
    private static function _pluginScopeApplies($userID = null)
    {
        // No hook manager, no listeners, and nothing to ask. LoadGlobals
        // builds it, so this is null on the paths that run before or
        // without a full boot -- the schema updater and the CLI harnesses.
        // objectInScope() has always dereferenced it unguarded; these two
        // are reached from listem() and from ~90 getIds()/getNames() call
        // sites, so they cannot.
        if (!self::$HookManager) {
            return false;
        }
        // Asking a plugin for a boundary must not recurse into asking a
        // plugin for a boundary. Two ways in, and neither is theoretical:
        //
        //   - HookManager::processEvent() primes its known-event cache with
        //     Route::getIds('hookevent'), which is a scoped read, which
        //     arrives back here. The cache is assigned AFTER that call
        //     returns, so on re-entry the guard is still null and the
        //     recursion has no floor -- it exhausts memory rather than
        //     erroring, which is why it looks like a hung request.
        //   - A listener that computes its own boundary by reading through
        //     getIds()/getNames() -- the obvious way to write one -- does
        //     the same thing one level further out.
        //
        // The nested read is answered with CORE's boundary alone. That is
        // the safe direction: the outer call still applies the plugin's,
        // and a boundary is only ever a narrowing, so the inner read is
        // wider than the caller's answer and never wider than core allows.
        if (self::$_inPluginScope) {
            return false;
        }
        return !self::_isUnrestricted($userID);
    }
    /**
     * A plugin's object boundary for a list, as a SQL fragment, or null.
     *
     * The list-side counterpart of OBJECT_SCOPE_CHECK, and the reason it
     * exists: OBJECT_SCOPE_CHECK is only ever consulted about ONE object,
     * so a plugin can veto `GET /host/5` and has no way at all to bound
     * `GET /host/list`. On 1.5 the same plugin could, through this event
     * and API_SCOPE_IDS. Firing them here means a plugin written against
     * 1.5 keeps bounding lists after the upgrade instead of silently
     * ceasing to -- and silently ceasing to would fail OPEN, which is the
     * direction that matters.
     *
     * The name keeps its API_ prefix even though this now fires for page
     * routes as well. It is a compatibility contract: a 1.5 plugin
     * registers THIS string, and a tidier name would leave every one of
     * them inert, which is the whole failure being fixed.
     *
     * THE RETURN IS A TRI-STATE, and it is not the id list's:
     *
     *   null      nobody answered -- fall through to API_SCOPE_IDS
     *   '<sql>'   narrow with this expression
     *   '1=0'     a real answer meaning "you may see nothing"
     *
     * There is deliberately no empty-string state. An empty fragment is
     * indistinguishable from silence, so it is READ as silence: a listener
     * meaning "nothing" must say so in SQL. Treating '' as deny-all would
     * make an accidental '' deny; treating it as a boundary would emit
     * `WHERE ()`, a syntax error. Reading it as no-answer is the only
     * option that fails toward the id-list path rather than toward
     * either a broken query or a silent policy change.
     *
     * $idExpr is the caller's own id column, already quoted and qualified,
     * so a listener can write `EXISTS (... WHERE assoc.hostID = <idExpr>)`
     * without knowing the table name or guessing at ambiguity in a join.
     *
     * Inert with no listener registered, which is every stock install.
     *
     * @param string   $node   the node being listed
     * @param string   $idExpr the object-id column, quoted and qualified
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return string|null the fragment, or null for no answer
     */
    private static function _pluginScopeWhere($node, $idExpr, $userID = null)
    {
        if (!self::_pluginScopeApplies($userID)) {
            return null;
        }
        // 'classname' and not 'node': the payload key is part of the
        // contract a 1.5 plugin was written against.
        $classname = strtolower(trim((string)$node));
        $where = null;
        self::$_inPluginScope = true;
        try {
            self::$HookManager->processEvent(
                'API_SCOPE_WHERE',
                [
                    'classname' => &$classname,
                    'idExpr' => &$idExpr,
                    'where' => &$where
                ]
            );
        } finally {
            // finally, not a trailing assignment: a listener that throws
            // would otherwise leave the guard set for the rest of the
            // request and silently disable every plugin boundary after it.
            self::$_inPluginScope = false;
        }
        if (!is_string($where)) {
            return null;
        }
        $where = trim($where);
        return '' === $where ? null : $where;
    }
    /**
     * A plugin's object boundary for a list, as ids, or null.
     *
     * Consulted only when nothing answered API_SCOPE_WHERE. A fragment
     * costs one expression whatever the fleet size; an id list costs a
     * lookup of every object the caller may see, materialised into PHP, on
     * every request. Both are supported because a plugin written before
     * the fragment event existed knows only this one.
     *
     * THE RETURN IS A TRI-STATE and it is NOT the fragment's:
     *
     *   null        no boundary -- leave the list alone
     *   array(...)  narrow to exactly these ids
     *   array()     a real answer meaning "nothing"
     *
     * null is the ONLY value meaning unbounded. `if (!$ids)` is true for
     * both null and array(), so a caller written that way shows every
     * object to the one caller entitled to none. Test `null ===`.
     *
     * Every element is put through intval here, once, so the callers may
     * interpolate the result into SQL.
     *
     * @param string   $node   the node being listed
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return array|null ids to narrow to, or null for no boundary
     */
    private static function _pluginScopeIDs($node, $userID = null)
    {
        if (!self::_pluginScopeApplies($userID)) {
            return null;
        }
        $classname = strtolower(trim((string)$node));
        $ids = null;
        self::$_inPluginScope = true;
        try {
            self::$HookManager->processEvent(
                'API_SCOPE_IDS',
                [
                    'classname' => &$classname,
                    'ids' => &$ids
                ]
            );
        } finally {
            self::$_inPluginScope = false;
        }
        return (
            is_array($ids) ?
            array_values(array_map('intval', $ids)) :
            null
        );
    }
    /**
     * ANDs core's boundary fragment together with a plugin's.
     *
     * Deny-wins by construction: each side can only ever remove rows the
     * other allowed. Both are parenthesised because a fragment is written
     * by someone else and may contain OR -- `a OR b AND c` would bind the
     * boundary to one arm and leave the rest matching server-wide, which is
     * the same mistake unisearch() carries a comment about.
     *
     * @param string|null $core   core's fragment, or null
     * @param string|null $plugin the plugin's fragment, or null
     *
     * @return string|null the combined fragment, or null when neither
     *                     side answered
     */
    private static function _andScopeFragment($core, $plugin)
    {
        if (null === $core) {
            return $plugin;
        }
        if (null === $plugin) {
            return $core;
        }
        return sprintf('(%s) AND (%s)', $core, $plugin);
    }
    /**
     * Intersects core's id boundary with a plugin's.
     *
     * The id-list twin of _andScopeFragment(), with the same property: an
     * intersection can only shrink. An empty result is passed on as an
     * empty ARRAY and not as null -- that is a real deny-all, and the two
     * are the trap this file documents in three other places.
     *
     * @param array|null $core   core's ids, or null for no boundary
     * @param array|null $plugin the plugin's ids, or null
     *
     * @return array|null the combined ids, or null when neither answered
     */
    private static function _composeScopeIDs($core, $plugin)
    {
        if (null === $core) {
            return $plugin;
        }
        if (null === $plugin) {
            return $core;
        }
        return array_values(
            array_intersect(array_map('intval', $core), $plugin)
        );
    }
    /**
     * Does ONE object satisfy the plugin boundary the lists are narrowed
     * with?
     *
     * Keeps `GET /<class>/<id>` and `GET /<class>/list` telling the same
     * story. The fragment is preferred and evaluated as a bounded existence
     * check -- one row at most, the id bound as a parameter, and the
     * plugin's id list never materialised.
     *
     * Allows when there is nothing to evaluate. A node with no model class,
     * or a model with no table, is not something a query can be built
     * against; a plugin that wants to deny such an object still has
     * OBJECT_SCOPE_CHECK, which is the event written for exactly that
     * decision and is fired first.
     *
     * @param string   $node   the node being checked
     * @param int      $id     the target object id
     * @param int|null $userID the acting user (defaults to current)
     *
     * @return bool
     */
    private static function _objectPassesPluginScope($node, $id, $userID = null)
    {
        if (!self::_pluginScopeApplies($userID)) {
            return true;
        }
        $vars = self::_scopeClassVars($node);
        if (null === $vars) {
            return true;
        }
        list($table, $idCol) = $vars;
        $frag = self::_pluginScopeWhere(
            $node,
            sprintf('`%s`.`%s`', $table, $idCol),
            $userID
        );
        if (null !== $frag) {
            return self::_objectSatisfies($table, $idCol, $id, $frag);
        }
        $ids = self::_pluginScopeIDs($node, $userID);
        if (null === $ids) {
            return true;
        }
        return in_array((int)$id, $ids, true);
    }
    /**
     * The table and id column behind a node, or null when there is none.
     *
     * Memoised per node. objectInScope() is called once per id on a mass
     * operation, and reflecting a class' default properties for each of
     * several hundred ids is the shape the grid query-storm bugs had.
     *
     * @param string $node the node
     *
     * @return array|null [table, idColumn], or null
     */
    private static function _scopeClassVars($node)
    {
        $node = strtolower(trim((string)$node));
        if (array_key_exists($node, self::$_scopeClassVars)) {
            return self::$_scopeClassVars[$node];
        }
        $vars = null;
        // Resolved through qualify(), which is the same lookup getClass()
        // performs on the next line, so the guard and the call cannot disagree
        // about which class a node names.
        //
        // Two wrong spellings have already been here. __NAMESPACE__ . '\\'
        // . $node builds FOG\Auth\host, which nothing declares. The BARE
        // $node worked only while every model re-exported itself globally --
        // once those aliases went (ADR 0013 §2) it stopped resolving too.
        //
        // Both fail the same way, and it is the dangerous way: class_exists()
        // is simply false, $vars stays null, and objectInScope() then has no
        // table to test against. Nothing is logged and nothing errors. Access
        // control that cannot find its own model must never look like access
        // control that ran.
        $qualified = self::qualify($node);
        if (class_exists($qualified, true)) {
            $props = self::getClass($node, '', true);
            if (!empty($props['databaseTable'])
                && !empty($props['databaseFields']['id'])
            ) {
                $vars = [
                    $props['databaseTable'],
                    $props['databaseFields']['id']
                ];
            }
        }
        self::$_scopeClassVars[$node] = $vars;
        return $vars;
    }
    /**
     * Is there a row with this id that also satisfies the fragment?
     *
     * A query that cannot run answers NO, and that is the safe direction
     * here: this decides whether to serve a single object, so refusing one
     * the caller was entitled to is a visible, reportable failure, where
     * serving one they were not is silent.
     *
     * @param string $table the model's table
     * @param string $idCol the model's id column
     * @param int    $id    the target object id
     * @param string $frag  the boundary fragment
     *
     * @return bool
     */
    private static function _objectSatisfies($table, $idCol, $id, $frag)
    {
        $sql = sprintf(
            'SELECT COUNT(*) AS `cnt` FROM `%s` '
            . 'WHERE `%s`.`%s` = :oid AND (%s)',
            $table,
            $table,
            $idCol,
            $frag
        );
        try {
            $res = self::$DB->query($sql, [], ['oid' => (int)$id]);
            if (false !== $res->error) {
                return false;
            }
            $row = $res->fetch(\PDO::FETCH_ASSOC)->get();
        } catch (\Exception $e) {
            return false;
        }
        return is_array($row) && isset($row['cnt']) && (int)$row['cnt'] > 0;
    }
    /**
     * Enforce object scope for a management page request. Allowed →
     * returns silently. Denied → 403 JSON (AJAX) or a flash message plus
     * redirect home (full page), then exits. Mirrors the denial UX of
     * requirePagePermission.
     *
     * @param string $node the page node
     * @param int    $id   the target object id (0/none = skipped)
     *
     * @return void
     */
    public static function requirePageObjectScope($node, $id)
    {
        if (self::objectInScope($node, $id)) {
            return;
        }
        if (self::$ajax) {
            http_response_code(HTTPResponseCodes::HTTP_FORBIDDEN);
            header('Content-Type: application/json');
            echo json_encode(
                ['error' => _('You do not have permission to perform this action.')]
            );
            exit;
        }
        self::setMessage(
            _('You do not have permission to access this item.'),
            _('Permission denied'),
            'warning'
        );
        self::redirect('?node=home');
    }
    /**
     * Enforce object scope across a batch of ids (mass op). Airtight
     * stance: if ANY id is out of scope the whole request is denied — no
     * silent drop of the offending ids. The first out-of-scope id denies
     * and exits via requirePageObjectScope.
     *
     * @param string $node the page node
     * @param array  $ids  the target object ids
     *
     * @return void
     */
    public static function requirePageObjectScopeMass($node, $ids)
    {
        foreach ((array)$ids as $id) {
            self::requirePageObjectScope($node, $id);
        }
    }
    /**
     * Enforce object scope for an API request. Allowed → returns
     * silently. Denied → 403 and exit.
     *
     * @param string $class the model class url parameter
     * @param int    $id    the target object id (0/none = skipped)
     *
     * @return void
     */
    public static function requireApiObjectScope($class, $id)
    {
        if (self::objectInScope($class, $id)) {
            return;
        }
        Route::sendResponse(
            HTTPResponseCodes::HTTP_FORBIDDEN,
            json_encode(
                [
                    'error' => _('You do not have permission to perform'
                        . ' this action.')
                ]
            )
        );
    }
    /**
     * The ids of every role granting exactly this permission string.
     *
     * Reads the table directly rather than going through Route::getIds().
     * Permission strings are the one kind of value that collides with the
     * query builder's caller-facing wildcard syntax: '*' and '+' are what
     * the RBAC grammar uses for "everything" and what the builder used to
     * rewrite into a SQL '%'. Route::expandSearchWildcards() now confines
     * that rewrite to request-supplied filters, but a permission lookup
     * should not depend on that distinction holding -- it is the query
     * whose wrong answer unlocks the install, so it owns its own SQL.
     *
     * Matches the exact string only. 'host.*' and '*' are DIFFERENT
     * permissions here; wildcard semantics are applied when a permission is
     * CHECKED (see can()), not when roles holding one are listed.
     *
     * @param string $permission the permission string, e.g. '*'
     *
     * @return array role ids
     */
    public static function rolesHolding($permission)
    {
        $rows = self::$DB
            ->query(
                'SELECT DISTINCT `rpRoleID` FROM `rolePermissions` '
                . 'WHERE `rpName` = :perm',
                [],
                ['perm' => (string)$permission]
            )
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        $roleIDs = [];
        foreach ((array)$rows as $row) {
            $roleIDs[] = (int)$row['rpRoleID'];
        }
        return $roleIDs;
    }
    /**
     * Is there at least one effective administrator besides the excluded
     * users? An effective administrator is a user holding the global '*'
     * permission through any role, directly or via a group. Used by the
     * lockout guards in role/user management and by the delete guard.
     *
     * @param array $excludeUserIDs user ids to pretend do not exist
     *
     * @return bool
     */
    public static function effectiveAdminExists($excludeUserIDs = [])
    {
        return self::adminExistsGiven(['excludeUsers' => $excludeUserIDs]);
    }
    /**
     * Would at least one effective administrator remain after applying the
     * proposed changes? Simulates the change against current membership
     * and star-role state without touching the database, so every lockout
     * guard (role delete, permission edit, membership edit, user delete)
     * asks the same question the same way.
     *
     * Recognized keys in $changes (all optional):
     * - 'excludeUsers'     => [userID, ...] users to pretend do not exist
     *                         (user deletion).
     * - 'roleUsers'        => [roleID => [userID, ...]] proposed FULL
     *                         membership of specific roles.
     * - 'userRoles'        => [userID => [roleID, ...]] proposed FULL role
     *                         list of specific users.
     * - 'rolePermissions'  => [roleID => [perm, ...]] proposed FULL
     *                         permission list of specific roles
     *                         (memberships unchanged).
     * - 'removeRoles'      => [roleID, ...] roles about to be deleted —
     *                         their assocs vanish, so members lose whatever
     *                         those roles granted them.
     * - 'groupUsers'       => [groupID => [userID, ...]] proposed FULL
     *                         membership of specific groups.
     * - 'userGroups'       => [userID => [groupID, ...]] proposed FULL
     *                         group list of specific users.
     * - 'groupRoles'       => [groupID => [roleID, ...]] proposed FULL role
     *                         list of specific groups.
     * - 'removeGroups'     => [groupID, ...] groups about to be deleted —
     *                         their memberships and role assignments vanish.
     * - 'localOnly'        => true count only administrators who can
     *                         authenticate without an external identity
     *                         provider (see below).
     * - 'authSources'      => [userID => 'source'] proposed users.uAuthSource
     *                         values, '' meaning local. Only consulted under
     *                         'localOnly'.
     *
     * A user is an effective administrator when they hold '*', counting
     * roles reached both directly and through any group they belong to.
     *
     * Under 'localOnly' they must also carry an empty users.uAuthSource.
     * That column makes an account unable to log in with a local password
     * (see User::passwordValidate()), so an install whose every
     * administrator carries one has no way in at all while its directory is
     * unreachable. This is the question the break-glass guards ask; every
     * other caller wants the plain one.
     *
     * A fog-user-token deliberately does NOT count. It reaches the API and
     * not the UI, and it is a bearer secret that can be rotated, revoked or
     * simply lost -- an install whose only remaining way in is a token
     * somebody may no longer have is not one this guard should call safe.
     *
     * @param array $changes the proposed changes (see above)
     *
     * @return bool
     */
    public static function adminExistsGiven($changes = [])
    {
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        // Read the users table directly rather than through Route::getIds(),
        // for the reason rolesHolding() and _externalUsers() do and for one
        // more that is specific to this read: ids() carries the CALLER'S
        // object scope when it is serving a request, and `user` is a scoped
        // node. A request with no signed-in user is bounded to nothing at
        // all -- scopedObjectWhere() answers '1=0' -- so this read came back
        // EMPTY, and a guard that can see no users concludes no
        // administrator would remain and refuses.
        //
        // That is not hypothetical: the OIDC callback applies the provider's
        // groups before the session exists, so any sign-in whose group
        // mapping removed a role or a group membership refused itself with
        // "This would leave no account able to administer FOG." A
        // site-scoped administrator hit the same thing from the other
        // direction -- they see only their own sites' users, which is right
        // for a list and wrong for this question.
        //
        // A lockout guard asks about the WHOLE install, so it must never be
        // answered through one caller's view of it.
        $allUsers = self::_userIDs();
        $special = (count($types) ? self::_userIDs($types) : []);
        $exclude = array_map(
            'intval',
            (array)($changes['excludeUsers'] ?? [])
        );
        $users = array_diff($allUsers, $special, $exclude);
        if (!empty($changes['localOnly'])) {
            $users = array_diff($users, self::_externalUsers($changes));
        }
        /*
         * Independent of localOnly, and deliberately so. They answer
         * different questions: localOnly asks who can get in without the
         * directory, this asks who can reach the UI at all. An API-only
         * account with a local password is still a local administrator --
         * it just cannot use a browser -- and a directory-sourced account
         * that can sign in interactively is still an interactive one.
         */
        if (!empty($changes['interactiveOnly'])) {
            $users = array_diff($users, self::_apiOnlyUsers($changes));
        }
        // Direct membership as roleID => [userIDs].
        $membership = [];
        $rows = self::$DB
            ->query('SELECT `ruaRoleID`, `ruaUserID` FROM `roleUserAssoc`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $membership[(int)$row['ruaRoleID']][] = (int)$row['ruaUserID'];
        }
        // Group membership as groupID => [userIDs].
        $groupMembers = [];
        $rows = self::$DB
            ->query('SELECT `ugmGroupID`, `ugmUserID` FROM `userGroupMembers`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $groupMembers[(int)$row['ugmGroupID']][] = (int)$row['ugmUserID'];
        }
        // Group role assignments as groupID => [roleIDs].
        $groupRoles = [];
        $rows = self::$DB
            ->query('SELECT `rugGroupID`, `rugRoleID` FROM `roleUserGroupAssoc`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $groupRoles[(int)$row['rugGroupID']][] = (int)$row['rugRoleID'];
        }
        $starRoles = self::rolesHolding('*');
        // Apply direct role<->user deltas.
        foreach ((array)($changes['roleUsers'] ?? []) as $rid => $uids) {
            $membership[(int)$rid] = array_map('intval', (array)$uids);
        }
        foreach ((array)($changes['userRoles'] ?? []) as $uid => $rids) {
            $uid = (int)$uid;
            foreach ($membership as $rid => &$uids2) {
                $uids2 = array_values(array_diff($uids2, [$uid]));
            }
            unset($uids2);
            foreach ((array)$rids as $rid) {
                $membership[(int)$rid][] = $uid;
            }
        }
        // Apply group<->user membership deltas.
        foreach ((array)($changes['groupUsers'] ?? []) as $gid => $uids) {
            $groupMembers[(int)$gid] = array_map('intval', (array)$uids);
        }
        foreach ((array)($changes['userGroups'] ?? []) as $uid => $gids) {
            $uid = (int)$uid;
            foreach ($groupMembers as $gid => &$uids2) {
                $uids2 = array_values(array_diff($uids2, [$uid]));
            }
            unset($uids2);
            foreach ((array)$gids as $gid) {
                $groupMembers[(int)$gid][] = $uid;
            }
        }
        // Apply group<->role deltas.
        foreach ((array)($changes['groupRoles'] ?? []) as $gid => $rids) {
            $groupRoles[(int)$gid] = array_map('intval', (array)$rids);
        }
        foreach ((array)($changes['rolePermissions'] ?? []) as $rid => $perms) {
            $rid = (int)$rid;
            $starRoles = array_values(array_diff($starRoles, [$rid]));
            if (in_array('*', (array)$perms, true)) {
                $starRoles[] = $rid;
            }
        }
        foreach ((array)($changes['removeRoles'] ?? []) as $rid) {
            $rid = (int)$rid;
            unset($membership[$rid]);
            $starRoles = array_values(array_diff($starRoles, [$rid]));
            // A deleted role also vanishes from every group that held it.
            foreach ($groupRoles as $gid => &$rids2) {
                $rids2 = array_values(array_diff($rids2, [$rid]));
            }
            unset($rids2);
        }
        foreach ((array)($changes['removeGroups'] ?? []) as $gid) {
            $gid = (int)$gid;
            unset($groupMembers[$gid], $groupRoles[$gid]);
        }
        // Fold group-sourced roles into effective membership: every member
        // of a group inherits that group's roles.
        foreach ($groupRoles as $gid => $rids) {
            $members = $groupMembers[$gid] ?? [];
            if (!count($members) || !count($rids)) {
                continue;
            }
            foreach ($rids as $rid) {
                $membership[$rid] = array_merge(
                    $membership[$rid] ?? [],
                    $members
                );
            }
        }
        // A role-less user used to count as an implicit administrator here.
        // With the fallback gone they have no access at all, so treating
        // them as proof that someone can still administer FOG would make
        // this guard answer "yes" precisely when an install has locked
        // itself out. Only a real holder of '*' counts now.
        foreach ($starRoles as $rid) {
            if (count(array_intersect($membership[$rid] ?? [], $users))) {
                return true;
            }
        }
        return false;
    }
    /**
     * Every user id, or only those of the given uType values.
     *
     * Owns its SQL for the reason given at the head of adminExistsGiven():
     * the routed read is scoped to whoever is asking, and this question is
     * about the install rather than about the caller.
     *
     * @param array $types uType values to restrict to; all users when empty
     *
     * @return array user ids
     */
    private static function _userIDs(array $types = [])
    {
        $sql = 'SELECT `uID` FROM `users`';
        $params = [];
        if (count($types)) {
            $names = [];
            foreach (array_values($types) as $index => $type) {
                $name = sprintf('type%d', $index);
                $names[] = ':' . $name;
                $params[$name] = (int)$type;
            }
            $sql .= ' WHERE `uType` IN (' . implode(',', $names) . ')';
        }
        $rows = self::$DB
            ->query($sql, [], $params)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        $ids = [];
        foreach ((array)$rows as $row) {
            $ids[] = (int)$row['uID'];
        }
        return $ids;
    }
    /**
     * The user ids whose identity is owned by an external provider, after
     * applying any proposed users.uAuthSource changes.
     *
     * Reads the column directly rather than going through Route::getIds()
     * for the reason rolesHolding() does: an auth source is an opaque
     * plugin-chosen string, and the query builder rewrites '*' and '+' in a
     * scalar filter value into a SQL LIKE wildcard. This is a query whose
     * wrong answer unlocks -- or bricks -- the install, so it owns its SQL.
     *
     * @param array $changes the proposed changes; 'authSources' is honored
     *
     * @return array user ids
     */
    private static function _externalUsers($changes = [])
    {
        $rows = self::$DB
            ->query('SELECT `uID`, `uAuthSource` FROM `users`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        $stored = [];
        foreach ((array)$rows as $row) {
            $stored[(int)$row['uID']] = (string)$row['uAuthSource'];
        }
        return self::externalUsersGiven(
            $stored,
            (array)($changes['authSources'] ?? [])
        );
    }
    /**
     * Which of these accounts an external provider would own.
     *
     * The decision half of _externalUsers(), split out because it is the
     * part with rules in it and the only part testable without a database:
     * a proposed source REPLACES the stored one rather than adding to it,
     * and an empty (or whitespace) source means local. Getting either
     * backwards would make the break-glass guards answer confidently and
     * wrongly, in the direction that locks an install out.
     *
     * @param array $stored   userID => stored users.uAuthSource
     * @param array $proposed userID => proposed users.uAuthSource
     *
     * @return array user ids
     */
    public static function externalUsersGiven(array $stored, array $proposed)
    {
        foreach ($proposed as $uid => $source) {
            $stored[(int)$uid] = (string)$source;
        }
        $external = [];
        foreach ($stored as $uid => $source) {
            if ('' !== trim((string)$source)) {
                $external[] = (int)$uid;
            }
        }
        return $external;
    }
    /**
     * The user ids barred from interactive sign-in, after applying any
     * proposed users.uAPIOnly changes.
     *
     * Reads the column directly for the reason _externalUsers() does: this
     * is a query whose wrong answer either bricks the install or lets it be
     * bricked, so it owns its SQL rather than inheriting the query
     * builder's wildcard handling.
     *
     * @param array $changes the proposed changes; 'apiOnly' is honored
     *
     * @return array user ids
     */
    private static function _apiOnlyUsers($changes = [])
    {
        $rows = self::$DB
            ->query('SELECT `uID`, `uAPIOnly` FROM `users`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        $stored = [];
        foreach ((array)$rows as $row) {
            $stored[(int)$row['uID']] = (string)$row['uAPIOnly'];
        }
        return self::apiOnlyUsersGiven(
            $stored,
            (array)($changes['apiOnly'] ?? [])
        );
    }
    /**
     * Which of these accounts would be barred from signing in.
     *
     * The decision half of _apiOnlyUsers(), split out for the same reason
     * externalUsersGiven() is: it is the part with rules in it and the only
     * part testable without a database. A proposed value REPLACES the
     * stored one, and anything that is not the string '1' means the account
     * can still sign in -- so an absent column, a null and a '0' all read
     * as interactive, which is the direction that cannot lock anyone out.
     *
     * @param array $stored   userID => stored users.uAPIOnly
     * @param array $proposed userID => proposed users.uAPIOnly
     *
     * @return array user ids
     */
    public static function apiOnlyUsersGiven(array $stored, array $proposed)
    {
        foreach ($proposed as $uid => $flag) {
            $stored[(int)$uid] = $flag ? '1' : '0';
        }
        $apiOnly = [];
        foreach ($stored as $uid => $flag) {
            if ('1' === (string)$flag) {
                $apiOnly[] = (int)$uid;
            }
        }
        return $apiOnly;
    }
    /**
     * Is there an administrator who can actually sign in to the UI?
     *
     * @param array $changes the proposed changes (see adminExistsGiven())
     *
     * @return bool
     */
    public static function interactiveAdminExists($changes = [])
    {
        $changes['interactiveOnly'] = true;
        return self::adminExistsGiven($changes);
    }
    /**
     * Refuses a change that would leave nobody able to sign in and
     * administer FOG.
     *
     * An API-only administrator is not locked out -- it can still work over
     * REST -- so this is a weaker property than the other two guards and
     * has to be, or marking a service account API-only on an install that
     * has no interactive administrator would be refused for no reason. What
     * it protects against is the plausible accident: marking the account
     * you are signed in as, or the last one anybody signs in as, and
     * discovering that the only way back is a token that may not exist yet.
     * A brand-new API-only account has no token until somebody issues one,
     * and issuing one takes a UI session.
     *
     * PRESERVES rather than REQUIRES, exactly like
     * assertLocalAdminRemains(): an install that already has no interactive
     * administrator has nothing here to protect, and refusing its
     * operations would brick it to defend a property it already gave up.
     *
     * @param array $changes the proposed changes (see adminExistsGiven())
     *
     * @throws Exception when the last interactive administrator would be lost
     * @return void
     */
    public static function assertInteractiveAdminRemains($changes = [])
    {
        if (!self::interactiveAdminExists()) {
            return;
        }
        if (self::interactiveAdminExists($changes)) {
            return;
        }
        self::_auditRefusal(
            'guard.lastinteractiveadmin',
            'no account would be able to sign in and administer FOG'
        );
        throw new \Exception(
            _(
                'This would leave no account able to sign in and administer '
                . 'FOG.'
            )
        );
    }
    /**
     * Is there an administrator who can sign in without a directory?
     *
     * @param array $changes the proposed changes (see adminExistsGiven())
     *
     * @return bool
     */
    public static function localAdminExists($changes = [])
    {
        $changes['localOnly'] = true;
        return self::adminExistsGiven($changes);
    }
    /**
     * Refuses a change that would leave nobody able to administer FOG
     * without its directory.
     *
     * PRESERVES rather than REQUIRES, and the difference matters: it only
     * refuses when a locally-authenticating administrator exists right now.
     * An install that has deliberately moved every administrator to a
     * directory has nothing left for this to protect, and refusing its
     * operations would brick it to defend a property it already gave up.
     *
     * So the rule is "do not be the operation that removes the last one",
     * which is the same standing property assertAdminRemainsAfterDelete()
     * holds for administrators in general.
     *
     * @param array $changes the proposed changes (see adminExistsGiven())
     *
     * @throws Exception when the last local administrator would be lost
     * @return void
     */
    public static function assertLocalAdminRemains($changes = [])
    {
        if (!self::localAdminExists()) {
            return;
        }
        if (self::localAdminExists($changes)) {
            return;
        }
        self::_auditRefusal(
            'guard.lastlocaladmin',
            'no account would be able to administer FOG without its '
            . 'identity provider'
        );
        throw new \Exception(
            _(
                'This would leave no account able to administer FOG without '
                . 'its identity provider.'
            )
        );
    }
    /**
     * Builds the adminExistsGiven() change map for deleting rows of an
     * RBAC-relevant class, then refuses the delete if it would leave the
     * install with no effective administrator.
     *
     * The guards used to live only in the three management pages, so every
     * path that was not those pages -- the REST API, assocSetter()'s
     * cascade, a plugin calling the ORM -- walked straight past them. Both
     * delete paths in this codebase issue raw SQL (Route::deletemass() and
     * FOGController::destroy() each build their own DELETE), so the guard
     * has to sit on the operations rather than on a model method, which is
     * what makes it a standing property rather than a UI courtesy.
     *
     * Unrecognized classes are not RBAC-relevant and pass through
     * untouched.
     *
     * @param string $classname the model class being deleted from
     * @param array  $ids       the ids of the rows being removed
     *
     * @throws Exception when no administrator would remain
     * @return void
     */
    public static function assertAdminRemainsAfterDelete($classname, $ids)
    {
        $ids = array_values(
            array_filter(array_map('intval', (array)$ids))
        );
        if (count($ids) < 1) {
            return;
        }
        $changes = [];
        switch (strtolower((string)$classname)) {
            case 'user':
                $changes = ['excludeUsers' => $ids];
                // Deleting an administrator can also be what takes away the
                // last account able to sign in without the directory, and
                // the check below would not notice: it counts every
                // administrator, so an install left with nothing but
                // directory-sourced admins passes it while being one
                // outage away from locked out.
                self::assertLocalAdminRemains($changes);
                // And can be what takes away the last account anybody signs
                // in as, which the check below equally would not notice: an
                // install left holding nothing but API-only administrators
                // passes it while having no way into its own UI.
                self::assertInteractiveAdminRemains($changes);
                break;
            case 'role':
                $changes = ['removeRoles' => $ids];
                break;
            case 'usergroup':
                $changes = ['removeGroups' => $ids];
                break;
            case 'rolepermission':
                $changes = [
                    'rolePermissions' => self::_remaining(
                        'rolepermission',
                        $ids,
                        'roleID',
                        'name'
                    )
                ];
                break;
            case 'roleuserassociation':
                $changes = [
                    'userRoles' => self::_remaining(
                        'roleuserassociation',
                        $ids,
                        'userID',
                        'roleID'
                    )
                ];
                break;
            case 'roleusergroupassociation':
                $changes = [
                    'groupRoles' => self::_remaining(
                        'roleusergroupassociation',
                        $ids,
                        'usergroupID',
                        'roleID'
                    )
                ];
                break;
            case 'usergroupmember':
                $changes = [
                    'userGroups' => self::_remaining(
                        'usergroupmember',
                        $ids,
                        'userID',
                        'usergroupID'
                    )
                ];
                break;
            default:
                return;
        }
        if (self::adminExistsGiven($changes)) {
            return;
        }
        self::_auditRefusal(
            'guard.lastadmin',
            'no account would be able to administer FOG',
            strtolower((string)$classname),
            $ids
        );
        throw new \Exception(
            _('This would leave no account able to administer FOG.')
        );
    }
    /**
     * The state an association/permission table would be left in.
     *
     * adminExistsGiven() takes proposed FULL lists, not deltas, so for the
     * rows being deleted we look up every owner they touch and return what
     * that owner would still hold afterward. Owners not named here are
     * left alone by the simulation, which is why only the affected ones
     * need computing.
     *
     * @param string $class   the association class
     * @param array  $ids     the row ids being deleted
     * @param string $ownerBy the field naming the owning entity
     * @param string $valueBy the field holding the value being taken away
     *
     * @return array ownerID => [remaining values]
     */
    private static function _remaining($class, $ids, $ownerBy, $valueBy)
    {
        $owners = array_unique(
            array_map(
                'intval',
                (array)Route::getIds($class, ['id' => $ids], $ownerBy)
            )
        );
        $remaining = [];
        foreach ($owners as $owner) {
            $allIds = array_map(
                'intval',
                (array)Route::getIds($class, [$ownerBy => $owner], 'id')
            );
            $keptIds = array_values(array_diff($allIds, $ids));
            $remaining[$owner] = (count($keptIds) < 1)
                ? []
                : array_values(
                    (array)Route::getIds($class, ['id' => $keptIds], $valueBy)
                );
        }
        return $remaining;
    }
    /**
     * Refuses a permission grant that would be an escalation.
     *
     * A role permission row is a free-text string, so any caller able to
     * create one could invent '*' and promote itself -- the role management
     * page validated names against the registry, but the REST API reached
     * the same table without passing through it.
     *
     * Two rules: the name has to be one the registry actually knows, and
     * handing out the global wildcard requires already holding it. The
     * second is what stops a role.create holder writing itself an
     * administrator role.
     *
     * @param string $permName the permission string being granted
     *
     * @throws Exception when the grant is invalid or an escalation
     * @return void
     */
    public static function assertCanGrant($permName)
    {
        $permName = trim((string)$permName);
        if ('' === $permName) {
            throw new \Exception(_('A permission name is required.'));
        }
        if ('*' === $permName) {
            if (!self::can('*')) {
                self::_auditRefusal(
                    'guard.grant',
                    'only an administrator may grant full access',
                    'rolepermission'
                );
                throw new \Exception(
                    _('Only an administrator may grant full access.')
                );
            }
            return;
        }
        $valid = ['*'];
        foreach ((array)self::registry() as $node => $actions) {
            $valid[] = $node;
            $valid[] = $node . '.*';
            foreach ((array)$actions as $action) {
                $valid[] = $node . '.' . $action;
            }
        }
        if (!in_array($permName, $valid, true)) {
            self::_auditRefusal(
                'guard.grant',
                'unknown permission: ' . $permName,
                'rolepermission'
            );
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    _('Unknown permission'),
                    $permName
                )
            );
        }
    }
    /**
     * Reset the per-request caches (used after role/permission writes so
     * subsequent checks in the same request see the new state).
     *
     * @return void
     */
    public static function resetCache()
    {
        self::$_permCache = [];
        self::$_registry = null;
    }
    /**
     * Remove every rolePermissions row belonging to a node namespace.
     *
     * Called when a plugin is uninstalled so its permission strings do
     * not linger in roles (and reappear as orphaned entries in the role
     * permission matrix) after the node is gone from the registry.
     * Matches the bare node, its wildcard and every scoped action:
     * 'site', 'site.*', 'site.view', ... but never a sibling like
     * 'siteother' (the dot boundary is required).
     *
     * A node core owns is never purged, whatever plugin name was passed.
     * The two callers derive the prefix from `plugins.pName`, so a plugin
     * sharing its name with a core node -- which is precisely what happens
     * while a capability is being moved out of a plugin and into core --
     * would otherwise delete live grants belonging to core on uninstall.
     * The grants outlive the plugin on purpose in that case; the node has
     * not left the registry, it changed owner.
     *
     * @param string $nodePrefix the registry node (e.g. 'site')
     *
     * @return void
     */
    public static function purgePermissions($nodePrefix)
    {
        $nodePrefix = strtolower(trim((string)$nodePrefix));
        if ('' === $nodePrefix) {
            return;
        }
        if (self::isCoreOwnedNode($nodePrefix)) {
            return;
        }
        $names = array_values(
            array_unique(
                array_filter(
                    (array)Route::getIds('rolepermission', [], 'name')
                )
            )
        );
        $match = [];
        foreach ($names as $name) {
            if ($name === $nodePrefix
                || 0 === strpos($name, $nodePrefix . '.')
            ) {
                $match[] = $name;
            }
        }
        if (count($match)) {
            Route::deletemass('rolepermission', ['name' => $match]);
        }
        self::resetCache();
    }
}
