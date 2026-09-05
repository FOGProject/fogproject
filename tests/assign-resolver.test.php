<?php
/**
 * Proves FOG\Assign\Resolver resolves what ADR 0038 promises -- the order for
 * snapins and printers (decision 6), and the three tiers for modules.
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
 *   13   the saID tiebreak, pinned on the source -- see the comment there.
 *   14-19 modules, the third grant and the only one that is a switch. A
 *        host-direct OFF beats every group that grants the module; the same
 *        grant still reaches a host that has not said OFF (one module, one
 *        grant, opposite answers, so neither "OFF is global" nor "the grant
 *        wins" survives); the union comes back ascending by module id rather
 *        than host-then-group; a module two of a host's groups grant appears
 *        once; a host with nothing is still a key; and a failed module read
 *        throws for the same decision-9 reason as check 12.
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
    'moduleStatusByHost',
    'groupModuleAssoc',
    'powerManagement',
    'groupPowerManagement',
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
 *          The two that are NOT the default carry '0' and 0. It used to be
 *          '0' and '', the two spellings a 1.5-origin varchar(2) really
 *          held, but schema step 426 normalizes '' to '0' and then MODIFYs
 *          the column to tinyint(1) -- so '' is no longer a value this
 *          column can hold, and seeding it is refused outright by MariaDB
 *          in strict mode rather than exercising anything. The check that
 *          remains is the one that still can fail: a comparison loose
 *          enough to read a quoted '0' as truthy makes the FIRST printer
 *          the default, which is a wrong answer nothing would report.
 * host 11: no direct printers; group 4 (alpha, first) names no default,
 *          group 5 (beta) does -> beta's default, and only because alpha
 *          declined it.
 * host 12: nothing at all -> null, not an error.
 */
$pdo->exec(
    "INSERT INTO `printerAssoc` (`paID`,`paHostID`,`paPrinterID`,`paIsDefault`) "
    . "VALUES (1,10,900,'0'),(2,10,901,'1'),(3,10,902,0)"
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

/*
 * MODULES -- the third grant, and the only one that is a switch.
 *
 * The scenario is built so the three tiers are separable. Reusing the same
 * groups keeps the membership fixture honest: host 13 has no `hosts` row at
 * all and is in group 3, so it proves the manager-bypass property a second
 * time on a table nobody has queried yet.
 *
 *   host 10  direct ON  950, direct OFF 901; in groups 1, 2, 3
 *   host 11  no direct rows at all;          in groups 4, 5
 *   host 12  nothing anywhere, in no group
 *   host 13  no `hosts` row;                 in group 3
 *
 *   group 3 grants 901 and 902
 *   group 1 grants 903
 *   group 5 grants 910
 *   group 4 grants 910 and 911
 *
 * 950 is deliberately the HIGHEST id in host 10's answer while being the
 * host-direct one. A resolver that appends the group grants after the direct
 * rows without sorting returns [950, 902, 903]; the promise is ascending by
 * id, so the right answer is [902, 903, 950] and the two are different lists
 * rather than the same list in a different order.
 *
 * 901 is the load-bearing pair. Host 10 says OFF and group 3 grants it, so
 * host 10 must NOT have it -- and host 13, in the same group with nothing of
 * its own to say, MUST. One module, one grant, opposite answers: a resolver
 * that treats OFF as global, or that lets the grant win, fails one of the
 * two whichever way it is wrong.
 */
$pdo->exec(
    "INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`) "
    . "VALUES (10,950,1),(10,901,0)"
);
$pdo->exec(
    "INSERT INTO `groupModuleAssoc` (`gmaGroupID`,`gmaModuleID`) VALUES "
    . "(3,901),(3,902),(1,903),(5,910),(4,910),(4,911)"
);

$mods = \FOG\Assign\Resolver::resolveModules([10, 11, 12, 13]);

$check(
    'a host-direct OFF beats every group that grants the module',
    !in_array(901, $mods[10] ?? [], true)
);
$check(
    'the same grant still reaches a host that has not said OFF',
    ($mods[13] ?? []) === [901, 902]
);
$check(
    'host ON and group grants union, ascending by module id',
    ($mods[10] ?? []) === [902, 903, 950]
);
$check(
    'a module granted by two of a host\'s groups appears once',
    ($mods[11] ?? []) === [910, 911]
);
$check(
    'a host with nothing anywhere is still a key, with an empty list',
    array_key_exists(12, $mods) && ($mods[12] ?? null) === []
);

/*
 * THE DEFAULT ON msState IS LOAD-BEARING.
 *
 * FOGController::addRemItem() used to append an explicit `state` of 1 to
 * every module row, because the column was a varchar(1) NOT NULL DEFAULT ''
 * and an insert that omitted it wrote the empty string. Schema step 409 made
 * it tinyint(1) NOT NULL DEFAULT 1, so that special case was removed and the
 * database now supplies the value.
 *
 * Which means the DEFAULT and the generic insert are one mechanism held in
 * two files. Under ADR 0038 a state of 0 is a host saying OFF and beats every
 * group grant, so if the default is ever dropped or flipped, every module
 * added through the generic path silently becomes the strongest statement in
 * the system -- and nothing would fail. This inserts a row the way
 * addRemItem() now does, against the REAL DDL out of the manifest, and reads
 * the answer back through the resolver rather than off the column: what
 * matters is not that the column says 1, it is that the module comes out
 * enabled.
 */
$pdo->exec(
    "INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`) VALUES (12,960)"
);
$defaulted = \FOG\Assign\Resolver::resolveModules([12]);
$check(
    'a module row inserted without a state resolves as enabled',
    ($defaulted[12] ?? []) === [960]
);

/*
 * 20-27: power management, the fourth grant (ADR 0038).
 *
 * A schedule is the one grant whose IDENTITY is a composite -- a cron
 * expression plus an action -- rather than an id, so the dedup key is a
 * string this resolver builds. That is the thing worth seeding a database
 * for: every way of getting it wrong produces a list that still looks like a
 * list of schedules.
 *
 * host 10 (groups 3 'zebra' order 1, 1 'middle' order 5, 2 'aaa-last' order 9):
 *   direct  pmID 1  `0 2 * * *` reboot        <- first
 *           pmID 2  `30 3 * * *` shutdown     <- second
 *           pmID 3  `0 4 * * *` shutdown  ON DEMAND -- must NOT appear
 *   grants  group 3 `15 1 * * *` wol          <- third (zebra resolves first)
 *           group 3 `0 2 * * *` reboot        -- identical to the host's, so
 *                                                deduped away
 *           group 1 `45 5 * * -1` shutdown    <- fourth, dow normalized to 7
 *           group 2 `15 1 * * *` wol          -- same as group 3's, deduped
 *
 * The ids fight the answer here too: taking the group rows in gpmID order
 * without the group ordering would put middle's 45-past before zebra's, and
 * ordering groups by id would put middle(1) before zebra(3).
 */
$pdo->exec(
    "INSERT INTO `powerManagement` "
    . "(`pmID`,`pmHostID`,`pmMin`,`pmHour`,`pmDom`,`pmMonth`,`pmDow`,"
    . "`pmAction`,`pmOndemand`) VALUES "
    . "(1,10,'0','2','*','*','*','reboot',0),"
    . "(2,10,'30','3','*','*','*','shutdown',0),"
    . "(3,10,'0','4','*','*','*','shutdown',1)"
);
$pdo->exec(
    "INSERT INTO `groupPowerManagement` "
    . "(`gpmID`,`gpmGroupID`,`gpmMin`,`gpmHour`,`gpmDom`,`gpmMonth`,`gpmDow`,"
    . "`gpmAction`) VALUES "
    . "(1,1,'45','5','*','*','-1','shutdown'),"
    . "(2,3,'15','1','*','*','*','wol'),"
    . "(3,3,'0','2','*','*','*','reboot'),"
    . "(4,2,'15','1','*','*','*','wol')"
);

$pm = \FOG\Assign\Resolver::resolvePowerManagement([10, 11, 12, 13]);
$flat = static function ($list) {
    return array_map(
        static function ($s) {
            return $s['cron'] . '|' . $s['action'];
        },
        (array)$list
    );
};

// 20: host-direct first, in pmID order, then the groups in resolved order.
$check(
    'schedules resolve host-direct first, then groups in decision-6 order',
    $flat($pm[10] ?? []) === [
        '0 2 * * *|reboot',
        '30 3 * * *|shutdown',
        '15 1 * * *|wol',
        '45 5 * * 7|shutdown'
    ]
);

// 21: the on-demand row is not a schedule. Asserted on its own rather than
// left implicit in check 20, because it is the one exclusion that would look
// like a working resolver -- the client would simply shut a machine down at
// 04:00 every day forever, having been handed a task as a cron.
$check(
    'an on-demand host row is excluded from the resolved schedules',
    !in_array('0 4 * * *|shutdown', $flat($pm[10] ?? []), true)
);

// 22: the identical cron+action reached host 10 both directly and from group
// 3, and appears once at the HOST's position. A dedup keyed on anything else
// -- the row id, the action alone -- gives a different list.
$check(
    'a schedule reached directly and by grant appears once, at the host position',
    1 === count(
        array_keys($flat($pm[10] ?? []), '0 2 * * *|reboot', true)
    )
);

// 23: groups 3 and 2 grant the SAME wol schedule. Two groups, one
// instruction: waking a machine twice is harmless, but rebooting one twice is
// not, and the dedup has to be a property of the resolver rather than of
// which action it happens to be.
$check(
    'the same schedule granted by two groups appears once',
    1 === count(array_keys($flat($pm[10] ?? []), '15 1 * * *|wol', true))
);

// 24: `wol` comes back. The resolver serves both the client (which wants
// shutdown and reboot) and TaskScheduler (which wants wake), so filtering
// here would mean a second resolver and a second ordering.
$check(
    'wol schedules are returned rather than filtered by the resolver',
    in_array('15 1 * * *|wol', $flat($pm[10] ?? []), true)
);

// 25: a -1 weekday becomes 7. FOG's cron picker writes -1 for Sunday and
// Quartz -- what the FOG client schedules against -- refuses it, so a
// Sunday-night shutdown would silently never run.
$check(
    'a -1 weekday is normalized to 7 in the cron expression',
    in_array('45 5 * * 7|shutdown', $flat($pm[10] ?? []), true)
);

// 25b: a WILDCARD weekday survives. This is its own check because it is its
// own bug, and one that only appears on PHP 8: the inline version this
// replaced read `if ($dow < 0)`, and `'*' < 0` is TRUE there -- comparing a
// non-numeric string with a number casts the NUMBER to string, and '*' (42)
// sorts below '0' (48). Every daily schedule was therefore handed to the
// client as `... 7`, Sundays only, on any server running PHP 8. On 7.4 the
// same expression is false. Nothing about FOG had to change for a working
// schedule to stop running; the server just had to be upgraded.
$check(
    'a wildcard weekday is left alone rather than rewritten to 7',
    in_array('30 3 * * *|shutdown', $flat($pm[10] ?? []), true)
    && !in_array('30 3 * * 7|shutdown', $flat($pm[10] ?? []), true)
);

// 26: a host with nothing anywhere is still a key, for the reason
// resolveSnapins() gives.
$check(
    'a host with no schedules is still a key, with an empty list',
    array_key_exists(12, $pm) && [] === $pm[12]
);

// 27: host 13 has no `hosts` row at all -- the manager-bypass property. A
// read that went through a manager would pick up `hostMAC.hmPrimary = '1'`
// in its WHERE and drop it, and a machine that quietly stops shutting down
// reports itself to nobody.
$check(
    'a host present only in the association tables still resolves its grants',
    $flat($pm[13] ?? []) === ['15 1 * * *|wol', '0 2 * * *|reboot']
);

// 28: the decision-9 gate on this resolver's own table.
$pdo->exec('DROP TABLE `groupPowerManagement`');
$threw = false;
$message = '';
try {
    \FOG\Assign\Resolver::resolvePowerManagement([10]);
} catch (\RuntimeException $e) {
    $threw = true;
    $message = $e->getMessage();
}
$check(
    'a failed schedule read throws rather than resolving to nothing',
    $threw && 0 === strpos($message, 'Assignment resolution failed')
);

// The decision-9 gate again, on the table this resolver added. Same reason:
// under the client's module protocol an empty answer is a legitimate "all
// modules off", so a read that fails silently switches the fleet off rather
// than reporting anything.
$pdo->exec('DROP TABLE `groupModuleAssoc`');
$threw = false;
$message = '';
try {
    \FOG\Assign\Resolver::resolveModules([10]);
} catch (\RuntimeException $e) {
    $threw = true;
    $message = $e->getMessage();
}
$check(
    'a failed module read throws rather than resolving to nothing',
    $threw && 0 === strpos($message, 'Assignment resolution failed')
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
