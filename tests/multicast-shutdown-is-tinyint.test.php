<?php
/**
 * Schema step 400 converts `multicastSessions`.`msShutdown` off enum('0','1').
 *
 * The column is `msAnon3` renamed, and the two branches' schema step arrays
 * share positions up to 263 and diverge from 264. A 1.5 database's
 * schemaVersion counts against dev-branch's array, so an upgrade to 1.6 treats
 * 264-277 as already applied and skips them -- including that rename. When the
 * boolean sweep at step 368 ran it looked for `msShutdown`, found no such
 * column, and moved on. SchemaReconciler's rename pass then produced the
 * column afterward, preserving the enum it had all along.
 *
 * Observed, not predicted: a real 1.5.10 database upgraded to the current
 * schema came out with `enum('0','1') NOT NULL` where the manifest says
 * `tinyint(1) NOT NULL DEFAULT 0`. Every other rename in the skipped range is
 * LONGTEXT or INTEGER, whose types no later step changes, which is why this is
 * one column rather than a sweep.
 *
 * Why an enum boolean is a bug rather than a preference: in MySQL an
 * enum('0','1') indexes from 1, so the integer 1 selects the member '0'.
 * `->set('msShutdown', 1)` therefore means FALSE if the value ever reaches the
 * server as an integer. See ADR 0028 and fogproject#1361.
 *
 * Usage: php tests/multicast-shutdown-is-tinyint.test.php
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

use FOG\Items\Schema;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('multicast-shutdown-is-tinyint');

$t = new FogChecks();
$root = dirname(__DIR__);

// -------------------------------------------------------------------------
// 1. The repair itself, driven through the REAL shared helper against a
//    server described exactly as the drifted one was.
// -------------------------------------------------------------------------

/**
 * Runs the conversion against a server whose msShutdown has $type.
 *
 * @param string $type      COLUMN_TYPE as information_schema reports it
 * @param mixed  $default   COLUMN_DEFAULT
 * @param string $nullable  IS_NULLABLE
 *
 * @return array the statements issued
 */
$convert = static function ($type, $default = '0', $nullable = 'NO') {
    $db = FogTestHarness::fakeDb();
    $db->responder = static function ($sql) use ($db, $type, $default, $nullable) {
        $db->error = false;
        if (false !== strpos($sql, 'information_schema')) {
            return [[
                'c' => 'msShutdown',
                'ty' => $type,
                'd' => $default,
                'n' => $nullable,
            ]];
        }
        return null;
    };
    $mark = count($db->log);
    Schema::enumToTinyint(['multicastSessions' => ['msShutdown']]);
    return array_slice($db->log, $mark);
};

$alters = static function (array $log) {
    return array_values(
        array_filter(
            $log,
            static function ($sql) {
                return (bool)preg_match('/^\s*(ALTER|UPDATE)\b/i', $sql);
            }
        )
    );
};

// The drifted server.
$log = $alters($convert("enum('0','1')"));
$t->check(
    'the drifted enum column is converted',
    count($log) === 3
);
$t->check(
    'and it lands on TINYINT(1) NOT NULL DEFAULT 0, as the manifest says',
    isset($log[2])
        && false !== stripos($log[2], '`multicastSessions`')
        && false !== stripos($log[2], '`msShutdown`')
        && false !== stripos($log[2], 'TINYINT(1)')
        && false !== stripos($log[2], 'NOT NULL')
        && false !== stripos($log[2], 'DEFAULT 0')
);
$t->check(
    'via VARCHAR first, so no value is reinterpreted by its enum index',
    isset($log[0]) && false !== stripos($log[0], 'VARCHAR(1)')
);

// A server that is already correct. This is the property that makes the step
// safe to ship to everybody rather than only to upgraded 1.5 servers: it must
// cost one information_schema read and nothing else.
$t->check(
    'a server already on tinyint(1) gets no ALTER at all',
    $alters($convert('tinyint(1)')) === []
);
// The same guarantee for a shape that is neither -- a hand-altered server
// must not be silently rewritten by a step aimed at one specific drift.
$t->check(
    'an unrelated column type is left alone',
    $alters($convert("enum('yes','no')")) === []
);

// -------------------------------------------------------------------------
// 2. That step 400 is what asks for it.
//    The behavioral half above exercises the helper, so it would stay green
//    if the step named the wrong table or column. This is the half that
//    would not.
// -------------------------------------------------------------------------
$src = (string)file_get_contents($root . '/packages/web/commons/schema.php');
$from = strpos($src, "\n// 400\n");
$to = strpos($src, "\n// 401\n");
$t->check(
    'step 400 is present and bounded by step 401',
    false !== $from && false !== $to && $to > $from
);
$body = false !== $from && false !== $to
    ? substr($src, $from, $to - $from)
    : '';

// Comments stripped, so the explanation above the step -- which necessarily
// names the column -- cannot be what satisfies this.
$code = '';
foreach (token_get_all('<?php ' . $body) as $tok) {
    if (is_array($tok) && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) {
        continue;
    }
    $code .= is_array($tok) ? $tok[1] : $tok;
}
$t->check(
    'step 400 converts multicastSessions.msShutdown',
    (bool)preg_match(
        '/enumToTinyint\(\s*\[\s*[\'"]multicastSessions[\'"]\s*=>\s*'
        . '\[\s*[\'"]msShutdown[\'"]\s*\]/',
        $code
    )
);
$t->check(
    'it reuses the shared helper rather than hand-rolling the ALTERs',
    false === stripos($code, 'ALTER TABLE')
);
// ...and the strip is real, or the check above passes for the wrong reason.
$t->check(
    'the comment explaining why it drifted is still there',
    false !== stripos($body, 'msAnon3')
);

$t->finish();
