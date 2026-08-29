<?php
/**
 * Guards the USER_LOGGING_OUT redirect seam.
 *
 *   tests/logout-redirect-seam.test.php
 *
 * Signing out of FOG destroys FOG's session and nothing else. When the
 * session was established by an external identity provider that leaves the
 * provider's SSO session open, so clicking the provider button again signs
 * straight back into the same account and there is no way to become somebody
 * else (fog-plugins#15). Ending that session is a redirect to the provider's
 * end_session_endpoint, and only the plugin that opened it knows the URL --
 * hence a seam rather than anything OIDC-shaped in core.
 *
 * Four properties, and each is a way the seam has already been got wrong or
 * could quietly stop working:
 *
 *   1. the hook is passed a redirect BY REFERENCE. Passed by value it looks
 *      identical at the call site and does nothing at all.
 *   2. the hook fires BEFORE the session is destroyed. A listener builds the
 *      URL out of $_SESSION -- the id_token_hint, the cached endpoint -- so
 *      firing it after session_destroy() hands it an empty array.
 *   3. logout() RETURNS the value and index.php USES it. Returning it and
 *      then ignoring it is the failure mode that reads as working code.
 *   4. the redirect is validated and is not a 308. 308 is permanent and
 *      cacheable, and the target carries a single-use id_token_hint.
 *
 * Source assertions only -- no DB, no session, no provider.
 *
 * Exit 0 = pass, 1 = fail.
 */
$root = dirname(__DIR__);
$user = $root . '/packages/web/src/Items/User.php';
$index = $root . '/packages/web/management/index.php';
$base = $root . '/packages/web/src/Base/FOGBase.php';

$pass = 0;
$fail = 0;
function ok($m)
{
    global $pass;
    ++$pass;
    echo "  ok    $m\n";
}
function bad($m)
{
    global $fail;
    ++$fail;
    echo "  FAIL  $m\n";
}

foreach ([$user, $index, $base] as $f) {
    if (!is_readable($f)) {
        echo "cannot read $f\n";
        exit(1);
    }
}

/**
 * Source with comments stripped.
 *
 * Every assertion below can otherwise be satisfied by the prose explaining
 * the code rather than the code: this seam's comments name
 * USER_LOGGING_OUT, the reference, the ordering and the 302, all of them.
 * A gate its own documentation passes is not a gate.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function src($file)
{
    $out = '';
    foreach (token_get_all(file_get_contents($file)) as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= $t[1];
            continue;
        }
        $out .= $t;
    }
    return $out;
}

/**
 * The body of one method, comments already stripped.
 *
 * @param string $src  stripped source
 * @param string $name the method name
 *
 * @return string
 */
function body($src, $name)
{
    $at = strpos($src, 'function ' . $name . '(');
    if (false === $at) {
        return '';
    }
    return substr($src, $at, 3000);
}

$userSrc = src($user);
$indexSrc = src($index);
$baseSrc = src($base);
$logout = body($userSrc, 'logout');

echo "1. the hook can actually change something\n";

// By reference. Passed by value this compiles, runs, and does nothing --
// which is indistinguishable from working until somebody tries it.
if (preg_match('#processEvent\s*\(\s*[\'"]USER_LOGGING_OUT[\'"]\s*,\s*\[\s*[\'"]redirect[\'"]\s*=>\s*&\s*\$#', $logout)) {
    ok('USER_LOGGING_OUT is passed $redirect by reference');
} else {
    bad('USER_LOGGING_OUT does not pass a by-reference redirect -- a listener cannot set anything');
}

echo "\n2. the hook fires while the session still exists\n";

// Ordering is the whole reason a listener can work at all. Compare offsets
// rather than trusting the source to stay in this order.
$hookAt = strpos($logout, 'USER_LOGGING_OUT');
$destroyAt = strpos($logout, 'session_destroy');
if (false === $hookAt || false === $destroyAt) {
    bad('cannot locate USER_LOGGING_OUT and session_destroy in logout()');
} elseif ($hookAt < $destroyAt) {
    ok('USER_LOGGING_OUT fires before session_destroy()');
} else {
    bad('USER_LOGGING_OUT fires AFTER session_destroy() -- $_SESSION is gone by then');
}

echo "\n3. the value is returned and consumed\n";

if (preg_match('#return\s+\$redirect\s*;#', $logout)) {
    ok('logout() returns the redirect');
} else {
    bad('logout() does not return the redirect');
}

// Both early exits must hand it back too, or single logout works or does not
// depending on whether a session happened to be active.
if (2 <= preg_match_all('#return\s+\$redirect\s*;#', $logout)) {
    ok('every return path in logout() hands the redirect back');
} else {
    bad('a return path in logout() drops the redirect');
}

// Consumed, and pinned to the line that ACTS on it. "index.php mentions
// logout()" would be satisfied by a version that calls it and discards the
// result, which is the defect this pins.
if (preg_match('#=\s*\(string\)\$currentUser->logout\(\)#', $indexSrc)) {
    ok('index.php captures logout()\'s return value');
} else {
    bad('index.php calls logout() without capturing what it returns');
}
if (preg_match('#FOGCore::redirect\s*\(\s*\$logoutRedirect\s*,\s*302\s*\)#', $indexSrc)) {
    ok('index.php redirects to it with 302');
} else {
    bad('index.php never redirects to the returned value with a 302');
}

echo "\n4. the destination is checked, and is not cacheable\n";

// An open redirect is the realistic risk: header() has refused CR/LF since
// PHP 5.1.2, so this is not response splitting.
if (preg_match('#preg_match\s*\(\s*\'\#\^https\?://\#i\'\s*,\s*\$logoutRedirect\s*\)#', $indexSrc)) {
    ok('index.php requires an absolute http(s) URL before redirecting');
} else {
    bad('index.php does not check the scheme of the hook-supplied URL');
}

// redirect() must still default to 308 for the callers that have always got
// one, and must accept an override.
if (preg_match('#function\s+redirect\s*\(\s*\$url\s*=\s*\'\'\s*,\s*\$status\s*=\s*308\s*\)#', $baseSrc)) {
    ok('FOGCore::redirect() still defaults to 308');
} else {
    bad('FOGCore::redirect() no longer defaults to 308 -- every existing caller changes behavior');
}
if (preg_match('#in_array\s*\(\s*\$status\s*,\s*\[\s*301\s*,\s*302\s*,\s*303\s*,\s*307\s*,\s*308\s*\]\s*,\s*true\s*\)#', $baseSrc)) {
    ok('FOGCore::redirect() rejects a status that is not a redirect');
} else {
    bad('FOGCore::redirect() passes its status through unchecked');
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
