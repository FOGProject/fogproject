<?php
/**
 * Builds commons/schema.php's step array outside the application.
 *
 * commons/schema.php is not a data file. It is a method body: it is include'd
 * from inside SchemaUpdaterPage::update() and expects that method's context --
 * $this->schema[], self::$DB, self::getClass() -- and it issues a query at file
 * scope before a single step has been collected. That is why every gate written
 * against it before now read it as TEXT, and why tests/schema-gate.test.php
 * counts `// N` comment labels rather than array elements: its docblock records
 * that a real count "was tried and rejected" because the file "wants ~35 config
 * constants and a couple of core classes, and every one of those is a thing an
 * unrelated schema commit could add".
 *
 * That objection is answered here rather than argued with. Neither dependency is
 * listed, so neither can be outgrown:
 *
 *   - constants are DISCOVERED by tokenising the file and defining a placeholder
 *     for each bare constant fetch. They only ever reach INSERT statements that
 *     seed default settings, so the values do not matter. DATABASE_NAME and
 *     FOG_SCHEMA are passed in for real because they do -- schema.php's step 29
 *     branches on FOG_SCHEMA.
 *   - unknown classes are MANUFACTURED on demand by an autoloader. Every call
 *     answers null, which is what "this database is empty" looks like to the
 *     introspection schema.php performs while building its steps -- and an empty
 *     database is the state a fresh install starts from.
 *
 * The result is exact: 367 elements at indexes 0-366, contiguous, against a
 * FOG_SCHEMA of 367. It includes the 35 elements the $keySequences foreach
 * appends from a single line of source, which no textual count can see.
 *
 * Extracted from tests/schema-executes.test.php, which had carried it alone.
 * There are now two callers and there must stay ONE copy: two hand-kept shims
 * would drift, and drift between two descriptions of the schema is the exact
 * failure both of those tests exist to catch.
 *
 * Included, never run: run-all.sh globs tests/*.test.php at the top level only,
 * so a helper in tests/lib/ is invisible to it.
 */

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

/**
 * Collect commons/schema.php's step array.
 *
 * Exits 1 with an explanation rather than returning on failure: a collector
 * that quietly answered an empty array would turn every caller into a test
 * that passes by measuring nothing.
 *
 * @param string $schemaFile   path to commons/schema.php
 * @param string $databaseName value for DATABASE_NAME; the statements name it,
 *                             so a caller that executes them points it at its
 *                             own scratch database
 * @param int    $fogSchema    value for FOG_SCHEMA. Pass the real constant to
 *                             model a real server; PHP_INT_MAX to collect
 *                             without regard to it
 *
 * @return array the steps, keyed 0..n-1
 */
function fogCollectSchemaSteps($schemaFile, $databaseName, $fogSchema)
{
    // Guarded because these are one-shot and a caller may have set its own.
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $databaseName);
    }
    if (!defined('FOG_SCHEMA')) {
        define('FOG_SCHEMA', $fogSchema);
    }
    if (!defined('DS')) {
        define('DS', '/');
    }
    foreach (fogDiscoverConstants($schemaFile) as $name) {
        if (defined($name)) {
            continue;
        }
        define($name, '');
    }

    SchemaCollector::$DB = new SchemaStubDB();
    $collector = new SchemaCollector();
    try {
        return $collector->collect($schemaFile);
    } catch (\Throwable $e) {
        fwrite(
            STDERR,
            "FAIL: could not collect the schema steps.\n"
            . '  ' . get_class($e) . ': ' . $e->getMessage() . "\n"
            . '  at ' . $e->getFile() . ':' . $e->getLine() . "\n\n"
            . "  This shims the context SchemaUpdaterPage::update() provides.\n"
            . "  If schema.php started using something the shim does not answer,\n"
            . "  teach SchemaStub about it -- do not delete the assertion.\n"
        );
        exit(1);
    }
}
