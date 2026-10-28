<?php
/**
 * A user export imports back, and the password hash leaves only on request
 * (GH-1987).
 *
 * THE BUG THIS GUARDS. The user export drops uPass, as every export drops
 * its secrets. But password is a required field, so every row of that file
 * failed to import with "Required database field is empty". The import also
 * stored a password column raw, so a typed password became a "hash" nothing
 * could ever match.
 *
 * What is pinned here:
 *   - user.export exists, and holding user.view does not grant it;
 *   - the "Include password hashes" box shows only to a holder of it;
 *   - the export keeps the password column only when told to, and never the
 *     API token;
 *   - User::isStoredHash() tells a stored hash from a typed password.
 *
 * importPost() itself and the POSTed flag cannot run here: the fake DB does
 * not answer exists()/save() as a server does, and filter_input() never sees
 * a CLI request. The round trip is checked against a live server instead.
 *
 * Usage: php tests/user-export-import-passwords.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('user-export-import-passwords');
FogTestHarness::fakeDb();

$t = new FogChecks();

$user = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $user);
}

/**
 * Sets the acting user's permissions for the checks that follow.
 *
 * @param array $perms the permission strings to hold
 *
 * @return void
 */
function grant(array $perms)
{
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => $perms]);
}

$page = (new \ReflectionClass('FOG\\Pages\\UserManagement'))
    ->newInstanceWithoutConstructor();
$childClass = new \ReflectionProperty('FOG\\Base\\FOGPage', 'childClass');
$childClass->setAccessible(true);
$childClass->setValue($page, 'User');

/**
 * Calls one of the page's non-public methods.
 *
 * @param string $method the method name
 * @param array  $args   its arguments
 *
 * @return mixed
 */
function call($method, array $args = [])
{
    global $page;
    $m = new \ReflectionMethod($page, $method);
    $m->setAccessible(true);
    return $m->invokeArgs($page, $args);
}

/**
 * The column keys the export would write.
 *
 * @param array $keep the secrets to keep
 *
 * @return array
 */
function exportKeys(array $keep)
{
    list(, , $columns) = call('_buildExportColumns', [$keep]);
    return array_column($columns, 'dt');
}

/*
 * 1. The permission exists, and the lesser grants do not reach it.
 */
$t->check(
    'user.export is a declared action',
    in_array('export', Authorization::coreRegistry()['user'], true)
);
grant(['user.view', 'user.edit']);
$t->check('user.view + user.edit do not hold user.export', !Authorization::can('user.export'));
grant(['user.view', 'user.export']);
$t->check('user.export holds user.export', Authorization::can('user.export'));
grant(['*']);
$t->check('* holds user.export', Authorization::can('user.export'));

/*
 * 2. The box shows only to a holder of user.export.
 */
grant(['user.view']);
$t->check('no box for a user.view holder', '' === call('exportOptions'));
grant(['user.view', 'user.export']);
$html = call('exportOptions');
$t->check(
    'the box is a fog-export-option named includehashes',
    false !== strpos($html, 'fog-export-option')
    && false !== strpos($html, 'name="includehashes"')
);

/*
 * 3. The column set: no secret by default; the password, and nothing else,
 *    when it is kept.
 */
$plain = exportKeys([]);
$t->check('default export has no password', !in_array('password', $plain, true));
$t->check('default export has no token', !in_array('token', $plain, true));
$kept = exportKeys(['password']);
$t->check('kept export has the password', in_array('password', $kept, true));
$t->check('kept export still has no token', !in_array('token', $kept, true));
$t->check(
    'the page keeps nothing when the box was not posted',
    [] === call('exportSecretsToKeep')
);

/*
 * 4. A stored hash is recognized; a typed password is not.
 */
$t->check('bcrypt is a stored hash', User::isStoredHash(User::generateHash('x')));
$t->check(
    '1.5 MD5 is a stored hash',
    User::isStoredHash('5f4dcc3b5aa765d61d8327deb882cf99')
);
$t->check('a typed password is not', !User::isStoredHash('password'));
$t->check('an empty value is not', !User::isStoredHash(''));

FogTestHarness::cleanup();
$t->finish();
