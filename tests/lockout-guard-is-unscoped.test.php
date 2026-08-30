<?php
/**
 * The lockout guard asks about the WHOLE install, never about the caller.
 *
 * Authorization::adminExistsGiven() decides whether an operation would
 * leave nobody able to administer FOG. It used to count users with
 * Route::getIds('user'), and that read is not neutral: when it is serving
 * a request, ids() bolts the CALLER'S object scope onto the query, and
 * `user` is a site-scoped node. A request with no signed-in user is
 * bounded to nothing at all -- scopedObjectWhere() answers the deny-all
 * fragment '1=0' -- so the guard counted zero users, concluded no
 * administrator would remain, and refused.
 *
 * That is not a corner case. The OIDC callback applies the provider's
 * group mappings BEFORE the session exists, so any sign-in whose mapping
 * dropped a role or a group membership refused itself:
 *
 *     {"error":"This would leave no account able to administer FOG."}
 *
 * and the account could never sign in again. A site-scoped administrator
 * reached the same wrong answer from the other side, seeing only their own
 * sites' users.
 *
 * The failure is silent in the direction that matters -- a guard that
 * cannot see anybody refuses everything, and the message it prints names
 * the wrong cause -- so this pins both halves: the count comes from the
 * users table directly, and the guard still refuses a change that really
 * would lock the install out.
 *
 * DB-free by the same means as site-scope-lists.test.php.
 *
 * Usage: php tests/lockout-guard-is-unscoped.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-lockout-guard-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);

if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
}

require_once $init;
new Initiator();

$fails = [];
$checks = 0;

function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

/** Minimal stand-in for PDODB; see site-scope-lists.test.php for rationale. */
class FakeDB
{
    public $error = false;
    public $log = [];
    private $_responder;
    private $_result;

    public function __construct(callable $responder)
    {
        $this->_responder = $responder;
    }

    public function query($sql, $a = [], $params = [])
    {
        $this->log[] = $sql;
        $this->_result = call_user_func($this->_responder, $sql, $params);
        return $this;
    }

    public function fetch($mode = null, $type = '')
    {
        return $this;
    }

    public function get($field = '')
    {
        return $this->_result;
    }
}

/** A hook manager with nothing registered, which is the stock install. */
class QuietHookManager
{
    public function processEvent($event, $arguments = [])
    {
        return;
    }
}

$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);
$hookProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hookProp->setAccessible(true);

/*
 * One administrator (user 1, through role 1) and one ordinary user. Any
 * table the guard reads and this scenario does not name answers empty, so
 * a read that starts going somewhere new shows up as a wrong answer rather
 * than as a silently absent row.
 */
$db = new FakeDB(
    function ($sql, $params) {
        if (false !== strpos($sql, 'FROM `users`')) {
            return [['uID' => 1], ['uID' => 2]];
        }
        if (false !== strpos($sql, 'FROM `roleUserAssoc`')) {
            return [['ruaRoleID' => 1, 'ruaUserID' => 1]];
        }
        if (false !== strpos($sql, 'FROM `rolePermissions`')) {
            return [['rpRoleID' => 1]];
        }
        return [];
    }
);
$dbProp->setValue(null, $db);
$hookProp->setValue(null, new QuietHookManager());

/**
 * adminExistsGiven(), or the error it died with.
 *
 * Wrapped because the read this test exists to keep out does not merely
 * answer wrongly here -- Route needs a booted router to answer at all, so
 * reintroducing it turns the guard into a fatal. Reported as a failed
 * check rather than left to kill the run, so the output names the cause.
 *
 * @param array $changes the proposed changes
 *
 * @return bool|string the answer, or the error message
 */
function guard(array $changes)
{
    try {
        return Authorization::adminExistsGiven($changes);
    } catch (\Throwable $e) {
        return 'threw: ' . $e->getMessage();
    }
}

/*
 * 1. The guard sees the administrator.
 */
check(
    'adminExistsGiven() cannot see the administrator in the users table',
    true === guard([]),
    $fails,
    $checks
);

/*
 * 2. It counted them by reading the table, and read nothing site-shaped on
 *    the way. Checking the queries and not just the boolean is the point:
 *    a routed read answers correctly for a caller who happens to be
 *    unbounded, so the boolean alone passes on the very install where the
 *    bug does not bite and fails on the customer's.
 */
$sawUsersTable = false;
$sawScope = false;
foreach ($db->log as $sql) {
    if (preg_match('#SELECT\s+`uID`\s+FROM\s+`users`#', $sql)) {
        $sawUsersTable = true;
    }
    if (false !== strpos($sql, 'siteUserMembers')
        || false !== strpos($sql, '1=0')
    ) {
        $sawScope = true;
    }
}
check(
    'adminExistsGiven() no longer reads the users table directly',
    $sawUsersTable,
    $fails,
    $checks
);
check(
    'adminExistsGiven() carried a site boundary into its own count',
    !$sawScope,
    $fails,
    $checks
);

/*
 * 3. And it still refuses. A guard that answers "yes, somebody remains" to
 *    everything is the same defect wearing the other face, so the negative
 *    cases matter as much as the positive one.
 */
check(
    'deleting the only administrator is no longer refused',
    false === guard(['excludeUsers' => [1]]),
    $fails,
    $checks
);
check(
    'deleting the role holding * is no longer refused',
    false === guard(['removeRoles' => [1]]),
    $fails,
    $checks
);
check(
    'stripping * from the only role holding it is no longer refused',
    false === guard(['rolePermissions' => [1 => []]]),
    $fails,
    $checks
);

/*
 * 4. The source-level half. The reads above can only be checked for what
 *    they DID; this checks what the method may not do at all, so a future
 *    edit that reintroduces a routed read is caught even if the scenario
 *    it is exercised under happens to be unbounded.
 */
$authFile = $webroot . '/src/Auth/Authorization.php';
$src = methodSource($authFile, 'adminExistsGiven');
if (null === $src) {
    $fails[] = 'Authorization::adminExistsGiven() is missing';
} else {
    check(
        'adminExistsGiven() reads through Route again; a routed read carries'
        . ' the caller\'s object scope, and a userless request is scoped to'
        . ' nothing',
        false === strpos($src, 'Route::'),
        $fails,
        $checks
    );
}

/**
 * Source text of one method, comments and whitespace stripped.
 *
 * Comments go first so the prose above a method -- which names the symbols
 * this test searches for -- cannot satisfy the search on its own. Same
 * helper as break-glass-auth-sources.test.php.
 *
 * @param string $file   path to read
 * @param string $method method name to find
 *
 * @return string|null code of the body, or null if not found
 */
function methodSource($file, $method)
{
    $t = token_get_all(file_get_contents($file));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || T_FUNCTION !== $t[$i][0]) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) {
            $j++;
        }
        if ($j >= $n || !is_array($t[$j]) || $t[$j][1] !== $method) {
            continue;
        }
        $depth = 0;
        $src = '';
        $started = false;
        for ($k = $j; $k < $n; $k++) {
            $c = $t[$k];
            if (is_array($c)
                && in_array($c[0], [T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            if (!is_array($c)) {
                if ('{' === $c) {
                    $depth++;
                    $started = true;
                } elseif ('}' === $c) {
                    if (0 === --$depth && $started) {
                        return preg_replace('#\s+#', '', $src);
                    }
                }
            }
            if ($started) {
                $src .= is_array($c) ? $c[1] : $c;
            }
        }
        return preg_replace('#\s+#', '', $src);
    }
    return null;
}

if (count($fails)) {
    fwrite(STDERR, "FAIL (" . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
