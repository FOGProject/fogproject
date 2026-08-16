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
 * labelled 330 (task type 26) and left FOG_SCHEMA at 329, so that step
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
 * Usage: php tests/schema-gate.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$schemaFile = $root . '/commons/schema.php';
$systemFile = $root . '/lib/fog/system.class.php';

foreach ([$schemaFile, $systemFile] as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "FAIL: cannot read $path\n");
        exit(1);
    }
}

/*
 * Highest step label. Anchored to the start of the line so a number
 * mentioned inside a step's own explanatory comment -- which is indented --
 * cannot be mistaken for a label.
 */
$labels = [];
preg_match_all(
    '/^\/\/ (\d+)$/m',
    file_get_contents($schemaFile),
    $labels
);
if (!count($labels[1])) {
    fwrite(STDERR, "FAIL: found no `// N` step labels in $schemaFile\n");
    exit(1);
}
$highest = max(array_map('intval', $labels[1]));

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

if ($schema < $highest) {
    fwrite(
        STDERR,
        "FAIL: FOG_SCHEMA is $schema but commons/schema.php goes to "
        . "$highest.\n"
        . "  Every step after $schema applies to nobody: the coarse gate\n"
        . "  (mySchema < FOG_SCHEMA) never sends anyone to the schema\n"
        . "  updater, so the updater's own count check never runs. There is\n"
        . "  no error and no log line -- the step is simply never applied.\n"
        . "  Set FOG_SCHEMA to $highest in packages/web/lib/fog/"
        . "system.class.php.\n"
    );
    exit(1);
}

echo "ok  FOG_SCHEMA $schema covers schema.php's highest step ($highest)\n";
exit(0);
