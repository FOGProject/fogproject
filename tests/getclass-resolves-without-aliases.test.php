<?php
/**
 * The bare names FOG uses to instantiate its own classes must resolve from
 * the src/ class map, not from the compatibility aliases.
 *
 * Every file under packages/web/src/ ends in a class_alias() re-exporting
 * itself into the global namespace (ADR 0013 §2). That alias is what makes
 * `getClass('Host')` work: the tree names its classes bare in ~520 literals,
 * in FOGController::getManager()'s `new $short.'Manager'`, and in all 52
 * lowercase entries of Route::$validClasses.
 *
 * Retiring those aliases is queued work (docs/composer-psr4-plan.md). The
 * step that makes it tractable is a single translation point --
 * Initiator::srcClassMap() supplying the names and FOGBase::qualify()
 * applying them -- so the 520 callers never need editing. This test covers
 * that point, and the list in part 3 is the work still outstanding before
 * the aliases can actually go.
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
 * FOGBase only reaches \Initiator::srcClassMap(), so a stub is enough to
 * load it standalone. Executing beats grepping here: a grep for "qualify"
 * passes just as happily when the method has been gutted to `return
 * $class;`.
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
}
require $web . '/src/Base/FOGBase.php';

$expect = [
    // A bare core name becomes qualified. This is the whole point.
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
    // Unknown names pass through, so this widens resolution and never
    // narrows it: a plugin class, a lib/ discovery class and a PHP built-in
    // all still reach the autoloader as themselves.
    'DateTimeZone'   => 'DateTimeZone',
    'CaponeTasking'  => 'CaponeTasking',
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

/** The 46 discovery-named classes under lib/, which carry their own aliases. */
$libClasses = [];
foreach (['pages', 'hooks', 'reports', 'events'] as $dir) {
    foreach (glob($web . '/lib/' . $dir . '/*.php') as $file) {
        $libClasses[strtolower(preg_replace('/\.(page|hook|report|event)\.php$/', '', basename($file)))] = true;
    }
}

/** Plugin classes: global-namespace by design (ADR 0009), never aliased. */
$pluginClasses = [];
if (is_dir($web . '/lib/plugins')) {
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($web . '/lib/plugins'));
    foreach ($walk as $file) {
        if ($file->isFile() && preg_match('/\.(class|page|hook|report|event|task)\.php$/', $file->getFilename())) {
            $pluginClasses[strtolower(preg_replace('/\.(class|page|hook|report|event|task)\.php$/', '', $file->getFilename()))] = true;
        }
    }
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

if (count($fails)) {
    fwrite(STDERR, 'FAIL:' . PHP_EOL);
    foreach ($fails as $fail) {
        fwrite(STDERR, "  - $fail\n");
    }
    exit(1);
}

printf(
    "ok: qualify() resolves %d cases, %d src/ classes map to the namespace "
    . "they declare, and all %d getClass() literals resolve without an alias\n",
    count($expect),
    count($srcMap),
    count($literals)
);
exit(0);
