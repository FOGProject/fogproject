<?php
/**
 * Pins per-task-type permissions (GH-1878).
 *
 * A task needs 'tasktype.<ttID>' as well as the object's own task
 * permission. Two halves:
 *
 *   - Behavior: canTaskType() answers from the role's grants, accepts the
 *     'tasktype.*' wildcard and '*', and refuses an undeclared type even to
 *     '*'. Seeded through reflection, so no database.
 *   - Coverage: every method that starts a task calls the check, and no
 *     file outside the known set calls createImagePackage(). A new entry
 *     point fails here until it checks the type or is added to the exempt
 *     list with a reason.
 *
 * DB-free, so it runs in tests/run-all.sh.
 *
 * Usage: php tests/tasktype-permission.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('tasktype-permission');
FogTestHarness::fakeDb();

$webroot = dirname(__DIR__) . '/packages/web';

$fails = [];
$check = function ($ok, $what) use (&$fails) {
    if (!$ok) {
        $fails[] = $what;
    }
};

// ---- Behavior --------------------------------------------------------
$set = function ($prop, $value) {
    FogTestHarness::setStatic('Authorization', $prop, $value);
};
$set('_taskTypes', [1 => 'Deploy', 2 => 'Capture']);
$set('_registry', null);
$set('_permCache', [
    5 => ['host.task', 'tasktype.1'],
    6 => ['host.task', 'tasktype.*'],
    7 => ['*'],
    8 => ['host.task']
]);

$check(Authorization::canTaskType(1, 5), 'a role granted tasktype.1 may deploy');
$check(!Authorization::canTaskType(2, 5), 'a role granted tasktype.1 may not capture');
$check(Authorization::canTaskType(2, 6), 'tasktype.* grants every type');
$check(Authorization::canTaskType(2, 7), "'*' grants every declared type");
$check(!Authorization::canTaskType(99, 7), "'*' does not grant a type that is not on the server");
$check(!Authorization::canTaskType(1, 8), 'host.task alone no longer starts a task');
$check(
    ['1', '2'] === Authorization::registry()['tasktype'],
    'registry() declares one tasktype action per ttID'
);
$check(
    [] === Authorization::coreRegistry()['tasktype'],
    'coreRegistry() stays DB-free: tasktype is filled by registry()'
);

// ---- Coverage --------------------------------------------------------
// Each method must call one of these. They all reach canTaskType().
$guards = '/assertTaskType\(|canTaskType\(|_mayStart\(|_assertTaskTypeOf\(/';
$mustCheck = [
    'src/Pages/HostManagement.php' => ['deploy', 'deployPost', 'deployMultiPost'],
    'src/Pages/GroupManagement.php' => ['deploy', 'deployPost'],
    'src/Pages/ImageManagement.php' => ['sessionCreate'],
    'src/Router/Route.php' => ['task', 'create', 'edit'],
    'src/Boot/IpxeBootMenu.php' => ['verifyCreds'],
    'src/Boot/Registration.php' => ['_deployHost'],
    'src/Base/FOGPageRender.php' => ['renderQuickTaskActions', 'taskTypeAccordion'],
];
foreach ($mustCheck as $file => $methods) {
    $src = (string)file_get_contents($webroot . '/' . $file);
    foreach ($methods as $method) {
        // The method body: from its signature to the next method signature.
        $re = '/function\s+' . preg_quote($method, '/') . '\s*\((.*?)'
            . '(?=\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s)/s';
        if (!preg_match($re, $src, $m)) {
            $fails[] = "$file: cannot find $method()";
            continue;
        }
        $check(
            1 === preg_match($guards, $m[1]),
            "$file::$method() starts or offers a task without checking its type"
        );
    }
}

// Every caller of createImagePackage(), outside comments. A caller not
// listed here is a new way to start a task: give it the check, and add it
// to $mustCheck above. Exempt only with a reason.
$known = [
    'src/Items/Host.php',      // the definition, and its own internal calls
    'src/Items/Group.php',     // the definition
    'src/Pages/HostManagement.php',
    'src/Pages/GroupManagement.php',
    'src/Router/Route.php',
    'src/Boot/IpxeBootMenu.php',
    'src/Boot/Registration.php',
    // Runs a task a user scheduled. The check ran when it was scheduled,
    // and the daemon has no user to check.
    'src/Service/TaskScheduler.php',
];
$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($webroot . '/src', \FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    if ('php' !== $f->getExtension()) {
        continue;
    }
    $code = '';
    foreach (token_get_all((string)file_get_contents($f->getPathname())) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($t) ? $t[1] : $t;
    }
    if (false === strpos($code, 'createImagePackage(')) {
        continue;
    }
    $rel = substr($f->getPathname(), strlen($webroot) + 1);
    $check(
        in_array($rel, $known, true),
        "$rel calls createImagePackage() and is not a known, checked caller"
    );
}

// A role that could task before the upgrade keeps every type.
$schema = (string)file_get_contents($webroot . '/commons/schema.php');
$check(
    false !== strpos($schema, "SELECT DISTINCT `rpRoleID`, 'tasktype.*'"),
    'schema seeds tasktype.* for roles that could already start tasks'
);

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: tasktype permission\n";
exit(0);
