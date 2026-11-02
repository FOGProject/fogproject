<?php
/**
 * Schema 447 on a real server, then the legacy-state read and the rollback of
 * a failed DirectoryRejoin::change() transaction.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 * It works only in the database fog_directory_test that tests/lib/directory-db.php
 * creates.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-bootstrap-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory bootstrap DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-bootstrap-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];

$t = new FogChecks();

function bootstrapNormalizeType($type)
{
    $type = strtolower(trim($type));
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
}

$actualColumns = $pdo->query('SHOW COLUMNS FROM hostDirectory')->fetchAll(\PDO::FETCH_ASSOC);
$expectedColumns = $manifest['tables']['hostDirectory']['columns'];
$structureMatches = true;
foreach ($actualColumns as $column) {
    $definition = $expectedColumns[$column['Field']];
    $expectedType = preg_split('/\s+(?:NOT NULL|DEFAULT)\b/i', $definition, 2)[0];
    $structureMatches = $structureMatches
        && bootstrapNormalizeType($column['Type']) === bootstrapNormalizeType($expectedType)
        && (strpos($definition, 'NOT NULL') !== false) === ($column['Null'] === 'NO');
}
$t->check('migrated hostDirectory structure matches schema-expected.php', $structureMatches);
$byField = array_column($actualColumns, null, 'Field');
$t->check(
    'the two rejoin columns are TEXT and default to NULL',
    'text' === $byField['hdRejoinConfig']['Type'] && null === $byField['hdRejoinConfig']['Default']
    && 'text' === $byField['hdRejoinState']['Type'] && null === $byField['hdRejoinState']['Default']
);

$legacy = [
    'version' => 1, 'phase' => 'ready', 'attempt_id' => str_repeat('a', 32),
    'source' => ['guid' => '11111111-1111-1111-1111-111111111111', 'sid' => 'S-1-5-21-1-1'],
    'target' => ['guid' => '22222222-2222-2222-2222-222222222222', 'sid' => 'S-1-5-21-1-2'],
    'clone_status' => 'detected', 'clone_origin' => ['host_id' => 99], 'origin_host_id' => 99
];
$legacyDecoded = $legacy;
unset($legacyDecoded['clone_status'], $legacyDecoded['clone_origin'], $legacyDecoded['origin_host_id']);
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?, hdRejoinState=? WHERE hdHostID=7')
    ->execute([json_encode(['version' => 1, 'enabled' => true]), json_encode($legacy)]);
$host = (new FOG\Items\Host())->set('id', 7);
$change = new \ReflectionMethod(FOG\Agent\DirectoryRejoin::class, 'change');
$change->setAccessible(true);

$t->check(
    'legacy state decode discards the clone classification',
    FOG\Agent\DirectoryRejoin::settings($host)['state'] === $legacyDecoded
);
$error = null;
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, []);
} catch (\RuntimeException $e) {
    $error = $e->getMessage();
}
$t->check(
    'a poll without the directory features is not refused while an operation is pending',
    null === $error
);
$error = null;
try {
    FOG\Agent\DirectoryRejoin::start($host, ['attempt_id' => $legacy['attempt_id']]);
} catch (\RuntimeException $e) {
    $error = $e->getMessage();
}
$t->check('a legacy v1 attempt is never executed', 'legacy_directory_operation_requires_review' === $error);
$t->check(
    'the refusal left the old operation as it was',
    FOG\Agent\DirectoryRejoin::settings($host)['state'] === $legacyDecoded
);
$change->invoke(null, $host, function (&$config, &$state) {
});
$t->check(
    'a no-op transaction leaves the legacy state unchanged',
    FOG\Agent\DirectoryRejoin::settings($host)['state'] === $legacyDecoded
);
$error = null;
try {
    $change->invoke(null, $host, function (&$config, &$state) {
        $state['phase'] = 'started';
        throw new \RuntimeException('injected');
    });
} catch (\RuntimeException $e) {
    $error = $e->getMessage();
}
$stored = json_decode($pdo->query('SELECT hdRejoinState FROM hostDirectory WHERE hdHostID=7')->fetchColumn(), true);
$t->check(
    'a failed transaction rolls back and leaves no transaction open',
    'injected' === $error && $stored === $legacyDecoded && !$pdo->inTransaction()
);

$pdo->exec('UPDATE hostDirectory SET hdRejoinConfig=NULL, hdRejoinState=NULL WHERE hdHostID=7');
FOG\Agent\DirectoryRejoin::beforePoll($host, []);
$row = $pdo->query('SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
$t->check(
    'an ordinary old agent may poll and changes nothing when the host has no operation',
    null === $row['hdRejoinConfig'] && null === $row['hdRejoinState']
);
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?, hdRejoinState=? WHERE hdHostID=7')
    ->execute([
        json_encode(['version' => 1, 'protocol' => 2, 'allow_create' => false]),
        json_encode(['version' => 1, 'protocol' => 2, 'phase' => 'unknown', 'attempt_id' => str_repeat('b', 32)])
    ]);
$error = null;
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, []);
} catch (\RuntimeException $e) {
    $error = $e->getMessage();
}
$t->check(
    'a poll without the directory features is not refused on a current v2 unknown state',
    null === $error
);

// Give the host a configured server through the plugin hook, so the guard,
// not the "unconfigured" refusal in front of it, is what answers.
$knownEvents = new \ReflectionProperty(FOG\Base\HookManager::class, 'knownEvents');
$knownEvents->setAccessible(true);
$knownEvents->setValue(null, ['DIRECTORY_CONNECTION' => true]);
$hooks = FogTestHarness::getStatic('FOGBase', 'HookManager');
$hooks->register(
    'DIRECTORY_CONNECTION',
    static function (array $args) {
        $args['connection']['uri'] = 'ldaps://dc1.corp.example';
    }
);
$error = null;
try {
    $pdo->beginTransaction();
    FOG\Agent\DirectoryRejoin::connection($host);
} catch (\RuntimeException $e) {
    $error = $e->getMessage();
} finally {
    $pdo->rollBack();
}
$t->check(
    'LDAP is never dialed inside a transaction',
    'directory_ldap_inside_transaction' === $error
);

$t->finish();
