<?php
/**
 * An enrollment request with no host is saved with aeHostID NULL, not 0.
 *
 * THE BUG (forum 18319). Enrollment::handle() saves a request that is not
 * bound to a host with hostID 0: an identity conflict on the directory
 * identity path, and a request with no MAC address. The foreign key
 * fk_agentEnrollment_aeHostID refuses 0 with error 1452, so the row was
 * never stored. The agent got a 409 on each poll, and All Pending Agents
 * never showed the request.
 *
 * Schema step 465 makes the column nullable. The manifest edit is what
 * makes save() write NULL (FOGBase::columnIsNullable() reads the
 * manifest, not the server), so the premises are checked here as well as
 * the bound value.
 *
 * Usage: php tests/agent-enrollment-unbound-row.test.php
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

use FOG\Items\AgentEnrollment;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-enrollment-unbound-row');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

// 1. The premises.
$manifest = (string)file_get_contents($web . '/commons/schema-expected.php');
$t->check(
    'aeHostID is declared nullable in the manifest',
    false !== strpos($manifest, "'aeHostID' => 'int(11) DEFAULT NULL'")
);
$constraints = (string)file_get_contents($web . '/commons/schema-constraints.php');
$t->check(
    'aeHostID carries the foreign key that makes 0 unstorable, with its sentinel',
    1 === preg_match(
        "/'child' => 'agentEnrollment',\s*'column' => 'aeHostID',"
        . "\s*'parent' => 'hosts',[^\n]*'sentinel' => 0,[^\n]*'enabled' => true/",
        $constraints
    )
);
$schema = (string)file_get_contents($web . '/commons/schema.php');
$t->check(
    'a schema step makes aeHostID nullable and converts 0 to NULL',
    1 === preg_match(
        '/MODIFY COLUMN `aeHostID` INT\(11\) NULL DEFAULT NULL.*?'
        . 'SET `aeHostID` = NULL "\s*\.\s*"WHERE `aeHostID` = 0/s',
        $schema
    )
);

// 2. What save() binds for a row built the way Enrollment::handle() builds
//    an unbound one: hostID set to 0.
$captured = [];
$db->responder = static function ($sql, $params) use ($db, &$captured) {
    $db->error = false;
    if (0 === stripos(ltrim($sql), 'INSERT INTO `agentEnrollment`')) {
        $captured = $params;
    }
    return null;
};
(new AgentEnrollment())
    ->set('fingerprint', str_repeat('a', 64))
    ->set('csr', 'csr')
    ->set('identity', '{}')
    ->set('hostID', 0)
    ->set('reason', 'identity-conflict')
    ->set('state', AgentEnrollment::STATE_PENDING)
    ->set('cert', '')
    ->save();

$t->check('the INSERT was built at all', count($captured) > 0);
$t->check(
    'an unbound row binds aeHostID as NULL, or leaves it out',
    !array_key_exists(':aeHostID_insert', $captured)
        || null === $captured[':aeHostID_insert']
);

$t->finish();
