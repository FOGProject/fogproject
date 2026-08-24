<?php
/**
 * The API token pane: a real grid, a scoped one, and named tokens.
 *
 * Three defects found by using the page rather than reading it, and the
 * checks below are shaped by what each of them cost.
 *
 *  1. SCOPE. visibleTo(), visibleToken() and the issue endpoint each asked
 *     SiteScope::isUnscoped() directly. Core declines to narrow for THREE
 *     reasons -- the caller holds '*', no sites are in use, or the caller
 *     is in a catch-all site -- and only the last is a SiteScope question.
 *     So an administrator holding '*' who did not happen to reach a
 *     catch-all site was narrowed on this page alone, while every other
 *     page in FOG showed them everything: the issue dropdown listed every
 *     user and the endpoint answered "No such user" for all of them. The
 *     boundary now comes from Authorization::scopedObjectIDs(), whose
 *     tri-state is INVERTED from the old one -- null means no boundary,
 *     and an empty array means sees-nothing -- so the two can never again
 *     be confused.
 *  2. UNIQUENESS WITHOUT AN INDEX. A token name is required and unique per
 *     user. It is NOT enforced by a UNIQUE index, and that is the
 *     load-bearing decision here: FOGController::save() writes with
 *     INSERT ... ON DUPLICATE KEY UPDATE, so a unique index would not
 *     reject a duplicate -- it would UPDATE the existing row and replace a
 *     live credential's hash, revoking a working token with no audit row
 *     saying so.
 *  3. THE DELETE MODAL. $.deleteSelected drives $.reAuth, which calls
 *     modal('show') on $('#deleteModal'). process() renders that modal only
 *     when the sub is 'list', and this pane's is not -- so with
 *     FOG_DELETE_REAUTH on, delete would open nothing, do nothing and log
 *     nothing. A silent no-op, which is the failure class this codebase
 *     keeps paying for.
 *
 * Usage: php tests/apitoken-grid-and-scope.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('apitoken-grid-and-scope');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$mgrSrc = file_get_contents($web . '/lib/fog/apitokenmanager.class.php');
$tokSrc = file_get_contents($web . '/lib/fog/apitoken.class.php');
$cfgSrc = file_get_contents($web . '/lib/pages/fogconfigurationpage.page.php');
$usrSrc = file_get_contents($web . '/lib/pages/usermanagement.page.php');
$authSrc = file_get_contents($web . '/lib/fog/authorization.class.php');
$jsSrc = file_get_contents(
    $web . '/management/js/fog/about/fog.about.apitokens.js'
);
$sysSrc = file_get_contents($web . '/lib/fog/system.class.php');

// Every source check runs against a comment-stripped copy: the comments
// here deliberately name the very things being searched for, so without
// this a gate would pass on its own documentation.
$strip = function ($src) {
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    return preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
};
$mgr = $strip($mgrSrc);
$tok = $strip($tokSrc);
$cfg = $strip($cfgSrc);
$usr = $strip($usrSrc);
$js = $strip($jsSrc);

// ---------------------------------------------------------------------------
// 1. Scope comes from Authorization, not SiteScope.
// ---------------------------------------------------------------------------
$t->check(
    'the manager no longer calls SiteScope directly',
    false === strpos($mgr, 'SiteScope::')
);
$t->check(
    'visibleTo() takes its boundary from Authorization::scopedObjectIDs',
    (bool)preg_match(
        "/\\\$ids = Authorization::scopedObjectIDs\('user', \\\$actingUserID\)/",
        $mgr
    )
);
$t->check(
    'null is treated as "no boundary", not as "sees nothing"',
    (bool)preg_match("/if \(null !== \\\$ids\) \{/", $mgr)
    && (bool)preg_match("/if \(null === \\\$ids\) \{\s*return true;/s", $mgr)
);
$t->check(
    'an EMPTY array still denies -- it is an enumeration, not silence',
    (bool)preg_match("/if \(count\(\\\$ids\) < 1\) \{\s*return \[\];/s", $mgr)
);
$t->check(
    'there is ONE statement of the boundary, reused',
    (bool)preg_match("/public function userInScope\(/", $mgr)
    && 2 === preg_match_all('/scopedObjectIDs/', $mgr)
);
$t->check(
    'visibleToken() goes through it rather than repeating it',
    (bool)preg_match(
        "/if \(!\\\$this->userInScope\(\(int\)\\\$token->get\('userID'\), "
        . "\\\$actingUserID\)\)/",
        $mgr
    )
);
$t->check(
    'the issue endpoint goes through it too, instead of an inline copy',
    (bool)preg_match("/->userInScope\(\\\$forUserID,/", $cfg)
    && false === strpos($cfg, 'SiteScope::isUnscoped')
);

// ---------------------------------------------------------------------------
// 2. Names: required, unique, and NOT via a unique index.
// ---------------------------------------------------------------------------
$t->check(
    'generate() refuses an empty name',
    (bool)preg_match(
        "/\\\$name = trim\(\(string\)\\\$name\);\s*if \('' === \\\$name\) \{\s*"
        . "return false;/s",
        $tok
    )
);
$t->check(
    'generate() refuses a duplicate with its own sentinel, not false',
    (bool)preg_match("/const DUPLICATE_NAME = /", $tok)
    && (bool)preg_match(
        "/if \(self::nameTaken\(\\\$userID, \\\$name\)\) \{\s*"
        . "return self::DUPLICATE_NAME;/s",
        $tok
    )
);
$t->check(
    'the duplicate check is case- and whitespace-insensitive',
    (bool)preg_match('/LOWER\(TRIM\(`atName`\)\) = LOWER\(:name\)/', $tok)
);
$t->check(
    'it is scoped to one user, not the whole server',
    (bool)preg_match('/WHERE `atUserID` = :uid/', $tok)
);
// The decision this file exists to protect. Adding the "obvious" index
// would turn a refusal into a silent overwrite of a live credential.
$t->check(
    'NO unique index on the token name is added anywhere',
    !preg_match('/UNIQUE.{0,80}atName/is', file_get_contents($web . '/commons/schema.php'))
    && !preg_match('/UNIQUE.{0,80}atName/is', file_get_contents($web . '/commons/schema-expected.php'))
);
$t->check(
    'both issue endpoints reject an empty name server-side',
    2 === preg_match_all(
        "/Give the token a name saying what it is for/",
        $cfgSrc . $usrSrc
    )
);
$t->check(
    'both issue endpoints handle DUPLICATE_NAME distinctly from false',
    (bool)preg_match("/APIToken::DUPLICATE_NAME === \\\$token/", $cfg)
    && (bool)preg_match("/APIToken::DUPLICATE_NAME === \\\$token/", $usr)
);

// ---------------------------------------------------------------------------
// 3. It is a real grid, with the modal its delete path needs.
// ---------------------------------------------------------------------------
$t->check(
    'the pane renders through process(), like every other list',
    (bool)preg_match("/\\\$this->process\(\s*12,\s*'dataTable',/s", $cfg)
);
$t->check(
    'it declares its columns as headerData rather than hand-built <th>',
    (bool)preg_match("/\\\$this->headerData = \[/", $cfg)
    && false === strpos($cfg, '<table class="table table-sm"')
);
$t->check(
    'the grid is wired with registerTable',
    (bool)preg_match("/\\\$\('#dataTable'\)\.registerTable\(/", $js)
);
$t->check(
    'its rows come from the pane\'s own endpoint, not Route::listem',
    false !== strpos($js, 'sub=apitokenlist')
    && (bool)preg_match('/public function apitokenlistPost\(\)/', $cfg)
    && false === strpos($cfg, "Route::listem('apitoken'")
);
$t->check(
    'the enabled column sorts on the raw value, not on badge markup',
    (bool)preg_match(
        "/if \(type !== 'display'\) \{\s*return data;/s",
        $js
    )
);
$t->check(
    'delete reuses the shared helper, pointed at the token endpoint',
    (bool)preg_match("/\\\$\.deleteSelected\(table,/", $js)
    && false !== strpos($js, 'sub=apitokendelete')
);
$t->check(
    'the re-auth modal $.reAuth needs is rendered by the pane',
    (bool)preg_match("/'deleteModal',/", $cfg)
    && (bool)preg_match("/'confirmDeleteModal',/", $cfg)
    && (bool)preg_match("/'deletePassword'/", $cfg)
);
$t->check(
    'enable and disable are one endpoint driven by a flag',
    (bool)preg_match('/public function apitokenenablePost\(\)/', $cfg)
    && (bool)preg_match("/'enabled': enabled \? 1 : 0|enabled: enabled \? 1 : 0/", $js)
);
$t->check(
    'bulk buttons start disabled and follow the selection',
    (bool)preg_match('/disableButtons\(true\);/', $js)
    && (bool)preg_match(
        '/disableButtons\(selected\.count\(\) === 0\)/',
        $js
    )
);
$t->check(
    'the grid reloads when the token modal is DISMISSED',
    (bool)preg_match(
        "/freshModal\.on\('hidden\.bs\.modal', function\(\) \{[^}]*"
        . "table\.ajax\.reload/s",
        $js
    )
);
$t->check(
    'the plaintext is cleared out of the DOM on dismiss',
    (bool)preg_match(
        "/freshModal\.on\('hidden\.bs\.modal', function\(\) \{\s*"
        . "\\\$\('#fresh-token-value'\)\.val\(''\);/s",
        $js
    )
);

// ---------------------------------------------------------------------------
// 3b. Every POST-only sub has a BASE method to be dispatched through.
//
// FOGPageManager::render() looks up the base method FIRST and rewrites
// $method to 'index' when it is missing -- so a sub implemented only as
// fooPost() never reaches the .Post test and renders the node's default
// page instead. On this node that is version(), so the endpoint answered
// HTTP 200 with the version card: no token, no error, a toast that said
// nothing, nothing logged. issueAPITokenFor shipped that way in #1329 and
// its Issue button had never once worked.
//
// Asserted by REFLECTION, not by grepping for the word 'function'. The
// question is exactly the one the dispatcher asks.
// ---------------------------------------------------------------------------
$cfgPage = $web . '/lib/pages/fogconfigurationpage.page.php';
$postSubs = [];
if (preg_match_all(
    '/public function ([A-Za-z][A-Za-z0-9_]*)Post\(/',
    file_get_contents($cfgPage),
    $m
)) {
    $postSubs = $m[1];
}
$t->check(
    'the page does declare POST-only subs (guard against an empty sweep)',
    count($postSubs) > 3
);
$missing = [];
foreach ($postSubs as $sub) {
    if (!preg_match(
        '/public function ' . preg_quote($sub, '/') . '\(/',
        file_get_contents($cfgPage)
    )) {
        $missing[] = $sub;
    }
}
$t->check(
    'every *Post sub on this page has a base method: '
    . (count($missing) ? implode(', ', $missing) : 'none missing'),
    count($missing) < 1
);
$t->check(
    'the read anchor answers rather than refusing -- it is a read',
    (bool)preg_match(
        '/public function apitokenlist\(\)\s*\{\s*'
        . '\$this->apitokenlistPost\(\);/s',
        $cfg
    )
);
$t->check(
    'every mutating anchor refuses the verb instead of doing the work',
    5 === preg_match_all('/\$this->_postOnly\(\);/', $cfg)
);
$t->check(
    'refusing means 405, not a silent 200',
    (bool)preg_match(
        '/HTTPResponseCodes::HTTP_METHOD_NOT_ALLOWED/',
        $cfg
    )
);

// ---------------------------------------------------------------------------
// 4. Each new sub carries its own permission.
// ---------------------------------------------------------------------------
foreach ([
    'apitokenlist' => 'apitoken.view',
    'apitokenenable' => 'apitoken.edit',
    'apitokendelete' => 'apitoken.delete'
] as $sub => $perm) {
    $t->check(
        sprintf('sub %s is gated on %s', $sub, $perm),
        (bool)preg_match(
            "/'" . preg_quote($sub, '/') . "' => '"
            . preg_quote($perm, '/') . "'/",
            $authSrc
        )
    );
}
// ---------------------------------------------------------------------------
// 5. The per-user Bearer card is the same grid, scoped to one account.
//
// It was a hand-built <table> of checkboxes posted through the user's API
// tab form -- no sort, no search, and sharing a POST target with the legacy
// uAPIToken card, which is what made an absent checkbox read as "unticked"
// and risked wiping uAPIToken as a side effect (the GH-987 class).
// ---------------------------------------------------------------------------
$usrJs = file_get_contents(
    $web . '/management/js/fog/user/fog.user.edit.js'
);
$usrJsStripped = $strip($usrJs);

$t->check(
    'the per-user card is a DataTable',
    false !== strpos($usrJsStripped, "$('#user-apitoken-table').registerTable(")
    && false !== strpos($usr, 'id="user-apitoken-table"')
);
$t->check(
    'it uses its OWN table id, not the shared one',
    false === strpos($usr, "'dataTable'")
);
$t->check(
    'the card no longer posts through the tab form',
    false === strpos($usr, 'name="tokenaction"')
    && false === strpos($usr, 'tokenenabled[]')
    && false === strpos($usr, 'tokendelete[]')
);
$t->check(
    'and its discriminator handler is gone with it',
    false === strpos($usr, '_userBearerTokensPost')
);
$t->check(
    'delete passes the owner id to revokeMany',
    (bool)preg_match(
        '/revokeMany\(\s*array_map[^;]*\(int\)\$this->obj->get\(.id.\)/s',
        $usr
    )
);
$t->check(
    'enable passes the owner id to setEnabledMany',
    (bool)preg_match(
        '/setEnabledMany\(\s*array_map[^;]*\(int\)\$this->obj->get\(.id.\)/s',
        $usr
    )
);
$t->check(
    'the central pane deliberately passes NO owner id',
    (bool)preg_match(
        '/revokeMany\(\s*array_map\(.intval., \(array\)\(\$_POST\[.remitems.\] \?\? \[\]\)\),\s*\(int\)self::\$FOGUser->get\(.id.\)\s*\)/s',
        $cfg
    )
);
// The narrowing itself, in the manager. Losing it turns the per-user card
// into a server-wide one for anybody holding user.edit.
$t->check(
    '_resolve() skips a token whose owner is not the requested one',
    (bool)preg_match(
        "/if \(null !== \\\$ownerID\s*&& \(int\)\\\$token->get\('userID'\) !== \\\$ownerID\s*\) \{\s*continue;/s",
        $mgr
    )
);
$t->check(
    'and still resolves every id through visibleToken()',
    (bool)preg_match(
        "/\\\$token = \\\$this->visibleToken\(\(int\)\\\$id, \\\$actingUserID\);/",
        $mgr
    )
);
$t->check(
    'the list endpoint filters to the account being edited',
    (bool)preg_match(
        "/if \(\\\$token\['userID'\] !== \\\$uid\) \{\s*continue;/s",
        $usr
    )
);
$t->check(
    'its POST-only subs have base methods too',
    (bool)preg_match('/public function userAPITokenDelete\(\)/', $usr)
    && (bool)preg_match('/public function userAPITokenEnable\(\)/', $usr)
    && (bool)preg_match('/public function userAPITokenList\(\)/', $usr)
);
foreach ([
    'userapitokenlist' => 'apitoken.view',
    'userapitokenenable' => 'apitoken.edit',
    'userapitokendelete' => 'apitoken.delete',
    'issueapitoken' => 'apitoken.create'
] as $sub => $perm) {
    $t->check(
        sprintf('user sub %s is gated on %s', $sub, $perm),
        (bool)preg_match(
            "/'" . preg_quote($sub, '/') . "' => '"
            . preg_quote($perm, '/') . "'/",
            $authSrc
        )
    );
}
$t->check(
    'the tab grid is paged, not virtual-scrolled (it inits hidden)',
    (bool)preg_match('/scroller: false/', $usrJsStripped)
);
$t->check(
    'and re-measures when the tab becomes visible',
    (bool)preg_match(
        "/shown\.bs\.tab[^}]*columns\.adjust\(\)/s",
        $usrJsStripped
    )
);
$t->check(
    'the plaintext is cleared from the DOM when its modal is dismissed',
    (bool)preg_match(
        "/freshModal\.on\('hidden\.bs\.modal'[^}]*"
        . "\\$\('#apitoken-fresh-value'\)\.val\(''\)/s",
        $usrJsStripped
    )
);

$t->check(
    'FOG_BCACHE_VER is at least 309',
    (bool)preg_match("/define\('FOG_BCACHE_VER', (\d+)\)/", $sysSrc, $m)
    && (int)$m[1] >= 309
);

$t->finish();
