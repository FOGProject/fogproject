<?php
/**
 * Callers with no browser must authenticate without establishing a session.
 *
 * FOG has two authentication entry points that are not a browser: the iPXE
 * boot menu (bootmenu.class.php) and service/ipxe/advanced.php. iPXE holds
 * no cookie, so a PHP session created for one of those requests can never
 * be presented back -- it is an authenticated session with no owner, minted
 * on every PXE menu login.
 *
 * Both used to go through attemptLogin() -> validatePw(), which starts a
 * session and stamps $_SESSION['FOG_USER'] unconditionally. The fix split
 * User::validatePw() into:
 *
 *   authenticate()      prove the credential, no side effects
 *   establishSession()  turn a proven identity into a logged-in session
 *
 * with validatePw() kept whole as authenticate + establishSession, so
 * third-party callers are unaffected.
 *
 * This gate pins the property that makes the split worth having. It is
 * static on purpose: the behaviour needs a database, a populated
 * FOG_PXE_ADVANCED and FOG_ADVANCED_MENU_LOGIN toggled on to observe, and a
 * test that needs all three is a test nobody runs.
 *
 * Usage: php tests/ipxe-auth-no-session.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];

/**
 * Extract one method's token stream by brace matching.
 *
 * @param string $file   path to read
 * @param string $method method name to find
 *
 * @return array|null tokens of the body, or null if not found
 */
function methodTokens($file, $method)
{
    $t = token_get_all(file_get_contents($file));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || T_FUNCTION !== $t[$i][0]) {
            continue;
        }
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) {
            $j++;
        }
        if ($j >= $n || !is_array($t[$j]) || $t[$j][1] !== $method) {
            continue;
        }
        // Walk to the opening brace, then match to its close.
        $depth = 0;
        $body = [];
        $started = false;
        for ($k = $j; $k < $n; $k++) {
            $c = $t[$k];
            if (!is_array($c)) {
                if ('{' === $c) {
                    $depth++;
                    $started = true;
                } elseif ('}' === $c) {
                    if (0 === --$depth && $started) {
                        return $body;
                    }
                } elseif (';' === $c && !$started) {
                    return null; // abstract/interface declaration
                }
            }
            if ($started) {
                $body[] = $c;
            }
        }
        return $body;
    }
    return null;
}

$userFile = 'packages/web/src/Items/User.php';

// 1. Both halves of the split must exist.
foreach (['authenticate', 'establishSession', 'validatePw'] as $m) {
    if (null === methodTokens($userFile, $m)) {
        $fails[] = "User::$m() is missing -- the authenticate/establishSession"
            . " split has been undone";
    }
}

// 2. authenticate() must have NO session or login side effects. These are
//    the exact things that made a PXE login mint a session.
$body = methodTokens($userFile, 'authenticate');
if (null !== $body) {
    $src = '';
    foreach ($body as $tok) {
        $src .= is_array($tok) ? $tok[1] : $tok;
    }
    $banned = [
        '$_SESSION' => 'writes session state',
        'session_start' => 'starts a session',
        'setAuthCookie' => 'sets a remember-me cookie',
        '_isLoggedIn' => 'runs the logged-in bookkeeping',
        'establishSession' => 'establishes a session',
    ];
    foreach ($banned as $needle => $why) {
        if (false !== strpos($src, $needle)) {
            $fails[] = "User::authenticate() $why ($needle) -- it must prove"
                . " the credential and nothing else";
        }
    }
}

/*
 * 3. No browser-less entry point may call attemptLogin(). attemptLogin()
 *    is authenticate + establishSession; these callers have nowhere to put
 *    the session. Comments are stripped first so the explanatory prose in
 *    those files does not trip the check.
 */
$browserless = array_merge(
    glob('packages/web/service/ipxe/*.php') ?: [],
    ['packages/web/src/Boot/BootMenu.php']
);
foreach ($browserless as $file) {
    if (!is_readable($file)) {
        continue;
    }
    foreach (token_get_all(file_get_contents($file)) as $tok) {
        if (is_array($tok)
            && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $text = is_array($tok) ? $tok[1] : $tok;
        if ('attemptLogin' === $text) {
            $fails[] = "$file calls attemptLogin(), which establishes a"
                . " session; use authenticateOnly() instead";
        }
    }
}

/*
 * 4. advanced.php must not emit FOG_PXE_ADVANCED unconditionally. The
 *    original bug was a printf sitting outside every conditional, so the
 *    menu went to any caller whatever FOG_ADVANCED_MENU_LOGIN said.
 */
$adv = 'packages/web/service/ipxe/advanced.php';
$advSrc = is_readable($adv) ? file_get_contents($adv) : '';
if ('' !== $advSrc && false === strpos($advSrc, 'FOG_ADVANCED_MENU_LOGIN')) {
    $fails[] = "$adv never reads FOG_ADVANCED_MENU_LOGIN, so the setting that"
        . " promises to gate the advanced menu cannot be enforced";
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: browser-less auth paths establish no session\n";
exit(0);
