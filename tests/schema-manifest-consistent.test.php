<?php
/**
 * A manifest table's `create` and its `columns` must describe the same table.
 *
 * commons/schema-expected.php holds two descriptions of every table, and
 * SchemaReconciler uses them for different jobs: `create` builds a table that
 * is absent, `columns` adds columns to a table that exists. The generator
 * derives both from one `SHOW CREATE TABLE`, so they agree by construction --
 * right up until somebody edits the file by hand, which the header asks
 * nobody to do and which has happened.
 *
 * 407be1a53 added `logType` and `logText` to taskLog's `create` and not to its
 * `columns`. The consequences were entirely silent:
 *
 *  - plan() pass 3 iterates `columns` and skips a table whose `columns` is
 *    empty, so the reconciler could never add those two to a taskLog that
 *    already existed -- and an existing table is the only kind it repairs.
 *  - the INSERT then failed with 1054 Unknown column, on any sql_mode.
 *  - PDODB::$throwOnQueryError is false, so query() recorded the error and
 *    returned normally; FOGController::save() caught its own insertId=0
 *    exception and returned false; TaskError::_logRow() ignores that return.
 *    So nothing threw, TaskError's catch never ran, the endpoint answered its
 *    usual 200, the notification fired, and the report was simply not there.
 *  - OpenAPI::_entitySchema() reads column types from the same `columns`
 *    block, so the published API document described both fields as
 *    "No type information available for this column."
 *
 * Nothing compared the two blocks, so nothing noticed. tests/schema-executes
 * executes the `create` strings into an empty database, which is exactly the
 * case where `columns` is never consulted.
 *
 * Checks both directions and the definition text, because all three are ways
 * for the blocks to disagree and only one of them was the bug we had:
 *
 *   a column in `create` and not in `columns`  -> reconciler cannot add it
 *   a column in `columns` and not in `create`  -> ALTER for a column a fresh
 *                                                 install will never have
 *   the same column defined differently        -> a fresh install and a
 *                                                 repaired one diverge
 *
 * Needs no database: both blocks are in the committed file, which is the
 * thing that drifted.
 *
 * Usage: php tests/schema-manifest-consistent.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$manifestFile = dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
if (!is_readable($manifestFile)) {
    fwrite(STDERR, "FAIL: cannot read $manifestFile\n");
    exit(1);
}
$manifest = include $manifestFile;

$t = new FogChecks();

/**
 * The column definitions a single-line CREATE TABLE declares.
 *
 * Hand-rolled rather than reusing the generator's parser: that one reads the
 * multi-line output of SHOW CREATE TABLE, one column per line, and the
 * committed `create` has had its whitespace collapsed. Splitting on top-level
 * commas is what that collapse costs.
 *
 * @param string $create The CREATE TABLE statement.
 *
 * @return array Column name => definition, in declaration order.
 */
function manifestCreateColumns($create)
{
    $open = strpos($create, '(');
    if (false === $open) {
        return [];
    }
    // Walk to the paren that closes the column list, skipping quoted text so
    // a default like DEFAULT '(' cannot end it early.
    $depth = 0;
    $end = null;
    $len = strlen($create);
    for ($i = $open; $i < $len; $i++) {
        $ch = $create[$i];
        if ("'" === $ch) {
            for ($i++; $i < $len; $i++) {
                if ('\\' === $create[$i]) {
                    $i++;
                    continue;
                }
                if ("'" === $create[$i]) {
                    break;
                }
            }
            continue;
        }
        if ('(' === $ch) {
            $depth++;
        } elseif (')' === $ch) {
            $depth--;
            if (0 === $depth) {
                $end = $i;
                break;
            }
        }
    }
    if (null === $end) {
        return [];
    }
    $body = substr($create, $open + 1, $end - $open - 1);

    $parts = [];
    $buf = '';
    $depth = 0;
    $len = strlen($body);
    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ("'" === $ch) {
            $buf .= $ch;
            for ($i++; $i < $len; $i++) {
                $buf .= $body[$i];
                if ('\\' === $body[$i]) {
                    $i++;
                    $buf .= $body[$i];
                    continue;
                }
                if ("'" === $body[$i]) {
                    break;
                }
            }
            continue;
        }
        if ('(' === $ch) {
            $depth++;
        }
        if (')' === $ch) {
            $depth--;
        }
        if (',' === $ch && 0 === $depth) {
            $parts[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if ('' !== trim($buf)) {
        $parts[] = trim($buf);
    }

    $columns = [];
    foreach ($parts as $part) {
        // Key and constraint clauses sit in the same comma list.
        if (preg_match(
            '/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|FULLTEXT|SPATIAL|INDEX|CHECK)\b/i',
            $part
        )) {
            continue;
        }
        if (!preg_match('/^`(\w+)`\s+(.+)$/s', $part, $m)) {
            continue;
        }
        // The generator drops AUTO_INCREMENT from a column definition: it is
        // only valid on a key column and ADD COLUMN has no key yet. Dropped
        // here too, or every table's id column would read as a difference.
        $columns[$m[1]] = trim(preg_replace('/\s*AUTO_INCREMENT\b/i', '', $m[2]));
    }
    return $columns;
}

$tables = $manifest['tables'] ?? [];
$t->check('the manifest ships a tables block', count($tables) > 0);

foreach ($tables as $table => $spec) {
    $fromCreate = manifestCreateColumns($spec['create'] ?? '');
    $declared = (array)($spec['columns'] ?? []);

    // Both halves have to be non-empty for the comparison to mean anything.
    // A parser that quietly returned nothing would otherwise make every
    // table below agree with itself.
    if (!$t->check("$table: `create` parses into columns", count($fromCreate) > 0)) {
        continue;
    }
    $t->check("$table: `columns` is not empty", count($declared) > 0);

    foreach (array_diff(array_keys($fromCreate), array_keys($declared)) as $c) {
        $t->check(
            "$table.$c is in `create` but missing from `columns`"
            . ' -- the reconciler can never add it',
            false
        );
    }
    foreach (array_diff(array_keys($declared), array_keys($fromCreate)) as $c) {
        $t->check(
            "$table.$c is in `columns` but missing from `create`"
            . ' -- a fresh install would never have it',
            false
        );
    }
    foreach (array_intersect_key($fromCreate, $declared) as $c => $def) {
        $t->check(
            "$table.$c is defined the same way in both blocks"
            . " (create: '$def', columns: '{$declared[$c]}')",
            $def === $declared[$c]
        );
    }
}

/*
 * No column default was captured from an install-specific constant.
 *
 * GH-1249. The manifest is generated by snapshotting a live database, so any
 * DEFAULT that schema.php builds by interpolating a PHP constant is captured
 * as whatever that ONE machine's value happened to be, and then shipped to
 * everyone. `ngmInterface` went out defaulting to 'enp58s0u2u4' -- the network
 * interface of the box that generated the file -- because step 26 declares it
 * DEFAULT '<STORAGE_INTERFACE>', which the installer writes per install.
 *
 * SchemaReconciler uses the manifest's text verbatim, both for the `create` of
 * a missing table and for ADD COLUMN of a missing column, so a server reaching
 * either path gets a default naming a NIC it does not have.
 *
 * Checked against schema.php rather than against a list, so a future step that
 * interpolates a constant into a DEFAULT is covered without anyone adding it
 * here. The rule is only that such a default must not be baked in: an empty
 * default is fine, a literal captured from someone's machine is not.
 */
$schemaSrc = file_get_contents(
    dirname(__DIR__) . '/packages/web/commons/schema.php'
);

/*
 * Statements are built by concatenation across lines, so the constant sits in
 * a `. CONSTANT` fragment after the DEFAULT that opens a quote. Match the
 * whole ALTER up to that point and read the table and column back out of it.
 */
$fromConstant = [];
preg_match_all(
    '#ALTER\s+TABLE\s+`(\w+)`(.*?)DEFAULT\s+\'"\s*\.\s*([A-Z][A-Z0-9_]+)#s',
    $schemaSrc,
    $matches,
    PREG_SET_ORDER
);
foreach ($matches as $match) {
    // The column is the last backticked name before the DEFAULT.
    if (!preg_match_all('#`(\w+)`#', $match[2], $names) || !count($names[1])) {
        continue;
    }
    $fromConstant[$match[1] . '.' . end($names[1])] = $match[3];
}

// A scan that matched nothing would pass vacuously, and this one has exactly
// one known subject today.
$t->check(
    'schema.php has at least one DEFAULT built from a constant to check'
    . ' (found ' . count($fromConstant) . ')',
    count($fromConstant) > 0
);

foreach ($fromConstant as $ref => $constant) {
    list($table, $column) = explode('.', $ref);
    $definition = $manifest['tables'][$table]['columns'][$column] ?? null;
    if (null === $definition) {
        continue;
    }
    if (!preg_match('#\bDEFAULT\s+\'(.*)\'#i', $definition, $default)) {
        continue;
    }
    $t->check(
        "$ref takes its DEFAULT from $constant, so the manifest must not bake"
        . " one machine's value in (it says '{$default[1]}')",
        '' === $default[1]
    );
}

$t->finish();
