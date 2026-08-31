<?php
/**
 * A plugin file may not shadow a core class of the same name.
 *
 * The original failure: Initiator::autoload() mapped a lowercased basename to
 * a path, and two files claiming one key were resolved by whichever the
 * directory walk reached first -- readdir order, which differs per install,
 * so the same code resolved to a different file on two servers with nothing
 * to say so. A bundled plugin shipping class/authorization.class.php could
 * replace core's Authorization on some installs and not others.
 *
 * That basename map is gone. Core is PSR-4 under src/ and a plugin is PSR-4
 * under <plugin>/src/ (ADR 0013, ADR 0035), so both sides are DERIVED from a
 * class name and there is no shared key space left to collide in. What
 * replaced the ordering rule is three separate things, and they are not
 * interchangeable:
 *
 *   1. Namespace separation. A plugin's classes are all under FOG\Plugins\,
 *      so its ProbeAlpha and core's are two different classes that both
 *      exist. tests/psr4-bridge.test.php holds this.
 *   2. ORDER, for the BARE spelling, which is the one flat name space core
 *      and plugins still share. autoload() answers a bare core name with a
 *      refusal and RETURNS rather than falling through to the plugin roots,
 *      and FOGBase::qualify() consults core's map before the plugin one.
 *      tests/psr4-bridge.test.php and tests/plugin-namespace.test.php hold
 *      those two halves.
 *   3. Uniqueness WITHIN src/, which is what this file holds. Two core files
 *      folding to one bare key cannot be ordered -- there is no honest basis
 *      to prefer one core file over another -- so srcFileList() refuses to
 *      serve either and says so. tests/psr4-layout.test.php refuses the same
 *      thing in the tree; this is the runtime half, because a hand-placed
 *      file never went through that gate.
 *
 * It also carries the four site membership models (section 3), which landed
 * here because moving Site out of the site plugin and into core was the
 * change that first made the shadowing rule load-bearing.
 *
 * DB-free, same shape as autoload.test.php: the Initiator constructor only
 * registers the autoloader, so the cache dir is redirected somewhere
 * throwaway and startInit() -- the part that needs MySQL -- is never called.
 *
 * Usage: php tests/autoload-core-wins.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$init = $root . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-core-wins-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
// An external plugin root, so both plugin shapes are exercised.
@mkdir($tmp . '/extplugins', 0700, true);
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
    define('FOG_PLUGIN_DIR', $tmp . '/extplugins');
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

/*
 * 1. Two files under src/ claiming one bare key. Neither is served, and the
 *    collision is logged: a bare name that silently resolves to whichever
 *    file was walked first is the whole failure this file is named after,
 *    and refusing is the only honest answer when both candidates are core.
 *
 *    Driven against a THROWAWAY src/ tree, because the assertion is about
 *    what happens when the rule is broken and the repository's own src/ must
 *    never hold a file that breaks it -- section 2 is the check that says so.
 */
$errLog = ini_get('error_log');
$quiet = $tmp . '/collisions.log';
ini_set('error_log', $quiet);

@mkdir($tmp . '/probe/src/Items', 0700, true);
@mkdir($tmp . '/probe/src/Managers', 0700, true);
file_put_contents(
    $tmp . '/probe/src/Items/Dup.php',
    "<?php\nnamespace FOG\\Items;\nclass Dup { }\n"
);
file_put_contents(
    $tmp . '/probe/src/Managers/Dup.php',
    "<?php\nnamespace FOG\\Managers;\nclass Dup { }\n"
);
file_put_contents(
    $tmp . '/probe/src/Items/Solo.php',
    "<?php\nnamespace FOG\\Items;\nclass Solo { }\n"
);

// Driven in a SUBPROCESS against a copied init.php. Initiator derives
// BASEPATH from its own file location and BASEPATH is a constant, so the
// only way to point srcFileList() at a different src/ is to run a second
// Initiator -- the same technique psr4-bridge.test.php uses, for the same
// reason. This process keeps the real tree, which section 2 then checks.
@mkdir($tmp . '/probe/commons', 0700, true);
copy($root . '/commons/init.php', $tmp . '/probe/commons/init.php');
$probe = <<<'PROBE'
<?php
define('FOG_CACHE_DIR', $argv[1] . '/cache');
define('FOG_LOG_DIR', $argv[1] . '/log');
define('FOG_PLUGIN_DIR', $argv[1] . '/extplugins');
ini_set('log_errors', '1');
ini_set('error_log', $argv[2]);
require $argv[1] . '/probe/commons/init.php';
new Initiator();
$map = Initiator::srcFileList();
echo json_encode(['dup' => isset($map['dup']), 'solo' => isset($map['solo'])]);
PROBE;
file_put_contents($tmp . '/probe.php', $probe);
$out = (string)shell_exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp . '/probe.php')
    . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($quiet) . ' 2>/dev/null'
);
$res = json_decode($out, true);
check(
    'a name two files under src/ both claim is served to nobody',
    is_array($res) && $res['dup'] === false,
    $failures,
    $checks
);
check(
    'and the rest of the tree is unaffected',
    is_array($res) && $res['solo'] === true,
    $failures,
    $checks
);
$logged = is_readable($quiet) ? (string)file_get_contents($quiet) : '';
check(
    'the collision was logged -- a silent shadow is the failure this whole '
    . 'mechanism exists to prevent',
    strpos($logged, 'two files under src/ claim the class name "dup"') !== false,
    $failures,
    $checks
);
ini_set('error_log', (string)$errLog);

/*
 * 2. The shipped tree has no colliding names -- neither within src/, which
 *    is the rule above, nor between two plugins, which is legal but makes
 *    the SHORT spelling ambiguous and is reported for that reason.
 */
$seen = [];
$collisions = [];
foreach (Initiator::srcFileList() as $key => $path) {
    $seen[$key] = $path;
}
foreach (Initiator::pluginFileList() as $path) {
    $key = strtolower(basename($path, '.php'));
    if (isset($seen[$key])) {
        $collisions[$key] = [$seen[$key], $path];
        continue;
    }
    $seen[$key] = $path;
}
check(
    'no plugin file claims a bare name core already uses'
    . (count($collisions)
        ? ' (found: ' . implode(', ', array_keys($collisions)) . ')'
        : ''),
    count($collisions) === 0,
    $failures,
    $checks
);

/*
 * 3. The four site membership models resolve and are wired to the tables
 *    schema step 331 creates. Cheap, and it catches the two mistakes that
 *    would otherwise surface as an empty result set rather than an error:
 *    a class name that does not match its filename, and a databaseFields
 *    key that does not match what assocSetter derives from Site.
 */
// Named by their FQCN: core is PSR-4 under src/ and no longer re-exports
// itself into the global namespace (ADR 0013 §2), so the bare spellings
// resolve to nothing.
$models = [
    'FOG\\Items\\SiteHostMember' => ['siteHostMembers', 'hostID'],
    'FOG\\Items\\SiteUserMember' => ['siteUserMembers', 'userID'],
    'FOG\\Items\\SiteGroupMember' => ['siteGroupMembers', 'groupID'],
    'FOG\\Items\\SiteUserGroupMember' => ['siteUserGroupMembers', 'usergroupID'],
];
foreach ($models as $class => $spec) {
    if (!class_exists($class, true)) {
        check("$class resolves", false, $failures, $checks);
        continue;
    }
    check("$class resolves", true, $failures, $checks);

    $ref = new \ReflectionClass($class);
    check(
        "$class extends FOGController",
        $ref->isSubclassOf(\FOG\Base\FOGController::class),
        $failures,
        $checks
    );

    $table = $ref->getProperty('databaseTable');
    $table->setAccessible(true);
    check(
        "$class maps to `{$spec[0]}`",
        $table->getValue($ref->newInstanceWithoutConstructor()) === $spec[0],
        $failures,
        $checks
    );

    $fields = $ref->getProperty('databaseFields');
    $fields->setAccessible(true);
    $keys = array_keys(
        $fields->getValue($ref->newInstanceWithoutConstructor())
    );
    // siteID is the half assocSetter derives from the Site class name; the
    // object key is the half the scope queries filter on. Both are load
    // bearing and neither is checked anywhere else.
    check(
        "$class has a siteID field",
        in_array('siteID', $keys, true),
        $failures,
        $checks
    );
    check(
        "$class has a {$spec[1]} field",
        in_array($spec[1], $keys, true),
        $failures,
        $checks
    );

    // The manager lives in the Managers bucket, not alongside the model.
    $mgr = 'FOG\\Managers\\'
        . substr($class, strrpos($class, '\\') + 1) . 'Manager';
    check(
        "$mgr resolves",
        class_exists($mgr, true),
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
exit(0);
