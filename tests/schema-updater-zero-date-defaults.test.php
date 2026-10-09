<?php
/**
 * The schema updater must be able to ALTER a table that carries a zero-date
 * DEFAULT.
 *
 * Forum 18266 -- a 1.5.10 upgrade stopped at schema step 284 on MySQL:
 *
 *   Update ID: 284  Error 1067: Invalid default value for 'hostSecTime'
 *   SQL: ALTER TABLE hosts MODIFY COLUMN hostLastDeploy DATETIME NULL ...
 *
 * This branch runs the same statements as step 344. The step names
 * hostLastDeploy, and the error names hostSecTime. An ALTER TABLE re-checks
 * EVERY column's default, not only the one it changes. Tables first built on
 * MySQL 5.x gave a second TIMESTAMP NOT NULL column an implicit DEFAULT
 * '0000-00-00 00:00:00', and MySQL's stock sql_mode (NO_ZERO_DATE with
 * STRICT_TRANS_TABLES) refuses that default. PDODB cleared sql_mode on every
 * connection until GH-1245, so no update met the check before the step that
 * removes the default. MariaDB's default sql_mode has no NO_ZERO_DATE, which
 * is why no MariaDB server showed it. tests/schema-executes.test.php cannot
 * see it either: a fresh replay on MySQL 8.0 never creates the implicit
 * default.
 *
 * Which columns carry such a default depends on each server's history, so no
 * step can list them. SchemaUpdaterPage drops NO_ZERO_DATE and
 * NO_ZERO_IN_DATE from its own session for the steps and the reconcile, and
 * puts the sql_mode back before the row seed. This test drives those two
 * helpers against a real server, on a scratch table, under MySQL's stock
 * flags. It also runs the same ALTER WITHOUT them and requires 1067, so the
 * fixture is shown to be the reported bug.
 *
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=13313;dbname=fogtest' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-updater-zero-date-defaults.test.php
 *
 * SKIPs without FOG_TEST_DSN. Creates and drops one table in that database.
 *
 * Usage: php tests/schema-updater-zero-date-defaults.test.php
 * Exit status 0 = pass (or skip), 1 = fail.
 */

$dsn = getenv('FOG_TEST_DSN');
if ($dsn === false || $dsn === '') {
    echo "SKIP  no FOG_TEST_DSN set; zero-date relax not checked on a server\n";
    exit(0);
}
if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: FOG_TEST_DSN is set but the pdo_mysql driver is missing.\n");
    exit(1);
}
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$user = ($user === false) ? 'root' : $user;
$pass = ($pass === false) ? '' : $pass;

try {
    $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]);
} catch (\PDOException $e) {
    fwrite(STDERR, 'FAIL: cannot connect: ' . $e->getMessage() . "\n");
    exit(1);
}

require __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('zerodate');

/**
 * The part of PDODB the two helpers touch, on a real connection: query(),
 * fetch()->get() and escape(). Real SQL semantics, which is the point --
 * the fake database in the harness would accept any sql_mode string.
 */
class ZeroDateRealDb
{
    /** @var \PDO */
    private $_pdo;

    /** @var array */
    private $_row = [];

    public function __construct(\PDO $pdo)
    {
        $this->_pdo = $pdo;
    }

    public function query($sql)
    {
        $stmt = $this->_pdo->query((string)$sql);
        $this->_row = [];
        if ($stmt && $stmt->columnCount() > 0) {
            $this->_row = (array)$stmt->fetch(\PDO::FETCH_ASSOC);
        }
        return $this;
    }

    public function fetch()
    {
        return $this;
    }

    public function get($field)
    {
        return $this->_row[$field] ?? null;
    }

    public function escape($value)
    {
        return $this->_pdo->quote((string)$value);
    }
}

FogTestHarness::setStatic('FOGBase', 'DB', new ZeroDateRealDb($pdo));

$page = 'FOG\\Pages\\SchemaUpdaterPage';
$relax = new \ReflectionMethod($page, '_relaxZeroDateChecks');
$relax->setAccessible(true);
$restore = new \ReflectionMethod($page, '_restoreSqlMode');
$restore->setAccessible(true);

$table = 'zzci_zerodate_' . getmypid();
register_shutdown_function(
    static function () use ($pdo, $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
);

$mode = static function () use ($pdo) {
    return (string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
};
$alter = "ALTER TABLE `$table` MODIFY COLUMN `lastDeploy` DATETIME NULL DEFAULT NULL";
$failures = [];

// The shape forum 18266's hosts table had: the column the step changes, plus
// a second one whose default is a zero date. Built with the checks off, the
// way a pre-GH-1245 server built it.
$pdo->exec("SET SESSION sql_mode = ''");
if (false === $pdo->exec(
    "CREATE TABLE `$table` ("
    . '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,'
    . '`lastDeploy` DATETIME NOT NULL,'
    . "`secTime` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'"
    . ') ENGINE=InnoDB'
)) {
    fwrite(STDERR, 'FAIL: could not build the fixture: ' . $pdo->errorInfo()[2] . "\n");
    exit(1);
}

// MySQL 8.0's stock flags, set explicitly so a MariaDB server tests the same
// thing.
$pdo->exec(
    "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,"
    . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
);
$before = $mode();

// Control: without the helpers this is the reported error.
$pdo->exec($alter);
if (1067 !== (int)($pdo->errorInfo()[1] ?? 0)) {
    $failures[] = sprintf(
        'control: the ALTER without the relaxed sql_mode answered %s, not 1067.'
        . ' The fixture no longer reproduces forum 18266.',
        var_export($pdo->errorInfo()[1] ?? null, true)
    );
}

$saved = $relax->invoke(null);
$relaxed = $mode();
$ok = false !== $pdo->exec($alter);
$err = $ok ? '' : (string)$pdo->errorInfo()[2];
$restore->invoke(null, $saved);
$after = $mode();

if ($saved !== $before) {
    $failures[] = "_relaxZeroDateChecks() returned '$saved', not the mode it replaced ('$before').";
}
if (false !== stripos($relaxed, 'NO_ZERO')) {
    $failures[] = "relaxed sql_mode still holds a NO_ZERO flag: '$relaxed'.";
}
if (false === stripos($relaxed, 'STRICT_TRANS_TABLES')) {
    $failures[] = "relaxed sql_mode dropped STRICT_TRANS_TABLES: '$relaxed'. Only the two zero-date flags may go.";
}
if (!$ok) {
    $failures[] = "the ALTER failed under the relaxed sql_mode: $err";
}
if ($after !== $before) {
    $failures[] = "_restoreSqlMode() left '$after', not '$before'. Every later query on this connection runs relaxed.";
}

if ($failures) {
    fwrite(STDERR, "FAIL:\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}
echo "PASS: a zero-date default no longer blocks a schema step's ALTER (forum 18266)\n";
exit(0);
