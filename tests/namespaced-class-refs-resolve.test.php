<?php
/**
 * Every unqualified class reference inside packages/web/src/ must resolve.
 *
 * PSR-4 moved ~202 classes under `packages/web/src/<Bucket>/` with a
 * `namespace FOG\<Bucket>;`. The 46 discovery-named files -- the `.page.php`,
 * `.hook.php`, `.report.php` and `.event.php` classes whose FILENAME is a
 * contract with FOGPageManager::loadPageClasses() and EventManager::load() --
 * stayed in `packages/web/lib/` under the flat `namespace FOG;`.
 *
 * That split is fine in one direction and a landmine in the other. PHP falls
 * back to the global namespace for FUNCTIONS and CONSTANTS, but NEVER for
 * class names. So an unqualified `new DashboardPage()` sitting in a file that
 * declares `namespace FOG\Base;` resolves to `FOG\Base\DashboardPage`, which
 * does not exist, and the request dies with a fatal -- a white screen.
 *
 * all-classes-load.test.php does not cover this: it proves every class
 * DECLARES, and a bad reference inside a method body declares perfectly well
 * and only explodes when that line runs. This shipped exactly that way and
 * white-screened the UI on the report menu.
 *
 * The fix is always a `use FOG\<Name>;` import, never a rename -- the two
 * HARD constraints stand: discovery-named files keep their names, and the
 * reverse class_alias ABI stays.
 *
 * Usage: php tests/namespaced-class-refs-resolve.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

/**
 * Short names declared under lib/ in the flat FOG namespace. Plugins are
 * excluded: they carry their own runtime loader (ADR 0009) and are not part
 * of the PSR-4 tree.
 */
$flat = [];
$walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/lib'));
foreach ($walk as $file) {
    if (!$file->isFile()
        || !preg_match('/\.(class|page|hook|report|event)\.php$/', $file->getFilename())
        || false !== strpos($file->getPathname(), '/lib/plugins/')
    ) {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (preg_match_all('/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait)\s+(\w+)/mi', $src, $m)) {
        foreach ($m[1] as $name) {
            $flat[$name] = str_replace($web . '/', '', $file->getPathname());
        }
    }
}

/** Short names declared under src/, i.e. resolvable from a sibling bucket. */
$bucketed = [];
$walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/src'));
foreach ($walk as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        $bucketed[$file->getBasename('.php')] = $file->getPathname();
    }
}

$fails = [];
foreach ($bucketed as $path) {
    $src = file_get_contents($path);
    $rel = str_replace($web . '/', '', $path);

    // Imports, by the short name they bind. `use A\B as C` binds C.
    $imports = [];
    if (preg_match_all('/^use\s+([^;]+);$/m', $src, $m)) {
        foreach ($m[1] as $use) {
            $use = trim($use);
            if (false !== stripos($use, ' as ')) {
                $parts = preg_split('/\s+as\s+/i', $use);
                $imports[trim($parts[1])] = true;
            } else {
                $imports[substr(strrchr('\\' . $use, '\\'), 1)] = true;
            }
        }
    }

    $tokens = token_get_all($src);
    $count = count($tokens);
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || T_STRING !== $token[0]) {
            continue;
        }
        $name = $token[1];
        if (!isset($flat[$name]) || isset($imports[$name]) || isset($bucketed[$name])) {
            continue;
        }
        // Neighbors with whitespace and comments skipped -- `new Foo` has a
        // T_WHITESPACE between the two, so a naive $tokens[$i - 1] never sees
        // the T_NEW and the reference reads as harmless.
        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
                continue;
            }
            $prev = $tokens[$j];
            break;
        }
        $next = null;
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
                continue;
            }
            $next = $tokens[$j];
            break;
        }
        // Already qualified, or a method/property/function/const name rather
        // than a class reference.
        if (is_array($prev)
            && in_array($prev[0], [T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST], true)
        ) {
            continue;
        }
        if (is_array($next) && T_NS_SEPARATOR === $next[0]) {
            continue;
        }
        $fails[] = sprintf(
            '%s:%d references %s unqualified, which resolves inside this '
            . "file's own namespace and does not exist. %s declares it in the "
            . 'flat FOG namespace -- add "use FOG\%s;"',
            $rel,
            $token[2],
            $name,
            $flat[$name],
            $name
        );
    }
}

$fails = array_values(array_unique($fails));
if (count($fails)) {
    fwrite(STDERR, 'FAIL:' . PHP_EOL);
    foreach ($fails as $fail) {
        fwrite(STDERR, "  - $fail\n");
    }
    exit(1);
}

printf(
    "ok: every reference from src/ (%d classes) to the %d flat lib/ classes is imported\n",
    count($bucketed),
    count($flat)
);
exit(0);
