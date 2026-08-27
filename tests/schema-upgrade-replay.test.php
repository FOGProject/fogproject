<?php
/**
 * An EXISTING server must be able to leave ?node=schema.
 *
 * Everything CI runs against the schema models a FRESH INSTALL. That is not a
 * gap anyone chose; it is what the code makes easy. SchemaUpdaterPage::update()
 * slices the step array from self::$mySchema, which is 0 on a new database, so
 * a fresh install genuinely executes every step from the beginning -- and
 * tests/schema-executes.test.php therefore sets $mySchema to 0 and FOG_SCHEMA
 * to PHP_INT_MAX, which neutralises the two expressions an upgrade turns on:
 *
 *     $hasIndexed = count($this->schema) > self::$mySchema;
 *     $items      = $hasIndexed ? array_slice($this->schema, self::$mySchema, null, true) : [];
 *     ... foreach ($items as $version => $updates) { ... }
 *     $newSchema->set('version', $version + 1);
 *
 * So the DATABASE side of an upgrade is already covered -- a server upgrading
 * from version N runs steps [0,N) then [N,end), which is the same statements in
 * the same order as a fresh install, and schema-executes runs exactly that. The
 * uncovered surface is not the SQL. It is the arithmetic above and the version
 * it leaves behind, and that is what this replays: for every version a server
 * could be sitting on, run the updater's own expressions over the real step
 * array and require the stored version to land on FOG_SCHEMA.
 *
 * Land below it and DatabaseManager::establish() goes on redirecting every
 * request to ?node=schema, while the updater reports "Update not required" --
 * an HTTP 204 that jQuery hands to $.notifyFromAPI as statusText "nocontent",
 * so the page loops a Generic Error toast forever. There is no way out from the
 * browser: the page that would fix the server is the page that is stuck.
 *
 * THE BUG THIS WOULD HAVE CAUGHT. #1338 appended seven UPDATE statements before
 * commons/schema.php's final `];` on the assumption that the file was one
 * array. It is ~326 separate `$this->schema[] = [...]` statements, so the seven
 * joined the PREVIOUS step: the element count did not move, FOG_SCHEMA went
 * 360 -> 367, and every existing server was left permanently on the schema
 * page. Fresh installs were correct throughout, because the statements still
 * ran inside that last step -- which is why all seven CI checks were green.
 *
 * WHY THIS COUNTS THE ARRAY WHEN tests/schema-gate.test.php COUNTS COMMENTS.
 * schema-gate's docblock records that a real count "was tried and rejected"
 * because schema.php "wants ~35 config constants and a couple of core classes,
 * and every one of those is a thing an unrelated schema commit could add". That
 * was true when it was written and is not any more:
 * tests/lib/fog-schema-collector.php DISCOVERS both, so neither can be
 * outgrown, and it has been executing this file in CI on three database engines
 * since GH-1221.
 *
 * The two gates are kept because they fail on different things and neither
 * subsumes the other:
 *
 *   schema-gate  compares FOG_SCHEMA to the highest `// N` label, and enforces
 *                that labels and appends come in pairs. Textual, so it also
 *                covers the label hygiene the collector cannot see.
 *   this test    compares FOG_SCHEMA to the number of elements that actually
 *                exist, which is label-independent. #1338 was invisible to the
 *                label check until the header comment introducing its steps was
 *                reworded -- a real count never needed telling.
 *
 * Needs no database, deliberately. The upgrade-specific surface is arithmetic,
 * so this runs on the plain PHP matrix and BLOCKS, rather than riding the
 * schema job where a failure would be one more tolerated leg.
 *
 * Usage: php tests/schema-upgrade-replay.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-schema-collector.php';

$root = dirname(__DIR__) . '/packages/web';
$schemaFile = $root . '/commons/schema.php';
$systemFile = $root . '/src/Base/System.php';

foreach ([$schemaFile, $systemFile] as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "FAIL: cannot read $path\n");
        exit(1);
    }
}

// Read from source rather than by booting System, whose constructor also
// defines FOG_VERSION and FOG_CHANNEL and calls _versionCompare(). The same
// regex tests/schema-gate.test.php uses, so the two cannot disagree about
// what the constant says.
$const = [];
if (!preg_match(
    "/define\(\s*'FOG_SCHEMA'\s*,\s*(\d+)\s*\)/",
    file_get_contents($systemFile),
    $const
)) {
    fwrite(STDERR, "FAIL: no FOG_SCHEMA definition in $systemFile\n");
    exit(1);
}
$fogSchema = (int)$const[1];

// The real constant, not PHP_INT_MAX: this test is about what a server does
// with it, and schema.php's step 29 branches on it.
$steps = fogCollectSchemaSteps($schemaFile, 'fog_schema_upgrade_replay', $fogSchema);
$count = count($steps);

$problems = [];

if (!$count) {
    fwrite(STDERR, "FAIL: collected no steps from commons/schema.php.\n");
    exit(1);
}

/*
 * Keys 0..n-1, contiguous. array_slice() is called with $preserve_keys = true,
 * and the stored version is written as `$version + 1` from the KEY -- so the
 * key is only a version number because the array is a contiguous list. One
 * `$this->schema[300] = ...` assignment by index and the arithmetic silently
 * means something else. schema.php already writes index 79 that way (`// 79`),
 * which is why this is checked rather than assumed.
 */
$expectedKeys = range(0, $count - 1);
if (array_keys($steps) !== $expectedKeys) {
    $gaps = array_values(array_diff($expectedKeys, array_keys($steps)));
    $extra = array_values(array_diff(array_keys($steps), $expectedKeys));
    $problems[] = "  The step array is not a contiguous list keyed from 0.\n"
        . '    missing key(s): ' . ($gaps ? implode(', ', array_slice($gaps, 0, 10)) : 'none') . "\n"
        . '    unexpected key(s): ' . ($extra ? implode(', ', array_slice($extra, 0, 10)) : 'none') . "\n"
        . "    The updater writes the stored version as \$version + 1 from the\n"
        . "    array KEY, so a gap makes that number mean something other than\n"
        . "    'steps applied' and a server can land short of FOG_SCHEMA.\n";
}

if ($count !== $fogSchema) {
    $problems[] = sprintf(
        "  FOG_SCHEMA is %d but commons/schema.php holds %d step(s).\n%s",
        $fogSchema,
        $count,
        $fogSchema > $count
            ? "    FOG_SCHEMA is ABOVE the element count, so the updater runs\n"
                . "    out of steps before the version reaches the gate. Every\n"
                . "    existing server is redirected to ?node=schema, the update\n"
                . "    answers 204 'Update not required', and the page loops.\n"
                . "    Fresh installs are unaffected, which is why this shape\n"
                . "    passes every other check. The usual cause is statements\n"
                . "    appended INSIDE the previous step's array rather than as\n"
                . "    new `\$this->schema[] = [...]` entries of their own (#1338).\n"
            : "    FOG_SCHEMA is BELOW the element count, so `mySchema <\n"
                . "    FOG_SCHEMA` stops being true before the last steps run.\n"
                . "    Nobody is ever sent to the updater to apply them and the\n"
                . "    steps above the gate reach no install at all (18edea94f).\n"
    );
}

/*
 * The replay. Every version a server could hold, driven through the updater's
 * own expressions -- copied in shape, not called, because indexPost() is 300
 * lines welded to a database, a session and three auth tiers.
 *
 * Every start rather than a sample: it is array arithmetic over 367 elements
 * and costs nothing, and sampling would leave "did you pick the right start"
 * as a question this test could have answered. Runs past FOG_SCHEMA because a
 * database can legitimately store a version above the array length -- a 1.5.x
 * carried count does exactly that (see SchemaReconciler's docblock).
 */
$stuck = [];
$ceiling = max($count, $fogSchema) + 2;
for ($start = 0; $start <= $ceiling; $start++) {
    $hasIndexed = $count > $start;
    $items = $hasIndexed
        ? array_slice($steps, $start, null, true)
        : [];
    // What SchemaUpdaterPage::update() would leave in schemaVersion.vValue:
    // unchanged if no step ran, otherwise the last key it reached, plus one.
    $stored = $start;
    foreach ($items as $version => $updates) {
        $stored = $version + 1;
    }
    // DatabaseManager::establish() / FOGBase::schemaNeedsDeploy(). A server at
    // or above the gate is never sent to the updater, so it has nothing to
    // escape from and there is nothing here to require of it.
    if ($start >= $fogSchema) {
        continue;
    }
    if ($stored < $fogSchema) {
        $stuck[] = [
            'from' => $start,
            'to' => $stored,
            'ran' => count($items),
        ];
    }
}

if ($stuck) {
    $lines = [];
    foreach (array_slice($stuck, 0, 3) as $s) {
        $lines[] = sprintf(
            "    a server at version %d applies %d step(s) and lands on %d,"
            . " still below FOG_SCHEMA (%d)",
            $s['from'],
            $s['ran'],
            $s['to'],
            $fogSchema
        );
    }
    $problems[] = sprintf(
        "  %d of the %d reachable starting version(s) can never leave"
        . " ?node=schema:\n%s\n%s"
        . "    Those servers are redirected to the updater on every request,\n"
        . "    and the updater answers 204 with 'Update not required'. jQuery\n"
        . "    reports that as statusText 'nocontent', which \$.notifyFromAPI\n"
        . "    renders as a Generic Error toast, on a loop. Nothing is logged\n"
        . "    and nothing errors -- and the page that would repair the server\n"
        . "    is the page that is stuck.\n",
        count($stuck),
        $fogSchema,
        implode("\n", $lines),
        count($stuck) > 3
            ? sprintf("    ... and %d more\n", count($stuck) - 3)
            : ''
    );
}

if ($problems) {
    fwrite(
        STDERR,
        "FAIL: commons/schema.php and FOG_SCHEMA do not agree, so an\n"
        . "upgrading server does not end up where it should.\n\n"
        . implode("\n", $problems) . "\n"
        . "  Every one of these is invisible to a fresh install, which runs\n"
        . "  from version 0 and therefore never evaluates the gate. Only a\n"
        . "  server that already has a version is affected.\n"
    );
    exit(1);
}

printf(
    "ok  every server from version 0 to %d reaches FOG_SCHEMA (%d)\n",
    $fogSchema - 1,
    $fogSchema
);
printf(
    "    %d steps collected, keys 0-%d, %d closure step(s) among them\n",
    $count,
    $count - 1,
    count(array_filter($steps, function ($updates) {
        foreach ((array)$updates as $update) {
            if (!is_string($update) && is_callable($update)) {
                return true;
            }
        }
        return false;
    }))
);
exit(0);
