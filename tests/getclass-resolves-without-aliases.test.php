<?php
/**
 * The bare names FOG uses to instantiate its own classes must resolve from
 * the src/ class map, not from the compatibility aliases.
 *
 * Core is addressed by string in ~440 places -- ~380 `getClass('Literal')`
 * calls, FOGController::getManager()'s `new $short.'Manager'`, and all of
 * Route::$validClasses, which spells its entries in lowercase. None of that
 * is visible to a static analyzer or to a compiler: it resolves at runtime,
 * from the global namespace, ignoring `use` imports entirely.
 *
 * Every file under packages/web/src/ USED to end in a class_alias()
 * re-exporting itself globally, which is what made those strings resolve.
 * All 202 were deleted in 1ecf0255d and ADR 0013 §2 is amended accordingly,
 * so the single translation point is now the only thing holding them up:
 * Initiator::srcClassMap() supplies the names, FOGBase::qualify() applies
 * them, and the ~440 callers were never edited.
 *
 * That makes this test load-bearing rather than transitional. A rename under
 * src/ that misses a string call site cannot fail to compile and cannot be
 * caught by PHPStan -- phpstan.neon analyzes this tree and is blind to all
 * of it. It fails here or it fails in production.
 *
 * Usage: php tests/getclass-resolves-without-aliases.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web  = $root . '/packages/web';
$fails = [];

/*
 * ------------------------------------------------------------------
 * 1. qualify() itself, executed against a map we control.
 *
 * FOGBase only reaches \Initiator::srcClassMap() and
 * \Initiator::pluginShortMap(), so a stub is enough to load it standalone.
 * Executing beats grepping here: a grep for "qualify" passes just as happily
 * when the method has been gutted to `return $class;`.
 *
 * The plugin half of the stub is deliberately hostile. Plugins are namespaced
 * too now -- FOG\Plugins\<Plugin>\<Class> -- so qualify() consults a second
 * map, and the ORDER it consults them in is a guarantee rather than a
 * preference: Authorization::_scopeClassVars() resolves a node to its model
 * through this function, so a plugin winning the bare name 'host' is access
 * control silently testing the wrong table. The stub therefore ships a rogue
 * plugin claiming exactly that.
 * ------------------------------------------------------------------
 */
class Initiator
{
    public static function srcClassMap(): array
    {
        return [
            'host'        => 'FOG\Items\Host',
            'hostmanager' => 'FOG\Managers\HostManager',
        ];
    }

    public static function pluginShortMap(): array
    {
        return [
            'caponetasking' => 'FOG\Plugins\Capone\CaponeTasking',
            // A plugin that declares its own Host. It is entitled to the
            // class; it is not entitled to the bare spelling.
            'host'          => 'FOG\Plugins\Rogue\Host',
        ];
    }
}
require $web . '/src/Base/FOGBase.php';

$expect = [
    // A bare core name becomes qualified. This is the whole point -- and,
    // since the stub's plugin map claims 'host' too, it is simultaneously
    // the check that core is consulted FIRST. That order is what stands
    // between a plugin and Authorization::_scopeClassVars() resolving the
    // 'host' node to the plugin's table.
    'Host'           => 'FOG\Items\Host',
    // Case-insensitively, because Route::$validClasses spells them lowercase
    // and the tree is inconsistent (both 'location' and 'Location' appear).
    'host'           => 'FOG\Items\Host',
    'HOST'           => 'FOG\Items\Host',
    // A manager lives in a different bucket from its model, so the name has
    // to survive being rebuilt from the bare one.
    'HostManager'    => 'FOG\Managers\HostManager',
    // Already qualified: untouched, never double-prefixed.
    'FOG\Items\Host' => 'FOG\Items\Host',
    // A bare PLUGIN name becomes qualified the same way a core one does,
    // which is what keeps the ~150 getClass('X') literals inside the plugins
    // working without editing one of them.
    'CaponeTasking'  => 'FOG\Plugins\Capone\CaponeTasking',
    'caponetasking'  => 'FOG\Plugins\Capone\CaponeTasking',
    // Unknown names still pass through, so this widens resolution and never
    // narrows it: a PHP built-in, a lib/ discovery class and a plugin still
    // written in the global namespace all reach the autoloader as themselves.
    'DateTimeZone'   => 'DateTimeZone',
    'ServerInfo'     => 'ServerInfo',
];
foreach ($expect as $in => $want) {
    $got = FOG\Base\FOGBase::qualify($in);
    if ($got !== $want) {
        $fails[] = "qualify('$in') returned '$got', expected '$want'";
    }
}

/*
 * ------------------------------------------------------------------
 * 2. The map derivation matches what the files actually declare.
 *
 * srcClassMap() derives the FQCN from the path -- src/<Bucket>/<Class>.php
 * is FOG\<Bucket>\<Class> -- which is true only while every file's own
 * namespace agrees with the directory holding it. Move a file between
 * buckets without editing its namespace line and the map starts handing out
 * a name nothing declares.
 * ------------------------------------------------------------------
 */
$srcMap = [];
$walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/src'));
foreach ($walk as $file) {
    if (!$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
    }
    $short  = $file->getBasename('.php');
    $bucket = basename(dirname($file->getPathname()));
    $fqcn   = 'FOG\\' . $bucket . '\\' . $short;
    $srcMap[strtolower($short)] = $fqcn;

    $src = file_get_contents($file->getPathname());
    $rel = str_replace($web . '/', '', $file->getPathname());
    if (!preg_match('/^namespace\s+([^;]+);/m', $src, $ns)) {
        $fails[] = "$rel declares no namespace, so srcClassMap() would name it $fqcn";
        continue;
    }
    if (trim($ns[1]) !== 'FOG\\' . $bucket) {
        $fails[] = sprintf(
            '%s declares namespace %s but sits in %s/, so srcClassMap() '
            . 'would name it %s -- which nothing declares',
            $rel,
            trim($ns[1]),
            $bucket,
            $fqcn
        );
    }
}

/*
 * ------------------------------------------------------------------
 * 3. Every bare name the tree instantiates resolves somewhere real.
 *
 * A name that is in none of these four buckets resolves today ONLY through
 * a compatibility alias, and would become a fatal the day those are
 * deleted. Finding one now is the point of this section.
 * ------------------------------------------------------------------
 */
$literals = [];
$dirs = ['src', 'lib', 'commons', 'service', 'api'];
foreach ($dirs as $dir) {
    if (!is_dir($web . '/' . $dir)) {
        continue;
    }
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/' . $dir));
    foreach ($walk as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }
        // Tokenised, not pattern-matched: a docblock in init.php explains
        // this very mechanism using getClass('X') as its example, and a
        // regex over raw source dutifully reports X as an unresolvable
        // class name.
        $tokens = token_get_all(file_get_contents($file->getPathname()));
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])
                || T_STRING !== $tokens[$i][0]
                || 'getClass' !== $tokens[$i][1]
            ) {
                continue;
            }
            $rest = [];
            for ($j = $i + 1; $j < $count && count($rest) < 2; $j++) {
                if (is_array($tokens[$j])
                    && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    continue;
                }
                $rest[] = $tokens[$j];
            }
            if ('(' !== ($rest[0] ?? null)
                || !is_array($rest[1] ?? null)
                || T_CONSTANT_ENCAPSED_STRING !== $rest[1][0]
            ) {
                continue;
            }
            $name = trim($rest[1][1], "'\"");
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                $literals[$name] = true;
            }
        }
    }
}

/**
 * The discovery-named classes that used to live under lib/{pages,hooks,
 * reports,events}. All 52 moved to src/<Bucket>/<Class>.php, so this finds
 * nothing on a current tree -- it is kept because a webroot upgraded rather
 * than laid fresh can still be carrying them, and a name that resolves there
 * and nowhere else is exactly what this file exists to name.
 */
$libClasses = [];
foreach (['pages', 'hooks', 'reports', 'events'] as $dir) {
    foreach (glob($web . '/lib/' . $dir . '/*.php') as $file) {
        $libClasses[strtolower(preg_replace('/\.(page|hook|report|event)\.php$/', '', basename($file)))] = true;
    }
}

/**
 * Plugin classes, which are PSR-4 exactly as core is: a plugin declares
 * FOG\Plugins\<Segment>\<Bucket>\<Class> out of
 * <plugin>/src/<Bucket>/<Class>.php (ADR 0035), and qualify() answers the
 * SHORT name from that path.
 *
 * Keyed on the basename, because the short name is what a getClass() literal
 * holds. This block used to match the pre-1.6 discovery suffixes, and that is
 * a mistake worth naming: lib/plugins is FETCHED rather than tracked, so it
 * is absent in CI and in a fresh clone -- the loop found no files, every
 * plugin literal fell through to the class_exists() escape, and the check
 * passed by never running. It only goes red on a tree that actually has
 * plugins, which is to say on a real server and nowhere else.
 */
$pluginClasses = [];
foreach ((array)glob($web . '/lib/plugins/*/src/*/*.php') as $file) {
    $pluginClasses[strtolower(basename($file, '.php'))] = true;
}

$unresolved = [];
foreach (array_keys($literals) as $name) {
    $key = strtolower($name);
    if (isset($srcMap[$key]) || isset($libClasses[$key]) || isset($pluginClasses[$key])) {
        continue;
    }
    // A PHP built-in or a vendored class, reached by its real global name.
    if (class_exists($name) || interface_exists($name)) {
        continue;
    }
    $unresolved[] = $name;
}
sort($unresolved);
if ($unresolved) {
    $fails[] = sprintf(
        "getClass() is called with %d name(s) that resolve through nothing "
        . "this tree declares -- they work only via a compatibility alias "
        . "and will fatal when those are retired: %s",
        count($unresolved),
        implode(', ', $unresolved)
    );
}

/*
 * ------------------------------------------------------------------
 * 4. Route instantiates its entities through Route::_newEntity().
 *
 * The router binds `:class` from a URL segment matched against
 * $validClasses, so a bare lowercase name reaches indiv(), edit(), task(),
 * create(), cancel() and getsearchbody(). `new $class` on that name works
 * only through the compatibility alias.
 *
 * Two `new $class($id)` calls legitimately remain, in edit() and create()
 * after a successful save(): $class holds the saved OBJECT by then, and
 * `new $object` never consulted an alias. So this cannot be a count or a
 * grep for `new $class` -- it has to know which of the two $class is.
 *
 * Resolved by walking back to the variable's last assignment inside its own
 * function: assigned from new/_newEntity means an object and is fine;
 * reaching the parameter list means a bare string that bypassed the helper.
 * ------------------------------------------------------------------
 */
$routeFile = $web . '/src/Router/Route.php';
$routeLines = explode("\n", file_get_contents($routeFile));
foreach ($routeLines as $i => $line) {
    if (!preg_match('/=\s*new\s+\$(\w+)\s*[;(]/', $line, $m)) {
        continue;
    }
    $var = $m[1];
    // Walk back to the enclosing function, noting the last assignment.
    $origin = 'parameter';
    for ($j = $i - 1; $j >= 0; $j--) {
        if (preg_match('/^\s*(?:public|protected|private)\s.*function\s/', $routeLines[$j])) {
            break;
        }
        if (preg_match('/\$' . $var . '\s*=\s*(new\s|self::_newEntity\()/', $routeLines[$j])) {
            $origin = 'object';
            break;
        }
    }
    if ('object' !== $origin) {
        $fails[] = sprintf(
            'src/Router/Route.php:%d instantiates $%s directly from the bare '
            . 'route name. Bare names resolve only through the compatibility '
            . 'alias -- use self::_newEntity($%s[, $id])',
            $i + 1,
            $var,
            $var
        );
    }
}

if (count($fails)) {
    fwrite(STDERR, 'FAIL:' . PHP_EOL);
    foreach ($fails as $fail) {
        fwrite(STDERR, "  - $fail\n");
    }
    exit(1);
}

printf(
    "ok: qualify() resolves %d cases, %d src/ classes map to the namespace "
    . "they declare, all %d getClass() literals resolve without an alias, and "
    . "Route instantiates only objects directly\n",
    count($expect),
    count($srcMap),
    count($literals)
);
exit(0);
