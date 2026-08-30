<?php
/**
 * Starting an impersonation is the REAL administrator's capability.
 *
 * Every other permission in FOG is answered for the EFFECTIVE identity,
 * because seeing what the impersonated user sees is the entire point of the
 * feature. Authorization::can() with no user id does exactly that, and it is
 * right everywhere except here. Starting a span is the one capability that
 * does not belong to the person being worn.
 *
 * THE BUG THIS EXISTS FOR did not look like a permission bug. Impersonating
 * `alice`, an account holding no roles, turned "Impersonate another" into
 * "You do not have permission to impersonate users." -- a sentence that was
 * perfectly true of alice and false of the administrator reading it. The
 * engine was never wrong: refusalReason() and begin() both take the real id
 * explicitly and would have allowed it. Only the DISPLAY gates asked, and
 * they asked the mask. So the control that refused was the control for
 * getting out of the mask.
 *
 * The shell's two gates were an if/elseif on $impersonating, which hid it:
 * the arm carrying the bare can() could not be reached during a span, so the
 * wrong identity was only consulted once "impersonate another" existed. That
 * is why this pins BOTH halves -- the predicate, and the fact that nothing
 * outside Identity itself is allowed to ask the question any other way.
 *
 * Third half, and the one that matters most if the grant is ever revoked
 * mid-span: ENDING must not be gated on canStart(). A withdrawn grant should
 * remove the swap and leave the exit, never the other way round, or the mask
 * becomes a trap.
 *
 * MUTATION-VERIFIED -- see the table in the pull request. Each edit was made
 * against a scratch copy of the file and restored from that copy.
 *
 * Usage: php tests/impersonation-start-gate-asks-the-real-user.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Auth\Identity;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('impersonation-start-gate');
/*
 * Nothing here queries, but User::set() logs and the logger reads a setting,
 * so constructing even a fake user needs SOMETHING behind $DB.
 */
FogTestHarness::fakeDb();

$fails = [];
$checks = 0;

/**
 * Record one assertion.
 *
 * @param string   $label  what was being asserted
 * @param bool     $cond   whether it held
 * @param string[] $fails  collected failures
 * @param int      $checks assertions run
 *
 * @return void
 */
function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

/**
 * PHP source with its comments removed.
 *
 * Every scan below is a claim about CODE, and the comments in both files
 * discuss Authorization::can() and Identity::PERMISSION at length precisely
 * because getting them wrong is the bug. A raw grep would fail on its own
 * documentation.
 *
 * @param string $src PHP source
 *
 * @return string
 */
function stripPhpComments($src)
{
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

/**
 * A User object that reports the given id and name without a database.
 *
 * @param int    $id   the user id
 * @param string $name the user name
 *
 * @return User
 */
function fakeUser($id, $name)
{
    $u = new User(0);
    $u->set('id', $id)->set('name', $name);

    return $u;
}

/*
 * BEHAVIOR.
 *
 * User 1 is the administrator and holds '*'. User 7 is alice, who holds
 * nothing. Seeding Authorization's permission cache directly keeps the whole
 * roles/groups join out of a test that is about WHICH ID gets asked, not
 * about how permissions are computed.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION[Identity::SESSION_REAL] = 1;
$_SESSION[Identity::SESSION_MASK] = 7;
$_SESSION[Identity::SESSION_SPAN] = str_repeat('b', 32);

FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*'], 7 => []]);
FogTestHarness::setStatic('FOGBase', 'FOGUser', fakeUser(7, 'alice'));

check(
    'canStart() answered NO to an administrator wearing a roleless account'
    . ' -- it asked the mask instead of the real user',
    true === Identity::canStart(),
    $fails,
    $checks
);
/*
 * The other side of the same coin, and the reason the check above is not
 * vacuous: a bare can() genuinely answers differently here. If this ever goes
 * true the fixture has stopped reproducing the condition and the check above
 * proves nothing.
 */
check(
    'the fixture is not reproducing the bug: a bare Authorization::can()'
    . ' agrees with canStart(), so nothing distinguishes them',
    false === Authorization::can(Identity::PERMISSION),
    $fails,
    $checks
);

/*
 * WITHDRAWN GRANT. Revoke the administrator's own permission mid-span and
 * starting must stop being offered -- canStart() is not a rubber stamp for
 * "a span is already open".
 */
FogTestHarness::setStatic('Authorization', '_permCache', [1 => [], 7 => []]);
check(
    'canStart() stayed true after the real administrator lost the grant',
    false === Identity::canStart(),
    $fails,
    $checks
);

/*
 * NO SESSION. realUserID() answers 0 and getPermissions() grants nothing to
 * id 0, so this is belt and braces -- but it is the state every logged-out
 * request is in, and the shell calls canStart() on all of them.
 */
$_SESSION = [];
FogTestHarness::setStatic('Authorization', '_permCache', [0 => ['*']]);
check(
    'canStart() answered yes with no user in the session',
    false === Identity::canStart(),
    $fails,
    $checks
);

/*
 * SOURCE. Nothing outside Identity may ask this question for itself.
 */
$web = dirname(__DIR__) . '/packages/web';
$files = [
    'the page shell' => $web . '/management/other/index.php',
    'the impersonate page' => $web . '/lib/pages/impersonatemanagement.page.php',
];
foreach ($files as $label => $path) {
    $src = stripPhpComments((string)file_get_contents($path));
    check(
        "$label was not found, so nothing about it was checked",
        '' !== $src,
        $fails,
        $checks
    );
    /*
     * Matching can(...PERMISSION) in either spelling -- imported as
     * Identity::PERMISSION, or fully qualified as the shell writes it.
     */
    check(
        "$label asks Authorization::can() for Identity::PERMISSION directly."
        . ' That answers for the EFFECTIVE user, which during a span is the'
        . ' impersonated one. Use Identity::canStart().',
        0 === preg_match(
            '#Authorization::can\(\s*\\\\?(?:FOG\\\\Auth\\\\)?Identity::PERMISSION#',
            $src
        ),
        $fails,
        $checks
    );
    check(
        "$label does not use Identity::canStart() at all, so its gate is"
        . ' asking something else entirely',
        false !== strpos($src, 'Identity::canStart()'),
        $fails,
        $checks
    );
}

/*
 * And the exit is gated on the SPAN, never on the permission. Found
 * structurally rather than by eye: take the conditional immediately enclosing
 * the end link and read what it tests.
 */
$shell = stripPhpComments((string)file_get_contents($files['the page shell']));
$endAt = strpos($shell, 'sub=end');
check(
    'the page shell no longer emits an end-impersonation link at all',
    false !== $endAt,
    $fails,
    $checks
);
if (false !== $endAt) {
    $before = substr($shell, 0, $endAt);
    $ifAt = strrpos($before, 'if (');
    $guard = false === $ifAt
        ? ''
        : substr($before, $ifAt, (int)strpos($before, ')', $ifAt) - $ifAt + 1);
    check(
        'the end-impersonation link is guarded by ' . var_export($guard, true)
        . ' -- it must be guarded by $impersonating and by nothing else.'
        . ' Gate the exit on the permission and a revoked grant traps the'
        . ' administrator inside the mask.',
        false !== strpos($guard, '$impersonating')
        && false === strpos($guard, 'canStart'),
        $fails,
        $checks
    );
}

if (count($fails)) {
    fwrite(STDERR, 'FAIL (' . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
