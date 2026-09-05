<?php
/**
 * FOG_SCHEMA must not sit below the last step in commons/schema.php.
 *
 * The schema updater has two gates. The coarse one is
 * `mySchema < FOG_SCHEMA` (DatabaseManager::init(), FOGBase::
 * schemaNeedsDeploy()); it decides whether the admin -- or the installer's
 * token bootstrap -- is sent to the updater page at all. The precise one is
 * `count($this->schema) > mySchema` inside SchemaUpdaterPage::update(); it
 * decides which elements actually run.
 *
 * Leave the coarse gate behind and the precise one never gets to speak. The
 * server reports itself up to date, the updater is never opened, and the
 * appended step applies to nobody. Nothing errors and nothing logs -- the
 * only symptom is a table or a row that is missing on every install in the
 * world, which is a thing you find out about months later from a bug report.
 *
 * This is not a hypothetical failure. 18edea94f appended the element
 * labeled 330 (task type 26) and left FOG_SCHEMA at 329, so that step
 * reached no install until the bump alongside this test. The comment on the
 * constant at the time said it had "drifted well above" the element count
 * and that nothing required the two to agree, which is exactly the kind of
 * thing a contributor reads as "no bump needed".
 *
 * WHAT IS COMPARED, and why it is the label rather than a real count.
 * Counting the array needs a database: commons/schema.php calls
 * self::$DB->query() at file scope. So the test compares FOG_SCHEMA against
 * the highest `// N` comment in that file, which the file has maintained
 * beside every element for its whole history -- 18edea94f wrote its `// 330`
 * correctly and only missed the constant, which is the shape of mistake this
 * catches.
 *
 * Those labels are NOT array indexes. Index 79 is written by a foreach over
 * $keySequences that appends 35 elements from a single line of source, so
 * the labels only line up as COUNTS: the element under `// N` is the Nth,
 * sitting at index N-1. Verified against a live 1.6 database at vValue 329,
 * where the element under `// 329` was applied and the one under `// 330`
 * was not.
 *
 * Deliberately an inequality, not an equality. The constant is documented as
 * a coarse gate and is allowed to run ahead of the count; only falling
 * behind breaks anything.
 *
 * The label would be a leaky proxy on its own, and it leaks in BOTH
 * directions, so there are two structural checks below rather than one:
 *
 *   - An element appended with no label does not raise the highest label, so
 *     the gate would look covered while the element stranded above it.
 *   - A LABEL WITH NO ELEMENT raises the count without adding a step, which
 *     puts FOG_SCHEMA permanently above the real array length. That is worse
 *     than stranding a step: `mySchema < FOG_SCHEMA` can then never be
 *     satisfied, so the updater has nothing left to apply and every page on
 *     the server redirects to it forever. It is trivially easy to do -- write
 *     the label, append the statements, and forget the `];` that closes the
 *     step before it, and the new statements silently join the PREVIOUS step
 *     while the label counts as a new one. That is exactly what happened
 *     with `// 347` on 2026-08-21, and it broke three lab servers.
 *
 * Together they close it: you cannot append without labeling, and you cannot
 * label without appending.
 *
 * Counting the array for real was rejected here, on the grounds that
 * commons/schema.php also wants ~35 config constants and a couple of core
 * classes and that any of those is a thing an unrelated schema commit could
 * add -- a guard failing for reasons unconnected to what it guards is a guard
 * people learn to ignore. That objection has since been ANSWERED rather than
 * overruled: tests/lib/fog-schema-collector.php DISCOVERS both the constants
 * and the classes instead of listing them, so neither can be outgrown, and
 * tests/schema-upgrade-replay.test.php uses it to compare FOG_SCHEMA against
 * the real element count.
 *
 * This test stays as it is, because the two fail on different things. A real
 * count is label-independent and catches shapes the text cannot see; these
 * checks cover the LABEL hygiene a count cannot -- an unlabeled append, or a
 * label with no append -- and they are what keeps the file's own numbering
 * meaning what it says. Neither subsumes the other.
 *
 * Usage: php tests/schema-gate.test.php
 * Exit status 0 = pass, 1 = fail.
 */

/**
 * Labels below this name appends the text cannot see. See the check below.
 */
const HISTORICAL_SHAPES = 80;

$root = dirname(__DIR__) . '/packages/web';
$schemaFile = $root . '/commons/schema.php';
$systemFile = $root . '/src/Base/System.php';

foreach ([$schemaFile, $systemFile] as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "FAIL: cannot read $path\n");
        exit(1);
    }
}

$schemaSrc = file_get_contents($schemaFile);

/*
 * Highest step label. Anchored to the start of the line so a number
 * mentioned inside a step's own explanatory comment -- which is indented --
 * cannot be mistaken for a label. Trailing prose is allowed: the file writes
 * both `// 320` and `// 278 is #268 in 1.5.8`.
 */
$labels = [];
preg_match_all(
    '/^\/\/ (\d+)(?:\D.*)?$/m',
    $schemaSrc,
    $labels
);
if (!count($labels[1])) {
    fwrite(STDERR, "FAIL: found no `// N` step labels in $schemaFile\n");
    exit(1);
}
$highest = max(array_map('intval', $labels[1]));

/*
 * No unlabeled appends. Every top-level `$this->schema[] =` must have a
 * `// N` line somewhere between it and the append before it -- true of all
 * 296 of them today, and the thing that lets the highest label stand in for
 * the element count.
 *
 * Deliberately "somewhere between" rather than "on the line directly above".
 * Thirteen steps read a column out of information_schema first and branch on
 * it (`$this->schema[] = count($column ?: []) ? [] : [...]`), so the label,
 * the lookup and the append are three separate statements. Requiring
 * adjacency would fail all thirteen and teach the next person that this test
 * is noise.
 *
 * Only column-zero appends are checked. The one indented append is inside
 * the foreach over $keySequences, which writes 35 elements (indexes 45-79)
 * from a single line of source; it is covered by the `// 45 - 79 setup`
 * label above the loop and cannot be counted from the text at all, which is
 * the specific reason the labels rather than a static count are authoritative
 * here.
 */
$lines = preg_split('/\r?\n/', $schemaSrc);
$unlabeled = [];
$seenLabel = false;
foreach ($lines as $i => $line) {
    if (preg_match('/^\/\/ \d+(?:\D.*)?$/', $line)) {
        $seenLabel = true;
        continue;
    }
    if (!preg_match('/^\$this->schema\[\] *=/', $line)) {
        continue;
    }
    if (!$seenLabel) {
        // Reported 1-indexed, to match what an editor shows.
        $unlabeled[] = $i + 1;
    }
    $seenLabel = false;
}

/*
 * No labels without appends. Every `// N` above HISTORICAL_SHAPES must be
 * followed by a top-level `$this->schema[] =` before the next label.
 *
 * Bounded to the modern half of the file because three labels below it name
 * appends the text cannot see, and all three are shapes that have not been
 * used since index 80 and will not be again: `// 29`'s append is inside an
 * `if`, `// 45 - 79 setup`'s is inside a foreach that writes 35 elements
 * from one line, and `// 79` assigns by index rather than appending. Failing
 * those three would teach the next person that this test is noise, which is
 * how a guard stops guarding.
 */
$pendingLabel = null;
$pendingLine = 0;
$emptyLabels = [];
foreach ($lines as $i => $line) {
    if (preg_match('/^\/\/ (\d+)(?:\D.*)?$/', $line, $m)) {
        if (null !== $pendingLabel
            && (int)$pendingLabel >= HISTORICAL_SHAPES
        ) {
            $emptyLabels[] = $pendingLabel . ' (line ' . ($pendingLine + 1) . ')';
        }
        $pendingLabel = $m[1];
        $pendingLine = $i;
        continue;
    }
    if (preg_match('/^\$this->schema\[\] *=/', $line)) {
        $pendingLabel = null;
    }
}
if (null !== $pendingLabel && (int)$pendingLabel >= HISTORICAL_SHAPES) {
    $emptyLabels[] = $pendingLabel . ' (line ' . ($pendingLine + 1) . ')';
}

if (count($emptyLabels)) {
    fwrite(
        STDERR,
        'FAIL: ' . count($emptyLabels) . " step label(s) in commons/schema.php\n"
        . "  are followed by no `\$this->schema[] =` append: "
        . implode(', ', $emptyLabels) . ".\n"
        . "  The label raises the step count that FOG_SCHEMA is checked\n"
        . "  against, but no element exists, so FOG_SCHEMA ends up ABOVE\n"
        . "  count(\$this->schema). `mySchema < FOG_SCHEMA` can then never\n"
        . "  be satisfied: the updater has nothing left to apply and every\n"
        . "  page on the server redirects to it, permanently.\n"
        . "  Two causes. A missing `];` closing the step before, so the\n"
        . "  new statements silently join the previous element while the\n"
        . "  label counts as a new one -- or a line INSIDE this step's own\n"
        . "  comment that begins `// ` followed by a digit (a version like\n"
        . "  `// 1.5, ...` at the start of a wrapped line). That is read as\n"
        . "  the next label and steals this one, so reflow the comment.\n"
        . "  the new statements silently join the previous element while\n"
        . "  the label counts as a new one.\n"
    );
    exit(1);
}

if (count($unlabeled)) {
    fwrite(
        STDERR,
        'FAIL: ' . count($unlabeled) . " append(s) in commons/schema.php\n"
        . "  carry no `// N` step label, at line(s) "
        . implode(', ', $unlabeled) . ".\n"
        . "  An unlabeled element does not raise the highest label, so the\n"
        . "  FOG_SCHEMA check below would still pass while that element sat\n"
        . "  above the gate and applied to nobody. Put the step's number on\n"
        . "  a line of its own above the append, as every other step does,\n"
        . "  and raise FOG_SCHEMA to match.\n"
    );
    exit(1);
}

$const = [];
if (!preg_match(
    "/define\(\s*'FOG_SCHEMA'\s*,\s*(\d+)\s*\)/",
    file_get_contents($systemFile),
    $const
)) {
    fwrite(STDERR, "FAIL: no FOG_SCHEMA definition in $systemFile\n");
    exit(1);
}
$schema = (int)$const[1];

/*
 * Compared for EQUALITY, not `<`. Both directions strand a server and the
 * docblock above says so, but this check was one-directional and only the
 * low side was enforced -- so the worse half went unguarded and then shipped.
 *
 * #1338 raised FOG_SCHEMA 360 -> 367 while appending its seven statements to
 * the previous step's array instead of writing seven `$this->schema[] = [...]`
 * of their own. No new label, no new append, nothing for either check above to
 * catch, and `$schema < $highest` is false when $schema is HIGHER. Every check
 * in CI passed, a fresh install was correct because the statements still ran
 * inside that last step, and every EXISTING server was left permanently on
 * ?node=schema: count($this->schema) == mySchema, so the updater reported
 * nothing to do and never advanced the version past 360, while
 * DatabaseManager::establish() went on redirecting every request to it.
 *
 * FOG_SCHEMA too HIGH is the unrecoverable direction -- a server cannot leave
 * the schema page to fix itself -- so it is worth the stricter comparison even
 * though it means a deliberate skip in the numbering is now a failure. There
 * has never been one.
 */
/*
 * A step label that is INDENTED is a step nested inside another step.
 *
 * The label scan above is anchored to column zero, which is what stops a
 * number inside a step's own prose being read as a label. The cost is a blind
 * spot with no symptom: append your closures inside the previous step's array
 * and label them `    // 369`, and every check here still passes. The highest
 * column-zero label has not moved, so FOG_SCHEMA already matches it; there is
 * no unlabeled append, because there is no append at all.
 *
 * What you get is a migration that runs on a FRESH install -- the containing
 * step still executes, so CI is green on every engine -- and never runs on an
 * UPGRADE, because that step's number is already stored and it is never
 * replayed. The columns exist everywhere the tests look and on no real server.
 *
 * That is exactly what happened to steps 369/370 (hostArch/imageArch): both
 * closures went inside step 368, CI passed on MariaDB 10.5, 11.8 and MySQL
 * 8.0, and the live upgrade silently did nothing.
 *
 * Only numbers ABOVE the highest real label are flagged. Steps routinely cite
 * earlier ones in prose ("same as 336/338/341"), and those are legitimate.
 */
$nested = [];
preg_match_all(
    '/^[ \t]+\/\/ (\d+)\s*[:.]/m',
    $schemaSrc,
    $nestedMatches,
    PREG_SET_ORDER
);
foreach ($nestedMatches as $m) {
    if ((int)$m[1] > $highest) {
        $nested[] = (int)$m[1];
    }
}
if (count($nested)) {
    fwrite(
        STDERR,
        "FAIL: indented step label(s) above the highest real step ("
        . $highest . "): " . implode(', ', $nested) . "\n"
        . "  An indented `// N` is a step appended INSIDE another step's\n"
        . "  array. It runs on a fresh install and never on an upgrade, and\n"
        . "  nothing else in this file's checks can see it.\n"
        . "  Move it to a column-zero `// N` label followed by its own\n"
        . "  `\$this->schema[] = [...]`, and bump FOG_SCHEMA to match.\n"
    );
    exit(1);
}

if ($schema !== $highest) {
    $why = $schema < $highest
        ? "  Every step after $schema applies to nobody: the coarse gate\n"
            . "  (mySchema < FOG_SCHEMA) never sends anyone to the schema\n"
            . "  updater, so the updater's own count check never runs. There\n"
            . "  is no error and no log line -- the step is never applied.\n"
        : "  FOG_SCHEMA is ABOVE the last step, so no server can ever reach\n"
            . "  it: the updater has nothing to apply, never advances the\n"
            . "  stored version, and every page redirects to ?node=schema\n"
            . "  permanently. The usual cause is statements appended INSIDE\n"
            . "  the previous step's array rather than as new\n"
            . "  `\$this->schema[] = [...]` entries of their own.\n";
    fwrite(
        STDERR,
        "FAIL: FOG_SCHEMA is $schema but commons/schema.php goes to "
        . "$highest.\n"
        . $why
        . "  Set FOG_SCHEMA to $highest in packages/web/src/Base/"
        . "System.php, or add the missing step.\n"
    );
    exit(1);
}

echo "ok  FOG_SCHEMA $schema covers schema.php's highest step ($highest)\n";
exit(0);
