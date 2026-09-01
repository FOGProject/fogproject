<?php
/**
 * Proves FOG\Assign\Resolver resolves the order ADR 0038 decision 6 promises.
 *
 * The resolver is the single place that answers "what is this host actually
 * assigned". Everything it gets wrong is silent: a snapin runs in the wrong
 * order, a printer quietly disappears from a machine, a host with no primary
 * MAC resolves to nothing. None of those raise anything, so the only way to
 * know is to seed a database and read the answer back.
 *
 * Seeds a scratch database with the REAL table definitions, lifted out of
 * commons/schema-expected.php rather than retyped, so a column that changes
 * shape cannot leave this test passing against a table nobody has any more.
 *
 * WHAT EACH CASE IS ABLE TO CATCH -- every one of these was made to fail
 * before it was kept:
 *
 *   1-2  host-direct order, sequence first. The saID tiebreak underneath
 *        it is check 13 and is pinned differently -- see the comment there,
 *        because a behavioral assertion genuinely cannot cover it.
 *   3-4  group grants come after host-direct, and groups run in groupOrder
 *        order rather than alphabetical -- the specific thing decision 6
 *        exists to guarantee, since a rename must never reorder installs.
 *   5    the groupName tiebreak, for the install that never sets an order.
 *   6    dedupe keeps the HOST's position, not the group's.
 *   7    a host id with nothing still comes back as a key. A missing key and
 *        an empty list want opposite handling at the call site.
 *   8    a host that exists ONLY in the association tables still resolves.
 *        This is the manager-bypass property standing in for the primary-MAC
 *        drop: the moment someone rewrites a read to go through a manager,
 *        the class chain reaches Host, `hostMAC.hmPrimary = '1'` lands in the
 *        WHERE, and hosts start vanishing from their own printer lists.
 *   9-11 printer default precedence: host-direct wins, otherwise the first
 *        group in the resolved order that names one, otherwise null.
 *   12   a failed query THROWS. Decision 9: an empty list is a legitimate
 *        answer meaning "no printers", so a resolver that returns one on
 *        failure strips the fleet under printer mode `ar`, one machine at a
 *        time, for as long as nobody notices.
 *
 * Skips without FOG_TEST_DSN, exactly as tests/schema-executes.test.php does.
 *
 * Usage: FOG_TEST_DSN=... php tests/assign-resolver.test.php
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
    echo "SKIP  no FOG_TEST_DSN set; the resolver is not checked on a server\n";
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

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';

// A scratch database, never the one the DSN names. This one plants and drops
// tables, so it must not be able to land in a real FOG database even if the
// DSN points at one.
$db = 'fog_assign_resolver_test';

$tmp = sys_get_temp_dir() . '/fog-assign-resolver-test-' . getmypid();
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
if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
}

$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
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

try {
    $pdo = new \PDO(
        $dsn,
        $user,
        $pass,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
} catch (\PDOException $e) {
    fwrite(STDERR, 'FAIL: cannot connect: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * The `self::$DB` the resolver talks to, over a real PDO handle.
 *
 * Mirrors the PDODB contract the resolver actually depends on and NOTHING
 * else: query() records a message on ->error instead of throwing, fetch()
 * takes a fetch style plus the 'fetch_all' marker, and get() returns the
 * rows. ->error being a MESSAGE on failure and the boolean false on success
 * is the part that matters -- the resolver tests `false !== $res->error`,
 * and a stub that used a plain boolean would let a broken check pass.
 */
class ResolverTestDB
{
    /** @var \PDO */
    public $pdo;

    /** @var string|bool false when the last statement succeeded */
    public $error = false;

    /** @var \PDOStatement|null */
    private $_stmt = null;

    /** @var bool */
    private $_all = false;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query($sql = null, $data = [], $params = [])
    {
        $this->error = false;
        $this->_stmt = null;
        try {
            $this->_stmt = $this->pdo->prepare((string)$sql);
            $this->_stmt->execute($params ?: null);
        } catch (\PDOException $e) {
            $this->_stmt = null;
            $this->error = $e->getMessage();
        }
        return $this;
    }

    public function fetch($type = null, $fetchType = null, $params = false)
    {
        $this->_all = ('fetch_all' === $fetchType);
        return $this;
    }

    public function get($key = null)
    {
        if (null === $this->_stmt) {
            return null;
        }
        return $this->_all
            ? $this->_stmt->fetchAll(\PDO::FETCH_ASSOC)
            : $this->_stmt->fetch(\PDO::FETCH_ASSOC);
    }
}

$pdo->exec("DROP DATABASE IF EXISTS `$db`");
$pdo->exec("CREATE DATABASE `$db`");
register_shutdown_function(
    function () use ($pdo, $db) {
        try {
            $pdo->exec("DROP DATABASE IF EXISTS `$db`");
        } catch (\PDOException $e) {
            // best effort; the scratch database is named, not guessed
        }
    }
);
$pdo->exec("USE `$db`");

// The real definitions, not retyped ones. schema-expected.php is generated
// DATA, so a table that changes shape changes here too rather than leaving
// this test green against a table that no longer exists in that form.
$manifest = include $webroot . '/commons/schema-expected.php';
$expected = $manifest['tables'] ?? [];
$need = [
    'groups',
    'groupMembers',
    'snapinAssoc',
    'printerAssoc',
    'groupSnapinAssoc',
    'groupPrinterAssoc',
];
foreach ($need as $table) {
    if (!isset($expected[$table]['create'])) {
        fwrite(
            STDERR,
            "FAIL: commons/schema-expected.php has no `$table`; regenerate it\n"
        );
        exit(1);
    }
    $pdo->exec($expected[$table]['create']);
}

$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);
$dbProp->setValue(null, new ResolverTestDB($pdo));

/*
 * The scenario, chosen so that the RIGHT answer and the several plausible
 * WRONG ones are different lists rather than the same list in a different
 * order.
 *
 * THE IDS FIGHT THE ANSWER ON PURPOSE. Ordering by groupID alone, or by
 * groupName alone, must each produce a DIFFERENT list from the right one --
 * otherwise the assertions pass under a resolver that ignores groupOrder
 * entirely, which is the single thing decision 6 is about. A first cut of
 * this test had group ids running 1,2,3 in resolved order, and ordering by
 * id alone sailed through it.
 *
 * groups, and the order they must resolve in:
 *   3  'zebra'     groupOrder 1  -> first,  though last by name and by id
 *   1  'middle'    groupOrder 5  -> second, though first by id
 *   2  'aaa-last'  groupOrder 9  -> third,  though first by name
 *   5  'alpha'     groupOrder 0  \ both sit at 0, so they fall to the name
 *   4  'beta'      groupOrder 0  / tiebreak: alpha(5) before beta(4), which
 *                                  is the opposite of their id order
 *
 * host 10 is in groups 1, 2, 3 and has three direct snapins.
 * host 11 is in groups 4 and 5 only.
 * host 12 is in no group and has nothing.
 * host 13 exists ONLY here -- no `hosts` row at all.
 */
$pdo->exec(
    "INSERT INTO `groups` (`groupID`,`groupName`,`groupOrder`) VALUES "
    . "(3,'zebra',1),(1,'middle',5),(2,'aaa-last',9),"
    . "(5,'alpha',0),(4,'beta',0)"
);
$pdo->exec(
    "INSERT INTO `groupMembers` (`gmHostID`,`gmGroupID`) VALUES "
    . "(10,1),(10,2),(10,3),(11,4),(11,5),(13,3)"
);

// Both direct rows sit at sequence 0 on purpose: that is what every row
// written before appendSnapinSequence() numbered them looks like, and the
// saID tiebreak is the only thing that makes the answer repeatable.
$pdo->exec(
    "INSERT INTO `snapinAssoc` (`saID`,`saHostID`,`saSnapinID`,`saSequence`) "
    . "VALUES (1,10,700,0),(2,10,701,0),(3,10,702,5)"
);
// 701 is ALSO granted by group 1. It must stay at the host's position
// (second), not move to where the group would put it.
$pdo->exec(
    "INSERT INTO `groupSnapinAssoc` "
    . "(`gsaID`,`gsaGroupID`,`gsaSnapinID`,`gsaSequence`) VALUES "
    . "(1,3,800,2),(2,3,801,1),(3,3,701,3),"
    . "(4,1,810,0),(5,2,820,0),"
    . "(6,5,830,0),(7,4,840,0)"
);

$resolved = \FOG\Assign\Resolver::resolveSnapins([10, 11, 12, 13]);

// 1-2: host-direct first, sequence then id. 702 has sequence 5 so it sorts
// after both zero-sequence rows; 700 before 701 on id alone.
$check(
    'host-direct snapins lead the list, ordered by sequence then id',
    array_slice($resolved[10] ?? [], 0, 3) === [700, 701, 702]
);
// 3-4-6: then groups in groupOrder order -- zebra(1), middle(5), aaa-last(9)
// -- and within group 1 by gsaSequence: 801(1), 800(2), then 701 which is
// dropped as a duplicate because the host already placed it.
$check(
    'group grants follow, groups in groupOrder and grants in gsaSequence',
    ($resolved[10] ?? []) === [700, 701, 702, 801, 800, 810, 820]
);
$check(
    'a snapin granted twice keeps the position the HOST gave it',
    array_search(701, $resolved[10] ?? [], true) === 1
);
// 5: groups 4 and 5 both sit at groupOrder 0, so alpha precedes beta.
$check(
    'groups at the same groupOrder fall back to groupName',
    ($resolved[11] ?? []) === [830, 840]
);
// 7: present as a key, empty as a value.
$check(
    'a host with nothing is still a key in the result',
    array_key_exists(12, $resolved) && [] === $resolved[12]
);
// 8: host 13 has no `hosts` row and no MAC. It must still resolve.
$check(
    'a host with no hosts row still resolves from its associations',
    ($resolved[13] ?? null) === [801, 800, 701]
);

/*
 * Printers. paIsDefault is varchar(2) here, which is what a 1.5-origin
 * database still carries, so the values planted are the strings it really
 * holds rather than ints that happen to compare equal.
 *
 * host 10: three direct printers, one of them the default -> direct wins.
 *          The two that are NOT the default carry '0' and '' respectively,
 *          which are both spellings a 1.5-origin varchar(2) column really
 *          holds. A comparison loose enough to read either as truthy makes
 *          the FIRST printer the default, which is a wrong answer nothing
 *          would report.
 * host 11: no direct printers; group 4 (alpha, first) names no default,
 *          group 5 (beta) does -> beta's default, and only because alpha
 *          declined it.
 * host 12: nothing at all -> null, not an error.
 */
$pdo->exec(
    "INSERT INTO `printerAssoc` (`paID`,`paHostID`,`paPrinterID`,`paIsDefault`) "
    . "VALUES (1,10,900,'0'),(2,10,901,'1'),(3,10,902,'')"
);
$pdo->exec(
    "INSERT INTO `groupPrinterAssoc` "
    . "(`gpaID`,`gpaGroupID`,`gpaPrinterID`,`gpaIsDefault`) VALUES "
    . "(1,3,910,1),(2,1,901,0),(3,5,920,0),(4,4,930,1)"
);

$printers = \FOG\Assign\Resolver::resolvePrinters([10, 11, 12]);

$check(
    'printers list host-direct first, then group grants, deduplicated',
    ($printers[10]['printers'] ?? null) === [900, 901, 902, 910]
);
// 9: group 1 declares 910 the default, but the host declared 901. The host
// wins, and this is the case that would silently flip a thousand desktops.
$check(
    'a host-direct default beats a group default',
    ($printers[10]['default'] ?? null) === 901
);
// 10: no host-direct default, so the first group in the resolved order that
// names one. alpha comes first and names none, so beta's stands.
$check(
    'with no host default, the first group in order that names one wins',
    ($printers[11]['default'] ?? null) === 930
        && ($printers[11]['printers'] ?? null) === [920, 930]
);
// 11: nothing is a legitimate answer, and it is not an error.
$check(
    'a host with no printers resolves to an empty list and a null default',
    array_key_exists(12, $printers)
        && [] === $printers[12]['printers']
        && null === $printers[12]['default']
);

/*
 * 13: the saID tiebreak, pinned STRUCTURALLY, which needs justifying because
 * this file otherwise refuses to assert on source text.
 *
 * The defect that clause guards is NONDETERMINISM: two snapinAssoc rows both
 * sitting at saSequence 0, which is every row written before
 * appendSnapinSequence() numbered them. Remove `saID` from the ORDER BY and
 * this test still passes -- verified, not assumed -- because InnoDB hands
 * back clustered-index order, which happens to BE id order. A behavioral
 * assertion cannot tell "ordered by id" from "the engine felt like it", so a
 * green run proves nothing about the clause and the test would be claiming
 * coverage it does not have.
 *
 * So the clause itself is the assertion. It anchors the whole ORDER BY rather
 * than grepping for the column name, so a rewrite that reorders the keys is a
 * visible failure rather than a passing grep.
 */
$source = (string)file_get_contents(
    $root . '/packages/web/src/Assign/Resolver.php'
);
$check(
    'the host-direct read still breaks its sequence tie on saID',
    false !== strpos(
        $source,
        'ORDER BY `saHostID`, `saSequence`, `saID`'
    )
);

// 12: the decision-9 gate. A failed read must not look like "no printers".
$pdo->exec('DROP TABLE `groupPrinterAssoc`');
$threw = false;
$message = '';
try {
    \FOG\Assign\Resolver::resolvePrinters([10, 11]);
} catch (\RuntimeException $e) {
    $threw = true;
    $message = $e->getMessage();
}
// The message is asserted, not just the throw: a RuntimeException escaping
// from somewhere else in the call would satisfy a bare catch and leave the
// thing this check exists for -- the resolver refusing to answer -- unproven.
$check(
    'a failed query throws rather than resolving to an empty list',
    $threw && 0 === strpos($message, 'Assignment resolution failed')
);

$pdo->exec('DROP TABLE `groupSnapinAssoc`');
$threw = false;
try {
    \FOG\Assign\Resolver::resolveSnapins([10]);
} catch (\RuntimeException $e) {
    $threw = true;
}
$check('a failed snapin read throws too', $threw);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the resolver did not order what it promises:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(STDERR, sprintf("%d of %d checks failed\n", count($failures), $checks));
    exit(1);
}

printf("PASS  assign resolver: %d checks\n", $checks);
exit(0);
