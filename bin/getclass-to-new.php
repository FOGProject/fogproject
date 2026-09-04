#!/usr/bin/env php
<?php
/**
 * Rewrite `getClass('Literal', ...)` into a plain `new` expression.
 *
 * `FOGBase::getClass()` exists as the chokepoint the Phase 3 namespacing
 * migration needed (docs/refactor-brief.md): one function that turned a bare
 * short name into an FQCN, so ~500 call sites never had to be edited while
 * 226 files were renamed underneath them. That migration is finished, and
 * what the literal call sites are left paying for is real -- `getClass()` is
 * documented `@return object|mixed`, so PHPStan cannot type a single one of
 * them and no editor can follow one to its definition.
 *
 * It is NOT a substitution seam: `FOGBase::qualify()` consults the core map
 * before the plugin map deliberately (ADR 0013), so nothing can ever answer a
 * core name with a different class. There is therefore no behavior to
 * preserve beyond name resolution itself.
 *
 * What this rewrites, and only this:
 *
 *   - the first argument is a single quoted string naming a plain identifier;
 *   - there is no third argument (the `$props = true` mode returns
 *     ReflectionClass::getDefaultProperties() and has no `new` equivalent);
 *   - the name is not 'ReflectionClass' (getClass() special-cases it).
 *
 * A call whose first argument is a variable is the one shape `new` cannot
 * express, and is left alone -- that is what getClass() keeps existing for.
 *
 * Resolution mirrors FOGBase::qualify() exactly, including its order: core's
 * short-name map first, then the plugins', then unchanged. Getting that order
 * wrong here would silently repoint a call at a plugin class.
 *
 * Two output styles, matching where the code lives:
 *
 *   --style=import  a `use` line and a bare `new Host()`. The house style in
 *                   packages/web/src (255 of 289 files already carry a use
 *                   block) and what ADR 0013 chose for volume reasons.
 *   --style=fqcn    inline `new \FOG\Items\Host()`. Required in fog-plugins,
 *                   whose tests/core-references-are-qualified.test.php refuses
 *                   a bare core name, and used here for files that declare no
 *                   namespace.
 *
 * Usage:
 *   php bin/getclass-to-new.php [options] [file ...]
 *
 * Options:
 *   --core-src=DIR    core PSR-4 root (default: packages/web/src)
 *   --plugin-root=DIR plugin root to add to the short map; repeatable
 *   --style=STYLE     import (default) or fqcn
 *   --dry-run         report only, write nothing
 *   --quiet           suppress the per-site listing
 *
 * With no file arguments it rewrites every `*.php` tracked by git under the
 * current directory.
 *
 * Exit status 0 = every site either rewritten or deliberately skipped,
 * 1 = at least one site could not be resolved and needs a human.
 */

$opts = [
    'core-src' => 'packages/web/src',
    'style' => 'import',
    'dry-run' => false,
    'quiet' => false,
];
$pluginRoots = [];
$argFiles = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '--quiet') {
        $opts[substr($arg, 2)] = true;
    } elseif (strpos($arg, '--plugin-root=') === 0) {
        $pluginRoots[] = rtrim(substr($arg, 14), '/');
    } elseif (preg_match('#^--(core-src|style)=(.*)$#', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif (strpos($arg, '--') === 0) {
        fwrite(STDERR, "unknown option: $arg\n");
        exit(2);
    } else {
        $argFiles[] = $arg;
    }
}
if (!in_array($opts['style'], ['import', 'fqcn'], true)) {
    fwrite(STDERR, "--style must be import or fqcn\n");
    exit(2);
}

/**
 * The namespace a file declares, or '' for the global namespace.
 *
 * @param array $tokens token_get_all output
 *
 * @return string
 */
function nsOf(array $tokens)
{
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_NAMESPACE) {
            continue;
        }
        $ns = '';
        for ($j = $i + 1; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_string($t)) {
                if ($t === ';' || $t === '{') {
                    break 2;
                }
                continue;
            }
            if ($t[0] === T_WHITESPACE) {
                continue;
            }
            $ns .= $t[1];
        }
        break;
    }
    return trim($ns ?? '', '\\');
}

/**
 * Core's short-name map, derived exactly as Initiator::srcClassMap() does:
 * the bucket directory and the file basename, never the declared namespace.
 *
 * @param string $dir the src root
 *
 * @return array lowercased short name => FQCN
 */
function coreMap($dir)
{
    $map = [];
    if (!is_dir($dir)) {
        return $map;
    }
    $walk = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walk as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $map[strtolower($file->getBasename('.php'))] = 'FOG\\'
            . basename(dirname($path)) . '\\' . $file->getBasename('.php');
    }
    return $map;
}

/**
 * A plugin tree's short-name map.
 *
 * The namespace is READ from each file rather than derived from the directory
 * name: Initiator::pluginSegment() reads it too, and the casing differs
 * (ldap/ declares FOG\Plugins\LDAP). Deriving it would produce a name that
 * only resolves because PHP is case-insensitive, which is the drift
 * tests/getclass-literals.test.php was written to stop.
 *
 * @param array $roots plugin roots
 *
 * @return array lowercased short name => FQCN
 */
function pluginMap(array $roots)
{
    $map = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        foreach (glob($root . '/*/src/*/*.php') ?: [] as $path) {
            $tokens = token_get_all(file_get_contents($path));
            $ns = nsOf($tokens);
            if ($ns === '') {
                continue;
            }
            $class = basename($path, '.php');
            $short = strtolower($class);
            if (isset($map[$short])) {
                continue;
            }
            $map[$short] = $ns . '\\' . $class;
        }
    }
    return $map;
}

$core = coreMap($opts['core-src']);
$plugins = pluginMap($pluginRoots);

if ($core === [] && $plugins === []) {
    fwrite(STDERR, "no classes found -- check --core-src / --plugin-root\n");
    exit(2);
}

$files = $argFiles;
if ($files === []) {
    $files = array_values(array_filter(
        explode("\n", (string) shell_exec('git ls-files "*.php"')),
        function ($f) {
            return '' !== $f && is_readable($f)
                && 0 !== strpos($f, 'packages/web/vendor/');
        }
    ));
}

$rewritten = 0;
$skipped = [];
$unresolved = [];
$touched = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);
    if (strpos($src, 'getClass') === false) {
        continue;
    }
    $tokens = token_get_all($src);
    $count = count($tokens);

    // Offsets, so replacements can be spliced back into the original bytes.
    $offset = 0;
    $offsets = [];
    foreach ($tokens as $i => $t) {
        $offsets[$i] = $offset;
        $offset += strlen(is_array($t) ? $t[1] : $t);
    }

    $ns = nsOf($tokens);
    // A file in the GLOBAL namespace may still carry an import block --
    // commons/schema.php does -- and mixing an inline FQCN in beside an
    // already-bare `Schema::` reads as two conventions in one file. Follow
    // whichever the file already demonstrates; fall back to inline FQCN only
    // when it demonstrates neither, which is also the only case addImports()
    // has nowhere to put a `use` line.
    $hasImports = (bool) preg_match('/^use\s+[^;(]+;/m', $src);
    $style = ($ns === '' && !$hasImports) ? 'fqcn' : $opts['style'];

    // What short names this file already binds, and to what.
    $bound = [];
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_CLASS
            && ($i < 1 || !is_array($tokens[$i - 1])
                || $tokens[$i - 1][0] !== T_DOUBLE_COLON)
        ) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $bound[strtolower($tokens[$j][1])] = ($ns === '' ? '' : $ns . '\\')
                        . $tokens[$j][1];
                    break;
                }
            }
        }
        if (!is_array($t) || $t[0] !== T_USE) {
            continue;
        }
        // Only top-level imports; a closure's `use (...)` is followed by '('.
        $clause = '';
        for ($j = $i + 1; $j < $count; $j++) {
            $u = $tokens[$j];
            if (is_string($u)) {
                if ($u === ';') {
                    break;
                }
                if ($u === '(' || $u === '{') {
                    $clause = '';
                    break;
                }
                $clause .= $u;
                continue;
            }
            if ($u[0] === T_WHITESPACE) {
                $clause .= ' ';
                continue;
            }
            $clause .= $u[1];
        }
        $clause = trim($clause);
        if ($clause === '' || stripos($clause, 'function ') === 0
            || stripos($clause, 'const ') === 0
        ) {
            continue;
        }
        if (preg_match('/^(\S+)\s+as\s+(\S+)$/i', $clause, $m)) {
            $bound[strtolower($m[2])] = ltrim($m[1], '\\');
        } else {
            $short = substr($clause, strrpos('\\' . $clause, '\\'));
            $bound[strtolower($short)] = ltrim($clause, '\\');
        }
    }

    $edits = [];
    $needImports = [];

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING
            || strtolower($t[1]) !== 'getclass'
        ) {
            continue;
        }
        // Preceding significant token must be `::`; `getClass` appearing as a
        // method name in a declaration or inside a string is not a call site.
        $p = $i - 1;
        while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
            $p--;
        }
        if ($p < 0 || !is_array($tokens[$p]) || $tokens[$p][0] !== T_DOUBLE_COLON) {
            continue;
        }
        $n = $i + 1;
        while ($n < $count && is_array($tokens[$n]) && $tokens[$n][0] === T_WHITESPACE) {
            $n++;
        }
        if ($n >= $count || $tokens[$n] !== '(') {
            continue;
        }

        // Walk the argument list, splitting on depth-1 commas.
        $depth = 0;
        $args = [];
        $curStart = null;
        $curEnd = null;
        $curToks = [];
        $close = null;
        for ($j = $n; $j < $count; $j++) {
            $tok = $tokens[$j];
            $text = is_array($tok) ? $tok[1] : $tok;
            $opens = is_string($tok) && strpos('([{', $tok) !== false;
            if (!$opens && is_array($tok)
                && in_array($tok[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)
            ) {
                // `"{$x}"` opens with an ARRAY token and closes with a plain
                // '}' string token. Counting only the close takes the depth
                // negative and truncates the argument list.
                $opens = true;
            }
            if ($opens) {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif (is_string($tok) && strpos(')]}', $tok) !== false) {
                $depth--;
                if ($depth === 0) {
                    $close = $j;
                    if ($curToks !== []) {
                        $args[] = [$curStart, $curEnd, $curToks];
                    }
                    break;
                }
            } elseif ($depth === 1 && $tok === ',') {
                $args[] = [$curStart, $curEnd, $curToks];
                $curStart = $curEnd = null;
                $curToks = [];
                continue;
            }
            if (is_array($tok) && $tok[0] === T_WHITESPACE && $curToks === []) {
                continue;
            }
            if (is_array($tok) && $tok[0] === T_WHITESPACE) {
                continue;
            }
            if ($curStart === null) {
                $curStart = $offsets[$j];
            }
            $curEnd = $offsets[$j] + strlen($text);
            $curToks[] = $tok;
        }
        if ($close === null || $args === []) {
            continue;
        }

        $where = $file . ':' . $t[2];

        // First argument must be exactly one quoted string.
        $first = $args[0][2];
        if (count($first) !== 1 || !is_array($first[0])
            || $first[0][0] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            $skipped[] = [$where, 'dynamic class name'];
            continue;
        }
        $name = substr($first[0][1], 1, -1);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            $skipped[] = [$where, 'non-identifier literal'];
            continue;
        }
        if (strtolower($name) === 'reflectionclass') {
            $skipped[] = [$where, 'ReflectionClass special case'];
            continue;
        }
        if (count($args) > 2) {
            $skipped[] = [$where, 'props/3-arg form'];
            continue;
        }

        $lower = strtolower($name);
        if (isset($core[$lower])) {
            $fqcn = $core[$lower];
        } elseif (isset($plugins[$lower])) {
            $fqcn = $plugins[$lower];
        } elseif (class_exists($name) || interface_exists($name)) {
            // A PHP built-in. tests/global-class-prefix.test.php requires
            // every global class reference to carry its leading backslash,
            // so these are always spelled inline and never imported.
            $edits[] = [
                $offsets[$p - 1],
                $offsets[$close] + 1,
                'new \\' . $name . '('
                . (count($args) > 1
                    ? substr($src, $args[1][0], $args[1][1] - $args[1][0])
                    : '')
                . ')',
            ];
            $rewritten++;
            continue;
        } else {
            $unresolved[] = [$where, $name];
            continue;
        }

        $short = substr($fqcn, strrpos('\\' . $fqcn, '\\'));
        $shortLower = strtolower($short);
        $ref = '\\' . $fqcn;
        if ($style === 'import') {
            if (isset($bound[$shortLower])) {
                // Already bound here: bare only if it means the same class.
                if ($bound[$shortLower] === $fqcn) {
                    $ref = $short;
                }
            } elseif ($ns !== '' && $ns . '\\' . $short === $fqcn) {
                $ref = $short;  // same namespace; an import would be noise
            } else {
                $needImports[$fqcn] = true;
                $bound[$shortLower] = $fqcn;
                $ref = $short;
            }
        }

        $inner = '';
        if (count($args) > 1) {
            $inner = substr($src, $args[1][0], $args[1][1] - $args[1][0]);
        }
        $expr = 'new ' . $ref . '(' . $inner . ')';

        // PHP's floor here is 7.4, so `new X()->y()` is a parse error.
        $after = $close + 1;
        while ($after < $count && is_array($tokens[$after])
            && $tokens[$after][0] === T_WHITESPACE
        ) {
            $after++;
        }
        $next = $after < $count ? $tokens[$after] : null;
        $chained = $next === '[' || $next === '{'
            || (is_array($next)
                && in_array($next[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true))
            || (defined('T_NULLSAFE_OBJECT_OPERATOR') && is_array($next)
                && $next[0] === T_NULLSAFE_OBJECT_OPERATOR);
        if ($chained) {
            $expr = '(' . $expr . ')';
        }

        // Replace from the class-reference prefix (`self`) through `)`.
        $start = $offsets[$p - 1];
        $q = $p - 1;
        while ($q > 0 && is_array($tokens[$q])
            && in_array($tokens[$q][0], [T_STRING, T_NS_SEPARATOR, T_STATIC], true)
        ) {
            $start = $offsets[$q];
            $q--;
        }
        $end = $offsets[$close] + 1;
        $edits[] = [$start, $end, $expr];
        $rewritten++;
    }

    if ($edits === [] && $needImports === []) {
        continue;
    }

    usort($edits, function ($a, $b) {
        return $b[0] - $a[0];
    });
    foreach ($edits as list($start, $end, $expr)) {
        $src = substr($src, 0, $start) . $expr . substr($src, $end);
    }

    if ($needImports !== []) {
        $src = addImports($src, array_keys($needImports));
    }

    $touched++;
    if (!$opts['dry-run']) {
        file_put_contents($file, $src);
    }
}

/**
 * Splice `use` lines into a file's existing import block, alphabetically.
 *
 * When the file has no import block yet the lines go after the namespace
 * declaration, separated by a blank line -- the shape every already-converted
 * file in packages/web/src carries.
 *
 * @param string $src   file contents
 * @param array  $fqcns fully qualified names to import
 *
 * @return string
 */
function addImports($src, array $fqcns)
{
    $lines = explode("\n", $src);
    $existing = [];
    $first = null;
    $last = null;
    $nsLine = null;
    foreach ($lines as $i => $line) {
        if ($nsLine === null && preg_match('/^namespace\s+/', $line)) {
            $nsLine = $i;
        }
        if (preg_match('/^use\s+([^;]+);/', $line, $m)
            && stripos($m[1], 'function ') !== 0
            && stripos($m[1], 'const ') !== 0
        ) {
            $existing[$i] = trim($m[1]);
            $first = $first ?? $i;
            $last = $i;
        }
    }
    $all = array_values($existing);
    foreach ($fqcns as $f) {
        $all[] = $f;
    }
    $all = array_unique($all);
    usort($all, 'strcasecmp');
    $block = array_map(function ($f) {
        return 'use ' . $f . ';';
    }, $all);

    if ($first !== null) {
        array_splice($lines, $first, $last - $first + 1, $block);
        return implode("\n", $lines);
    }
    if ($nsLine === null) {
        // No namespace and no existing import block: the caller is supposed
        // to have chosen fqcn style, so reaching here means a bug rather
        // than a file to guess at.
        fwrite(STDERR, "no place to add imports: " . implode(', ', $fqcns) . "\n");
        exit(2);
    }
    array_splice($lines, $nsLine + 1, 0, array_merge([''], $block));
    return implode("\n", $lines);
}

if (!$opts['quiet']) {
    foreach ($skipped as list($where, $why)) {
        echo "skip  $where  ($why)\n";
    }
}
foreach ($unresolved as list($where, $name)) {
    fwrite(STDERR, "UNRESOLVED  $where  '$name'\n");
}
printf(
    "%s%d site(s) rewritten across %d file(s); %d skipped; %d unresolved\n",
    $opts['dry-run'] ? '[dry-run] ' : '',
    $rewritten,
    $touched,
    count($skipped),
    count($unresolved)
);
exit($unresolved === [] ? 0 : 1);
