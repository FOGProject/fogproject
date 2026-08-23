<?php
/**
 * The manifest's `retired` block silences a real check -- so bound it.
 *
 * .githooks/pre-commit diffs bin/schema-reference-1.5.php against
 * commons/schema-expected.php to answer "has 1.6 forgotten a structural
 * change 1.5 made?". A table 1.6 dropped ON PURPOSE looks identical to a
 * forgotten port from the outside: present there, absent here. ADR 0022
 * decision 3 retired `imagingLog`, so that warning began firing on every
 * schema commit, and a warning that always fires is one nobody reads --
 * which is the whole check going quiet, not just the one line.
 *
 * The `retired` block is how the difference is declared accounted for. It
 * is a SUPPRESSION, and the failure mode of a suppression is that it grows
 * to cover something real, so these are the bounds:
 *
 *   - a retired table must not be one schema.php still creates. Naming a
 *     live table would hide a genuinely missing column set behind the
 *     table-level skip.
 *   - a retired table must actually be absent from `tables`, or the entry
 *     is stale and describes nothing.
 *   - every entry carries a reason. Without one the next reader cannot
 *     tell a decision from an accident, which is the state this block
 *     exists to end.
 *   - the generator preserves the block. `renames` had to be preserved by
 *     hand for the same reason and the mechanism is shared; a regeneration
 *     that dropped `retired` would restore the always-firing warning with
 *     nothing to show it happened.
 *   - the diff really does honor it, run as the hook runs it.
 *
 * Needs no database: every input is a committed file.
 *
 * Usage: php tests/schema-retired-tables.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$manifestFile = $root . '/packages/web/commons/schema-expected.php';
$schemaFile = $root . '/packages/web/commons/schema.php';
$generator = $root . '/bin/schema-manifest.php';
$reference = $root . '/bin/schema-reference-1.5.php';

$t = new FogChecks();

foreach ([$manifestFile, $schemaFile, $generator, $reference] as $f) {
    if (!is_readable($f)) {
        fwrite(STDERR, "FAIL: cannot read $f\n");
        exit(1);
    }
}

$manifest = include $manifestFile;
$retired = (array)($manifest['retired'] ?? []);
$tables = array_change_key_case((array)($manifest['tables'] ?? []), CASE_LOWER);

$t->check(
    'the manifest still declares a retired block',
    count($retired) > 0
);

// schema.php is an append-only replay log, so a retired table is still
// CREATEd at the step where it was introduced -- a fresh install walks the
// whole history. What makes its absence from the manifest legitimate is the
// dropTable() that comes later, which is what this looks for. Asserting the
// CREATE was removed would be asserting the schema had been rewritten, which
// is the one thing that file must never do.
$schemaSrc = file_get_contents($schemaFile);

foreach ($retired as $i => $entry) {
    $name = (string)($entry['table'] ?? '');
    $t->check(
        "retired entry $i names a table",
        '' !== $name
    );
    if ('' === $name) {
        continue;
    }
    $t->check(
        "retired `$name` records a reason",
        '' !== trim((string)($entry['reason'] ?? ''))
    );
    $t->check(
        "retired `$name` is absent from the manifest's tables",
        !isset($tables[strtolower($name)])
    );
    $created = false !== stripos(
        $schemaSrc,
        'CREATE TABLE IF NOT EXISTS `' . $name . '`'
    ) || false !== stripos($schemaSrc, 'CREATE TABLE `' . $name . '`');
    $dropped = false !== stripos(
        $schemaSrc,
        "Schema::dropTable('" . $name . "')"
    );
    $t->check(
        "schema.php drops `$name`, so a fresh install does not keep it",
        $dropped
    );
    // The drop has to come after the last thing that builds the table, or
    // the replay ends with the table present and the manifest is simply
    // wrong about the end state.
    if ($created && $dropped) {
        $lastBuild = 0;
        foreach (['CREATE TABLE `', 'ALTER TABLE `'] as $verb) {
            $needle = $verb . $name . '`';
            $at = 0;
            while (false !== ($at = stripos($schemaSrc, $needle, $at))) {
                $lastBuild = max($lastBuild, $at);
                $at++;
            }
        }
        $t->check(
            "the drop of `$name` comes after everything that builds it",
            stripos($schemaSrc, "Schema::dropTable('" . $name . "')")
            > $lastBuild
        );
    }
}

/*
 * The generator round-trip. Reading the source rather than running it,
 * because `generate` needs a live database of the right server version --
 * see the manifest header. What must hold is that the block is both read
 * back from the existing file and written out again.
 */
$genSrc = file_get_contents($generator);
$t->check(
    'the generator reads an existing `retired` block back',
    false !== strpos($genSrc, "\$existing['retired']")
);
$t->check(
    'the generator writes the `retired` block out again',
    false !== strpos($genSrc, "'retired' => \" . render(\$retired)")
);

/*
 * And the check the hook actually runs. Exercised end to end rather than by
 * inspection: this is the assertion that the suppression works, and it is
 * one process away.
 */
$cmd = sprintf(
    'php %s diff %s %s 2>&1',
    escapeshellarg($generator),
    escapeshellarg($reference),
    escapeshellarg($manifestFile)
);
$out = [];
$rc = 0;
exec($cmd, $out, $rc);
$text = implode("\n", $out);
$t->check(
    'the 1.5 comparison passes with the retired tables accounted for',
    0 === $rc
);
foreach ($retired as $entry) {
    $name = (string)($entry['table'] ?? '');
    if ('' === $name) {
        continue;
    }
    $t->check(
        "the comparison still REPORTS `$name` as retired rather than hiding it",
        false !== stripos($text, 'RETIRED TABLE')
        && false !== stripos($text, strtolower($name))
    );
}

$t->finish();
