<?php
/**
 * A signed-out non-browser caller must be answered, not handed the form.
 *
 * management/index.php is shared by browsers and machines. When a session
 * has expired it used to render the sign-in FORM to both, at HTTP 200 with
 * Content-type: text/html. jQuery treats 200 as success, so $.apiCall ran
 * its SUCCESS handler with a string body, and $.notifyFromAPI -- finding
 * none of error/info/warning/msg in a string -- kept its 'success' default
 * and drew a GREEN toast reading "Bad Response". The write had been thrown
 * away and the UI said it worked.
 *
 * Reported against the plugin Update button, but nothing about that button
 * was involved: this branch is reached by every ?node= endpoint, so it was
 * every Save on every page, on every install, whenever a session lapsed.
 *
 * Two independent halves, and each one alone still leaves a lie on screen,
 * so both are pinned here:
 *
 * 1. index.php answers a non-browser caller with 401 and a JSON reason.
 * 2. fog.common.js treats a body it cannot read as a failure.
 *
 * Three mutations this exists to catch, all of which review clean:
 *
 * - Folding the two FOG_LOCAL_LOGIN guards together, or dropping the
 *   defined() test from the 401 arm. management/login.php defines that
 *   constant and then requires this file; it is the break-glass page that
 *   exists for when the identity provider is down, and answering it with
 *   JSON removes it on exactly the request shape it is there to serve.
 * - Moving the arm above the $browserNavigation assignment. The variable
 *   then reads null, !null is true, and EVERY signed-out caller -- browsers
 *   included -- gets 401 JSON instead of a login page. That is the whole UI
 *   gone, and it is one cut-and-paste away.
 * - Restoring `type = 'success'` as the declaration default in
 *   notifyFromAPI, or dropping the non-object guard back to `=== undefined`.
 *   Either one puts the green toast back with the server side still correct.
 *
 * This is inspection, not execution: reaching the branch for real needs a
 * bootstrapped session, a database and a rendered Page. The property being
 * pinned is where the arm SITS relative to the value it reads and the form
 * it replaces, which is a fact about the source.
 *
 * Usage: php tests/signed-out-xhr-gets-401-json.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$indexFile = 'packages/web/management/index.php';
$jsFile = 'packages/web/management/js/fog/fog.common.js';

/**
 * PHP source with comments stripped and whitespace collapsed.
 *
 * Comments go first, and it matters: index.php carries a long block naming
 * 401, application/json and $browserNavigation, so a test reading raw text
 * would be satisfied by its own documentation with the code deleted.
 *
 * @param string $file path to read
 *
 * @return string stripped source
 */
function strippedPhp($file)
{
    $out = '';
    foreach (token_get_all(file_get_contents($file)) as $c) {
        if (is_array($c)
            && in_array($c[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($c) ? $c[1] : $c;
    }
    return preg_replace('#\s+#', '', $out);
}

/**
 * Condition and body of the first `if` whose condition contains a needle.
 *
 * Brace counting is done on tokens rather than on the stripped string so a
 * brace inside a string literal cannot close the block early -- the
 * containment claims below are the whole test, and a containment claim
 * computed from a substring search is not one.
 *
 * @param string $file      path to read
 * @param string $condition text the condition must contain, stripped form
 *
 * @return array|null ['cond' => string, 'body' => string], or null
 */
function phpGuard($file, $condition)
{
    $pieces = [];
    foreach (token_get_all(file_get_contents($file)) as $c) {
        if (is_array($c)
            && in_array(
                $c[0],
                [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE],
                true
            )
        ) {
            continue;
        }
        $pieces[] = is_array($c) ? [$c[0], $c[1]] : [null, $c];
    }
    $n = count($pieces);
    for ($i = 0; $i < $n; $i++) {
        if (T_IF !== $pieces[$i][0]
            || $i + 1 >= $n
            || '(' !== $pieces[$i + 1][1]
        ) {
            continue;
        }
        $depth = 0;
        $cond = '';
        for ($j = $i + 1; $j < $n; $j++) {
            $tok = $pieces[$j][1];
            if (null === $pieces[$j][0] && '(' === $tok) {
                $depth++;
            } elseif (null === $pieces[$j][0] && ')' === $tok) {
                if (0 === --$depth) {
                    break;
                }
            }
            $cond .= $tok;
        }
        if (false === strpos($cond . ')', $condition)) {
            continue;
        }
        // A guard written without braces cannot contain anything, so a
        // single-statement rewrite reads as "not found" rather than
        // quietly passing with an empty body.
        if ($j + 1 >= $n || '{' !== $pieces[$j + 1][1]) {
            return null;
        }
        $depth = 0;
        $body = '';
        for ($k = $j + 1; $k < $n; $k++) {
            $type = $pieces[$k][0];
            $tok = $pieces[$k][1];
            if ((null === $type && '{' === $tok)
                || T_CURLY_OPEN === $type
                || T_DOLLAR_OPEN_CURLY_BRACES === $type
            ) {
                $depth++;
            } elseif (null === $type && '}' === $tok) {
                if (0 === --$depth) {
                    return ['cond' => $cond . ')', 'body' => $body];
                }
            }
            if ($depth > 0 && $k > $j + 1) {
                $body .= $tok;
            }
        }
        return null;
    }
    return null;
}

/*
 * 1. index.php answers a non-browser caller instead of rendering the form.
 *
 * `!$browserNavigation` is what makes this the 401 arm and not the redirect
 * guard above it, whose condition holds the same constant test but the
 * variable unnegated.
 */
$index = strippedPhp($indexFile);
$arm = phpGuard($indexFile, 'FOGCore::$ajax');
if (null === $arm) {
    $fails[] = $indexFile . ' no longer has a guard on FOGCore::$ajax, so'
        . ' a signed-out XHR is handed the sign-in form at HTTP 200 and every'
        . ' failed Save in the UI reports success';
} else {
    if (false === strpos($arm['cond'], "!defined('FOG_LOCAL_LOGIN')")) {
        $fails[] = 'the XHR 401 arm in ' . $indexFile . ' no'
            . ' longer exempts FOG_LOCAL_LOGIN, so management/login.php --'
            . ' the break-glass form for when the identity provider is'
            . ' down -- answers JSON to any client not asking for text/html';
    }
    if (false === strpos($arm['body'], 'HTTP_UNAUTHORIZED')
        && false === strpos($arm['body'], '401')
    ) {
        $fails[] = 'the XHR 401 arm in ' . $indexFile . ' no'
            . ' longer sets 401, and a status jQuery reads as success is'
            . ' the whole bug however good the body is';
    }
    if (false === strpos($arm['body'], 'application/json')) {
        $fails[] = 'the XHR 401 arm in ' . $indexFile . ' no'
            . ' longer declares application/json, so jQuery hands the'
            . ' caller a string and notifyFromAPI has nothing to read';
    }
    if (false === strpos($arm['body'], 'exit;')) {
        $fails[] = 'the XHR 401 arm in ' . $indexFile . ' does'
            . ' not stop, so the login form is appended to the JSON body';
    }
    /*
     * And it must stay NARROW. Widening it to !$browserNavigation -- which
     * looks like the more thorough answer, and which is what shipped first --
     * answers 401 to every caller that is not a document navigation. That
     * includes checkWebTier() in lib/common/functions.sh, which probes
     * ?node=schema with a tokenless GET and curl -fL purely to prove the web
     * tier renders. It took the 401, saw zero bytes and aborted the install
     * with "Checking web server serves FOG...Failed!" on three healthy
     * servers. Monitoring and the recovery curl this installer prints on
     * failure are the same shape and equally unenumerable from here.
     */
    if (false !== strpos($arm['cond'], 'browserNavigation')) {
        $fails[] = 'the XHR 401 arm in ' . $indexFile . ' is gated on'
            . ' $browserNavigation, so every non-browser caller gets 401 --'
            . ' including the installer\'s own liveness probe, which then'
            . ' aborts the install with "Checking web server serves'
            . ' FOG...Failed!" on a server that is working perfectly';
    }
    $armAt = strpos($index, $arm['cond']);
    /*
     * And it has to come before the thing it is replacing. After the form
     * is echoed the headers are already committed.
     */
    $formAt = strpos($index, 'echo$login;');
    if (false !== $formAt && false !== $armAt && $armAt > $formAt) {
        $fails[] = $indexFile . ' renders the login form before the 401'
            . ' arm runs, so the XHR still receives the form';
    }
}

/*
 * 2. notifyFromAPI treats a body it cannot read as a failure.
 */
$js = file_get_contents($jsFile);
$fnAt = strpos($js, '$.notifyFromAPI = function');
if (false === $fnAt) {
    $fails[] = $jsFile . ' no longer defines $.notifyFromAPI; if it moved,'
        . ' this half of the test is looking at the wrong code';
} else {
    /*
     * Slice the function out, tracking quotes so a brace inside a string
     * cannot close it early, and dropping comments so the prose in this
     * function -- which names both 'success' and === undefined -- cannot
     * satisfy or fail a claim about the code.
     */
    $body = '';
    $depth = 0;
    $started = false;
    $len = strlen($js);
    for ($i = $fnAt; $i < $len; $i++) {
        $ch = $js[$i];
        $two = substr($js, $i, 2);
        if ('//' === $two) {
            $i = strpos($js, "\n", $i);
            if (false === $i) {
                break;
            }
            continue;
        }
        if ('/*' === $two) {
            $end = strpos($js, '*/', $i + 2);
            if (false === $end) {
                break;
            }
            $i = $end + 1;
            continue;
        }
        if ("'" === $ch || '"' === $ch || '`' === $ch) {
            $quote = $ch;
            $body .= $ch;
            for ($i++; $i < $len; $i++) {
                $body .= $js[$i];
                if ('\\' === $js[$i]) {
                    $body .= $js[++$i];
                    continue;
                }
                if ($quote === $js[$i]) {
                    break;
                }
            }
            continue;
        }
        $body .= $ch;
        if ('{' === $ch) {
            $depth++;
            $started = true;
        } elseif ('}' === $ch) {
            if ($started && 0 === --$depth) {
                break;
            }
        }
    }
    $body = str_replace('"', "'", preg_replace('#\s+#', '', $body));
    /*
     * The guard has to reject any non-object. `=== undefined` is the
     * version that shipped the bug: a STRING body -- which is what an HTML
     * answer arrives as -- walks straight past it.
     */
    if (false === strpos($body, "typeofres!=='object'")) {
        $fails[] = 'notifyFromAPI in ' . $jsFile . ' no longer tests that'
            . ' the body is an object, so a string body (an endpoint'
            . ' answering with HTML) reaches the lookups below it and'
            . ' every one of them is undefined';
    }
    /*
     * The declaration default. Each branch overrides it, so this only
     * decides what a response carrying none of the four keys renders as --
     * which is precisely the case that has nothing to say and must not be
     * green.
     */
    $declAt = strpos($body, 'vartitle=');
    if (false === $declAt) {
        $fails[] = 'notifyFromAPI in ' . $jsFile . ' no longer declares its'
            . ' locals in one var statement; this test cannot see the'
            . ' default for `type` any more';
    } else {
        $end = strpos($body, ';', $declAt);
        $decl = false === $end
            ? substr($body, $declAt)
            : substr($body, $declAt, $end - $declAt);
        if (false === strpos($decl, "type='error'")) {
            $fails[] = 'notifyFromAPI in ' . $jsFile . ' no longer defaults'
                . ' `type` to error, so a response nothing could be read'
                . ' out of draws a green toast saying it worked';
        }
        /*
         * `msg` was assigned by every branch as an implicit global and read
         * by `if (!msg)`, so the fallback that exists for a message-less
         * body threw ReferenceError instead of running.
         */
        if (false === strpos($decl, ',msg')) {
            $fails[] = 'notifyFromAPI in ' . $jsFile . ' no longer declares'
                . ' `msg`, so the fallback that reads it throws'
                . ' ReferenceError out of the success handler and takes the'
                . ' caller\'s callback with it';
        }
    }
}

if (count($fails) > 0) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo '  - ' . $f . "\n";
    }
    exit(1);
}
echo "ok: a signed-out XHR is answered 401 JSON and rendered as an error\n";
exit(0);
