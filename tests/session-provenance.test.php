<?php
/**
 * A session must record HOW it was established, not just who owns it.
 *
 * Phase 2 PR 2.3. Today every logged-in session looks identical: $_SESSION
 * holds FOG_USER and nothing else, and the history row says "user
 * successfully logged in" whatever proved the credential. Once an identity
 * provider can establish a session that is no longer good enough, for two
 * reasons that are worth stating separately:
 *
 *   Audit -- an install that adopts SSO otherwise loses the ability to
 *   answer "did this person come in through the IdP or through a local
 *   password?", which is precisely the question asked after an incident.
 *
 *   Break-glass -- an IdP outage must leave local password login working,
 *   and the checks that will guarantee that (PR 2.5) need to count sessions
 *   by how they were MADE. They cannot use users.uAuthSource for this:
 *   uAuthSource is a property of the ACCOUNT and says which directory owns
 *   it, while provenance is a property of the REQUEST. An LDAP-sourced
 *   account reaching a break-glass path still made this particular session
 *   somehow, and that is the fact being recorded here.
 *
 * The behavioural half of establishSession() cannot be exercised without a
 * database -- it writes a history row and runs the logged-in bookkeeping --
 * so the pure part was pulled out into User::normalizeAuthSource() and is
 * tested for real below. The rest is pinned statically.
 *
 * Usage: php tests/session-provenance.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$userFile = 'packages/web/src/Items/User.php';

/**
 * Source text of one method, comments and whitespace stripped.
 *
 * Comments go first so the explanatory prose above each method -- which
 * names every symbol this test searches for -- cannot satisfy the search
 * on its own. The same trap as the browser-less session gate.
 *
 * @param string $file   path to read
 * @param string $method method name to find
 *
 * @return string|null code of the body, or null if not found
 */
function methodSource($file, $method)
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
        $depth = 0;
        $src = '';
        $started = false;
        for ($k = $j; $k < $n; $k++) {
            $c = $t[$k];
            if (is_array($c)
                && in_array($c[0], [T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            if (!is_array($c)) {
                if ('{' === $c) {
                    $depth++;
                    $started = true;
                } elseif ('}' === $c) {
                    if (0 === --$depth && $started) {
                        return $src;
                    }
                } elseif (';' === $c && !$started) {
                    return null;
                }
            }
            if ($started) {
                $src .= is_array($c) ? $c[1] : $c;
            }
        }
        return $src;
    }
    return null;
}

// 1. establishSession() stamps the session with the provenance, and does it
//    through the normaliser rather than with whatever it was handed.
$body = methodSource($userFile, 'establishSession');
if (null === $body) {
    $fails[] = 'User::establishSession() is missing';
} else {
    if (false === strpos($body, "\$_SESSION['FOG_AUTH_SOURCE']")) {
        $fails[] = "User::establishSession() does not set"
            . " \$_SESSION['FOG_AUTH_SOURCE'] -- a session established by an"
            . " identity provider would be indistinguishable from a password"
            . ' one';
    }
    if (false === strpos($body, 'normalizeAuthSource')) {
        $fails[] = 'User::establishSession() does not pass its $source'
            . ' through normalizeAuthSource(); the value reaches the history'
            . ' table and a session key and a plugin supplies it';
    }
}

// 2. validatePw() must still call establishSession() with no argument. That
//    default is the whole no-behaviour-change claim for the password path:
//    every existing caller keeps the meaning it already had.
$pw = methodSource($userFile, 'validatePw');
if (null !== $pw && false === strpos($pw, 'establishSession()')) {
    $fails[] = 'User::validatePw() no longer calls establishSession() with'
        . ' no argument -- the password path must keep taking the default';
}

/*
 * 3. logout() needs no FOG_AUTH_SOURCE line of its own, and this is why:
 *    session_unset() empties $_SESSION wholesale, so every key added here
 *    or by a future provider is cleared without anyone remembering to. If
 *    that ever becomes a selective unset, provenance would survive a logout
 *    and describe the NEXT session -- so pin the wholesale clear rather
 *    than adding a line that looks redundant today.
 */
$out = methodSource($userFile, 'logout');
if (null !== $out
    && false === strpos($out, 'session_unset')
    && false === strpos($out, 'FOG_AUTH_SOURCE')
) {
    $fails[] = 'User::logout() no longer clears the session wholesale with'
        . ' session_unset(); FOG_AUTH_SOURCE would outlive the session that'
        . ' set it';
}

/*
 * 4. The normaliser, for real. Booting the autoloader is enough to reach it
 *    -- Initiator's constructor only registers the autoloader, and this is
 *    a pure static method, so no database is involved. FOG_CACHE_DIR and
 *    friends are redirected into a throwaway directory first; see the long
 *    note in tests/autoload.test.php for why that line must never become
 *    conditional.
 */
$tmp = sys_get_temp_dir() . '/fog-provenance-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        foreach (glob($tmp . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($tmp . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($tmp);
    }
);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');

require_once $root . '/packages/web/commons/init.php';
new Initiator();

$cases = [
    // As supplied           => as recorded
    'password'               => 'password',
    'oidc'                   => 'oidc',
    'OIDC'                   => 'oidc',
    '  saml  '               => 'saml',
    'azure-ad'               => 'azure-ad',
    'my_provider2'           => 'my_provider2',
    // Refused: not a plain slug. Recorded as unknown rather than passed
    // through, because this string reaches an audit trail.
    ''                       => 'unknown',
    '-leading'               => 'unknown',
    'has space'              => 'unknown',
    "new\nline"              => 'unknown',
    'quote"inject'           => 'unknown',
    '<script>'               => 'unknown',
    str_repeat('a', 33)      => 'unknown',
];
foreach ($cases as $in => $want) {
    $got = \FOG\User::normalizeAuthSource($in);
    if ($got !== $want) {
        $fails[] = sprintf(
            'User::normalizeAuthSource(%s) returned %s, expected %s',
            var_export($in, true),
            var_export($got, true),
            var_export($want, true)
        );
    }
}

// 32 characters is the boundary, and it is on the allowed side of it.
if ('unknown' === \FOG\User::normalizeAuthSource(str_repeat('a', 32))) {
    $fails[] = 'User::normalizeAuthSource() rejects a 32-character slug;'
        . ' the documented limit is 32, not 31';
}

// 5. No session, no provenance -- and no notice for reading a key that is
//    not there. Every caller of this reads it on requests that may have no
//    session at all.
if ('' !== \FOG\User::sessionAuthSource()) {
    $fails[] = 'User::sessionAuthSource() returned a value with no active'
        . ' session';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: sessions record how they were established\n";
exit(0);
