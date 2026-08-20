<?php
/**
 * The checked-in schema must actually EXECUTE on the oldest server FOG supports.
 *
 * tests/schema-portable-collation.test.php is textual: it reads the two schema
 * files and rejects a collation name outside a portable allowlist. That catches
 * the shape of GH-1147, but only the shape. It cannot catch a statement that is
 * well-formed, names nothing unusual, and still will not run on MariaDB 10.5 --
 * and it cannot see what the schema RESOLVES to once a server has applied its
 * own defaults, which is the whole of GH-1152.
 *
 * Nothing in CI has ever run this schema against a database. Four reasons, all
 * of them still true when this was written:
 *
 *   1. fog-workflows' fogproject-tests.yml runs this suite on a PHP matrix with
 *      no database at all.
 *   2. schema-portable-collation.test.php is textual BY DESIGN -- its docblock
 *      says commons/schema.php "calls self::$DB->query() at file scope and
 *      cannot be included without a database".
 *   3. run_all_distros.yml is workflow_dispatch only, so distro installs never
 *      fire on a pull request.
 *   4. reusable_distro_workflow.yml hardcoded `git clone -b dev-branch`, so even
 *      a manual dispatch never installed the 1.6 schema.
 *
 * GH-1147's own commit message states the problem this closes: "this cannot be
 * caught by the person introducing it: on the generating server every one of
 * these statements is valid." Only an older server ever sees the failure. This
 * test is how an older server gets to see it.
 *
 * WHAT IT DOES
 *
 * Two independent scratch databases, because they model two different things
 * that reach a server by different routes:
 *
 *   DB 1 -- replays commons/schema.php's indexed steps in order. This is the
 *           FRESH INSTALL path: SchemaUpdaterPage::update() slices the step
 *           array from self::$mySchema, which is 0 on a new database, so a
 *           fresh install really does execute every step from the beginning.
 *
 *   DB 2 -- executes the `create` strings from commons/schema-expected.php into
 *           an EMPTY database. This is the RECONCILER path
 *           (SchemaReconciler::plan()). It gets its own database deliberately:
 *           run against DB 1 every table already exists, so `CREATE TABLE IF
 *           NOT EXISTS` may short-circuit and the collation names would never
 *           be resolved by the server at all. An empty database forces every
 *           one of them to be executed for real.
 *
 * Each database is then asserted on twice:
 *
 *   (a) every statement executed. This is the GH-1147 class -- an unknown
 *       collation, or any other DDL the target server cannot run.
 *   (b) every resulting table shares one collation. This is GH-1152. On a
 *       server below MariaDB 11.4 this passes; on 11.4+ it is expected to fail
 *       until the collation decision in GH-1152 lands, which is why the 11.4
 *       leg of the CI matrix is continue-on-error rather than blocking.
 *
 * HOW commons/schema.php IS LOADED
 *
 * It is include'd from inside SchemaUpdaterPage::update() and expects that
 * method's context: $this->schema[], self::$DB, self::getClass(). It has no
 * `namespace` declaration -- the real FOG classes reach it through class_alias
 * into the global namespace -- so global stubs are what it resolves against.
 *
 * Two of its dependencies are DISCOVERED rather than listed, so that adding a
 * constant or a class reference to schema.php cannot silently break this test:
 *
 *   - constants are found by tokenising the file and defining a placeholder for
 *     each bare constant fetch. They only ever reach INSERT statements that seed
 *     default settings, and only DDL is executed here, so the values do not
 *     matter. DATABASE_NAME and FOG_SCHEMA are set for real because they do.
 *   - unknown classes are manufactured on demand by an autoloader. Every call
 *     answers null, which is exactly what "this database is empty" looks like to
 *     the introspection schema.php performs at build time -- and an empty
 *     database is the correct answer for a fresh install, which is the path
 *     being modelled.
 *
 * The 8 closure steps are NOT executed. They are data migrations that need the
 * full application, and they emit no DDL. That is a real limit of this test,
 * stated rather than hidden: it covers the schema's structure, not its data.
 *
 * SKIPPING
 *
 * With no FOG_TEST_DSN this prints SKIP and exits 0, so `sh tests/run-all.sh`
 * on a machine with no database still reports a green suite. That is the same
 * convention secureboot-authvars.test.sh uses when efitools is absent, and
 * run-all.sh counts a skip as a pass.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-executes.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$schemaFile = $root . '/commons/schema.php';
$manifestFile = $root . '/commons/schema-expected.php';

$dsn = getenv('FOG_TEST_DSN');
if ($dsn === false || $dsn === '') {
    echo "SKIP  no FOG_TEST_DSN set; schema execution not checked\n";
    exit(0);
}

if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: FOG_TEST_DSN is set but the pdo_mysql driver is missing.\n");
    exit(1);
}

foreach ([$schemaFile, $manifestFile] as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "FAIL: cannot read $path\n");
        exit(1);
    }
}

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$user = ($user === false) ? 'root' : $user;
$pass = ($pass === false) ? '' : $pass;

$stepDb = 'fog_schema_steps_test';
$manifestDb = 'fog_schema_manifest_test';

/**
 * Bare constant fetches in a PHP file.
 *
 * A T_STRING that is not a function call, not method or property access, not a
 * class reference and not a declaration is a constant read.
 *
 * @param string $file the file to scan
 *
 * @return array list of constant names
 */
function fogDiscoverConstants($file)
{
    $tokens = token_get_all(file_get_contents($file));
    $count = count($tokens);
    $skipPrev = [
        T_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_CLASS,
        T_NEW,
        T_CONST,
        T_USE,
        T_NS_SEPARATOR,
    ];
    $trivia = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $found = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }
        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], $trivia, true)) {
                continue;
            }
            $prev = $tokens[$j];
            break;
        }
        $next = null;
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], $trivia, true)) {
                continue;
            }
            $next = $tokens[$j];
            break;
        }
        $prevType = is_array($prev) ? $prev[0] : $prev;
        $nextType = is_array($next) ? $next[0] : $next;

        if (in_array($prevType, $skipPrev, true)) {
            continue;
        }
        if ($nextType === T_DOUBLE_COLON || $nextType === '(' || $nextType === T_NS_SEPARATOR) {
            continue;
        }
        if (in_array(strtolower($token[1]), ['true', 'false', 'null'], true)) {
            continue;
        }
        $found[$token[1]] = true;
    }

    return array_keys($found);
}

/**
 * Permissive stand-in for every FOG class schema.php touches while its step
 * array is being built. Answering null to everything is what an empty database
 * looks like to the introspection it does, and an empty database is precisely
 * the state a fresh install starts from.
 */
class SchemaStub
{
    public function __construct(...$args)
    {
    }
    public function __call($name, $args)
    {
        return null;
    }
    public static function __callStatic($name, $args)
    {
        return null;
    }
    public function __get($name)
    {
        return null;
    }
    public function __set($name, $value)
    {
    }
    public function get($key = null)
    {
        return 0;
    }
    public function isValid()
    {
        return false;
    }
    public function save()
    {
        return true;
    }
}

/**
 * The real Schema class builds these two from DATABASE_NAME; so does this one,
 * which is what points the replay at the scratch database.
 */
class Schema extends SchemaStub
{
    public static function createDatabaseQuery()
    {
        return sprintf('CREATE DATABASE IF NOT EXISTS `%s`', DATABASE_NAME);
    }
    public static function useDatabaseQuery()
    {
        return sprintf('USE `%s`', DATABASE_NAME);
    }
}

/**
 * Stands in for self::$DB. schema.php issues one query at file scope, before a
 * single step has been collected; swallowing it is the point.
 */
class SchemaStubDB extends SchemaStub
{
    public function query($query = null, ...$rest)
    {
        return $this;
    }
    public function fetch($what = null, ...$rest)
    {
        return $this;
    }
    public function get($key = null)
    {
        return [];
    }
    public function escape($value)
    {
        return $value;
    }
    public function sanitize($value)
    {
        return $value;
    }
    public function __call($name, $args)
    {
        return $this;
    }
}

/**
 * Stands in for SchemaUpdaterPage, whose method body schema.php is written to
 * run inside.
 */
class SchemaCollector extends SchemaStub
{
    public $schema = [];
    public static $DB;
    public static $mySchema = 0;

    public static function getClass($name, ...$args)
    {
        return class_exists($name, true) ? new $name() : new SchemaStub();
    }
    public static function getManager($name)
    {
        return new SchemaStub();
    }
    public static function getSetting($key)
    {
        return '';
    }
    public static function setSetting($key, $value)
    {
        return true;
    }
    public static function createSecToken()
    {
        return '';
    }
    public static function fastmerge(...$arrays)
    {
        $out = [];
        foreach ($arrays as $array) {
            $out = array_merge($out, (array)$array);
        }
        return $out;
    }
    public function dropDuplicateData(...$args)
    {
        return null;
    }
    public function collect($file)
    {
        include $file;
        return $this->schema;
    }
}

spl_autoload_register(
    function ($class) {
        // schema.php is global-namespaced, so anything carrying a separator is
        // not a name it could have written and not ours to invent.
        if (strpos($class, '\\') !== false) {
            return;
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
            return;
        }
        eval("class {$class} extends SchemaStub {}");
    }
);

define('DATABASE_NAME', $stepDb);
define('FOG_SCHEMA', PHP_INT_MAX);
define('DS', '/');
foreach (fogDiscoverConstants($schemaFile) as $name) {
    if (defined($name)) {
        continue;
    }
    define($name, '');
}

SchemaCollector::$DB = new SchemaStubDB();
$collector = new SchemaCollector();
try {
    $steps = $collector->collect($schemaFile);
} catch (\Throwable $e) {
    fwrite(
        STDERR,
        "FAIL: could not collect the schema steps.\n"
        . '  ' . get_class($e) . ': ' . $e->getMessage() . "\n"
        . '  at ' . $e->getFile() . ':' . $e->getLine() . "\n\n"
        . "  This test shims the context SchemaUpdaterPage::update() provides.\n"
        . "  If schema.php started using something the shim does not answer,\n"
        . "  teach SchemaStub about it -- do not delete the assertion.\n"
    );
    exit(1);
}

$stepDdl = [];
$closures = 0;
foreach ($steps as $updates) {
    foreach ((array)$updates as $update) {
        if (!$update) {
            continue;
        }
        if (is_string($update)) {
            if (preg_match('/^\s*(CREATE|ALTER|DROP)/i', $update)) {
                $stepDdl[] = $update;
            }
            continue;
        }
        if (is_callable($update)) {
            $closures++;
        }
    }
}

$manifest = include $manifestFile;
$manifestDdl = [];
foreach ((array)$manifest as $table => $spec) {
    if (!empty($spec['create'])) {
        $manifestDdl[] = $spec['create'];
    }
}

if (!$stepDdl || !$manifestDdl) {
    fwrite(
        STDERR,
        sprintf(
            "FAIL: collected %d step statements and %d manifest statements;"
            . " expected both to be non-empty.\n",
            count($stepDdl),
            count($manifestDdl)
        )
    );
    exit(1);
}

try {
    $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
} catch (\PDOException $e) {
    fwrite(STDERR, 'FAIL: cannot connect with FOG_TEST_DSN: ' . $e->getMessage() . "\n");
    exit(1);
}

$server = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

/**
 * Run a list of statements into a freshly created database.
 *
 * @param \PDO   $pdo        open connection
 * @param string $database   scratch database name, dropped and recreated
 * @param array  $statements statements to execute in order
 *
 * @return array list of ['sql' => string, 'error' => string] for each failure
 */
function fogRunInto($pdo, $database, array $statements)
{
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    $pdo->exec(sprintf('CREATE DATABASE `%s`', $database));
    $pdo->exec(sprintf('USE `%s`', $database));

    $failures = [];
    foreach ($statements as $sql) {
        // The replay creates its own database in step 0; that statement names
        // DATABASE_NAME, which is this scratch database, so it is harmless --
        // but a stray CREATE DATABASE for anything else is not, and neither is
        // a USE that would move us off the scratch schema.
        if (preg_match('/^\s*CREATE\s+DATABASE/i', $sql)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            $failures[] = [
                'sql' => preg_replace('/\s+/', ' ', substr($sql, 0, 220)),
                'error' => $e->getMessage(),
            ];
        }
    }
    return $failures;
}

/**
 * Distinct table collations present in a database.
 *
 * @param \PDO   $pdo      open connection
 * @param string $database schema to inspect
 *
 * @return array collation name => table count
 */
function fogCollations($pdo, $database)
{
    $stmt = $pdo->prepare(
        'SELECT TABLE_COLLATION, COUNT(*) AS n FROM information_schema.TABLES'
        . ' WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\''
        . ' GROUP BY TABLE_COLLATION ORDER BY n DESC'
    );
    $stmt->execute([$database]);
    $out = [];
    foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) {
        $out[(string)$row[0]] = (int)$row[1];
    }
    return $out;
}

$problems = [];
$report = [];

foreach ([
    ['label' => 'schema.php steps (fresh install path)', 'db' => $stepDb, 'sql' => $stepDdl],
    ['label' => 'schema-expected.php (reconciler path)', 'db' => $manifestDb, 'sql' => $manifestDdl],
] as $pass) {
    $failures = fogRunInto($pdo, $pass['db'], $pass['sql']);
    $collations = fogCollations($pdo, $pass['db']);

    $report[] = sprintf(
        '  %-38s %3d statements, %2d tables, %d collation(s)',
        $pass['label'],
        count($pass['sql']),
        array_sum($collations),
        count($collations)
    );

    if ($failures) {
        $lines = [];
        foreach (array_slice($failures, 0, 5) as $f) {
            $lines[] = '      ' . $f['error'] . "\n        " . $f['sql'];
        }
        $problems[] = sprintf(
            "  %d of %d statements failed on %s (%s):\n%s%s",
            count($failures),
            count($pass['sql']),
            $pass['label'],
            $server,
            implode("\n", $lines),
            count($failures) > 5 ? sprintf("\n      ... and %d more\n", count($failures) - 5) : "\n"
        );
    }

    if (count($collations) > 1) {
        $parts = [];
        foreach ($collations as $name => $n) {
            $parts[] = sprintf('%s (%d table%s)', $name, $n, $n === 1 ? '' : 's');
        }
        $problems[] = sprintf(
            "  %s resolved to MORE THAN ONE collation on %s:\n      %s\n",
            $pass['label'],
            $server,
            implode("\n      ", $parts)
        );
    }
}

$pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $stepDb));
$pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $manifestDb));

if ($problems) {
    fwrite(
        STDERR,
        "FAIL: the schema did not execute cleanly on " . $server . "\n\n"
        . implode("\n", $problems) . "\n"
        . "  A statement that fails here fails the reconcile on a real server,\n"
        . "  which also stops the schema version being recorded -- after which\n"
        . "  DatabaseManager::establish() 308-redirects every request to\n"
        . "  ?node=schema. See GH-1147.\n\n"
        . "  More than one collation means a varchar join across the split will\n"
        . "  raise \"Illegal mix of collations\". See GH-1152.\n"
    );
    exit(1);
}

printf("ok  schema executes on %s\n", $server);
foreach ($report as $line) {
    echo $line . "\n";
}
printf("    %d closure step(s) skipped (data migrations, no DDL)\n", $closures);
exit(0);
