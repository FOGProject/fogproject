<?php
/**
 * Prefixes unqualified references to PHP's built-in classes with a backslash.
 *
 * Phase 0.1 of the namespace work. Inside `namespace FOG;` an unqualified
 * `catch (Exception $e)` means `FOG\Exception`, a class that does not exist,
 * so the catch silently stops matching -- it compiles, it lints, it passes
 * every static check, and it fails only on the error path, only at runtime, on
 * somebody's server. Writing `\Exception` now, while the whole tree is still
 * in the global namespace and the two spellings are identical, turns that from
 * a Phase 3 risk into a Phase 3 non-event.
 *
 * Membership of "built-in" is decided by ReflectionClass::isInternal() on the
 * running PHP rather than by a list kept here, so a class nobody thought of is
 * caught instead of missed. Run it on a PHP whose extension set matches the
 * server's; a class from an extension you do not have loaded is invisible to
 * this tool.
 *
 * Usage:
 *   php bin/prefix-global-classes.php --check [path...]   list, exit 1 if any
 *   php bin/prefix-global-classes.php --fix   [path...]   rewrite in place
 *
 * With no paths it walks every tracked PHP file. --fix is idempotent: an
 * already-prefixed name lexes as T_NAME_FULLY_QUALIFIED, a different token,
 * so a second run finds nothing to do.
 *
 * @category Tooling
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Third-party source. Composer owns these files and rewrites them on every
 * install, so a prefix here would be reverted and the diff would never settle
 * -- the same reason psrfix() and the gettext sweep skip the directory.
 */
const SKIP_PREFIXES = [
    'packages/web/vendor/',
];

/**
 * Collects the files to consider.
 *
 * Selection is by content rather than by extension on purpose: the nine daemon
 * entry points under packages/service/ have no extension and open with a
 * shebang before their `<?php`, and a namespace declaration in one of those
 * would break just as loudly as one anywhere else.
 *
 * @param string $root  Repository root.
 * @param array  $paths Optional explicit paths.
 *
 * @return array
 */
function collectFiles($root, array $paths)
{
    $candidates = [];
    if (count($paths) > 0) {
        foreach ($paths as $p) {
            if (is_dir($p)) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $f) {
                    $candidates[] = $f->getPathname();
                }
                continue;
            }
            $candidates[] = $p;
        }
    } else {
        // --cached --others --exclude-standard, not a bare ls-files: a file
        // that is written but not yet `git add`ed is exactly the one being
        // checked, and a bare listing cannot see it. That gap let a new test
        // file ship a bare ReflectionClass -- green locally, red the moment
        // the merge made it tracked. --exclude-standard keeps .gitignore
        // honoured, so vendor/ and lib/plugins stay out.
        $out = [];
        exec(
            'git -C ' . escapeshellarg($root)
            . ' ls-files --cached --others --exclude-standard',
            $out
        );
        foreach ($out as $rel) {
            $candidates[] = $root . DIRECTORY_SEPARATOR . $rel;
        }
    }

    $files = [];
    foreach ($candidates as $f) {
        if (!is_file($f)) {
            continue;
        }
        $rel = ltrim(str_replace($root, '', $f), DIRECTORY_SEPARATOR);
        foreach (SKIP_PREFIXES as $skip) {
            if (strpos($rel, $skip) === 0) {
                continue 2;
            }
        }
        $head = (string)@file_get_contents($f, false, null, 0, 512);
        if (strpos($head, '<?php') === false) {
            continue;
        }
        $files[] = $f;
    }
    sort($files);
    return $files;
}

/**
 * Finds the token offsets of bare built-in class references in one file.
 *
 * A T_STRING is a class reference when it sits in one of the positions PHP
 * resolves as a class name: after `new`, before `::`, after `instanceof`,
 * after `extends`, or inside a catch list. Method calls, declarations and
 * already-qualified names are all excluded -- which is the whole reason this
 * is a tokenizer and not a regex. "Exception" also appears in docblocks
 * (@throws Exception), in translated strings, in method names and inside
 * SlackException / UploadException / PushbulletException, and only the
 * tokenizer knows the difference between a class-reference position and a
 * substring.
 *
 * @param array $tokens token_get_all() output.
 *
 * @return array Token indices to prefix.
 */
function findRefs(array $tokens)
{
    $n = count($tokens);

    // Index of the significant tokens, so "the previous token" means the
    // previous real one rather than a run of whitespace.
    $ix = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t)
            && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $ix[] = $i;
    }
    $count = count($ix);

    $hits = [];
    for ($k = 0; $k < $count; $k++) {
        $i = $ix[$k];
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING) {
            continue;
        }

        $prev = $k > 0 ? $tokens[$ix[$k - 1]] : null;
        $next = $k + 1 < $count ? $tokens[$ix[$k + 1]] : null;
        $pv = is_array($prev) ? $prev[0] : $prev;
        $nv = is_array($next) ? $next[0] : $next;

        // Already qualified, or one segment of a longer name.
        if ($pv === T_NS_SEPARATOR || $nv === T_NS_SEPARATOR) {
            continue;
        }
        // A method call, a property fetch, or the name being declared.
        if ($pv === T_OBJECT_OPERATOR
            || $pv === T_NULLSAFE_OBJECT_OPERATOR
            || $pv === T_FUNCTION
            || $pv === T_CONST
            || $pv === T_CLASS
            || $pv === T_INTERFACE
            || $pv === T_TRAIT
        ) {
            continue;
        }

        $isRef = ($pv === T_NEW)
            || ($nv === T_DOUBLE_COLON)
            || ($pv === T_INSTANCEOF)
            || ($pv === T_EXTENDS);

        // catch (X ... and catch (X | Y ...
        if (!$isRef && ($pv === '(' || $pv === '|')) {
            for ($b = $k - 1; $b >= 0 && $b > $k - 6; $b--) {
                $bt = $tokens[$ix[$b]];
                if (is_array($bt) && $bt[0] === T_CATCH) {
                    $isRef = true;
                    break;
                }
                if ($bt === ')' || $bt === ';' || $bt === '{') {
                    break;
                }
            }
        }
        if (!$isRef) {
            continue;
        }

        // The runtime decides, not a list kept here.
        try {
            $ref = new \ReflectionClass($t[1]);
        } catch (\Throwable $e) {
            continue;
        }
        if (!$ref->isInternal()) {
            continue;
        }
        $hits[] = $i;
    }
    return $hits;
}

/**
 * Rebuilds a file's source with the separator inserted.
 *
 * @param array $tokens token_get_all() output.
 * @param array $hits   Token indices to prefix.
 *
 * @return string
 */
function applyPrefix(array $tokens, array $hits)
{
    $set = array_flip($hits);
    $out = '';
    foreach ($tokens as $i => $t) {
        $text = is_array($t) ? $t[1] : $t;
        $out .= isset($set[$i]) ? '\\' . $text : $text;
    }
    return $out;
}

// ---- main ----------------------------------------------------------------

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$mode = null;
$paths = [];
foreach ($args as $a) {
    if ($a === '--check' || $a === '--fix') {
        $mode = $a;
        continue;
    }
    $paths[] = $a;
}

if ($mode === null) {
    fwrite(
        STDERR,
        "usage: php bin/prefix-global-classes.php --check|--fix [path...]\n"
    );
    exit(2);
}

$files = collectFiles($root, $paths);
$totalSites = 0;
$totalFiles = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);
    $tokens = token_get_all($src);
    $hits = findRefs($tokens);
    if (count($hits) === 0) {
        continue;
    }
    $totalFiles++;
    $totalSites += count($hits);
    $rel = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);

    if ($mode === '--check') {
        foreach ($hits as $i) {
            printf("%s:%d: %s\n", $rel, $tokens[$i][2], $tokens[$i][1]);
        }
        continue;
    }

    file_put_contents($file, applyPrefix($tokens, $hits));
    printf("%s: %d\n", $rel, count($hits));
}

printf(
    "\n%s: %d sites in %d files (of %d scanned)\n",
    $mode === '--check' ? 'unprefixed' : 'prefixed',
    $totalSites,
    $totalFiles,
    count($files)
);

exit(($mode === '--check' && $totalSites > 0) ? 1 : 0);
