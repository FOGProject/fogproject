<?php
/**
 * DirectoryRejoin::change() answers a deadlock (InnoDB 1213) with
 * rejoin_deadlock_retry and a changed-since-read conflict (MariaDB 1020, seen
 * under snapshot isolation) with rejoin_plan_changed, both as 409, and rolls
 * the transaction back first. The server error is injected by a PDO that
 * throws on the locking read of hostDirectory.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-deadlock-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory deadlock DB: FOG_TEST_DSN not set\n";
    exit(0);
}

/** A PDO that raises a chosen driver error on the hostDirectory locking read. */
class DeadlockInjectingPdo extends \PDO
{
    public $driverCode = 0;

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->driverCode && preg_match('/FROM\s+hostDirectory\b.*\bFOR\s+UPDATE\b/is', (string)$query)) {
            $e = new \PDOException('injected driver error ' . $this->driverCode);
            $e->errorInfo = ['40001', $this->driverCode, 'injected'];
            throw $e;
        }
        return parent::prepare($query, $options);
    }
}

$directoryDbName = 'agent-directory-deadlock-db';
require __DIR__ . '/lib/directory-db.php';
$t = new FogChecks();

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$injecting = new DeadlockInjectingPdo(
    directoryTestDsn(),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
);
directoryTestBindPdodb($injecting);

$host = (new FOG\Items\Host())->set('id', 7);
$outcome = static function () use ($host, $injecting) {
    try {
        FOG\Agent\DirectoryRejoin::change($host, static function (&$config, &$state) {
            $state['phase'] = 'started';
        });
    } catch (\RuntimeException $e) {
        return [$e->getMessage(), $e->getCode(), $injecting->inTransaction()];
    }
    return ['', 0, $injecting->inTransaction()];
};

$injecting->driverCode = 1213;
$t->check('a deadlock answers rejoin_deadlock_retry, 409, rolled back', ['rejoin_deadlock_retry', 409, false] === $outcome());
$injecting->driverCode = 1020;
$t->check('a changed-since-read error answers rejoin_plan_changed, 409, rolled back', ['rejoin_plan_changed', 409, false] === $outcome());
$injecting->driverCode = 0;
$t->check('without an injected error the same change commits', ['', 0, false] === $outcome());

$t->finish();
