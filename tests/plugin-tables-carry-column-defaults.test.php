<?php
/**
 * A plugin install must build its table with a DEFAULT on every optional
 * column -- and without one on the columns that must keep their teeth.
 *
 * GH-1245, the plugin half. Schema step 286 gives the optional columns of an
 * existing install a default, but it cannot help a plugin table: install()
 * calls uninstall(), uninstall() DROPS the table, and createTable() then
 * rebuilt it with `NOT NULL` and no default on almost everything. 72 of the
 * 99 columns across the plugin tables came back mandatory, and under the
 * server's own sql_mode any INSERT omitting one fails with error 1364.
 *
 * Two defects, and the second was hiding the first:
 *
 *   - createTable() tested the caller's default for TRUTHINESS, so a default
 *     of '0' -- an enum's first member, a boolean-ish flag -- was dropped on
 *     the floor. Thirteen columns across the tree lost their default that
 *     way, three of them in the LDAP plugin.
 *   - what callers pass is a VALUE, not SQL. Emitting them raw put an
 *     unquoted 0 against an ENUM, where 0 is an INDEX and index 0 is the
 *     error value, and an unquoted zero date against a TIMESTAMP. The server
 *     refuses both -- so fixing the truthiness test ALONE would have made
 *     five CREATE TABLE statements fail outright. That is why
 *     defaultLiteral() exists and why it is checked here.
 *
 * WHICH COLUMNS KEEP THEIR TEETH is not a judgement call and not a list kept
 * by hand: FOGManagerController already holds the model's
 * $databaseFieldsRequired, resolved up the inheritance chain by its
 * constructor. Three kinds of column stay bare -- the primary key and
 * auto-increment column, anything the model declares required, and anything
 * whose name ends in ID. That is deliberately the SAME rule schema step 286
 * applies, so a table built by a plugin install and a table migrated by the
 * step say the same thing.
 *
 * The core managers are deliberately NOT converted: nothing calls a core
 * manager's install() -- their tables come from commons/schema.php -- so
 * changing 46 unreachable methods would be churn. They still get the
 * defaultLiteral() fix, because createTable() is shared and without it four
 * of them would now emit invalid DDL.
 *
 * The live half was proven separately, against a real 1.5 server:
 * scripts/background_scripts/prove_plugin_table_defaults.php builds every
 * plugin's CREATE TABLE through FOG's own boot, creates it under a scratch
 * name, and reads the result back out of information_schema.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = array();
$checks = 0;

/**
 * Records a check.
 *
 * @param bool   $ok      whether it passed
 * @param string $message what failed, stated as the defect
 *
 * @return void
 */
function ptCheck($ok, $message)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
}

/**
 * Lifts one method's source out of a class file by matching braces.
 *
 * The two methods under test are pure enough to run on their own, and running
 * the SHIPPED source beats restating its rules in the test -- a restatement
 * passes happily while the real thing is broken.
 *
 * @param string $src  the file contents
 * @param string $name the method name
 *
 * @return string|false
 */
function ptGrabMethod($src, $name)
{
    $at = strpos($src, 'public static function ' . $name . '(');
    if (false === $at) {
        return false;
    }
    $open = strpos($src, '{', $at);
    if (false === $open) {
        return false;
    }
    $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') {
            $depth++;
        }
        if ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $at, $i - $at + 1);
            }
        }
    }

    return false;
}

$schemaSrc = file_get_contents($web . '/lib/fog/schema.class.php');
$managerSrc = file_get_contents($web . '/lib/fog/fogmanagercontroller.class.php');

// ---------------------------------------------------------------
// 1. defaultLiteral() renders a value as SQL, and knows an expression.
// ---------------------------------------------------------------
$literal = ptGrabMethod($schemaSrc, 'defaultLiteral');
ptCheck(
    false !== $literal,
    'Schema::defaultLiteral() is gone. Caller defaults go back to being '
    . 'emitted raw, which puts an unquoted 0 against an ENUM -- where 0 is '
    . 'an index and index 0 is the error value -- and the server refuses the '
    . 'CREATE TABLE outright.'
);
if (false !== $literal) {
    eval('class PtLiteral { ' . $literal . ' }');
    $cases = array(
        // input, expected, why it matters
        array('0', "'0'", "an enum's first member must be quoted; unquoted 0 "
            . 'is an index and index 0 is the error value'),
        array('1', "'1'", 'same, for the second member'),
        array('0000-00-00 00:00:00', "'0000-00-00 00:00:00'",
            'an unquoted zero date is a syntax error'),
        array('https', "'https'", 'a bare word is not an SQL expression'),
        array('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP',
            'quoting this turns a live default into the literal string'),
        array('current_timestamp()', 'current_timestamp()',
            'the parenthesised spelling is the same expression'),
        array('NOW()', 'NOW()', 'likewise'),
        array("'already'", "'already'",
            'a caller that already quoted must not be double-quoted'),
        array("('')", "('')",
            "MySQL 8.0.13+ needs a TEXT default written as an expression"),
        array("it's", "'it''s'", 'an embedded quote must be escaped'),
    );
    foreach ($cases as $case) {
        list($in, $want, $why) = $case;
        $got = PtLiteral::defaultLiteral($in);
        ptCheck(
            $got === $want,
            sprintf(
                'defaultLiteral(%s) returned %s, wanted %s -- %s',
                var_export($in, true),
                var_export($got, true),
                var_export($want, true),
                $why
            )
        );
    }
}

// ---------------------------------------------------------------
// 2. emptyDefaultFor() writes down the server's own implicit zero.
// ---------------------------------------------------------------
$empty = ptGrabMethod($schemaSrc, 'emptyDefaultFor');
ptCheck(
    false !== $empty,
    'Schema::emptyDefaultFor() is gone, so createTableSql() has nothing to '
    . 'fill an optional column with and every plugin table goes back to '
    . 'being mandatory throughout.'
);
if (false !== $empty) {
    // The LOB arm asks the server its version; everything else returns
    // before touching it. A fake with just enough shape to answer that.
    eval('
        class PtFakeRow {
            public $v;
            public function __construct($v) { $this->v = $v; }
            public function get($k) { return $this->v; }
        }
        class PtFakeDB {
            public $v;
            public function __construct($v) { $this->v = $v; }
            public function query($sql) { return $this; }
            public function fetch() { return new PtFakeRow($this->v); }
        }
    ');
    $server = function ($version) use ($empty) {
        // A fresh class each time: emptyDefaultFor() caches the version in a
        // static, which is right in production and would hide a bug here.
        static $n = 0;
        $cls = 'PtEmpty' . (++$n);
        eval(
            'class ' . $cls . ' { public static $DB; ' . $empty . ' }'
        );
        $cls::$DB = new PtFakeDB($version);

        return $cls;
    };

    $maria = $server('11.8.8-MariaDB');
    // Every type the tree's createTable() callers actually write, because
    // these are hand-written type strings, not information_schema's
    // vocabulary: 'INTEGER' appears 120 times and does not match a rule
    // written for 'int(11)'.
    $simple = array(
        'INTEGER' => '0',
        'int(11)' => '0',
        'BIGINT(20)' => '0',
        'MEDIUMINT' => '0',
        'TINYINT(1)' => '0',
        'BOOLEAN' => '0',
        'DATETIME' => 'current_timestamp()',
        'TIMESTAMP' => 'current_timestamp()',
        'VARCHAR(255)' => "''",
        "ENUM('0', '1')" => "'0'",
        "ENUM('shutdown','reboot','wol')" => "'shutdown'",
        'LONGTEXT' => "''",
        'TEXT' => "''",
        'LONGBLOB' => "''",
    );
    foreach ($simple as $type => $want) {
        $got = $maria::emptyDefaultFor($type);
        ptCheck(
            $got === $want,
            sprintf(
                'emptyDefaultFor(%s) returned %s on MariaDB, wanted %s. This '
                . 'is the value the server used to substitute silently while '
                . 'sql_mode was cleared; writing down a different one changes '
                . 'behaviour rather than recording it.',
                var_export($type, true),
                var_export($got, true),
                var_export($want, true)
            )
        );
    }

    $my8 = $server('8.0.36');
    ptCheck(
        $my8::emptyDefaultFor('longtext') === "('')",
        'MySQL 8.0.13+ requires a TEXT/BLOB default written as a '
        . 'parenthesised expression and rejects the bare literal, so the '
        . 'CREATE TABLE fails on MySQL while passing on MariaDB.'
    );
    ptCheck(
        $my8::emptyDefaultFor('INTEGER') === '0',
        'the MySQL branch changed a non-LOB default'
    );

    $my57 = $server('5.7.44');
    ptCheck(
        null === $my57::emptyDefaultFor('longtext'),
        'MySQL below 8.0.13 cannot carry a default on a TEXT or BLOB column '
        . 'at all. Returning anything but null there makes the CREATE TABLE '
        . 'fail on an older server; null means "leave it bare", which costs '
        . 'nothing because save() writes the column and insertBatch() '
        . 'backfills it.'
    );
}

// ---------------------------------------------------------------
// 3. createTable() no longer tests the default for truthiness.
// ---------------------------------------------------------------
ptCheck(
    (bool) preg_match(
        '/self::defaultLiteral\(\s*\$default\[\$i\]\s*\)/',
        $schemaSrc
    ),
    'createTable() emits the caller default without defaultLiteral(). An '
    . "unquoted 0 against an ENUM and an unquoted zero date against a "
    . 'TIMESTAMP are both refused, so five CREATE TABLE statements in the '
    . 'tree stop working.'
);
ptCheck(
    !preg_match('/\n\s*\$default\[\$i\] \?\n/', $schemaSrc),
    "createTable() tests the caller's default for truthiness again. '0' is "
    . 'falsey in PHP, so a DEFAULT of \'0\' is silently dropped and the '
    . 'column ships bare -- which is GH-1245 inflicted by the builder itself.'
);

// ---------------------------------------------------------------
// 4. createTableSql() exists and keeps all three exemptions.
// ---------------------------------------------------------------
ptCheck(
    false !== strpos($managerSrc, 'public function createTableSql('),
    'FOGManagerController::createTableSql() is gone, so a plugin install '
    . 'builds its table straight through createTable() again and every '
    . 'optional column comes back mandatory.'
);
$wrapper = '';
$at = strpos($managerSrc, 'public function createTableSql(');
if (false !== $at) {
    $wrapper = substr($managerSrc, $at, 4000);
}
$exemptions = array(
    'databaseFieldsRequired' =>
        'a column the model declares required would be given a default, so '
        . 'the model refuses the save and the database accepts it -- every '
        . 'write path that skips isValid() can still create the forbidden row',
    '$prime' =>
        'the primary key would be given a default',
    '$autoin' =>
        'the auto-increment column would be given a default, which MySQL '
        . 'refuses outright',
    "preg_match('/ID\$/', \$field)" =>
        'a foreign key would be given a default, so an INSERT that forgets '
        . 'the row it hangs off succeeds and makes a silent orphan',
);
foreach ($exemptions as $needle => $why) {
    ptCheck(
        false !== strpos($wrapper, $needle),
        sprintf('createTableSql() no longer exempts %s: %s.', $needle, $why)
    );
}
ptCheck(
    false !== strpos($wrapper, '|| $hasDefault'),
    'createTableSql() overwrites a default the caller passed explicitly. An '
    . 'explicit default is a decision; this should only fill in where there '
    . 'was nothing.'
);

// ---------------------------------------------------------------
// 5. Every plugin install() goes through the wrapper.
// ---------------------------------------------------------------
$plugins = $web . '/lib/plugins';
$seen = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugins));
foreach ($it as $file) {
    if (!preg_match('/manager\.class\.php$/', $file->getFilename())) {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (false === strpos($src, 'createTableSql(')
        && false === strpos($src, 'Schema::createTable(')
    ) {
        continue;
    }
    $seen++;
    $short = str_replace($web . '/', '', $file->getPathname());
    ptCheck(
        false === strpos($src, 'Schema::createTable('),
        sprintf(
            '%s calls Schema::createTable() directly, so its table is built '
            . 'with every optional column NOT NULL and no default -- and '
            . 'schema step 286 cannot repair it, because install() drops the '
            . 'table and builds it again.',
            $short
        )
    );
}
ptCheck(
    $seen >= 20,
    sprintf(
        'only %d plugin managers were scanned, so the check above passes '
        . 'vacuously',
        $seen
    )
);

// The gate is only meaningful if the scan reached the tree at all.
ptCheck(
    strlen($schemaSrc) > 10000 && strlen($managerSrc) > 10000,
    'the scan did not reach the sources and would pass vacuously'
);

if (count($failures) > 0) {
    echo 'FAIL plugin-tables-carry-column-defaults ('
        . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS plugin-tables-carry-column-defaults ($checks checks)\n";
exit(0);
