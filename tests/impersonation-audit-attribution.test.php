<?php
/**
 * An audit row names the administrator, never the person they are wearing.
 *
 * This is the check the whole feature stands on. Impersonation works by
 * pointing $GLOBALS['currentUser'] at the impersonated user, and FOGBase
 * binds self::$FOGUser as a REFERENCE to that global -- which is what makes
 * permissions, site scope, displayTimeZone() and displayTheme() follow the
 * target with one assignment. Audit::_actor() read the same reference. So
 * the one-line change that makes impersonation work would, unaccompanied,
 * have rewritten alCreatedBy to the target for everything the administrator
 * did.
 *
 * An audit trail that names somebody who did not act is worse than no audit
 * trail: it destroys repudiation for the one person who cannot disprove it.
 * The failure is also silent -- every row looks perfectly well-formed -- so
 * nothing but a test stands between the code and that outcome.
 *
 * Four things are pinned:
 *
 *   (a) _actor() answers the REAL user while a span is open.
 *   (b) It still answers the session user when no span is open, and the
 *       machine actor when there is nobody -- and it reaches the first of
 *       those WITHOUT a users-table read, which is the only thing that can
 *       distinguish the correct implementation from one that drops the
 *       isImpersonating() guard and resolves the real user every time.
 *   (c) realUserName() reads the users table directly. A routed read would
 *       bolt the MASK's object scope onto the query, and a mask scoped to
 *       nothing would answer '' -- so the actor would silently degrade to
 *       'fog' and the row would claim FOG did it to itself.
 *   (d) $_SESSION['FOG_USER'] still holds the administrator, and
 *       FOG_AUTH_SOURCE is untouched. That direction is the reason (a) can
 *       fail safely: anything that reads FOG_USER and was never found keeps
 *       naming the administrator.
 *
 * MUTATION-VERIFIED. Deleting the real-actor branch from _actor() turns (a)
 * red naming 'sarah' -- the forgery itself. Deleting only its
 * isImpersonating() guard leaves every NAME in this file correct, which is
 * why (b) counts queries instead: that mutation is caught by the read count
 * and by nothing else here.
 *
 * DB-free by the same means as lockout-guard-is-unscoped.test.php.
 *
 * Usage: php tests/impersonation-audit-attribution.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Audit\Audit;
use FOG\Auth\Identity;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('impersonation-actor');

$fails = [];
$checks = 0;

function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

/**
 * How many times a statement log reads a username out of the users table.
 *
 * Taken as a parameter rather than read off the fake DB in place: this is
 * the assertion that tells Audit::_actor()'s two possible implementations
 * apart, so it has to count what actually ran.
 *
 * @param array<int, string> $log the fake DB's statement log
 *
 * @return int
 */
function countUserNameReads(array $log)
{
    $n = 0;
    foreach ($log as $sql) {
        if (preg_match('#SELECT\s+`uName`\s+FROM\s+`users`#', $sql)) {
            $n++;
        }
    }

    return $n;
}

/*
 * User 1 is the administrator holding the session; user 7 is the person
 * being impersonated. Anything else falls through to the harness defaults,
 * so a read that starts going somewhere new shows up as a wrong answer
 * rather than as a silently absent row.
 */
$db = FogTestHarness::fakeDb();
$db->responder = function ($sql, $params) {
    if (false !== strpos($sql, 'SELECT `uName` FROM `users`')) {
        $names = [1 => 'adminuser', 7 => 'sarah'];
        return ['uName' => $names[(int)($params['uid'] ?? 0)] ?? ''];
    }
    return null;
};

$actor = new \ReflectionMethod(Audit::class, '_actor');
$actor->setAccessible(true);

/**
 * Reset Identity's per-request name memos between scenarios.
 *
 * @return void
 */
function forgetIdentityMemos()
{
    foreach (['_realName', '_maskName'] as $name) {
        $p = new \ReflectionProperty(Identity::class, $name);
        $p->setAccessible(true);
        $p->setValue(null, null);
    }
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
 * A REAL session, not just a populated $_SESSION. Identity reads through
 * session_status() exactly as LoadGlobals does, so a test that faked the
 * array would pass while every one of these methods answered "no session"
 * on a live server -- which is the shape of a green test on broken code.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];

/*
 * SCENARIO 1 -- a span is open. currentUser is the MASK, exactly as
 * Identity::bind() leaves it.
 */
$_SESSION[Identity::SESSION_REAL] = 1;
$_SESSION[Identity::SESSION_MASK] = 7;
$_SESSION[Identity::SESSION_SPAN] = str_repeat('a', 32);
$_SESSION['FOG_AUTH_SOURCE'] = 'password';
forgetIdentityMemos();
FogTestHarness::setStatic('FOGBase', 'FOGUser', fakeUser(7, 'sarah'));

$actedBy = (string)$actor->invoke(null);

check(
    'audit actor is the impersonated user, not the administrator'
    . " (got '$actedBy')",
    'adminuser' === $actedBy,
    $fails,
    $checks
);
check(
    'Identity::isImpersonating() does not see an open span',
    true === Identity::isImpersonating(),
    $fails,
    $checks
);
check(
    'the acted-as name is not the impersonated user',
    'sarah' === Identity::impersonatedUserName(),
    $fails,
    $checks
);
check(
    'the span id is not carried through to the audit columns',
    str_repeat('a', 32) === Identity::spanID(),
    $fails,
    $checks
);

/*
 * (d) The session still holds the administrator, and the auth source still
 *     describes how the ADMINISTRATOR got in. Rewriting either would
 *     corrupt the break-glass counting establishSession() exists to keep
 *     honest, and would remove the property that makes a missed reader fail
 *     safely.
 */
check(
    'FOG_USER was overwritten with the impersonated user',
    1 === (int)$_SESSION[Identity::SESSION_REAL],
    $fails,
    $checks
);
check(
    'FOG_AUTH_SOURCE was rewritten by impersonation',
    'password' === $_SESSION['FOG_AUTH_SOURCE'],
    $fails,
    $checks
);

/*
 * (c) The real user's name came from the users table directly. A routed
 *     read would carry the MASK's object scope, and `user` is a site-scoped
 *     node -- so a mask in no site would answer nothing and the actor would
 *     silently degrade to 'fog'. Asserting the SQL and not only the string
 *     is the point: the string is right on any install where the mask
 *     happens to be unbounded, and wrong on the customer's.
 */
$sawDirectRead = false;
$sawScopeFragment = false;
foreach ($db->log as $sql) {
    if (preg_match('#SELECT\s+`uName`\s+FROM\s+`users`#', $sql)) {
        $sawDirectRead = true;
    }
    if (false !== stripos($sql, 'siteUserMembers')
        || false !== strpos($sql, '1=0')
    ) {
        $sawScopeFragment = true;
    }
}
check(
    'the real user name was not read from the users table directly',
    $sawDirectRead,
    $fails,
    $checks
);
check(
    'the real user name was read through a site-scoped query',
    !$sawScopeFragment,
    $fails,
    $checks
);

/*
 * SCENARIO 2 -- no span. The pre-existing behavior has to survive: the
 * actor is the session user, and getting there costs NOTHING extra.
 *
 * The cost is asserted, not just the name, and that is what makes this a
 * gate rather than a restatement. With no span open the two answers are the
 * same string by construction -- the session user IS the real user -- so
 * an over-correction that dropped the isImpersonating() guard and always
 * resolved the real user would still name 'adminuser' and this check would
 * pass on it. What it would NOT do is stay quiet: it would put a users-table
 * read on every audited request on every install that never impersonates
 * anybody. Counting the queries is the only thing here that can tell the
 * two implementations apart.
 */
unset(
    $_SESSION[Identity::SESSION_MASK],
    $_SESSION[Identity::SESSION_SPAN]
);
forgetIdentityMemos();
FogTestHarness::setStatic('FOGBase', 'FOGUser', fakeUser(1, 'adminuser'));
$db->log = [];

check(
    'without a span the actor is no longer the session user',
    'adminuser' === (string)$actor->invoke(null),
    $fails,
    $checks
);
$readsWithNoSpan = countUserNameReads($db->log);
check(
    'the actor path queries the users table when nobody is impersonating'
    . " ($readsWithNoSpan reads)",
    0 === $readsWithNoSpan,
    $fails,
    $checks
);
check(
    'a closed span still reports an acted-as name',
    '' === Identity::impersonatedUserName(),
    $fails,
    $checks
);
check(
    'a closed span still reports a span id',
    '' === Identity::spanID(),
    $fails,
    $checks
);

/*
 * SCENARIO 3 -- a machine path: no session user at all. Must still be the
 * machine actor, which is what FOGController::save()'s createdBy auto-fill
 * already produces.
 */
$_SESSION = [];
forgetIdentityMemos();
FogTestHarness::setStatic('FOGBase', 'FOGUser', new User(0));

check(
    'a userless request no longer records the machine actor',
    Audit::MACHINE_ACTOR === (string)$actor->invoke(null),
    $fails,
    $checks
);

/*
 * The two columns exist on the model, spelled the way Audit::record()
 * spells them, and spanID is exempt from save()'s "ends in id, must be a
 * foreign key" inference -- without which every row would claim to belong
 * to span 0 and the bracket would be gone, silently.
 */
$fields = (new \ReflectionClass(\FOG\Items\AuditLog::class))
    ->getDefaultProperties();
check(
    'AuditLog does not map actedAs to alActedAs',
    ($fields['databaseFields']['actedAs'] ?? '') === 'alActedAs',
    $fails,
    $checks
);
check(
    'AuditLog does not map spanID to alSpanID',
    ($fields['databaseFields']['spanID'] ?? '') === 'alSpanID',
    $fails,
    $checks
);
check(
    'spanID is not exempt from the foreign-key name inference',
    in_array('spanID', (array)($fields['databaseFieldsNotInt'] ?? []), true),
    $fails,
    $checks
);

if (count($fails)) {
    fwrite(STDERR, "FAIL (" . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
