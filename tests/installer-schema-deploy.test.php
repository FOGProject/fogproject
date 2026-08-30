<?php
/**
 * The installer's schema deploy must never be answered with a redirect to
 * an identity provider.
 *
 * updateDB() (lib/common/functions.sh) POSTs to
 * management/index.php?node=schema carrying X-Fog-Install-Token, follows
 * redirects, and reports "Updating Database...Failed!" on any status at or
 * above 400. It is a machine: it cannot sign in at a provider, and it is
 * running at the one moment in a FOG install when the web root has already
 * been replaced.
 *
 * Two things went wrong at once on 2026-08-18, and each is enough on its
 * own, so both are pinned here.
 *
 * 1. A schema that is already current bounced the installer's POST to
 *    index.php with the query string gone. That landed on the anonymous
 *    branch, which a forced-redirect plugin (fog-plugins#17) then sent to
 *    the provider. The installer POSTed there, got 501, and reported a
 *    database failure on a database that was fine. The fix is a gate of the
 *    same class as FOG_WANTS_SESSION: an entry point shared by browsers and
 *    machines offers a browser-only answer only to a browser.
 *
 * 2. The bounce itself. The installer reads nothing but the status, so
 *    "your schema is already current" was being signalled by whatever the
 *    dashboard happened to do with an anonymous visitor -- which is not a
 *    signal at all, and which is why an unrelated plugin setting could
 *    break the installer. A caller holding the install token now gets the
 *    answer directly.
 *
 * Both failures are invisible on an install without such a plugin, and both
 * surface only during an upgrade, on somebody else's server.
 *
 * This is inspection, not execution: reaching either branch for real needs
 * a bootstrapped session, a database and a rendered Page. The properties
 * being pinned are where these tests SIT relative to their guards, which
 * are facts about the source.
 *
 * Usage: php tests/installer-schema-deploy.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$indexFile = 'packages/web/management/index.php';
$schemaFile = 'packages/web/src/Pages/SchemaUpdaterPage.php';

/**
 * Source text with comments and whitespace stripped.
 *
 * Comments go first, and it matters here: both files carry long prose
 * naming REQUEST_METHOD, text/html and validSchemaBootstrap(), so a test
 * reading raw text would be satisfied by its own documentation with the
 * code deleted.
 *
 * @param string $file path to read
 *
 * @return string stripped source
 */
function strippedSource($file)
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
 * brace inside a string literal or an interpolation cannot close the block
 * early -- the containment claims below are the whole test, and a
 * containment claim computed from a substring search is not one.
 *
 * @param string $file      path to read
 * @param string $condition text the condition must contain, stripped form
 *
 * @return array|null ['cond' => string, 'body' => string], or null
 */
function guardParts($file, $condition)
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
        if (T_IF !== $pieces[$i][0]) {
            continue;
        }
        if ($i + 1 >= $n || '(' !== $pieces[$i + 1][1]) {
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
        // A guard written without braces cannot contain anything, so an
        // alternative-syntax or single-statement rewrite reads as "not
        // found" rather than quietly passing with an empty body.
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
 * 1. index.php only offers LOGIN_PAGE_REDIRECT to a browser navigation.
 */
$index = strippedSource($indexFile);
$guard = guardParts($indexFile, "!defined('FOG_LOCAL_LOGIN')");
if (null === $guard) {
    $fails[] = 'index.php has no FOG_LOCAL_LOGIN guard around'
        . ' LOGIN_PAGE_REDIRECT; tests/local-login-entrypoint.test.php'
        . ' explains why that alone is a lockout';
} else {
    /*
     * The gate has to be part of the guard's own condition. Computing
     * $browserNavigation and then not testing it is the mutation that
     * reviews clean, and it restores the exact failure this file is named
     * after.
     */
    if (false === strpos($guard['cond'], '$browserNavigation')) {
        $fails[] = 'the FOG_LOCAL_LOGIN guard in index.php no longer tests'
            . ' $browserNavigation, so the installer\'s schema POST is'
            . ' redirected to the identity provider again and installfog.sh'
            . ' reports "Updating Database...Failed!" on a healthy database';
    }
    $at = strpos($index, '$browserNavigation=');
    if (false === $at) {
        $fails[] = 'index.php no longer computes $browserNavigation';
    } else {
        $end = strpos($index, ';', $at);
        $expr = false === $end
            ? substr($index, $at)
            : substr($index, $at, $end - $at);
        /*
         * A document navigation is a GET that asks for text/html. Both
         * halves matter and each fails differently: without the method
         * test a POST whose body cannot survive the round trip is still
         * sent away, and without the Accept test every non-browser client
         * -- the installer, fetch/XHR, the fog-client -- is still sent to
         * a provider it cannot use.
         */
        if (false === strpos($expr, "'GET'")
            || false === strpos($expr, 'REQUEST_METHOD')
        ) {
            $fails[] = '$browserNavigation in index.php no longer requires'
                . ' the request to be a GET';
        }
        if (false === strpos($expr, 'HTTP_ACCEPT')
            || false === strpos($expr, "'text/html'")
        ) {
            $fails[] = '$browserNavigation in index.php no longer requires'
                . ' the caller to ask for text/html, which is the only'
                . ' thing separating a browser from the installer\'s curl';
        }
        if (false !== strpos($expr, '||')) {
            $fails[] = '$browserNavigation in index.php combines its two'
                . ' tests with ||; either one alone admits the installer';
        }
        if ($at > strpos($index, $guard['cond'])) {
            $fails[] = 'index.php computes $browserNavigation after the'
                . ' guard that reads it, so the guard reads null';
        }
    }
}

/*
 * 2. The schema page answers a token-holding caller instead of bouncing it.
 */
$schema = strippedSource($schemaFile);
$done = guardParts($schemaFile, ">=FOG_SCHEMA");
if (null === $done) {
    $fails[] = $schemaFile . ' no longer has an "already current" guard;'
        . ' if that check moved, this whole test is looking at the wrong'
        . ' code';
} else {
    $tokenAt = strpos($done['body'], 'validSchemaBootstrap()');
    $redirectAt = strpos($done['body'], 'self::redirect(');
    if (false === $tokenAt) {
        $fails[] = $schemaFile . ' no longer checks validSchemaBootstrap()'
            . ' before deciding what to do with an already-current schema,'
            . ' so the installer is bounced to a page whose behavior'
            . ' belongs to whatever plugins are installed';
    }
    if (false === $redirectAt) {
        $fails[] = $schemaFile . ' no longer redirects a browser away from'
            . ' an already-current schema';
    }
    if (false !== $tokenAt && false !== $redirectAt
        && $tokenAt > $redirectAt
    ) {
        $fails[] = $schemaFile . ' checks validSchemaBootstrap() after the'
            . ' redirect, which has already sent the installer away';
    }
    /*
     * And it must actually stop. Answering and then falling through to the
     * redirect sends the installer to the provider with a 200-shaped
     * intention -- the redirect wins, because it is what reaches the wire.
     */
    if (false !== $tokenAt
        && false === strpos(
            substr($done['body'], $tokenAt),
            'exit;'
        )
    ) {
        $fails[] = $schemaFile . ' answers the install token without'
            . ' stopping, so the redirect below still runs';
    }
}

if (count($fails) > 0) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo '  - ' . $f . "\n";
    }
    exit(1);
}
echo "ok: the installer's schema deploy cannot be routed to a provider\n";
exit(0);
