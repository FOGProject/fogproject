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

namespace FOG;

class Route extends FOGBase
{
    /**
     * Muted em-dash used to render an empty list cell (no value / no
     * associated entity) consistently across every entity list.
     *
     * @var string
     */
    const EMPTY_CELL = '<span class="text-muted">&mdash;</span>';
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
     * Where a plugin-contributed route must live.
     *
     * A reserved mount point rather than "anywhere core is not currently
     * using" (ADR 0009, Phase 2). Core mints new top-level paths from
     * $validClasses, so today's free path is not tomorrow's, and a plugin
     * route that silently started shadowing -- or worse, being shadowed by --
     * a core one on upgrade would be a very quiet failure. Under /ext/ the
     * two namespaces cannot meet, which also means declaring a plugin route
     * public can never open a core path by exact-match coincidence.
     *
     * 'ext' and not 'plugin': 'plugin' is already an API class, so /plugin/
     * belongs to core's CRUD routes for it.
     *
     * @var string
     */
    const PLUGIN_ROUTE_PREFIX = '/ext/';
    /**
     * Prefix stamped on a plugin route's name before it is registered.
     *
     * The route NAME is what resolveApiPermission() keys on, so an
     * unprefixed plugin name could land on a core mapping: a route called
     * 'status' would inherit API_ROUTE_PERMISSIONS['status'] = null and skip
     * the permission check entirely. Core owns the namespace; plugins get
     * their own.
     *
     * @var string
     */
    const PLUGIN_ROUTE_NAME_PREFIX = 'ext:';
    /**
     * Validated plugin routes, or null before the hook has been fired.
     *
     * @var array|null
     */
    private static $_pluginRoutes = null;
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
     * True only for requests that entered through the REST API
     * (api/index.php constructs a Route; the web UI calls the static
     * helpers). Lets listeners tell a genuine REST call apart from a web
     * AJAX list without trusting the client-set X-Requested-With header.
     *
     * @var bool
     */
    public static $apiRequest = false;
    /**
     * Requested relation-expansion tokens (lowercased) from ?expand=a,b,c.
     *
     * @var array
     */
    public static $expand = [];
    /**
     * True when ?expand=all was requested (expand every known relation).
     *
     * @var bool
     */
    public static $expandAll = false;
    /**
     * Re-entrancy guard so relation expansion stays depth-limited.
     *
     * @var int
     */
    protected static $expandDepth = 0;
    /**
     * Depth of the current getter() serialization stack. Non-zero means we are
     * serializing an object (or one of its sub-objects) and any listem()/getter
     * call is INTERNAL, not the entity the API route targeted. Expansion is
     * gated on this being 0 so it only decorates the top-level request and
     * never leaks into the many internal list/getter calls fired while
     * serializing relations.
     *
     * @var int
     */
    protected static $getterDepth = 0;
    /**
     * Which serialized key of which class holds another entity.
     *
     * `parent class => [ key => child class ]`, recorded by embed() at the
     * moment it serializes a nested object. It is not hand-maintained on
     * purpose: the emitter has to know what class every nested object is in
     * order to strip its secrets, and a table someone must remember to update
     * whenever getter() gains an embed is a credential leak waiting for the
     * one time they forget. Deriving it from the object being embedded means
     * it cannot drift from the code that creates the nesting.
     *
     * Keyed by class rather than by path, so it stays small and applies
     * wherever that parent appears -- a list row, a single GET, or nested
     * inside something else.
     *
     * @var array
     */
    protected static $nestedClasses = [];
    /**
     * Set while count() is borrowing listem() to reach recordsFiltered. The
     * row query and every per-row formatter are then skipped -- see the note
     * in FOGManagerController::complex(). Refs GH-707.
     *
     * @var bool
     */
    protected static $countOnly = false;
    /**
     * Related objects already resolved while formatting the current grid.
     *
     * Keyed 'class:id'. Emptied at the top of every listem() call so it can
     * never outlive one grid render: the services hold this class for their
     * whole lifetime, and a cache that persisted between calls would keep
     * serving an image or host by its old name until the daemon restarted.
     *
     * @var array
     */
    protected static $relCache = [];
    /**
     * The class the current API route is serving, recorded so printer() can
     * strip secrets from a payload that does not name its own class.
     *
     * A list payload carries a '_lang' stamp, but a single-entity GET is a
     * flat object with no such marker. Rather than add one -- which would
     * change the documented response shape for every consumer -- the class is
     * kept alongside the payload and read back at the emitter.
     *
     * @var string
     */
    protected static $emitClassname = '';
    /**
     * Maximum relation-expansion depth. Related objects are serialized with
     * plain getter() (no further expansion), so expansion is one level deep
     * and cannot recurse back onto the parent entity.
     */
    const EXPAND_MAX_DEPTH = 1;
    /**
     * How far stripEntity() will walk into a payload's nesting.
     *
     * Well above the three levels anything reaches today (task -> host ->
     * inventory). See stripEntity() for why this is a backstop rather than a
     * real bound.
     *
     * @var int
     */
    const STRIP_MAX_DEPTH = 8;
    /**
     * Hard cap on how many items a single expanded relation (or an expanded
     * list) will materialize, to bound memory. Overflow is truncated and
     * flagged with a companion `<relation>_truncated` key.
     */
    const EXPAND_MAX_ITEMS = 2500;
    /**
     * Fields stripped from any host/user object serialized as a related or
     * list item, so decrypted secrets are never exposed outside a direct
     * single-entity GET.
     *
     * @var array
     */
    /**
     * Recursion depth of deletemass(), so the lockout guard runs once per
     * operation rather than once per cascaded table.
     *
     * @var int
     */
    private static $_deleteDepth = 0;
    /**
     * Nesting depth of the getX() result wrappers.
     *
     * Non-zero means a caller below the HTTP boundary is on the stack, so
     * failed() rethrows instead of ending the response. A counter rather than
     * a flag because listem() fires hooks that may re-enter Route, and the
     * inner call must inherit the outer caller's context.
     *
     * @var int
     */
    private static $_rethrowDepth = 0;
    public static $sensitiveFields = [
        'host' => [
            'ADPass',
            'ADPassLegacy',
            'productKey',
            'pub_key',
            'sec_tok',
            'prev_sec_tok',
            'sec_time',
            'token',
        ],
    ];
    /**
     * Fields stripped from EVERY API payload, a direct single-entity GET
     * included.
     *
     * The single-GET carve-out above exists for one reason: fog-client reads
     * a host's ADPass back out to join a domain, so that field has a real
     * consumer and cannot be closed off. A secret with no such consumer
     * belongs here instead, so it never leaves the server at all.
     *
     * Anything listed here is also stripped from lists; the two tiers are
     * unioned for list payloads. Add a field here rather than above whenever
     * "some client legitimately needs to read this" is not true of it.
     *
     * Empty in core: the only entry this ever held was the LDAP plugin's
     * bindPwd, hand-written here because a plugin had no way to declare its
     * own secrets. It declares them through API_SENSITIVE_FIELDS now -- read
     * both tiers via sensitiveFieldMap(), never these properties directly,
     * or plugin-declared fields are skipped.
     *
     * @var array
     */
    public static $sensitiveAlwaysFields = [
        // A storage node's FTP credential. It reaches the root-running
        // replicator's lftp invocation and the SSH helpers in
        // Snapin/TaskQueue, so holding it is holding the node -- the same
        // credential class as GHSA-2hqx-5ffg-w4c3.
        //
        // Tier 2 rather than tier 1 because nothing reads it back over the
        // API: every consumer is server-side PHP with the object already
        // in hand (snapin.class.php, taskqueue.class.php,
        // snapinclient.class.php, the node edit page), and the FOS handoff
        // in service/hostinfo.php sends the node's IP and path only. So
        // there is no legitimate reader to carve out for, the way host
        // ADPass has one in fog-client.
        //
        // Found leaking through the storage GROUP list, not the node list:
        // the group's `masternode` column embeds the whole node object,
        // password included, to anyone holding storagegroup.view.
        // A user's API token and password hash. Tier 2 rather than tier 1
        // because nothing reads either back over the API.
        //
        // token: the API tab renders it server-side from the object
        // (UserManagement::userAPI) and the reset button posts to
        // management/status/newtoken.php -- neither goes through a REST
        // payload. It is a complete standalone credential now that
        // Authorization: Bearer accepts it, so a single-entity GET handing
        // it to any holder of user.view was the whole account.
        //
        // password: the bcrypt hash. Every consumer of a password is
        // INBOUND -- service/checkcredentials.php and the iPXE advanced
        // menu read a submitted parameter, never the stored value -- so
        // there is nothing to carve out for. A hash is not a credential
        // you can present, but it is offline-crackable, and it has no
        // business leaving the server at all.
        'user' => [
            'token',
            'password',
        ],
        'storagenode' => [
            'pass',
            'key',
        ],
        // The remember-me validator hash. userauth is not in $validClasses,
        // so no route emits it and this changes no API behaviour -- it is
        // here because the registry is now read by the audit trail as well
        // as the emitter (ADR 0021 Decision 6), and a credential column that
        // no route happens to expose is still a credential column.
        //
        // uaSelectorHash is deliberately absent: the selector is the LOOKUP
        // half of the pair and is not secret by design. Withholding it would
        // suggest it is.
        'userauth' => [
            'password',
        ],
    ];
    /**
     * Memoized union of the core tiers above and what plugins declare
     * through API_SENSITIVE_FIELDS. Null until first built.
     *
     * @var array|null
     */
    private static $_sensitiveMap = null;
    /**
     * Fields the SERVER maintains: class => [friendly keys].
     *
     * edit() and create() copy a JSON body straight into a model's
     * databaseFields, so before this list every column of every class in
     * $validClasses was settable by anyone who could reach the route --
     * and the route's own auth is thin: an api-enabled non-admin passes.
     * Reported by Aisle Research alongside 020.
     *
     * A field belongs here when the server is its only legitimate writer
     * AND it is a credential or telemetry. That is narrower than "looks
     * important" on purpose:
     *
     *   - task telemetry is written by service/progress.php through the
     *     ORM, never through this router, so nothing legitimate loses a
     *     write. Left open, an api user can rewrite another host's
     *     imaging progress -- and on dev-branch three of those five
     *     fields reach the page unescaped.
     *   - the host token set is what the fog-client protocol
     *     authenticates with. Choosing a host's sec_tok is impersonating
     *     that host. Every in-repo writer is server-side ORM
     *     (fogpage.class.php, taskqueue.class.php).
     *   - user.token is the API credential itself; writing it is taking
     *     over that account's API access. Written by the UI reset and by
     *     Route's own issue path, both server-side.
     *
     * Deliberately NOT here: host.ADPass, ADPassLegacy and productKey.
     * They are secrets, and they are also things an admin legitimately
     * sets through the API -- the emitter strips them from list output,
     * which is a different question from who may write them. Nor
     * user.password: User::set() hashes it, so a supplied one is a real
     * and supported write.
     *
     * Read through serverOwnedFields(), never this property directly, or
     * plugin-declared entries are skipped.
     *
     * @var array
     */
    public static $serverOwnedFields = [
        'task' => [
            'pct',
            'bpm',
            'timeElapsed',
            'timeRemaining',
            'dataCopied',
            'dataTotal',
            'percent',
        ],
        'host' => [
            'pub_key',
            'sec_tok',
            'prev_sec_tok',
            'sec_time',
            'token',
        ],
        'user' => [
            'token',
        ],
    ];
    /**
     * Memoized union of the list above and what plugins declare through
     * API_SERVER_OWNED_FIELDS. Null until first built.
     *
     * @var array|null
     */
    private static $_serverOwnedMap = null;
    /**
     * globalSettings rows whose VALUE is a credential.
     *
     * Settings are the odd one out: they are key/value rows, so the secret is
     * the value of a particular *row* rather than a column present on every
     * row. A setting matched here has its 'value' removed from API output
     * while its name, description and category stay, so a consumer can still
     * see the setting exists.
     *
     * Matching is a pattern plus a short explicit list rather than a hand
     * maintained enumeration of every key, so a credential setting added
     * later is masked by default instead of silently leaking until someone
     * remembers to add it. The pattern deliberately requires PASSWORD/PASSWD/
     * PWD and not a bare "PASS": FOG_USER_MINPASSLENGTH,
     * FOG_USER_VALIDPASSCHARS and FOG_USER_VALIDPASSHELPMSG are password
     * *policy* and must stay readable for the UI to describe its own rules.
     *
     * @var string
     */
    const SENSITIVE_SETTING_PATTERN = '#(PASSWORD|PASSWD|PWD|SECRET|TOKEN)#i';
    /**
     * Credential settings the pattern does not catch.
     *
     * @var array
     */
    public static $sensitiveSettings = [
        'FOG_STORAGENODE_MYSQLPASS',
        // FOGBase::NODE_API_KEY_SETTING, as a literal: a class constant here
        // would autoload FOGCore while Route's own class body is still being
        // built. SENSITIVE_SETTING_PATTERN does not cover it -- adding KEY to
        // that pattern would mask unrelated settings -- and it is a shared
        // HMAC secret, so it must not be readable over REST.
        'FOG_NODE_API_KEY',
    ];
    /**
     * Settings the pattern catches that are not credentials.
     *
     * FOG_ENABLE_SHOW_PASSWORDS is a boolean toggle whose name merely
     * contains "PASSWORDS"; masking it would hide a UI preference.
     *
     * @var array
     */
    public static $sensitiveSettingsExempt = [
        'FOG_ENABLE_SHOW_PASSWORDS',
    ];
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
        'role',
        'rolepermission',
        'roleuserassociation',
        'roleusergroupassociation',
        'scheduledtask',
        'setting',
        'site',
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
        'usergroup',
        'usergroupmember',
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
     * Classes the API serves for reading only.
     *
     * ADR 0020 decision 7: the three event tables lose their write routes.
     * These are the records of what happened, and a record of what happened
     * that can be edited through the same API that produced it is not a
     * record. Retention pruning, if it is ever wanted, is a named operation
     * with its own permission -- not `DELETE /api/history/{id}`.
     *
     * Listed here rather than removed from $validClasses, because the read
     * side of each is legitimate and used: the Hosts And Users report and
     * the Login History tabs go through usertracking.view, Task Management's
     * log pane and the activity viewer read tasklog, and the activity viewer
     * and History_Report read history.
     *
     * WHAT EACH ONE WAS EXPOSING. None of these were reachable by accident;
     * they were all grantable permissions that worked.
     *
     *  - `usertracking` -> the node has no `create` at all in
     *    coreRegistry(): rows come from the fog-client's own endpoint, which
     *    is node `client` and permission-exempt, so nothing legitimate POSTs
     *    one. The generic routes offered create, join, update and delete
     *    anyway, on movement records for named people.
     *  - `history` -> resolves to `report`, whose node declares
     *    view/create/edit/delete. So a `report.delete` grant could remove
     *    rows from the administrative audit trail, and REST DELETE funnels
     *    through deletemass() rather than destroy(), so it did not even pass
     *    the model.
     *  - `tasklog` -> resolves to `task`. A `task.delete` grant could rewrite
     *    or remove the imaging and task reports that GH-1206 exists to keep
     *    findable after the fact.
     *
     * Nothing in FOG calls any of them -- no page, no JS, no service. They
     * existed because the route map expanded over every class without asking
     * whether each one should be writable.
     *
     * An unmatched route is a 404, not a 403, and that is the honest answer:
     * the operation does not exist rather than being withheld.
     *
     * OpenAPI::_classPaths() reads this too, through writableClasses(), so
     * the document stops advertising the four verbs in the same commit that
     * stops answering them -- it is generated from this list rather than
     * from a second copy of it.
     *
     * @var array
     */
    public static $readOnlyClasses = [
        'history',
        'tasklog',
        'usertracking'
    ];
    /**
     * $validClasses minus the read-only ones, for the write routes.
     *
     * @return array
     */
    public static function writableClasses()
    {
        return array_values(
            array_diff(
                (array)self::$validClasses,
                (array)self::$readOnlyClasses
            )
        );
    }
    /**
     * Initialize element.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$apiRequest = true;
        list(
            self::$_enabled,
            self::$_token,
            $webrootsetting
        ) = self::getSetting(
            [
                'FOG_API_ENABLED',
                'FOG_API_TOKEN',
                'FOG_WEB_ROOT'
            ]
        );
        /**
         * GH-529: the paths below were written as literal '/fog/...', so at a
         * custom webroot none of them matched. Anchor them to the configured
         * webroot instead, normalised the same way BootMenu does since the
         * setting turns up with and without either slash.
         *
         * Every mismatch here fails closed -- a wrong or empty setting makes
         * the prefixes stop matching, so an endpoint reverts to requiring
         * auth. There is no value of FOG_WEB_ROOT that opens anything the
         * literal '/fog/' did not already open.
         */
        $webrootbase = trim((string)$webrootsetting, '/');
        $webrootbase = '/' . ($webrootbase === '' ? '' : $webrootbase . '/');
        self::$_webrootbase = $webrootbase;

        /**
         * If API is not enabled redirect to home page.
         */
        if (!self::$ajax && !self::$_enabled) {
            header(
                sprintf(
                    'Location: %s://%s%smanagement/index.php',
                    self::$httpproto,
                    self::$httphost,
                    $webrootbase
                ),
                true,
                308
            );
            exit;
        }
        /**
         * Routes reachable without API auth. The wildcard routes below
         * vary in their trailing segment, so they are matched on their
         * parent path via dirname(). The /system endpoints are matched
         * exactly instead: status/info are public (dashboard polling),
         * but privileged siblings like /system/export must NOT be swept
         * in by a shared parent-path match.
         */
        $unauthprefixes = [
            $webrootbase . 'bandwidth',
            $webrootbase . 'storagegroupid',
            $webrootbase . 'storagenodeid'
        ];
        $unauthexact = [
            $webrootbase . 'system/status',
            $webrootbase . 'system/info',
            $webrootbase . 'system/openapi',
            // swagger.json is where a great many people and tools look first,
            // Swagger UI having been the name for this long before it was
            // renamed OpenAPI. Same handler, same document.
            $webrootbase . 'swagger.json'
        ];
        /**
         * A plugin may declare one of its /ext/ routes reachable without API
         * auth -- an IdP redirecting a browser back to a callback carries no
         * token and no session, so the request must be answerable before
         * either exists. Only an exact literal path can be declared this way
         * (see _validatePluginRoute), matching how the /system endpoints are
         * handled rather than the parent-path rule the wildcard routes use.
         *
         * Being unauthenticated here does NOT mean unguarded: the handler
         * still runs behind whatever it checks for itself, and the route
         * carries no permission, so nothing in FOG's data is reachable
         * through it that the plugin did not deliberately expose.
         */
        foreach (self::pluginRoutes() as $pluginRoute) {
            if ('public' !== $pluginRoute['auth']) {
                continue;
            }
            $unauthexact[] = $webrootbase . ltrim($pluginRoute['path'], '/');
        }
        $requripath = strtok((string)self::$requesturi, '?');
        $requribase = dirname($requripath);
        $isunauth = in_array($requribase, $unauthprefixes)
            || in_array(rtrim($requripath, '/'), $unauthexact);
        /**
         * Snapshot auth state BEFORE running the token/basic-auth tests.
         * At this point FOGUser is valid only when the request arrived
         * already-authenticated via the browser session cookie (populated
         * by loadglobals). Token/basic-auth API clients arrive invalid and
         * become valid inside the block below, so they are excluded here.
         * That distinction is what lets us enforce CSRF on session-cookie
         * traffic (the CSRF-able surface) without touching headless clients.
         */
        $sessionAuthed = self::$FOGUser->isValid();
        if (!$sessionAuthed
            && !$isunauth
        ) {
            /**
             * A Bearer token authenticates on its own -- see _testBearer for
             * why it does not additionally need the server-wide token. The
             * legacy schemes apply only when no Bearer credential is offered,
             * so every existing client is unaffected.
             */
            if (!self::_testBearer()) {
                /**
                 * Test our token.
                 */
                self::_testToken();
                /**
                 * Test our authentication.
                 */
                self::_testAuth();
            }
        }
        /**
         * A valid session cookie authenticates API calls with no token,
         * so state-changing routes reached that way are CSRF-able. The web
         * UI already sends X-CSRF-Token on every same-origin request
         * (bootstrap-csrf.js); headless clients use a token instead of a
         * session cookie and are exempt via $sessionAuthed above.
         */
        if ($sessionAuthed) {
            CSRF::requireForStateChanging();
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
        /**
         * GH-529: the base path was the literal '/fog', so every route was
         * registered somewhere the request could never reach on a custom
         * webroot. AltoRouter wants it without the trailing slash, and an
         * install served from the document root itself wants it empty.
         */
        self::$router = new \AltoRouter(
            [],
            rtrim(self::$_webrootbase, '/')
        );
        self::defineRoutes();
        self::setMatches();
        self::runMatches();
        self::printer(self::$data);
    }
    /**
     * The configured webroot, normalised with a leading and trailing slash.
     *
     * Exposed so OpenAPI can build servers[].url from the same value the
     * router anchors its paths to, rather than reconstructing it from the
     * setting and risking the two disagreeing (GH-529).
     *
     * @return string
     */
    public static function webrootbase()
    {
        return self::$_webrootbase;
    }
    /**
     * Just ensures the where items are consistent for later use
     *
     * A STRING argument is request-supplied (the `[*:whereItems]` URL
     * segment), so its values get the caller-facing wildcard convenience --
     * see expandSearchWildcards(). An ARRAY argument came from PHP code and
     * is left exactly as passed, so a value is matched literally.
     *
     * A STRING argument is also the only way an arbitrary filter key can
     * reach _buildSql: parse_str() takes whatever the URL segment spells.
     * Pass $class to have those keys checked against the class before they
     * get that far, so a caller is told its filter key is wrong instead of
     * silently receiving a match-nothing result. The JSON search body needs
     * no such check -- getsearchbody() already intersects with the class's
     * own fields. Internal callers pass arrays and are not validated here;
     * a bad key from PHP code is a programming error, handled in _buildSql.
     *
     * A caller may also send the same string as `?filter=`, which is the
     * only form a generated client can use. OpenAPI cannot mark a PATH
     * parameter optional, so documenting the trailing segment would mean a
     * second path per operation per class -- 204 of them -- and every
     * generator would emit a separate operation for each. A query parameter
     * documents as one optional argument on the operation that already
     * exists. The segment keeps working and still wins when both are sent;
     * nothing that relied on it changes.
     *
     * @param string|array $whereItems The test item.
     * @param string       $class      Class to validate request keys against.
     * @return array $whereItems The normalized structure
     */
    /**
     * ?filter= belonging to the route currently being dispatched.
     *
     * Null except between runMatches() capturing it and the route handler
     * consuming it. See handleWhereItems() for why it is not simply read
     * from the query string at the point of use.
     *
     * @var string|null
     */
    private static $_requestFilter = null;
    /**
     * Records the dispatched route's ?filter=, if it takes one.
     *
     * Named routes only, so a filter cannot be smuggled into a route that
     * does not advertise one -- search in particular, which builds its own
     * where clause and would otherwise be overridable from the request.
     *
     * @param string $routeName The matched route's name.
     *
     * @return void
     */
    public static function captureRequestFilter($routeName)
    {
        self::$_requestFilter = null;
        if (!in_array($routeName, ['list', 'count', 'names', 'ids'], true)) {
            return;
        }
        $filter = self::queryParam('filter');
        if (null !== $filter && '' !== $filter) {
            self::$_requestFilter = $filter;
        }
    }
    /**
     * Returns the captured filter and clears it.
     *
     * @return string|null
     */
    public static function takeRequestFilter()
    {
        $filter = self::$_requestFilter;
        self::$_requestFilter = null;
        return $filter;
    }
    public static function handleWhereItems($whereItems, $class = null)
    {
        // ?filter= from the dispatched route, consumed once. Only when
        // there is NO filter already -- false from listem()'s default, or an
        // empty array from names()/ids(); a NON-EMPTY array is a filter built
        // in PHP and must never be replaceable from the request, because
        // search() hands its matched ids down this way.
        //
        // Consumed rather than read, and captured in runMatches() rather than
        // read from the query string here, because this helper is shared by
        // the routes and by a great deal of internal code that legitimately
        // passes no filter at all. Reading the request directly made every
        // one of those calls pick the caller's filter up -- including
        // getActivePlugins(), which lists `hookevent` during LoadGlobals
        // before any route has been dispatched, so ?filter=hostID=1 turned
        // the whole API into a 500 at boot. Taking it once means the
        // dispatched handler gets it and every nested call sees null.
        if (false === $whereItems || (is_array($whereItems) && count($whereItems) < 1)) {
            $filter = self::takeRequestFilter();
            if (null !== $filter && '' !== $filter) {
                $whereItems = $filter;
            }
        }
        if (is_string($whereItems)) {
            parse_str(urldecode($whereItems), $whereItems);

            // Process comma-separated values
            foreach ($whereItems as $key => $val) {
                if (!empty($val) && strpos($val, ',') !== false) {
                    $whereItems[$key] = explode(',', $val);
                }
            }
            $whereItems = self::expandSearchWildcards($whereItems);
            if ($class) {
                self::_assertFilterKeys($whereItems, $class);
            }
        }
        return $whereItems;
    }
    /**
     * Rejects request-supplied filter keys the class does not declare.
     *
     * Without this an unknown key reached _buildSql, which mapped it to an
     * empty column identifier and emitted `WHERE `` = :where_0`. The DB
     * rejected that outright, so /count and /list answered with a raw
     * SQLSTATE string and /ids and /names answered HTTP 200 with [] --
     * indistinguishable from "your filter matched nothing".
     *
     * Names the offending key deliberately: the caller is already
     * authenticated, and a filter error the caller cannot see the cause of
     * is the thing being fixed. Only the key is echoed, never the SQL.
     *
     * @param array  $whereItems The parsed request filters.
     * @param string $class      The class being queried.
     *
     * @return void
     */
    private static function _assertFilterKeys($whereItems, $class)
    {
        if (count($whereItems ?: []) < 1) {
            return;
        }
        self::_assertNoSensitiveFilter($whereItems, $class);
        $classVars = self::getClass($class, '', true);
        // Blocked fields are dropped from the advertised list as well as
        // refused above -- an error that names them as valid alternatives
        // would be telling the caller to retry with the one thing this
        // refuses.
        $valid = array_values(
            array_diff(
                array_keys((array)$classVars['databaseFields']),
                self::unfilterableFields($class)
            )
        );
        $unknown = array_diff(array_keys($whereItems), $valid);
        if (count($unknown) < 1) {
            return;
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_BAD_REQUEST,
            json_encode(
                [
                    'error' => sprintf(
                        _('Unknown filter field(s) for %s: %s'),
                        strtolower($class),
                        implode(', ', $unknown)
                    ),
                    'valid' => $valid
                ]
            )
        );
    }
    /**
     * Fields a REQUEST may never filter or search on.
     *
     * The same list the emitter strips, read from the same place, because
     * two lists that must agree are two lists that will not. Plugin secrets
     * declared through API_SENSITIVE_FIELDS are included by construction --
     * sensitiveFieldMap() fires that event -- so a plugin gets this
     * protection without knowing the rule exists.
     *
     * Both tiers, deliberately. 'always' is never returned at all; 'fields'
     * is returned on a direct single-entity GET and stripped everywhere
     * else. Filtering is neither: it is a question asked of every row at
     * once, which is exactly the shape a single-GET exemption is not meant
     * to cover.
     *
     * NOT the place for globalSettings values. A setting's value is
     * ordinary configuration for all but a handful of keys, so it is
     * filtered per ROW against isSensitiveSetting() rather than blocked
     * per FIELD -- blocking the field would take "which setting holds
     * bzImage" away to protect four passwords.
     *
     * @param string $class The entity being filtered.
     *
     * @return array friendly field names
     */
    public static function unfilterableFields($class)
    {
        $map = self::sensitiveFieldMap();
        $classname = strtolower(trim((string)$class));
        return array_values(
            array_unique(
                array_merge(
                    (array)($map['always'][$classname] ?? []),
                    (array)($map['fields'][$classname] ?? [])
                )
            )
        );
    }
    /**
     * Refuses a request that filters on a field the emitter would strip.
     *
     * Rejected rather than silently ignored. Dropping the key would answer
     * with the UNFILTERED set, which is a worse surprise than an error --
     * a caller asking for one host would get all of them. The field names
     * are already public in the OpenAPI document, so naming them here tells
     * an attacker nothing they could not read from /system/openapi.
     *
     * @param array  $whereItems The request-supplied filter.
     * @param string $class      The entity being filtered.
     *
     * @return void
     */
    private static function _assertNoSensitiveFilter($whereItems, $class)
    {
        $blocked = array_intersect(
            array_keys((array)$whereItems),
            self::unfilterableFields($class)
        );
        if (count($blocked) < 1) {
            return;
        }
        self::sendResponse(
            HTTPResponseCodes::HTTP_BAD_REQUEST,
            json_encode(
                [
                    'error' => sprintf(
                        _('Cannot filter %s on: %s'),
                        strtolower($class),
                        implode(', ', $blocked)
                    )
                ]
            )
        );
    }
    /**
     * Turn the caller-facing wildcards '*' and '+' into the SQL '%'.
     *
     * This USED to live in _buildSql(), where it ran against every filter
     * value including ones built in PHP -- so any internal lookup whose
     * value could legitimately contain '*' or '+' silently became a LIKE
     * against a wildcard. Authorization::adminExistsGiven() looked up the
     * holders of the RBAC global permission with ['name' => '*'], which
     * compiled to `rpName LIKE '%'` and matched every permission row; the
     * lockout guard consequently believed an administrator always remained
     * and let an install delete its last one.
     *
     * The convenience is real, but it belongs to values that came from the
     * request -- a URL where-string or the JSON search body -- not to
     * values assembled by code. Applying it at those two entry points
     * instead of in the builder keeps the feature and removes the trap.
     *
     * _buildSql() still switches to LIKE when a value contains '%', so a
     * caller that genuinely wants a pattern (the capone plugin looks up
     * 'FOG_PLUGIN_CAPONE_%') passes one and is unaffected.
     *
     * @param array $whereItems the parsed filters
     *
     * @return array
     */
    public static function expandSearchWildcards($whereItems)
    {
        foreach ((array)$whereItems as $key => $val) {
            if (is_array($val)) {
                // Array values compile to IN (...) and were never wildcarded.
                continue;
            }
            if (!is_string($val)) {
                continue;
            }
            $whereItems[$key] = str_replace(['+', '*'], '%', $val);
        }
        return $whereItems;
    }
    /**
     * Routes contributed by plugins, validated and normalised.
     *
     * Closes ADR 0009's first gap: every plugin-facing router hook until now
     * mutated a CLASS LIST, so a plugin could add a resource but never a
     * path. An OIDC callback is not a CRUD shape on any class, and neither is
     * most of what a provider needs.
     *
     * Fired once and memoised, because the answer is needed twice and in two
     * different phases -- before authentication, to know which paths are
     * declared public, and again at defineRoutes() to register them.
     *
     * Shape of an entry, as a plugin supplies it:
     *
     *   [
     *     'name'       => 'oidcCallback',
     *     'method'     => 'GET',                     // default GET
     *     'path'       => '/ext/oidc/callback',      // must be under /ext/
     *     'handler'    => [OidcRoutes::class, 'callback'],
     *     'auth'       => 'public',                  // default 'required'
     *     'permission' => 'oidc.view'                // when auth is required
     *   ]
     *
     * Everything about the validation below fails closed. A malformed entry
     * is dropped, an unrecognised 'auth' becomes 'required', and a route that
     * declares no permission is still registered but resolves to a
     * permission no role can hold (see Authorization::resolveApiPermission),
     * so it answers 403 with a log line naming the fix rather than 404 with
     * nothing. Silence is the one outcome not on offer.
     *
     * @return array
     */
    public static function pluginRoutes()
    {
        if (self::$_pluginRoutes !== null) {
            return self::$_pluginRoutes;
        }
        $declared = [];
        self::$HookManager->processEvent(
            'API_PLUGIN_ROUTES',
            ['routes' => &$declared]
        );
        $routes = [];
        $seen = [];
        foreach ((array)$declared as $entry) {
            $route = self::_validatePluginRoute((array)$entry, $seen);
            if ($route === null) {
                continue;
            }
            $seen[$route['name']] = true;
            $routes[] = $route;
        }
        self::$_pluginRoutes = $routes;
        return self::$_pluginRoutes;
    }
    /**
     * Check one plugin route declaration, or reject it.
     *
     * @param array $entry The declaration as the plugin supplied it.
     * @param array $seen  Names already taken, as name => true.
     *
     * @return array|null The normalised route, or null to drop it.
     */
    private static function _validatePluginRoute(array $entry, array $seen)
    {
        $reject = function ($why) use ($entry) {
            error_log(
                sprintf(
                    'FOG plugin route: ignoring %s -- %s',
                    isset($entry['path']) && is_string($entry['path'])
                        ? $entry['path']
                        : '(no path)',
                    $why
                )
            );
            return null;
        };
        $name = isset($entry['name']) ? (string)$entry['name'] : '';
        $path = isset($entry['path']) ? (string)$entry['path'] : '';
        if ('' === $name || !preg_match('#^[A-Za-z][A-Za-z0-9_]*$#', $name)) {
            return $reject('the name must be a bare identifier');
        }
        $name = self::PLUGIN_ROUTE_NAME_PREFIX . $name;
        if (isset($seen[$name])) {
            return $reject(sprintf('another plugin already registered %s', $name));
        }
        if (0 !== strpos($path, self::PLUGIN_ROUTE_PREFIX)
            || false !== strpos($path, '..')
        ) {
            return $reject(
                sprintf('a plugin route must live under %s', self::PLUGIN_ROUTE_PREFIX)
            );
        }
        if (!isset($entry['handler']) || !is_callable($entry['handler'])) {
            return $reject('the handler is not callable');
        }
        $method = strtoupper(
            isset($entry['method']) ? (string)$entry['method'] : 'GET'
        );
        if (!preg_match('#^(GET|POST|PUT|PATCH|DELETE|HEAD)(\|(GET|POST|PUT|PATCH|DELETE|HEAD))*$#', $method)) {
            return $reject(sprintf('unsupported method %s', $method));
        }
        // Anything but the exact string 'public' is 'required'. A typo must
        // not open a route, and neither must a truthy value someone thought
        // meant "needs auth".
        $auth = (isset($entry['auth']) && 'public' === $entry['auth'])
            ? 'public'
            : 'required';
        if ('public' === $auth && false !== strpos($path, '[')) {
            // The unauthenticated test is an exact string comparison against
            // the request URI, so a path with router parameters in it cannot
            // be matched there -- it would be declared public and then still
            // demand auth, which is worse than refusing.
            error_log(
                sprintf(
                    'FOG plugin route: %s cannot be public because it has URL '
                    . 'parameters; treating it as authenticated. Split the '
                    . 'literal part into its own route if it must be reachable '
                    . 'before login.',
                    $path
                )
            );
            $auth = 'required';
        }
        $permission = null;
        if ('required' === $auth
            && isset($entry['permission'])
            && is_string($entry['permission'])
            && '' !== $entry['permission']
        ) {
            $permission = $entry['permission'];
        }
        return [
            'name' => $name,
            'method' => $method,
            'path' => $path,
            'handler' => $entry['handler'],
            'auth' => $auth,
            'permission' => $permission,
        ];
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
        // The four write verbs below expand over this, not $expanded: see
        // $readOnlyClasses.
        $expandedw = sprintf(
            '/[%s:class]',
            implode('|', self::writableClasses())
        );
        self::$router->map(
            'HEAD|GET',
            '/system/[status|info]',
            [__CLASS__, 'status'],
            'status'
        )->get(
            '/system/openapi',
            [__CLASS__, 'openapi'],
            'openapi'
        )->get(
            // Alias. swagger.json is the filename people and tooling reach
            // for first -- Swagger UI predates the OpenAPI rename and the
            // habit stuck. Same handler, same document, so neither name is
            // the one that goes stale.
            '/swagger.json',
            [__CLASS__, 'openapi'],
            'openapiSwaggerAlias'
        )->get(
            '/system/export',
            [__CLASS__, 'export'],
            'export'
        )->map(
            'GET|POST',
            '/[search|unisearch]/[*:item]/[i:limit]?',
            [__CLASS__, 'unisearch'],
            'unisearch'
        )->map(
            'PUT|POST',
            "{$expandedw}/join",
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
            "{$expandeda}/[current|active]",
            [__CLASS__, 'active'],
            'active'
        )->get(
            "{$expanded}/count/[*:whereItems]?",
            [__CLASS__, 'count'],
            'count'
        )->get(
            "{$expanded}/names/[*:whereItems]?",
            [__CLASS__, 'names'],
            'names'
        )->get(
            "{$expanded}/ids/[*:whereItems]?/[*:getField]?",
            [__CLASS__, 'ids'],
            'ids'
        )->get(
            '/bandwidth/[*:dev]',
            [__CLASS__, 'bandwidth'],
            'bandwidth'
        )->get(
            "{$expanded}/search/[*:item]",
            [__CLASS__, 'search'],
            'search'
        )->get(
            "{$expanded}/[i:id]",
            [__CLASS__, 'indiv'],
            'indiv'
        )->get(
            "{$expanded}/[list|all]?/[*:whereItems]?",
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
            "{$expandedw}/[i:id]/[update|edit]?",
            [__CLASS__, 'edit'],
            'update'
        )->post(
            "{$expandedt}/[i:id]/[task]",
            [__CLASS__, 'task'],
            'task'
        )->post(
            '/snapin/createwithfile',
            [__CLASS__, 'createSnapinWithFile'],
            'snapinCreateWithFile'
        )->post(
            '/storagegroup/[i:id]/uploadsnapinfiles',
            [__CLASS__, 'uploadSnapinFiles'],
            'uploadSnapinFiles'
        )->post(
            "{$expandedw}/[create|new]?",
            [__CLASS__, 'create'],
            'create'
        )->get(
            '/settings/cache',
            [__CLASS__, 'settingsCacheView'],
            'settingsCacheView'
        )->post(
            '/settings/cache/flush',
            [__CLASS__, 'settingsCacheFlush'],
            'settingsCacheFlush'
        )->post(
            '/settings/cache/refresh',
            [__CLASS__, 'settingsCacheRefresh'],
            'settingsCacheRefresh'
        )->delete(
            "{$expandedt}/[i:id]?/[cancel]",
            [__CLASS__, 'cancel'],
            'cancel'
        )->delete(
            "{$expandedw}/[i:id]/[delete|remove]?",
            [__CLASS__, 'delete'],
            'delete'
        );
        /**
         * Plugin routes go on LAST, so a core path always matches first --
         * AltoRouter returns the first route that matches, and the /ext/
         * mount point means the two sets cannot overlap anyway. Belt and
         * braces on purpose: this is a seam that lets third-party code answer
         * a URL, and it should be safe for two independent reasons.
         *
         * The permission each declares is handed to Authorization here rather
         * than read from the route later, so there is exactly one moment at
         * which a route and its permission are decided together.
         */
        foreach (self::pluginRoutes() as $route) {
            self::$router->map(
                $route['method'],
                $route['path'],
                $route['handler'],
                $route['name']
            );
            if ('public' === $route['auth']) {
                // Declared public: no permission, because there is no
                // authenticated user to hold one.
                Authorization::declareRoutePermission($route['name'], null);
            } elseif (null !== $route['permission']) {
                Authorization::declareRoutePermission(
                    $route['name'],
                    $route['permission']
                );
            }
            // No third branch, and the absence is the point. A route that
            // declared no permission is registered but NOT declared here, so
            // resolveApiPermission() finds nothing and denies it. Passing null
            // through instead would read as "public" and hand an undeclared
            // route a free pass -- which is precisely the inversion this seam
            // must not have, and is what the gate test caught when this code
            // did exactly that.
        }
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
            Authorization::requireApiPermission(
                Authorization::resolveApiPermission(
                    self::$matches['name'] ?? '',
                    self::$matches['params']['class'] ?? ''
                ),
                // The class and id the route addressed, so the audit header
                // says WHAT was acted on and not only which permission was
                // consulted. Resolution itself is unchanged.
                self::$matches['params']['class'] ?? '',
                self::$matches['params']['id'] ?? 0
            );
            // Object-scope boundary (optional, plugin-enforced): a per-object
            // REST call carries the target id; confirm it is within the acting
            // user's scope. Inert unless a listener registers.
            Authorization::requireApiObjectScope(
                self::$matches['params']['class'] ?? '',
                self::$matches['params']['id'] ?? 0
            );
            $args = array_values(self::$matches['params']);
            // Capture ?filter= for the route about to run. Done HERE, in
            // the dispatcher, because this is the only place that knows the
            // request matched a filterable route -- see handleWhereItems().
            self::captureRequestFilter(self::$matches['name'] ?? '');
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
        if (!hash_equals((string)self::$_token, (string)$passtoken)) {
            // Before sendResponse(), which exits. The presented token is
            // NOT recorded -- a rejected credential is still a credential,
            // and #1261/#1262 was exactly this mistake in the SQL fault log.
            Audit::record(
                [
                    'type' => Audit::TOKEN_REJECTED,
                    'outcome' => Audit::DENIED,
                    'subjectType' => 'system',
                    'authSource' => 'api-token',
                    'renderable' => 1
                ]
            );
            self::sendResponse(
                HTTPResponseCodes::HTTP_FORBIDDEN
            );
        }
    }
    /**
     * Reads a $_SERVER value, preferring filter_input.
     *
     * filter_input(INPUT_SERVER) reads the SAPI's original request table,
     * not the live $_SERVER array. Keys PHP synthesises after startup --
     * PHP_AUTH_USER/PHP_AUTH_PW among them -- can therefore come back null
     * under FPM even though $_SERVER holds them. Falling back to $_SERVER
     * is the only way to see them reliably; the value is treated as
     * untrusted either way (see _basicAuthCredentials).
     *
     * @param string $key The $_SERVER key to read.
     *
     * @return string
     */
    private static function _serverVar($key)
    {
        $val = filter_input(INPUT_SERVER, $key);
        if ($val === null || $val === false) {
            $val = $_SERVER[$key] ?? '';
        }
        return (string)$val;
    }
    /**
     * Resolves HTTP basic auth credentials for the current request.
     *
     * PHP only populates PHP_AUTH_USER/PHP_AUTH_PW when the Authorization
     * header actually reaches it. Under FastCGI it does not arrive on its
     * own: nginx passes only the fastcgi_params whitelist, and Apache's
     * proxy_fcgi strips it unless CGIPassAuth/SetEnvIf puts it back. FOG's
     * installer now emits that config, but every install that predates the
     * fix still has webserver config without it, and those must keep
     * working without a reinstall -- so decode the header ourselves when
     * PHP_AUTH_USER is absent.
     *
     * REDIRECT_HTTP_AUTHORIZATION is checked too: the Apache vhost rewrites
     * /fog/* into /fog/api/index.php, and Apache prefixes REDIRECT_ onto
     * environment variables that survive an internal redirect.
     *
     * This only recovers a credential the client already sent. It is not a
     * second way in: the caller still has to clear passwordValidate() and
     * the uAllowAPI test below.
     *
     * @return array The username and password, empty strings if absent.
     */
    private static function _basicAuthCredentials()
    {
        $user = self::_serverVar('PHP_AUTH_USER');
        if ($user !== '') {
            return [$user, self::_serverVar('PHP_AUTH_PW')];
        }
        $keys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION'
        ];
        foreach ($keys as $key) {
            $header = trim(self::_serverVar($key));
            if (stripos($header, 'basic ') !== 0) {
                continue;
            }
            // strict base64 decode: a malformed header is not a credential.
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded === false || strpos($decoded, ':') === false) {
                continue;
            }
            // Split on the first colon only -- colons are legal in a
            // password, but not in a username.
            return explode(':', $decoded, 2);
        }
        return ['', ''];
    }
    /**
     * Reads an RFC 6750 Bearer credential from the Authorization header.
     *
     * The value is an APIToken -- a `fog_`-prefixed string shown once at
     * creation -- sent EXACTLY as issued, with no encoding applied.
     *
     * It is deliberately not base64, and this is the one place the two token
     * systems visibly differ. users.uAPIToken is base64 in its header
     * because the UI displays it that way and always has. An APIToken is
     * displayed once, by us, so the string the user copies can simply be the
     * string the server wants -- there is nothing to encode and nothing to
     * get wrong. Since Bearer accepts only APITokens (ADR 0027), no
     * ambiguity arises from the two spellings.
     *
     * REDIRECT_HTTP_AUTHORIZATION is read for the reason _basicAuthCredentials
     * explains -- under FastCGI the Authorization header does not arrive on
     * its own, and Apache prefixes REDIRECT_ onto what survives the vhost's
     * internal rewrite. That plumbing is why Bearer needs no new webserver
     * config: it rides the header basic auth already had to recover by hand.
     *
     * @return string|null The decoded token, or null when no Bearer
     *                     credential was presented at all. An empty string
     *                     means one was presented and was malformed, which
     *                     is a failed attempt rather than an absent one.
     */
    private static function _bearerCredential()
    {
        $keys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION'
        ];
        foreach ($keys as $key) {
            $header = trim(self::_serverVar($key));
            if (stripos($header, 'bearer') !== 0) {
                continue;
            }
            $rest = substr($header, 6);
            // The scheme must be followed by whitespace or by nothing at all.
            // 'Bearertoken' is not a Bearer credential and must fall through
            // to the other schemes; a bare 'Bearer' with no value IS one --
            // it is what a client that built the header from an unset config
            // value sends, and answering 401 tells them that, where falling
            // through answers 403 about a server token they never heard of.
            if ('' !== $rest && !preg_match('/^\s/', $rest)) {
                continue;
            }
            // Returned as sent. An empty value is still an ATTEMPT -- ''
            // rather than null -- so the caller refuses it instead of
            // falling through to another scheme.
            return trim($rest);
        }
        return null;
    }
    /**
     * Test Bearer authentication.
     *
     * The ONLY credential accepted here is an APIToken (ADR 0027): a 512-bit
     * CSPRNG secret, stored hashed, shown once, individually revocable. It
     * stands on its own, so a Bearer request does NOT additionally need the
     * server-wide fog-api-token. HTTP basic auth still does: its credential
     * is a human-chosen password, and that server-wide gate is the only
     * thing keeping every FOG password from being a standalone API
     * credential. The asymmetry is deliberate, and it is also forced -- one
     * Authorization header carries one credential, so the legacy two-header
     * pair has no Bearer spelling.
     *
     * users.uAPIToken is NOT accepted here, in any encoding. #1324 shipped
     * an interim shape that did accept it; that is withdrawn deliberately
     * rather than by oversight. It stays plaintext in the database and
     * visible in the UI, which is exactly what a Bearer credential must not
     * be -- so the two are separate credentials with separate properties,
     * not two spellings of one. uAPIToken keeps working unchanged as
     * fog-user-token beside fog-api-token, so nothing has to migrate.
     *
     * Once a Bearer credential is PRESENTED it decides the request. Falling
     * through to the other schemes would let a bad Bearer plus good legacy
     * headers still authenticate, which no real client does and which makes
     * a failure impossible to reason about from the status code.
     *
     * @return bool Whether the request authenticated. False means no Bearer
     *              credential was offered -- a presented one that failed
     *              does not return at all.
     */
    private static function _testBearer()
    {
        $token = self::_bearerCredential();
        if (null === $token) {
            return false;
        }
        $user = APIToken::resolve($token);
        if (null !== $user) {
            // Bind the token's owner as the acting user so role-based
            // authorization applies, exactly as the fog-user-token path does.
            self::$FOGUser = $user;
            return true;
        }
        // Presented and refused. Recorded for the same reason the
        // fog-user-token rejection is, and the presented token is NOT
        // written down -- a rejected credential is still a credential,
        // which is the mistake #1261/#1262 fixed in the SQL fault log.
        //
        // No subject: a refused token resolved to no owner, by definition of
        // having been refused. Naming the account whose DISABLED token was
        // tried would be useful, but it would mean a second lookup path that
        // reports why a credential failed, and that is an oracle. "A bearer
        // token was refused" is the honest fact.
        Audit::record(
            [
                'type' => Audit::TOKEN_REJECTED,
                'outcome' => Audit::DENIED,
                'subjectType' => 'user',
                'authSource' => 'bearer',
                'renderable' => 1
            ]
        );
        self::sendResponse(
            HTTPResponseCodes::HTTP_UNAUTHORIZED
        );
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
            // Bind the token's owner as the acting user so role-based
            // authorization applies to token-authenticated requests.
            self::$FOGUser = $pwtoken;
            return;
        }
        // A user token was PRESENTED and did not work. Falling through to
        // basic auth is correct -- a client may send neither, or both --
        // but the rejection is a fact, and until now it left no trace at
        // all. Only recorded when something was actually presented: an
        // empty header is not an attempt.
        if ('' !== $usertoken) {
            Audit::record(
                [
                    'type' => Audit::TOKEN_REJECTED,
                    'outcome' => Audit::DENIED,
                    'subjectType' => 'user',
                    // The token is a credential and is not written down. If
                    // it resolved to an account at all, that account's name
                    // is the fact worth keeping; if it did not, there is
                    // nothing to say beyond "a token was refused".
                    'subjectID' => (int)$pwtoken->get('id'),
                    'subjectLabel' => (string)$pwtoken->get('name'),
                    'authSource' => 'user-token',
                    'renderable' => 1
                ]
            );
        }
        list($authUser, $authPass) = self::_basicAuthCredentials();
        $auth = self::$FOGUser->passwordValidate(
            $authUser,
            $authPass
        );
        if (!$auth) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
        // passwordValidate() proves the credential; it says nothing about
        // whether this account may use the API. The token branch above
        // tests uAllowAPI, so without the same test here turning "Allow
        // API" off left basic auth as an unaffected way in.
        //
        // Reloading also gives the acting user a fully populated object,
        // matching what the token branch binds -- passwordValidate() only
        // fills in id, name and type on the object it was called against.
        $apiUser = self::getClass('User', (int)self::$FOGUser->get('id'));
        if (!$apiUser->isValid() || !$apiUser->get('api')) {
            // A correct password for an account that may not use the API.
            // Distinct from a bad credential and worth telling apart: this
            // one says somebody's real credential is being used somewhere
            // it is not meant to be.
            Audit::record(
                [
                    'type' => Audit::API_DENIED,
                    'outcome' => Audit::DENIED,
                    'subjectType' => 'user',
                    'subjectID' => (int)$apiUser->get('id'),
                    'subjectLabel' => (string)$authUser,
                    'createdBy' => (string)$authUser,
                    'authSource' => 'basic',
                    'renderable' => 1
                ]
            );
            self::sendResponse(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
        self::$FOGUser = $apiUser;
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
        // The router is the HTTP boundary and is entitled to end the response:
        // breakHead() sets the status and exits. That is still exactly what
        // happens for every caller that reaches here normally.
        //
        // It is the wrong answer below that boundary, and this is called from
        // well below it -- eleven classes under lib/service back the CLI
        // daemons, where the exit ends the daemon process after writing an
        // HTTP status line to stdout, and the client endpoints, where a
        // non-2xx is invisible to the client and reads as a transport failure
        // rather than an answer. multicasttask.class.php already carries a
        // hand-rolled guard against exactly this (see #907); it had to
        // duplicate indiv()'s own validity test to avoid calling it.
        //
        // So when a getX() result wrapper is on the stack, report the failure
        // as an exception and let the caller decide. Those callers can catch
        // and log; they cannot un-exit. See ADR 0011.
        //
        // The guard lives here rather than in the individual catch blocks
        // because this is the single choke point -- 44 call sites in this
        // class funnel through it, and paths like getsearchbody() end the
        // response without ever reaching listem()'s own catch.
        if (self::$_rethrowDepth > 0) {
            throw new \RuntimeException(
                is_string($msg) && $msg !== '' ? $msg : (string)$code,
                (int)$code
            );
        }
        // The gate could only say the action was ALLOWED. Whether it then
        // worked is known here, and "allowed, and it failed" is a different
        // fact from "allowed" -- particularly to somebody reading the trail
        // to find out why a change did not stick (ADR 0021 merge 4).
        //
        // 401 and 403 are excluded because they are already recorded, as
        // denials, by the gate and by the token tests. Re-marking them would
        // overwrite the row that says somebody was turned away with one that
        // says something went wrong. markOutcome() refuses that anyway; this
        // is the same rule stated where a reader will look for it.
        if ((int)$code >= 400
            && !in_array(
                (int)$code,
                [
                    HTTPResponseCodes::HTTP_UNAUTHORIZED,
                    HTTPResponseCodes::HTTP_FORBIDDEN
                ],
                true
            )
        ) {
            // The code and the message are both in hand here and both used
            // to be dropped, so every `failed` row in the trail read
            // "something went wrong" with no way to find out what. A delete
            // of an id that is already gone and a delete that hit a
            // constraint are the same row without this.
            $why = 'HTTP ' . (int)$code;
            if (is_string($msg) && '' !== $msg) {
                $why .= ': ' . $msg;
            }
            Audit::markOutcome(Audit::FAILED, $why);
        }
        HTTPResponseCodes::breakHead(
            $code,
            $msg
        );
    }
    /**
     * Ends the response for an exception caught in a route handler.
     *
     * DEC-5. Every one of these catches used to answer HTTP 406 and discard
     * the code the inner failure chose. Over plain HTTP that was invisible:
     * the inner sendResponse() exits inside breakHead(), so the wire status
     * was whatever that inner call picked and the catch never ran. Under a
     * getX()/asValue() result wrapper it is not -- there sendResponse()
     * throws rather than exiting (see above), the catch below it does run,
     * and a caller reading through the wrapper saw 406 for a refusal the
     * source raised as 400.
     *
     * So re-raise the inner code when it is one, and keep 406 for everything
     * else. "Is one" means 400-599 and nothing wider: a PDOException carries
     * a SQLSTATE ('42S22'), which casts to a plausible-looking 42, and a
     * hand-thrown Exception defaults to 0.
     *
     * @param \Exception $e The caught exception.
     *
     * @return void
     */
    private static function _sendCaught(\Exception $e)
    {
        $code = (int)$e->getCode();
        if ($code < 400 || $code > 599) {
            $code = HTTPResponseCodes::HTTP_NOT_ACCEPTABLE;
        }
        self::sendResponse(
            $code,
            $e->getMessage()
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
            // The paging bounds ride along here as well as in the OpenAPI
            // document, because this is the endpoint a client can afford to
            // call. The document is several hundred kilobytes and a client
            // that wants to know how large a page it may ask for should not
            // have to fetch all of it. Same source either way, so the two
            // cannot disagree.
            'paging' => OpenAPI::pagingLimits(),
            'msg' => _('success')
        ];
    }
    /**
     * Serves an OpenAPI description of this server's API.
     *
     * Unauthenticated, alongside status/info, so a client can discover what
     * it is talking to before it has credentials. It exposes only the shape
     * of the API -- class names, field names and types -- all of which are
     * already public in the source, and no data.
     *
     * Built per request rather than read from a shipped file, because
     * $validClasses and the sensitive-field lists are both mutated at
     * runtime by plugin hooks; a static file would describe the classes FOG
     * ships with rather than the ones this server actually exposes. See
     * OpenAPI for what the generator can and cannot derive.
     *
     * @return void
     */
    public static function openapi()
    {
        self::$data = OpenAPI::document();
    }
    /**
     * Streams a full SQL backup of the FOG database.
     *
     * Token-authenticated, headless equivalent of the management
     * "Export Database" button (management/export.php?type=sql), which
     * requires a logged-in session and CSRF token and so cannot be used
     * by scripts. This endpoint relies only on the standard API auth
     * already enforced in the constructor (a Bearer token, or fog-api-token
     * plus an api-enabled fog-user-token, or HTTP basic auth) and reuses
     * Schema::exportdb() so the dump matches the web UI byte-for-byte.
     *
     * The dump is streamed as an attachment; we exit afterward to keep
     * printer() from appending JSON to the SQL body.
     *
     * @return void
     */
    public static function export()
    {
        /**
         * Belt-and-suspenders: this streams the entire database, so never
         * serve it to an unauthenticated caller even if the routing
         * whitelist is ever mis-scoped again. A valid session bypasses
         * the token checks exactly as the constructor does.
         */
        if (!self::$FOGUser->isValid()) {
            self::_testToken();
            self::_testAuth();
        }
        $backup_name = sprintf(
            'fog_backup_%s.sql',
            self::formatTime('now', 'Ymd_His')
        );
        self::getClass('Schema')->exportdb($backup_name);
        exit;
    }
    /**
     * Flushes the per-process settings cache and raises the cross-process
     * flush signal. Auth is enforced by the constructor.
     *
     * @return void
     */
    public static function settingsCacheFlush()
    {
        FOGBase::clearSettingsCache();
        self::$data = ['status' => 'flushed'];
    }
    /**
     * Read-only view of the settings cache (counters + cached key names and
     * ages, never values). Auth is enforced by the constructor.
     *
     * @return void
     */
    public static function settingsCacheView()
    {
        self::$data = FOGBase::getSettingsCacheStats();
    }
    /**
     * Reloads every global setting into the cache in a single query and
     * raises the cross-process flush signal. Auth is enforced by the
     * constructor.
     *
     * @return void
     */
    public static function settingsCacheRefresh()
    {
        self::$data = [
            'status' => 'refreshed',
            'count' => FOGBase::refreshSettingsCache(),
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
                // DataTables POSTs carry pagination in the request body, but a
                // plain GET (?length=3&start=0) carries it in the query string,
                // which FOG's rewrite drops from $_GET. Fold ?length/?start in
                // so GET clients get real pagination too. Only fill fields the
                // body didn't already set.
                $qsLen = self::queryParam('length');
                if ($qsLen !== null && $qsLen !== ''
                    && !isset($pass_vars['length'])
                ) {
                    $pass_vars['length'] = (int)$qsLen;
                    $qsStart = self::queryParam('start');
                    // Default start=0 so complex()'s LIMIT is well-formed; a
                    // length without a start would otherwise LIMIT start,0 and
                    // return zero rows.
                    $pass_vars['start'] = ($qsStart !== null && $qsStart !== '')
                        ? (int)$qsStart
                        : 0;
                }
            }
            self::parseExpand();
            if (self::$getterDepth === 0
                && self::expandRequested()
                && isset($pass_vars)
            ) {
                // Expansion materializes a full object per row; bound memory
                // by clamping an unbounded/oversized page to EXPAND_MAX_ITEMS.
                $len = isset($pass_vars['length'])
                    ? (int)$pass_vars['length']
                    : 0;
                if ($len <= 0 || $len > self::EXPAND_MAX_ITEMS) {
                    $pass_vars['length'] = self::EXPAND_MAX_ITEMS;
                    if (!isset($pass_vars['start'])) {
                        $pass_vars['start'] = 0;
                    }
                }
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }
            $whereItems = self::handleWhereItems($whereItems, $class);
            if ('snapintask' === strtolower($class)
                && isset($whereItems['jobID'])
            ) {
                $jobIDs = self::positiveIntIds($whereItems['jobID']);
                if (count($jobIDs) > 0) {
                    $whereItems['jobID'] = $jobIDs;
                } else {
                    // Force an empty result set for invalid job filters.
                    $whereItems['jobID'] = [-1];
                }
            }
            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($class);
            }

            self::$data = [];
            // Fresh per grid -- see $relCache and rel().
            self::$relCache = [];
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
                    break;
            }
            self::$HookManager->processEvent(
                'API_REMOVE_COLUMNS',
                ['tmpcolumns' => &$tmpcolumns]
            );

            // The column table itself: see _gridColumns(). $tableID comes
            // back by reference because a class need not have an id column.
            $columns = self::_gridColumns($classname, $tmpcolumns, $tableID);
            self::$HookManager->processEvent(
                'CUSTOMIZE_DT_COLUMNS',
                [
                    'columns' => &$columns,
                    'classman' => &$classman,
                    'classname' => &$classname
                ]
            );

            // A field the emitter strips must not be searchable either.
            //
            // Marked unsearchable rather than dropped, because these columns
            // are load bearing for callers that are not the API. listem() is
            // shared with the web tier: product_keys.report.php calls
            // listem('host') and has nothing to report without productKey.
            // Removing the column would break the report to close a search;
            // stripSensitive() at the emitter is what keeps it off the wire.
            //
            // Searching is the part with no legitimate use. The value never
            // comes back, so a match can only ever be read as an answer about
            // a value the caller is not allowed to see -- and DataTables
            // filters are substring LIKEs, so the answer is repeatable one
            // character at a time. host.sec_tok and user.token are stored in
            // plaintext and matched exactly at authentication, which is what
            // makes this worth closing rather than noting.
            //
            // Applied after CUSTOMIZE_DT_COLUMNS so a column a plugin adds
            // for its own declared secret is covered too, and keyed on 'dt'
            // because that is the name a DataTables request asks for.
            $unsearchable = self::unfilterableFields($classname);
            if (count($unsearchable)) {
                foreach ($columns as $ci => $col) {
                    if (in_array($col['dt'] ?? '', $unsearchable, true)) {
                        $columns[$ci]['nosearch'] = true;
                    }
                }
            }

            // The site boundary goes into the QUERY, not onto the rows.
            //
            // _applySiteScope() below still runs and still filters, but it
            // cannot be the enforcement on a paginated list: complex()
            // applies the LIMIT, so the database chooses the page before any
            // row filtering happens. A user scoped to one site of a 90-host
            // server therefore got an EMPTY first page -- their host was at
            // offset 75 -- with recordsTotal 0 and nextUrl null, so the grid
            // said "no records" while the rows existed two pages further on.
            // The counts have the same problem in the other direction:
            // computed by SQL over the unscoped set, they described objects
            // the user may not see.
            //
            // $whereAll is the parameter for this and already existed: it is
            // ANDed into the row query and the filter count, and appended to
            // the total count, which is exactly the three places the boundary
            // has to hold. Passed as a subquery rather than an id list so it
            // costs one expression whatever the fleet size.
            //
            // Qualified with the table name because these queries carry joins
            // and a bare id column can be ambiguous.
            $scopeWhere = Authorization::scopedObjectWhere(
                $classname,
                sprintf('`%s`.`%s`', $table, $tableID)
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
                $scopeWhere,
                $orderby,
                self::$countOnly
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
            self::_applySiteScope($classname);
            self::_applySettingValueScope(
                $classname,
                isset($pass_vars) ? $pass_vars : [],
                is_array($whereItems) ? $whereItems : []
            );
            self::$data['_lang'] = $classname;
            if (self::$getterDepth === 0
                && self::expandRequested()
                && isset(self::$data['data'])
                && is_array(self::$data['data'])
            ) {
                // Serializing a row calls getter()/expandRelations()/plugin
                // hooks, which reach helpers like getIds() that overwrite the
                // shared static self::$data (getIds even leaves it an empty
                // string). Snapshot the payload and enrich a local copy so the
                // loop is immune to that clobbering, then restore it.
                $listData = self::$data;
                $rows = $listData['data'];
                // One query for the page's objects instead of one per row.
                // Same treatment the grid columns got for GH-707 -- rel() and
                // primeRel() exist for exactly this and the expand branch
                // never used them, so ?expand cost ~20 statements per row
                // where the plain path is flat at 4 for the whole page.
                //
                // rel() falls back to a load for anything the prime missed,
                // and caches an empty object carrying the id for an id with
                // no record -- which is the state a failed load() leaves
                // behind, so the isValid() test below behaves as it did.
                self::primeRel($class, array_column($rows, 'id'));
                foreach ($rows as $i => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $rid = isset($row['id']) ? (int)$row['id'] : 0;
                    if ($rid < 1) {
                        continue;
                    }
                    $robj = self::rel($class, $rid);
                    if (!$robj->isValid()) {
                        continue;
                    }
                    // Inline ONLY the requested relations onto the flat grid
                    // row. Merging the full getter() output here would drag in
                    // every relation the entity's base serialization embeds
                    // (for Host: inventory/image/hostscreen/hostalo/macs),
                    // which defeats the selective contract of ?expand=token.
                    $exp = self::expandRelations($classname, $robj, $row);
                    $exp = self::enrichPluginItems($classname, $robj, $exp);
                    // No strip here, and none anywhere else in listem().
                    // listem() is shared with the web tier -- the LDAP login
                    // path needs bindPwd to bind at all, the Product Keys
                    // report needs productKey to report anything -- so it
                    // hands the row back whole and the emitter removes
                    // secrets on the way out, expanded relations included.
                    // That was already how a PLAIN list behaved; stripping
                    // here as well meant an ?expand= caller inside the server
                    // got a redacted row where a plain one did not.
                    $rows[$i] = $exp;
                }
                $listData['data'] = $rows;
                self::$data = $listData;
            }
            self::paginate(isset($pass_vars) ? $pass_vars : []);
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Builds the DataTables column table for one class.
     *
     * This is the bulk of listem() by line count and none of it is policy: it
     * maps each of the manager's columns onto one or more output columns, and
     * then adds the per-class extras that have no database field behind them.
     * Every access-control decision listem() makes happens either before this
     * runs (arrayRemove, API_REMOVE_COLUMNS) or after it (CUSTOMIZE_DT_COLUMNS,
     * the nosearch pass, the row filters, the emitter), and all of those stay
     * where they are -- see docs/route-listem-access-control-map.md, which is
     * written against those positions.
     *
     * Moved out verbatim. The column table it produces is pinned line for line
     * by tests/route-column-contract.test.php, including each formatter's
     * `use (...)` list and what each primer actually primes, so "verbatim" is
     * checked rather than asserted.
     *
     * @param string $classname  The lowercased class being listed.
     * @param array  $tmpcolumns The manager's columns, already filtered by
     *                           arrayRemove() and API_REMOVE_COLUMNS.
     * @param mixed  $tableID    Out. The database column holding the primary
     *                           key, which listem() needs for its DT_RowId and
     *                           for the DataTables request. Set only if the
     *                           class still has an `id` column -- a plugin can
     *                           remove it on API_REMOVE_COLUMNS -- so it is
     *                           passed by reference rather than returned, and
     *                           left alone when there is nothing to say.
     *
     * @return array The column definitions.
     */
    private static function _gridColumns($classname, $tmpcolumns, &$tableID)
    {
        $columns = [];
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
                        // `Name - (id)`, via the shared sink. This grid used to
                        // emit `(id) - Name` while every association tab emitted
                        // the other order; entityLink() settles it on the name
                        // first, so the two agree. The pxemenuoptions -> ipxe
                        // remap stays here: it is a quirk of how this class is
                        // named versus its node, not something a link formatter
                        // should know about.
                        'formatter' => function ($d, $row) use ($classname, $tmpcolumns) {
                            return self::entityLink(
                                ($classname == 'pxemenuoptions' ? 'ipxe' : $classname),
                                $row[$tmpcolumns['id']],
                                $d
                            );
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
                    // The two halves of "when was this host last seen" (schema
                    // step 353). They are plain datetimes here on purpose: no
                    // combined column is emitted, because a DataTables column
                    // has exactly one `db` binding, so a "last seen" cell
                    // formatted from both would sort and search on whichever
                    // one it was bound to and silently disagree with what it
                    // displays. Two honest columns beat one that lies when you
                    // click its header.
                case 'lastping':
                case 'lastcheckin':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common,
                        'formatter' => function ($d, $row) {
                            if (self::validDate($d)) {
                                return self::niceDate($d)->format('Y-m-d H:i:s');
                            }
                            return self::EMPTY_CELL;
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
                            // hostPingCode is FOUR states, not two, and the
                            // third is the one this column used to get
                            // wrong:
                            //
                            //   NULL/''       never pinged
                            //   0             up, and the port answered
                            //   ECONNREFUSED  UP -- the host's own kernel
                            //                 sent a RST; nothing is
                            //                 listening on the port
                            //   anything else not reachable
                            //
                            // Ping::isAlive() owns which errnos mean the
                            // host answered, shared with the service that
                            // stamps hostLastPing, so the badge and the
                            // timestamp cannot disagree.
                            //
                            // Refused gets its own colour rather than
                            // borrowing success: the machine is up, which
                            // is what was asked, but "the port is shut" is
                            // the fact behind every "why is my Linux host
                            // green now" question and is worth reading off
                            // the grid.
                            if ($d === null || $d === '') {
                                return '<span class="badge bg-secondary">'
                                    . _('Not pinged')
                                    . '</span>';
                            }
                            if ((int)$d === 0) {
                                // WHICH probe answered, from the sibling
                                // column -- an echo reply and a completed
                                // connect are both errno 0 and mean
                                // different things to whoever is asking
                                // "is the service running". Rows written
                                // before schema 356 carry no method and
                                // fall back to a bare "Online", which is
                                // exactly as much as is known about them.
                                $how = $row['hostPingMethod'] ?? '';
                                // The protocol names are not translated --
                                // they are protocol names. Only the word
                                // around them is, which also keeps an HTML
                                // entity out of the msgid.
                                if (Ping::METHOD_ICMP === $how) {
                                    return '<span class="badge bg-success">'
                                        . sprintf(
                                            '%s &middot; ICMP',
                                            _('Online')
                                        )
                                        . '</span>';
                                }
                                if (Ping::METHOD_TCP === $how) {
                                    return '<span class="badge bg-success">'
                                        . sprintf(
                                            '%s &middot; TCP',
                                            _('Online')
                                        )
                                        . '</span>';
                                }
                                return '<span class="badge bg-success">'
                                    . _('Online')
                                    . '</span>';
                            }
                            if (Ping::isAlive($d)) {
                                return '<span class="badge bg-info">'
                                    . _('Up, port closed')
                                    . '</span>';
                            }
                            return '<span class="badge bg-secondary">'
                                . _(socket_strerror((int)$d))
                                . '</span>';
                        }
                    ];
                    break;
                case 'groupID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'groupLink',
                        'group',
                        function ($d, $row) use ($tmpcolumns) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=group&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::rel('group', $d)->get('name')
                                . '</a>';
                        }
                    );
                    break;
                case 'hostID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'hostLink',
                        'host',
                        function ($d, $row) use ($classname) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            // ADR 0020 phase 4: the stored name answers when
                            // the host is gone, so a deleted host's rows do
                            // not all render "(41) - " forever.
                            return '<a href="../management/index.php?node=host&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - '
                                . self::_hostLabel($d, $row, $classname)
                                . '</a>';
                        },
                        self::_hostNameOrder($classname)
                    );
                    break;
                case 'image':
                case 'imageID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'imageLink',
                        'Image',
                        function ($d, $row) use ($classname) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            // imaginglog stored the image NAME in this
                            // column and needed a lookup by name; it is gone
                            // (ADR 0022), and every remaining class stores an
                            // id. taskLog's own image name is a separate key
                            // and does not come through here.
                            $image = self::rel('Image', $d);
                            $imageName = $image->get('name');
                            if ($image->isValid()) {
                                return '<a href="../management/index.php?node=image&'
                                    . 'sub=edit&id='
                                    . $d
                                    . '">'
                                    . '(' . $d . ') - ' . $imageName
                                    . '</a>';
                            }
                            return $imageName;
                        }
                    );
                    break;
                case 'snapinID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'snapinLink',
                        'Snapin',
                        function ($d, $row) use ($tmpcolumns) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=snapin&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::rel('Snapin', $d)->get('name')
                                . '</a>';
                        }
                    );
                    break;
                case 'mem':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common,
                        'formatter' => function ($d, $row) {
                            if (!$d) {
                                return self::EMPTY_CELL;
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
                    $columns[] = self::relColumn(
                        $real,
                        'storagegroupLink',
                        'storagegroup',
                        function ($d, $row) use ($tmpcolumns) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=storagegroup&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::rel('storagegroup', $d)->get('name')
                                . '</a>';
                        }
                    );
                    break;
                case 'storagenodeID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'storagenodeLink',
                        'storagenode',
                        function ($d, $row) use ($tmpcolumns) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=storagenode&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::rel('storagenode', $d)->get('name')
                                . '</a>';
                        }
                    );
                    break;
                case 'userID':
                    $columns[] = [
                        'db' => $real,
                        'dt' => $common
                    ];
                    $columns[] = self::relColumn(
                        $real,
                        'userLink',
                        'user',
                        function ($d, $row) use ($tmpcolumns) {
                            if (!$d) {
                                return self::EMPTY_CELL;
                            }
                            return '<a href="../management/index.php?node=user&'
                                . 'sub=edit&id='
                                . $d
                                . '">'
                                . '(' . $d . ') - ' . self::rel('user', $d)->get('name')
                                . '</a>';
                        }
                    );
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
                // Vendor name for the primary MAC; rides along in the JSON
                // and is rendered as a tooltip icon client-side. Not a
                // visible column and never reaches the CSV export path.
                $columns[] = [
                    'db' => 'hmMAC',
                    'dt' => 'primac_vendor',
                    'prime' => function ($rows) {
                        MACAddress::primeVendors(
                            array_column((array) $rows, 'hmMAC')
                        );
                    },
                    'formatter' => function ($d, $row) {
                        return MACAddress::getVendor($d);
                    }
                ];
                break;
            case 'macaddressassociation':
                // Vendor name for each MAC row (additional + pending MACs).
                $columns[] = [
                    'db' => 'hmMAC',
                    'dt' => 'mac_vendor',
                    'prime' => function ($rows) {
                        MACAddress::primeVendors(
                            array_column((array) $rows, 'hmMAC')
                        );
                    },
                    'formatter' => function ($d, $row) {
                        return MACAddress::getVendor($d);
                    }
                ];
                break;
            case 'group':
                $columns[] = [
                    'db' => 'gmMembers',
                    'dt' => 'members',
                    'removeFromQuery' => true
                ];
                break;
            case 'site':
                // The four member counts Site::$sqlQueryStr computes.
                // removeFromQuery because they are aggregates of the
                // JOINs, not columns of `sites` -- selecting them again
                // by name would be an unknown-column error.
                //
                // The dt names are the plugin's, unchanged: they are
                // the DataTables field names fog.site.list.js binds to,
                // and a tidier spelling here would leave every column
                // rendering empty with nothing to say why.
                $columns[] = [
                    'db' => 'shmMembers',
                    'dt' => 'hostcount',
                    'removeFromQuery' => true
                ];
                $columns[] = [
                    'db' => 'sumMembers',
                    'dt' => 'usercount',
                    'removeFromQuery' => true
                ];
                $columns[] = [
                    'db' => 'sgmMembers',
                    'dt' => 'groupcount',
                    'removeFromQuery' => true
                ];
                $columns[] = [
                    'db' => 'sugmMembers',
                    'dt' => 'usergroupcount',
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
                            return self::EMPTY_CELL;
                        }
                        // Aisle 019: this column is an intentional anchor, so
                        // the Inventory Report cannot neutralise it with
                        // DataTables render.text() the way it now does for the
                        // other columns -- escape the host name here instead.
                        // Mirrors the 'mainlink' formatter above.
                        return '<a href="../management/index.php?node=host&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . '(' . $d . ') - ' . \Initiator::e($row['hostName'])
                            . '</a>';
                    }
                ];
                break;
            case 'scheduledtask':
                $columns[] = [
                    'db' => 'stGroupHostID',
                    'dt' => 'hostLink',
                    'prime' => function ($rows) {
                        self::primeRel(
                            'Group',
                            array_column((array) $rows, 'stGroupHostID')
                        );
                        self::primeRel(
                            'Host',
                            array_column((array) $rows, 'stGroupHostID')
                        );
                    },
                    'formatter' => function ($d, $row) {
                        $linkName = $row['stIsGroup'] ? 'group' : 'host';
                        $capName = $row['stIsGroup'] ? 'Group' : 'Host';
                        $itemName = self::rel($capName, $d)->get('name');
                        return sprintf(
                            '<a href="../management/index.php?node=%s&sub=edit&id=%s">%s: (%s) - %s</a>',
                            $linkName,
                            $d,
                            _($capName),
                            $d,
                            $itemName
                        );
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
                $columns[] = self::relColumn(
                    'stTaskTypeID',
                    'taskTypeName',
                    'TaskType',
                    function ($d, $row) {
                        return self::rel('TaskType', $d)->get('name');
                    }
                );
                $columns[] = [
                    'db' => 'stActive',
                    'dt' => 'isActive',
                    'formatter' => function ($d, $row) {
                        return $d <= 0 ? _('No') : _('Yes');
                    }
                ];
                break;
            case 'filedeletequeue':
                $columns[] = self::relColumn(
                    'fdqState',
                    'taskstateicon',
                    'taskstate',
                    function ($d, $row) {
                        return self::rel('taskstate', $d)->get('icon');
                    }
                );
                $columns[] = self::relColumn(
                    'fdqState',
                    'taskstatename',
                    'taskstate',
                    function ($d, $row) {
                        return self::rel('taskstate', $d)->get('name');
                    }
                );
                break;
            case 'snapintask':
                // Every host column below is reached through the task's
                // job. When that job is not there -- deleted, or a stJobID
                // of 0 for a task that never had one -- get('host')
                // returns a STRING, and calling get() on it is a fatal,
                // not an empty cell. One such row took the entire snapin
                // task list down with "Call to a member function get() on
                // string". Resolve once, defensively, and let a row that
                // cannot name its host render blank instead of killing the
                // page for every other row.
                //
                // Schema step 318 sweeps the rows themselves; this is what
                // stops the next one being fatal.
                //
                // Refs https://github.com/FOGProject/fogproject/issues/895
                $snapinTaskHost = function ($jobID) {
                    $host = self::rel('snapinjob', $jobID)
                        ->get('host');
                    return is_object($host) && $host->isValid()
                        ? $host
                        : null;
                };
                // Primed once for all three stJobID columns below -- they
                // share $snapinTaskHost, so the first primer to run fills
                // the cache the other two read. SnapinJob declares Host as
                // a class relationship, so the job's host is joined in by
                // the same query and costs nothing extra.
                $columns[] = self::relColumn(
                    'stJobID',
                    'hostID',
                    'snapinjob',
                    function ($d, $row) use ($snapinTaskHost) {
                        $host = $snapinTaskHost($d);
                        return $host ? $host->get('id') : '';
                    }
                );
                $columns[] = [
                    'db' => 'stJobID',
                    'dt' => 'hostname',
                    'formatter' => function ($d, $row) use ($snapinTaskHost) {
                        $host = $snapinTaskHost($d);
                        return $host ? $host->get('name') : '';
                    }
                ];
                $columns[] = [
                    'db' => 'stJobID',
                    'dt' => 'hostLink',
                    // Sorted by host name, not by stJobID: the group page's
                    // Snapin History tab groups on this column, so the sort
                    // key decides the order the hosts appear in.
                    'order' => self::_hostNameOrder($classname),
                    'formatter' => function ($d, $row) use ($snapinTaskHost) {
                        $tmphost = $snapinTaskHost($d);
                        if (!$tmphost) {
                            return '';
                        }
                        return '<a href="../management/index.php?node=host&'
                            . 'sub=edit&id='
                            . $tmphost->get('id')
                            . '">'
                            . '(' . $tmphost->get('id') . ') - ' . $tmphost->get('name')
                            . '</a>';
                    }
                ];
                $columns[] = self::relColumn(
                    'stState',
                    'taskstateicon',
                    'taskstate',
                    function ($d, $row) {
                        return self::rel('taskstate', $d)->get('icon');
                    }
                );
                $columns[] = self::relColumn(
                    'stState',
                    'taskstatename',
                    'taskstate',
                    function ($d, $row) {
                        return self::rel('taskstate', $d)->get('name');
                    }
                );
                $columns[] = self::relColumn(
                    'stSnapinID',
                    'snapinID',
                    'Snapin',
                    function ($d, $row) {
                        return self::rel('Snapin', $d)->get('id');
                    }
                );
                $columns[] = self::relColumn(
                    'stSnapinID',
                    'snapinname',
                    'Snapin',
                    function ($d, $row) {
                        return self::rel('Snapin', $d)->get('name');
                    }
                );
                $columns[] = self::relColumn(
                    'stSnapinID',
                    'snapinLink',
                    'Snapin',
                    function ($d, $row) {
                        if (!$d) {
                            return self::EMPTY_CELL;
                        }
                        return '<a href="../management/index.php?node=snapin&'
                            . 'sub=edit&id='
                            . $d
                            . '">'
                            . '(' . $d . ') - ' . self::rel('Snapin', $d)->get('name')
                            . '</a>';
                    }
                );
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
            case 'tasklog':
                // The host and group imaging-history tabs read this class
                // since imagingLog was retired (ADR 0022 decision 3), and a
                // raw taskStateID is not something to put on a page.
                //
                // Resolved per id and memoized, the $storageGroups pattern
                // below: a row's state is one of a handful of values, so the
                // whole grid costs at most that many lookups.
                $taskStates = [];
                $columns[] = [
                    'db' => 'taskStateID',
                    'dt' => 'statename',
                    'formatter' => function ($d) use (&$taskStates) {
                        $id = (int)$d;
                        if (!isset($taskStates[$id])) {
                            $taskStates[$id] = self::getClass('TaskState', $id)
                                ->get('name');
                        }
                        return $taskStates[$id] ?: self::EMPTY_CELL;
                    }
                ];
                // ADR 0023 item 5: the one-line "what happened" the activity
                // viewer shows, in the same output column every source uses.
                $columns[] = [
                    'dt' => 'summary',
                    'formatter' => function ($d, $row) use (
                        &$taskStates,
                        $classname
                    ) {
                        // An error or warning row already IS a sentence
                        // somebody wrote for a person to read (the FOS report
                        // endpoint's message), so it is used as it stands.
                        // Only a state row has to be assembled.
                        $text = isset($row['logText']) ? (string)$row['logText'] : '';
                        if ('' !== $text) {
                            return \Initiator::e($text);
                        }
                        $host = self::_hostLabel(
                            isset($row['logHostID']) ? $row['logHostID'] : 0,
                            $row,
                            $classname
                        );
                        $what = isset($row['logTaskTypeName'])
                            ? (string)$row['logTaskTypeName']
                            : '';
                        $image = isset($row['logImageName'])
                            ? (string)$row['logImageName']
                            : '';
                        $stateId = isset($row['taskStateID'])
                            ? (int)$row['taskStateID']
                            : 0;
                        if (!isset($taskStates[$stateId])) {
                            $taskStates[$stateId] = self::getClass(
                                'TaskState',
                                $stateId
                            )->get('name');
                        }
                        // An unresolvable state renders as a word, not as
                        // nothing. The `statename` column can afford an
                        // EMPTY_CELL because it sits beside other columns;
                        // this one IS the activity viewer's only content
                        // column for this source, so an empty string here is
                        // a row that renders and says nothing at all. Same
                        // stance as _userTrackingAction()'s unknown arm.
                        $state = (string)$taskStates[$stateId];
                        if ('' === $state) {
                            $state = _('Unknown');
                        }
                        if ('' === $what) {
                            // Nothing to say beyond the state. Rows written
                            // before schema 341 backfilled the type name are
                            // the case, and there is no way to recover it.
                            return \Initiator::e($state);
                        }
                        // Spelled out per shape rather than assembled from
                        // fragments: a format string built from a variable
                        // never reaches the catalogue.
                        if ('' !== $image && '' !== $host) {
                            return \Initiator::e(
                                sprintf(
                                    _('%1$s of %2$s on %3$s: %4$s'),
                                    $what,
                                    $image,
                                    $host,
                                    $state
                                )
                            );
                        }
                        if ('' !== $host) {
                            return \Initiator::e(
                                sprintf(_('%1$s on %2$s: %3$s'), $what, $host, $state)
                            );
                        }
                        return \Initiator::e(
                            sprintf(_('%1$s: %2$s'), $what, $state)
                        );
                    }
                ];
                break;
            case 'storagegroup':
                // Each formatter resolves the row's OWN group.
                //
                // They used to share one `new StorageGroup()` threaded
                // between them by reference: the enablednodes formatter did
                // ->set('id', $row['ngID'])->load(), and the masternode
                // formatter then called getMasterStorageNode() on whatever
                // that had left behind.
                //
                // That was not merely an ordering dependency. set()/load() on
                // an object that has already loaded a DIFFERENT group does
                // not clear what it resolved for the previous one, so from
                // the second row onwards both columns answered about the
                // FIRST group. On the lab, three groups whose real members
                // are [1], [3,2] and [] all reported enablednodes [1] and
                // DefaultMember as their master node. The wrong answer is a
                // real node name, so the grid looked right.
                //
                // Memoized per group id -- the $snapinTaskHost pattern a few
                // cases above -- with a fresh object per id, so each group is
                // loaded once and answers about itself. Same load() call as
                // before, deliberately: loadMany() through primeRel() leaves
                // a group in a state getMasterStorageNode() answers
                // differently on, so priming here would trade one wrong
                // answer for another.
                $storageGroups = [];
                $groupFor = function ($id) use (&$storageGroups) {
                    $id = (int) $id;
                    if (!isset($storageGroups[$id])) {
                        $storageGroups[$id] = self::getClass('StorageGroup')
                            ->set('id', $id)
                            ->load();
                    }
                    return $storageGroups[$id];
                };
                $columns[] = [
                    'dt' => 'enablednodes',
                    'formatter' => function ($d, $row) use ($groupFor) {
                        return $groupFor($row['ngID'])->get('enablednodes');
                    }
                ];
                $columns[] = [
                    'dt' => 'masternode',
                    'formatter' => function ($d, $row) use ($groupFor) {
                        try {
                            $sn = $groupFor($row['ngID'])
                                ->getMasterStorageNode();
                        } catch (\Exception $e) {
                            $sn = new StorageNode();
                        }
                        // embed(), not getter(): this is a nested entity on
                        // a storagegroup LIST row, and the emitter can only
                        // strip its FTP credential if it is told what class
                        // it is. This one is the reason the registry exists
                        // -- the node's password used to reach anyone with
                        // storagegroup.view through this column.
                        return self::embed('storagegroup', 'masternode', $sn);
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
                $columns[] = self::relColumn(
                    'ngmID',
                    'clientload',
                    'StorageNode',
                    function ($d, $row) {
                        return self::rel('StorageNode', $d)->getClientLoad();
                    }
                );
                $columns[] = self::relColumn(
                    'ngmID',
                    'location_url',
                    'StorageNode',
                    function ($d, $row) {
                        $node = self::rel('StorageNode', $d);
                        return sprintf(
                            '%s://%s/%s',
                            self::$httpproto,
                            $node->get('ip'),
                            $node->get('webroot')
                        );
                    }
                );
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
            case 'history':
                // ADR 0020 phase 4: the readable line, built at RENDER from
                // the structured columns phase 3 fills, so a row reads in
                // the language of whoever is looking at it rather than the
                // language of whoever triggered it.
                //
                // A separate output column rather than a new formatter on
                // `info`: `info` is `hText` and stays exactly what it was,
                // so anything reading the REST list keeps getting the stored
                // string, the search filter keeps matching real column text,
                // and a legacy row's prose is still reachable beside the
                // summary in the detail pane. "Readers switch" is what this
                // phase is; rewriting a field's meaning underneath its
                // consumers is not.
                //
                // Not orderable and not searchable, because it is not a
                // column: there is nothing in the database to ORDER BY or
                // LIKE against. `info` and `subjectLabel` are both still
                // real columns and both still searchable, which is where a
                // search for a message or an object name lands.
                $columns[] = [
                    'dt' => 'summary',
                    'formatter' => function ($d, $row) {
                        return \Initiator::e(self::_historySummary($row));
                    }
                ];
                break;
            case 'usertracking':
                $columns[] = [
                    'db' => 'utUserName',
                    'dt' => 'username',
                    'formatter' => function ($d, $row) {
                        return \Initiator::e($d);
                    }
                ];
                $columns[] = self::relColumn(
                    'utHostID',
                    'hostname',
                    'Host',
                    function ($d, $row) use ($classname) {
                        return \Initiator::e(
                            self::_hostLabel($d, $row, $classname)
                        );
                    }
                );
                $columns[] = [
                    'db' => 'utAction',
                    'dt' => 'action',
                    'formatter' => function ($d, $row) {
                        // Escaped here rather than in the helper: an
                        // unrecognised code renders as itself, and the
                        // summary below escapes the whole sentence once.
                        return \Initiator::e(self::_userTrackingAction($d));
                    }
                ];
                // ADR 0023 item 5: the one-line "what happened" the activity
                // viewer shows, in the same output column every source uses.
                $columns[] = [
                    'dt' => 'summary',
                    'formatter' => function ($d, $row) use ($classname) {
                        $who = isset($row['utUserName'])
                            ? (string)$row['utUserName']
                            : '';
                        $host = self::_hostLabel(
                            isset($row['utHostID']) ? $row['utHostID'] : 0,
                            $row,
                            $classname
                        );
                        $action = self::_userTrackingAction(
                            isset($row['utAction']) ? $row['utAction'] : ''
                        );
                        // Both halves are optional in practice: a service
                        // start has no person, and a row whose host was
                        // deleted before phase 3 has no name to fall back
                        // on. Each msgid is spelled out because a format
                        // string built from a variable never reaches the
                        // catalogue.
                        if ('' !== $who && '' !== $host) {
                            return \Initiator::e(
                                sprintf(_('%1$s: %2$s on %3$s'), $action, $who, $host)
                            );
                        }
                        if ('' !== $host) {
                            return \Initiator::e(
                                sprintf(_('%1$s on %2$s'), $action, $host)
                            );
                        }
                        if ('' !== $who) {
                            return \Initiator::e(
                                sprintf(_('%1$s: %2$s'), $action, $who)
                            );
                        }
                        return \Initiator::e($action);
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
        return $columns;
    }
    /**
     * Annotates a list envelope with pagination metadata so a client can see
     * that a response is a page and walk the pages without recomputing offsets.
     *
     * Always adds recordsReturned (rows actually in this response). When the
     * response is a page against a non-empty result set -- because the request
     * asked for one with ?length, or because limit() capped an unbounded one --
     * also adds first/prev/next/last page URLs; each is null when it does not
     * apply (e.g. prevUrl on the first page). recordsTotal/recordsFiltered
     * keep their existing full-count meaning — the DataTables UI depends on it.
     *
     * @param array $pass_vars The resolved pagination request (start/length).
     *
     * @return void
     */
    public static function paginate($pass_vars)
    {
        if (!isset(self::$data['data']) || !is_array(self::$data['data'])) {
            return;
        }
        self::$data['recordsReturned'] = count(self::$data['data']);
        $length = isset($pass_vars['length']) ? (int)$pass_vars['length'] : 0;
        $start = isset($pass_vars['start']) ? (int)$pass_vars['start'] : 0;
        $filtered = isset(self::$data['recordsFiltered'])
            ? (int)self::$data['recordsFiltered']
            : 0;
        // An unpaginated caller no longer gets the whole table -- limit() caps
        // it at MAX_ROWS so a million-row log cannot exhaust PHP mid-fetch. It
        // therefore did receive a page, even though it never asked for one, so
        // it needs the URLs to walk the rest; without them the cap is a wall
        // and the rows behind it are unreachable by that client. The cap is the
        // page size it was served at.
        if ($length <= 0 && !empty(self::$data['truncated'])) {
            $length = FOGManagerController::MAX_ROWS;
        }
        if ($length <= 0 || $filtered <= 0) {
            self::$data['firstUrl'] = null;
            self::$data['prevUrl'] = null;
            self::$data['nextUrl'] = null;
            self::$data['lastUrl'] = null;
            return;
        }
        if ($start < 0) {
            $start = 0;
        }
        $lastStart = intdiv(max(0, $filtered - 1), $length) * $length;
        self::$data['firstUrl'] = self::pageUrl(0, $length);
        self::$data['prevUrl'] = $start > 0
            ? self::pageUrl(max(0, $start - $length), $length)
            : null;
        self::$data['nextUrl'] = ($start + $length) < $filtered
            ? self::pageUrl($start + $length, $length)
            : null;
        self::$data['lastUrl'] = self::pageUrl($lastStart, $length);
    }
    /**
     * Builds a request-relative URL for a given pagination window, preserving
     * every other query parameter of the current request.
     *
     * @param int $start  The row offset for the page.
     * @param int $length The page size.
     *
     * @return string
     */
    public static function pageUrl($start, $length)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = $uri;
        $query = '';
        $qpos = strpos($uri, '?');
        if ($qpos !== false) {
            $path = substr($uri, 0, $qpos);
            $query = substr($uri, $qpos + 1);
        }
        $params = [];
        if ($query !== '') {
            parse_str($query, $params);
        }
        $params['start'] = (int)$start;
        $params['length'] = (int)$length;
        return $path . '?' . http_build_query($params);
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
            // GH-707: only recordsFiltered is wanted, so tell listem() to
            // answer with the count queries alone.
            self::$countOnly = true;
            try {
                self::listem($class, $whereItems, $inputoverride, $operator, $orderby);
            } finally {
                self::$countOnly = false;
            }
            self::$data = ['total' => self::$data['recordsFiltered']];
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Returns the count of matching records directly as an int.
     *
     * Wraps the count()/getData() pair callers repeat throughout the
     * codebase: count() stashes ['total' => N] in self::$data, getData()
     * JSON-encodes it, and the caller decodes and plucks ->total.
     *
     * @param string $class         The class to work with.
     * @param mixed  $whereItems    Any special things to search for.
     * @param bool   $inputoverride Override php://input to blank.
     * @param string $operator      The operator for the SQL. AND is default.
     * @param string $orderby       How to order the returned values.
     *
     * @return int
     */
    public static function getCount(
        $class,
        $whereItems = false,
        $inputoverride = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        self::count($class, $whereItems, $inputoverride, $operator, $orderby);
        $result = json_decode(self::getData());
        return (int)($result->total ?? 0);
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
                // Skip entities the acting user may not view; unknown
                // (plugin-added) entries resolve to null = allowed.
                if (!Authorization::can(
                    Authorization::resolveApiPermission('list', $search)
                )
                ) {
                    continue;
                }
                // The search BOX labels a bucket `ipxe` and fills it from
                // pxeMenu, which is what that box has always linked to.
                // The remap stays here rather than moving into
                // _searchRows(), because it is a property of this bucket
                // and not of the class: Route::search('ipxe') asking for
                // ipxeTable rows and being handed pxeMenu ids is exactly
                // the GH-1290 defect, and search() no longer reads these
                // buckets at all.
                $searchfor = $search;
                if ($search === 'ipxe') {
                    $searchfor = 'pxemenuoptions';
                }
                $rows = self::_searchRows($searchfor, $item, $limit);
                // null = the entity has no name to search. Skipped BEFORE
                // the _lang stamp, so it does not leave a heading behind
                // for results it will never contribute.
                if (null === $rows) {
                    continue;
                }
                $data['_lang'][$search] = (
                    $search != 'setting' ?
                    _($search) :
                    _('settings')
                );
                foreach ($rows as $row) {
                    if (!self::$ajax) {
                        $api = stripos($row['name'], '_api');
                        if (false !== $api) {
                            continue;
                        }
                    }
                    $data[$search][] = $row;
                }
                if (array_key_exists($search, $data)) {
                    $data['_results'][$search] = count(isset($data[$search]) ? $data[$search] : []);
                }
                unset($search);
            }
            self::$HookManager->processEvent(
                'API_UNISEARCH_RESULTS',
                ['data' => &$data]
            );
            self::$data = $data;
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * The id/name rows of ONE class whose id or name matches a term.
     *
     * Extracted from unisearch()'s loop body so that search() can use it
     * too. GH-1290: search() used to call unisearch() and then read the
     * bucket keyed by its own class name out of the result, which made two
     * separate defects inevitable.
     *
     *  - unisearch() iterates $searchPages (16 entries), not $validClasses
     *    (51). A class with no bucket produced no ids, listem() matched
     *    nothing, and the route answered 200 with recordsFiltered 0 --
     *    indistinguishable from "no matches exist", for 17 classes.
     *  - unisearch() remaps the MODEL for `ipxe` to `pxemenuoptions` but
     *    keys the bucket `ipxe`, so search('ipxe') fed pxeMenu ids into a
     *    lookup against ipxeTable. Those tables share nothing but an
     *    integer, so it returned real rows that were not the rows asked
     *    for -- wrong data presented as a match, silently, which is worse
     *    than the empty case.
     *
     * Sharing the body rather than giving search() its own query is the
     * point: THE GUARDS ARE IN HERE. A second implementation would need a
     * second copy of the object-scope boundary and of the credential rule
     * below, and the day the two drift nothing fails -- the rows just
     * quietly become visible again.
     *
     * Returns NULL for a class that cannot be searched at all, and an empty
     * array for one that can and matched nothing. Callers need the
     * difference: unisearch() must not stamp a heading for results that
     * can never arrive, and search() must not report "no matches" for a
     * question it never asked.
     *
     * The `_api` filter is deliberately NOT applied here. Its two callers
     * have different policies today -- unisearch() drops those names only
     * when the request is not XHR, search() drops them always -- and
     * folding that difference into a shared helper would change one of
     * them silently.
     *
     * @param string $class The lowercase class to query.
     * @param string $item  The term.
     * @param int    $limit Row cap, 0 for none.
     *
     * @return array|null Rows of ['id' => , 'name' => ], or null if the
     *                    class has no name field to search.
     */
    private static function _searchRows($class, $item, $limit = 0)
    {
        $classVars = self::getClass($class, '', true);
        // An entity with no `name` field has nothing to match on or to
        // label a result with. Not ours alone to enumerate, either:
        // SEARCH_PAGES hands $searchPages to plugins BY REFERENCE and they
        // append to it, so no amount of reading the core list tells you
        // what arrives here. The live example is the ntfy plugin, whose
        // model is id/serverURL/topicEndpoint/credentials -- every
        // unisearch emitted two "Undefined array key: name" warnings,
        // built a SELECT with an empty backtick pair, got false back, and
        // then foreach()ed over the false.
        if (!isset($classVars['databaseFields']['name'])) {
            return null;
        }
        $item = trim($item);
        $like = '%' . $item . '%';
        $idCol = $classVars['databaseFields']['id'];
        $nameCol = $classVars['databaseFields']['name'];

        $j = $w = $g = '';
        $params = ['item1' => $like, 'item2' => $like];
        switch ($class) {
            case 'host':
                $j = "LEFT OUTER JOIN `hostMAC`
                ON `hosts`.`hostID` = `hostMAC`.`hmHostID`";
                $w = " OR `hostMAC`.`hmMAC` LIKE :item3";
                $params['item3'] = $like;
                $g = "GROUP BY `hosts`.`hostName`";
                break;
            case 'setting':
                // The value IS matched -- searching "bzImage" to find
                // FOG_TFTP_PXE_KERNEL is the point of searching settings
                // at all, and a key-only search can never do it.
                //
                // What must not happen is confirming a CREDENTIAL value.
                // globalSettings is also where FOG keeps its passwords,
                // maskSensitiveSetting() strips their value from this same
                // user's API reads, and a hit here would answer the
                // question that masking refuses -- repeatedly, a few
                // characters at a time. So a credential row that matched
                // ONLY on its value is dropped below, after the query.
                //
                // Dropped after rather than excluded in the WHERE on
                // purpose: an SQL-side exclusion needs a second copy of
                // isSensitiveSetting()'s rule (pattern, include list,
                // exempt list) written in a different dialect, and the day
                // the two drift nothing fails -- the values just quietly
                // become findable again. Calling the real predicate keeps
                // one rule in one place.
                $w = " OR `settingValue` LIKE :item3";
                $params['item3'] = $like;
                break;
            case 'storagenode':
                $w = " OR `ngmHostname` LIKE :item3";
                $params['item3'] = $like;
        }

        // Object scope, in the query.
        //
        // Both routes that reach here take an entity permission and no
        // object scope, so without this any authenticated api user could
        // read the id and name of every match on the server. The
        // per-entity Authorization::can() check in unisearch() is about
        // WHICH ENTITIES may be searched, not which objects of them.
        //
        // PARENTHESISED, and that is the whole of the risk here: the match
        // clause is a chain of ORs, so ANDing a boundary onto the end of it
        // binds to the last OR arm only and every other arm keeps matching
        // server-wide. The boundary has to wrap the disjunction, not join
        // it.
        $scopeWhere = self::_requestScopeWhere(
            $class,
            "`{$classVars['databaseTable']}`.`{$idCol}`"
        );
        $sql = "SELECT `{$idCol}`,`{$nameCol}`
            FROM `{$classVars['databaseTable']}`
        {$j}
        WHERE (`{$idCol}` LIKE :item1
        OR `{$nameCol}` LIKE :item2
        {$w})"
        . (null === $scopeWhere ? '' : " AND {$scopeWhere}")
        . "
        {$g}";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $vals = self::$DB->query(
            $sql,
            [],
            $params
        )->fetch(
            \PDO::FETCH_ASSOC,
            'fetch_all'
        )->get();

        $rows = [];
        foreach ((array)$vals as $val) {
            // Skip if the fields don't exist
            if (!($val[$idCol] ?? '')) {
                continue;
            }
            if (!($val[$nameCol] ?? '')) {
                continue;
            }
            // A credential setting that matched only on its VALUE is
            // dropped: returning it would confirm a substring of a value
            // maskSensitiveSetting() refuses to show. Matching its key
            // still returns it -- searching "PASSWORD" should find
            // FOG_TFTP_FTP_PASSWORD, that is not a secret.
            //
            // Recomputed here rather than asked of the query, because SQL
            // cannot say which OR arm matched. stripos is the same
            // substring test the bound '%term%' performs. Where the two
            // can disagree -- a term containing % or _, which LIKE treats
            // as a wildcard and stripos does not -- the disagreement drops
            // the row, which is the safe direction.
            if ('setting' === $class) {
                $sid = (string)$val[$idCol];
                $skey = (string)$val[$nameCol];
                $visible = false !== stripos($sid, $item)
                    || false !== stripos($skey, $item);
                if (!$visible && self::isSensitiveSetting($skey)) {
                    continue;
                }
            }
            $rows[] = [
                'id' => $val[$idCol],
                'name' => $val[$nameCol]
            ];
        }

        return $rows;
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
            // GH-1290: searches THIS class, rather than running the
            // universal search and reading a bucket out of its result.
            //
            // The old shape could only ever answer for a class that
            // unisearch() happened to have populated a bucket for -- and
            // unisearch() iterates $searchPages (16), not $validClasses
            // (51), and skips `task` outright. For the other 17 named
            // classes $ids came back empty, listem() matched nothing, and
            // this route answered 200 with recordsFiltered 0. Nothing
            // errored and nothing was logged, so from the caller's side it
            // was indistinguishable from "no matches exist" -- which is
            // how a client ends up shipping code against a route that has
            // never worked. It also fed `ipxe` searches a set of pxeMenu
            // ids to look up in ipxeTable, returning real rows that were
            // not the ones asked for.
            //
            // A null answer means the class has no name field to search
            // on. That is left as an empty result rather than an error:
            // the route is generic over every class, OpenAPI stopped
            // ADVERTISING it for those classes in GH-1285, and turning a
            // documented-as-absent operation into a new error response is
            // a separate decision from making the working ones work.
            $rows = (array)self::_searchRows($classname, $item);
            $ids = [];
            foreach ($rows as $row) {
                // Dropped unconditionally here, unlike unisearch(), which
                // only drops them for a non-XHR request. Preserved as it
                // was rather than unified -- see _searchRows().
                if (false != stripos($row['name'], '_api')) {
                    continue;
                }
                $ids[] = $row['id'];
            }
            // An empty id list is NOT an unbounded query: filter() turns an
            // empty array into `id = ''`, which matches no row. Worth
            // knowing, because the alternative reading of an empty IN list
            // is "return the whole table".
            self::listem($classname, ['id' => $ids]);
            self::$HookManager->processEvent(
                'API_MASSDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'classman' => &$classman
                ]
            );
            self::_applySiteScope($classname);
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
            // Recorded for printer(): a single-entity payload is flat and does
            // not name its own class.
            self::$emitClassname = $classname;
            $class = new $class($id);
            if (!$class->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            self::$data = [];
            // Before getter(), not after: getter() now asks wantsExpand()
            // whether to build storagenode's images/snapinfiles, and reading
            // that before it is parsed answers "no" on every request. Nothing
            // else in getter() consults expansion state, and expandRelations()
            // below is unaffected by the move.
            self::parseExpand();
            self::$data = self::getter(
                $classname,
                $class
            );
            self::$data = self::expandRelations(
                $classname,
                $class,
                self::$data
            );
            self::$data = self::enrichPluginItems(
                $classname,
                $class,
                self::$data
            );
            self::$HookManager->processEvent(
                'API_INDIVDATA_MAPPING',
                [
                    'data' => &self::$data,
                    'classname' => &$classname,
                    'class' => &$class
                ]
            );
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
            $serverOwned = self::serverOwnedFields($classname);
            foreach ($classVars['databaseFields'] as &$key) {
                $key = $class->key($key);
                if ($key == 'id') {
                    unset($key);
                    continue;
                }
                // A field the body did not mention is left exactly as
                // loaded. It used to be re-set to its own current value,
                // which looks like a no-op and is not: set() may
                // transform, and User::set() hashes any non-override
                // write to 'password'. So every PUT to a user re-hashed
                // the stored hash and locked that account out for good.
                // save() writes from $this->data for every databaseField
                // regardless of what was set(), so skipping is otherwise
                // byte-identical.
                if (!isset($vars->$key)) {
                    unset($key);
                    continue;
                }
                if (in_array($key, $serverOwned, true)) {
                    self::_refuseServerOwned($class, $key, $vars->$key);
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
                        $snapins = Route::getIds('snapin', false);
                        $snapinsToRem = array_diff(
                            $snapins,
                            (array)$vars->snapins
                        );
                        $class
                            ->removeSnapin($snapinsToRem)
                            ->addSnapin($vars->snapins);
                    }
                    if (isset($vars->printers)) {
                        $printers = Route::getIds('printer', false);
                        $printersToRem = array_diff(
                            $printers,
                            (array)$vars->printers
                        );
                        $class
                            ->removePrinter($printersToRem)
                            ->addPrinter($vars->printers);
                    }
                    if (isset($vars->modules)) {
                        $modules = Route::getIds('module', false);
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
                    if (isset($vars->imageID)) {
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
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
        $tids = Route::getIds('tasktype', false);
        $task = json_decode(
            file_get_contents('php://input')
        );
        Route::indiv('tasktype', $task->taskTypeID);
        $TaskType = json_decode(Route::getData());
        try {
            $deploySnapins = false;
            $snapinAbortOnFailure = false;
            if (isset($task->deploySnapins)) {
                $deploySnapins = $task->deploySnapins;
                if ($deploySnapins === true) {
                    $deploySnapins = -1;
                } elseif (
                    !is_numeric($deploySnapins) || (
                        $deploySnapins < 0 && $deploySnapins != -1
                    )
                ) {
                    $deploySnapins = false;
                }
            }
            if (isset($task->snapinAbortOnFailure)) {
                $snapinAbortOnFailure = (bool)$task->snapinAbortOnFailure;
            }
            $class->createImagePackage(
                $TaskType,
                ($task->taskName ?? ''),
                ($task->shutdown ?? false),
                ($task->debug ?? false),
                $deploySnapins,
                $class instanceof Group,
                (self::_basicAuthCredentials()[0] ?: 'API'),
                $task->passreset ?? '',
                $task->sessionjoin ?? '',
                $task->wol ?? 1,
                false,
                $snapinAbortOnFailure
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
            $serverOwned = self::serverOwnedFields($classname);
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
                // Null passed above rather than the object: there is no
                // stored value to compare a create against, so the test
                // is whether a value was asked for at all.
                if (in_array($key, $serverOwned, true)) {
                    self::_refuseServerOwned(null, $key, $val);
                    unset($key);
                    continue;
                }
                $class->set($key, $val);
                unset($key);
            }
            switch ($classname) {
                case 'host':
                    if (isset($vars->mac)) {
                        $vars->macs = array_merge(
                            (array)$vars->mac,
                            isset($vars->macs) ? (array)$vars->macs : []
                        );
                    }
                    // edit() honours 'primac' but create() did not, and 'primac'
                    // is an additionalFields entry derived from the
                    // MACAddressAssociation join rather than a real column, so
                    // the databaseFields loop above skips it too. The result was
                    // a create that named its primary MAC returning 200 with a
                    // host that had no MAC at all -- which then never matches a
                    // PXE request, so the host silently reads as unregistered.
                    // Prepended rather than appended: 'primac' says explicitly
                    // which MAC is primary, so it must win the array_shift below
                    // over the positional 'mac'/'macs' forms.
                    if (isset($vars->primac)) {
                        $vars->macs = array_merge(
                            (array)$vars->primac,
                            isset($vars->macs) ? (array)$vars->macs : []
                        );
                    }
                    if (isset($vars->macs)) {
                        // Set the primary MAC now (deferred via the 'mac' key
                        // and persisted by save() once the host id exists).
                        // Secondaries are added after save() below, when
                        // $this->get('id') is valid — otherwise they would be
                        // inserted with hmHostID=0 and orphaned.
                        $vars->macs = array_unique((array)$vars->macs);
                        $class->addPriMAC(array_shift($vars->macs));
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
                if ('host' === $classname && !empty($vars->macs)) {
                    $class->addMAC($vars->macs);
                }
            } else {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            self::indiv($classname, $id);
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Creates a Snapin from a multipart/form-data POST that includes
     * the snapin file. The UI's snapin add flow accepts an uploaded
     * file; the generic JSON `create` endpoint does not, so this is
     * the API-side counterpart for that flow.
     *
     * Maps the same exception types as the helper but with REST-
     * conventional codes: validation -> 400, transport/save -> 500.
     * The UI page deliberately preserves the legacy 400 for SSH/SFTP
     * RuntimeExceptions. See
     * docs/adr/0001-api-ui-http-status-divergence.md.
     *
     * @return void
     */
    public static function createSnapinWithFile()
    {
        try {
            if (empty($_FILES['snapinfile']['name'])
                || (int)($_FILES['snapinfile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            ) {
                self::setErrorMessage(
                    _('A file must be uploaded via the "snapinfile" multipart field'),
                    HTTPResponseCodes::HTTP_BAD_REQUEST
                );
                return;
            }
            $Snapin = Snapin::uploadAndCreate($_POST, $_FILES);
            http_response_code(HTTPResponseCodes::HTTP_CREATED);
            self::indiv('Snapin', $Snapin->get('id'));
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                $e->getMessage()
            );
        } catch (\RuntimeException $e) {
            // Covers SnapinSaveException (subclass) and any SSH/SFTP failure.
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }
    /**
     * Uploads one or more snapin files to a Storage Group's Master
     * Node over SFTP. Pure transport — does not create or modify any
     * Snapin DB row. After upload, the FOGSnapinReplicator daemon
     * propagates the files to the group's other nodes on its normal
     * cycle.
     *
     * Closes #823.
     *
     * Multi-file: pass files as snapinfiles[]=@file1 snapinfiles[]=@file2.
     * Collision: existing files with the same basename are overwritten
     * (matches the createwithfile / UI behavior).
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
                    _('Storage Group not found')
                );
                return;
            }
            if (empty($_FILES['snapinfiles']['name'])
                || !is_array($_FILES['snapinfiles']['name'])
            ) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    _('One or more files must be uploaded via the "snapinfiles[]" multipart field')
                );
                return;
            }
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode || !$StorageNode->isValid()) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                    _('Storage Group has no reachable Master Node')
                );
                return;
            }
            Snapin::uploadFilesToNode($StorageNode, $_FILES['snapinfiles']);
            // sendResponse exits, so printer() doesn't run and override
            // the status / emit a null body. 204 = success, no body.
            self::sendResponse(HTTPResponseCodes::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                $e->getMessage()
            );
        } catch (\RuntimeException $e) {
            self::sendResponse(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
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
            // The states a task can be cancelled FROM. This is the same
            // allowlist every "is this task live" test in the tree uses --
            // Host::loadTask() and getActiveTaskCount() both ask for exactly
            // it -- so anything outside it is already finished: Complete,
            // Cancelled, and Failed since schema 339.
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
                    // Read ids, filtered by state, rather than paging through
                    // listem(). Two separate faults lived in that call:
                    //
                    //  - It passed NO state filter, and every row that came
                    //    back was cancelled regardless of state. The listing
                    //    is not state-scoped either: the same host filter
                    //    matches 29 rows for group 2 on the development
                    //    server, none of them active. How much of that a real
                    //    bodyless DELETE actually reached could not be
                    //    reproduced outside a browser session -- listem()
                    //    returns nothing at all to a CLI harness -- so treat
                    //    29 as the exposure, not as a measured outcome.
                    //  - listem() returns a PAGINATED envelope, so the work
                    //    was bounded by a page either way. (Iterating the
                    //    envelope rather than ->data once cancelled nothing at
                    //    all -- that half was already fixed.)
                    //
                    // TaskManager::cancel() is the established bulk path and
                    // re-applies the same state filter itself.
                    $taskIDs = self::getIds(
                        'task',
                        [
                            'hostID' => $class->get('hosts'),
                            'stateID' => $states
                        ]
                    );
                    if (count($taskIDs) < 1) {
                        self::_notCancellable(
                            _('No active tasks to cancel for this group')
                        );
                    }
                    self::getClass('TaskManager')->cancel($taskIDs);
                    break;
                case 'host':
                    if (!$class->isValid()) {
                        self::sendResponse(
                            HTTPResponseCodes::HTTP_NOT_FOUND
                        );
                    }
                    // isValid(), not instanceof. Host::loadTask() sets this
                    // field to `new Task(null)` when the host has nothing
                    // running, and that IS `instanceof Task` -- so the old
                    // test was true unconditionally. Task::cancel() then opens
                    // with getHost()->get('snapinjob'), and getHost() on an
                    // empty task returns the empty STRING, so the call raised
                    // "Call to a member function get() on string": an Error,
                    // not an \Exception, so the catch below never saw it and
                    // an idle host answered a bodyless 500. Reproduced against
                    // a copy of the live database, host 154.
                    $Task = $class->get('task');
                    if (!($Task instanceof Task) || !$Task->isValid()) {
                        self::_notCancellable(
                            _('Host has no active task to cancel')
                        );
                    }
                    $Task->cancel();
                    break;
                case 'scheduledtask':
                    // Has no stateID at all -- it carries isActive -- so it
                    // fell into the default arm below, failed the state test
                    // and returned 200 having done nothing. The endpoint has
                    // therefore never cancelled a scheduled task. Its model
                    // cancel() is a destroy(), which is what the management
                    // page does too, and there is no state to be wrong about.
                    if (!$class->isValid()) {
                        self::sendResponse(
                            HTTPResponseCodes::HTTP_NOT_FOUND
                        );
                    }
                    $class->cancel();
                    break;
                case 'filedeletequeue':
                    // FileDeleteQueue is the one tasking class with no model
                    // cancel(); only the manager implements one. So a valid id
                    // reached $class->cancel() and raised an Error -- which is
                    // not an \Exception, so the catch below never saw it and
                    // the caller got a bodyless 500. The daemon does set these
                    // rows to the progress state (filedeleter.class.php), so
                    // that is reachable, not theoretical.
                    if (!$class->isValid()) {
                        self::sendResponse(
                            HTTPResponseCodes::HTTP_NOT_FOUND
                        );
                    }
                    if (!in_array($class->get('stateID'), $states)) {
                        self::_notCancellable(
                            _('Queued deletion is not active and cannot be cancelled')
                        );
                    }
                    $class->getManager()->cancel([$class->get('id')]);
                    break;
                default:
                    if (!$class->isValid()) {
                        $classman = $class->getManager();
                        $find = self::getsearchbody($classname);
                        $find['stateID'] = $states;
                        $ids = self::ids($classname, $find);
                        // A search that matches nothing stays a 200. This arm
                        // is a bulk filter, and matching no rows is a
                        // legitimate result for one; the 409s in this method
                        // are for a caller who named a specific resource and
                        // is entitled to know it was not touched.
                        $classman->cancel($ids);
                    } else {
                        // Falling out of this test used to be silent: the
                        // method returned normally and the router answered 200
                        // "cancelled" with the state untouched. That is how a
                        // Failed task came back from the API reporting success
                        // and stayed Failed.
                        if (!in_array($class->get('stateID'), $states)) {
                            $stateName = self::getClass(
                                'TaskState',
                                $class->get('stateID')
                            )->get('name');
                            self::_notCancellable(
                                sprintf(
                                    '%s: %s',
                                    _('Task is not active and cannot be cancelled'),
                                    ($stateName ?: $class->get('stateID'))
                                )
                            );
                        }
                        $class->cancel();
                    }
            }
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Refuses a cancel the named resource is not in a state to accept.
     *
     * The body is a JSON object, not the bare reason string the older non-2xx
     * paths in this class emit. breakHead() has always declared
     * `Content-Type: application/json` and then echoed whatever it was given,
     * so those replies claim a type they are not; 409 is a status no caller
     * receives today, so it can start out matching its own header without
     * breaking anyone. Same `{"msg": ...}` shape the 200 already uses, which
     * is what $.notifyFromAPI() reads.
     *
     * @param string $msg Why the resource cannot be cancelled.
     *
     * @return void
     */
    private static function _notCancellable($msg)
    {
        self::sendResponse(
            HTTPResponseCodes::HTTP_CONFLICT,
            json_encode(['msg' => $msg])
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
            $classname = $class;
            $class = new $class;
            foreach ($classVars['databaseFields'] as &$key) {
                $key = $class->key($key);
                if (isset($vars->$key)) {
                    $find[$key] = $vars->$key;
                }
                unset($key);
            }
            // The other request-facing filter entry point. Intersecting with
            // the class's own fields, which is all this used to do, admits
            // every sensitive field -- they ARE the class's fields. Only
            // _assertNoSensitiveFilter() is called, not the full key check:
            // this body has always ignored keys it does not recognise, and
            // starting to 400 on them would be a separate behaviour change.
            self::_assertNoSensitiveFilter($find, $classname);

            // Request-supplied, so the caller-facing '*'/'+' wildcards apply
            // here (they used to be expanded down in _buildSql, which also
            // caught internally-built filters -- see expandSearchWildcards).
            return self::expandSearchWildcards($find);
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
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
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
            $count = self::getCount($classname, $whereItems);
            if (!$count) {
                self::sendResponse(
                    HTTPResponseCodes::HTTP_NOT_FOUND
                );
            }
            // Funnel REST single-delete through the shared cascade authority so it
            // runs the same removeItems map and fires DELETEMASS_API for plugins,
            // instead of a bare row delete that orphaned every association.
            return self::deletemass($class, $whereItems);
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
        self::$data = ['error' => $message];
        self::printer(self::$data, $code);
        exit;
    }
    /**
     * Emits an RFC 5988 Link header from a paginated list envelope so clients
     * that prefer headers over the body can walk pages. No-op unless the
     * payload carries the pagination *Url fields paginate() adds.
     *
     * @param mixed $data The response payload.
     *
     * @return void
     */
    public static function emitLinkHeader($data)
    {
        if (!is_array($data) || headers_sent()) {
            return;
        }
        $rels = [
            'first' => $data['firstUrl'] ?? null,
            'prev' => $data['prevUrl'] ?? null,
            'next' => $data['nextUrl'] ?? null,
            'last' => $data['lastUrl'] ?? null,
        ];
        $parts = [];
        foreach ($rels as $rel => $url) {
            if ($url) {
                $parts[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }
        if (count($parts) > 0) {
            header('Link: ' . implode(', ', $parts));
        }
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
            JSON_UNESCAPED_UNICODE
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
        // Masking happens HERE and must not move into indiv()/getData(). The
        // web tier reads through getData() and depends on it staying unmasked:
        // FOGConfigurationPage::settingsPost() compares $Setting->value against
        // the posted value to decide whether to write, so an absent 'value'
        // would leave $val empty, defeat the short-circuit and fall through to
        // writing. See fogconfigurationpage.page.php.
        $data = self::stripSensitivePayload($data);
        self::emitLinkHeader($data);
        $message = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
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
     * Removes sensitive columns from a list payload on its way out of the API.
     *
     * Stripping used to happen only inside listem()'s ?expand branch, so a
     * PLAIN list -- overwhelmingly the common call -- returned the raw grid row
     * untouched and handed ldap.bindPwd (and host.productKey wherever one is
     * set) to any API caller. Whether a row was decorated with ?expand has
     * nothing to do with whether it may carry a secret, so the guard has to be
     * unconditional or it is not a guard.
     *
     * It belongs HERE, in the single emitter every API route returns through,
     * and deliberately not in listem(). listem() is shared with the web tier:
     * the LDAP login path calls Route::listem('ldap') and needs bindPwd to
     * bind at all, and the Product Keys report calls Route::listem('host') and
     * needs productKey to have anything to report. Both read the result with
     * getData() and never reach printer(), so stripping at the emitter closes
     * the API surface without breaking the internal callers that legitimately
     * want the value. Stripping inside listem() would have broken LDAP
     * authentication outright.
     *
     * Only list-shaped payloads are touched -- those carrying both the '_lang'
     * classname stamp and a 'data' array. A single-entity GET is a flat object
     * with neither, so it keeps the documented "secrets only on a direct
     * single GET" contract that fog-client depends on for host ADPass.
     *
     * @param mixed $data The payload about to be encoded.
     *
     * @return mixed
     */
    public static function stripSensitivePayload($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        // is_scalar, because '_lang' is not always the classname stamp this
        // was written for. unisearch() builds it as an ARRAY -- a heading per
        // entity plus 'AllResults' -- so the cast produced the literal string
        // "Array", a PHP "Array to string conversion" warning on every
        // universal search, and a classname of "array" that matches nothing
        // in the sensitive map. Stripping therefore did nothing at all on
        // that path while appearing to run.
        //
        // Falling through to $emitClassname is right for it: a multi-entity
        // payload has no single classname, and guessing one would strip the
        // wrong entity's fields. What keeps that safe is upstream -- the
        // unisearch query selects exactly two columns and each row is rebuilt
        // as ['id' => ..., 'name' => ...], so no secret can reach here. If
        // that ever selects more, this needs a per-entity pass, not a cast.
        $classname = isset($data['_lang']) && is_scalar($data['_lang'])
            ? strtolower((string)$data['_lang'])
            : self::$emitClassname;
        if ('' === $classname) {
            return $data;
        }
        if (isset($data['data']) && is_array($data['data'])) {
            // List payload: both tiers, every row, and every entity nested
            // inside a row.
            foreach ($data['data'] as $i => $row) {
                $data['data'][$i] = self::stripEntity($classname, $row, false);
            }
            return $data;
        }
        // Single-entity payload: only the fields no client may read back.
        // Its nested entities are not covered by that carve-out -- see
        // stripEntity().
        return self::stripEntity($classname, $data, true);
    }
    /**
     * Strips one serialized entity, then everything nested inside it.
     *
     * The nesting comes from self::$nestedClasses, which embed() and
     * expandRelations() fill in as they build the payload, so this knows what
     * class each nested object is without a hand-kept table to fall out of
     * date. Both the one-object and the many-objects shapes are handled: an
     * expanded to-many relation is a list of entities under a single key.
     *
     * $alwaysOnly is TRUE only for the payload's top-level object on a direct
     * single-entity GET, and never propagates into the nesting. That is the
     * whole of the tier-1 carve-out: fog-client reads a host's ADPass back
     * from GET /host/{id} to join a domain, so tier 1 survives there -- but a
     * host nested inside a task is not that request, and GET /task/{id} was
     * handing out the host's client token and the storage node's FTP password
     * because nothing walked into it.
     *
     * Depth is capped rather than tracked by identity. The recursion follows
     * the DATA, which is finite and already built, so it terminates on its
     * own; the cap is there so a future embed that does contain a cycle
     * degrades into an unstripped inner object rather than an exhausted
     * stack, and it sits well above the three levels anything reaches today
     * (task -> host -> inventory).
     *
     * @param string $classname  The class this data is.
     * @param mixed  $data       The serialized entity.
     * @param bool   $alwaysOnly Tier 2 only, for the top-level single GET.
     * @param int    $depth      Recursion guard.
     *
     * @return mixed
     */
    protected static function stripEntity(
        $classname,
        $data,
        $alwaysOnly = false,
        $depth = 0
    ) {
        if (!is_array($data) || $depth > self::STRIP_MAX_DEPTH) {
            return $data;
        }
        $data = self::stripSensitive($classname, $data, $alwaysOnly);
        $nested = (array)(self::$nestedClasses[strtolower((string)$classname)]
            ?? self::$nestedClasses[$classname]
            ?? []);
        foreach ($nested as $key => $child) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }
            $value = $data[$key];
            // A to-many relation is a list of entities; a to-one is the
            // entity itself. An entity is an associative array, so a list
            // is the case where the keys are 0..n-1.
            $isList = $value === []
                || array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                foreach ($value as $j => $item) {
                    $value[$j] = self::stripEntity(
                        $child,
                        $item,
                        false,
                        $depth + 1
                    );
                }
                $data[$key] = $value;
                continue;
            }
            $data[$key] = self::stripEntity($child, $value, false, $depth + 1);
        }
        return $data;
    }
    /**
     * Serializes one embedded entity and records what class it is.
     *
     * Every nested object in a payload goes through here. The recording is
     * the point: stripSensitivePayload() strips secrets at the emitter, and
     * to do that on a NESTED object it has to know that class. getter() is
     * the only code that knows -- so it says so here, once, beside the
     * embed itself, and the emitter reads it back out of $nestedClasses.
     *
     * The class is derived from the object rather than passed in, so an
     * embed cannot be registered under the wrong name and a new one cannot
     * be added without registering it at all.
     *
     * Non-objects answer [] rather than fataling. Several call sites here
     * guard the same case by hand today -- a snapin task whose job row is
     * gone resolves get('snapinjob') to a STRING and get() on that is fatal
     * (#895) -- and one guard in one place is easier to keep right than
     * five.
     *
     * @param string $parentClass Class of the entity being built.
     * @param string $key         Key the child is stored under.
     * @param mixed  $obj         The related object, possibly not one.
     * @param bool   $useGetter   Shape the child with getter() rather than
     *                            its plain get(); matches what each call
     *                            site did before.
     *
     * @return array The serialized child.
     */
    protected static function embed($parentClass, $key, $obj, $useGetter = false)
    {
        if (!is_object($obj) || !$obj instanceof FOGController) {
            return [];
        }
        $child = strtolower(FOGCore::shortName($obj));
        self::$nestedClasses[$parentClass][$key] = $child;
        if (!$useGetter) {
            return (array) $obj->get();
        }
        $data = self::getter($child, $obj);
        return is_array($data) ? $data : [];
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
    public static function getter($classname, $class)
    {
        self::$getterDepth++;
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
                            'hostscreen' => self::embed(
                                $classname,
                                'hostscreen',
                                $class->get('hostscreen')
                            ),
                            'hostalo' => self::embed(
                                $classname,
                                'hostalo',
                                $class->get('hostalo')
                            ),
                            'inventory' => self::embed(
                                $classname,
                                'inventory',
                                $class->get('inventory'),
                                true
                            ),
                            'image' => self::embed(
                                $classname,
                                'image',
                                $class->get('imagename')
                            ),
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
                            'os' => self::embed(
                                $classname,
                                'os',
                                $class->get('os')
                            ),
                            'imagepartitiontype' => self::embed(
                                $classname,
                                'imagepartitiontype',
                                $class->get('imagepartitiontype')
                            ),
                            'imagetype' => self::embed(
                                $classname,
                                'imagetype',
                                $class->get('imagetype')
                            ),
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
                    $extra = [
                        'online' => $class->get('online'),
                        //'logfiles' => $class->get('logfiles'),
                        'storagegroup' => self::embed(
                            $classname,
                            'storagegroup',
                            $class->get('storagegroup')
                        ),
                        'location_url' => sprintf(
                            '%s://%s/%s',
                            self::$httpproto,
                            $class->get('ip'),
                            $class->get('webroot')
                        )
                    ];
                    /*
                     * images/snapinfiles/logfiles are not columns. Each one
                     * is an outbound HTTP GET to status/getfiles.php on that
                     * node, so serialising a node used to cost two round
                     * trips to a machine that may be down -- paid by every
                     * caller, including ones that never look at the answer.
                     * FOGMulticastManager re-reads its master nodes every
                     * MULTICASTSLEEPTIME (10s), and the storagegroup grid
                     * serialises a master node per row.
                     *
                     * logfiles was already commented out for exactly this
                     * cost, which is the tell that the other two should have
                     * been opt-in rather than deleted a third time. Left
                     * commented as it was; making it an expand token is a
                     * separate decision from stopping the fan-out.
                     *
                     * images and snapinfiles are still reachable, by
                     * ?expand=images,snapinfiles or ?expand=all. Callers that
                     * want the objects rather than the payload are unaffected:
                     * the UI reads them off StorageNode itself
                     * ($StorageNode->get('snapinfiles') in
                     * snapinmanagement.page.php), which never went through
                     * this serializer.
                     */
                    if (self::wantsExpand('images')) {
                        $extra['images'] = $class->get('images');
                    }
                    if (self::wantsExpand('snapinfiles')) {
                        $extra['snapinfiles'] = $class->get('snapinfiles');
                    }
                    $data = FOGCore::fastmerge($class->get(), $extra);
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
                            'image' => self::embed(
                                $classname,
                                'image',
                                $class->get('image')
                            ),
                            'host' => self::embed(
                                $classname,
                                'host',
                                $class->get('host'),
                                true
                            ),
                            'type' => self::embed(
                                $classname,
                                'type',
                                $class->get('type')
                            ),
                            'state' => self::embed(
                                $classname,
                                'state',
                                $class->get('state')
                            ),
                            'storagenode' => self::embed(
                                $classname,
                                'storagenode',
                                $class->get('storagenode')
                            ),
                            'storagegroup' => self::embed(
                                $classname,
                                'storagegroup',
                                $class->get('storagegroup')
                            )
                        ]
                    );
                    break;
                case 'plugin':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        ['hash' => md5($class->get('name'))]
                    );
                    break;
                case 'snapintask':
                    // Same trap as the snapin task LIST (see the snapintask
                    // case in the column setup above): a task whose job is
                    // gone resolves get('snapinjob') to a STRING, and get()
                    // on that is a fatal. Here it takes out the single-record
                    // GET -- and, because create() returns the new record
                    // through this same expansion, the create response too,
                    // which is how it was found.
                    //
                    // The relation is dropped entirely when the job is not there, rather
                    // than substituted with an empty SnapinJob. An empty one is
                    // still an instanceof, so getter() accepts it and runs the
                    // 'snapinjob' expansion below -- which dereferences that
                    // job's own state and fatals in turn. Guarding here and
                    // handing the failure downstream just moves it.
                    //
                    // Same for the snapin: a task can outlive it (one whose job
                    // is intact is deliberately not swept by schema step 318).
                    //
                    // Refs https://github.com/FOGProject/fogproject/issues/895
                    $snapinjob = $class->get('snapinjob');
                    $hasJob = is_object($snapinjob) && $snapinjob->isValid();
                    $sj = $hasJob
                        ? new Snapinjob($snapinjob->get('id'))
                        : null;
                    $host = $hasJob
                        ? new Host($snapinjob->get('hostID'))
                        : null;
                    $snapin = $class->get('snapin');
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'snapin' => self::embed(
                                $classname,
                                'snapin',
                                $snapin
                            ),
                            'snapinjob' => self::embed(
                                $classname,
                                'snapinjob',
                                $sj,
                                true
                            ),
                            'host' => self::embed(
                                $classname,
                                'host',
                                $host,
                                true
                            ),
                            'state' => self::embed(
                                $classname,
                                'state',
                                $class->get('state')
                            )
                        ]
                    );
                    break;
                case 'snapinjob':
                    // Reached through getter() from the snapintask case above
                    // as well as directly, so it has to stand up to a job that
                    // does not resolve: an unloadable state comes back as a
                    // string, and this was the fatal left over once the
                    // snapintask case stopped throwing on its own.
                    // Refs https://github.com/FOGProject/fogproject/issues/895
                    $sjState = $class->get('state');
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'host' => self::embed(
                                $classname,
                                'host',
                                $class->get('host'),
                                true
                            ),
                            'state' => self::embed(
                                $classname,
                                'state',
                                $sjState
                            )
                        ]
                    );
                    break;
                case 'usertracking':
                    // getter() is safe on its own -- it returns early unless
                    // it is handed the right kind of object -- but the bare
                    // hostname dereference beside it is not, and a login
                    // record can outlive the host it was recorded against.
                    // No such row exists on the box this was written against,
                    // so this is the guard being made consistent rather than a
                    // reproduced failure.
                    // Refs https://github.com/FOGProject/fogproject/issues/895
                    $utHost = $class->get('host');
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'host' => self::embed(
                                $classname,
                                'host',
                                $utHost,
                                true
                            ),
                            'hostname' => is_object($utHost)
                                ? $utHost->get('name')
                                : ''
                        ]
                    );
                    break;
                case 'multicastsession':
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            'imageID' => $class->get('image'),
                            'image' => self::embed(
                                $classname,
                                'image',
                                $class->get('imagename')
                            ),
                            'state' => self::embed(
                                $classname,
                                'state',
                                $class->get('state')
                            )
                        ]
                    );
                    unset($data['imagename']);
                    break;
                case 'scheduledtask':
                    // Lifted out of the nested ternaries it used to be: the
                    // key and the object have to agree, and embed() has to be
                    // told the key it is registering under.
                    $stGroupBased = $class->isGroupBased();
                    $stKey = $stGroupBased ? 'group' : 'host';
                    $stObj = $stGroupBased
                        ? $class->getGroup()
                        : $class->getHost();
                    $data = FOGCore::fastmerge(
                        $class->get(),
                        [
                            $stKey => self::embed(
                                $classname,
                                $stKey,
                                $stObj,
                                true
                            ),
                            'tasktype' => self::embed(
                                $classname,
                                'tasktype',
                                $class->getTaskType()
                            ),
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
            // NOTHING is stripped here. Secrets come out at the emitter,
            // once, and that is the whole of the rule -- printer() says so
            // for lists and it now holds for single entities and for every
            // nested object as well.
            //
            // It used to strip tier 2 here, because the emitter keyed off
            // the payload's '_lang' stamp and so read a storagenode nested
            // in a storagegroup row as a storagegroup, which is to say not
            // at all. That fixed the nesting in the wrong layer and cost
            // twice:
            //
            //   - it only ever covered embeds built by recursing into
            //     getter(). The 14 that were a plain ->get() -- task's
            //     storagenode among them -- were stripped at no level, and
            //     GET /task/{id} returned the node's FTP password and the
            //     host's client token in the clear.
            //   - it made getItem() and getList() disagree. getList() reads
            //     listem()'s rows before the emitter and so sees everything;
            //     getItem() came through here and saw a redacted object. The
            //     replicator asked getItem() for the node it was about to
            //     send to and got no password, so every transfer was refused
            //     at login and blamed on the admin's stored credential.
            //
            // embed() records what class each nested object is instead, and
            // stripSensitivePayload() walks that on the way out. Internal
            // callers -- the daemons, the LDAP bind, the Product Keys report
            // -- get the whole object, consistently, whichever wrapper they
            // used to ask for it.
            return $data;
        } catch (\Exception $e) {
            self::_sendCaught($e);
        } finally {
            self::$getterDepth--;
        }
    }
    /**
     * Parses the ?expand=a,b,c[,all] query parameter into the static
     * expansion state used by expandRelations()/enrichPluginItems().
     *
     * Called once at the entry of indiv()/listem(). Resets any prior state
     * so a single PHP request that serves multiple entities stays clean.
     *
     * @return void
     */
    public static function parseExpand()
    {
        self::$expand = [];
        self::$expandAll = false;
        self::$expandDepth = 0;
        $raw = self::queryParam('expand');
        if ($raw === null || $raw === false || $raw === '') {
            return;
        }
        $tokens = array_filter(
            array_map('trim', explode(',', strtolower((string)$raw)))
        );
        if (in_array('all', $tokens, true)) {
            self::$expandAll = true;
        }
        self::$expand = array_values($tokens);
    }
    /**
     * Reads a query-string parameter for the API.
     *
     * FOG's API is served through an nginx/apache internal rewrite to
     * api/index.php that does not propagate the request's query string into
     * QUERY_STRING/$_GET (the API otherwise takes every input from the request
     * body). The original request line still carries the query string, so fall
     * back to parsing REQUEST_URI when $_GET has not been populated.
     *
     * Apache's rewrite carries QSA and never had the problem; nginx's
     * try_files fallback did, and the installer now appends $is_args$args to
     * fix it at the source. This stays because a server that has not re-run
     * the installer keeps its old vhost, and because a handler cannot tell
     * which of the two it is running under.
     *
     * Public, not protected, because plugins register handlers on this same
     * router and hit the same problem the moment they read a query parameter
     * -- the OIDC plugin's ?provider= is how this was found. A plugin calling
     * filter_input(INPUT_GET, ...) on a routed path silently sees nothing, so
     * the working answer has to be reachable rather than re-invented.
     *
     * @param string $key The parameter name to read.
     *
     * @return string|null The raw value, or null when absent.
     */
    public static function queryParam($key)
    {
        $val = filter_input(INPUT_GET, $key);
        if ($val !== null && $val !== false) {
            return $val;
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs === '') {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $pos = strpos($uri, '?');
            if ($pos !== false) {
                $qs = substr($uri, $pos + 1);
            }
        }
        if ($qs === '') {
            return null;
        }
        parse_str($qs, $parsed);
        return isset($parsed[$key]) ? (string)$parsed[$key] : null;
    }
    /**
     * True when the client asked for any relation expansion.
     *
     * @return bool
     */
    public static function expandRequested()
    {
        return self::$expandAll || !empty(self::$expand);
    }
    /**
     * True when a specific relation token was requested (or ?expand=all).
     *
     * @param string $token The relation token to test.
     *
     * @return bool
     */
    public static function wantsExpand($token)
    {
        return self::$expandAll || in_array($token, self::$expand, true);
    }
    /**
     * Both secret-field tiers, with what plugins declare folded in.
     *
     * A plugin's own table can hold a credential just as core's can -- an
     * API token, a webhook URL, a bind password -- and before this hook the
     * only way to keep one out of REST output was to hand-write it into the
     * core arrays above, which is exactly what the LDAP plugin's bindPwd
     * needed and no third-party plugin could ever do. Listeners append to
     * either tier by class name, e.g.
     *
     *   $arguments['always']['ldap'][] = 'bindPwd';
     *
     * A third bucket, 'exempt', carries the opposite declaration: a friendly
     * key that matches Redaction::CREDENTIAL_PATTERN and is NOT a credential.
     * Without it a plugin could only ever say "this is a secret", so a name
     * like capone's 'key' -- a DMI string to match an image on, submitted in
     * the clear by the unauthenticated capone endpoint -- had nowhere to be
     * classified. Core's own answers live in Redaction::$patternExempt and
     * seed this bucket; a plugin appends to it the same way:
     *
     *   $arguments['exempt']['capone'][] = 'key';
     *
     * It rides this event rather than one of its own because it is read by
     * the same call, and because a second event would fire every listener
     * twice for one question.
     *
     * Memoized per request: the tiers are read on every serialized object,
     * so firing the event each time would put a hook pass in the middle of
     * every list payload's inner loop.
     *
     * @return array ['fields' => tier-1, 'always' => tier-2, 'exempt' => map]
     */
    public static function sensitiveFieldMap()
    {
        if (null !== self::$_sensitiveMap) {
            return self::$_sensitiveMap;
        }
        $fields = self::$sensitiveFields;
        $always = self::$sensitiveAlwaysFields;
        $exempt = Redaction::$patternExempt;
        // Memoized with the CORE tiers BEFORE the event fires, and again with
        // the plugin-augmented ones after. The pre-set is what makes this
        // re-entrant, and it has to be: HookManager::processEvent() populates
        // its $knownEvents list by calling Route::getIds('hookevent'), so
        // firing ANY event can re-enter Route -- and any Route path that
        // consults this map then arrives here with $_sensitiveMap still null
        // and fires the event again, forever. That is not hypothetical; it is
        // an OOM in ~40 frames, and today only the accident that ids() never
        // asked for the map keeps the cycle finite:
        //
        //   sensitiveFieldMap -> processEvent -> getIds -> ids
        //     -> unfilterableFields -> sensitiveFieldMap -> ...
        //
        // A re-entrant caller sees the core tiers, never a smaller set, so it
        // can only ever miss a PLUGIN-declared field for the duration of that
        // one nested call -- and the only re-entrant caller is the hookevent
        // name lookup, whose class declares no secrets at all. Losing a
        // plugin field for that is the safe side of the trade; the unsafe
        // side is the process dying.
        //
        // Same shape, same fix, in serverOwnedFields() below.
        self::$_sensitiveMap = [
            'fields' => (array)$fields,
            'always' => (array)$always,
            'exempt' => (array)$exempt
        ];
        self::$HookManager->processEvent(
            'API_SENSITIVE_FIELDS',
            [
                'fields' => &$fields,
                'always' => &$always,
                'exempt' => &$exempt
            ]
        );
        return self::$_sensitiveMap = [
            'fields' => (array)$fields,
            'always' => (array)$always,
            'exempt' => (array)$exempt
        ];
    }
    /**
     * The fields of a class that only the server may write.
     *
     * @param string $class The class name (any case).
     *
     * @return array friendly keys
     */
    public static function serverOwnedFields($class)
    {
        if (null === self::$_serverOwnedMap) {
            $fields = self::$serverOwnedFields;
            // Pre-set before the event, then again after -- see the long note
            // in sensitiveFieldMap(). Not currently reachable re-entrantly
            // (no path from processEvent() consults this map), but it is the
            // same construction one call site away from the same OOM.
            self::$_serverOwnedMap = (array)$fields;
            self::$HookManager->processEvent(
                'API_SERVER_OWNED_FIELDS',
                ['fields' => &$fields]
            );
            self::$_serverOwnedMap = (array)$fields;
        }
        $classname = strtolower((string)$class);
        return isset(self::$_serverOwnedMap[$classname])
            ? (array)self::$_serverOwnedMap[$classname]
            : [];
    }
    /**
     * Refuses a write to a server-maintained field.
     *
     * Answers 400 rather than dropping the field, so a caller that meant
     * to set it learns that it did not happen -- silently ignoring a
     * requested write is how a client ends up believing state it never
     * achieved.
     *
     * But it refuses only an actual CHANGE. Reading an object and PUTting
     * the whole thing back is ordinary REST, a single-entity GET returns
     * these fields, and a body that carries a value identical to the
     * stored one is asking for nothing. Rejecting that would break every
     * round-tripping client to close a hole none of them are in.
     *
     * @param object $class The loaded object, or null on create.
     * @param string $key   The friendly key being written.
     * @param mixed  $value The value the body supplied.
     *
     * @return void
     */
    private static function _refuseServerOwned($class, $key, $value)
    {
        if (null !== $class) {
            $stored = $class->get($key);
            // Scalars only: a non-scalar can never equal a column value,
            // and casting an array to string is a fatal.
            if (is_scalar($value) || null === $value) {
                if ((string)$value === (string)$stored) {
                    return;
                }
            }
        } elseif (null === $value
            || (is_scalar($value) && '' === trim((string)$value))
        ) {
            // On create there is nothing to compare against, so the test
            // is "did you ask for a value". An empty one is what the
            // server was going to store anyway.
            return;
        }
        self::setErrorMessage(
            sprintf(
                _('%s is maintained by the server and cannot be set'),
                $key
            ),
            HTTPResponseCodes::HTTP_BAD_REQUEST
        );
    }
    /**
     * Removes decrypted secrets from a related/list object so they are only
     * ever exposed on a direct single-entity GET.
     *
     * @param string $classname The class the data belongs to.
     * @param mixed  $data      The serialized object (assoc array).
     *
     * @return mixed
     */
    public static function stripSensitive($classname, $data, $alwaysOnly = false)
    {
        if (!is_array($data)) {
            return $data;
        }
        $map = self::sensitiveFieldMap();
        $fields = (array)($map['always'][$classname] ?? []);
        if (!$alwaysOnly) {
            $fields = array_merge(
                $fields,
                (array)($map['fields'][$classname] ?? [])
            );
        }
        foreach ($fields as $field) {
            unset($data[$field]);
        }
        if ('setting' === $classname) {
            $data = self::maskSensitiveSetting($data);
        }
        return $data;
    }
    /**
     * Blanks the value of a globalSettings row that holds a credential.
     *
     * Unlike every other class the sensitive part of a setting is not a
     * column but the value of a particular key, so this keys off the row's
     * name. The name/description/category stay, so the setting is still
     * visible -- only its value goes.
     *
     * @param mixed $data A serialized setting row.
     *
     * @return mixed
     */
    public static function maskSensitiveSetting($data)
    {
        if (!is_array($data) || !isset($data['name'])) {
            return $data;
        }
        if (self::isSensitiveSetting((string)$data['name'])) {
            unset($data['value']);
        }
        return $data;
    }
    /**
     * Whether a globalSettings key holds a credential.
     *
     * @param string $key The settingKey to test.
     *
     * @return bool
     */
    public static function isSensitiveSetting($key)
    {
        if (in_array($key, self::$sensitiveSettingsExempt, true)) {
            return false;
        }
        if (in_array($key, self::$sensitiveSettings, true)) {
            return true;
        }
        return 1 === preg_match(self::SENSITIVE_SETTING_PATTERN, $key);
    }
    /**
     * Declarative per-class relation map driving ?expand. Each entry:
     *   token => ['class' => <target>, 'many' => bool, 'field' => <accessor>]
     * where <accessor> is a get() key returning either a related object
     * (many=false) or an array of integer ids (many=true).
     *
     * Only relations whose target getter does NOT re-embed the parent are
     * listed, so expansion is inherently free of back-references.
     *
     * @param string $classname The entity being serialized.
     *
     * @return array
     */
    protected static function relationMap($classname)
    {
        $maps = [
            'host' => [
                'image' => [
                    'class' => 'image',
                    'many' => false,
                    'field' => 'imagename',
                ],
                'snapins' => [
                    'class' => 'snapin',
                    'many' => true,
                    'field' => 'snapins',
                ],
                'printers' => [
                    'class' => 'printer',
                    'many' => true,
                    'field' => 'printers',
                ],
                'groups' => [
                    'class' => 'group',
                    'many' => true,
                    'field' => 'groups',
                ],
                'modules' => [
                    'class' => 'module',
                    'many' => true,
                    'field' => 'modules',
                ],
            ],
        ];
        return $maps[$classname] ?? [];
    }
    /**
     * Additively inlines requested related objects onto an already
     * serialized entity. Scalar foreign keys are preserved; expanded
     * objects are added under their relation token. One-to-many relations
     * become a capped array plus companion `<token>_total`/`<token>_truncated`
     * keys. Related objects are serialized one level deep (no further
     * expansion). Their secrets are removed at the emitter like every other
     * nested object's, via the class this records in $nestedClasses.
     *
     * @param string $classname The entity classname.
     * @param object $class      The loaded entity object.
     * @param array  $data       The serialized entity data.
     *
     * @return array
     */
    public static function expandRelations($classname, $class, $data)
    {
        if (!is_array($data) || !self::expandRequested()) {
            return $data;
        }
        if (self::$expandDepth >= self::EXPAND_MAX_DEPTH) {
            return $data;
        }
        $map = self::relationMap($classname);
        if (empty($map)) {
            return $data;
        }
        self::$expandDepth++;
        foreach ($map as $token => $rel) {
            if (!self::wantsExpand($token)) {
                continue;
            }
            if (empty($rel['many'])) {
                $obj = $class->get($rel['field']);
                if ($obj instanceof FOGController && $obj->isValid()) {
                    $g = self::getter($rel['class'], $obj);
                    if (is_array($g)) {
                        // Registered, not stripped. Same rule as embed():
                        // the emitter removes secrets, and it can only do
                        // that on a related object if it knows the class.
                        self::$nestedClasses[$classname][$token]
                            = $rel['class'];
                        $data[$token] = $g;
                    }
                }
                continue;
            }
            $ids = self::positiveIntIds((array)$class->get($rel['field']));
            $total = count($ids);
            $truncated = false;
            if ($total > self::EXPAND_MAX_ITEMS) {
                $ids = array_slice($ids, 0, self::EXPAND_MAX_ITEMS);
                $truncated = true;
            }
            $items = [];
            // As above: the whole collection in one query rather than one per
            // member. This is the inner half of the cost -- a host expanded
            // with its macs, snapins and modules resolves every one of them
            // here.
            self::primeRel($rel['class'], $ids);
            foreach ($ids as $rid) {
                $robj = self::rel($rel['class'], $rid);
                if (!$robj->isValid()) {
                    continue;
                }
                $g = self::getter($rel['class'], $robj);
                if (is_array($g)) {
                    self::$nestedClasses[$classname][$token] = $rel['class'];
                    $items[] = $g;
                }
            }
            $data[$token] = $items;
            $data[$token . '_total'] = $total;
            $data[$token . '_truncated'] = $truncated;
        }
        self::$expandDepth--;
        return $data;
    }
    /**
     * Fires the API_PLUGIN_ITEMS event so plugins can inject their
     * associations into a namespaced `pluginItems` envelope without
     * clobbering core fields. Only invoked at the top level of a single
     * GET or of each list row (never on nested/related objects), which is
     * what keeps plugin back-references out of expanded children.
     *
     * @param string $classname The entity classname.
     * @param object $class      The loaded entity object.
     * @param array  $data       The serialized entity data.
     *
     * @return array
     */
    public static function enrichPluginItems($classname, $class, $data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $pluginItems = [];
        self::$HookManager->processEvent(
            'API_PLUGIN_ITEMS',
            [
                'data' => &$data,
                'pluginItems' => &$pluginItems,
                'classname' => &$classname,
                'class' => &$class
            ]
        );
        if (!empty($pluginItems)) {
            $data['pluginItems'] = $pluginItems;
        }
        return $data;
    }
    /**
     * Returns the current bandwidth.
     *
     * @param string $dev The device to get bandwidth from.
     *
     * @return mixed
     */
    public static function bandwidth($dev)
    {
        if (!$dev || !preg_match('/^[a-zA-Z0-9._-]+$/', $dev)) {
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

            $whereItems = self::handleWhereItems($whereItems, $class);
            // The value oracle again -- see names(). Refused here rather
            // than dropped per row, because this route projects $getField
            // and nothing else: on the default /setting/ids the setting NAME
            // is not in the result at all, so there is no row to ask
            // isSensitiveSetting() about. Refusing costs a caller nothing --
            // /setting?filter=value=<x> still answers, with the credential
            // rows removed.
            //
            // SAPI-gated exactly like the sensitive-getField refusal below:
            // sendResponse() exits, and a daemon that exits is a restart
            // loop. Off-request this returns no rows and logs, fail-closed.
            if ('setting' === $classname
                && is_array($whereItems)
                && array_key_exists('value', $whereItems)
            ) {
                if ('cli' === PHP_SAPI) {
                    self::error(
                        'Route::ids: refusing a setting filter on value.'
                        . ' Returning no rows.'
                    );
                    self::$data = [];
                    return;
                }
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    json_encode(
                        [
                            'error' => _('Cannot filter setting ids on: value')
                        ]
                    )
                );
            }
            if (false !== $whereItems && count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }
            if (isset($vars->getField) && $vars->getField) {
                $getField = $vars->getField;
            }
            // Same empty-identifier trap as an unknown filter key, but on the
            // selected column: getField is a URL segment ("/ids/id=1/name")
            // or a JSON body field, so an unrecognised value compiled to
            // `SELECT `` FROM ...` and returned HTTP 200 with [].
            //
            // Only answered when actually serving a request. Unlike a filter
            // key -- which handleWhereItems() vets on the string form alone,
            // so only ever for request input -- getField reaches here from
            // the services and from FOGController's association helper,
            // which passes it in a variable. sendResponse() exits, so
            // answering unconditionally would turn a bad field into a dead
            // daemon (cf. 2d199fa4b). Off-request, log and leave the
            // pre-existing rejected-query behaviour alone.
            // $getField may be an array, in which case each row comes back as
            // a map of friendly name => value instead of a bare scalar. Only
            // ever passed internally -- a URL segment or JSON body can only
            // name one field -- but it is what lets a caller that needs two
            // columns together read them in one query rather than one query
            // per row. Refs GH-707.
            $getFields = (array)$getField;
            // A field the emitter would strip must not be SELECTable either.
            //
            // getField is request-supplied -- it is the last segment of
            // "/ids/id=1/name" -- and it names a column outright, so the only
            // check standing between a caller and any column of any class in
            // $validClasses was the databaseFields test below. That test says
            // the column exists, not that the caller may read it, and
            // stripSensitivePayload() cannot make up the difference: this
            // route answers with a bare array of scalars carrying no '_lang'
            // stamp, so the emitter resolves an empty classname and returns
            // the payload untouched. GET /host/ids/id=1/sec_tok therefore
            // handed a host's plaintext fog-client token -- and ADPass,
            // productKey, user.password, user.token -- to any caller holding
            // <entity>.view, the same permission as reading the list.
            //
            // Refused rather than dropped, matching _assertNoSensitiveFilter()
            // exactly: silently substituting a different column would answer a
            // question the caller did not ask. Same list, read the same way,
            // so a plugin's own declared secret is covered by construction.
            //
            // Deliberately before the existence test, so a sensitive field is
            // named as forbidden rather than as valid-but-refused, and so the
            // 'valid' hint below cannot advertise it.
            //
            // Gated on SAPI for the same reason the unknown-field branch
            // below is: sendResponse() exits, and a daemon that exits is a
            // systemd restart loop (cf. 2d199fa4b). Both arms refuse -- what
            // differs is how. Serving a request answers 400. Off-request the
            // call returns an EMPTY result and logs, which is fail-closed
            // without ending the process; falling through the way the
            // unknown-field branch does would hand the secret back, and that
            // is the one outcome this must not have.
            //
            // No in-repo caller is affected. Every getIds()/ids() call site
            // was checked and the fields asked for are id, name, path,
            // snapinpath, hostID, ip, mac, userID, usergroupID, siteID,
            // storagegroupID, imageID, groupID, msID, isMaster, pending,
            // sslpath, trustedcidrs, grantroleID, clientIgnore, imageIgnore,
            // lastcheckin.
            //   grep -rn 'getIds(' --include=*.php packages/ /path/to/fog-plugins
            $blocked = array_intersect(
                $getFields,
                self::unfilterableFields($classname)
            );
            if (count($blocked) > 0) {
                if ('cli' === PHP_SAPI) {
                    self::error(
                        sprintf(
                            'Route::ids: refusing sensitive field(s) for %s: %s.'
                            . ' Returning no rows.',
                            $classname,
                            implode(', ', $blocked)
                        )
                    );
                    self::$data = [];
                    return;
                }
                self::sendResponse(
                    HTTPResponseCodes::HTTP_BAD_REQUEST,
                    json_encode(
                        [
                            'error' => sprintf(
                                _('Cannot select %s field(s): %s'),
                                $classname,
                                implode(', ', $blocked)
                            )
                        ]
                    )
                );
            }
            foreach ($getFields as $field) {
                if (isset($classVars['databaseFields'][$field])) {
                    continue;
                }
                if ('cli' === PHP_SAPI) {
                    self::error(
                        sprintf(
                            'Route::ids: unknown field for %s: %s',
                            $classname,
                            $field
                        )
                    );
                } else {
                    self::sendResponse(
                        HTTPResponseCodes::HTTP_BAD_REQUEST,
                        json_encode(
                            [
                                'error' => sprintf(
                                    _('Unknown field for %s: %s'),
                                    $classname,
                                    $field
                                ),
                                'valid' => array_keys(
                                    (array)$classVars['databaseFields']
                                )
                            ]
                        )
                    );
                }
            }

            $realFields = [];
            foreach ($getFields as $field) {
                $realFields[$field] = $classVars['databaseFields'][$field];
            }
            $sql = 'SELECT `'
                . implode('`,`', array_unique(array_values($realFields)))
                . '` FROM `'
                . $classVars['databaseTable']
                . '`';

            // Object scope, in the query. This route answered server-wide
            // to anyone holding <entity>.view -- the same permission as
            // reading the list, which IS scoped -- so a site-scoped user
            // could enumerate every host id and name on the server in one
            // request.
            //
            // In the WHERE rather than over the results, and that is what
            // makes it work at all here: the boundary constrains ROWS, so it
            // is independent of which COLUMN the caller asked for. Filtering
            // the results would need the id, and this route need not return
            // one -- `/host/ids/id=1/name` answers with bare names.
            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby,
                self::_requestScopeWhere(
                    $classname,
                    '`' . $classVars['databaseFields']['id'] . '`'
                )
            );

            $vals = self::$DB->query($sqlResult['sql'], [], $sqlResult['params'])->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            foreach ($vals as &$val) {
                if (is_array($getField)) {
                    $row = [];
                    foreach ($realFields as $field => $real) {
                        $row[$field] = $val[$real];
                    }
                    $data[] = $row;
                } else {
                    $data[] = $val[$realFields[$getField]];
                }
                unset($val);
            }
            self::$data = $data;
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Resolves a related object for a grid cell, once per grid.
     *
     * GH-707: the per-row formatters in listem() each build a related object
     * from an id -- the host's image, a task's host, a snapin task's snapin,
     * a task state's icon. One object per row is one query per row, so a
     * 1000-row grid ran a thousand extra queries on top of its own, which is
     * what made a large task list hang the page. The same ids repeat over and
     * over down a column, so resolve each distinct one once.
     *
     * The cache lives only for the current listem() call -- see $relCache.
     *
     * @param string $class The class to resolve.
     * @param mixed  $id    The id to resolve it by.
     *
     * @return object
     */
    protected static function rel($class, $id)
    {
        $key = strtolower($class) . ':' . $id;
        if (!array_key_exists($key, self::$relCache)) {
            self::$relCache[$key] = self::getClass($class, $id);
        }
        return self::$relCache[$key];
    }
    /**
     * Resolves a whole column's worth of related objects up front.
     *
     * rel() collapses repeats, but a column of distinct ids -- the host on
     * every row of a task list, say -- still costs one query per row. This is
     * the column's 'prime' callback: dataOutput() hands it the entire result
     * set before any formatter runs, so every id the column will ask for can
     * be fetched in a single query. Ids with no matching record are cached as
     * an empty object carrying the id, which is exactly the state a failed
     * load() leaves behind, so a stale reference still renders as it did and
     * still costs nothing. Refs GH-707.
     *
     * @param string $class The class to resolve.
     * @param array  $ids   The ids appearing in the column.
     *
     * @return void
     */
    protected static function primeRel($class, $ids)
    {
        $missing = [];
        foreach ((array) $ids as $id) {
            if (null === $id || '' === $id || (int) $id < 1) {
                continue;
            }
            $key = strtolower($class) . ':' . $id;
            if (array_key_exists($key, self::$relCache)) {
                continue;
            }
            $missing[$key] = $id;
        }
        if (count($missing ?: []) < 1) {
            return;
        }
        $objects = self::getClass($class)->loadMany(array_values($missing));
        foreach ($missing as $key => $id) {
            self::$relCache[$key] = isset($objects[$id]) ?
                $objects[$id] :
                self::getClass($class)->set('id', $id);
        }
    }
    /**
     * Builds a grid column whose formatter resolves a related object.
     *
     * Pairs the formatter with the matching primeRel() call so the two can
     * never drift apart -- a formatter that reaches for a relation without a
     * primer is exactly the per-row query storm this exists to stop.
     *
     * @param string   $real      The database column holding the id.
     * @param string   $dt        The output name for the column.
     * @param string   $relclass  The class the ids refer to.
     * @param callable $formatter The per-row formatter.
     * @param string   $order     Optional SQL to sort this column by, for
     *                            the case where sorting by the id sorts by
     *                            something the reader cannot see. Only ever
     *                            set from code in this file; see
     *                            FOGManagerController::orderRef().
     *
     * @return array The column definition.
     */
    protected static function relColumn(
        $real,
        $dt,
        $relclass,
        $formatter,
        $order = null
    ) {
        $column = [
            'db' => $real,
            'dt' => $dt,
            'prime' => function ($rows) use ($real, $relclass) {
                self::primeRel(
                    $relclass,
                    array_column((array) $rows, $real)
                );
            },
            'formatter' => $formatter
        ];
        if (null !== $order) {
            $column['order'] = $order;
        }
        return $column;
    }
    /**
     * A userTracking action code as its label.
     *
     * Shared by the grid's own Action column and by the activity viewer's
     * summary, so the two can never disagree about what a code means.
     *
     * @param mixed $code The stored utAction value.
     *
     * @return string
     */
    private static function _userTrackingAction($code)
    {
        switch ((string) $code) {
            case (string) UserTracking::ACTION_LOGOUT:
                return _('Logout');
            case (string) UserTracking::ACTION_LOGIN:
                return _('Login');
            case (string) UserTracking::ACTION_SERVICE_START:
                return _('Service Start');
        }
        // A code this does not know renders as itself, not as an empty
        // cell. utAction has no lookup table and nothing constrains the
        // column to the three codes UserTracking declares, so an
        // unrecognised one is a real possibility (a plugin writing its own,
        // or the '' that save() wrote into every unset column before
        // GH-1245). Falling out of the switch returned null, so the row
        // still listed with a blank Action and nothing said why.
        return '' === (string) $code ? _('Unknown') : (string) $code;
    }
    /**
     * One history row as a sentence, in the READER's language.
     *
     * Delegates to History::summary(), which is where the renderer lives
     * now. It moved there when the dashboard's Recent Activity card became
     * a second reader (ADR 0020 decision 5, writer half): the sentence is a
     * property of a history row, not of the router, and two readers reaching
     * into the router for it is how they drift apart.
     *
     * Kept as a wrapper rather than inlined at the call site so the grid
     * column's formatter still reads like every other formatter here.
     *
     * @param array $row The raw database row.
     *
     * @return string
     */
    private static function _historySummary($row)
    {
        return History::summary($row);
    }
    /**
     * The row column holding a denormalized copy of the host's name.
     *
     * ADR 0020 phase 4, and the reason phase 2 added the column at all: a
     * grid that resolves the host name live from an id renders a blank cell
     * forever once the host is deleted, and `Route::deletemass('host')`
     * leaves both of these tables' rows in place. The row survives and
     * becomes unreadable, which is the worst of both policies.
     *
     * The live name is still preferred where the host exists, so a renamed
     * host reads as its current name; the stored copy answers only when
     * there is nothing left to look up.
     *
     * @param string $classname The lowercased class being listed.
     *
     * @return string|null The row key, or null if the class has no copy.
     */
    private static function _hostNameColumn($classname)
    {
        switch ($classname) {
            case 'tasklog':
                return 'logHostName';
            case 'usertracking':
                return 'utHostName';
        }
        return null;
    }
    /**
     * The host's name for a grid row: live if it still exists, else stored.
     *
     * @param mixed  $id        The host id from the row.
     * @param array  $row       The raw database row.
     * @param string $classname The lowercased class being listed.
     *
     * @return string
     */
    private static function _hostLabel($id, $row, $classname)
    {
        $name = $id ? (string)self::rel('host', $id)->get('name') : '';
        if ('' !== $name) {
            return $name;
        }
        $key = self::_hostNameColumn($classname);
        if (null !== $key && isset($row[$key])) {
            return (string)$row[$key];
        }
        return $name;
    }
    /**
     * The SQL to sort a class's host column by host NAME rather than id.
     *
     * Only available to the classes whose model declares a join to `hosts`
     * in its list query -- TaskLog, UserTracking and SnapinTask, which are
     * the three the group page's history tabs list. Every other class using
     * the generic hostID column keeps ordering by the raw id, because the
     * name is not in its query and naming it would be an unknown column.
     *
     * Why it is a CONCAT rather than just the name: these grids group their
     * rows by host, and RowGroup repeats a group header whenever a group's
     * rows are not contiguous. Two hosts sharing a name would interleave on
     * the name alone, so the id follows it as a tie-break. The separator is
     * a space, which sorts below every alphanumeric, so "ab 9" still comes
     * before "abc 1" -- the name remains the primary key of the sort.
     *
     * @param string $classname The lowercased class being listed.
     *
     * @return string|null The ORDER BY expression, or null if unavailable.
     */
    private static function _hostNameOrder($classname)
    {
        switch ($classname) {
            case 'tasklog':
                $id = '`taskLog`.`logHostID`';
                break;
            case 'usertracking':
                $id = '`userTracking`.`utHostID`';
                break;
            case 'snapintask':
                // Reached through the job, so it is null when the job is gone.
                $id = '`snapinJobs`.`sjHostID`';
                break;
            default:
                return null;
        }
        // The id is COALESCEd too, and not for tidiness: CONCAT returns NULL
        // if any argument is NULL, so a row with no host id -- taskLog wrote
        // none on a state row before ADR 0022, and 53 such rows exist on the
        // development server -- would get a NULL key. Those rows all render
        // an empty host cell and belong in one group, which is what a shared
        // non-NULL key gives them.
        return "CONCAT(COALESCE(`hosts`.`hostName`, ''), ' ', COALESCE({$id}, 0))";
    }
    /**
     * Returns the matching ids directly as a PHP array.
     *
     * Convenience wrapper around ids() that skips the
     * json_encode/json_decode round-trip getData() incurs, for the
     * common `Route::ids(...); json_decode(Route::getData())` idiom.
     *
     * @param string $class      The class to get list of.
     * @param array  $whereItems The items to filter.
     * @param mixed  $getField   The field to get, or an array of fields, in
     *                           which case each element is a map of field
     *                           name => value rather than a bare value.
     * @param string $operator   The operator for the SQL. AND is default.
     * @param string $orderby    How to order the returned values.
     *
     * @return array
     */
    public static function getIds(
        $class,
        $whereItems = [],
        $getField = 'id',
        $operator = 'AND',
        $orderby = 'name'
    ) {
        self::ids($class, $whereItems, $getField, $operator, $orderby);
        $data = self::$data;
        self::$data = '';
        return is_array($data) ? $data : [];
    }
    /**
     * Returns a list's rows directly as a PHP array.
     *
     * The listem() counterpart of getIds(), and the reason it exists is the
     * same: `Route::listem(...); json_decode(Route::getData());` encodes the
     * result to JSON only to decode it straight back, and leaves every caller
     * holding the paginated envelope.
     *
     * That envelope is the DataTables and pagination contract and is not going
     * anywhere -- but the rows live under its `data` key, and a caller that
     * iterates the envelope instead walks its eleven scalar members (draw,
     * recordsTotal, the page URLs...) and gets null for every field. It does
     * not error, it warns. That mistake has shipped three times: the ou
     * plugin never set ADOU on any client check-in, cancelling a group's tasks
     * cancelled nothing, and the iPXE menu never applied its fog.local label.
     *
     * This returns the rows, so there is no envelope for a caller to hold
     * wrongly. Every permission filter, hook, expand and pluginItems step
     * still runs -- listem() does the work and this adds no policy.
     *
     * Failures rethrow rather than ending the response; see failed().
     *
     * @param string $class      The class to list.
     * @param mixed  $whereItems The items to filter on.
     * @param string $operator   The operator for the SQL. AND is default.
     * @param string $orderby    How to order the returned rows.
     *
     * @throws \Exception When the underlying query fails.
     *
     * @return array The rows, or an empty array. Never null, so a foreach
     *               over the result is always safe.
     */
    public static function getList(
        $class,
        $whereItems = false,
        $operator = 'AND',
        $orderby = 'name'
    ) {
        self::$_rethrowDepth++;
        try {
            // inputoverride stays true: an internal caller is not answering a
            // DataTables POST, and without it listem() would read pagination
            // out of php://input and silently page a result the caller asked
            // for in full.
            self::listem($class, $whereItems, true, $operator, $orderby);
            $data = self::$data;
            self::$data = '';
        } finally {
            self::$_rethrowDepth--;
        }
        if (is_array($data) && isset($data['data'])) {
            $rows = is_array($data['data']) ? $data['data'] : [];
        } else {
            $rows = is_array($data) ? $data : [];
        }
        return self::objectify($rows);
    }
    /**
     * Builds the object graph json_decode() would have produced, without the
     * encode/decode round-trip.
     *
     * The idiom these wrappers replace ends in json_decode(), so its rows
     * arrive as stdClass and every one of the ~190 call sites reads
     * `$row->field`. Handing back raw arrays would make migrating them a
     * rewrite rather than a swap, and a `$row->id` left behind on an array is
     * a null read, not an error -- the same silent shape as the envelope bug
     * this is meant to end.
     *
     * Mirrors json_decode()'s rule exactly: a list stays a list, an
     * associative array becomes stdClass, scalars pass through untouched. An
     * empty array stays an array, as json_decode('[]') does.
     *
     * @param mixed $value The value to convert.
     *
     * @return mixed
     */
    private static function objectify($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        // array_is_list() is PHP 8.1; the floor here is 7.4.
        $isList = $value === []
            || array_keys($value) === range(0, count($value) - 1);
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = self::objectify($item);
        }
        return $isList ? $out : (object)$out;
    }
    /**
     * Returns one entity directly as a PHP object.
     *
     * The indiv() counterpart of getIds(). indiv()'s payload is flat -- there
     * is no envelope on a single entity -- so this exists for the round-trip
     * and for the not-found behaviour rather than for unwrapping.
     *
     * Existence is established first with an id-only query, deliberately.
     * indiv() answers a row it cannot find with sendResponse(404), which
     * reaches breakHead() and exits, and that is the right answer at the HTTP
     * boundary. Rather than change it, this asks a cheap indexed question
     * first so the wrapper can return null the way its callers need. One
     * extra id-only query is the price of not making indiv() ambiguous.
     *
     * Failures rethrow rather than ending the response; see failed().
     *
     * @param string $class The class to fetch.
     * @param mixed  $id    The id to fetch.
     *
     * @throws \Exception When the underlying query fails.
     *
     * @return object|null The entity, or null when no such row exists or the
     *                     caller may not see it.
     */
    public static function getItem($class, $id)
    {
        self::$_rethrowDepth++;
        try {
            if (count(self::getIds($class, ['id' => $id])) === 0) {
                return null;
            }
            self::indiv($class, $id);
            $data = self::$data;
            self::$data = '';
        } finally {
            self::$_rethrowDepth--;
        }
        // getter() builds an associative array; hand back the object graph so
        // call sites read the same as the json_decode() form they replace.
        return self::objectify($data);
    }
    /**
     * Runs any router call as a value rather than as a response.
     *
     * getList() and getItem() cover listem() and indiv(), which is most of the
     * tree, and they drop the envelope because their callers only ever wanted
     * the rows. Some callers want the envelope: taskscheduler reads
     * `->recordsFiltered` off active() for its task count and then iterates
     * `->data`, so unwrapping would change what it does.
     *
     * This is the escape hatch for those, and for the helpers no wrapper
     * covers -- active(), names(), availablekernels(), logfiles(). It returns
     * exactly what `json_decode(Route::getData())` returned, envelope and all,
     * so adopting it is a swap with no semantic change at all. What it adds is
     * the part the daemons need: inside the callable, a failure raises instead
     * of ending the process.
     *
     *     $tasks = Route::asValue(function () { Route::active('scheduledtask'); });
     *     $tasks->recordsFiltered;   // reads exactly as before
     *
     * Prefer getList()/getItem() where they fit; they say more about intent
     * and they remove the envelope that has now caused three silent bugs.
     *
     * @param callable $call Makes the router call. Its return value is
     *                       ignored; the result is read from Route::$data,
     *                       which is how every helper reports.
     *
     * @throws \Exception Whatever the call raised, rather than exiting.
     *
     * @return mixed
     */
    public static function asValue(callable $call)
    {
        self::$_rethrowDepth++;
        try {
            $call();
            $data = self::$data;
            self::$data = '';
        } finally {
            self::$_rethrowDepth--;
        }
        return self::objectify($data);
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
            // Lockout guard. Only at the outermost call: the cascade below
            // re-enters deletemass() for each dependent table, and those
            // intermediate states are part of one operation that has
            // already been judged as a whole -- checking them individually
            // would refuse deletes that are actually fine.
            if (self::$_deleteDepth < 1) {
                Authorization::assertAdminRemainsAfterDelete(
                    $classname,
                    $itemIDs
                );
            }
            self::$_deleteDepth++;

            // Per-object destroy events.
            //
            // DESTROY_HOST and DESTROY_IMAGE used to be fired by
            // Host::destroy()/Image::destroy(), which meant they only ever
            // reached a listener on the UI path: Route::delete() funnels the
            // REST single-delete straight into here and deliberately never
            // builds the object, so the override -- and with it the event --
            // never ran. A plugin watching for a host being removed simply
            // did not hear about it when the host went out over the API.
            //
            // Firing here instead puts the announcement on the one path every
            // delete already shares, so it happens exactly once per object no
            // matter which door the delete came in. It is fired BEFORE the
            // switch below so a listener still sees the row and its
            // associations intact, which is the order destroy() used.
            //
            // Building an object per id is the whole cost of doing this, and
            // deletemass() is the mass path, so it is only paid when a hook is
            // actually registered. With nothing listening the event name is
            // still announced -- that is what records it in the hook catalog,
            // and is unchanged -- just without a payload nobody would read.
            //
            // Refs https://github.com/FOGProject/fogproject/issues/895
            $destroyEvents = [
                'host' => ['DESTROY_HOST', 'Host'],
                'image' => ['DESTROY_IMAGE', 'Image']
            ];
            if (isset($destroyEvents[$classname])) {
                list($destroyEvent, $destroyKey) = $destroyEvents[$classname];
                if (count($itemIDs ?: [])
                    && self::$HookManager->hasListeners($destroyEvent)
                ) {
                    foreach ((array) $itemIDs as $destroyID) {
                        $destroyObj = self::getClass($classname, $destroyID);
                        self::$HookManager->processEvent(
                            $destroyEvent,
                            [$destroyKey => &$destroyObj]
                        );
                        unset($destroyObj);
                    }
                } else {
                    self::$HookManager->processEvent($destroyEvent);
                }
            }

            switch ($classname) {
                case 'host':
                    $snapinjobIDs = ['jobID' => Route::getIds('snapinjob', ['hostID' => $itemIDs])];
                    $findWhere = ['hostID' => $itemIDs];
                    $removeItems = [
                        'nodefailure' => $findWhere,
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
                    $snapinjobIDs = Route::getIds(
                        'snapintask',
                        $findWhere,
                        'jobID'
                    );
                    $removeItems = [
                        'snapinassociation' => $findWhere,
                        'snapingroupassociation' => $findWhere
                    ];
                    // Drop this snapin's tasks HERE, inline, and not by adding
                    // 'snapintask' to $removeItems above.
                    //
                    // Two things depend on it, and both were broken:
                    //
                    //  - $removeItems is not processed until after this switch
                    //    returns, so a snapin deleted over the REST path left
                    //    its snapintask rows behind pointing at a snapin that
                    //    no longer exists. Snapin::destroy() deletes them, so
                    //    the UI path was clean and only the API orphaned them.
                    //  - The loop below cancels any queued job this snapin was
                    //    the last remaining task of, which it decides by
                    //    counting the tasks still on each job. With those tasks
                    //    still present every count came back non-zero, every
                    //    job hit the `continue`, and the cancel was unreachable
                    //    -- the job stayed queued forever against a deleted
                    //    snapin. Deleting first is what makes the count mean
                    //    "anything OTHER than what we just removed", which is
                    //    the order Snapin::destroy() already used.
                    //
                    // Refs https://github.com/FOGProject/fogproject/issues/885
                    Route::deletemass('snapintask', $findWhere);
                    $queuedStates = self::getQueuedStates();
                    $queuedStates[] = self::getProgressState();
                    $snapinjobIDs = Route::getIds(
                        'snapinjob',
                        [
                            'id' => $snapinjobIDs,
                            'stateID' => $queuedStates
                        ]
                    );
                    $sjIDs = [];
                    foreach ((array)$snapinjobIDs as &$sjID) {
                        $jobCount = self::getCount(
                            'snapintask',
                            ['jobID' => $sjID]
                        );
                        if ($jobCount) {
                            continue;
                        }
                        $sjIDs[] = $sjID;
                        unset($sjID);
                    }
                    if (count($sjIDs ?: [])) {
                        self::getClass('SnapinJobManager')->cancel($sjIDs);
                    }
                    break;
                case 'user':
                    $findWhere = ['userID' => $itemIDs];
                    $removeItems = [
                        'roleuserassociation' => $findWhere,
                        'usergroupmember' => $findWhere,
                        // A token outlives its owner as a WORKING credential
                        // if this is missed, which is why it goes here rather
                        // than in User::destroy(): destroy() is only the UI
                        // path, and the REST delete funnels to deletemass()
                        // without ever calling it. That split is what left
                        // orphans before (see the snapintask note below), and
                        // an orphaned API token is a live way in belonging to
                        // an account that no longer exists.
                        //
                        // APIToken::resolve() also refuses a token whose
                        // owner will not load, so a future miss here fails
                        // closed rather than authenticating as nobody. Both,
                        // deliberately: this is the fix and that is the net.
                        'apitoken' => $findWhere
                    ];
                    break;
                case 'role':
                    $findWhere = ['roleID' => $itemIDs];
                    $removeItems = [
                        'rolepermission' => $findWhere,
                        'roleuserassociation' => $findWhere,
                        'roleusergroupassociation' => $findWhere
                    ];
                    break;
                case 'usergroup':
                    $findWhere = ['usergroupID' => $itemIDs];
                    $removeItems = [
                        'usergroupmember' => $findWhere,
                        'roleusergroupassociation' => $findWhere
                    ];
                    break;
                case 'site':
                    // Deleting a site clears its four membership lists.
                    // Nothing stops the CATCH-ALL site being deleted here:
                    // it is an ordinary site carrying a flag, and refusing
                    // would be a rule the admin cannot see or undo. What it
                    // costs is real though -- every user who relied on it
                    // for blanket access falls back to their own sites, and
                    // a user with none then sees nothing.
                    $findWhere = ['siteID' => $itemIDs];
                    $removeItems = [
                        'sitehostmember' => $findWhere,
                        'siteusermember' => $findWhere,
                        'sitegroupmember' => $findWhere,
                        'siteusergroupmember' => $findWhere,
                        // ...and the two grant lists. A grant naming a
                        // site that no longer exists would keep putting
                        // its holders "in scope" for an id that resolves
                        // to nothing, which reads as deny-all.
                        'siterolegrant' => $findWhere,
                        'siteusergroupgrant' => $findWhere
                    ];
                    break;
                default:
                    $findWhere = [];
                    $removeItems = [];
            }

            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }

            // Core site membership cleanup. Added after the switch rather
            // than repeated across four of its cases, and before
            // DELETEMASS_API so a listener still sees the full map.
            //
            // A leftover membership row is not merely untidy. InnoDB
            // recomputes AUTO_INCREMENT as MAX(id)+1 on restart, so ids
            // are reused -- a row left behind by a deleted host can later
            // put an unrelated NEW host into the site the old one was in,
            // granting access nobody asked for.
            $siteMemberTables = [
                'host' => 'sitehostmember',
                'user' => 'siteusermember',
                'group' => 'sitegroupmember',
                'usergroup' => 'siteusergroupmember'
            ];
            if (isset($siteMemberTables[$classname])) {
                $removeItems[$siteMemberTables[$classname]] = [
                    $classname . 'ID' => $itemIDs
                ];
            }

            // The grant side of the same cleanup, and the same id-reuse
            // hazard with a sharper edge: a grant row left behind by a
            // deleted role can later put every holder of an unrelated NEW
            // role into the site the old one granted. Membership leaks one
            // object into a site; a stale grant leaks a whole population.
            //
            // Its own map rather than an entry in the one above because
            // that one derives the column as "{$classname}ID", and these
            // columns are grantroleID and grantusergroupID -- the "grant"
            // prefix being what keeps them distinct from the membership
            // sense of the same two ids.
            $siteGrantTables = [
                'role' => ['siterolegrant', 'grantroleID'],
                'usergroup' => ['siteusergroupgrant', 'grantusergroupID']
            ];
            if (isset($siteGrantTables[$classname])) {
                list($grantTable, $grantCol) = $siteGrantTables[$classname];
                $removeItems[$grantTable] = [$grantCol => $itemIDs];
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

            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby
            );

            return self::$DB->query($sqlResult['sql'], [], $sqlResult['params']);
        } catch (\Exception $e) {
            self::_sendCaught($e);
        } finally {
            // max() because the guard above can throw before the increment.
            self::$_deleteDepth = max(0, self::$_deleteDepth - 1);
        }
    }
    /**
     * Builds the sql query with the where.
     *
     * When $retWhere is false, returns ['sql' => string, 'params' => array]
     * where params contains named PDO placeholders (e.g. 'where_0' => value).
     * When $retWhere is true, returns only the WHERE clause string with values
     * safely escaped via PDO::quote() for use with the DataTables complex() path.
     *
     * @param string $sql        The sql string we need to adjust.
     * @param array  $classVars  The current class variables.
     * @param mixed  $whereItems The where items to build up.
     * @param bool   $retWhere   Only return where element.
     * @param string $operator   The logical operator between conditions.
     * @param string $orderby    How to order the returned values.
     *
     * @return string|array
     */
    /**
     * Narrows a REST list/search payload to the acting user's site scope.
     *
     * The web UI path re-lists with the in-scope ids, which keeps the row
     * counts honest. This path cannot: the payload has already been built
     * by FOGManagerController::complex() from a SQL statement assembled
     * from the request, so the boundary is applied to the rows that came
     * back. The counts are rewritten to what survived, because leaving the
     * unscoped totals in place would tell a site-restricted user how many
     * objects exist outside their scope.
     *
     * On a payload capped by MAX_ROWS (flagged `truncated`) the rows are
     * one page of the result set, so the rewritten count is a FLOOR on the
     * in-scope total rather than the total. The flag rides along and says
     * which of the two the consumer is holding. A true scoped count needs
     * the boundary pushed into the query's WHERE clause, which is a larger
     * change than this.
     *
     * @param string $classname the class being listed
     *
     * @return void
     */
    private static function _applySiteScope($classname)
    {
        $node = strtolower((string)$classname);
        if (!SiteScope::isScopedNode($node)) {
            return;
        }
        $scopeIDs = Authorization::scopedObjectIDs($node);
        if (null === $scopeIDs) {
            return;
        }
        // Snapshot by value rather than working through self::$data. The
        // scope lookups issue their own queries, and anything that touches
        // self::$data mid-method would otherwise blank out the payload
        // being filtered.
        $payload = self::$data;
        if (empty($payload['data']) || !is_array($payload['data'])) {
            return;
        }
        $allowed = array_flip(array_map('intval', $scopeIDs));
        $kept = [];
        foreach ($payload['data'] as $row) {
            $id = (int)(
                is_array($row) ?
                ($row['id'] ?? 0) :
                ($row->id ?? 0)
            );
            if (isset($allowed[$id])) {
                $kept[] = $row;
            }
        }
        if (count($kept) === count($payload['data'])) {
            self::$data = $payload;
            return;
        }
        // Reaching here means this filter REMOVED something, which since the
        // boundary moved into the query should not happen on the listem()
        // path -- the database was told to exclude those rows before it chose
        // the page. Removing them is still correct and this stays as the
        // fail-closed backstop; what is worth knowing is that the two
        // disagreed.
        //
        // Not every removal is a fault. search() runs this a second time
        // AFTER API_MASSDATA_MAPPING, and hooks receive `data` by reference,
        // so a plugin appending an out-of-scope row lands here by design --
        // which is also something an administrator should be able to find out
        // about.
        //
        // One line per list rather than per row, and through error() rather
        // than error_log(): a diagnostic for someone already looking, not a
        // condition to shout about on a server that is working.
        self::error(
            sprintf(
                'Route::_applySiteScope: removed %d of %d %s row(s) the query'
                . ' should already have excluded. Either the boundary did not'
                . ' reach the SQL, or something added rows after it ran.',
                count($payload['data']) - count($kept),
                count($payload['data']),
                $node
            )
        );
        $payload['data'] = array_values($kept);
        $payload['recordsFiltered'] = count($kept);
        $payload['recordsTotal'] = count($kept);
        self::$data = $payload;
    }
    /**
     * Drops credential settings that a grid search could only have matched
     * on their value.
     *
     * The grid half of what unisearch() does inline. A setting's value stays
     * searchable, because that is what searching settings is FOR -- finding
     * FOG_TFTP_PXE_KERNEL by "bzImage" is the everyday case and a key-only
     * search can never do it. What must not happen is a hit confirming a
     * substring of a value maskSensitiveSetting() has blanked: the row comes
     * back with an empty value, and its mere presence is the answer.
     *
     * Done here, on the rows, rather than as a NOT IN or NOT REGEXP in the
     * WHERE. An SQL-side exclusion needs isSensitiveSetting()'s rule --
     * pattern, include list, exempt list -- rewritten in another dialect,
     * and when the two drift nothing fails; the values simply become
     * findable again. Calling the predicate keeps one rule in one place.
     *
     * Three properties worth stating because each is load bearing:
     *
     *   - With NO search term this does nothing. A plain listing must still
     *     return every setting with its value masked, exactly as before.
     *   - A sensitive row matched on any VISIBLE field is kept. Searching
     *     "PASSWORD" should still find FOG_TFTP_FTP_PASSWORD; the key was
     *     never the secret.
     *   - recordsTotal is rewritten as well as recordsFiltered. Leaving the
     *     SQL count in place would answer the question the dropped row was
     *     dropped for.
     *
     * @param string $classname The entity listed.
     * @param array  $vars      The DataTables request body.
     *
     * @return void
     */
    private static function _applySettingValueScope(
        $classname,
        $vars,
        $whereItems = []
    ) {
        if ('setting' !== strtolower((string)$classname)) {
            return;
        }
        // ?filter=value=<guess> is the same oracle asked as an equality
        // rather than a substring, and it arrives by a different door:
        // handleWhereItems(), not the DataTables search box. It is not
        // refused per FIELD -- unfilterableFields() deliberately leaves
        // settings out so ?filter=value=/images keeps working -- so it is
        // answered per ROW here, like everything else in this function.
        //
        // Stricter than the search arm, and deliberately so. A search term
        // is ORed across the columns, so a sensitive row that matched on
        // its NAME is kept. Filter terms are ANDed, so `value=x&name=y`
        // matching on name does not make the value any less load bearing --
        // the row's presence still confirms the guess. Once a request
        // filters on value at all, every sensitive row goes.
        $valueFiltered = array_key_exists('value', (array)$whereItems);
        $terms = [];
        if ('' !== trim((string)($vars['search']['value'] ?? ''))) {
            $terms[] = (string)$vars['search']['value'];
        }
        foreach ((array)($vars['columns'] ?? []) as $col) {
            if ('' !== trim((string)($col['search']['value'] ?? ''))) {
                $terms[] = (string)$col['search']['value'];
            }
        }
        if (!count($terms) && !$valueFiltered) {
            return;
        }
        $payload = self::$data;
        if (empty($payload['data']) || !is_array($payload['data'])) {
            return;
        }
        $kept = [];
        foreach ($payload['data'] as $row) {
            $arr = (array)$row;
            if (!self::isSensitiveSetting((string)($arr['name'] ?? ''))) {
                $kept[] = $row;
                continue;
            }
            if ($valueFiltered) {
                continue;
            }
            // Every field EXCEPT the value, rather than a list of the four
            // this class has today, so a column added later is covered by
            // default instead of by remembering.
            $visible = false;
            foreach ($arr as $field => $cell) {
                if ('value' === $field || !is_scalar($cell)) {
                    continue;
                }
                foreach ($terms as $term) {
                    if (false !== stripos((string)$cell, $term)) {
                        $visible = true;
                        break 2;
                    }
                }
            }
            if ($visible) {
                $kept[] = $row;
            }
        }
        if (count($kept) === count($payload['data'])) {
            return;
        }
        $payload['data'] = array_values($kept);
        $payload['recordsFiltered'] = count($kept);
        $payload['recordsTotal'] = count($kept);
        self::$data = $payload;
    }
    /**
     * The site boundary for a read that is SERVING A REQUEST, or null.
     *
     * listem() carries the boundary unconditionally, because its row filter
     * always did. The single-purpose read routes -- names(), ids() -- cannot,
     * and the difference is not a matter of taste: getIds() and getNames()
     * are called from ~90 places in core and the services, and a daemon has
     * no FOGUser. Asking the boundary about a userless caller is answered
     * correctly and uselessly with '1=0' -- that user is in no site, so they
     * reach nothing -- and every replicator, scheduler and multicast manager
     * on a site-configured server would quietly stop finding its work.
     *
     * So the boundary applies to a request and not to a process. Same
     * predicate ids() already uses to decide whether it may answer 400 or
     * must return empty and log (sendResponse() exits, and a daemon that
     * exits is a systemd restart loop), which keeps one notion of "am I
     * serving a request" in this file rather than two.
     *
     * That predicate is load bearing. If PHP_SAPI ever stopped separating
     * these two worlds, this would fail OPEN -- a request-side caller would
     * be treated as a daemon and get no boundary. It is worth stating rather
     * than leaving to be discovered, and it is why the boundary belongs in
     * the query for a route that ALSO has an unauthenticated caller, instead
     * of a blanket filter over self::$data.
     *
     * @param string $classname the entity being read
     * @param string $idExpr    the object-id column, quoted
     *
     * @return string|null the WHERE fragment, or null for no boundary
     */
    private static function _requestScopeWhere($classname, $idExpr)
    {
        if ('cli' === PHP_SAPI) {
            return null;
        }
        return Authorization::scopedObjectWhere($classname, $idExpr);
    }
    /**
     * Builds the sql statement.
     *
     * @return array
     */
    private static function _buildSql(
        $sql,
        $classVars,
        $whereItems = '',
        $retWhere = false,
        $operator = 'AND',
        $orderby = 'name',
        $extraWhere = null
    ) {
        try {
            if (empty($operator)) {
                $operator = 'AND';
            }
            if (empty($orderby)) {
                $orderby = 'name';
            }

            $whereItems = self::handleWhereItems($whereItems);
            // If the caller passed any filter as an empty array, they mean
            // "value IN ()" — logically match nothing. Stripping the empty key
            // and letting the rest of the WHERE run would silently broaden
            // the query, returning rows the caller never asked for.
            $hadFilters = is_array($whereItems) && count($whereItems) > 0;
            $emptyArrayFilter = false;
            if ($hadFilters) {
                foreach ($whereItems as $v) {
                    if (is_array($v) && count($v) < 1) {
                        $emptyArrayFilter = true;
                        break;
                    }
                }
            }
            $whereItems = array_filter(
                $whereItems ?: [],
                function ($v) {
                    return !is_array($v) || count($v) > 0;
                }
            );

            // A key the class does not declare used to compile to an empty
            // column identifier -- `WHERE `` = :where_0` -- which the DB
            // rejects outright, so the caller got a failed query instead of
            // an answer, and deletemass() callers never noticed because the
            // return is not checked. Request-supplied keys are already
            // refused by handleWhereItems(), so reaching here means PHP code
            // named a field that does not exist: a programming error.
            //
            // Match nothing rather than drop the key, for the same reason as
            // the empty IN-set above -- dropping it broadens the query. Log
            // rather than throw: the catch at the foot of this method calls
            // sendResponse(), which exits, and in a CLI service that turns a
            // typo into a systemd restart loop (cf. 2d199fa4b).
            $unknownKeys = array_diff(
                array_keys($whereItems),
                array_keys((array)$classVars['databaseFields'])
            );
            if (count($unknownKeys) > 0) {
                self::error(
                    sprintf(
                        'Route::_buildSql: unknown filter field(s) for `%s`: %s',
                        $classVars['databaseTable'],
                        implode(', ', $unknownKeys)
                    )
                );
            }

            // Filters were supplied but nothing survived (an empty IN-set, or
            // a field the class does not declare) → match nothing.
            if ($hadFilters
                && ($emptyArrayFilter
                || count($unknownKeys) > 0
                || count($whereItems) < 1)
            ) {
                if ($retWhere) {
                    return '1=0';
                }
                $sql .= ' WHERE 1=0 ORDER BY `'
                    . (
                        isset($classVars['databaseFields'][$orderby]) ?
                        $classVars['databaseFields'][$orderby] :
                        $classVars['databaseFields']['id']
                    )
                    . '` ASC';
                return ['sql' => $sql, 'params' => []];
            }

            $params = [];
            if (count($whereItems) > 0) {
                $where = '';
                $paramIdx = 0;
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
                        if ($retWhere) {
                            $db = DatabaseManager::getLink();
                            $escaped = array_map([$db, 'quote'], $field);
                            $where .= ' IN (' . implode(',', $escaped) . ')';
                        } else {
                            $placeholders = [];
                            foreach ($field as $idx => $val) {
                                $pname = 'where_' . $paramIdx . '_' . $idx;
                                $placeholders[] = ':' . $pname;
                                $params[$pname] = $val;
                            }
                            $where .= ' IN (' . implode(',', $placeholders) . ')';
                        }
                    } else {
                        // No wildcard rewriting here. '*'/'+' are expanded at
                        // the request-facing entry points only (see
                        // expandSearchWildcards); a value assembled in PHP is
                        // matched literally, so a permission string like '*'
                        // or a path containing '+' cannot turn itself into a
                        // LIKE that matches far more than the caller meant. A
                        // caller wanting a pattern passes '%' explicitly.
                        $oper = false !== strpos((string)$field, '%')
                            ? 'LIKE'
                            : '=';
                        if ($retWhere) {
                            $db = DatabaseManager::getLink();
                            $where .= ' ' . $oper . ' ' . $db->quote($field);
                        } else {
                            $pname = 'where_' . $paramIdx;
                            $params[$pname] = $field;
                            $where .= ' ' . $oper . ' :' . $pname;
                        }
                    }
                    $paramIdx++;
                }
                $sql .= $where;
            }
            if ($retWhere) {
                return isset($where) ? $where : '';
            }
            // A condition the CALLER could not express as a filter -- today
            // only the site boundary, which is a subquery. ANDed after the
            // request's own filters so it can only ever narrow the result,
            // never widen it, whatever the caller passed.
            //
            // Not folded into $whereItems: those are field => value pairs
            // that get parameterised, and this is a fragment. Not applied
            // inside handleWhereItems() either, because that runs for
            // retWhere callers too and listem() already carries the boundary
            // by a different route.
            if (null !== $extraWhere && '' !== $extraWhere) {
                $sql .= (
                    count($whereItems) > 0 ?
                    ' AND ' :
                    ' WHERE '
                ) . $extraWhere;
            }
            $sql .= ' ORDER BY `'
                . (
                    isset($classVars['databaseFields'][$orderby]) ?
                    $classVars['databaseFields'][$orderby] :
                    $classVars['databaseFields']['id']
                )
                . '` ASC';

            return ['sql' => $sql, 'params' => $params];
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
            
            $whereItems = self::handleWhereItems($whereItems, $classname);
            if (count($whereItems ?: []) < 1) {
                $whereItems = self::getsearchbody($classname);
            }

            // Object scope, in the query -- see ids(). This route answered
            // the id and name of every object of the class to any caller
            // holding <entity>.view.
            $sqlResult = self::_buildSql(
                $sql,
                $classVars,
                $whereItems,
                false,
                $operator,
                $orderby,
                self::_requestScopeWhere(
                    $classname,
                    '`' . $classVars['databaseFields']['id'] . '`'
                )
            );
            $vals = self::$DB->query($sqlResult['sql'], [], $sqlResult['params'])->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            // Same value oracle listem() answers per row, and worse here:
            // this route returns only id and name, so there is no value for
            // maskSensitiveSetting() to strip and the row's mere presence IS
            // the whole answer. `?filter=value=<guess>` against a credential
            // key would confirm the guess outright.
            //
            // Dropped rather than refused because the name is right here in
            // the projection, so the real predicate can be asked. Only when
            // the request filtered on value at all -- a plain /setting/names
            // must still list every key.
            $dropSensitive = 'setting' === $classname
                && is_array($whereItems)
                && array_key_exists('value', $whereItems);
            foreach ($vals as &$val) {
                $name = $val[$classVars['databaseFields']['name']];
                if ($dropSensitive && self::isSensitiveSetting((string)$name)) {
                    unset($val);
                    continue;
                }
                $data[] = [
                    'id' => $val[$classVars['databaseFields']['id']],
                    'name' => $name
                ];
                unset($val);
            }

            self::$data = $data;
        } catch (\Exception $e) {
            self::_sendCaught($e);
        }
    }
    /**
     * Allows joining items.
     *
     * Static because runMatches() dispatches routed targets through
     * is_callable([__CLASS__, 'method']), which PHP 8 evaluates to false for
     * a non-static method reference. This was the only non-static target of
     * the 27 registered, so it was the only route that fell through to the
     * 501 at the bottom of runMatches() -- the join endpoint answered 501 for
     * every class and both verbs, and nothing it writes had ever executed
     * over HTTP.
     *
     * @param string $class The class to join items to.
     *
     * @return void
     */
    public static function joining($class)
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
                                    $c->addModule($vars->modules);
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
                                // isset(), like every sibling guard: a group
                                // join without an imageID otherwise emits an
                                // "Undefined property" warning, which would
                                // become a fatal under an ErrorException
                                // converter. Only reachable at all since the
                                // route stopped answering 501 (#919).
                                if (isset($vars->imageID)) {
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
                        $id = Route::getIds(
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
        } catch (\Exception $e) {
            self::_sendCaught($e);
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
    /**
     * Presents the kernel or initrd listing from github
     * @param array $data The data from github to parse.
     * @param string $type The type of data to parse, either kernel or initrd
     *
     * @return array The parsed data to present in the frontend
     */
    public static function kernelOrInitJson($data, $type)
    {
        if ($type != 'kernel' && $type != 'initrd') {
            return [];
        }
        $jsonData = [];
        foreach ($data as &$release) {
            if ($type == 'kernel') {
                $patt = '/Linux kernel (.*)?/';
            } elseif ($type == 'initrd') {
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
                    // Lowercased before matching. The feed carries a release
                    // named "EXPERIMENTAL test kernels for issue #108 (do not
                    // use in production)", and a case-sensitive 'Exp' test
                    // drops it into default -- so the one build that says in
                    // its own title not to use it is the one whose Type column
                    // comes out blank.
                    switch (strtolower(substr($release->name, 0, 3))) {
                        case 'fog':
                            $_fogParts = explode(' ', $release->name);
                            $k_hint = ' (FOG ' . ($_fogParts[1] ?? '?') . ')';
                            break;
                        case 'lat':
                            $k_hint = ' (devel)';
                            break;
                        case 'exp':
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
                    // $k_hint carries a leading space and parentheses from its
                    // old use as an inline label appended to a title; in a
                    // column of its own that is just noise.
                    $k_i_type = trim($k_hint, ' ()');
                    if ($k_i_type === '') {
                        // The default arm of the switch above means "prefix not
                        // recognised", which is an unknown, not a promise of
                        // stability -- so show the release's own name rather
                        // than an empty cell. It filters and sorts usefully too.
                        $k_i_type = $release->name;
                    }
                    // Escaped because this is text straight out of the GitHub
                    // feed and DataTables renders cell data as HTML.
                    $k_i_type = \Initiator::e($k_i_type);
                    if ($k_hint === ' (experimental)') {
                        // The badge replaces the plain text rather than sitting
                        // next to it -- appending one leaves the cell reading
                        // "experimental experimental". Plain type text is easy
                        // to skim past on a page whose whole purpose is picking
                        // the build every client will boot from.
                        $k_i_type = '<span class="badge bg-warning">'
                            . $k_i_type
                            . '</span>';
                    }
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
     * The five server facts this route publishes, in the order they are
     * returned. Also the exact key list the installer writes into
     * .fogsettings.pub -- keep the two in step.
     *
     * GH-1120 renamed these along with every other .fogsettings key, and the
     * response keys move with them rather than being mapped back. That is a
     * BREAKING change to /api/whoami, taken deliberately on the way to 1.6
     * stable: a shim would have frozen the old spellings into the API for the
     * life of the release, to keep a name like 'osid' that is itself a
     * second encoding of 'osname' and has already changed meaning once.
     *
     * @var array
     */
    const WHOAMI_KEYS = [
        'NET_fog_server_ip',
        'NET_hostname',
        'FOG_os_id',
        'FOG_os_name',
        'FOG_install_type'
    ];

    /**
     * Returns this server's own identity facts.
     *
     * Reads .fogsettings.pub, NOT .fogsettings. The latter holds the
     * $SVC_password and $DB_password cleartext credentials and is now 0600
     * root:root; this route reading it directly was the reason it had to be
     * world-readable, which meant every local account on the server could
     * read the database password and the fleet-wide replication FTP one.
     * The installer publishes just these five keys separately so the route
     * keeps working with the secrets shut away.
     *
     * Per server, deliberately, rather than from globalSettings: a storage
     * node serves this route too and its database is the MASTER's, so a
     * table-backed answer would have every node reporting the master's
     * hostname, IP and install type.
     *
     * @return void
     */
    public static function whoami()
    {
        $base = FOG_BASE_DIR . DS;
        $data = false;
        // .fogsettings is still tried, second, for the window where the web
        // tree has been updated but the installer has not been re-run --
        // copybacktrunk.sh and any other web-only deploy produce exactly
        // that. It is unreadable once the installer has run, so this costs
        // nothing on a fully updated server and stops the route going blank
        // on a partially updated one.
        //
        // GH-1120 narrows that safety net once: a server whose web tree is new
        // but whose installer has not re-run still has PRE-rename keys in
        // .fogsettings, which these names no longer match, so the route answers
        // with empty strings until the installer runs. Deliberate -- reading
        // both spellings would mean carrying the retired names in code that
        // exists only to cover a deploy ordering the installer fixes anyway.
        foreach (['.fogsettings.pub', '.fogsettings'] as $file) {
            if (!is_readable($base . $file)) {
                continue;
            }
            $parsed = @parse_ini_file($base . $file);
            if (is_array($parsed)) {
                $data = $parsed;
                break;
            }
        }
        // Answer with empty strings rather than dying when neither file can
        // be read. The old code extract()ed the parse result unchecked, so a
        // missing or unparsable file raised undefined-variable warnings and,
        // on a false return, a TypeError -- a 500 on a route whose whole job
        // is to let a client find out what it is talking to.
        self::$data = [];
        foreach (self::WHOAMI_KEYS as $key) {
            self::$data[$key] = is_array($data) && isset($data[$key])
                ? $data[$key]
                : '';
        }
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Route', 'Route');
