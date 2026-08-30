<?php
/**
 * An API-only account cannot be impersonated.
 *
 * users.uAPIOnly means "may hold API credentials and act with its roles over
 * REST, and NO BROWSER SESSION MAY EVER BE MADE FOR IT". User::isAPIOnly()'s
 * docblock enumerates the three places that enforce it, one per way a session
 * comes into existence -- passwordValidate(), establishSession() and
 * _isLoggedIn() -- and says plainly why there are three: no single one of them
 * sits downstream of the other two.
 *
 * IMPERSONATION IS A FOURTH WAY, added after that comment was written, and it
 * passes through none of them. Identity::begin() writes the mask straight into
 * the session. So the flag was bypassable by anyone holding impersonate.start,
 * which is the whole reason this is a refusal in the ENGINE rather than a
 * filter on the picker's query.
 *
 * That distinction is the thing most likely to be lost later. The visible
 * symptom is a service account in a dropdown, and the obvious fix is
 * `AND uAPIOnly != '1'` in _candidates(). That would tidy the list and leave
 * the POST wide open -- and it would put the rule in two places, which is the
 * mistake ADR 0034 is about. One authority: refusalReason().
 *
 * Refusing there covers all three paths at once, because all three ask it:
 *
 *   the picker  -- _candidates() keeps a user only when it returns ''
 *   the POST    -- begin() refuses and records a REFUSED row
 *   an open span-- bind() re-checks on EVERY request and ends the span, so an
 *                  account marked API-only mid-span stops on the next request
 *
 * ORDERING IS ASSERTED, not incidental. The check sits after the
 * impersonator's own permission test: answering "that account is API-only" to
 * somebody who may not impersonate at all discloses something about an account
 * they had no business asking about.
 *
 * MUTATION-VERIFIED -- see the table in the pull request.
 *
 * Usage: php tests/impersonation-refuses-api-only-accounts.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Identity;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('impersonation-api-only');

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
 * The scans below are claims about CODE, and the comments in both files
 * discuss uAPIOnly and _candidates() at length precisely because getting this
 * wrong is the bug -- so a raw grep would match its own documentation.
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

/*
 * User 1 is the administrator and holds '*'. User 7 is an ordinary account.
 * User 9 is a service account with uAPIOnly set.
 *
 * The fake DB answers the users read; everything else falls through to the
 * harness defaults, so a lookup that starts going somewhere new shows up as a
 * wrong answer rather than as a silently absent row.
 */
$db = FogTestHarness::fakeDb();
$db->responder = function ($sql, $params) {
    // `SELECT `users`.* FROM `users` WHERE `uId`=:id`, confirmed by probing
    // the real query rather than guessing at it -- the first cut keyed on
    // 'uId' and every row came back empty, which the two fixture-honesty
    // checks below caught.
    if (false !== strpos($sql, 'FROM `users`')) {
        $id = (int)($params[':id'] ?? 0);
        // Every column the model declares, not just the two under test.
        // A partial row loads fine and then warns per missing key, and a
        // suite that prints warnings is a suite people stop reading.
        $row = function ($id, $name, $apiOnly) {
            return [
                'uId' => $id,
                'uName' => $name,
                'uPass' => '',
                'uCreateDate' => '',
                'uCreateBy' => '',
                'uType' => '0',
                'uDisplay' => '',
                'uAllowAPI' => '0',
                'uAPIToken' => '',
                'uAuthSource' => '',
                'uAPIOnly' => $apiOnly,
            ];
        };
        $rows = [
            7 => $row(7, 'sarah', '0'),
            9 => $row(9, 'svc-datto', '1'),
        ];

        return $rows[$id] ?? null;
    }

    return null;
};

FogTestHarness::setStatic('Authorization', '_permCache', [
    1 => ['*'],
    7 => [],
    9 => [],
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION[Identity::SESSION_REAL] = 1;

/*
 * THE FIXTURE IS HONEST. Before asserting that user 9 is refused, prove the
 * object under test actually reports what the fixture intends -- otherwise a
 * responder that quietly returned nothing would produce a refusal for the
 * wrong reason and this file would report "verified" on a broken guard.
 */
$svc = new User(9);
$ordinary = new User(7);
check(
    'the fixture does not produce an API-only user, so the refusal below'
    . ' would pass for the wrong reason',
    $svc->isValid() && $svc->isAPIOnly(),
    $fails,
    $checks
);
check(
    'the fixture does not produce an ordinary user to contrast against',
    $ordinary->isValid() && !$ordinary->isAPIOnly(),
    $fails,
    $checks
);

/*
 * THE REFUSAL, and its opposite. Both directions matter: a check that only
 * asserted the refusal would pass just as well if refusalReason() refused
 * EVERYBODY, which is a way of breaking the feature entirely.
 */
$whyService = Identity::refusalReason(1, 9);
check(
    'an API-only account is offered as an impersonation target',
    '' !== $whyService,
    $fails,
    $checks
);
check(
    'the refusal for an API-only account is ' . var_export($whyService, true)
    . ' -- it must name the API-only reason, or the audit trail records the'
    . ' wrong cause and the next reader debugs the wrong thing',
    false !== strpos($whyService, 'API-only'),
    $fails,
    $checks
);
check(
    'an ordinary user is now refused too, so this refuses everybody rather'
    . ' than refusing service accounts',
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * begin() REFUSES. The picker filtering the list is not the guard -- the POST
 * takes a targetid from the request body and nothing stops somebody sending
 * an id the dropdown never offered.
 */
$threw = false;
try {
    Identity::begin(9);
} catch (\Throwable $e) {
    $threw = true;
}
check(
    'begin() opened a span on an API-only account -- the picker filtering'
    . ' the dropdown does not stop a hand-made POST',
    $threw,
    $fails,
    $checks
);
check(
    'begin() left a span open on the refused account',
    0 === (int)($_SESSION[Identity::SESSION_MASK] ?? 0),
    $fails,
    $checks
);

/*
 * ORDERING. The impersonator's own permission is tested FIRST, so somebody
 * who may not impersonate learns nothing about the target.
 */
FogTestHarness::setStatic('Authorization', '_permCache', [
    1 => [],
    9 => [],
]);
$whyNoGrant = Identity::refusalReason(1, 9);
check(
    'a caller holding no grant is told the target is API-only, which'
    . ' discloses an account they may not ask about. The permission test'
    . ' must come first.',
    false === strpos($whyNoGrant, 'API-only'),
    $fails,
    $checks
);

/*
 * SOURCE. One authority, and it is the engine.
 */
$web = dirname(__DIR__) . '/packages/web';
$identity = stripPhpComments(
    (string)file_get_contents($web . '/src/Auth/Identity.php')
);
$page = stripPhpComments(
    (string)file_get_contents(
        $web . '/src/Pages/ImpersonateManagement.php'
    )
);

check(
    'refusalReason() does not consult isAPIOnly(), so the rule lives'
    . ' somewhere other than the one place all three paths ask',
    false !== strpos($identity, 'isAPIOnly()'),
    $fails,
    $checks
);
check(
    'the picker filters API-only accounts in its OWN query. That tidies the'
    . ' dropdown and leaves the POST open, and it is a second authority for'
    . ' a rule that already has one -- see ADR 0034.',
    false === stripos($page, 'uAPIOnly')
    && false === strpos($page, 'isAPIOnly'),
    $fails,
    $checks
);
check(
    'the picker no longer builds its list from refusalReason(), so a rule'
    . ' added to the engine no longer reaches the dropdown',
    false !== strpos($page, 'Identity::refusalReason('),
    $fails,
    $checks
);

if (count($fails)) {
    fwrite(STDERR, 'FAIL (' . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
