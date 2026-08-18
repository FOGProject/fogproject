<?php
/**
 * The local login page must never be routable to an identity provider.
 *
 * An install can be configured to send every anonymous visitor straight to
 * an external provider (fog-plugins#17), and that is a setting which can
 * lock every administrator out of their own server: provider down, expired
 * certificate, broken discovery, mistyped issuer. A browser then has no way
 * back in -- including for the local break-glass account that exists for
 * exactly that failure, and which tests/break-glass-auth-sources.test.php
 * spends its whole length keeping alive.
 *
 * management/login.php is the way back: FOG's own login form at one URL
 * that always renders it, the equivalent of ServiceNow's login.do.
 *
 * What makes that a guarantee rather than a promise is STRUCTURAL, and it
 * is the only thing here worth pinning. index.php offers the
 * LOGIN_PAGE_REDIRECT hook only when FOG_LOCAL_LOGIN is undefined, so on
 * login.php a redirect listener is never reached -- not consulted and
 * overruled, not asked politely. A plugin cannot opt back in, and a plugin
 * that is half-installed or throwing cannot take this page down with it,
 * because it is not asked anything.
 *
 * Every way that breaks is silent. Moving the processEvent call one line
 * out of its guard, dropping the reference from the hook argument, letting
 * an unvalidated string reach a Location header, or reformatting login.php
 * into its own copy of the form -- all four leave a login page that still
 * renders and still logs people in, on every install that has no such
 * plugin. The failure only appears on the install that needed the escape
 * hatch, at the moment it needed it.
 *
 * This is inspection, not execution: reaching index.php's login branch for
 * real needs a bootstrapped session, a database and a rendered Page. The
 * property being pinned is where the hook call SITS relative to the guard,
 * which is a fact about the source.
 *
 * Usage: php tests/local-login-entrypoint.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$indexFile = 'packages/web/management/index.php';
$loginFile = 'packages/web/management/login.php';

/**
 * Source text with comments and whitespace stripped.
 *
 * Comments go first, and it matters more here than usual: both files carry
 * long prose naming FOG_LOCAL_LOGIN, LOGIN_PAGE_REDIRECT and the redirect
 * itself, so a test reading raw text would be satisfied by its own
 * documentation with the code deleted.
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
 * Body of the first `if` whose condition contains a needle.
 *
 * Brace counting is done on tokens rather than on the stripped string so a
 * brace inside a string literal or an interpolation cannot close the block
 * early -- the containment claim below is the whole test, and a containment
 * claim computed from a substring search is not one.
 *
 * @param string $file      path to read
 * @param string $condition text the condition must contain, stripped form
 *
 * @return string|null stripped body between the braces, or null
 */
function guardBody($file, $condition)
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
        $pieces[] = is_array($c)
            ? [$c[0], $c[1]]
            : [null, $c];
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
                    return $body;
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
 * 1. The page exists at all, and is a real entry point rather than a
 *    redirect or an alias somebody could point elsewhere.
 */
if (!is_file($loginFile)) {
    $fails[] = $loginFile . ' is missing; a forced-redirect install has no'
        . ' way back to FOG\'s own login form';
} else {
    $login = strippedSource($loginFile);

    /*
     * The constant is what index.php reads, so it has to be defined BEFORE
     * index.php runs. Defining it afterwards is the mutation that looks
     * entirely fine in review and does nothing whatsoever.
     */
    $defineAt = strpos($login, "define('FOG_LOCAL_LOGIN',true);");
    $requireAt = strpos($login, "require__DIR__");
    if (false === $defineAt) {
        $fails[] = 'management/login.php no longer defines FOG_LOCAL_LOGIN;'
            . ' index.php would offer LOGIN_PAGE_REDIRECT here like anywhere'
            . ' else and the break-glass page would redirect to the provider'
            . ' that is broken';
    }
    if (false === $requireAt) {
        $fails[] = 'management/login.php no longer requires index.php';
    }
    if (false !== $defineAt && false !== $requireAt
        && $defineAt > $requireAt
    ) {
        $fails[] = 'management/login.php defines FOG_LOCAL_LOGIN after'
            . ' loading index.php, which is after index.php has already'
            . ' decided whether to redirect';
    }
    if (false === strpos($login, "'index.php'")) {
        $fails[] = 'management/login.php no longer loads index.php by name';
    }

    /*
     * And it stays a two-liner. The moment this file grows its own form or
     * its own credential handling it becomes a second authentication path,
     * which is the one thing a break-glass page must not be -- a bug fixed
     * on the normal login would not be fixed on the emergency one, and
     * nobody exercises the emergency one until the day it has to work.
     */
    foreach (['uname', 'upass', '<form', 'ProcessLogin'] as $marker) {
        if (false !== stripos($login, $marker)) {
            $fails[] = 'management/login.php carries its own login markup or'
                . ' handling (' . $marker . '); it must reuse index.php so'
                . ' the emergency form cannot drift from the real one';
        }
    }
}

/*
 * 2. The hook, and where it sits.
 *
 * Presence is not the property. LOGIN_PAGE_REDIRECT firing is fine; it
 * firing on login.php is the lockout. So the call has to be found INSIDE
 * the guard's braces, not merely somewhere after the word "defined".
 */
$index = strippedSource($indexFile);
$body = guardBody($indexFile, "!defined('FOG_LOCAL_LOGIN')");
if (null === $body) {
    if (false !== strpos($index, "'LOGIN_PAGE_REDIRECT'")) {
        $fails[] = 'index.php fires LOGIN_PAGE_REDIRECT but no longer wraps'
            . ' it in an if (!defined(\'FOG_LOCAL_LOGIN\')) guard, so'
            . ' management/login.php redirects to the identity provider like'
            . ' every other page';
    } else {
        $fails[] = 'index.php has no FOG_LOCAL_LOGIN guard';
    }
} else {
    if (false === strpos($body, "processEvent('LOGIN_PAGE_REDIRECT'")) {
        $fails[] = 'the FOG_LOCAL_LOGIN guard in index.php no longer'
            . ' contains the LOGIN_PAGE_REDIRECT call; if the call moved out'
            . ' of the guard the break-glass page redirects with it';
    }
    /*
     * By reference, or the listener writes into a copy and every install
     * with a forced-redirect plugin silently keeps showing the local form
     * -- the failure in the harmless direction, which is exactly why it
     * would ship.
     */
    if (false === strpos($body, "['redirect'=>&\$loginRedirect]")) {
        $fails[] = 'index.php no longer passes $loginRedirect by reference'
            . ' to LOGIN_PAGE_REDIRECT; a listener cannot return anything'
            . ' any other way';
    }
    /*
     * What a hook put in a variable is not a good enough answer for a
     * Location header. Absolute http(s) only.
     */
    if (false === strpos($body, "preg_match('#^https?://#i',\$loginRedirect)")
    ) {
        $fails[] = 'index.php no longer validates the LOGIN_PAGE_REDIRECT'
            . ' value as an absolute http(s) URL before putting it in a'
            . ' Location header';
    }
    /*
     * 302, not redirect()'s cacheable 308 default. A browser that cached
     * the login page as permanently moved to a provider which has since
     * been switched off would have no way back -- the same lockout this
     * whole file exists to prevent, arriving through the cache instead.
     */
    if (false === strpos($body, 'FOGCore::redirect($loginRedirect,302);')) {
        $fails[] = 'index.php no longer redirects with a temporary 302; a'
            . ' cached permanent redirect to a dead provider is its own'
            . ' lockout';
    }
    if (false === strpos($body, "\$loginRedirect='';")) {
        $fails[] = 'index.php no longer initialises $loginRedirect, so an'
            . ' install with no listener redirects on whatever was left in'
            . ' scope';
    }
}

/*
 * 3. One guard, one hook site. A second unguarded processEvent for the same
 *    event -- added later for the API or a fragment endpoint -- reinstates
 *    the lockout while every assertion above still passes.
 */
$hookSites = substr_count($index, "processEvent('LOGIN_PAGE_REDIRECT'");
if ($hookSites > 1) {
    $fails[] = 'index.php fires LOGIN_PAGE_REDIRECT ' . $hookSites
        . ' times; only the one inside the FOG_LOCAL_LOGIN guard is'
        . ' verified here, and an unguarded second site redirects the'
        . ' break-glass page';
}

if (count($fails) > 0) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo '  - ' . $f . "\n";
    }
    exit(1);
}
echo "ok: management/login.php cannot be routed to an identity provider\n";
exit(0);
