<?php
/**
 * The stale `site` plugins row is actually removed by an upgrade.
 *
 * Schema step 334 was written to do this and CANNOT, on the only path it
 * exists for. Its gate reads `plugins`.`pLocation`, which is a RENAME of
 * `pAnon3` -- a column FOG 1.5 declares and never writes. Every row carried
 * across from 1.5 therefore has the empty string, the gate's first arm
 * matches, and the DELETE is unreachable. Verified against a real 1.5.10
 * database, whose one `plugins` row has `pAnon3` of length 0. GH-1543.
 *
 * A step runs once, so 334 could not be corrected in place and step 399
 * replaces it. This gates 399's DECISION rather than its presence: a grep for
 * the step would pass with the condition inverted, which is the shape of the
 * original bug.
 *
 * The step is a closure, and closures are the one thing
 * tests/schema-executes.test.php deliberately does not run. So it is executed
 * here against a scripted stand-in for `self::$DB` -- the collector already
 * provides one -- which answers the two information_schema probes and records
 * whether the DELETE was issued. No database server is needed and none of the
 * decision is re-implemented: the real closure runs.
 *
 * Usage: php tests/site-plugin-row-retired.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-schema-collector.php';
require_once __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$schemaFile = $root . '/packages/web/commons/schema.php';

$fogSchema = 0;
if (preg_match(
    "/define\('FOG_SCHEMA',\s*(\d+)\)/",
    (string)file_get_contents($root . '/packages/web/src/Base/System.php'),
    $m
)) {
    $fogSchema = (int)$m[1];
}

$steps = fogCollectSchemaSteps($schemaFile, 'fogtest', $fogSchema);
$t = new FogChecks();

// The step this test is about. Pinned, so that adding step 402 cannot quietly
// point these assertions somewhere else.
define('SITE_RETIRE_STEP', 399);

/**
 * A `self::$DB` that answers the table probes and records what was written.
 */
class SiteRetireDB extends SchemaStubDB
{
    /**
     * Table names the fake server holds, lowercased.
     *
     * @var array
     */
    public $tables = [];

    /**
     * Every statement issued, in order.
     *
     * @var array
     */
    public $log = [];

    /**
     * The answer to the last COUNT probe.
     *
     * @var int
     */
    private $_answer = 0;

    public function query($query = null, ...$rest)
    {
        $this->log[] = (string)$query;
        // The step probes information_schema with the table name bound, so
        // the name is in the params rather than in the SQL.
        if (false !== strpos((string)$query, 'information_schema')) {
            $params = [];
            foreach ($rest as $arg) {
                if (is_array($arg) && count($arg)) {
                    $params = $arg;
                }
            }
            $want = strtolower((string)reset($params));
            $this->_answer = in_array($want, $this->tables, true) ? 1 : 0;
        }
        return $this;
    }

    public function fetch($what = null, ...$rest)
    {
        return $this;
    }

    public function get($key = null)
    {
        return ['n' => $this->_answer];
    }
}

/**
 * Runs the last step against a fake server holding $tables.
 *
 * @param array $tables lowercased table names the server has
 *
 * @return array statements issued
 */
function runRetireStep(array $tables)
{
    global $steps;
    $db = new SiteRetireDB();
    $db->tables = $tables;
    $prev = SchemaCollector::$DB;
    SchemaCollector::$DB = $db;
    // Indexed by the step's OWN number, not end($steps). Keying on the last
    // step means every future step silently retargets this test at itself --
    // which is exactly what steps 400 and 401 did, turning it red on code it
    // does not test.
    $step = $steps[SITE_RETIRE_STEP - 1];
    foreach ((array)$step as $update) {
        if (is_callable($update)) {
            $update();
        }
    }
    SchemaCollector::$DB = $prev;
    return $db->log;
}


/**
 * Whether any statement deletes the site plugins row.
 *
 * @param array $log statements issued
 *
 * @return bool
 */
function deleted(array $log)
{
    foreach ($log as $sql) {
        if (false !== stripos($sql, 'DELETE FROM `plugins`')) {
            return true;
        }
    }
    return false;
}

// The step under test has to be the one this test THINKS it is, or every
// assertion below is about something else entirely.
//
// Indexed by SITE_RETIRE_STEP, not end($steps), for the reason runRetireStep()
// already gives: keying on the last step retargets this test at whatever
// landed most recently. That was fixed there and missed here, so this
// assertion went on passing by luck -- steps 400, 401 and 402 all happen to
// carry a closure. Step 405 does not, and it turned this red on code it does
// not test.
$under = $steps[SITE_RETIRE_STEP - 1];
$hasClosure = false;
foreach ((array)$under as $u) {
    $hasClosure = $hasClosure || is_callable($u);
}
$t->check('step ' . SITE_RETIRE_STEP . ' is a closure', $hasClosure);
$t->check(
    'FOG_SCHEMA matches the step count',
    $fogSchema === count($steps)
);

// --- the migration ran: sites exists, the plugin's table is gone -----------
$t->check(
    'the stale row is deleted once sites exists and site is gone',
    deleted(runRetireStep(['sites', 'plugins', 'hosts']))
);

// --- step 332 kept the plugin tables because the counts disagreed ---------
// The row must SURVIVE: an admin whose data did not migrate needs the plugin
// listed. This is the arm that stops the fix being "delete it always".
$t->check(
    'the row survives while the plugin table is still present',
    !deleted(runRetireStep(['sites', 'site', 'plugins', 'hosts']))
);

// --- the migration never ran at all ---------------------------------------
$t->check(
    'the row survives when core has no sites table',
    !deleted(runRetireStep(['site', 'plugins', 'hosts']))
);

// --- and it does not depend on pLocation ----------------------------------
// The whole defect was a gate keyed on a column 1.5 never writes. No CODE in
// the replacement may read it, or the fix reproduces the bug.
//
// Comments are stripped first, deliberately: the step's own comment quotes
// the broken gate in order to explain it, and a check that could not tell
// prose from code would force the explanation out of the file -- which is the
// one part of it a future reader most needs.
$src = (string)file_get_contents($schemaFile);
$tail = substr(
    $src,
    (int)strpos($src, "\n// " . SITE_RETIRE_STEP . "\n"),
    (int)strpos($src, "\n// " . (SITE_RETIRE_STEP + 1) . "\n")
        - (int)strpos($src, "\n// " . SITE_RETIRE_STEP . "\n")
);
$code = '';
foreach (token_get_all('<?php ' . $tail) as $tok) {
    if (is_array($tok) && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) {
        continue;
    }
    $code .= is_array($tok) ? $tok[1] : $tok;
}
$t->check(
    'the replacement step does not read pLocation',
    false === stripos($code, 'pLocation')
);
// ...and the strip is real, or the check above passes for the wrong reason.
$t->check(
    'the comment that explains the defect is still there',
    false !== stripos($tail, 'pLocation')
);

$t->finish();
