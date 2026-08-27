<?php
/**
 * Every column a model can leave empty has a type save() knows how to empty.
 *
 * GH-1245. `FOGController::save()` used to write '' for every unset optional
 * field whose key does not end in "id". '' is a value only a string column can
 * hold; everywhere else the server either refused it under a strict sql_mode
 * or coerced it without one. FOG only ever saw the second, because
 * `PDODB::_connect()` issued `SET SESSION sql_mode=''` on every connection --
 * for nine years, since 13661edb in May 2016.
 *
 * `emptyValueFor()` now writes the value the server was coercing to anyway:
 *
 *   date/time  ->  NULL, or omitted when the column is NOT NULL so its
 *                  DEFAULT applies
 *   integer    ->  0
 *   enum/set   ->  the first member
 *   otherwise  ->  '', which is what a string column wanted all along
 *
 * That list is exhaustive against today's schema, and this test is what keeps
 * it exhaustive. A column type outside those families -- a decimal, a json, a
 * binary -- would fall through to '' and be refused by the server, which is
 * the failure this whole issue is about and is invisible until someone runs a
 * server that checks.
 *
 * The reachable set is computed the same way GH-1245's survey was: a field is
 * reachable when it is not the primary key, not required, not auto-filled by
 * save()'s own switch, and not a foreign key (a key ending in "id" that the
 * model has not declared a string), because those take a different arm.
 *
 * Column types come from commons/schema-expected.php, generated from a real
 * server, so a column that changes type under a model fails here rather than
 * in the field.
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
$failures = [];
$checks = 0;

/**
 * Source with comments removed, so a commented-out entry cannot satisfy a
 * check and a commented-out literal cannot fail one.
 *
 * @param string $src the file's source
 *
 * @return string
 */
function evStripComments($src)
{
    $clean = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

/**
 * The bracketed body of an array literal assigned to a property.
 *
 * @param string $clean comment-stripped source
 * @param string $prop  the property name, without the dollar
 *
 * @return string the body including its brackets, or '' when not declared
 */
function evArrayBody($clean, $prop)
{
    $at = strpos($clean, '$' . $prop);
    if (false === $at) {
        return '';
    }
    $open = strpos($clean, '[', $at);
    if (false === $open) {
        return '';
    }
    $depth = 0;
    $len = strlen($clean);
    for ($i = $open; $i < $len; $i++) {
        if ('[' === $clean[$i]) {
            $depth++;
        } elseif (']' === $clean[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($clean, $open, $i - $open + 1);
            }
        }
    }
    return '';
}

$manifest = require $web . '/commons/schema-expected.php';
$tables = $manifest['tables'] ?? [];
if (count($tables) < 1) {
    fwrite(STDERR, "FAIL: schema-expected.php produced no tables\n");
    exit(1);
}

// The families emptyValueFor() has a branch for. Anything else falls through
// to '' and is only legal on a string column.
$handled = '#^(datetime|timestamp|date|(tiny|small|medium|big)?int|enum|set)\b#i';
// Types where '' is a value the column can genuinely hold.
$stringy = '#^((var)?char|(tiny|medium|long)?(text|blob)|varbinary|binary)\b#i';

$reachable = 0;
foreach (glob($web . '/src/*/*.php') as $file) {
    $clean = evStripComments(file_get_contents($file));
    if (!preg_match('#\$databaseTable\s*=\s*[\'"](\w+)[\'"]#', $clean, $m)) {
        continue;
    }
    $table = $m[1];
    if (!isset($tables[$table]['columns'])) {
        continue;
    }
    $columns = $tables[$table]['columns'];

    preg_match_all(
        '#[\'"](\w+)[\'"]\s*=>\s*[\'"](\w+)[\'"]#',
        evArrayBody($clean, 'databaseFields'),
        $fm
    );
    if (!count($fm[1])) {
        continue;
    }
    preg_match_all('#[\'"](\w+)[\'"]#', evArrayBody($clean, 'databaseFieldsRequired'), $rm);
    $required = array_map('strtolower', $rm[1]);
    preg_match_all('#[\'"](\w+)[\'"]#', evArrayBody($clean, 'databaseFieldsNotInt'), $nm);
    $notInt = array_map('strtolower', $nm[1]);

    foreach (array_combine($fm[1], $fm[2]) as $key => $column) {
        if (!isset($columns[$column])) {
            continue;
        }
        $low = strtolower($key);
        if ('id' === $low
            || in_array($low, $required, true)
            || in_array($low, ['createdtime', 'createdby'], true)
        ) {
            continue;
        }
        // Foreign keys take the arm above, which writes 0 or omits.
        if ('id' === substr($low, -2) && !in_array($low, $notInt, true)) {
            continue;
        }
        $reachable++;
        $checks++;
        $type = $columns[$column];
        if (preg_match($handled, $type) || preg_match($stringy, $type)) {
            continue;
        }
        $failures[] = sprintf(
            '%s -> %s.%s is "%s", which emptyValueFor() has no branch for; '
            . 'it would write \'\' and the server would refuse it',
            basename($file),
            $table,
            $column,
            trim($type)
        );
    }
}

// A scan that matched nothing would pass vacuously. GH-1245's survey counted
// 277; well under that means the parse stopped working.
$checks++;
if ($reachable < 200) {
    $failures[] = sprintf(
        'only %d reachable fields found; the scan is not reaching the models '
        . 'and would pass vacuously',
        $reachable
    );
}

// ---------------------------------------------------------------
// A table the manifest does not describe is asked about, not guessed at.
//
// schema-expected.php describes core's tables and nothing else, so every
// plugin column used to come back '' -- which is the exact bug this file
// exists to prevent, reintroduced one layer down. Invisible while PDODB
// cleared sql_mode; with the clear gone the server refuses the write, so
// saving an LDAP server without a port is error 1366. On the maintainer's own
// install that is 18 tables, 16 enum/set and 44 integer columns.
// ---------------------------------------------------------------
//
// The seam moved to FOGBase when insertBatch() needed it too: FOGController
// and FOGManagerController are siblings, so a helper only one of them can
// reach is a helper the other write path silently does without. That is not
// hypothetical -- it is why a strict server rejected saving FOG settings and
// tasking a group's snapins while saving a host was fine. Everything below is
// unchanged in substance; only the file it is read from moved.
$controllerSrc = evStripComments(
    file_get_contents($web . '/src/Base/FOGBase.php')
);
$squashed = preg_replace('#\s+#', '', $controllerSrc);

$checks++;
if (strpos($squashed, 'if(!isset(self::$columnTypes[$t])){self::_loadPluginColumnTypes($t);}') === false) {
    $failures[] = 'FOGBase::columnType() no longer falls back to the '
        . 'server catalog for a table the manifest does not describe, so '
        . "every plugin column answers '' again -- GH-1245's own bug, one "
        . 'layer down';
}

$checks++;
if (strpos($squashed, 'information_schema') === false) {
    $failures[] = 'FOGBase no longer reads information_schema at all';
}

// The rebuilt definition has to carry NOT NULL, or columnIsNullable() -- which
// greps the manifest's definition strings for it -- reads every plugin column
// as nullable and binds a NULL the server refuses.
$checks++;
// The needle is whitespace-squashed, so ' NOT NULL' reads as 'NOTNULL'.
if (strpos($squashed, "==='NO'?'NOTNULL':''") === false) {
    $failures[] = '_loadPluginColumnTypes() no longer appends NOT NULL to the '
        . 'definition it builds, so columnIsNullable() reads every plugin '
        . 'column as nullable';
}

// Cached before the query, so a table that does not exist is asked about once
// rather than on every field of every save.
$checks++;
$at = strpos($squashed, 'staticfunction_loadPluginColumnTypes($table){');
$body = false === $at ? '' : substr($squashed, $at, 400);
$seed = false === $at ? false : strpos($body, 'self::$columnTypes[$table]=[];');
$query = false === $at ? false : strpos($body, 'self::$DB->query(');
if (false === $seed || false === $query || $seed > $query) {
    $failures[] = '_loadPluginColumnTypes() no longer seeds its cache entry '
        . 'before querying, so a table that does not exist is looked up again '
        . 'for every field of every save';
}

// ---------------------------------------------------------------
// The clear does not come back.
// ---------------------------------------------------------------
$checks++;
$pdodb = evStripComments(file_get_contents($web . '/src/Db/PDODB.php'));
if (preg_match('#SET\s+SESSION\s+sql_mode#i', $pdodb)) {
    $failures[] = 'PDODB sets a session sql_mode again. FOG ran for nine '
        . 'years with the server\'s checks off; every value it stored was '
        . 'unvalidated. See GH-1245 and the comment in _connect().';
}

// ---------------------------------------------------------------
// Logging cannot re-enter itself.
// ---------------------------------------------------------------
//
// PDODB::sqlerror() calls FOGBase::debug() on a failed fetch, and _writeLog()
// reads a FOG_LOG_* setting, which is a query. Without the guard one failed
// statement recursed until the PHP worker died on memory, reporting nothing.
$checks++;
$fogbase = evStripComments(file_get_contents($web . '/src/Base/FOGBase.php'));
if (!preg_match('#static\s+\$inWriteLog\s*=\s*false\s*;#', $fogbase)
    || !preg_match('#if\s*\(\s*\$inWriteLog\s*\)\s*\{\s*return\s*;#', $fogbase)
) {
    $failures[] = 'FOGBase::_writeLog() lost its re-entrancy guard; a failed '
        . 'query recurses through sqlerror() -> debug() -> getSetting() -> '
        . 'query() until the worker dies. See GH-1245.';
}

if (count($failures) > 0) {
    echo "FAIL empty-values-match-column-types (" . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS empty-values-match-column-types "
    . "($checks checks, $reachable reachable fields)\n";
exit(0);
