<?php
/**
 * Full registration copies the AD defaults onto a new host only for a
 * registrant who signed in with an account that may create hosts.
 *
 * service/auto.register.php takes no session. _fullReg() used to copy
 * FOG_AD_DEFAULT_PASSWORD onto the new host whenever the request said
 * doad, and checked a FOG login only when the request also said doimage.
 * The host then handed that password to whoever completed the client
 * handshake for its MAC, so anyone who could reach the web port could read
 * the domain join account (GHSA-97vv-f7hc-rp5m).
 *
 * The paths are pinned by source. Reaching _fullReg() needs a whole HTTP
 * request and a database, and filter_input() reads nothing under the CLI.
 *
 * No database.
 *
 * Usage: php tests/registration-ad-defaults-need-login.test.php [file]
 *   [file] defaults to packages/web/src/Boot/Registration.php. Pass an
 *   older copy to watch this test fail on the unfixed code.
 * Exit status 0 = pass, 1 = fail.
 */

$path = $argv[1] ?? dirname(__DIR__) . '/packages/web/src/Boot/Registration.php';
$src = @file_get_contents($path);
if (false === $src) {
    fwrite(STDERR, "FAIL: cannot read $path\n");
    exit(1);
}

// Comments removed, so a comment that names a call cannot satisfy a pin.
$code = '';
foreach (token_get_all($src) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
        continue;
    }
    $code .= is_array($t) ? $t[1] : $t;
}

/**
 * The body of one method, up to the next method declaration.
 */
function methodBody($code, $name)
{
    $re = '/function\s+' . preg_quote($name, '/') . '\s*\((.*?)'
        . '(?=\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s|\z)/s';
    return preg_match($re, $code, $m) ? $m[1] : '';
}

$fails = [];
$fullReg = methodBody($code, '_fullReg');
$gate = methodBody($code, '_registrantMayJoinDomain');

// Vacuity guard: an absent method would make every "comes before" check
// below meaningless.
if (strlen($fullReg) < 2000) {
    $fails[] = '_fullReg() not found, or read as only ' . strlen($fullReg) . ' bytes';
}
if ('' === $gate) {
    $fails[] = '_registrantMayJoinDomain() does not exist';
}

/**
 * The body between the brace that opens at or after $from and its match.
 */
function braceBody($code, $from)
{
    $open = strpos($code, '{', $from);
    if (false === $open) {
        return '';
    }
    $depth = 0;
    $len = strlen($code);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $code[$i]) {
            $depth++;
        } elseif ('}' === $code[$i] && 0 === --$depth) {
            return substr($code, $open + 1, $i - $open - 1);
        }
    }
    return '';
}

// The gate itself: a real password check whose failure refuses, then the
// host.create permission for the same account.
$validate = strpos($gate, 'if (!self::_registrantLoginValid()) {');
$perm = strpos($gate, "->can('host.create')");
if (false === $validate) {
    $fails[] = '_registrantMayJoinDomain() does not refuse on a failed login';
} elseif (!preg_match('/^\s*return false;\s*$/', braceBody($gate, $validate))) {
    $fails[] = '_registrantMayJoinDomain() checks the login but does not return false on a failure';
}
$loginCheck = methodBody($code, '_registrantLoginValid');
if (!preg_match('/\(new User\(\)\)\s*->passwordValidate\(\$username, \$password\)/', $loginCheck)) {
    $fails[] = '_registrantLoginValid() does not call passwordValidate() on the posted login';
}
if (false === $perm) {
    $fails[] = "_registrantMayJoinDomain() does not check can('host.create')";
}
if (false !== $validate && false !== $perm && $perm < $validate) {
    $fails[] = '_registrantMayJoinDomain() checks the permission before the password';
}
if (false === strpos($gate, '_postedCredentials()')) {
    $fails[] = '_registrantMayJoinDomain() does not read the login through'
        . ' _postedCredentials(), so it can decode differently from imaging';
}

// _fullReg(): the gate's answer is what $adRefused holds, and every read of
// the AD password sits inside the branch that $adRefused guards.
if (!preg_match('/\$adRefused = \$wantAD && !self::_registrantMayJoinDomain\(\);/', $fullReg)) {
    $fails[] = '_fullReg() does not take $adRefused from _registrantMayJoinDomain()';
}
$readCount = substr_count($fullReg, "'FOG_AD_DEFAULT_PASSWORD'");
if (0 === $readCount) {
    $fails[] = '_fullReg() no longer reads FOG_AD_DEFAULT_PASSWORD; re-check this test';
}
$guardAt = strpos($fullReg, 'if ($wantAD && !$adRefused) {');
if (false === $guardAt) {
    $fails[] = '_fullReg() has no branch guarded by !$adRefused';
} elseif (substr_count(braceBody($fullReg, $guardAt), "'FOG_AD_DEFAULT_PASSWORD'") !== $readCount) {
    $fails[] = '_fullReg() reads FOG_AD_DEFAULT_PASSWORD outside the branch the login gate guards';
}

// Imaging and domain join decode the login the same way.
if (false === strpos(methodBody($code, '_deployHost'), 'self::_postedCredentials()')) {
    $fails[] = '_deployHost() no longer reads its login through _postedCredentials()';
}

if ($fails) {
    fwrite(STDERR, "FAIL\n");
    foreach ($fails as $why) {
        fwrite(STDERR, '  - ' . $why . "\n");
    }
    exit(1);
}
fwrite(STDERR, "PASS registration-ad-defaults-need-login\n");
exit(0);
