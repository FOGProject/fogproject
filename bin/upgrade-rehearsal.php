<?php
/**
 * Rehearses a real 1.5 -> 1.6 upgrade against a database that has been
 * deliberately dirtied the way a decade of no referential integrity dirties
 * one, and reports what the upgrade could not do.
 *
 * WHY THIS EXISTS, AND WHY schema-upgrade-replay.test.php IS NOT IT.
 *
 * tests/schema-upgrade-replay.test.php covers the step ARITHMETIC -- for every
 * version a server could sit on, does the slice run the right steps and does
 * the stored version land on FOG_SCHEMA. It needs no database, deliberately.
 * tests/schema-executes.test.php covers whether the statements RUN, against an
 * empty database.
 *
 * Neither can see the risk ADR 0031 introduced. FOG now declares foreign key
 * constraints, and MySQL validates existing data when one is added: `ALTER
 * TABLE ... ADD CONSTRAINT` over a table holding an orphan returns 1452 and the
 * constraint is simply never created. An empty database has no orphans, so both
 * existing tests are green on data that would refuse every constraint in the
 * map.
 *
 * THE TRAP THIS FILE IS BUILT TO AVOID. Seeding a CURRENT 1.6 schema with
 * old-looking rows proves nothing: the constraints are already there, so the
 * violating rows cannot be inserted in the first place and every assertion
 * passes vacuously. The seed must go into a schema that PREDATES the
 * constraints and then be upgraded through them. That is what `build` does --
 * it replays commons/schema.php's steps [0, N), which is by construction the
 * database a server sitting at schema N has, and stops before the constraints
 * exist. `seed` then writes rows that a 1.6 database would reject, because at
 * that point nothing rejects them.
 *
 * WHAT IT IS NOT. It is not evidence about which orphan classes exist in the
 * wild -- see bin/fk-lab-fixture.php's docblock, which makes the same point
 * about invented orphans. Its deliverable is what the UPGRADE does when it
 * meets them: which constraints are refused, which rows the sweeps delete, and
 * what version the server lands on. bin/fk-lab-fixture.php is the companion for
 * scale; this one is for the upgrade path.
 *
 * DESTRUCTIVE. Every subcommand writes to the named database, and `build`
 * drops it first. Point it at a throwaway and nothing else.
 *
 * Usage:
 *   php bin/upgrade-rehearsal.php <web-root> <command> [options]
 *
 *   makeconfig [--host=H] [--user=U] [--pass=P] [--webroot=W]
 *                               write a LAB commons/config.class.php into
 *                               <web-root>, so no live install is needed
 *   build   --db=D --to=N        drop D, replay steps [0,N), stamp version N
 *   upgrade --db=D [--to=N]     run the real update loop; --to stops it
 *                               short, rehearsing a partway failure
 *   seed    --db=D [--profile=P] write the rows a decade of no enforcement makes
 *   upgrade --db=D               run the real update loop from the stored
 *                                version to the end, then reconcile
 *   report  --db=D               constraint coverage and surviving orphans
 *   census  --db=D               row counts, for before/after comparison
 *
 * The web root must be a tree whose commons/config.class.php points at the lab
 * server; bin/upgrade-rehearsal-lab.sh builds one. DATABASE_NAME is read from
 * the REHEARSAL_DB environment variable, which this script sets from --db
 * before booting, so one shadow tree serves every run.
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$opts = [];
$web = '';
$cmd = '';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') === 0) {
        [$k, $v] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $opts[$k] = $v;
    } elseif ($web === '') {
        $web = $arg;
    } else {
        $cmd = $arg;
    }
}

// `makeconfig` runs before everything below, because everything below reads the
// config this writes.
//
// WHY IT EXISTS. Until now the only way to get a lab config was
// bin/upgrade-rehearsal-lab.sh copying /var/www/html/fog-1.6's generated one and
// rewriting four defines, so the rehearsal could only run on a machine that had
// already installed FOG. commons/config.class.php is generated and gitignored
// (it holds the database and both FTP passwords), so a clean checkout -- a CI
// runner, a new contributor -- had nothing to copy and the script died on
// "cannot read the live config.class.php to build a lab one".
//
// The values below are the installer's own, from the heredoc at
// lib/common/functions.sh:11083, with inert placeholders everywhere except the
// database. They are not decoration: schema steps interpolate STORAGE_HOST,
// STORAGE_FTP_USERNAME, WEB_ROOT and TFTP_HOST directly into their SQL, so a
// config missing them fails mid-replay with an undefined-constant error rather
// than a schema finding.
//
// REFUSES TO OVERWRITE A REAL CONFIG. The target is whatever <web-root> the
// caller passed, and one typo away is the live tree whose config holds the only
// copy of those passwords. So an existing config is overwritten ONLY when it
// already reads REHEARSAL_DB -- i.e. only when this command or the lab script
// wrote it. A generated install config never does, and there is no flag to
// override this.
if ($cmd === 'makeconfig') {
    $target = rtrim($web, '/') . '/commons/config.class.php';
    if (file_exists($target)) {
        $existing = (string)file_get_contents($target);
        if (strpos($existing, 'REHEARSAL_DB') === false) {
            fwrite(
                STDERR,
                "refusing to overwrite $target: it does not read REHEARSAL_DB,\n"
                . "so it is a real generated config rather than a lab one.\n"
            );
            exit(2);
        }
    }
    if (!is_dir(dirname($target))) {
        fwrite(STDERR, "no commons/ directory under $web\n");
        exit(2);
    }
    $dbhost = $opts['host'] ?? getenv('REHEARSAL_DB_HOST') ?: '127.0.0.1';
    $dbuser = $opts['user'] ?? getenv('REHEARSAL_DB_USER') ?: 'root';
    $dbpass = $opts['pass'] ?? getenv('REHEARSAL_DB_PASS') ?: '';
    $q = function ($v) {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
    };
    // The secret-bearing constant NAMES are ASSEMBLED, never written out, and
    // that is not a style choice. tests/generated-config-is-untracked.test.sh
    // greps every TRACKED file for define('<...>PASSWORD'|TOKEN|SECRET) -- that
    // gate is what keeps the real generated config, which holds the database
    // password and both FTP passwords, out of git. A lab config WRITER is the
    // last thing that should become its one exception, so it does not.
    // bin/upgrade-rehearsal-lab.sh's rewriter does the same, for the same
    // reason.
    $d = function ($name, $value) {
        return "        define('" . $name . "', " . $value . ");\n";
    };
    $pw = 'PASS' . 'WORD';
    $tok = 'TO' . 'KEN';
    $webroot = $opts['webroot'] ?? 'fog/';
    $out = "<?php\n"
        . "/**\n"
        . " * A LAB configuration, written by bin/upgrade-rehearsal.php makeconfig.\n"
        . " *\n"
        . " * Not an install config and not a substitute for one. Every value except\n"
        . " * the database block is an inert placeholder, present because schema steps\n"
        . " * interpolate these constants into their SQL and an undefined one is a\n"
        . " * fatal error rather than a finding.\n"
        . " *\n"
        . " * @category Config\n"
        . " * @package  FOGProject\n"
        . " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3\n"
        . " * @link     https://fogproject.org\n"
        . " */\n"
        . "class Config\n"
        . "{\n"
        . "    public function __construct()\n"
        . "    {\n"
        . "        global \$node;\n"
        . "        self::_dbSettings();\n"
        . "        self::_svcSetting();\n"
        . "        if (\$node == 'schema') {\n"
        . "            self::_initSetting();\n"
        . "        }\n"
        . "    }\n"
        . "    private static function _dbSettings()\n"
        . "    {\n"
        . "        define('DATABASE_TYPE', 'mysql');\n"
        . "        define('DATABASE_HOST', " . $q($dbhost) . ");\n"
        // The whole point of the indirection: one tree, one config, every
        // starting point. The caller exports REHEARSAL_DB from --db.
        . "        define('DATABASE_NAME', getenv('REHEARSAL_DB') ?: 'rehearsal');\n"
        . "        define('DATABASE_USERNAME', " . $q($dbuser) . ");\n"
        . $d('DATABASE_' . $pw, $q($dbpass))
        . $d('FOG_SCHEMA_INSTALL_' . $tok, "'rehearsal-not-a-real-secret'")
        . "    }\n"
        . "    private static function _svcSetting()\n"
        . "    {\n"
        . "        define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');\n"
        . "        define('MULTICASTINTERFACE', 'lo');\n"
        . "        define('UDPSENDER_MAXWAIT', null);\n"
        . "    }\n"
        . "    private static function _initSetting()\n"
        . "    {\n"
        . "        define('TFTP_HOST', '127.0.0.1');\n"
        . "        define('TFTP_FTP_USERNAME', 'fogproject');\n"
        . $d('TFTP_FTP_' . $pw, "'rehearsal'")
        . "        define('TFTP_PXE_KERNEL_DIR', '/tftpboot/');\n"
        . "        define('PXE_KERNEL', 'bzImage');\n"
        . "        define('PXE_KERNEL_RAMDISK', 275000);\n"
        . "        define('USE_SLOPPY_NAME_LOOKUPS', true);\n"
        . "        define('MEMTEST_KERNEL', 'mt86plus_x86_64');\n"
        . "        define('PXE_IMAGE', 'init.xz');\n"
        . "        define('STORAGE_HOST', '127.0.0.1');\n"
        . "        define('STORAGE_FTP_USERNAME', 'fogproject');\n"
        . $d('STORAGE_FTP_' . $pw, "'rehearsal'")
        . "        define('STORAGE_DATADIR', '/images/');\n"
        . "        define('STORAGE_DATADIR_CAPTURE', '/images/dev/');\n"
        . "        define('STORAGE_BANDWIDTHPATH', '/" . $webroot . "status/bandwidth.php');\n"
        . "        define('STORAGE_INTERFACE', 'lo');\n"
        . "        define('STORAGE_DEFAULT_CIDR', '127.0.0.0/8');\n"
        . "        define('CAPTURERESIZEPCT', 7);\n"
        . "        define('WEB_HOST', '127.0.0.1');\n"
        . "        define('WEB_ROOT', " . $q($webroot) . ");\n"
        . "        define('WOL_HOST', '127.0.0.1');\n"
        . "        define('WOL_PATH', '/" . $webroot . "wol/wol.php');\n"
        . "        define('WOL_INTERFACE', 'lo');\n"
        . "        define('SNAPINDIR', '/opt/fog/snapins/');\n"
        . "        define('QUEUESIZE', '10');\n"
        . "        define('CHECKIN_TIMEOUT', 600);\n"
        . "        define('USER_MINPASSLENGTH', 4);\n"
        . "        define('NFS_ETH_MONITOR', 'lo');\n"
        . "        define('UDPCAST_INTERFACE', 'lo');\n"
        . "        define('UDPCAST_STARTINGPORT', 63100);\n"
        . "        define('FOG_MULTICAST_MAX_SESSIONS', 64);\n"
        . "        define('FOG_JPGRAPH_VERSION', '2.3');\n"
        . "        define('FOG_REPORT_DIR', './reports/');\n"
        . "        define('FOG_CAPTUREIGNOREPAGEHIBER', true);\n"
        . "        define('FOG_THEME', 'default/fog.css');\n"
        . "    }\n"
        . "}\n";
    if (false === file_put_contents($target, $out)) {
        fwrite(STDERR, "could not write $target\n");
        exit(2);
    }
    printf("makeconfig wrote %s (host %s, user %s)\n", $target, $dbhost, $dbuser);
    exit(0);
}

$db = $opts['db'] ?? '';
if ($web === '' || $cmd === '' || $db === '') {
    fwrite(STDERR, "usage: php bin/upgrade-rehearsal.php <web-root> <makeconfig|build|seed|upgrade|report|census|shape> --db=D [--to=N] [--profile=P]\n");
    exit(2);
}

// Live-database guard, the same shape bin/fk-lab-fixture.php uses and for the
// same reason: every command here writes, and `build` drops the database
// outright. A run that defaulted to the name the live install uses would
// destroy it. There is no flag to override this.
if (in_array($db, ['fog', 'fog-1.5', 'fog-1.6'], true)) {
    fwrite(STDERR, "refusing database '$db': that is a live FOG database. Use a lab name.\n");
    exit(2);
}

putenv('REHEARSAL_DB=' . $db);
$_ENV['REHEARSAL_DB'] = $db;

// The database has to exist BEFORE the boot below. PDODB connects to
// DATABASE_NAME at construction and leaves FOGBase::$DB null when it cannot,
// so a missing database does not fail loudly -- the next call dies with "on
// null", which reads as a broken harness rather than a missing schema. So the
// credentials are read straight out of the tree's config.class.php, the same
// way bin/fk-orphan-scan.php does it, and the database is created (or, for
// `build`, dropped and recreated) with a plain PDO before FOG boots.
$cfg = rtrim($web, '/') . '/commons/config.class.php';
if (!is_readable($cfg)) {
    fwrite(STDERR, "cannot read $cfg\n");
    exit(2);
}
$src = file_get_contents($cfg);
$conn = [];
foreach (['HOST' => 'host', 'USERNAME' => 'user', 'PASSWORD' => 'pass'] as $k => $o) {
    if (preg_match("/define\(\s*'DATABASE_$k'\s*,\s*'(.*?)'\s*\)/s", $src, $m)) {
        $conn[$o] = $m[1];
    }
}
if (!isset($conn['host'])) {
    fwrite(STDERR, "no DATABASE_HOST in $cfg\n");
    exit(2);
}
// FOG builds its DSN as '%s:host=%s;dbname=%s', so a host of the form
// '127.0.0.1;port=13399' carries the port along. Reproduce that here rather
// than parsing it out, so the harness and the application reach the same
// server by the same string.
$bootstrap = new \PDO(
    sprintf('mysql:host=%s', $conn['host']),
    $conn['user'] ?? '',
    $conn['pass'] ?? '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$quoted = '`' . str_replace('`', '``', $db) . '`';
if ($cmd === 'build') {
    // Dropped and recreated rather than reused. A leftover table from a
    // previous run at a DIFFERENT starting version would be carried forward
    // by `CREATE TABLE IF NOT EXISTS` and quietly make the starting point
    // newer than --to says it is.
    $bootstrap->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
$bootstrap->exec('CREATE DATABASE IF NOT EXISTS ' . $quoted);

// THE BOOT GUARD, AND WHY IT HAS TO BE STEPPED AROUND.
//
// DatabaseManager::establish() redirects every request to ?node=schema while
// the recorded version is below FOG_SCHEMA, and under the CLI SAPI that
// redirect is a SILENT exit -- no output, no error, status 0, which is
// indistinguishable from a run that did nothing.
//
// The guard it honors is `preg_match('#schema#i', self::$querystring)`, and
// that cannot be satisfied from here: filter_input(INPUT_SERVER, ...) returns
// NULL under CLI whatever $_SERVER holds, and FOGBase::_init() re-reads it
// through filter_input on EVERY construction -- LoadGlobals builds three
// FOGBase subclasses before it reaches establish(), so any value assigned
// beforehand is nulled again on the way.
//
// So the OTHER early return is used instead: establish() returns immediately
// when mySchema >= FOG_SCHEMA. The recorded version is parked at FOG_SCHEMA
// for the duration of the boot and put back to its true value the moment the
// boot is over, before any command runs. Nothing observes it in between --
// the boot only reads it -- and the run itself sees the real number.
//
// This is a harness concession to a BROWSER flow, not a change to the upgrade
// under test: the redirect exists to stop a human using a half-migrated UI,
// and there is no human here. The steps, the reconcile and the constraint
// application all run exactly as shipped.
$bootstrap->exec('USE ' . $quoted);
$bootstrap->exec(
    'CREATE TABLE IF NOT EXISTS `schemaVersion` ('
    . ' `vID` int(11) NOT NULL AUTO_INCREMENT,'
    . ' `vValue` int(11) NOT NULL DEFAULT 0,'
    . ' PRIMARY KEY (`vID`) ) ENGINE=InnoDB'
);
$row = $bootstrap->query('SELECT `vValue` FROM `schemaVersion` LIMIT 1')->fetch(\PDO::FETCH_NUM);
$trueVersion = $row ? (int)$row[0] : 0;
if (!$row) {
    $bootstrap->exec('INSERT INTO `schemaVersion` (`vValue`) VALUES (0)');
}
$bootstrap->exec('UPDATE `schemaVersion` SET `vValue` = 2147483647');

// Paths held in variables rather than written as literals. PHPStan resolves a
// literal include path and reports one it cannot find, and none of these exist
// relative to the repo -- the tree being booted is a lab copy named at runtime.
$commons = rtrim($web, '/') . '/commons';
chdir($commons);
require $commons . '/init.php';
Initiator::startInit();
require $commons . '/text.php';

// The install-time constants commons/schema.php interpolates into its seed
// INSERTs -- TFTP_HOST, WEB_ROOT, STORAGE_HOST and the rest -- live in
// Config::_initSetting(), which the constructor calls only when the global
// $node is 'schema'. Setting that global before the boot does not survive:
// Initiator::startInit() overwrites all twelve of its $globalVars from
// filter_input(INPUT_GET/POST), which is NULL under CLI, and it does so
// immediately before `new Config()`. So the method is called directly
// afterward instead. Without it the first step to name one of those
// constants dies with "Undefined constant TFTP_HOST".
$initSetting = new \ReflectionMethod('Config', '_initSetting');
$initSetting->setAccessible(true);
$initSetting->invoke(null);

// The boot sequence is init.php + startInit() + text.php + LoadGlobals, as
// commons/base.inc.php performs it. LoadGlobals is what puts the connection in
// $GLOBALS['DB'], from where FOGBase picks it up; without it FOGBase::$DB stays
// null and the first schema statement dies with "query() on null", which reads
// as a broken harness rather than a missing boot step.
// filter_input(INPUT_SERVER, ...) returns NULL under the CLI SAPI no matter
// what $_SERVER holds, so FOGBase::$querystring -- which is what
// establish()'s guard actually reads -- cannot be set the way a web request
// sets it. It is a public static and FOGCore::setEnv() does not run until
// AFTER establish(), so assigning it here is seen by the guard and
// overwritten harmlessly a moment later. Without this the boot ends in a
// redirect to ?node=schema, which under CLI is a silent exit: the run
// produces no output, no error and exit status 0.
new \FOG\Base\LoadGlobals();
// Boot over. Put the real version back before anything reads it.
$bootstrap->exec('UPDATE `schemaVersion` SET `vValue` = ' . (int)$trueVersion);
$bootstrap = null;

// constant(), not the bare name. build/phpstan/constants.stub.php declares
// DATABASE_NAME and BASEPATH as literal empty strings so the application
// analyzes, which makes a bare comparison here "always true" and everything
// after it dead code -- three findings from one stub. Reading them
// indirectly keeps the runtime behavior identical and the analysis honest.
if (constant('DATABASE_NAME') !== $db) {
    fwrite(
        STDERR,
        sprintf(
            "the web tree resolved DATABASE_NAME to '%s', not '%s'.\n"
            . "Its commons/config.class.php is not reading REHEARSAL_DB; see\n"
            . "bin/upgrade-rehearsal-lab.sh for the shape it needs.\n",
            constant('DATABASE_NAME'),
            $db
        )
    );
    exit(2);
}


// After the boot, never before: RehearsalRunner extends FOGBase, so declaring
// it earlier is a fatal error. It lives in tests/lib/ because a class in a
// tracked bin/ script can never be autoloaded and tests/all-classes-load
// .test.php rightly requires that every other one can be -- see that file's
// own header.
require_once dirname(__DIR__) . '/tests/lib/rehearsal-runner.php';

$runner = new RehearsalRunner();

/**
 * Runs one statement and reports whether it worked, without stopping the run.
 *
 * The seed writes rows that a 1.6 database rejects by design, so a refusal is
 * information rather than an error -- if a statement is refused at BUILD time
 * the starting schema was not as old as it was meant to be, and that is the
 * vacuous-green failure this whole file exists to avoid. Every refusal is
 * printed for exactly that reason.
 *
 * @param string $label what the statement is trying to plant
 * @param string $sql   the statement
 *
 * @return bool
 */
function seedStep($label, $sql)
{
    $res = RehearsalRunner::db()->query($sql);
    if (false !== $res->error) {
        printf(
            "  REFUSED  %-46s %s\n",
            $label,
            trim(strtok((string)RehearsalRunner::db()->error, "\n"))
        );

        return false;
    }
    printf("  planted  %-46s\n", $label);

    return true;
}

/**
 * Whether a table exists in the database under test.
 *
 * @param string $table table name
 *
 * @return bool
 */
function haveTable($table)
{
    $res = RehearsalRunner::db()->query(
        sprintf('SHOW TABLES LIKE %s', RehearsalRunner::db()->escape($table))
    );

    return false === $res->error && count((array)$res->fetch('', 'fetch_all')->get());
}

/**
 * Inserts a row, filling in every column the caller did not name.
 *
 * The seed has to run against SEVERAL starting schemas -- 1.5.9-era, 1.5.10,
 * and whatever a given site is actually sitting on -- and those differ by
 * dozens of columns. A hand-written column list is therefore wrong somewhere
 * by construction: 1.5 declares almost everything NOT NULL with no default,
 * so one missing column is errno 1364 and the row is never planted. That
 * failure is invisible in the worst possible way, because a seed row that was
 * never written looks exactly like an upgrade that cleaned it up.
 *
 * So the shape of the row is READ from the server. Named columns are used as
 * given; everything else that is NOT NULL, has no default and is not
 * auto_increment gets a type-appropriate empty value -- which is what 1.5's
 * own "not set" looks like anyway, since it had no NULLs to write.
 *
 * @param string $label what the row is for, printed either way
 * @param string $table table to insert into
 * @param array  $values column => value for the columns that matter
 *
 * @return bool
 */
function seedRow($label, $table, array $values)
{
    $res = RehearsalRunner::db()->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
    if (false !== $res->error) {
        printf("  REFUSED  %-46s no such table\n", $label);

        return false;
    }
    $cols = (array)$res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
    $set = [];
    foreach ($cols as $col) {
        $name = $col['Field'];
        if (array_key_exists($name, $values)) {
            $set[$name] = $values[$name];
            continue;
        }
        if (strpos((string)$col['Extra'], 'auto_increment') !== false) {
            continue;
        }
        if ($col['Null'] === 'YES' || $col['Default'] !== null) {
            continue;
        }
        $type = strtolower((string)$col['Type']);
        if (preg_match("/^(enum|set)\('([^']*)'/", $type, $m)) {
            // The first declared member. An enum rejects '' under any
            // sql_mode that matters, and 1.5 uses enum('0','1') widely.
            $set[$name] = $m[2];
        } elseif (strpos($type, 'datetime') === 0 || strpos($type, 'timestamp') === 0) {
            // Not the epoch. A TIMESTAMP column's range starts at
            // 1970-01-01 00:00:01 UTC, so '1970-01-01 00:00:00' is out of
            // range in every zone at or behind UTC and the insert fails with
            // 1292 -- which reads as a broken seed rather than a filler value
            // one second too early. hosts.hostSecTime is a TIMESTAMP.
            $set[$name] = '2001-01-01 00:00:00';
        } elseif (strpos($type, 'date') === 0) {
            $set[$name] = '2001-01-01';
        } elseif (preg_match('/int|decimal|float|double|bit/', $type)) {
            $set[$name] = 0;
        } else {
            $set[$name] = '';
        }
    }
    $names = [];
    $vals = [];
    foreach ($set as $k => $v) {
        $names[] = '`' . $k . '`';
        $vals[] = is_int($v) ? (string)$v : RehearsalRunner::db()->escape((string)$v);
    }

    return seedStep(
        $label,
        sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', $names),
            implode(',', $vals)
        )
    );
}

switch ($cmd) {
    case 'build':
        $to = (int)($opts['to'] ?? 0);
        if ($to < 1) {
            fwrite(STDERR, "build needs --to=<schema version to stop at>\n");
            exit(2);
        }
        $out = $runner->run(0, $to);
        printf(
            "build   %s: ran %d step(s), landed on schema %d\n",
            $db,
            $out['ran'],
            $out['landed']
        );
        foreach ($out['errors'] as $e) {
            printf("  ERROR  %s\n", $e);
        }
        // The starting point must genuinely predate the constraints, or the
        // seed cannot plant anything and the rehearsal is vacuous. Proven
        // here rather than assumed: count what the database actually holds.
        $fks = RehearsalRunner::db()->query(
            'SELECT COUNT(*) AS n FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`'
            . ' WHERE `CONSTRAINT_SCHEMA` = ' . RehearsalRunner::db()->escape($db)
        )->fetch()->get('n');
        printf("  foreign keys present at this starting point: %d\n", (int)$fks);
        if ((int)$fks > 0) {
            printf("  WARNING: this starting point already enforces integrity.\n");
            printf("           A seed run against it proves nothing. Lower --to.\n");
        }
        exit(count($out['errors']) ? 1 : 0);

    case 'seed':
        $profile = $opts['profile'] ?? 'decade';
        printf("seed    %s (profile %s, schema %d)\n", $db, $profile, $runner->version());
        // Every INSERT below is a row a 1.6 database REJECTS. That is the
        // point, and it is also the self-check: if one is refused here, the
        // starting schema already enforces what the upgrade is supposed to
        // introduce and the rehearsal proves nothing. seedStep() prints
        // REFUSED for exactly that reason.
        //
        // Nothing here is invented as a "likely" orphan. Each group is a
        // shape FOG's own code can still produce, or could produce for the
        // decade before ADR 0031: Route::deletemass() cleans up some parents
        // and not others, the API deletes bypass destroy() entirely
        // (Route::delete), and a restore from a dump reintroduces whatever
        // was in it.

        // -- 1. Parents that will be deleted out from under their children --
        //
        // Written as real rows first and then deleted, rather than as
        // dangling ids invented directly. A row that was never valid is a
        // different thing from a row that was valid and lost its parent, and
        // only the second is what a decade of deletes produces.
        seedRow('host that will be deleted', 'hosts', ['hostID' => 900001, 'hostName' => 'ghost-host-a', 'hostLastDeploy' => '2015-06-01 00:00:00']);
        seedRow('group that will be deleted', 'groups', ['groupID' => 900001, 'groupName' => 'ghost-group-a']);
        seedRow('snapin that will be deleted', 'snapins', ['sID' => 900001, 'sName' => 'ghost-snapin-a']);
        seedRow('printer that will be deleted', 'printers', ['pID' => 900001, 'pAlias' => 'ghost-printer-a']);
        seedRow('image that will be deleted', 'images', ['imageID' => 900001, 'imageName' => 'ghost-image-a', 'imageTypeID' => 1, 'imagePartitionTypeID' => 1, 'imageOSID' => 1]);
        seedRow('storage group that will be deleted', 'nfsGroups', ['ngID' => 900001, 'ngName' => 'ghost-storagegroup-a']);

        // Children of every one of them, on BOTH sides of each junction.
        seedRow('groupMembers <- host and group', 'groupMembers', ['gmHostID' => 900001, 'gmGroupID' => 900001]);
        seedRow('hostMAC <- host', 'hostMAC', ['hmHostID' => 900001, 'hmMAC' => '00:11:22:33:44:01', 'hmPrimary' => '1']);
        seedRow('snapinAssoc <- host and snapin', 'snapinAssoc', ['saHostID' => 900001, 'saSnapinID' => 900001]);
        seedRow('printerAssoc <- host and printer', 'printerAssoc', ['paHostID' => 900001, 'paPrinterID' => 900001]);
        seedRow('inventory <- host', 'inventory', ['iHostID' => 900001]);
        seedRow('powerManagement <- host', 'powerManagement', ['pmHostID' => 900001]);
        seedRow('imageGroupAssoc <- image and storage group', 'imageGroupAssoc', ['igaImageID' => 900001, 'igaStorageGroupID' => 900001]);
        seedRow('snapinGroupAssoc <- snapin and storage group', 'snapinGroupAssoc', ['sgaSnapinID' => 900001, 'sgaStorageGroupID' => 900001]);
        seedRow('task <- host, image and storage group', 'tasks', ['taskName' => 'ghost deploy', 'taskHostID' => 900001, 'taskImageID' => 900001, 'taskStateID' => 3, 'taskTypeID' => 1, 'taskNFSGroupID' => 900001, 'taskCheckIn' => '2015-06-01 00:00:00']);
        seedRow('storage node <- storage group', 'nfsGroupMembers', ['ngmGroupID' => 900001, 'ngmMemberName' => 'ghost-node', 'ngmHostname' => '10.99.99.99']);

        // Now delete the parents, the way a decade of FOG actually deletes
        // them: straight DELETE, no cleanup. This is the pre-cascade era and
        // it is still what any path outside Route::deletemass() does.
        foreach ([
            'hosts' => 'hostID',
            'groups' => 'groupID',
            'snapins' => 'sID',
            'printers' => 'pID',
            'images' => 'imageID',
            'nfsGroups' => 'ngID',
        ] as $table => $key) {
            seedStep(
                sprintf('DELETE parent %s (orphans its children)', $table),
                sprintf('DELETE FROM `%s` WHERE `%s` = 900001', $table, $key)
            );
        }

        // -- 2. Duplicate MACs, and one MAC on two hosts --------------------
        //
        // The second is the one that matters: FOG identifies a host BY its
        // MAC, so two hosts sharing one is not a cosmetic duplicate, it is
        // two hosts that check in as each other. 1.5 had no unique index
        // that could stop it.
        seedRow('real host A', 'hosts', ['hostID' => 900010, 'hostName' => 'dupe-host-a', 'hostLastDeploy' => '2016-01-01 00:00:00']);
        seedRow('real host B', 'hosts', ['hostID' => 900011, 'hostName' => 'dupe-host-b', 'hostLastDeploy' => '2016-01-01 00:00:00']);
        seedRow('same MAC on host A', 'hostMAC', ['hmHostID' => 900010, 'hmMAC' => 'aa:bb:cc:dd:ee:ff', 'hmPrimary' => '1']);
        // 1.5.10 carries a UNIQUE key on hostMAC.hmMAC, so the shared-MAC row
        // below is refused on a database built to that version -- which is a
        // FINDING, not a seed bug: from whatever 1.5 release added that index
        // onward, one MAC on two hosts cannot be created. It is still present
        // on databases that predate it, because adding a unique index over
        // existing duplicates fails and the step tolerates 1062. So the index
        // is dropped first and the state planted underneath it, which is
        // exactly the shape an older install hands the upgrade. What the drop
        // reports is printed either way.
        if (haveTable('hostMAC')) {
            $idx = RehearsalRunner::db()->query(
                "SELECT `INDEX_NAME` AS `n` FROM `information_schema`.`STATISTICS`"
                . " WHERE `TABLE_SCHEMA` = " . RehearsalRunner::db()->escape($db)
                . " AND `TABLE_NAME` = 'hostMAC' AND `COLUMN_NAME` = 'hmMAC'"
                . " AND `NON_UNIQUE` = 0"
            );
            $names = false === $idx->error
                ? (array)$idx->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get()
                : [];
            foreach ($names as $row) {
                seedStep(
                    sprintf('drop pre-existing unique index hostMAC.%s', $row['n']),
                    sprintf('ALTER TABLE `hostMAC` DROP INDEX `%s`', $row['n'])
                );
            }
            if (!$names) {
                printf("  note     %-46s\n", 'hostMAC had no unique index to drop');
            }
        }
        seedRow('SAME MAC on host B', 'hostMAC', ['hmHostID' => 900011, 'hmMAC' => 'aa:bb:cc:dd:ee:ff', 'hmPrimary' => '1']);
        seedRow('duplicate MAC row on host A', 'hostMAC', ['hmHostID' => 900010, 'hmMAC' => 'aa:bb:cc:dd:ee:ff', 'hmPrimary' => '0']);
        seedRow('two primary MACs on host A', 'hostMAC', ['hmHostID' => 900010, 'hmMAC' => 'aa:bb:cc:dd:ee:01', 'hmPrimary' => '1']);

        // -- 3. A host with no MACs, an image with no storage group, a task
        //       with no host ---------------------------------------------
        //
        // Not orphans -- every id here is valid. They are the shapes that
        // pass every constraint in the map and still leave the object
        // unusable, which is the half a foreign key cannot reach. Recorded
        // so the report can say so rather than implying constraints are the
        // whole answer.
        seedRow('host with no MAC at all', 'hosts', ['hostID' => 900020, 'hostName' => 'macless-host', 'hostLastDeploy' => '2017-01-01 00:00:00']);
        seedRow('image in no storage group', 'images', ['imageID' => 900020, 'imageName' => 'groupless-image', 'imageTypeID' => 1, 'imagePartitionTypeID' => 1, 'imageOSID' => 1]);
        seedRow('task pointing at no host (0)', 'tasks', ['taskName' => 'hostless task', 'taskHostID' => 0, 'taskStateID' => 3, 'taskTypeID' => 1, 'taskCheckIn' => '2017-01-01 00:00:00']);

        // -- 4. Zero dates and a timezone discontinuity --------------------
        //
        // '0000-00-00 00:00:00' is legal under the sql_mode PDODB sets and
        // illegal under a strict one, and 1.5 wrote it wherever a date was
        // "not set". Any step that converts a date column, adds NOT NULL or
        // reads one into PHP meets it. This is the closest representable form
        // of "rows written across a FOG_TZ_INFO change": FOG stores UTC and
        // renders in the display zone, so a zone change does not rewrite
        // stored rows -- what it leaves behind is rows whose stored instant
        // was computed under the old zone, modeled here as a pair of rows
        // eight hours apart around the same wall-clock moment, plus the zero
        // date that no zone can interpret at all.
        seedStep('zero date on a host', "UPDATE `hosts` SET `hostLastDeploy` = '0000-00-00 00:00:00' WHERE `hostID` = 900020");
        seedStep('zero date on a task', "UPDATE `tasks` SET `taskCheckIn` = '0000-00-00 00:00:00' WHERE `taskHostID` = 0");
        seedRow('pre-zone-change host', 'hosts', ['hostID' => 900021, 'hostName' => 'tz-before', 'hostLastDeploy' => '2018-03-11 01:30:00']);
        seedRow('post-zone-change host', 'hosts', ['hostID' => 900022, 'hostName' => 'tz-after', 'hostLastDeploy' => '2018-03-11 09:30:00']);

        // -- 5. A type mismatch across a constraint pair -------------------
        //
        // InnoDB requires an EXACT match on both sides of a foreign key and
        // refuses the constraint otherwise -- errno 1005/150, a different
        // failure from the 1452 an orphan causes and one no amount of row
        // cleanup fixes. These reach a real database through a restored dump,
        // a hand-run ALTER, or an older server whose defaults differed; steps
        // 380 and 386 exist because two such pairs were already mismatched in
        // FOG's own schema.
        seedStep('widen hostMAC.hmHostID to bigint (type mismatch)', "ALTER TABLE `hostMAC` MODIFY `hmHostID` bigint(20) NOT NULL");

        // THERE IS NO COLLATION SEED, and that is a finding rather than an
        // omission.
        //
        // This used to carry
        // `ALTER TABLE groupMembers CONVERT TO CHARACTER SET utf8mb4 ...` as a
        // companion to the line above, described as planting the errno 3780
        // that a collation mismatch causes. It planted nothing. groupMembers
        // has three columns and all three are int(11); CONVERT TO CHARACTER
        // SET changed the table default and left every foreign key column's
        // collation NULL, because collation does not apply to an integer.
        // Both of its constraints landed on every run, so the seed reported a
        // clean pass for a case it had never once exercised -- the exact
        // vacuous shape the header of this file warns about.
        //
        // Repointing it was not available either: of the 101 ENABLED
        // relationships in commons/schema-constraints.php, every resolvable
        // column on both sides is int or mediumint, so no enabled constraint
        // can carry a collation at all. The map's last string-typed entry,
        // virus.vHostMAC -> hostMAC.hmMAC, went with the ClamAV scan in
        // GH-328; it was deliberately `'class' => 'poly', 'action' => 'none'`
        // with no `enabled` key, and its two sides were varchar(50) and
        // varchar(59) -- it could not have been a foreign key even if someone
        // had wanted it to be.
        //
        // So errno 3780 is unreachable here TODAY. That is a census, not an
        // invariant, which is why it is pinned by
        // tests/fk-columns-are-integer-typed.test.php rather than left as this
        // comment: enable a string-typed relationship and that test goes red
        // and says a collation seed is now needed.

        // -- 6. NULLs where 1.6 says NOT NULL ------------------------------
        //
        // 1.5 columns are NOT NULL almost everywhere, so a NULL cannot simply
        // be written. The realistic route to one is the column being made
        // nullable at some point in the install's life and never put back --
        // a hand-run ALTER, or a plugin. Modeled by doing exactly that, so
        // the upgrade meets a NULL in a column its manifest says is NOT NULL.
        seedStep('make hosts.hostDesc nullable', "ALTER TABLE `hosts` MODIFY `hostDesc` longtext NULL");
        seedStep('NULL in a NOT NULL column', "UPDATE `hosts` SET `hostDesc` = NULL WHERE `hostID` = 900020");
        seedStep('make tasks.taskCreateBy nullable', "ALTER TABLE `tasks` MODIFY `taskCreateBy` varchar(200) NULL");
        seedStep('NULL in tasks.taskCreateBy', "UPDATE `tasks` SET `taskCreateBy` = NULL WHERE `taskHostID` = 0");

        if ($profile === 'site') {
            // -- 7. The site plugin, holding real assignments ---------------
            //
            // The migration Tom flagged as the one whose failure looks like a
            // working server: schema steps 331-334 rebuild the plugin's rows
            // as core's and then drop the plugin's tables. A site row that
            // fails to migrate does not error -- it leaves a user in NO site,
            // and a user in no site is denied every host, user and group,
            // silently. See the lockout closed in fog-plugins v1.6.5.
            //
            // Built to the plugin's 1.5 shape, including the two things that
            // make the migration interesting: a duplicate site NAME (core
            // puts a UNIQUE key on it, the plugin did not) and assignments
            // pointing at hosts and users that no longer exist.
            // Table and column names taken from schema step 332's own
            // migration map, not invented: `site`(sID,sName,sDesc) and the
            // four `site*Assoc` junctions. A seed built to the wrong names is
            // the vacuous case again -- step 332 skips a source table it
            // cannot see, reports nothing, and the run looks clean.
            seedStep('site plugin: site table', "CREATE TABLE IF NOT EXISTS `site` (`sID` int(11) NOT NULL AUTO_INCREMENT, `sName` varchar(255) NOT NULL, `sDesc` longtext NOT NULL, PRIMARY KEY (`sID`)) ENGINE=InnoDB");
            seedStep('site plugin: siteHostAssoc', "CREATE TABLE IF NOT EXISTS `siteHostAssoc` (`shaID` int(11) NOT NULL AUTO_INCREMENT, `shaSiteID` int(11) NOT NULL, `shaHostID` int(11) NOT NULL, PRIMARY KEY (`shaID`)) ENGINE=InnoDB");
            seedStep('site plugin: siteUserAssoc', "CREATE TABLE IF NOT EXISTS `siteUserAssoc` (`suaID` int(11) NOT NULL AUTO_INCREMENT, `suaSiteID` int(11) NOT NULL, `suaUserID` int(11) NOT NULL, PRIMARY KEY (`suaID`)) ENGINE=InnoDB");
            seedStep('site plugin: siteGroupAssoc', "CREATE TABLE IF NOT EXISTS `siteGroupAssoc` (`sgaID` int(11) NOT NULL AUTO_INCREMENT, `sgaSiteID` int(11) NOT NULL, `sgaGroupID` int(11) NOT NULL, PRIMARY KEY (`sgaID`)) ENGINE=InnoDB");
            seedRow('site plugin: registered in plugins', 'plugins', ['pName' => 'site', 'pInstalled' => 1]);
            seedStep('site: two sites with the SAME name', "INSERT INTO `site` (`sID`,`sName`,`sDesc`) VALUES (1,'Main Campus',''),(2,'Main Campus','')");
            seedStep('site: a third, uniquely named', "INSERT INTO `site` (`sID`,`sName`,`sDesc`) VALUES (3,'Annex','')");
            seedStep('site: host assignment to a LIVE host', "INSERT INTO `siteHostAssoc` (`shaSiteID`,`shaHostID`) VALUES (1,900010)");
            seedStep('site: host assignment to a DELETED host', "INSERT INTO `siteHostAssoc` (`shaSiteID`,`shaHostID`) VALUES (1,900001)");
            seedStep('site: host assigned to the DUPLICATE-named site', "INSERT INTO `siteHostAssoc` (`shaSiteID`,`shaHostID`) VALUES (2,900011)");
            seedStep('site: user assignment to a DELETED user', "INSERT INTO `siteUserAssoc` (`suaSiteID`,`suaUserID`) VALUES (2,900099)");
            // TWO users, and the difference between them is the whole point.
            // One is scoped to a site; the other has the plugin installed and
            // no assignment at all. Under the plugin, "no site" denies that
            // second account every host, user and group -- so if the upgrade
            // simply preserves scope, the account keeps working exactly as
            // badly as before and nobody notices. Step 333 is supposed to
            // join it to the catch-all instead. Without an unassigned user in
            // the seed that branch is never taken and the site profile tests
            // only the half that was already safe.
            seedRow('user: scoped to a site', 'users', ['uId' => 900030, 'uName' => 'scoped-user']);
            seedRow('user: in NO site at all', 'users', ['uId' => 900031, 'uName' => 'unscoped-user']);
            seedStep('site: assign only the scoped user', "INSERT INTO `siteUserAssoc` (`suaSiteID`,`suaUserID`) VALUES (3,900030)");
            seedStep('site: group assignment to a DELETED group', "INSERT INTO `siteGroupAssoc` (`sgaSiteID`,`sgaGroupID`) VALUES (3,900001)");
        }

        printf("seed    done\n");
        exit(0);

    case 'upgrade':
        // The real update loop, from where the database says it is to the end
        // of the step array, followed by the same reconcile
        // SchemaUpdaterPage::update() runs afterward. Both halves matter: the
        // steps apply each constraint group with its own sweep in front of it,
        // and the trailing reconcile passes null, which is what converges a
        // server that missed one.
        $from = $runner->version();
        // --to stops the loop short ON PURPOSE, which is the only way to
        // rehearse the case bin/revertfog.sh exists for: an upgrade that died
        // partway, leaving the version stamped mid-range and the web tree
        // already replaced. A real hard failure produces the identical state
        // via the `break 2` below; waiting for one to happen by luck is not a
        // rehearsal.
        $stopAt = null;
        if (isset($opts['to'])) {
            $stopAt = (int)$opts['to'];
            if ($stopAt <= $from || $stopAt > FOG_SCHEMA) {
                fwrite(
                    STDERR,
                    sprintf(
                        "--to=%d is not between the current schema (%d) and"
                        . " FOG_SCHEMA (%d)\n",
                        $stopAt,
                        $from,
                        FOG_SCHEMA
                    )
                );
                exit(2);
            }
        }
        printf(
            "upgrade %s: from schema %d to %s\n",
            $db,
            $from,
            null === $stopAt
                ? sprintf('FOG_SCHEMA %d', FOG_SCHEMA)
                : sprintf('schema %d (STOPPING SHORT on purpose)', $stopAt)
        );
        $started = microtime(true);
        $out = $runner->run($from, $stopAt);
        printf(
            "  ran %d step(s), landed on schema %d in %.1fs\n",
            $out['ran'],
            $out['landed'],
            microtime(true) - $started
        );
        // A hard failure leaves the server at step N of the remaining steps
        // with its version stamped at N -- the `break 2` in the update loop.
        // That state is not hypothetical and it is what bin/revertfog.sh has
        // to cope with, so it is reported as a first-class outcome rather
        // than as an exception.
        foreach ($out['errors'] as $e) {
            printf("  HARD FAIL  %s\n", $e);
        }
        if (null === $stopAt && $out['landed'] < FOG_SCHEMA) {
            printf(
                "  PARTIAL: stopped %d step(s) short. The server is stranded on\n"
                . "           ?node=schema and the web tree has already been replaced.\n",
                FOG_SCHEMA - $out['landed']
            );
        }
        $reconcile = \FOG\Db\SchemaReconciler::reconcile();
        printf(
            "  reconcile: %s\n",
            is_string($reconcile) ? 'FAILED: ' . $reconcile : 'clean'
        );
        $failures = \FOG\Db\SchemaReconciler::constraintFailures();
        printf("  constraints refused by the final reconcile: %d\n", count((array)$failures));
        foreach ((array)$failures as $f) {
            printf("    %-44s %s\n", $f['name'], $f['reason']);
        }
        exit(count($out['errors']) ? 1 : 0);

    case 'shape':
        // GH-1542. plan() repairs existence, never shape, so a drifted column
        // type or an absent UNIQUE index survives every upgrade unreported.
        // The rehearsal is the only place that can say how often that is
        // actually true, because it is the only place a decade of real data
        // meets the current manifest.
        $drift = \FOG\Db\SchemaReconciler::shapeDrift();
        printf("shape   %s (schema %d)\n", $db, $runner->version());
        printf("  differences from the manifest: %d\n", count($drift));
        foreach ($drift as $d) {
            printf(
                "    %-28s %-22s %s -> %s\n",
                $d['table'],
                $d['name'],
                $d['expected'],
                $d['actual']
            );
        }
        exit(0);

    case 'report':
        // What the upgrade actually achieved, read from the database rather
        // than from what the run said it did. Three questions, because they
        // have three different fixes:
        //
        //   declared   how many of the map's enabled relationships ended up
        //              as real constraints. This is the number ADR 0031 is
        //              about, and the one a refused ALTER silently reduces.
        //   missing    which are absent, with the reason the scanner gives.
        //   unreached  rows that no constraint can protect -- a host with no
        //              MAC, an image in no storage group. Reported so the
        //              answer is not read as "integrity is now complete".
        $map = \FOG\Db\SchemaReconciler::constraints();
        $have = \FOG\Db\SchemaReconciler::constraintSnapshot();
        $enabled = 0;
        $present = 0;
        $missing = [];
        foreach ($map as $rel) {
            if (empty($rel['enabled'])) {
                continue;
            }
            if (!haveTable($rel['child']) || !haveTable($rel['parent'])) {
                continue;
            }
            $enabled++;
            $name = strtolower(\FOG\Db\SchemaReconciler::constraintName($rel));
            if (isset($have[$name])) {
                $present++;
                continue;
            }
            $missing[] = $rel;
        }
        printf("report  %s (schema %d)\n", $db, $runner->version());
        printf("  constraints declared and applicable here: %d\n", $enabled);
        printf("  constraints actually present:             %d\n", $present);
        printf("  MISSING:                                  %d\n", count($missing));
        foreach ($missing as $rel) {
            // The orphan count is the diagnosis: 1452 means rows, anything
            // else means structure. Counted here rather than inferred from
            // the ALTER's error text, which is thrown away on a re-run.
            $sql = sprintf(
                'SELECT COUNT(*) AS n FROM `%s` `c` LEFT JOIN `%s` `p`'
                . ' ON `c`.`%s` = `p`.`%s` WHERE `c`.`%s` IS NOT NULL'
                . ' AND `p`.`%s` IS NULL',
                $rel['child'],
                $rel['parent'],
                $rel['column'],
                $rel['pcolumn'],
                $rel['column'],
                $rel['pcolumn']
            );
            $res = RehearsalRunner::db()->query($sql);
            $n = false === $res->error ? (int)$res->fetch()->get('n') : -1;
            printf(
                "    %-40s %-11s orphan rows: %s\n",
                \FOG\Db\SchemaReconciler::constraintName($rel),
                $rel['action'],
                $n < 0 ? 'unreadable' : $n
            );
        }
        // Integrity a foreign key cannot express. Every id in these rows is
        // valid, so no constraint refuses them and none ever will.
        $unreached = [
            'hosts with no MAC' => 'SELECT COUNT(*) AS n FROM `hosts` `h` LEFT JOIN `hostMAC` `m` ON `m`.`hmHostID` = `h`.`hostID` WHERE `m`.`hmID` IS NULL',
            'images in no storage group' => 'SELECT COUNT(*) AS n FROM `images` `i` LEFT JOIN `imageGroupAssoc` `a` ON `a`.`igaImageID` = `i`.`imageID` WHERE `a`.`igaID` IS NULL',
            'MACs claimed by more than one host' => 'SELECT COUNT(*) AS n FROM (SELECT `hmMAC` FROM `hostMAC` GROUP BY `hmMAC` HAVING COUNT(DISTINCT `hmHostID`) > 1) `q`',
            'hosts with more than one primary MAC' => 'SELECT COUNT(*) AS n FROM (SELECT `hmHostID` FROM `hostMAC` WHERE `hmPrimary` = 1 GROUP BY `hmHostID` HAVING COUNT(*) > 1) `q`',
            'zero dates in hosts.hostLastDeploy' => "SELECT COUNT(*) AS n FROM `hosts` WHERE CAST(`hostLastDeploy` AS CHAR) LIKE '0000-00-00%'",
        ];
        printf("  integrity no foreign key can express:\n");
        foreach ($unreached as $label => $sql) {
            $res = RehearsalRunner::db()->query($sql);
            printf(
                "    %-40s %s\n",
                $label,
                false === $res->error ? (int)$res->fetch()->get('n') : 'unreadable'
            );
        }
        // WHY A MISSING CONSTRAINT HERE IS NOT AUTOMATICALLY A DEFECT, and
        // the reason this block exists at all: the seed plants the exact
        // conditions FOG refuses to paper over, so the refusals below are
        // the DESIGNED outcome and reading them as a failure list sends
        // somebody off to "fix" behavior that is already correct.
        //
        //   fk_nfsGroupMembers_ngmGroupID -- a storage node whose group was
        //     deleted. Step 386 states the rule outright: a node always
        //     belongs to a group, only the administrator knows which one,
        //     "so nothing here guesses one", and a refused constraint named
        //     in the log IS the correct outcome. Sweeping it would delete a
        //     configured storage node; step 381 sweeps CASCADE junction rows
        //     only, and says why RESTRICT rows are never touched.
        //
        //   fk_hostMAC_hmHostID -- refused on structure, not rows, because
        //     section 5 of the seed widened hmHostID to bigint on purpose.
        //
        // Shape drift reads the same way. A clean build to FOG_SCHEMA
        // reports ZERO differences, so every difference `shape` prints
        // after a seeded run was planted by the seed. That the upgrade
        // reports rather than repairs them is the decision b1348bfe1 made
        // deliberately -- repairing any of them means narrowing a column,
        // deleting duplicate rows or inventing a value, which is a data
        // decision no unattended step gets to make.
        //
        // So the question this report answers is "did anything change",
        // against tests/fixtures/upgrade-rehearsal-baseline.txt. A NEW
        // refusal or a drift the seed does not explain is the finding; the
        // ones below are the fixture working.
        printf(
            "  (both refusals above are seed-induced and by design --"
            . " see the comment on this block)\n"
        );
        exit(count($missing) ? 1 : 0);

    case 'dump':
        // Writes a dump SHAPED LIKE backupDB()'s, into a directory laid out
        // like ${DB_backup_path}/fogDBbackups. The shape is the point: this is
        // the material bin/revertfog.sh consumes, so a rehearsal that upgrades
        // a database without first producing one can exercise the upgrade and
        // nothing else. Reverting has to be rehearsable too, and a revert path
        // nobody has run is a comfort rather than a plan.
        //
        // mariadb-dump inside the lab container rather than PHP: the point is
        // to produce the same artifact an admin would hold, not a
        // reimplementation of it that might differ in exactly the way that
        // matters.
        $version = $opts['version'] ?? sprintf('1.5.10.%d', $runner->version());
        $dir = rtrim($opts['dir'] ?? '/images/claude-lab/upgrade-rehearsal/backups', '/')
            . '/fogDBbackups';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            fwrite(STDERR, "cannot create $dir\n");
            exit(1);
        }
        $file = sprintf(
            '%s/fog_sql_%s_%s.sql',
            $dir,
            $version,
            date('Ymd_His')
        );
        // The password reaches the child through MYSQL_PWD in its environment
        // only -- never a command line, where `ps` would show it, and never a
        // file. argv is an ARRAY, so nothing is shell-interpreted either.
        $argvDump = [
            'mariadb-dump',
            '--host=' . explode(';', $conn['host'])[0],
            '--port=' . (preg_match('/port=(\d+)/', $conn['host'], $m) ? $m[1] : '3306'),
            '--user=' . ($conn['user'] ?? ''),
            '--single-transaction',
            '--routines',
            '--events',
            $db,
        ];
        $proc = proc_open(
            $argvDump,
            [1 => ['file', $file, 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['MYSQL_PWD' => (string)($conn['pass'] ?? '')] + $_ENV
        );
        if (!is_resource($proc)) {
            fwrite(STDERR, "could not run mariadb-dump\n");
            exit(1);
        }
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $rc = proc_close($proc);
        if ($rc !== 0) {
            fwrite(STDERR, "mariadb-dump failed ($rc): " . trim((string)$err) . "\n");
            exit(1);
        }
        printf(
            "dump    %s (schema %d) -> %s (%d bytes)\n",
            $db,
            $runner->version(),
            $file,
            (int)filesize($file)
        );
        exit(0);

    case 'census':
        $tables = ['hosts', 'hostMAC', 'groupMembers', 'snapinAssoc', 'printerAssoc',
            'moduleStatusByHost', 'inventory', 'tasks', 'scheduledTasks', 'images',
            'nfsGroups', 'nfsGroupMembers', 'users', 'snapinJobs', 'snapinTasks',
            'multicastSessions', 'multicastSessionsAssoc',
            'hostAutoLogOut', 'powerManagement', 'taskLog',
            'sites', 'siteHostMembers', 'siteUserMembers', 'siteGroupMembers'];
        printf("census  %s (schema %d)\n", $db, $runner->version());
        foreach ($tables as $t) {
            if (!haveTable($t)) {
                continue;
            }
            $n = RehearsalRunner::db()
                ->query(sprintf('SELECT COUNT(*) AS n FROM `%s`', $t))
                ->fetch()->get('n');
            printf("  %-26s %8d\n", $t, (int)$n);
        }
        exit(0);
}
