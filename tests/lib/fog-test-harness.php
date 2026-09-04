<?php
/**
 * A bootable FOG with no database behind it.
 *
 * The suite's rule is standalone scripts with no framework and no database
 * (docs/adr/0008-secure-boot-enrolment-task-type.md:103,144), and until now
 * that has meant route tests read the SOURCE. Reading the source pins the
 * shape of the code, not what it does -- which is how eight separate
 * single-line deletions, each removing one access-control mechanism from the
 * API read path, all left the suite at "72 passed, 0 failed".
 * (docs/route-listem-access-control-map.md §6.)
 *
 * This is what lets a test drive the real functions instead. It is not a
 * mock framework and should not become one: it is the minimum surface of
 * PDODB that Route and its collaborators actually touch, plus the bootstrap
 * LoadGlobals normally performs against a live server.
 *
 * WHY IT REACHES AS FAR AS IT DOES. Route::listem() does not run its queries
 * through FOGBase::$DB. FOGManagerController::complex() asks
 * DatabaseManager::getLink() for the raw PDO handle and prepares statements on
 * that. But getLink() is `self::$DB->link()` -- so a fake installed on the
 * static $DB reaches the row query, the filter count and the total count as
 * well as everything going through the ordinary query() path. That is the one
 * fact that makes an end-to-end listem() assertion possible at all, and it is
 * worth knowing before anyone "tidies" getLink() into a private connection.
 *
 * NOT usable for: anything asserting real SQL semantics. The fake answers
 * statements structurally -- it reads the column list out of a SELECT and
 * synthesizes rows for it. It will happily "execute" SQL a real server would
 * reject. Tests here assert what the PHP layer does with rows, never that a
 * query is valid.
 *
 * Included, never run: run-all.sh globs tests/*.test.php and tests/*.test.sh
 * at the top level only, so a helper in tests/lib/ is invisible to it.
 */

use FOG\Base\EventManager;
use FOG\Base\FOGCore;
use FOG\Base\HookManager;
use FOG\Items\User;

/**
 * One prepared statement's worth of canned rows.
 */
class FogFakeStatement
{
    private $_rows;

    public function __construct(array $rows)
    {
        $this->_rows = $rows;
    }

    public function bindValue($key, $val, $type = null)
    {
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function fetchAll($mode = null)
    {
        return $this->_rows;
    }

    public function closeCursor()
    {
        return true;
    }
}

/**
 * Stands in for the raw PDO handle DatabaseManager::getLink() hands out.
 */
class FogFakePdo
{
    /** @var array every statement prepared, in order */
    public $log = [];

    /** @var int how many rows a non-COUNT SELECT returns */
    public $rowCount = 3;

    /** @var int what a COUNT(...) answers */
    public $countValue = 3;

    /**
     * @var callable|null fn(array $columns, int $n): array
     *                    Builds one row. $n is 1-based. Default fills the
     *                    primary key with $n and every other column with a
     *                    marker naming itself, so a test can tell which
     *                    column a value came from.
     */
    public $rowFactory = null;

    public function prepare($sql)
    {
        $this->log[] = $sql;
        if (preg_match('/^\s*SELECT\s+COUNT/i', $sql)) {
            return new FogFakeStatement(
                [[0 => $this->countValue, 'cnt' => $this->countValue]]
            );
        }
        $columns = self::selectedColumns($sql);
        $rows = [];
        for ($n = 1; $n <= $this->rowCount; $n++) {
            $rows[] = null !== $this->rowFactory
                ? call_user_func($this->rowFactory, $columns, $n)
                : self::defaultRow($columns, $n);
        }
        return new FogFakeStatement($rows);
    }

    public function quote($value)
    {
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }

    /**
     * The backticked column names a SELECT asks for.
     *
     * @param string $sql the statement
     *
     * @return array
     */
    public static function selectedColumns($sql)
    {
        if (!preg_match('/SELECT (.+?) FROM/is', $sql, $m)) {
            return [];
        }
        $out = [];
        foreach (explode('`,`', trim($m[1], '` ')) as $col) {
            $col = trim($col, '` ');
            if ('' !== $col) {
                $out[] = $col;
            }
        }
        return $out;
    }

    /**
     * @param array $columns the selected columns
     * @param int   $n       1-based row number
     *
     * @return array
     */
    public static function defaultRow(array $columns, $n)
    {
        $row = [];
        foreach ($columns as $col) {
            // An id column has to be an integer: _applySiteScope() casts the
            // row's id and matches it against the scope list, so a marker
            // string there would cast to 0 and every row would look
            // out-of-scope for reasons that have nothing to do with the test.
            $row[$col] = preg_match('/ID$/', $col) || 'id' === $col
                ? $n
                : $col . '-' . $n;
        }
        return $row;
    }
}

/**
 * Stands in for PDODB. Only the surface Route's paths actually call.
 */
class FogFakeDb
{
    /** Event names answered to HookManager's known-event lookup. */
    const KNOWN_EVENTS = [
        'API_SENSITIVE_FIELDS',
        'API_SERVER_OWNED_FIELDS',
        'API_REMOVE_COLUMNS',
        'CUSTOMIZE_DT_COLUMNS',
        'API_MASSDATA_MAPPING',
        'API_INDIVDATA_MAPPING',
        'API_VALID_CLASSES',
        'API_TASKING_CLASSES',
        'API_ACTIVE_TASK_CLASSES',
        'API_PLUGIN_ROUTES',
        'API_UNISEARCH_RESULTS',
        'SEARCH_PAGES',
        'PERMISSION_REGISTRY_DATA',
        'PLUGINS_INJECT_TABDATA',
        'MAIN_MENU_DATA',
    ];

    /**
     * Last error, matching PDODB::$error: false when the statement
     * succeeded, the driver's message when it did not. Typed the same way
     * on purpose -- a fake that can only ever say "fine" cannot stand in
     * for the real one in a test about what happens when it is not.
     *
     * @var bool|string
     */
    public $error = false;

    /**
     * Last error code, matching PDODB::$errorCode. SchemaReconciler reads
     * it to decide whether a failure is one of the tolerated duplicates.
     *
     * @var bool|int|string
     */
    public $errorCode = false;

    /** @var array every statement passed to query(), in order */
    public $log = [];

    /** @var FogFakePdo */
    public $pdo;

    /**
     * @var callable|null fn(string $sql, array $params): array|null
     *                    Return null to fall through to the defaults.
     */
    public $responder = null;

    private $_result = [];

    public function __construct()
    {
        $this->pdo = new FogFakePdo();
    }

    public function link()
    {
        return $this->pdo;
    }

    public function query($sql, $a = [], $params = [])
    {
        $this->log[] = $sql;
        if (null !== $this->responder) {
            $answer = call_user_func($this->responder, $sql, $params);
            if (null !== $answer) {
                $this->_result = $answer;
                return $this;
            }
        }
        // HookManager::processEvent() learns which events exist by calling
        // Route::getIds('hookevent'). Answering nothing sends it off to
        // save() a row for every event fired, which on a fake database is
        // pure noise -- and, less obviously, is what makes
        // sensitiveFieldMap() re-entrant (see F-37). Answer the lookup so
        // the re-entrant call still happens and stops there.
        if (false !== strpos($sql, 'hookEvents')) {
            $rows = [];
            foreach (self::KNOWN_EVENTS as $name) {
                $rows[] = ['heName' => $name];
            }
            $this->_result = $rows;
            return $this;
        }
        $this->_result = [];
        return $this;
    }

    public function fetch($mode = null, $type = '')
    {
        return $this;
    }

    public function get($field = '')
    {
        if ('' === $field || !is_array($this->_result)) {
            return $this->_result;
        }
        // PDODB::get('COLUMN_NAME') flattens to that column's values.
        $flat = [];
        foreach ($this->_result as $row) {
            if (is_array($row) && array_key_exists($field, $row)) {
                $flat[] = $row[$field];
            }
        }
        return $flat ?: $this->_result;
    }

    public function insertId()
    {
        return 1;
    }

    /**
     * Row count the next affectedRows() reports.
     *
     * Settable because a sweep's only operator-visible evidence is the count
     * in its log line, and a hardcoded 0 makes "swept nothing" and "never
     * counted" indistinguishable from a test.
     *
     * @var int
     */
    public $affected = 0;

    public function affectedRows()
    {
        return (int)$this->affected;
    }

    public function sqlerror()
    {
        return '';
    }

    public function escape($value)
    {
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }

    public function dbName()
    {
        return 'fogtest';
    }

    public function close()
    {
        return $this;
    }
}

/**
 * Bootstrap and teardown.
 */
class FogTestHarness
{
    /** @var string */
    private static $_tmp = '';

    /**
     * Bring up enough of FOG to call into lib/ classes, with no database.
     *
     * @param string $label used in the temp directory name
     *
     * @return void
     */
    public static function boot($label)
    {
        $webroot = dirname(__DIR__, 2) . '/packages/web';
        $init = $webroot . '/commons/init.php';
        if (!is_readable($init)) {
            fwrite(STDERR, "FAIL: cannot read $init\n");
            exit(1);
        }

        self::$_tmp = sys_get_temp_dir() . '/fog-' . $label . '-' . getmypid();
        foreach (['cache', 'log', 'plugins'] as $dir) {
            @mkdir(self::$_tmp . '/' . $dir, 0700, true);
        }
        register_shutdown_function([__CLASS__, 'cleanup']);

        if (!defined('FOG_CACHE_DIR')) {
            define('FOG_CACHE_DIR', self::$_tmp . '/cache');
        }
        if (!defined('FOG_LOG_DIR')) {
            define('FOG_LOG_DIR', self::$_tmp . '/log');
        }
        if (!defined('FOG_PLUGIN_DIR')) {
            define('FOG_PLUGIN_DIR', self::$_tmp . '/plugins');
        }
        // FOGBase::_writeLog() stamps this into every line. A real install
        // gets it from the generated config.class.php, which a test has not
        // got, and without it any logging call is a fatal.
        if (!defined('FOG_SCHEMA')) {
            define('FOG_SCHEMA', 0);
        }

        require_once $init;
        new Initiator();

        // FOGBase binds self::$foglang by reference to the global that
        // commons/text.php defines. base.inc.php requires it; init.php does
        // not, and without it every message lookup warns on a null array.
        //
        // `global` is not decoration: a require inside a METHOD evaluates the
        // included file in that method's scope, so text.php's $foglang would
        // be a local that vanishes on return and the binding would still find
        // nothing.
        global $foglang;
        require_once $webroot . '/commons/text.php';

        // LoadGlobals builds these against a live server. Real instances,
        // not stubs: the re-entrancy the read-path tests pin runs through
        // HookManager::processEvent()'s own getIds() call. They load nothing
        // -- the plugin dir above is empty and every core hook ships
        // $active = false (F-11).
        // Event::__construct() -- which every Hook runs through -- calls
        // self::$FOGUser->isValid() before anything else, so an acting user
        // has to exist before the first hook is built, even an anonymous one.
        // It falls back to $GLOBALS['currentUser'] when that user is invalid,
        // so both are seeded.
        $anon = new User();
        $GLOBALS['currentUser'] = $anon;
        self::setStatic('FOGBase', 'FOGUser', $anon);

        self::setStatic('FOGBase', 'HookManager', new HookManager());
        self::setStatic('FOGBase', 'EventManager', new EventManager());
    }

    /**
     * Install a fake database everywhere the read path looks for one.
     *
     * Both statics matter. FOGBase::$DB is what query() callers use;
     * DatabaseManager::$DB is what getLink() dereferences for complex().
     * They are the same property inherited twice, but PHP resolves a static
     * per declaring class, so both are set explicitly rather than trusting
     * which one wins.
     *
     * @return FogFakeDb
     */
    public static function fakeDb()
    {
        $db = new FogFakeDb();
        self::setStatic('FOGBase', 'DB', $db);
        self::setStatic('DatabaseManager', 'DB', $db);
        return $db;
    }

    /**
     * Set a static property regardless of visibility.
     *
     * @param string $class    the declaring class
     * @param string $property the property name
     * @param mixed  $value    what to set
     *
     * @return void
     */
    /**
     * Resolve a short core class name to its namespaced one.
     *
     * The reflection helpers below take a class NAME as a string, and every
     * call site in the suite passes the short one -- setStatic('FOGBase', ...)
     * reads as what it is in a way that FOG\Base\FOGBase does not. Core is no
     * longer aliased into the global namespace (ADR 0013 §2), so a string has
     * to be resolved rather than merely reflected on.
     *
     * FOGBase::qualify() is the same lookup production code uses -- getClass()
     * and Route::_newEntity() both go through it -- so the harness cannot
     * accept a name the application would reject, and a name that is not
     * core's (a test's own fixture class, a plugin double) is returned
     * untouched and reflects exactly as before.
     *
     * @param string $class the short or already-qualified class name
     *
     * @return string
     */
    private static function _resolve($class)
    {
        return \FOG\Base\FOGBase::qualify((string)$class);
    }

    public static function setStatic($class, $property, $value)
    {
        $p = new \ReflectionProperty(self::_resolve($class), $property);
        $p->setAccessible(true);
        $p->setValue(null, $value);
    }

    /**
     * Read a static property regardless of visibility.
     *
     * @param string $class    the declaring class
     * @param string $property the property name
     *
     * @return mixed
     */
    public static function getStatic($class, $property)
    {
        $p = new \ReflectionProperty(self::_resolve($class), $property);
        $p->setAccessible(true);
        return $p->getValue();
    }

    /**
     * Call a private/protected static method.
     *
     * @param string $class  the declaring class
     * @param string $method the method name
     * @param array  $args   arguments
     *
     * @return mixed
     */
    public static function callStatic($class, $method, array $args = [])
    {
        $m = new \ReflectionMethod(self::_resolve($class), $method);
        $m->setAccessible(true);
        return $m->invokeArgs(null, $args);
    }

    /**
     * @return void
     */
    public static function cleanup()
    {
        if ('' === self::$_tmp || !is_dir(self::$_tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                self::$_tmp,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir(self::$_tmp);
    }
}

/**
 * The assertion helper every test in this suite hand-rolls.
 */
class FogChecks
{
    /** @var array */
    public $failures = [];

    /** @var int */
    public $count = 0;

    /**
     * @param string $label what is being asserted
     * @param bool   $cond  the assertion
     *
     * @return bool the assertion, so a caller can branch on it
     */
    public function check($label, $cond)
    {
        $this->count++;
        if (!$cond) {
            $this->failures[] = $label;
        }
        return (bool)$cond;
    }

    /**
     * Print the verdict and exit with the suite's convention.
     *
     * @return void
     */
    public function finish()
    {
        if (count($this->failures)) {
            fwrite(
                STDERR,
                'FAIL (' . count($this->failures) . ' of ' . $this->count . "):\n"
            );
            foreach ($this->failures as $f) {
                fwrite(STDERR, "  - $f\n");
            }
            exit(1);
        }
        echo 'ok  ' . $this->count . " checks passed\n";
        exit(0);
    }
}
