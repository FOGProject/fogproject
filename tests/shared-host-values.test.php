<?php
/**
 * Proves FOG\Util\SharedHostValues is honest about disagreement, and cheap.
 *
 * Two properties, and the first is the one that costs people machines.
 *
 * 1. HOSTS THAT DISAGREE MUST READ AS DISAGREEING. A form editing forty hosts
 *    with six images shows "(varies)", never one of the six. Showing one is
 *    how an admin confirms what they believe is already set and overwrites
 *    thirty-nine machines. The trap is SQL's own NULL semantics: COUNT(DISTINCT)
 *    SKIPS NULLs, so a column that is set on some hosts and NULL on the rest
 *    counts one distinct value and reports agreement among rows that have
 *    nothing in common. COALESCE is what stops that, and cases 3 and 4 exist
 *    to make sure it is still there.
 *
 * 2. ONE QUERY, whatever the size of the column map or the selection. The DB
 *    stub COUNTS statements, so a rewrite that reads per column or per host --
 *    the natural shape, and a query storm at four hundred hosts -- fails
 *    rather than merely getting slower where nobody is watching.
 *
 * Skips without FOG_TEST_DSN, exactly as tests/schema-executes.test.php does.
 *
 * Usage: FOG_TEST_DSN=... php tests/shared-host-values.test.php
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
    echo "SKIP  no FOG_TEST_DSN set; shared host values not checked on a server\n";
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

// A scratch database, never the one the DSN names.
$db = 'fog_shared_host_values_test';

$tmp = sys_get_temp_dir() . '/fog-shared-host-values-test-' . getmypid();
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
 * The statement counter, kept in $GLOBALS rather than on the stub.
 *
 * Not stylistic. The increment happens inside SharedHostValues, through a
 * $DB static analysis cannot connect back to the stub object, so a property
 * -- instance OR static -- is seen as assigned zero and never touched, and
 * every assertion about the count is reported as always-false. Analysis does
 * not track $GLOBALS, so this is the one shape that lets the count be
 * asserted at all. Both were tried; the note is here so the next person does
 * not retry them.
 */
function svCountQuery()
{
    $GLOBALS['sv_queries'] = (int)($GLOBALS['sv_queries'] ?? 0) + 1;
}

/**
 * Reads the counter and clears it, so each case counts only its own call.
 *
 * @return int statements run since the last call
 */
function svTakeQueryCount()
{
    $n = (int)($GLOBALS['sv_queries'] ?? 0);
    $GLOBALS['sv_queries'] = 0;
    return $n;
}

/**
 * A `self::$DB` over real PDO that COUNTS the statements it is asked to run.
 *
 * The count is the point. "One query per call" is a design property that
 * nothing else can observe -- a per-column rewrite returns identical answers
 * and is simply slower, which is invisible until somebody points the form at
 * four hundred hosts.
 */
class SharedValuesTestDB
{
    /** @var \PDO */
    public $pdo;


    /** @var string|bool */
    public $error = false;

    /** @var \PDOStatement|null */
    private $_stmt = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query($sql = null, $data = [], $params = [])
    {
        svCountQuery();
        $this->error = false;
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
        return $this;
    }

    /**
     * SharedHostValues reads columns off the row by name, so get($key)
     * has to answer per column rather than hand back the whole row.
     */
    public function get($key = null)
    {
        if (null === $this->_stmt) {
            return null;
        }
        static $row = null;
        if (null === $key) {
            return null;
        }
        $row = $this->_row ?? ($this->_row = $this->_stmt->fetch(\PDO::FETCH_ASSOC));
        return $row[$key] ?? null;
    }

    /** @var array|false|null */
    private $_row = null;

    public function resetRow()
    {
        $this->_row = null;
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

// Not the real `hosts` table: this one needs columns that are NULLABLE, and
// the shipped table declares almost everything NOT NULL. The point under test
// is how NULL and '' are collapsed, which the real schema cannot express, so
// the fixture is deliberately more permissive than production rather than
// less.
$pdo->exec(
    'CREATE TABLE `hosts` ('
    . '`hostID` int(11) NOT NULL, '
    . '`hostImage` varchar(50) NULL, '
    . '`hostKernel` varchar(255) NULL, '
    . '`hostDesc` varchar(255) NULL, '
    . '`hostAD` varchar(50) NULL, '
    . 'PRIMARY KEY (`hostID`)'
    . ') ENGINE=InnoDB'
);
$pdo->exec(
    "INSERT INTO `hosts` (`hostID`,`hostImage`,`hostKernel`,`hostDesc`,`hostAD`) VALUES "
    // 1 and 2 agree on the image, disagree on the kernel.
    . "(1,'ubuntu','bzImage','shared','yes'), "
    . "(2,'ubuntu','vmlinuz','shared','yes'), "
    // 3 agrees on the image; its kernel is NULL while 1 and 2 have values,
    // and its description is '' while theirs is 'shared'. Both are the
    // COALESCE trap -- without it, COUNT(DISTINCT) skips the NULL and 1/2/3
    // read as agreeing about a kernel they do not share.
    . "(3,'ubuntu',NULL,'',NULL), "
    // 4 and 5 hold NULL and '' for the image: different spellings of the same
    // nothing, which must read as agreement on "empty".
    . "(4,NULL,NULL,NULL,NULL), "
    . "(5,'',NULL,NULL,NULL)"
);

$stub = new SharedValuesTestDB($pdo);
$dbProp = new \ReflectionProperty(\FOG\Base\FOGBase::class, 'DB');
$dbProp->setAccessible(true);
$dbProp->setValue(null, $stub);

$columns = [
    'image' => 'hostImage',
    'kernel' => 'hostKernel',
    'desc' => 'hostDesc',
    'ad' => 'hostAD',
];

svTakeQueryCount();
$stub->resetRow();
$got = \FOG\Util\SharedHostValues::forHosts([1, 2, 3], $columns);

$check(
    'hosts agreeing on a column report it, with the value',
    true === ($got['image']['uniform'] ?? null)
        && 'ubuntu' === ($got['image']['value'] ?? null)
);
$check(
    'hosts holding different values report as not uniform',
    false === ($got['kernel']['uniform'] ?? null)
);
// THE COALESCE GUARD, and the only case that actually tests it. Hosts 1 and 2
// hold 'yes'; host 3 holds NULL. COUNT(DISTINCT) SKIPS NULLs, so without the
// COALESCE it counts ONE distinct value and reports that all three agree about
// a setting one of them does not have -- and MIN() skips it too, so the form
// would show 'yes (all)'. The kernel column above cannot catch this: 1 and 2
// already disagree there, so the answer is "not uniform" either way.
$check(
    'a value on some hosts and NULL on others is a disagreement',
    false === ($got['ad']['uniform'] ?? null)
);
// The same guard from the '' side: host 3's description is '', 1 and 2 hold
// 'shared'.
$check(
    'an empty string among real values is a disagreement',
    false === ($got['desc']['uniform'] ?? null)
);
$check(
    'the whole answer takes ONE query, whatever the column count',
    1 === svTakeQueryCount()
);

svTakeQueryCount();
$stub->resetRow();
$got = \FOG\Util\SharedHostValues::forHosts([4, 5], $columns);
// NULL and '' are two spellings of the same nothing, so these hosts DO agree.
$check(
    'NULL and the empty string count as the same value',
    true === ($got['image']['uniform'] ?? null)
        && '' === ($got['image']['value'] ?? null)
);
$check(
    'a column that is empty everywhere is uniform and empty',
    true === ($got['desc']['uniform'] ?? null)
        && '' === ($got['desc']['value'] ?? null)
);
$check(
    'a second selection is still one query',
    1 === svTakeQueryCount()
);

// An empty selection must still answer for every column, and must answer
// "not uniform" -- which renders as (varies) and is the safe thing for a form
// about to offer to overwrite them.
svTakeQueryCount();
$stub->resetRow();
$got = \FOG\Util\SharedHostValues::forHosts([], $columns);
$check(
    'an empty selection reports every column, none uniform',
    ['image', 'kernel', 'desc', 'ad'] === array_keys($got)
        && false === $got['image']['uniform']
        && false === $got['kernel']['uniform']
);
$check(
    'an empty selection runs no query at all',
    0 === svTakeQueryCount()
);

// A host id that does not exist must not be treated as agreement with
// nothing: no rows means no agreement.
svTakeQueryCount();
$stub->resetRow();
$got = \FOG\Util\SharedHostValues::forHosts([9999], $columns);
$check(
    'a selection matching no rows is not uniform',
    false === $got['image']['uniform']
);

// The rendering half.
$check(
    'mixed values render as (varies)',
    _('(varies)') === \FOG\Util\SharedHostValues::text(
        ['uniform' => false, 'value' => 'ubuntu']
    )
);
$check(
    'an agreed empty renders as (empty on all)',
    _('(empty on all)') === \FOG\Util\SharedHostValues::text(
        ['uniform' => true, 'value' => '']
    )
);
$check(
    'an agreed value renders with (all), HTML-escaped',
    false !== strpos(
        \FOG\Util\SharedHostValues::text(
            ['uniform' => true, 'value' => '<b>x</b>']
        ),
        '&lt;b&gt;'
    )
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: shared host values are not telling the truth:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  shared host values: %d checks\n", $checks);
exit(0);
