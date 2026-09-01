<?php
/**
 * The client is told the RESOLVED module list, and short names come from the
 * class that has them.
 *
 * TWO FAILURES, one new and one that has been live for the whole of 1.6.
 *
 * 1. ADR 0038: a group GRANTS a module. `Host::get('modules')` is the
 *    host-direct list -- the value the edit surfaces diff against -- so a
 *    client path that reads it ignores every grant. The three client-facing
 *    readers must call `Host::resolvedModules()` instead.
 *
 * 2. `ServiceModule::checkPassiveModule()` asked `moduleassociation` for
 *    `shortName`. `ModuleAssociation` has four fields -- id, hostID,
 *    moduleID, state -- and `shortName` is not one of them, so the projection
 *    could never resolve and the call returned an empty list on every server.
 *    `$hostDisabled` is `array_diff($globalModules, $hostEnabled)`, so an
 *    empty $hostEnabled makes EVERY module disabled for EVERY host and the
 *    endpoint answers `#!nh` unconditionally. Confirmed against a real
 *    MariaDB before the fix: `Route::getIds('moduleassociation', ['id' =>
 *    [2,3]], 'shortName')` returned [] with an "Undefined array key
 *    shortName" notice, while the `module` spelling returned
 *    ["greenfog","printermanager"].
 *
 * WHAT IS ASSERTED, AND HOW. The class-name fact is read off the MODELS
 * themselves -- `Module` declares `shortName`, `ModuleAssociation` does not
 * -- through reflection on `$databaseFields`. That is the property that makes
 * the old call impossible to answer, and it goes red the moment either model
 * changes. A FakeDB was tried first and rejected: a fake that answers every
 * query with a row keyed by the columns the SELECT named cannot tell the two
 * spellings apart (both came back with a value), so it would have measured
 * the fixture rather than the fix.
 *
 * The rest is source-anchored. Which of two accessors a call site uses is not
 * observable from outside without standing up the whole client protocol, a
 * Host row and a session; the BEHAVIOR both accessors feed is covered by
 * tests/assign-resolver.test.php, which drives the resolver against a real
 * database. What is left here is the wiring, and the wiring is a fact about
 * the source.
 *
 * Usage: php tests/host-modules-are-resolved.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-host-modules-test-' . getmypid();
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
foreach (
    [
        'FOG_CACHE_DIR' => $tmp . '/cache',
        'FOG_LOG_DIR' => $tmp . '/log',
        'FOG_PLUGIN_DIR' => $tmp . '/plugins',
    ] as $const => $value
) {
    if (!defined($const)) {
        define($const, $value);
    }
}
// FOGBase::_writeLog() stamps the schema version into every line; a real
// install gets this from the generated config.class.php, which a test has not.
if (!defined('FOG_SCHEMA')) {
    define('FOG_SCHEMA', 0);
}

require_once $init;
new Initiator();

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

/*
 * 1-2. The class-name fact, read off the models. `Module` has a shortName to
 * project; `ModuleAssociation` has four fields and none of them is it, which
 * is why the old call site could never answer and returned an empty list on
 * every server.
 */
$fields = static function ($class) {
    $prop = new \ReflectionProperty($class, 'databaseFields');
    $prop->setAccessible(true);
    $obj = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

    return array_keys((array)$prop->getValue($obj));
};
$check(
    'the module class declares shortName, so it can be projected',
    in_array('shortName', $fields(\FOG\Items\Module::class), true)
);
$check(
    'the moduleassociation class does not -- there is nothing to project',
    !in_array('shortName', $fields(\FOG\Items\ModuleAssociation::class), true)
);

/*
 * 3-6. The wiring. Every client-facing reader asks for the RESOLVED list,
 * and asks the class that can answer.
 */
$readers = [
    'src/Client/ServiceModule.php' => 'checkPassiveModule',
    'src/Client/FOGClient.php' => 'the client endpoint gate',
    'src/Base/FOGPage.php' => 'the servicemodule-active response',
];
foreach ($readers as $file => $what) {
    $src = (string)@file_get_contents($webroot . '/' . $file);
    $check(
        "$what reads the resolved module list, not the host-direct one",
        false !== strpos($src, "self::\$Host->resolvedModules()")
            && false === strpos($src, "self::\$Host->get('modules')")
    );
}
$svc = (string)@file_get_contents($webroot . '/src/Client/ServiceModule.php');
$check(
    'checkPassiveModule() asks the module class for its short names',
    1 === preg_match(
        "/getIds\(\s*'module',\s*\[\s*'id' => self::\\\$Host->resolvedModules/s",
        $svc
    )
);

/*
 * 7-8. The other half of the split: the host-direct list stays host-direct,
 * and it stops at the rows the host has turned ON. Route's host update arm
 * diffs against get('modules'), so a resolved value there would write a host
 * row for every grant -- turning a grant back into a copy, which is the one
 * thing ADR 0038 exists to prevent.
 */
$host = (string)@file_get_contents($webroot . '/src/Items/Host.php');
$check(
    'loadModules() filters to the rows the host has turned ON',
    1 === preg_match(
        "/protected function loadModules\(\).*?"
        . "\\\$find = \['hostID' => \\\$this->get\('id'\), 'state' => 1\]/s",
        $host
    )
);
$check(
    'resolvedModules() is a separate public accessor, not a cached field',
    1 === preg_match(
        '/public function resolvedModules\(\).*?'
        . 'Resolver::resolveModules\(\[\$id\]\)/s',
        $host
    )
    && false === strpos($host, "'modulesResolved'")
);

/*
 * 9. The generic association insert stays generic. addRemItem() used to
 * append an explicit `state` of 1 for module rows, because the column was a
 * varchar(1) NOT NULL DEFAULT '' and an omitted value wrote the empty
 * string. Schema step 409 made it tinyint(1) NOT NULL DEFAULT 1, so the
 * database supplies it and the special case is gone.
 *
 * Pinned on the source because the BEHAVIOR is indistinguishable: writing 1
 * explicitly and letting the default write 1 produce the same row. What this
 * catches is the special case coming back with a different value in it --
 * and under ADR 0038, 0 is a host saying OFF and beats every group grant.
 * That the default itself is 1 is pinned behaviorally, against the real DDL,
 * in tests/assign-resolver.test.php.
 */
$controller = (string)@file_get_contents(
    $webroot . '/src/Base/FOGController.php'
);
$check(
    'addRemItem() has no module special case left',
    false === strpos($controller, "\$assocstr == 'moduleID'")
        && false === strpos(
            $controller,
            "strtolower(\$classCall) == 'moduleassociation'"
        )
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the module read path is wrong:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  host modules are resolved: %d checks\n", $checks);
exit(0);
