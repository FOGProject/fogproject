<?php
/**
 * Remember Me must survive the inactivity timeout (forum 18315).
 *
 * User::_isLoggedIn() redirects an idle session to node=logout, and
 * logout() clears the foguserauth* cookies. So when that branch ignored the
 * remember-me cookie, Remember Me lasted only until the first idle hour.
 * It was hidden while PHP's 24-minute session GC deleted the session first;
 * #1306 gave FOG an 86400s store and exposed it.
 *
 * WHAT IS PINNED, all of it executed:
 *
 *  1. UserAuth::matches() accepts a live token for its own user, and
 *     refuses each way a token can be wrong: another user, a bad selector,
 *     a bad password, the isExpired flag, a past expiry, empty cookies, an
 *     unsaved row.
 *  2. User::isLoggedIn() on an idle session: with no cookie, or a cookie for
 *     another user, it still queues "Session Expired"; with a valid cookie
 *     for this user it does not, and it refreshes lastactivity. An ACTIVE
 *     session with no cookie is not logged out.
 *
 * FOGBase::$service is set so redirect() returns instead of sending headers;
 * the queued "Session Expired" message is what shows the redirect fired.
 *
 * Usage: php tests/remember-me-outlives-inactivity.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

use FOG\Items\User;
use FOG\Items\UserAuth;

FogTestHarness::boot('remember-me-outlives-inactivity');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$selector = 'sel-' . bin2hex(random_bytes(8));
$password = 'pw-' . bin2hex(random_bytes(8));
$selHash = password_hash($selector, PASSWORD_DEFAULT);
$pwHash = password_hash($password, PASSWORD_DEFAULT);
$future = date('Y-m-d H:i:s', time() + 86400);

/**
 * Build an unsaved userAuths row: token 7 for user 3, unless overridden.
 *
 * @param string $selHash  hash of the selector
 * @param string $pwHash   hash of the password
 * @param string $expire   the expiry
 * @param array  $override field => value overrides
 *
 * @return UserAuth
 */
function rememberMeRow($selHash, $pwHash, $expire, array $override = [])
{
    $row = new UserAuth();
    $fields = $override + [
        'id' => 7,
        'userID' => 3,
        'isExpired' => 0,
        'expire' => $expire,
        'selector' => $selHash,
        'password' => $pwHash,
    ];
    foreach ($fields as $key => $value) {
        $row->set($key, $value);
    }
    return $row;
}

// ---------------------------------------------------------------------------
// 1. matches() behavior.
// ---------------------------------------------------------------------------
$row = function (array $override = []) use ($selHash, $pwHash, $future) {
    return rememberMeRow($selHash, $pwHash, $future, $override);
};
$t->check(
    'a live token for its own user matches',
    $row()->matches(3, $selector, $password)
);
$t->check(
    'a token for another user does not match',
    !$row()->matches(4, $selector, $password)
);
$t->check(
    'a wrong selector does not match',
    !$row()->matches(3, 'nope', $password)
);
$t->check(
    'a wrong password does not match',
    !$row()->matches(3, $selector, 'nope')
);
$t->check(
    'a token flagged isExpired does not match',
    !$row(['isExpired' => 1])->matches(3, $selector, $password)
);
$t->check(
    'a token past its expiry does not match',
    !$row(['expire' => date('Y-m-d H:i:s', time() - 60)])
        ->matches(3, $selector, $password)
);
$t->check(
    'empty cookies do not match',
    !$row()->matches(3, '', '')
);
$t->check(
    'an unsaved row (no id) does not match',
    !$row(['id' => 0])->matches(3, $selector, $password)
);
$t->check(
    'user id 0 does not match a row for user 0',
    !$row(['userID' => 0])->matches(0, $selector, $password)
);

// ---------------------------------------------------------------------------
// 2. isLoggedIn() on an idle session.
// ---------------------------------------------------------------------------
// One hour timeout, regeneration far away so that branch stays out of it.
$settings = [
    'FOG_ALWAYS_LOGGED_IN' => '0',
    'FOG_INACTIVITY_TIMEOUT' => '1',
    'FOG_REGENERATE_TIMEOUT' => '24',
];
$db->responder = function ($sql) use ($settings, $selHash, $pwHash, $future) {
    if (false !== strpos($sql, '`globalSettings`')) {
        $rows = [];
        foreach ($settings as $key => $value) {
            $rows[] = ['settingKey' => $key, 'settingValue' => $value];
        }
        return $rows;
    }
    if (false !== strpos($sql, '`userAuths`')) {
        // One row, not a list: FOGController::load() fetches a single row.
        return [
            'uaID' => 7,
            'uaUserID' => 3,
            'uaExpireDate' => $future,
            'uaIsExpired' => 0,
            'uaSelectorHash' => $selHash,
            'uaPasswordHash' => $pwHash,
        ];
    }
    return null;
};
FogTestHarness::setStatic('FOGBase', 'service', true);

ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');
session_save_path(sys_get_temp_dir());
session_start();

/**
 * Run isLoggedIn() for user 3 with the given cookies and idle time.
 *
 * @param array $cookie the request's cookies
 * @param int   $idle   seconds since the last request
 * @param int   $uid    the signed-in user
 *
 * @return array [bool logged out, int lastactivity after]
 */
function idleRequest(array $cookie, $idle, $uid = 3)
{
    $_COOKIE = $cookie;
    $_SESSION = [
        'sessioncreated' => time(),
        'lastactivity' => time() - $idle,
    ];
    $user = new User();
    $user->set('id', $uid)->set('name', 'remembered');
    $user->isLoggedIn();
    $loggedOut = false;
    foreach ((array)($_SESSION['FOG_MESSAGES'] ?? []) as $msg) {
        if (false !== strpos(json_encode($msg), 'inactivity')) {
            $loggedOut = true;
        }
    }
    return [$loggedOut, (int)$_SESSION['lastactivity']];
}

$valid = [
    'foguserauthid' => '7',
    'foguserauthsel' => $selector,
    'foguserauthpass' => $password,
];

list($out) = idleRequest([], 7200);
$t->check('idle two hours with no cookie: logged out', $out);

list($out, $last) = idleRequest($valid, 7200);
$t->check('idle two hours with a valid cookie: NOT logged out', !$out);
$t->check(
    'idle two hours with a valid cookie: lastactivity refreshed',
    $last >= time() - 5
);

list($out) = idleRequest(['foguserauthsel' => $password] + $valid, 7200);
$t->check('idle two hours with a bad selector: logged out', $out);

list($out) = idleRequest($valid, 7200, 4);
$t->check('idle two hours, another user\'s valid cookie: logged out', $out);

list($out) = idleRequest(['foguserauthid' => '0'] + $valid, 7200);
$t->check('idle two hours with no token id: logged out', $out);

list($out) = idleRequest([], 60);
$t->check('active, no cookie: not logged out', !$out);

session_destroy();
$t->finish();
