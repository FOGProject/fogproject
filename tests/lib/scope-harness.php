<?php
/**
 * Boot and fixtures for site-api-scope-behaviour.test.php.
 *
 * Separate file because that test re-execs itself as a subprocess to observe
 * a route that answers by exiting, and both processes need the identical
 * bootstrap. Two copies of a bootstrap is two chances for the parent and the
 * child to be testing different things.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/** Fake PDO handing back a fixed set of rows for the table under test. */
class ScopeFakeDB
{
    public $error = false;
    public $log = array();
    public $rowIds = array(1, 2, 3);
    private $_r = array();

    public function query($sql, $a = array(), $p = array())
    {
        $this->log[] = $sql;
        foreach (array('hosts', 'groups') as $t) {
            if (false === strpos($sql, '`' . $t . '`')) {
                continue;
            }
            $vars = Route::getClass(rtrim($t, 's'), '', true);
            $this->_r = array();
            foreach ($this->rowIds as $i) {
                $row = array();
                // Every declared column, so loading an object does not warn
                // its way through a row that is missing most of itself.
                foreach ($vars['databaseFields'] as $col) {
                    $row[$col] = '';
                }
                $row[$vars['databaseFields']['id']] = $i;
                $row[$vars['databaseFields']['name']] = 'h' . $i;
                $this->_r[] = $row;
            }
            return $this;
        }
        $this->_r = array();
        return $this;
    }

    public function fetch($m = null, $t = '', $p = array())
    {
        return $this;
    }

    public function get($f = '')
    {
        return $this->_r;
    }

    // save() on an unknown hook event reaches these; answering keeps the
    // fixture from dying on the first event name this test introduces.
    public function insertId()
    {
        return 1;
    }

    public function sqlerror()
    {
        return '';
    }

    public function affectedRows()
    {
        return 1;
    }
}

/**
 * Boots FOG far enough to drive Route, with the probe registered.
 *
 * @param string $web   packages/web
 * @param string $state 'none', 'scoped' or 'deny' -- only used by the
 *                      subprocess arm, which cannot be handed an array
 *
 * @return void
 */
function scopeHarnessBoot($web, $state = 'none')
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    $tmp = sys_get_temp_dir() . '/fog-scope-behaviour-' . getmypid();
    @mkdir($tmp . '/cache', 0700, true);
    @mkdir($tmp . '/log', 0700, true);
    @mkdir($tmp . '/plugins', 0700, true);
    register_shutdown_function(
        function () use ($tmp) {
            if (!is_dir($tmp)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $tmp,
                    \FilesystemIterator::SKIP_DOTS
                ),
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
    if (!defined('FOG_SCHEMA')) {
        define('FOG_SCHEMA', 0);
    }
    // The row-shaped fixtures below are deliberately partial, so the noise a
    // partial row makes is not the output of this test.
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

    require_once $web . '/commons/init.php';
    new Initiator();

    $set = function ($class, $prop, $value) {
        $p = new \ReflectionProperty($class, $prop);
        $p->setAccessible(true);
        $p->setValue(null, $value);
    };
    // LoadGlobals is what normally builds these and it wants a database.
    $set('FOGBase', 'HookManager', new HookManager());
    $set('FOGBase', 'EventManager', new EventManager());
    // AFTER the managers: constructing one re-runs FOGBase's own wiring and
    // leaves $DB null behind it.
    $set('FOGBase', 'DB', new ScopeFakeDB());
    // MACAddress logs a parse failure through FOGCore, and a Host builds one
    // on load. Without this the host cases die on a null.
    $set(
        'FOGBase',
        'FOGCore',
        new class {
            public function debug($m = '')
            {
            }
            public function error($m = '')
            {
            }
        }
    );

    // processEvent() writes an unseen event name to the hookevent table before
    // dispatching it. Seeded so the fixture is not answering INSERTs.
    $known = new \ReflectionProperty('HookManager', 'knownEvents');
    $known->setAccessible(true);
    $known->setValue(
        null,
        array_flip(
            array(
                'API_SCOPE_IDS',
                'API_SCOPE_WHERE',
                'API_GETTER',
                'API_MASSDATA_MAPPING',
                'API_INDIVDATA_MAPPING',
                'API_VALID_CLASSES',
                'API_SENSITIVE_FIELDS',
            )
        )
    );

    // After Initiator: ScopeProbe extends Hook, which does not exist until
    // the autoloader does.
    require_once __DIR__ . '/scopeprobe.php';
    $probe = (new \ReflectionClass('ScopeProbe'))->newInstanceWithoutConstructor();
    $hm = new \ReflectionProperty('FOGBase', 'HookManager');
    $hm->setAccessible(true);
    $hm->getValue()->register('API_SCOPE_IDS', array($probe, 'scope'));
    $hm->getValue()->register('API_SCOPE_WHERE', array($probe, 'scopeWhere'));

    switch ($state) {
        case 'scoped':
            ScopeProbe::$answer = array(2, 3);
            break;
        case 'deny':
            ScopeProbe::$answer = array();
            break;
        default:
            ScopeProbe::$answer = 'none';
    }
}

/**
 * Whether a table exists in the database under test.
 *
 * The site tables are created by the site plugin, not by commons/schema.php,
 * so a database built by replaying the schema alone does not have them and a
 * fixture that needs them has nothing to say.
 *
 * @param object $db    the connection to ask
 * @param string $table the table name
 *
 * @return bool
 */
function scopeHarnessHasTable($db, $table)
{
    $rows = $db->query(
        "SELECT `TABLE_NAME` AS `t` FROM `information_schema`.`TABLES` "
        . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $table . "'"
    )->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
    return count((array) $rows) > 0;
}

/**
 * Inserts a fixture row, filling in every column the server insists on.
 *
 * GH-1245. These fixtures used to hand-write an INSERT naming only the
 * columns the test cared about, which worked because PDODB cleared sql_mode
 * on every connection -- the server silently supplied its implicit default
 * for the rest. It no longer does, so an INSERT that omits a NOT NULL column
 * with no DEFAULT is error 1364 and the whole fixture collapses with nothing
 * useful said.
 *
 * Reading the missing columns from information_schema rather than listing
 * them here: `hosts` alone has twenty-one of them, and a list in a test file
 * goes stale the first time somebody adds a column. The value chosen per type
 * is the same rule FOGController::emptyValueFor() follows.
 *
 * @param object $db     the connection to insert through
 * @param string $table  the table to insert into
 * @param array  $values column => value, the ones the test actually cares about
 *
 * @return int the new row's id
 */
function scopeHarnessInsert($db, $table, array $values)
{
    static $cache = array();
    if (!isset($cache[$table])) {
        $cache[$table] = array();
        $rows = $db->query(
            "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty`, "
            . "`IS_NULLABLE` AS `n`, `COLUMN_DEFAULT` AS `d`, `EXTRA` AS `e` "
            . "FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = '" . $table . "'"
        )->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ((array) $rows as $row) {
            $cache[$table][$row['c']] = $row;
        }
    }

    foreach ($cache[$table] as $name => $info) {
        if (array_key_exists($name, $values)) {
            continue;
        }
        if (strtoupper($info['n']) === 'YES'
            || null !== $info['d']
            || false !== stripos($info['e'], 'auto_increment')
        ) {
            continue;
        }
        $type = $info['ty'];
        if (preg_match('#^(datetime|timestamp|date)#i', $type)) {
            // NOT NULL with no default: there is nothing else it can be.
            $values[$name] = date('Y-m-d H:i:s');
        } elseif (preg_match('#^(tiny|small|medium|big)?int#i', $type)) {
            $values[$name] = 0;
        } elseif (preg_match("#^(enum|set)\s*\(\s*'((?:[^']|'')*)'#i", $type, $m)) {
            $values[$name] = str_replace("''", "'", $m[2]);
        } else {
            $values[$name] = '';
        }
    }

    $cols = array();
    $phs = array();
    $params = array();
    foreach ($values as $name => $value) {
        $cols[] = '`' . $name . '`';
        $phs[] = ':' . $name;
        $params[$name] = $value;
    }
    $db->query(
        sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', $cols),
            implode(',', $phs)
        ),
        array(),
        $params
    );

    return (int) $db->insertId();
}

/**
 * Why the database arm cannot run, or null when it can.
 *
 * Also defines the DATABASE_* constants when it returns null, reading them
 * out of a live install's generated config so no credential is written down
 * in the repository.
 *
 * @return string|null
 */
function scopeHarnessDbReason()
{
    /*
     * FOG_TEST_DSN points the database arm at a scratch server instead of a
     * local install, which is the only way to run it against a schema this
     * tree's own commons/schema.php built -- a developer's 1.5 install is
     * usually a few steps behind, and CI has no install at all.
     *
     *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=13313;dbname=fog15' \
     *   FOG_TEST_USER=root FOG_TEST_PASS= php tests/<name>.test.php
     *
     * scripts/background_scripts/replay_15_schema_1245.sh builds exactly such
     * a database.
     */
    $dsn = getenv('FOG_TEST_DSN');
    if (false !== $dsn && '' !== $dsn) {
        $parts = array();
        foreach (explode(';', substr($dsn, strpos($dsn, ':') + 1)) as $bit) {
            if (false === strpos($bit, '=')) {
                continue;
            }
            list($k, $v) = explode('=', $bit, 2);
            $parts[trim($k)] = trim($v);
        }
        $host = isset($parts['host']) ? $parts['host'] : '127.0.0.1';
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }
        $vals = array(
            'DATABASE_TYPE' => 'mysql',
            'DATABASE_HOST' => $host,
            'DATABASE_NAME' => isset($parts['dbname']) ? $parts['dbname'] : 'fog',
            'DATABASE_USERNAME' => (string) getenv('FOG_TEST_USER'),
            'DATABASE_PASSWORD' => (string) getenv('FOG_TEST_PASS'),
        );
        foreach ($vals as $k => $v) {
            if (!defined($k)) {
                define($k, $v);
            }
        }
        return scopeHarnessSchemaReason($vals);
    }

    foreach (glob('/var/www/html/fog-1.5*/lib/fog/config.class.php') ?: array() as $cfg) {
        $src = @file_get_contents($cfg);
        if (false === $src) {
            continue;
        }
        preg_match_all(
            "/define\('(DATABASE_[A-Z]+)', *'([^']*)'/",
            $src,
            $m,
            PREG_SET_ORDER
        );
        $vals = array();
        foreach ($m as $d) {
            $vals[$d[1]] = $d[2];
        }
        if (count($vals) < 5) {
            continue;
        }
        foreach ($vals as $k => $v) {
            if (!defined($k)) {
                define($k, $v);
            }
        }
        return scopeHarnessSchemaReason($vals);
    }
    return 'no 1.5 install found under /var/www/html to read a database'
        . ' configuration from';
}

/**
 * Why that database is too old to test against, or null when it is current.
 *
 * These tests boot the ORM directly, which skips the gate every real request
 * goes through: DatabaseManager::establish() redirects to the schema updater
 * whenever the installed schema is behind FOG_SCHEMA, so nothing can reach a
 * write path against a stale database in the field. The harness can, and
 * since GH-1245 that difference is visible -- save() writes a real NULL for
 * an empty date, which is an error until schema step 284 has made those
 * columns nullable, and PDODB no longer clears sql_mode so the server says
 * so rather than coercing it.
 *
 * Skipping rather than failing: a developer's install being a few steps
 * behind is not a defect in the code under test, and reporting it as one
 * sends people looking in the wrong place.
 *
 * @param array $vals the DATABASE_* values read from the install's config
 *
 * @return string|null
 */
function scopeHarnessSchemaReason($vals)
{
    $sysFile = dirname(dirname(__DIR__)) . '/packages/web/lib/fog/system.class.php';
    if (!preg_match(
        "/define\('FOG_SCHEMA',\s*(\d+)\)/",
        (string) @file_get_contents($sysFile),
        $m
    )) {
        return null;
    }
    $want = (int) $m[1];

    $host = isset($vals['DATABASE_HOST']) ? $vals['DATABASE_HOST'] : '127.0.0.1';
    $port = 3306;
    if (false !== strpos($host, ':')) {
        list($host, $port) = explode(':', $host, 2);
    }
    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s',
                $host,
                (int) $port,
                isset($vals['DATABASE_NAME']) ? $vals['DATABASE_NAME'] : 'fog'
            ),
            isset($vals['DATABASE_USERNAME']) ? $vals['DATABASE_USERNAME'] : '',
            isset($vals['DATABASE_PASSWORD']) ? $vals['DATABASE_PASSWORD'] : '',
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        $have = (int) $pdo->query('SELECT MAX(`vValue`) FROM `schemaVersion`')
            ->fetchColumn();
    } catch (Exception $e) {
        return 'cannot read the schema version from the 1.5 install: '
            . $e->getMessage();
    }
    if ($have < $want) {
        return sprintf(
            'the 1.5 install is at schema %d and this tree needs %d -- run '
            . 'the schema updater on it first',
            $have,
            $want
        );
    }
    return null;
}

/**
 * Two hosts with NO MAC, in a site of their own, and a teardown for both.
 *
 * Exists because of a bug the group fixture cannot see. SiteHostAssociation
 * declares a class relationship to Host, find() walks those to build joins,
 * and Host's MACAddressAssociation relationship carries array('primary' => 1)
 * -- which buildQuery() emits as `hostMAC`.`hmPrimary` = '1' in the WHERE.
 * That turns the LEFT OUTER JOIN into an inner one, so a plain membership
 * lookup silently dropped every host with no primary MAC: 95 of 1000 in the
 * lab. Hosts with no MAC row at all are the sharpest case, so that is what
 * this makes.
 *
 * @param object $db the real PDODB
 *
 * @return array array(siteID, array(hostID, hostID))
 */
function scopeHarnessMaclessFixture($db)
{
    $mark = 'zzmac' . getmypid();
    register_shutdown_function(
        function () use ($db, $mark) {
            // Associations first: they are what points at the hosts, and a
            // row left behind here would be a dangling membership.
            $db->query(
                "DELETE `siteHostAssoc` FROM `siteHostAssoc`"
                . " JOIN `site` ON `site`.`sID` = `siteHostAssoc`.`shaSiteID`"
                . " WHERE `site`.`sName` = '" . $mark . "'"
            );
            $db->query("DELETE FROM `site` WHERE `sName` = '" . $mark . "'");
            $db->query(
                "DELETE FROM `hosts` WHERE `hostName` LIKE '" . $mark . "%'"
            );
        }
    );
    $siteID = scopeHarnessInsert(
        $db,
        'site',
        array('sName' => $mark, 'sDesc' => 'fixture')
    );
    $hostIDs = array();
    foreach (array('a', 'b') as $n) {
        $hostIDs[] = scopeHarnessInsert(
            $db,
            'hosts',
            array('hostName' => $mark . $n, 'hostIP' => '', 'hostUseAD' => '0')
        );
    }
    foreach ($hostIDs as $hid) {
        scopeHarnessInsert(
            $db,
            'siteHostAssoc',
            array('shaName' => '', 'shaSiteID' => $siteID, 'shaHostID' => $hid)
        );
    }
    return array($siteID, $hostIDs);
}

/**
 * Swaps the database FOGBase reads through.
 *
 * @param object $db the connection to install
 *
 * @return void
 */
function scopeHarnessSetDb($db)
{
    $p = new \ReflectionProperty('FOGBase', 'DB');
    $p->setAccessible(true);
    $p->setValue(null, $db);
}

/**
 * Three groups this process owns, and a teardown that removes exactly those.
 *
 * Marked with the pid so a concurrent run, or an abandoned one, cannot be
 * mistaken for this run's rows -- and so the DELETE can name what it removes
 * rather than clearing a table this server may be using for something.
 *
 * @param object $db the real PDODB
 *
 * @return array the three ids
 */
function scopeHarnessFixture($db)
{
    $mark = 'zz-scopetest-' . getmypid() . '-';
    register_shutdown_function(
        function () use ($db, $mark) {
            $db->query(
                "DELETE FROM `groups` WHERE `groupName` LIKE '" . $mark . "%'"
            );
        }
    );
    $ids = array();
    foreach (array(1, 2, 3) as $n) {
        $ids[] = scopeHarnessInsert(
            $db,
            'groups',
            array(
                'groupName' => $mark . $n,
                'groupDesc' => 'api scope characterization',
            )
        );
    }
    return $ids;
}
