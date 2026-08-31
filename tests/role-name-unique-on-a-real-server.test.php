<?php
/**
 * Schema step 401 restores `roles`.`rName`'s UNIQUE index on a REAL server.
 *
 * tests/role-name-unique-restored.test.php drives the step against a stub and
 * pins its policy: rename, never delete, first holder keeps the name, skip the
 * index rather than abort. That stub computes duplicates in PHP, so there is
 * one property it cannot test and must not claim to -- whether the duplicate
 * search agrees with the index about what a duplicate IS.
 *
 * It has to, and the reason is collation. `rName` is utf8mb3_general_ci, so
 * `ADD UNIQUE KEY` rejects 'Techs' alongside 'techs'. If the search were
 * case-sensitive it would pass over that pair, and the ALTER would then fail
 * with errno 1062 partway through an upgrade -- on a table the step had
 * already renamed rows in. Only a real server can say whether the two agree,
 * because only a real server applies the collation.
 *
 * Runs on every server CI covers -- MariaDB 10.5, MariaDB 11.8 and MySQL 8.0
 * -- which also makes it the check that a dialect difference between them
 * cannot hide in.
 *
 * Skips without FOG_TEST_DSN, exactly as tests/schema-executes.test.php does.
 *
 * Usage: FOG_TEST_DSN=... php tests/role-name-unique-on-a-real-server.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$dsn = getenv('FOG_TEST_DSN');
if ($dsn === false || $dsn === '') {
    echo "SKIP  no FOG_TEST_DSN set; role index not checked on a server\n";
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

$db = 'fog_role_index_test';

require __DIR__ . '/lib/fog-schema-collector.php';

$root = dirname(__DIR__);
$fogSchema = 0;
if (preg_match(
    "/define\('FOG_SCHEMA',\s*(\d+)\)/",
    (string)file_get_contents($root . '/packages/web/src/Base/System.php'),
    $m
)) {
    $fogSchema = (int)$m[1];
}
$steps = fogCollectSchemaSteps(
    $root . '/packages/web/commons/schema.php',
    $db,
    $fogSchema
);

// The step under test, pinned by number rather than taken as the last one.
define('ROLE_INDEX_STEP', 401);

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

try {
    $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
} catch (\PDOException $e) {
    fwrite(STDERR, 'FAIL: cannot connect: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * A `self::$DB` that speaks to the real server through PDO.
 */
class RealSchemaDB extends SchemaStubDB
{
    /** @var \PDO */
    public $pdo;

    /** @var \PDOStatement|null */
    private $_stmt = null;

    /** @var bool whether the last statement returned rows */
    private $_rows = false;

    public function query($query = null, ...$rest)
    {
        $params = [];
        foreach ($rest as $arg) {
            if (is_array($arg) && count($arg)) {
                $params = $arg;
            }
        }
        $this->_stmt = $this->pdo->prepare((string)$query);
        $this->_stmt->execute($params ?: null);
        $this->_rows = (bool)preg_match('/^\s*SELECT\b/i', (string)$query);
        return $this;
    }

    public function fetch($what = null, ...$rest)
    {
        $this->_all = in_array('fetch_all', $rest, true)
            || 'fetch_all' === $what;
        return $this;
    }

    /** @var bool */
    private $_all = false;

    public function get($key = null)
    {
        if (!$this->_rows || null === $this->_stmt) {
            return null;
        }
        return $this->_all
            ? $this->_stmt->fetchAll(\PDO::FETCH_ASSOC)
            : $this->_stmt->fetch(\PDO::FETCH_ASSOC);
    }
}

// A scratch database, not the one the DSN names: this test plants duplicate
// roles and drops them again, which must never touch a real FOG database.
// A FOG service account deliberately has no CREATE DATABASE right, so that is
// a SKIP rather than a failure -- the same distinction schema-executes.test.php
// draws.
try {
    $pdo->exec("DROP DATABASE IF EXISTS `$db`");
    $pdo->exec("CREATE DATABASE `$db`");
} catch (\PDOException $e) {
    printf("SKIP  %s may not CREATE DATABASE; role index not checked\n", $user);
    exit(0);
}
$pdo->exec("USE `$db`");

// The table as the 1.5 accesscontrol plugin left it: no UNIQUE on rName.
// That absence is the whole defect, so it is built rather than assumed.
$pdo->exec(
    "CREATE TABLE `roles` ("
    . " `rID` int(11) NOT NULL AUTO_INCREMENT,"
    . " `rName` varchar(255) NOT NULL,"
    . " `rDesc` longtext NOT NULL,"
    . " PRIMARY KEY (`rID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci"
);
// Case-variant duplicates: the pair a case-sensitive search would miss.
$pdo->exec(
    "INSERT INTO `roles` (`rID`, `rName`, `rDesc`) VALUES"
    . " (1, 'Administrators', ''),"
    . " (2, 'Techs', ''),"
    . " (3, 'techs', ''),"
    . " (4, 'TECHS', '')"
);

$sdb = new RealSchemaDB();
$sdb->pdo = $pdo;
$prev = SchemaCollector::$DB;
SchemaCollector::$DB = $sdb;
foreach ((array)$steps[ROLE_INDEX_STEP - 1] as $update) {
    if (is_callable($update)) {
        $update();
    }
}
SchemaCollector::$DB = $prev;

// 1. The index exists. This is the assertion that could only pass if the
//    duplicate search agreed with the collation -- ADD UNIQUE would have
//    thrown 1062 otherwise.
$idx = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.STATISTICS"
    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles'"
    . " AND INDEX_NAME = 'rName' AND NON_UNIQUE = 0"
)->fetch(\PDO::FETCH_ASSOC);
$check(
    'the UNIQUE index is created on a server that had case-variant duplicates',
    (int)($idx['n'] ?? 0) === 1
);

// 2. Nothing was deleted. Six tables CASCADE off roles.rID, so a delete here
//    would silently strip permissions and assignments.
$rows = $pdo->query('SELECT `rID`, `rName` FROM `roles` ORDER BY `rID`')
    ->fetchAll(\PDO::FETCH_ASSOC);
$check('every role survived', count($rows) === 4);

// 3. The first holder kept its name, so a by-name lookup is unchanged.
$check(
    'the first holder of the name keeps it',
    ($rows[1]['rName'] ?? '') === 'Techs'
);
$check(
    'the untouched role is untouched',
    ($rows[0]['rName'] ?? '') === 'Administrators'
);

// 4. And the renames really are distinct UNDER THE COLLATION, which is a
//    different statement from being distinct as PHP strings.
$dupes = $pdo->query(
    'SELECT COUNT(*) AS n FROM (SELECT `rName` FROM `roles`'
    . ' GROUP BY `rName` HAVING COUNT(*) > 1) d'
)->fetch(\PDO::FETCH_ASSOC);
$check('no two names collide under the column collation', (int)($dupes['n'] ?? 0) === 0);

// 5. The index is enforced from here on -- the guarantee the whole step is
//    for. Without this the four checks above could all hold on a table that
//    still accepted a duplicate tomorrow.
$rejected = false;
try {
    $pdo->exec("INSERT INTO `roles` (`rName`, `rDesc`) VALUES ('administrators', '')");
} catch (\PDOException $e) {
    $rejected = '23000' === $e->getCode();
}
$check(
    'the server now REJECTS a case-variant duplicate insert',
    true === $rejected
);

// 6. Re-running is a no-op rather than an error: ADD UNIQUE has no
//    IF NOT EXISTS on these servers, so the step must probe.
$again = true;
try {
    SchemaCollector::$DB = $sdb;
    foreach ((array)$steps[ROLE_INDEX_STEP - 1] as $update) {
        if (is_callable($update)) {
            $update();
        }
    }
    SchemaCollector::$DB = $prev;
} catch (\Throwable $e) {
    $again = false;
    $failures[] = 're-running step 401 threw: ' . $e->getMessage();
    $checks++;
}
if ($again) {
    $check('re-running the step on a converged server is a no-op', true);
}

$pdo->exec("DROP DATABASE IF EXISTS `$db`");

if (count($failures)) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " of $checks check(s):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
printf("ok  %d checks passed\n", $checks);
exit(0);
