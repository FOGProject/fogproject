<?php
/**
 * A host module left "Not set" follows the module default (GH-1972).
 *
 * What broke: Resolver::resolveModules() had no default tier. The module
 * default (`modules`.`default`) was copied into a host's rows once, when the
 * host was created. Changing a default never reached an existing host, and a
 * module left "Not set" on a host was off.
 *
 * What this pins:
 *   - no host row, default 1: ON;
 *   - no host row, default 0: OFF;
 *   - a host OFF row beats the default and a group grant;
 *   - a group grant is ON when the default is 0;
 *   - the default is read once per call, not once per host;
 *   - schema step 443 only inserts, inserts msState 0, and keeps every host
 *     whose missing row meant OFF on an install upgraded from 1.5 (it skips
 *     a host with a row and a host whose group grants the module);
 *   - FOG_SCHEMA is 443;
 *   - host creation no longer copies the default modules into host rows.
 *
 * Usage: php tests/module-not-set-follows-default.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/fog-schema-collector.php';

FogTestHarness::boot('module-not-set-follows-default');
$db = FogTestHarness::fakeDb();

use FOG\Assign\Resolver;

$t = new FogChecks();

// The module table the responder serves. id => `default`.
$modules = [1 => 1, 2 => 0, 3 => 1, 4 => 0];
// Host rows: [hostID, moduleID, msState].
$rows = [
    [20, 1, 0],  // host 20 says OFF to a default-1 module
    [20, 4, 1],  // host 20 says ON to a default-0 module
    [21, 3, 0],  // host 21 says OFF; a group also grants it
];
// Host 21 is in group 7, which grants modules 2 and 3.
$members = [[21, 7]];
$grants = [[7, 2], [7, 3]];

$db->responder = function ($sql) use (&$modules, &$rows, &$members, &$grants) {
    if (false !== strpos($sql, 'FROM `moduleStatusByHost`')) {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['msHostID' => $r[0], 'msModuleID' => $r[1], 'msState' => $r[2]];
        }
        return $out;
    }
    if (false !== strpos($sql, 'FROM `modules`')) {
        // Answers only what the SQL asks for: a resolver that dropped the
        // WHERE would get every module and the default-0 checks would fail.
        $out = [];
        foreach ($modules as $id => $default) {
            if (false !== strpos($sql, '`default` = 1') && 1 !== $default) {
                continue;
            }
            $out[] = ['id' => $id];
        }
        return $out;
    }
    if (false !== strpos($sql, 'FROM `groupMembers`')) {
        $out = [];
        foreach ($members as $m) {
            $out[] = ['gmHostID' => $m[0], 'gmGroupID' => $m[1]];
        }
        return $out;
    }
    if (false !== strpos($sql, 'FROM `groupModuleAssoc`')) {
        $out = [];
        foreach ($grants as $g) {
            $out[] = ['gmaGroupID' => $g[0], 'gmaModuleID' => $g[1]];
        }
        return $out;
    }
    return null;
};

$logStart = count($db->log);
$res = Resolver::resolveModules([20, 21, 22]);

// Host 22 has no row and no group.
$t->check('unset + default 1 is ON; unset + default 0 is OFF', ($res[22] ?? null) === [1, 3]);
// Host 20: OFF on module 1 beats default 1; ON on module 4 beats default 0.
$t->check('a host OFF row beats the default', !in_array(1, $res[20] ?? [], true));
$t->check('a host ON row beats default 0 and the default still applies elsewhere', ($res[20] ?? null) === [3, 4]);
// Host 21: OFF on 3 beats default 1 and the group grant; grant of 2 is ON with default 0.
$t->check('a host OFF row beats the default and a group grant', !in_array(3, $res[21] ?? [], true));
$t->check('a group grant is ON with default 0', ($res[21] ?? null) === [1, 2]);

$defaultReads = 0;
foreach (array_slice($db->log, $logStart) as $sql) {
    if (false !== strpos($sql, 'FROM `modules`')) {
        $defaultReads++;
    }
}
$t->check('the default set is read once per call', 1 === $defaultReads);

// Schema step 443 is array index 442: the array is keyed from 0.
$systemSrc = file_get_contents(dirname(__DIR__) . '/packages/web/src/Base/System.php');
$t->check('FOG_SCHEMA is 443', 1 === preg_match("/define\(\s*'FOG_SCHEMA'\s*,\s*443\s*\)/", $systemSrc));
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    'module_not_set_follows_default',
    443
);
$sql = isset($steps[442]) ? implode("\n", (array)$steps[442]) : '';
$t->check('step 443 is the last step', 443 === count($steps));
$t->check('step 443 inserts msState 0', 1 === preg_match('/INSERT IGNORE INTO `moduleStatusByHost`.*SELECT h\.`hostID`, m\.`id`, 0 FROM/s', $sql));
$t->check('step 443 has no DELETE or UPDATE', 0 === preg_match('/\b(DELETE|UPDATE)\b/i', $sql));
$t->check('step 443 skips hosts with a row', false !== strpos($sql, 'FROM `moduleStatusByHost` s'));
$t->check('step 443 skips hosts a group grants the module to', false !== strpos($sql, '`groupModuleAssoc` g'));
$t->check('step 443 covers default modules only', false !== strpos($sql, 'm.`default` = 1'));

// Host creation no longer stamps the default modules.
$root = dirname(__DIR__) . '/packages/web/src/';
foreach ([
    'Boot/Registration.php',
    'Client/RegisterClient.php',
    'Agent/Enrollment.php',
    'Pages/HostManagement.php',
    'Base/FOGPage.php',
] as $file) {
    $src = file_get_contents($root . $file);
    $t->check(
        "$file does not copy default modules into a new host",
        0 === preg_match("/'module',\s*\[?\s*\[?'isDefault'/", $src)
        && 0 === preg_match("/Route::getIds\(\s*'module',\s*\\\$find/", $src)
    );
}

$t->finish();
