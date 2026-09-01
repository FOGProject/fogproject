<?php
/**
 * The bulk "Add to Group" endpoint is authenticated, CSRF-gated, bounded to
 * the caller's sites, and asks for the narrow permission.
 *
 * `host&sub=saveGroup` is the handler behind the host list's Add to Group
 * modal. Three separate gates were missing from it, all three verified
 * against a running 1.6 server before this test was written (ADR 0038,
 * proposal §5 UNKNOWN-6):
 *
 *   1. NO CSRF CHECK. The router's gate keys off a `*Post`/`*Ajax` method
 *      suffix and this handler has neither, so a session cookie alone was
 *      enough from any origin. The control that shows this is not a quirk of
 *      the test: `deployMultiPost`, the same request shape on the same page,
 *      answered 403 to the identical unheadered request while this answered
 *      202.
 *   2. NO OBJECT SCOPE. Neither the posted host ids nor the posted group ids
 *      were bounded, so a site-scoped operator could add any host on the
 *      server to any group on the server.
 *   3. THE WRONG PERMISSION. SUB_OVERRIDES pointed it at `group.create`.
 *      Adding existing hosts to existing groups is an edit, and once a group
 *      is the labeling primitive (ADR 0038 decision 16) requiring the
 *      permission to MINT groups in order to APPLY one is backwards.
 *
 * The permission half is executed rather than read: resolvePagePermission()
 * is what the router actually calls, so this pins the resolution and not the
 * constant it happens to consult. Both verbs are also checked against
 * coreRegistry(), because the fix is only safe if it uses vocabulary that
 * already exists -- a bespoke sixth verb on the `group` node would leave
 * every existing role without it and break labeling on upgrade for
 * everybody.
 *
 * The three call sites cannot be executed without a session and a database,
 * so they are pinned POSITIONALLY: each must appear inside saveGroup() and
 * BEFORE its try block. A grep for the bare symbol would pass on
 * `if (false && ...)` or on a gate that had drifted into the catch, which is
 * the failure this shape is chosen to avoid.
 *
 * DB-free, like usertracking-permission-split.test.php: the Initiator
 * constructor only registers the autoloader, and everything executed here is
 * a class constant or a pure resolver.
 *
 * Usage: php tests/savegroup-is-gated.test.php
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

$tmp = sys_get_temp_dir() . '/fog-savegroup-gated-test-' . getmypid();
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

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

if (!class_exists(\FOG\Auth\Authorization::class, true)) {
    fwrite(STDERR, "FAIL: Authorization did not resolve\n");
    exit(1);
}

/**
 * resolvePagePermission() reaches exemptNodes() and registry(), both of
 * which fire hook events, and a CLI harness has no HookManager. Same stub
 * as api-server-owned-fields.test.php: the events carry nothing this test
 * depends on, so a no-op listener registry is the whole requirement.
 */
class SaveGroupStubHooks
{
    public function processEvent($event, $arguments = [])
    {
    }
}

$hooks = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'HookManager');
$hooks->setAccessible(true);
$hooks->setValue(null, new SaveGroupStubHooks());

/*
 * 1. The permission the ROUTER resolves, exercised. group.edit is the floor
 *    for every call; group.create is the handler's business because it
 *    depends on the request body.
 */
$perm = Authorization::resolvePagePermission('host', 'savegroup');
check(
    "resolvePagePermission('host','savegroup') is 'group.edit' (got '$perm')",
    'group.edit' === $perm,
    $failures,
    $checks
);
check(
    'and is no longer group.create',
    'group.create' !== $perm,
    $failures,
    $checks
);

/*
 * 2. Both verbs already exist on the group node. If either did not, the fix
 *    would be inventing registry vocabulary, and every role on an upgraded
 *    server would lack it.
 */
$core = Authorization::coreRegistry();
$groupActions = (array)($core['group'] ?? []);
foreach (['edit', 'create'] as $action) {
    check(
        "coreRegistry()['group'] declares '$action'",
        in_array($action, $groupActions, true),
        $failures,
        $checks
    );
}

/*
 * 3. The three gates, inside saveGroup() and ahead of its try block.
 */
$src = (string)file_get_contents($webroot . '/src/Pages/HostManagement.php');
$start = strpos($src, 'public function saveGroup()');
check(
    'HostManagement declares saveGroup()',
    false !== $start,
    $failures,
    $checks
);

$body = '';
if (false !== $start) {
    $next = strpos($src, "\n    public function ", $start + 10);
    $body = substr(
        $src,
        $start,
        (false === $next ? strlen($src) : $next) - $start
    );
}

$tryAt = strpos($body, 'try {');
check(
    'saveGroup() still has its try block',
    false !== $tryAt,
    $failures,
    $checks
);

$gates = [
    'authenticates and checks CSRF' => 'self::checkAuthAndCSRF();',
    'bounds the posted host ids to the caller sites'
        => "Authorization::requirePageObjectScopeMass('host', \$hosts);",
    'bounds the posted group ids to the caller sites'
        => "Authorization::requirePageObjectScopeMass('group', \$groups);",
];
foreach ($gates as $label => $needle) {
    $at = strpos($body, $needle);
    check(
        "saveGroup() $label",
        false !== $at,
        $failures,
        $checks
    );
    check(
        "saveGroup() does so BEFORE its try block ($label)",
        false !== $at && false !== $tryAt && $at < $tryAt,
        $failures,
        $checks
    );
}

/*
 * 4. The body-dependent half: creating a group needs group.create, and the
 *    check is tied to groups_new actually carrying something. Matched as one
 *    expression so that dropping either half is a failure -- an unconditional
 *    check would take labeling away from a role that may only edit, and a
 *    condition with no check would give group creation away for free.
 */
check(
    'saveGroup() demands group.create only when groups_new[] is non-empty',
    1 === preg_match(
        '/if\s*\(\s*count\(\$groups_new\)\s*&&\s*'
        . '!\s*Authorization::can\(\s*\'group\.create\'\s*\)\s*\)/',
        $body
    ),
    $failures,
    $checks
);
check(
    'and refuses with 403 rather than a generic 400',
    1 === preg_match(
        '/HTTPResponseCodes::HTTP_FORBIDDEN/',
        substr($body, 0, false === $tryAt ? strlen($body) : $tryAt)
    ),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
