<?php
/**
 * Guards the "filter key the class does not declare" bug class.
 *
 * Route::_buildSql() resolves a filter key to a column with
 * $classVars['databaseFields'][$key]. A key the class does not declare used
 * to resolve to nothing, emitting `WHERE `` = :where_0`, which the database
 * rejects (ER_BAD_FIELD_ERROR). Callers that do not check their return --
 * deletemass() among them -- swallowed that silently: Group::createImagePackage()
 * carried a delete of multicastsessionassociation by 'hostID' (a column that
 * table has never had) that therefore never ran once in six years.
 *
 * _buildSql() now compiles such a filter to 1=0 and logs it, and
 * handleWhereItems() refuses request-supplied keys outright, so the failure
 * mode is no longer a rejected query. Neither guard stops a caller writing
 * the wrong field name in the first place, which is what this checks.
 *
 * Static by design: Route extends FOGBase, so exercising _buildSql directly
 * would need the full bootstrap and a live database. This parses source, so
 * it runs anywhere, including in the pre-commit hook.
 *
 * Usage: php tests/route-filter-fields.test.php [path/to/packages/web]
 * Exit status 0 = pass, 1 = fail.
 */

$root = rtrim(
    $argv[1] ?? dirname(__DIR__) . '/packages/web',
    '/'
);

if (!is_dir($root)) {
    fwrite(STDERR, "FAIL: no such directory: $root\n");
    exit(1);
}

$files = [];
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($rii as $f) {
    if (!$f->isDir() && substr($f->getFilename(), -4) === '.php') {
        $files[] = $f->getPathname();
    }
}
sort($files);

/*
 * Build "class name (lowercased) => declared friendly field names" from
 * source. Reflection is not an option: FOG's autoloader is lazy, so
 * get_declared_classes() sees almost nothing without a full boot. 1.6 writes
 * $databaseFields = [...]; 1.5/dev-branch writes array(...) -- accept both so
 * this test can be pointed at either branch.
 */
$fields = [];
foreach ($files as $path) {
    $src = file_get_contents($path);
    if (!preg_match('/\bclass\s+([A-Za-z0-9_]+)/', $src, $cm)) {
        continue;
    }
    $re = '/\$databaseFields\s*=\s*(?:\[(.*?)\n\s*\];|array\((.*?)\n\s*\);)/s';
    if (!preg_match($re, $src, $fm)) {
        continue;
    }
    $body = ('' !== $fm[1] ? $fm[1] : ($fm[2] ?? ''));
    preg_match_all("/'([A-Za-z0-9_]+)'\s*=>/", $body, $km);
    if ($km[1]) {
        $fields[strtolower($cm[1])] = $km[1];
    }
}

if (count($fields) < 1) {
    fwrite(STDERR, "FAIL: parsed no databaseFields maps under $root\n");
    exit(1);
}

/*
 * Match Route:: calls that name both the class and their filter keys as
 * literals -- the only form that can be checked without running anything.
 * One level of nesting is allowed so ['stateID' => [1, 2]] is still seen.
 * Calls built from variables are out of scope and are not reported.
 */
$failures = [];
$re = "/Route::(deletemass|getIds|ids|listem|count|activeCount)"
    . "\(\s*'([A-Za-z0-9_]+)'\s*,\s*\[((?:[^\]\[]|\[[^\]\[]*\])*)\]/s";
foreach ($files as $path) {
    $src = file_get_contents($path);
    if (!preg_match_all($re, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($matches as $m) {
        $class = strtolower($m[2][0]);
        if (!isset($fields[$class])) {
            // Not a model with a field map (or named via an alias) -- the
            // check has nothing to compare against, so say nothing.
            continue;
        }
        preg_match_all("/'([A-Za-z0-9_]+)'\s*=>/", $m[3][0], $km);
        foreach ($km[1] as $key) {
            if (in_array($key, $fields[$class], true)) {
                continue;
            }
            $failures[] = sprintf(
                "%s:%d\n    Route::%s('%s', ['%s' => ...])\n"
                . "    '%s' is not a field of %s; declared: %s",
                str_replace($root . '/', '', $path),
                substr_count(substr($src, 0, $m[0][1]), "\n") + 1,
                $m[1][0],
                $m[2][0],
                $key,
                $key,
                $m[2][0],
                implode(', ', $fields[$class])
            );
        }
    }
}

printf(
    "route-filter-fields: %d files, %d field maps, %d violation(s)\n",
    count($files),
    count($fields),
    count($failures)
);

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "\nFAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
