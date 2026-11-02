<?php
/**
 * A host with directory membership off (useAD = 0) polls with both agent
 * features and a directory_attempt. The poll must create no hostDirectory row,
 * leave an existing row byte for byte as it was, and issue no locking read.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-ad-off-poll-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory AD-off poll DB: FOG_TEST_DSN not set\n";
    exit(0);
}

/** A PDO that records every statement it is asked to run. */
class AdOffLoggingPdo extends \PDO
{
    public $log = [];

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        $this->log[] = (string)$query;
        return parent::prepare($query, $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, ...$args)
    {
        $this->log[] = (string)$query;
        return parent::query($query, ...$args);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $this->log[] = (string)$statement;
        return parent::exec($statement);
    }
}

$directoryDbName = 'agent-directory-ad-off-poll-db';
require __DIR__ . '/lib/directory-db.php';
$t = new FogChecks();

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$logging = new AdOffLoggingPdo(
    directoryTestDsn(),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
);
directoryTestBindPdodb($logging);

// The identity check resolves the host by MAC, with SMBIOS matching off, and
// the agent facts setting is on so only useAD can stop the observation.
FogTestHarness::setStatic('FOGBase', '_settingsCache', [
    'FOG_HOST_IDENTIFY_SMBIOS' => ['value' => 'off'],
    FOG\Agent\State::FACTS_SETTING => ['value' => '1']
]);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

// Resolving the host loads it, and a Host load LEFT JOINs many tables. Create
// whichever of them are missing from the manifest, empty.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table) {
    $pdo->exec($table['create']);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$mac = 'aa:bb:cc:dd:ee:07';
$pdo->exec('DELETE FROM hostMAC');
$pdo->prepare('INSERT INTO hostMAC (hmHostID,hmMAC,hmPending) VALUES (7,?,0)')->execute([$mac]);
$pdo->exec('UPDATE hosts SET hostUseAD=0 WHERE hostID=7');
$pdo->exec('DELETE FROM hostDirectory');

$host = (new FOG\Items\Host())->set('id', 7)->set('name', 'B')->set('useAD', 0)
    ->set('agentFingerprint', str_repeat('f', 64));
$body = [
    'features' => [FOG\Agent\MachineIdentity::FEATURE, FOG\Agent\DirectoryOperation::FEATURE],
    'identity' => ['macs' => [$mac]],
    'directory_attempt' => ['phase' => 'none']
];

$logging->log = [];
FOG\Agent\DirectoryRejoin::beforePoll($host, $body);
$rows = (int)$pdo->query('SELECT COUNT(*) FROM hostDirectory')->fetchColumn();
$t->check('a poll from a host with directory membership off creates no hostDirectory row', 0 === $rows);
$locking = array_filter($logging->log, static function ($sql) {
    return (bool)preg_match('/\bFOR\s+UPDATE\b/i', $sql);
});
$t->check('the poll issues no locking read', [] === array_values($locking));
$t->check(
    'the identity check ran, so the host was resolved and not skipped',
    (bool)array_filter($logging->log, static function ($sql) {
        return false !== stripos($sql, 'hostMAC');
    })
);

// An existing row, with state in it, is not touched either.
$state = json_encode(['version' => 1, 'protocol' => 2, 'phase' => 'verified', 'attempt_id' => str_repeat('c', 32)]);
$pdo->exec('DELETE FROM hostDirectory');
$pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (7,?,?)')
    ->execute([json_encode(['version' => 1, 'enabled' => true]), $state]);
$before = $pdo->query('SELECT * FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
$logging->log = [];
FOG\Agent\DirectoryRejoin::beforePoll($host, $body);
$after = $pdo->query('SELECT * FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
$t->check('an existing hostDirectory row is unchanged by the poll', $before === $after);
$locking = array_filter($logging->log, static function ($sql) {
    return (bool)preg_match('/\bFOR\s+UPDATE\b|\bINSERT\b|\bUPDATE\b/i', $sql);
});
$t->check('the poll with an existing row writes and locks nothing', [] === array_values($locking));

$t->finish();
