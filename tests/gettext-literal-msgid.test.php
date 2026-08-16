<?php
/**
 * A gettext msgid must be a literal, not a sentence built at runtime.
 *
 * xgettext extracts the LITERAL SOURCE TEXT of a _() argument. PHP evaluates
 * that argument before _() is ever called. So when the two differ, the msgid
 * in the catalog and the string looked up at runtime are different strings,
 * and the lookup can never hit:
 *
 *     echo _("MACs $msg successfully");
 *
 *     msgid in messages.pot:  "MACs $msg successfully"
 *     looked up at runtime:   "MACs approved successfully"
 *
 * gettext misses, returns its argument unchanged, and the line renders in
 * English on every install regardless of the selected language. Nothing
 * errors and nothing logs -- the only symptom is a string that is never
 * translated, which is invisible to anyone testing in English. It also
 * leaves translators no way to inflect the sentence around the variable,
 * which several of the shipped languages need.
 *
 * WHAT THIS FORBIDS: mixing literal text with a variable in one msgid.
 *   _("Add selected $type")           -> sprintf(_('Add selected %s'), $type)
 *   _('Remove ' . $type)              -> sprintf(_('Remove %s'), $type)
 *   _(sprintf('Invalid %s', $var))    -> sprintf(_('Invalid %s'), $var)
 *
 * The fix is always the same shape: the literal goes INSIDE _(), the variable
 * is substituted OUTSIDE it. That gives xgettext a complete format string to
 * extract and gives the translator a placeholder they can move.
 *
 * WHAT THIS ALLOWS, deliberately: a bare runtime lookup with no literal text
 * of its own, e.g. _($node) or _(ucwords($report)). That is a different and
 * much weaker problem -- it resolves correctly whenever the VALUE is itself a
 * msgid extracted from some other literal site, which is how FOG translates
 * class and node names. xgettext cannot verify that, so it stays fragile, but
 * it is not broken by construction the way the mixed form is. Drawing the
 * line here needs no allowlist: an argument either carries literal text or it
 * does not.
 *
 * Usage: php tests/gettext-literal-msgid.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        return '' !== $f
            && is_readable($f)
            && 0 !== strpos($f, 'packages/web/vendor/');
    }
);

if (count($files) < 100) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($files) . " files scanned; expected the whole "
        . "tree. Is this a git checkout?\n"
    );
    exit(1);
}

/**
 * Collect the tokens of a call's first argument.
 *
 * @param array $t     full token stream
 * @param int   $open  index of the opening parenthesis
 *
 * @return array tokens between the parens, stopping at a depth-1 comma
 */
function firstArgument(array $t, $open)
{
    $depth = 0;
    $arg = [];
    for ($k = $open, $n = count($t); $k < $n; $k++) {
        $c = $t[$k];
        if (!is_array($c)) {
            if ('(' === $c || '[' === $c || '{' === $c) {
                $depth++;
            } elseif (')' === $c || ']' === $c || '}' === $c) {
                if (0 === --$depth) {
                    break;
                }
            } elseif (',' === $c && 1 === $depth) {
                break;
            }
        }
        if ($depth >= 1 && $k > $open) {
            $arg[] = $c;
        }
    }
    return $arg;
}

$hits = [];
foreach ($files as $file) {
    $t = token_get_all(file_get_contents($file));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        $tok = $t[$i];
        if (!is_array($tok) || T_STRING !== $tok[0]) {
            continue;
        }
        if ('_' !== $tok[1] && 'gettext' !== $tok[1]) {
            continue;
        }
        /*
         * Skip anything that is not a plain call: ->_(, ::_(, function _(.
         * A method happening to be named _ is not gettext.
         */
        $p = $i - 1;
        while ($p >= 0 && is_array($t[$p])
            && in_array($t[$p][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ) {
            $p--;
        }
        if ($p >= 0 && is_array($t[$p]) && in_array(
            $t[$p][0],
            [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW],
            true
        )) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) {
            $j++;
        }
        if ($j >= $n || '(' !== $t[$j]) {
            continue;
        }

        $arg = firstArgument($t, $j);
        // Leading whitespace is real -- _( sprintf(...) ) is written that way
        // in the tree -- so find the first token that is not whitespace and
        // compare against that, not against index 0.
        $lead = null;
        foreach ($arg as $idx => $a) {
            if (!is_array($a) || T_WHITESPACE !== $a[0]) {
                $lead = $idx;
                break;
            }
        }
        $kind = null;
        $depth = 0;
        foreach ($arg as $idx => $a) {
            if (!is_array($a)) {
                if ('(' === $a || '[' === $a) {
                    $depth++;
                    continue;
                }
                if (')' === $a || ']' === $a) {
                    $depth--;
                    continue;
                }
                /*
                 * A double-quoted string that interpolates is tokenized as a
                 * bare '"' delimiter with parts between; one that does not is
                 * a single T_CONSTANT_ENCAPSED_STRING. So a bare '"' here IS
                 * the interpolation.
                 */
                if ('"' === $a) {
                    $kind = 'interpolated string';
                    break;
                }
                // Concatenation, but only when a variable is one of the
                // operands -- xgettext folds 'a' . 'b' into one msgid fine.
                if ('.' === $a && 0 === $depth) {
                    foreach ($arg as $b) {
                        if (is_array($b) && T_VARIABLE === $b[0]) {
                            $kind = 'concatenated with a variable';
                            break 2;
                        }
                    }
                }
                continue;
            }
            if (T_START_HEREDOC === $a[0]) {
                $kind = 'heredoc';
                break;
            }
            /*
             * sprintf() INSIDE _() is the same bug wearing a placeholder: the
             * format string is the msgid and it has to be the _() argument,
             * not sprintf's. Only flag the outermost call, i.e. sprintf as
             * the whole argument.
             */
            if (T_STRING === $a[0] && 0 === $depth && $idx === $lead
                && in_array(strtolower($a[1]), ['sprintf', 'vsprintf'], true)
            ) {
                $kind = 'sprintf inside _()';
                break;
            }
        }
        if (null === $kind) {
            continue;
        }
        $src = '';
        foreach ($arg as $a) {
            $src .= is_array($a) ? $a[1] : $a;
        }
        $src = trim(preg_replace('/\s+/', ' ', $src));
        if (strlen($src) > 70) {
            $src = substr($src, 0, 67) . '...';
        }
        $hits[] = sprintf('%s:%d  [%s]  _(%s)', $file, $tok[2], $kind, $src);
    }
}

if (count($hits) > 0) {
    fwrite(
        STDERR,
        'FAIL: ' . count($hits) . " gettext msgid(s) built at runtime, so the "
        . "extracted\nmsgid can never match the string looked up:\n"
    );
    foreach ($hits as $hit) {
        fwrite(STDERR, "  $hit\n");
    }
    fwrite(
        STDERR,
        "\nPut the literal inside _() and substitute outside it:\n"
        . "  sprintf(_('Add selected %s'), \$type)\n"
    );
    exit(1);
}

printf("ok: %d file(s), every gettext msgid is a literal\n", count($files));
exit(0);
