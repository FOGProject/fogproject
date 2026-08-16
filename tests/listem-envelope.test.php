<?php
/**
 * Guards against iterating Route::listem()'s envelope instead of its rows.
 *
 * listem() puts a paginated wrapper in Route::$data -- draw, recordsTotal,
 * recordsFiltered, truncated, data, _lang, recordsReturned and four page URLs
 * -- so the rows are under ->data. Iterating the wrapper walks those eleven
 * scalar members instead, which does not error: it warns "Attempt to read
 * property X on int" and yields null for every field.
 *
 * That has bitten three times, in three repositories' worth of code, and each
 * time it failed silently rather than loudly:
 *   - ou plugin: ADOU never set on any client check-in
 *   - bootitem.hook.php: the fog.local boot-to-disk label never applied
 *   - route.class.php: cancelling a group's tasks cancelled nothing
 *
 * Static source check (no DB, no server) -- these call sites need the full FOG
 * bootstrap to run, so this parses them instead.
 *
 * Usage: php tests/listem-envelope.test.php [path...]
 * Exit status 0 = pass, 1 = fail.
 */

$roots = array_slice($argv, 1);
if (count($roots) === 0) {
    $roots = [dirname(__DIR__) . '/packages/web'];
}

$files = [];
foreach ($roots as $root) {
    if (is_file($root)) {
        $files[] = $root;
        continue;
    }
    if (!is_dir($root)) {
        fwrite(STDERR, "FAIL: cannot read $root\n");
        exit(1);
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        // Composer's tree is third-party and does not use Route at all.
        if (strpos($f->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        // lib/plugins is gitignored here -- it is fetched from FOGProject
        // /fog-plugins by bin/fetch-plugins.sh (ADR 0009), so its contents
        // vary per checkout and are not this repo's to assert on. That repo
        // runs this same test against its own tree; point this one at it with
        // an explicit path argument if you want to check both at once.
        if (strpos($f->getPathname(), DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $files[] = $f->getPathname();
    }
}
sort($files);

$failures = [];

foreach ($files as $file) {
    $src = file_get_contents($file);

    // $Foo = json_decode(Route::getData());  ... then how $Foo gets used.
    if (!preg_match_all(
        '/Route::listem\s*\(.{0,400}?\$(\w+)\s*=\s*json_decode\s*\(\s*Route::getData\(\)\s*\)\s*;(.{0,800})/s',
        $src,
        $matches,
        PREG_SET_ORDER
    )) {
        continue;
    }

    foreach ($matches as $match) {
        $var = $match[1];
        $after = $match[2];

        // Reading ->data is the correct shape; nothing more to check.
        if (preg_match('/\$' . $var . '\s*->\s*data\b/', $after)) {
            continue;
        }
        // foreach over the variable itself, or subscripting it, means the
        // envelope is being treated as the row set.
        if (preg_match(
            '/foreach\s*\(\s*\$' . $var . '\s*(?!->)|\$' . $var . '\s*\[/',
            $after
        )) {
            $line = substr_count(
                substr($src, 0, strpos($src, $match[0])),
                "\n"
            ) + 1;
            $failures[] = sprintf(
                '%s:~%d: $%s holds listem()\'s envelope but is iterated '
                . 'directly -- read $%s->data',
                $file,
                $line,
                $var,
                $var
            );
        }
    }
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

printf("listem-envelope: %d files scanned, no envelope iteration\n", count($files));
echo "PASS\n";
exit(0);
