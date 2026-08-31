<?php
/**
 * A request value goes into a string function as a string, never as null.
 *
 * `filter_input()` answers **null** for a key that is not in the request --
 * not '' -- and `filter_var()` answers **false** for a value it rejects. PHP
 * 8.1 deprecated passing null to a non-nullable parameter of an internal
 * function, so `trim(filter_input(INPUT_POST, 'account'))` emits
 *
 *   Deprecated: trim(): Passing null to parameter #1 ($string) of type
 *   string is deprecated
 *
 * on every server running 8.1 or newer -- which is most of them. FOG buffers
 * its own output through `Initiator::sanitizeOutput()`, so a deprecation
 * raised mid-page is emitted into the response body ahead of whatever the
 * page was building, and on an AJAX endpoint that is a JSON reply the
 * browser cannot parse.
 *
 * The runtime VALUE is unaffected -- PHP still coerces the null to '' -- so
 * every one of these sites has always produced the right answer, which is
 * exactly why the shape spread to 153 call sites without anyone noticing.
 * The cast is not a behavior change; it is the same coercion written down,
 * which is the same argument GH-1245 made about `sql_mode`.
 *
 * IT IS NOT ALWAYS COSMETIC, and forum topic 18232 is the proof. On
 * dev-branch the tasking form read `account` with a bare `filter_input()`
 * and no trim at all, so the null was not coerced by anything -- it went
 * straight into `Group::createImagePackage()`'s batch insert and hit
 * `tasks`.`taskPassreset`, which is NOT NULL:
 *
 *   SQLSTATE[23000]: 1048 Column 'taskPassreset' cannot be null
 *
 * and no group could be tasked at all. Once the value escapes the string
 * function the coercion that was hiding the null is gone. That is the case
 * this test exists to make impossible to reintroduce.
 *
 * WHAT IS SCANNED. Core only. `packages/web/lib/plugins` is not repo source
 * -- it is the installed artifact of the fog-plugins release (ADR 0009), is
 * gitignored, and has its own repository and its own pull requests; a test
 * here cannot fix it and must not fail on it. `packages/web/vendor` is other
 * people's code.
 *
 * HOW. Tokens, not a regex. Nearly every site in this tree is written over
 * three lines --
 *
 *     $ip = trim(
 *         filter_input(INPUT_POST, 'ip')
 *     );
 *
 * -- so a line-oriented pattern sees none of them, and an argument in the
 * second position (`explode(',', filter_input(...))`) needs the enclosing
 * call to be tracked rather than the preceding characters. The walk keeps a
 * stack of enclosing calls, which answers both.
 *
 * Usage: php tests/request-input-is-cast-before-string-use.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);

/*
 * Functions whose relevant parameter is declared `string` and which FOG
 * actually calls on request data. Deliberately a list rather than
 * reflection over every internal function: the point is to name the ones in
 * use, so that adding a call to something else is a decision someone makes
 * here rather than a silent gap.
 *
 * str_replace() and preg_replace() accept array|string, but filter_input()
 * only returns an array when asked with FILTER_REQUIRE_ARRAY, which nothing
 * in core does -- and filter_input_array() is a different function this does
 * not match. So they belong on the list.
 */
$stringFns = array_flip(
    [
        'trim', 'ltrim', 'rtrim', 'strtolower', 'strtoupper', 'strlen',
        'substr', 'str_replace', 'preg_replace', 'preg_match', 'preg_split',
        'explode', 'ucfirst', 'ucwords', 'htmlspecialchars', 'htmlentities',
        'base64_decode', 'base64_encode', 'str_split', 'strrev', 'nl2br',
        'urldecode', 'urlencode', 'rawurldecode', 'md5', 'sha1', 'hash',
        'strip_tags', 'addslashes', 'str_pad', 'wordwrap', 'strpos',
        'stripos', 'strstr', 'str_contains', 'str_starts_with',
        'str_ends_with', 'strcmp', 'strcasecmp', 'implode', 'password_verify',
        'escapeshellarg', 'basename', 'dirname', 'pathinfo', 'mb_strlen',
        'mb_substr', 'mb_strtolower', 'mb_strtoupper', 'preg_quote',
        'strtotime', 'html_entity_decode', 'stripslashes', 'str_repeat',
        'substr_count', 'json_decode', 'number_format', 'str_word_count',
    ]
);

$paths = [
    '/packages/web/src',
    '/packages/web/commons',
    '/packages/web/api',
    '/packages/web/management',
    '/packages/web/client',
    '/packages/web/maintenance',
    '/packages/web/status',
    '/packages/web/lib/router',
    '/packages/service',
];

$casts = [
    T_STRING_CAST, T_INT_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_ARRAY_CAST,
];

$findings = [];
$scanned = 0;

foreach ($paths as $sub) {
    $dir = $root . $sub;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (preg_match('#/(vendor|plugins)/#', $path)) {
            continue;
        }
        $src = (string)file_get_contents($path);
        if (false === strpos($src, 'filter_input')
            && false === strpos($src, 'filter_var')
        ) {
            continue;
        }
        ++$scanned;
        $tokens = token_get_all($src);

        // Significant tokens only, so whitespace and comments cannot hide
        // the shape of a call.
        $sig = [];
        foreach ($tokens as $i => $t) {
            if (is_array($t)
                && in_array(
                    $t[0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )
            ) {
                continue;
            }
            $sig[] = $i;
        }

        $stack = [];
        foreach ($sig as $p => $i) {
            $t = $tokens[$i];
            $text = is_array($t) ? $t[1] : $t;

            if ('(' === $text) {
                // The name in front of the paren, when there is one, is the
                // call this argument list belongs to.
                $callee = null;
                if ($p >= 1) {
                    $prev = $tokens[$sig[$p - 1]];
                    if (is_array($prev) && T_STRING === $prev[0]) {
                        $callee = strtolower($prev[1]);
                    }
                }
                $stack[] = $callee;
                continue;
            }
            if (')' === $text) {
                array_pop($stack);
                continue;
            }
            if (!is_array($t) || T_STRING !== $t[0]) {
                continue;
            }
            $name = strtolower($t[1]);
            if ('filter_input' !== $name && 'filter_var' !== $name) {
                continue;
            }
            // A call, not a bare mention of the name.
            if (!isset($sig[$p + 1])) {
                continue;
            }
            $next = $tokens[$sig[$p + 1]];
            if ('(' !== (is_array($next) ? $next[1] : $next)) {
                continue;
            }
            // Guarded already.
            if ($p >= 1) {
                $prev = $tokens[$sig[$p - 1]];
                if (is_array($prev) && in_array($prev[0], $casts, true)) {
                    continue;
                }
            }
            $enclosing = end($stack);
            if (false === $enclosing || null === $enclosing) {
                continue;
            }
            if (!isset($stringFns[$enclosing])) {
                continue;
            }
            $findings[] = sprintf(
                '%s:%d  %s(%s(...))',
                ltrim(str_replace($root, '', $path), '/'),
                $t[2],
                $enclosing,
                $name
            );
        }
    }
}

/*
 * The scan finding nothing because it looked at nothing is the failure this
 * test could not otherwise report. Core reads request input in dozens of
 * files, so a run that opened none of them means the paths above are wrong.
 */
if ($scanned < 20) {
    fwrite(
        STDERR,
        sprintf(
            "FAIL: only %d files carry filter_input/filter_var -- the scan"
            . " paths are wrong, not the code\n",
            $scanned
        )
    );
    exit(1);
}

if (count($findings) > 0) {
    fwrite(
        STDERR,
        sprintf(
            "FAIL: %d request value(s) reach a string function uncast.\n"
            . "filter_input() returns null for an absent key; PHP 8.1+"
            . " deprecates that. Write (string) in front of it.\n",
            count($findings)
        )
    );
    foreach ($findings as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

printf("ok  %d files scanned, no uncast request input\n", $scanned);
exit(0);
