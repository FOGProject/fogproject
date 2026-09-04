<?php
/**
 * An API-only account must never get a browser session.
 *
 * The flag is one column and its whole meaning is three refusals. Each of
 * them guards a DIFFERENT way a session comes into existence, and no one of
 * them sits downstream of the other two -- so losing any single one is a
 * silent hole rather than a broken feature: the account signs in normally
 * and nothing anywhere says the flag was ignored.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. All three refusals exist -- User::passwordValidate() (local password
 *     and the iPXE menu login), User::establishSession() (a provider that
 *     proves an identity itself, OIDC today) and User::_isLoggedIn() (a
 *     remember-me cookie issued before the flag was set, and an account
 *     marked while it is signed in).
 *  2. The passwordValidate() refusal comes BEFORE the remember-me cookie is
 *     minted. This is the load-bearing ordering and the reason the refusal
 *     is not simply left to establishSession(): passwordValidate() writes
 *     the foguserauth* cookies and a userAuths row a few lines further down,
 *     so refusing downstream would reject the login and leave a working
 *     two-day credential behind.
 *  3. establishSession() THROWS rather than returning quietly. Its callers
 *     disagree about return values and agree about exceptions -- OIDC's
 *     callback ignores the return entirely -- so a quiet return is a
 *     sign-in that appears to succeed and lands nowhere.
 *  4. The last administrator who can still sign in cannot be taken away.
 *     An API-only administrator is not locked out (REST still works), but a
 *     brand-new API-only account has no token until somebody issues one and
 *     issuing one takes a UI session.
 *  5. The guard sits on User::save() and on the delete path, not on the
 *     pages. uAPIOnly is an ordinary field and is not in
 *     Route::$serverOwnedFields, so PUT /fog/user/{id}, a plugin's own
 *     save() and the CSV import all reach it.
 *  6. apiOnlyUsersGiven() reads anything that is not '1' as interactive.
 *     Executed rather than pattern-matched, because getting it backwards
 *     makes the guard answer confidently and wrongly in the direction that
 *     locks an install out.
 *  7. The Password tab is hidden while the flag is set, for the same reason
 *     it is hidden for a directory-owned account: a password stored there
 *     could never be accepted, and a tab that stores one reads as "I have
 *     set them a password".
 *
 * Usage: php tests/api-only-users.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-only-users');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$userSrc = file_get_contents($web . '/src/Items/User.php');
$authSrc = file_get_contents($web . '/src/Auth/Authorization.php');
$schemaSrc = file_get_contents($web . '/commons/schema.php');
$manifestSrc = file_get_contents($web . '/commons/schema-expected.php');
$sysSrc = file_get_contents($web . '/src/Base/System.php');
$pageSrc = file_get_contents($web . '/src/Pages/UserManagement.php');

// ---------------------------------------------------------------------------
// 0. The column exists and the model can read it.
// ---------------------------------------------------------------------------
$t->check(
    'schema step 360 adds users.uAPIOnly, defaulting to interactive',
    (bool)preg_match(
        "/ADD COLUMN `uAPIOnly` ENUM\('0','1'\) NOT NULL DEFAULT '0'/",
        $schemaSrc
    )
);
$t->check(
    'the column is guarded so an install that already has it is skipped',
    (bool)preg_match(
        "/getColumns\(\s*'users',\s*'uAPIOnly'/s",
        $schemaSrc
    )
);
$t->check(
    'FOG_SCHEMA is at least 360',
    (bool)preg_match("/define\('FOG_SCHEMA', (\d+)\)/", $sysSrc, $m)
    && (int)$m[1] >= 360
);
$t->check(
    'the manifest carries uAPIOnly',
    false !== strpos($manifestSrc, "'uAPIOnly' =>")
);
$t->check(
    'User maps apionly to uAPIOnly',
    (bool)preg_match("/'apionly' => 'uAPIOnly'/", $userSrc)
);
$t->check(
    "isAPIOnly() tests for the string '1' rather than truthiness",
    (bool)preg_match(
        "/function isAPIOnly\(\)\s*\{\s*return '1' === \(string\)"
        . "\\\$this->get\('apionly'\);/s",
        $userSrc
    )
);

// ---------------------------------------------------------------------------
// 1. All three refusals exist.
// ---------------------------------------------------------------------------
$bodyOf = function ($src, $signature) {
    $at = strpos($src, $signature);
    if (false === $at) {
        return '';
    }
    // Enough to cover the method without depending on brace counting; each
    // of these is well under this length.
    return substr($src, $at, 9000);
};

$pwBody = $bodyOf($userSrc, 'public function passwordValidate(');
$esBody = $bodyOf($userSrc, 'public function establishSession(');
$liBody = $bodyOf($userSrc, 'private function _isLoggedIn()');

$t->check(
    'passwordValidate() refuses an API-only account',
    (bool)preg_match("/isAPIOnly\(\)\s*\)\s*\{\s*self::_auditLoginFailure/s", $pwBody)
);
$t->check(
    'establishSession() refuses an API-only account',
    false !== strpos($esBody, '$this->isAPIOnly()')
);
$t->check(
    '_isLoggedIn() refuses an API-only account',
    (bool)preg_match(
        "/if \(\\\$this->isAPIOnly\(\)\) \{\s*return false;/s",
        substr($liBody, 0, 2000)
    )
);

// ---------------------------------------------------------------------------
// 2. The password refusal precedes the remember-me minting. THE ordering.
// ---------------------------------------------------------------------------
$refusalAt = strpos($pwBody, 'isAPIOnly()');
$cookieAt = strpos($pwBody, 'foguserauthpass');
$t->check(
    'the API-only refusal precedes the remember-me cookie in '
    . 'passwordValidate()',
    false !== $refusalAt
    && false !== $cookieAt
    && $refusalAt < $cookieAt
);
// Same request, the other artifact: a userAuths row outlives the response
// even if the cookie somehow did not reach the browser.
$authRowAt = strpos($pwBody, 'new UserAuth(');
$t->check(
    'the refusal also precedes the userAuths row',
    false !== $refusalAt && false !== $authRowAt && $refusalAt < $authRowAt
);

// ---------------------------------------------------------------------------
// 3. establishSession() throws.
// ---------------------------------------------------------------------------
$t->check(
    'establishSession() throws rather than returning quietly',
    (bool)preg_match(
        "/if \(\\\$this->isAPIOnly\(\)\) \{\s*throw new \\\\Exception/s",
        $esBody
    )
);

// ---------------------------------------------------------------------------
// 4/5. The guard, and where it sits.
// ---------------------------------------------------------------------------
$t->check(
    'User::save() runs the interactive-login guard',
    (bool)preg_match(
        "/function save\(\)\s*\{.{0,400}?"
        . "_assertApiOnlyKeepsInteractiveLogin\(\);/s",
        $userSrc
    )
);
$t->check(
    'the guard only fires when the flag is actually being SET',
    (bool)preg_match(
        "/function _assertApiOnlyKeepsInteractiveLogin\(\).*?"
        . "!\\\$this->isDirty\('apionly'\).*?"
        . "if \(!\\\$this->isAPIOnly\(\)\) \{\s*return;/s",
        $userSrc
    )
);
$t->check(
    'the guard delegates to Authorization::assertInteractiveAdminRemains',
    (bool)preg_match(
        "/function _assertApiOnlyKeepsInteractiveLogin\(\).*?"
        . "Authorization::assertInteractiveAdminRemains\(\s*"
        . "\['apiOnly' => \[\\\$id => true\]\]/s",
        $userSrc
    )
);
$t->check(
    'deleting a user is guarded too',
    (bool)preg_match(
        "/case 'user':.{0,1200}?assertInteractiveAdminRemains\(\\\$changes\);/s",
        $authSrc
    )
);
$t->check(
    'adminExistsGiven() honors interactiveOnly',
    (bool)preg_match(
        "/if \(!empty\(\\\$changes\['interactiveOnly'\]\)\) \{\s*"
        . "\\\$users = array_diff\(\\\$users, self::_apiOnlyUsers\(\\\$changes\)\);/s",
        $authSrc
    )
);
// PRESERVES rather than REQUIRES: an install that already has no interactive
// administrator must not have every subsequent operation refused.
$t->check(
    'the guard returns rather than refusing when there is nothing to protect',
    (bool)preg_match(
        "/function assertInteractiveAdminRemains\(.*?\)\s*\{\s*"
        . "if \(!self::interactiveAdminExists\(\)\) \{\s*return;/s",
        $authSrc
    )
);

// ---------------------------------------------------------------------------
// 6. The decision half, executed.
// ---------------------------------------------------------------------------
$given = ['FOG\\Auth\\Authorization', 'apiOnlyUsersGiven'];
$t->check(
    'apiOnlyUsersGiven() is callable without a database',
    is_callable($given)
);
if (is_callable($given)) {
    // The 'x' case is the one that carries this check. '0', '' and null are
    // all falsy in PHP, so they cannot tell a strict '1' comparison apart
    // from plain truthiness -- a mutation to (bool)$flag passes on them. A
    // value that is truthy and is NOT '1' is what separates the two, and the
    // strict form is required because this must agree with User::isAPIOnly(),
    // which is also strict. If the guard's model of who can sign in disagrees
    // with the refusal that actually stops them, the guard protects the wrong
    // set of accounts.
    $t->check(
        "only the string '1' bars an account",
        [2] === call_user_func(
            $given,
            [1 => '0', 2 => '1', 3 => '', 4 => null, 5 => 'x'],
            []
        )
    );
    $t->check(
        'a proposed value replaces the stored one, both ways',
        [1] === call_user_func(
            $given,
            [1 => '0', 2 => '1'],
            [1 => true, 2 => false]
        )
    );
    $t->check(
        'an account with no stored row is only barred if proposed so',
        [9] === call_user_func($given, [], [9 => true])
    );
    $t->check(
        'no accounts, no answer',
        [] === call_user_func($given, [], [])
    );
}

// ---------------------------------------------------------------------------
// 7. The Password tab, and the create form.
// ---------------------------------------------------------------------------
$t->check(
    'the Password tab is hidden while the account is API-only',
    (bool)preg_match(
        "/'' === trim\(\(string\)\\\$this->obj->get\('authsource'\)\)\s*"
        . "&& !\\\$this->obj->isAPIOnly\(\)/s",
        $pageSrc
    )
);
$t->check(
    'the flag can be set when the account is created',
    (bool)preg_match("/->set\('apionly', \\\$apionly\)/", $pageSrc)
    && (bool)preg_match(
        "/\\\$apionly = \(int\)isset\(\\\$_POST\['apionly'\]\);/",
        $pageSrc
    )
);
$t->check(
    'and cleared as well as set from the General tab',
    (bool)preg_match(
        "/->set\('apionly', \(int\)isset\(\\\$_POST\['apionly'\]\)\)/",
        $pageSrc
    )
);

$t->finish();
