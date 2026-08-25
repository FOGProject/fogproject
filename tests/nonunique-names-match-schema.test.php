<?php
/**
 * Route::$nonUniqueNameClasses must agree with the schema.
 *
 * create() and edit() refuse a body whose `name` any existing row of that
 * class already holds, unless the class is listed in
 * Route::$nonUniqueNameClasses. Whether that refusal is correct is a
 * question about the DATABASE: does a unique index cover the name column
 * on its own? Where the list and the schema disagree, the API answers a
 * 500 reading `Already created` to a write the database would have taken.
 *
 * The case that shipped: rolePermissions is keyed `(rpRoleID, rpName)`, a
 * COMPOSITE unique key, so two roles are meant to hold `plugin.view`. The
 * name appears in a unique index, which reads as "unique" to a careless
 * check, and is not unique by itself -- so granting a permission to a
 * second role was impossible over REST.
 *
 * Checks one direction only, and the asymmetry is the point:
 *
 *   listed here, but the schema makes the name unique  -> FAIL. The entry
 *       is wrong or stale, and it makes the API accept a duplicate the
 *       database will then reject with a constraint error.
 *
 *   NOT listed, and the schema does not make it unique -> reported, not
 *       failed. Four classes are in that state on purpose (imagetype,
 *       keysequence, module, pxemenuoptions): nothing indexes their names
 *       and nothing shows duplicates are intended either, so they stay
 *       strict. Failing on those would force the list to say something
 *       the schema cannot answer.
 *
 * Source-level, no database: the manifest carries each table's whole
 * CREATE TABLE statement, which is where the unique keys are written
 * (docs/adr/0008-secure-boot-enrolment-task-type.md:103).
 *
 * The manifest is CORE tables only, so a class whose table is absent is
 * skipped and counted -- see docs on schema-expected.php. A plugin class
 * is never in this list anyway.
 *
 * Usage: php tests/nonunique-names-match-schema.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$routeFile = $web . '/lib/router/route.class.php';
$manifestFile = $web . '/commons/schema-expected.php';

foreach ([$routeFile, $manifestFile] as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "FAIL: cannot read $file\n");
        exit(1);
    }
}

$routeSrc = (string)file_get_contents($routeFile);

/**
 * The classes the property declares.
 *
 * @param string $src route.class.php source
 * @param string $prop property name
 *
 * @return array
 */
function tnListProperty($src, $prop)
{
    if (!preg_match(
        '/public static \$' . preg_quote($prop, '/') . ' = \[(.*?)\];/s',
        $src,
        $m
    )) {
        fwrite(STDERR, "FAIL: could not find \$$prop\n");
        exit(1);
    }
    preg_match_all("/'([a-z0-9_]+)'/i", $m[1], $names);

    return array_map('strtolower', $names[1]);
}

$nonUnique = tnListProperty($routeSrc, 'nonUniqueNameClasses');
$validClasses = tnListProperty($routeSrc, 'validClasses');
if (count($nonUnique) < 1 || count($validClasses) < 1) {
    fwrite(STDERR, "FAIL: parsed an empty class list; the parser is stale\n");
    exit(1);
}

$manifest = require $manifestFile;
$tables = (array)($manifest['tables'] ?? []);
if (count($tables) < 1) {
    fwrite(STDERR, "FAIL: manifest carries no tables\n");
    exit(1);
}

/**
 * Does a UNIQUE index cover this column and nothing else?
 *
 * PRIMARY KEY counts: it is a unique index, and a name column that is the
 * primary key is unique by itself.
 *
 * @param string $create the CREATE TABLE statement
 * @param string $column the column to test
 *
 * @return bool
 */
function tnUniqueAlone($create, $column)
{
    preg_match_all(
        '/(?:UNIQUE\s+KEY\s+`[^`]+`|PRIMARY\s+KEY)\s*\(([^)]*)\)/i',
        $create,
        $m
    );
    foreach ($m[1] as $cols) {
        $parts = array_map(
            function ($c) {
                // Drop a prefix length, `col`(191).
                return strtolower(trim(preg_replace('/\(\d+\)/', '', trim($c)), " `\t\n"));
            },
            explode(',', $cols)
        );
        $parts = array_values(array_filter($parts, 'strlen'));
        if (1 === count($parts) && $parts[0] === strtolower($column)) {
            return true;
        }
    }

    return false;
}

$failures = [];
$skipped = [];
$strictButUnindexed = [];
$checked = 0;

foreach ($validClasses as $class) {
    $modelFile = $web . '/lib/fog/' . $class . '.class.php';
    if (!is_readable($modelFile)) {
        continue;
    }
    $src = (string)file_get_contents($modelFile);
    if (!preg_match("/protected \\\$databaseTable\s*=\s*'([^']+)'/", $src, $t)) {
        continue;
    }
    $table = $t[1];
    // The friendly `name` key, and the column it maps to. A class with no
    // `name` never reaches the uniqueness check at all -- create() guards
    // on property_exists($vars, 'name').
    if (!preg_match("/'name'\s*=>\s*'([^']+)'/", $src, $n)) {
        continue;
    }
    $column = $n[1];

    if (!isset($tables[$table]['create'])) {
        $skipped[] = $class . ' (' . $table . ' not in the manifest)';
        continue;
    }
    $checked++;
    $alone = tnUniqueAlone((string)$tables[$table]['create'], $column);
    $listed = in_array($class, $nonUnique, true);

    if ($listed && $alone) {
        $failures[] = sprintf(
            '%s is in $nonUniqueNameClasses, but `%s`.`%s` IS covered by a '
            . 'unique index on its own. The API will accept a duplicate the '
            . 'database then rejects.',
            $class,
            $table,
            $column
        );
    }
    if (!$listed && !$alone) {
        $strictButUnindexed[] = $class . ' (`' . $table . '`.`' . $column . '`)';
    }
}

if (count($skipped) > 0) {
    fwrite(STDOUT, 'skipped (no manifest entry): ' . implode(', ', $skipped) . "\n");
}
if (count($strictButUnindexed) > 0) {
    fwrite(
        STDOUT,
        "strict by choice -- no unique index on the name, still refusing\n"
        . "duplicates, see the docblock on \$nonUniqueNameClasses:\n  "
        . implode("\n  ", $strictButUnindexed) . "\n"
    );
}

if (count($failures) > 0) {
    fwrite(STDERR, "FAIL\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  ' . $f . "\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "ok  %d class(es) checked against the schema manifest; %d declared "
        . "non-unique, none of them uniquely indexed\n",
        $checked,
        count($nonUnique)
    )
);
exit(0);
