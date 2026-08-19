<?php
/**
 * The site boundary must reach the API, and the tri-state must not be
 * collapsed.
 *
 * The bug this guards: the site plugin's only filtering hook was
 * registered on HOST_DATA and GROUP_DATA, whose handlers switch on the
 * global $node/$sub the management pages set. Nothing under api/ fires
 * those events, so a site-restricted user saw their site in the grid and
 * every host on the server through /fog/host/list -- on the same
 * credentials, and without an API token, because Route skips API auth
 * entirely when a management session is already valid.
 *
 * Proven end to end against the 1.5 lab (2079 hosts, a user entitled to
 * 2) before this landed: list 126, search 68, names 2079, ids 2079 for
 * the restricted user, identical to the administrator's. After: 2 on
 * every route, the administrator unchanged, and a restricted user
 * belonging to NO site gets 0 rather than 2079. The driver is
 * scripts/background_scripts/probe_site_api_scope.php.
 *
 * Static by design. Route extends FOGBase, so exercising these paths for
 * real needs the full bootstrap, a database, the site plugin installed
 * and a restricted user -- that is the probe's job. What runs anywhere,
 * including in the pre-commit hook, is the shape: that each read route
 * still consults the boundary, that the dispatcher still gates
 * per-object routes, that nobody has rewritten a `null ===` test into a
 * falsy one, and that the membership rule is stated once.
 *
 * Usage: php tests/site-api-scope.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */

$root = rtrim($argv[1] ?? dirname(__DIR__) . '/packages/web', '/');
if (!is_dir($root)) {
    fwrite(STDERR, "FAIL: no such directory: $root\n");
    exit(1);
}

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

function readFile_($path, array &$failures, &$checks)
{
    $src = @file_get_contents($path);
    check('readable: ' . basename($path), false !== $src, $failures, $checks);
    return (string)$src;
}

/**
 * The body of a named method, brace-matched from its signature. Needed
 * because "the file mentions _scopeIDs somewhere" is not the assertion --
 * "listem() consults it" is, and a file-wide grep passes even after the
 * call is deleted from the one method that mattered.
 */
function methodBody($src, $name)
{
    $sig = strpos($src, 'function ' . $name . '(');
    if (false === $sig) {
        return '';
    }
    $open = strpos($src, '{', $sig);
    if (false === $open) {
        return '';
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return '';
}

$route = readFile_($root . '/lib/router/route.class.php', $failures, $checks);
$site = readFile_($root . '/lib/plugins/site/class/site.class.php', $failures, $checks);
$apiHook = readFile_(
    $root . '/lib/plugins/site/hooks/addsiteapi.hook.php',
    $failures,
    $checks
);
$uiHook = readFile_(
    $root . '/lib/plugins/site/hooks/addsitefiltersearch.hook.php',
    $failures,
    $checks
);

/*
 * 1. Every route that answers "which objects are there" consults the
 *    boundary. listem() and search() build objects and filter the rows;
 *    names() and ids() only ever produce ids, so they fold it into the
 *    WHERE instead. listdetails() is not listed because it delegates to
 *    listem() -- and that delegation is asserted, so it cannot quietly
 *    stop delegating.
 */
foreach (['listem', 'search'] as $fn) {
    check(
        "$fn() consults the object boundary",
        false !== strpos(methodBody($route, $fn), '_scopeIDs('),
        $failures,
        $checks
    );
}
foreach (['names', 'ids'] as $fn) {
    check(
        "$fn() folds the object boundary into its WHERE",
        false !== strpos(methodBody($route, $fn), '_scopeWhereItems('),
        $failures,
        $checks
    );
}
check(
    'listdetails() still delegates to listem(), which carries the boundary',
    false !== strpos(methodBody($route, 'listdetails'), 'self::listem('),
    $failures,
    $checks
);

/*
 * 2. The per-object routes -- indiv, update, delete, task, cancel -- are
 *    gated once at dispatch, next to the uType check, for the same reason
 *    that one is there: one place to audit.
 */
check(
    'runMatches() gates per-object routes on the boundary',
    false !== strpos(methodBody($route, 'runMatches'), '_requireObjectScope('),
    $failures,
    $checks
);

/*
 * 3. The tri-state. null means "no boundary", an ARRAY narrows, and an
 *    EMPTY array is a real answer meaning "nothing". `if (!$scope)` and
 *    `empty($scope)` are true for both null and array(), so either one
 *    shows every host to the one user entitled to none -- the failure is
 *    silent, it looks like the feature working, and it is the whole
 *    reason these tests exist. Every test against a scope value must be
 *    an explicit null comparison.
 */
$looseScope = [];
if (preg_match_all(
    '/(?:if\s*\(\s*!\s*\$scope\b|empty\s*\(\s*\$scope\s*\)|if\s*\(\s*\$scope\s*\))/',
    $route,
    $m
)) {
    $looseScope = $m[0];
}
check(
    'route.class.php tests $scope against null, never for falsiness'
    . (count($looseScope) ? ' (found: ' . implode(', ', $looseScope) . ')' : ''),
    count($looseScope) < 1,
    $failures,
    $checks
);
// Counted, not merely present. The regex above catches `if (!$scope)`
// but not `if ($scope && ...)` spanning two lines, and the whole failure
// mode here is a comparison quietly losing its `null` half.
check(
    'the row filters compare against null exactly twice (listem, search)',
    2 === preg_match_all('/null !== \$scope/', $route, $mn),
    $failures,
    $checks
);
check(
    'the WHERE narrowing and the dispatch gate each short-circuit on null',
    2 === preg_match_all('/null === \$scope/', $route, $mn2),
    $failures,
    $checks
);
check(
    '_scopeIDs() distinguishes an array from null rather than from empty',
    false !== strpos(methodBody($route, '_scopeIDs'), 'is_array($ids)'),
    $failures,
    $checks
);
check(
    'the deny-all branch returns an empty ARRAY, not null',
    (bool)preg_match(
        '/count\(\$siteIDs\)\s*<\s*1\s*\)\s*\{\s*return\s+array\(\)\s*;/',
        $site
    ),
    $failures,
    $checks
);
check(
    'scopedObjectIDs() returns null only for an unbounded class or user',
    2 === preg_match_all('/\n\s*return null;/', $site, $m2),
    $failures,
    $checks
);

/*
 * 4. An empty intersection must reach _buildWhere() as an empty array,
 *    because that is what compiles to `WHERE 1=0`. Dropping the term
 *    instead -- which is what happens if the caller "tidies up" an empty
 *    filter -- returns the whole table.
 */
check(
    '_buildWhere() still compiles an empty IN set to 1=0',
    false !== strpos(methodBody($route, '_buildWhere'), "return ' WHERE 1=0';"),
    $failures,
    $checks
);

/*
 * 5. The plugin answers the event, and answers it from the same rule the
 *    management pages use. Two statements of "who may see what" is a
 *    boundary that is decorative the first time they disagree, which is
 *    why the UI hook's helpers were made to delegate rather than copied.
 */
check(
    'the site plugin registers on API_SCOPE_IDS',
    false !== strpos($apiHook, "'API_SCOPE_IDS'"),
    $failures,
    $checks
);
check(
    'the API hook asks Site for the boundary rather than restating it',
    false !== strpos($apiHook, 'Site::scopedObjectIDs('),
    $failures,
    $checks
);
check(
    'the API hook applies no boundary when nobody is logged in (daemons)',
    false !== strpos($apiHook, 'self::$FOGUser->isValid()'),
    $failures,
    $checks
);
// SiteHostAssociation is read twice in the management hook and only one
// of those is the boundary. The grid shows each row's site name, which is
// a per-host lookup by hostID and stays. What must not come back is a
// lookup keyed on siteID -- that is the membership question, and Site
// answers it for both paths now.
check(
    'the management hook asks no membership question of its own',
    !preg_match("/'siteID'\s*=>/", $uiHook),
    $failures,
    $checks
);
check(
    'Site owns the SiteHostAssociation membership lookup',
    false !== strpos($site, "'SiteHostAssociation'"),
    $failures,
    $checks
);
foreach (
    [
        'SiteUserRestriction' => 'Site::userIsRestricted()',
        'SiteUserAssociation' => 'Site::userSiteIDs()',
        'GroupAssociation' => 'Site::groupIDsForSites()'
    ] as $table => $owner
) {
    check(
        "the management hook reads $table through $owner, not its own query",
        false === strpos($uiHook, "'$table'"),
        $failures,
        $checks
    );
    check(
        "Site owns the $table lookup",
        false !== strpos($site, "'$table'"),
        $failures,
        $checks
    );
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
echo "ok  $checks checks passed\n";
