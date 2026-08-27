<?php
/**
 * Adds the `use` imports that let core name its own classes without the
 * compatibility aliases.
 *
 * Every file under packages/web/src/ ends in a class_alias() re-exporting
 * itself into the global namespace (ADR 0013 §2). That alias is what makes
 * the rest of the tree work, because almost nothing that NAMES a core class
 * is namespaced:
 *
 *   lib/       the 46 discovery-named classes, `namespace FOG;`. A bare
 *              `Hook` there resolves to FOG\Hook, which Composer cannot
 *              serve, so Initiator::_bridgeNamespaced() falls back to the
 *              global alias.
 *   commons/   global namespace. `new System()` is the very first thing
 *              startInit() does.
 *   service/   global namespace, one bare `new X(...)` per entry point.
 *   api/
 *   management/
 *   status/      the node and fog-client endpoints -- bandwidth, getfiles,
 *   maintenance/ gethash, hostgetkey, newtoken, create_update_node. Added
 *   client/      after the first sweep missed them: they are not under
 *                service/ and are easy to forget precisely because nothing
 *                in the test suite drives them.
 *
 * This adds `use FOG\<Bucket>\<Name>;` so each bare name resolves to the
 * import instead. Imports rather than inline qualification because the
 * volume is lopsided: one lib/ file carries 165 references to
 * HTTPResponseCodes, and `\FOG\Router\HTTPResponseCodes::` written out 165
 * times is not code anyone can read.
 *
 * Tokenised, never regex. A class name in a docblock, a string or a comment
 * is left alone, and `$obj->Route` is not a class reference.
 *
 * Names already imported, already qualified, or declared by the file itself
 * are skipped. So is any name the file's own namespace already resolves --
 * a lib/ file in `namespace FOG;` referring to another lib/ class needs
 * nothing.
 *
 * Usage:
 *   php bin/import-core-classes.php [--fix]
 *
 * Without --fix it reports and changes nothing. Exit 1 if anything is left
 * to do, 0 when clean.
 */

$fix = in_array('--fix', $argv, true);
$repo = dirname(__DIR__);
$web = $repo . '/packages/web';

/** src/ classes: lowercased short name => FQCN, PSR-4 so the path is the name. */
$core = [];
$walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/src'));
foreach ($walk as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        $short = $file->getBasename('.php');
        $core[strtolower($short)] = [
            'fqcn' => 'FOG\\' . basename(dirname($file->getPathname())) . '\\' . $short,
            'short' => $short,
        ];
    }
}

/**
 * The 46 discovery-named classes under lib/, in the flat FOG namespace.
 *
 * Not rewritten themselves -- their filenames are a contract with
 * FOGPageManager::loadPageClasses() -- but a file that NAMES one needs to
 * know where it lives, and from a global-namespace file that is FOG\Name.
 */
$flat = [];
foreach (['pages', 'hooks', 'reports', 'events'] as $dir) {
    foreach (glob($web . '/lib/' . $dir . '/*.php') as $path) {
        $src = file_get_contents($path);
        if (preg_match('/^\s*(?:final\s+|abstract\s+)*class\s+(\w+)/mi', $src, $m)) {
            $flat[strtolower($m[1])] = ['fqcn' => 'FOG\\' . $m[1], 'short' => $m[1]];
        }
    }
}

$targets = [
    $web . '/lib/pages', $web . '/lib/hooks', $web . '/lib/reports',
    $web . '/lib/events', $web . '/commons', $web . '/service', $web . '/api',
    $web . '/management', $web . '/status', $web . '/maintenance',
    $web . '/client', $repo . '/packages/service', $repo . '/tests',
];

/**
 * Tests that assert on the RESOLUTION MECHANISM rather than merely using it.
 *
 * Adding a `use` import to one of these would make the bare name it is
 * deliberately probing resolve, and the assertion would pass for the reason
 * the import supplies rather than the one under test -- a gate that can only
 * ever be green. They are edited by hand.
 */
$handEdited = [
    'tests/autoload.test.php',
    'tests/autoload-core-wins.test.php',
    'tests/psr4-bridge.test.php',
    'tests/all-classes-load.test.php',
    'tests/stale-class-file-list.test.php',
    'tests/global-class-prefix.test.php',
    'tests/entrypoint-classes-resolve.test.php',
    'tests/getclass-resolves-without-aliases.test.php',
    'tests/namespaced-class-refs-resolve.test.php',
    'tests/oldcopy-retires-moved-classes.test.php',
];

$skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
$totalRefs = 0;
$totalFiles = 0;
$report = [];

foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target));
    foreach ($walk as $file) {
        $path = $file->getPathname();
        if (!$file->isFile() || 'php' !== $file->getExtension()
            || false !== strpos($path, '/plugins/')
            || false !== strpos($path, '/vendor/')
            || in_array(
                str_replace($repo . '/', '', $path),
                $handEdited,
                true
            )
        ) {
            continue;
        }
        $src = file_get_contents($path);

        // The file's own namespace, and what it already declares or imports.
        preg_match('/^namespace\s+([^;]+);/m', $src, $nsm);
        $ns = isset($nsm[1]) ? trim($nsm[1]) : '';
        $known = [];
        if (preg_match_all('/^use\s+([^;]+);$/m', $src, $um)) {
            foreach ($um[1] as $use) {
                $use = trim($use);
                $bind = false !== stripos($use, ' as ')
                    ? trim(preg_split('/\s+as\s+/i', $use)[1])
                    : substr(strrchr('\\' . $use, '\\'), 1);
                $known[strtolower($bind)] = true;
            }
        }
        if (preg_match_all('/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait)\s+(\w+)/mi', $src, $dm)) {
            foreach ($dm[1] as $name) {
                $known[strtolower($name)] = true;
            }
        }

        $tokens = token_get_all($src);
        $count = count($tokens);
        $wanted = [];
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || T_STRING !== $tokens[$i][0]) {
                continue;
            }
            $name = $tokens[$i][1];
            $key = strtolower($name);
            if (in_array($key, ['self', 'parent', 'static'], true) || isset($known[$key])) {
                continue;
            }
            $entry = $core[$key] ?? ($flat[$key] ?? null);
            if (null === $entry) {
                continue;
            }
            // A file already inside FOG\ resolves the flat names itself.
            if ('FOG' === $ns && isset($flat[$key])) {
                continue;
            }
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
            if (is_array($prev)
                && in_array($prev[0], [T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST], true)
            ) {
                continue;
            }
            if (is_array($next) && T_NS_SEPARATOR === $next[0]) {
                continue;
            }
            $isClassRef = (is_array($next) && T_DOUBLE_COLON === $next[0])
                || (is_array($prev) && in_array($prev[0], [T_NEW, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF], true));
            if (!$isClassRef) {
                continue;
            }
            $wanted[$entry['fqcn']] = true;
        }
        if (!$wanted) {
            continue;
        }
        $rel = str_replace($repo . '/', '', $path);
        $report[] = sprintf('%-56s %d', $rel, count($wanted));
        $totalFiles++;
        $totalRefs += count($wanted);
        if (!$fix) {
            continue;
        }
        $lines = array_keys($wanted);
        sort($lines);
        $block = implode("\n", array_map(fn ($f) => "use $f;", $lines));
        if ('' !== $ns) {
            // After the namespace declaration, merged with any existing block.
            $src = preg_replace(
                '/^(namespace\s+[^;]+;\n)/m',
                "$1\n" . $block . "\n",
                $src,
                1
            );
        } else {
            // Global namespace: `use` is legal at top level. It must go after
            // the file docblock AND after any declare(), because
            // declare(strict_types=1) has to be the very first statement in
            // the file -- putting the imports above it is a parse error, not
            // a style problem. api/index.php found this immediately.
            $src = preg_replace(
                '/^(<\?php\n(?:\s*declare\s*\([^)]*\)\s*;\n)?(?:\s*\/\*\*.*?\*\/\n)?(?:\s*declare\s*\([^)]*\)\s*;\n)?)/s',
                "$1\n" . $block . "\n",
                $src,
                1
            );
        }
        // Fold a duplicated blank line the insertion may have created, then
        // guarantee one AFTER the block. The insert puts a newline after the
        // last `use`, which is a blank line only when the following line was
        // itself blank -- in a global-namespace file the class docblock
        // usually butts straight up against the anchor, so without this the
        // block and the docblock end up adjacent.
        $src = preg_replace("/\n{3,}/", "\n\n", $src);
        $src = preg_replace('/^(use [^;\n]+;\n)(?!use |\n)/m', "$1\n", $src, 1);
        file_put_contents($path, $src);
    }
}

sort($report);
echo $report ? implode("\n", $report) . "\n" : "nothing to import\n";
printf(
    "%s: %d import(s) across %d file(s)\n",
    $fix ? 'added' : 'would add',
    $totalRefs,
    $totalFiles
);
exit($totalRefs && !$fix ? 1 : 0);
