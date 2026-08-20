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
        return null;
    }
    return 'no 1.5 install found under /var/www/html to read a database'
        . ' configuration from';
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
    $db->query(
        "INSERT INTO `site` (`sName`,`sDesc`) VALUES ('" . $mark . "','fixture')"
    );
    $siteID = (int)$db->insertId();
    $hostIDs = array();
    foreach (array('a', 'b') as $n) {
        $db->query(
            "INSERT INTO `hosts` (`hostName`,`hostIP`,`hostUseAD`)"
            . " VALUES ('" . $mark . $n . "','','0')"
        );
        $hostIDs[] = (int)$db->insertId();
    }
    foreach ($hostIDs as $hid) {
        $db->query(
            "INSERT INTO `siteHostAssoc` (`shaName`,`shaSiteID`,`shaHostID`)"
            . " VALUES ('', " . $siteID . ", " . $hid . ")"
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
        $db->query(
            "INSERT INTO `groups` (`groupName`,`groupDesc`)"
            . " VALUES ('" . $mark . $n . "','api scope characterization')"
        );
        $ids[] = (int)$db->insertId();
    }
    return $ids;
}
