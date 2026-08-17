<?php
/**
 * Moves FOG's class files into the FOG\ namespace, one file at a time.
 *
 * Phase 3 of the refactor. Each file gets exactly two additions and nothing
 * else:
 *
 *   1. `namespace FOG;` after the file-level docblock.
 *   2. `class_alias(__NAMESPACE__ . '\Name', 'Name');` at the end.
 *
 * The alias is the whole compatibility story and it is not a transitional
 * convenience -- it is the 1.6 plugin ABI. Every consumer of a class name
 * keeps working through it: `new Host`, `$obj instanceof $str`,
 * `class_exists('host')` (lookup stays case-insensitive), Reflection,
 * `is_subclass_of($c, 'PluginTask')`, and all 350 getClass() literals. None
 * of them are edited, here or ever.
 *
 * Cross-references inside a converted file are left unqualified on purpose.
 * `class Child extends FOGController` inside `namespace FOG;` asks the
 * autoloader for FOG\FOGController, and Initiator::_bridgeNamespaced()
 * answers that whether or not fogcontroller.class.php has itself been
 * converted yet. That is what makes a partially converted tree work, and so
 * what makes this safe to run in batches.
 *
 * References to PHP's own classes are already backslash-prefixed tree-wide
 * (Phase 0.1, guarded by tests/global-class-prefix.test.php), so nothing
 * here has to reason about them. Functions and constants need no prefix:
 * both fall back to the global scope from inside a namespace.
 *
 * Usage:
 *   php bin/namespace-fog-classes.php --check [path...]   # list, exit 1 if any
 *   php bin/namespace-fog-classes.php --fix   [path...]   # convert
 *
 * With no paths, walks every tracked PHP file. Idempotent: a file that
 * already declares a namespace is skipped.
 *
 * @category Tooling
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

const NS = 'FOG';

/**
 * Never converted, each for its own reason.
 *
 * altorouter.class.php and its companion altotransformer.class.php are a
 * FORK of altorouter/altorouter, not a vendored copy: 324 of 357 code lines
 * differ from every upstream tag and from master, and route.class.php is
 * built on 29 calls to a method upstream does not have. They are excluded
 * because they keep upstream's name, authorship and MIT license, and moving
 * someone else's class into FOG\ misattributes it. The reasoning is in the
 * class docblock; the swap that used to be the reason is not happening.
 *
 * mysqldump.class.php came off this list when its swap did happen: it is now
 * a short FOG subclass of the Composer package, so it is namespaced like any
 * other FOG class and the library it wraps lives under vendor/, which this
 * tool never walks.
 *
 * Initiator IS the autoloader. A namespaced autoloader that has to load
 * itself is a bootstrap problem in exchange for nothing.
 */
const EXCLUDE = [
    'packages/web/lib/router/altorouter.class.php',
    'packages/web/lib/router/altotransformer.class.php',
    'packages/web/commons/init.php',
];

$args = array_slice($argv, 1);
$mode = '';
$paths = [];
foreach ($args as $arg) {
    if ('--check' === $arg || '--fix' === $arg) {
        $mode = $arg;
        continue;
    }
    $paths[] = $arg;
}
if ('' === $mode) {
    fwrite(STDERR, "usage: {$argv[0]} --check|--fix [path...]\n");
    exit(2);
}

$root = dirname(__DIR__);
chdir($root);

if (0 === count($paths)) {
    $paths = array_filter(
        explode("\n", (string) shell_exec('git ls-files "*.php"'))
    );
}

$pending = [];
$converted = 0;
$qualified = 0;
$skipped = 0;

foreach ($paths as $path) {
    $path = trim($path);
    if ('' === $path || !is_readable($path) || !is_file($path)) {
        continue;
    }
    if (0 === strpos($path, 'packages/web/vendor/')
        || 0 === strpos($path, 'tests/')
        || in_array($path, EXCLUDE, true)
    ) {
        $skipped++;
        continue;
    }

    $src = file_get_contents($path);
    $info = inspect($src);

    if (null !== $info) {
        if ('--check' === $mode) {
            $pending[] = $path . '  (' . $info['name'] . ') -- not namespaced';
            continue;
        }
        $src = convert($src, $info);
        $converted++;
        printf("converted %-58s %s\n", $path, $info['name']);
    }

    // Second pass, and it runs on ALREADY-converted files too. See
    // qualifyExcluded() for why this is not optional.
    $refs = qualifyExcluded($src);
    if (0 === count($refs['names'])) {
        if (null === $info) {
            $skipped++;
        }
        continue;
    }
    if ('--check' === $mode) {
        $pending[] = sprintf(
            '%s  -- %d unqualified reference(s) to excluded class(es): %s',
            $path,
            $refs['count'],
            implode(', ', array_keys($refs['names']))
        );
        continue;
    }
    file_put_contents($path, $refs['src']);
    $qualified += $refs['count'];
    printf(
        "qualified %-58s %d ref(s): %s\n",
        $path,
        $refs['count'],
        implode(', ', array_keys($refs['names']))
    );
    $src = $refs['src'];
}

if ('--check' === $mode) {
    if (0 === count($pending)) {
        printf("ok: nothing left to do (%d file(s) skipped)\n", $skipped);
        exit(0);
    }
    fwrite(STDERR, count($pending) . " file(s) need work:\n");
    foreach ($pending as $p) {
        fwrite(STDERR, "  $p\n");
    }
    exit(1);
}

printf(
    "\n%d converted, %d reference(s) qualified, %d skipped\n",
    $converted,
    $qualified,
    $skipped
);
exit(0);

/**
 * What a file declares, and where its namespace line belongs.
 *
 * Returns null when there is nothing to do: no class/interface/trait, or a
 * namespace already present.
 *
 * @param string $src the file's contents
 *
 * @return array|null ['name' => string, 'offset' => int]
 */
function inspect($src)
{
    $tokens = token_get_all($src);
    $count = count($tokens);
    $name = null;
    // Byte offset the namespace line is inserted at. Defaults to just past
    // `<?php`; moves past a declare() and the file docblock when present.
    $offset = null;
    $sawDocblock = false;
    $cursor = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;

        if (is_array($token)) {
            if (T_NAMESPACE === $token[0]) {
                // Already done. Idempotent by design -- see the header.
                return null;
            }
            if (T_OPEN_TAG === $token[0]) {
                $offset = $cursor + strlen($text);
            }
            if (T_DOC_COMMENT === $token[0] && !$sawDocblock) {
                // The FIRST docblock is the file docblock. FOG's files then
                // repeat it as the class docblock, and that second one must
                // stay attached to the class -- so only the first one moves
                // the insertion point.
                $sawDocblock = true;
                $offset = $cursor + strlen($text);
            }
            if (T_DECLARE === $token[0]) {
                // namespace must follow declare(strict_types=1);
                $semi = $i;
                while ($semi < $count && ';' !== $tokens[$semi]) {
                    $semi++;
                }
                $upto = 0;
                for ($k = 0; $k <= $semi && $k < $count; $k++) {
                    $upto += strlen(
                        is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k]
                    );
                }
                $offset = $upto;
            }
            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)
                && null === $name
            ) {
                // Skip `X::class` and anonymous classes.
                $back = $i;
                while (--$back >= 0
                    && is_array($tokens[$back])
                    && T_WHITESPACE === $tokens[$back][0]
                ) {
                    continue;
                }
                if (!isset($tokens[$back])
                    || !is_array($tokens[$back])
                    || T_DOUBLE_COLON !== $tokens[$back][0]
                ) {
                    $j = $i + 1;
                    while ($j < $count
                        && is_array($tokens[$j])
                        && T_WHITESPACE === $tokens[$j][0]
                    ) {
                        $j++;
                    }
                    if (isset($tokens[$j])
                        && is_array($tokens[$j])
                        && T_STRING === $tokens[$j][0]
                    ) {
                        $name = $tokens[$j][1];
                    }
                }
            }
        }
        $cursor += strlen($text);
    }

    if (null === $name || null === $offset) {
        return null;
    }
    return ['name' => $name, 'offset' => $offset];
}

/**
 * Insert the namespace declaration and append the compatibility alias.
 *
 * @param string $src  the file's contents
 * @param array  $info from inspect()
 *
 * @return string
 */
function convert($src, array $info)
{
    $head = substr($src, 0, $info['offset']);
    $tail = substr($src, $info['offset']);

    $out = rtrim($head, "\n") . "\n\nnamespace " . NS . ";\n\n"
        . ltrim($tail, "\n");

    $alias = sprintf(
        "\n/*\n"
        . " * Compatibility alias. Every consumer of this class' name -- core,\n"
        . " * bundled plugins and third-party plugins alike -- keeps working\n"
        . " * unqualified through this, so no call site had to be edited.\n"
        . " * Supported for all of 1.6; see docs/adr/0013.\n"
        . " */\n"
        . "class_alias(__NAMESPACE__ . '\\\\%s', '%s');\n",
        $info['name'],
        $info['name']
    );

    return rtrim($out, "\n") . "\n" . $alias;
}

/**
 * Backslash-prefix references to classes that are NEVER namespaced.
 *
 * The excluded files stay in the global namespace, so from inside
 * `namespace FOG;` an unqualified `Initiator::e($x)` resolves to
 * FOG\Initiator -- which does not exist and, in Initiator's case, never can:
 * commons/init.php is not a *.class.php file, so it is not in the
 * autoloader's map at all and no bridge can reach it.
 *
 * This is the one part of the migration that unit tests cannot catch. Class
 * resolution passes, every source scan passes, and the application is still
 * completely broken -- Initiator::e() is the output escaper, so it is on
 * essentially every page. It was found by booting the tree against a live
 * database, and it is why bin/... --check is wired into the test suite.
 *
 * Runs on already-converted files as well as newly converted ones, so it can
 * be applied after the fact.
 *
 * @param string $src the file's contents
 *
 * @return array ['src' => string, 'count' => int, 'names' => array]
 */
function qualifyExcluded($src)
{
    static $excluded = null;
    if (null === $excluded) {
        $excluded = [];
        foreach (EXCLUDE as $file) {
            if (!is_readable($file)) {
                continue;
            }
            foreach (declaredIn($file) as $name) {
                $excluded[$name] = true;
            }
        }
    }

    $out = ['src' => $src, 'count' => 0, 'names' => []];
    if (false === strpos($src, 'namespace ' . NS . ';')) {
        return $out;
    }

    $tokens = token_get_all($src);
    $count = count($tokens);
    $pieces = [];
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;
        if (!is_array($token)
            || T_STRING !== $token[0]
            || !isset($excluded[$token[1]])
        ) {
            $pieces[] = $text;
            continue;
        }
        // Previous meaningful token decides whether this is a class
        // reference at all.
        $back = $i;
        while (--$back >= 0
            && is_array($tokens[$back])
            && T_WHITESPACE === $tokens[$back][0]
        ) {
            continue;
        }
        $prev = isset($tokens[$back]) ? $tokens[$back] : null;
        $skip = is_array($prev)
            && in_array(
                $prev[0],
                [
                    T_NS_SEPARATOR,      // already qualified
                    T_OBJECT_OPERATOR,   // ->Initiator(...)
                    T_DOUBLE_COLON,      // Foo::Initiator
                    T_FUNCTION,          // function Initiator()
                    T_CLASS,             // class Initiator
                    T_INTERFACE,
                    T_TRAIT,
                    T_CONST,
                ],
                true
            );
        if ($skip) {
            $pieces[] = $text;
            continue;
        }
        $pieces[] = '\\' . $text;
        $out['count']++;
        $out['names'][$token[1]] = true;
    }

    $out['src'] = implode('', $pieces);
    return $out;
}

/**
 * Names of every class, interface and trait a file declares.
 *
 * @param string $file path to read
 *
 * @return array of string
 */
function declaredIn($file)
{
    $tokens = token_get_all(file_get_contents($file));
    $count = count($tokens);
    $names = [];
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])
            || !in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT], true)
        ) {
            continue;
        }
        $back = $i;
        while (--$back >= 0
            && is_array($tokens[$back])
            && T_WHITESPACE === $tokens[$back][0]
        ) {
            continue;
        }
        if (isset($tokens[$back])
            && is_array($tokens[$back])
            && T_DOUBLE_COLON === $tokens[$back][0]
        ) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count
            && is_array($tokens[$j])
            && T_WHITESPACE === $tokens[$j][0]
        ) {
            $j++;
        }
        if (isset($tokens[$j])
            && is_array($tokens[$j])
            && T_STRING === $tokens[$j][0]
        ) {
            $names[] = $tokens[$j][1];
        }
    }
    return $names;
}
