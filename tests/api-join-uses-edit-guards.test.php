<?php
/**
 * PUT /{class}/join must treat each object exactly like PUT /{class}/{id}.
 *
 * What broke (GH-1950): Route::_joiningUpdate() copied the body onto each
 * loaded object with a loop of its own. For every field the body did NOT
 * name it read the stored value and set() it back. That is not a no-op:
 * User::set() hashes any non-override write to 'password', so a join that
 * only changed a description re-hashed the stored hash and locked the user
 * out. The same loop never consulted serverOwnedFields(), so a server-owned
 * field that edit() refuses with a 400 was stored through the join. It also
 * skipped _assertEditName() and _assertTaskTypeOf().
 *
 * Why: the join had its own copy of the field loop instead of calling
 * _applyEditFields() and the assertions edit() uses.
 *
 * What this pins, driving _joiningUpdate() itself against a fake database:
 *
 *   1. A join body that names only `description` writes the stored bcrypt
 *      hash back byte for byte. Not re-hashed.
 *   2. A field the body does name still lands, so (1) cannot pass because
 *      the join stopped copying anything at all.
 *   3. A server-owned field (user.token) is refused with the same message
 *      edit() gives, and no row is written.
 *
 * DB-free. FogTestHarness::fakeDb() logs every statement and answers the
 * listem() read and the load by id.
 *
 * Usage: php tests/api-join-uses-edit-guards.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('join-edit-guards');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

$userVars = FOGCore::getClass('User', '', true);
$cols = $userVars['databaseFields'];
$hash = password_hash('secret', PASSWORD_BCRYPT);

// listem() reads through the PDO layer. Its default row fills `uId` with a
// marker string, which casts to 0 and drops the row, so name the id here.
$db->pdo->rowCount = 1;
$db->pdo->countValue = 0;
$db->pdo->rowFactory = function ($columns, $n) use ($cols) {
    $row = [];
    foreach ($columns as $col) {
        $row[$col] = '';
    }
    $row[$cols['id']] = 1;
    $row[$cols['name']] = 'probe';
    return $row;
};

// The load by id, through query(). Every column, so nothing reads as unset.
$userRow = [];
foreach ($cols as $column) {
    $userRow[$column] = '';
}
$userRow[$cols['id']] = 1;
$userRow[$cols['name']] = 'probe';
$userRow[$cols['password']] = $hash;
$userRow[$cols['token']] = 'stored-token';

// Every write to `users`, with its bound values.
$writes = [];
$db->responder = function ($sql, $params) use ($userRow, &$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|REPLACE)\b[^(]*`users`/i', $sql)) {
        $writes[] = $params;
    }
    if (false !== strpos($sql, 'FROM `users`')
        && false !== strpos($sql, ':id')
    ) {
        return $userRow;
    }
    return null;
};

$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

$classVars = FogTestHarness::callStatic('Route', '_classVars', ['User']);

/**
 * Run one join body. Returns the error message, or '' when it completed.
 */
$join = function ($body) use ($classVars, &$writes) {
    $writes = [];
    try {
        Route::asValue(
            function () use ($classVars, $body) {
                FogTestHarness::callStatic(
                    'Route',
                    '_joiningUpdate',
                    ['user', $classVars, (object)$body]
                );
            }
        );
    } catch (\Throwable $e) {
        return (string)$e->getMessage() . ' ' . (string)Route::getData();
    }
    return '';
};

$pass = ':' . $cols['password'] . '_insert';
$display = ':' . $cols['display'] . '_insert';

// 1. An unnamed password is written back exactly as stored.
$err = $join(['ids' => [1], 'description' => 'x']);
$t->check("join with only description completed (got: $err)", '' === $err);
$t->check(
    'join with only description wrote the user row once',
    1 === count($writes)
);
$stored = isset($writes[0][$pass]) ? $writes[0][$pass] : null;
$t->check(
    'join re-hashed the stored password hash, which locks the user out '
    . "(stored $hash, wrote " . var_export($stored, true) . ')',
    $hash === $stored
);

// 2. A named field still lands, and the password is still untouched.
$err = $join(['ids' => [1], 'display' => 'New Display']);
$t->check("join with display completed (got: $err)", '' === $err);
$t->check(
    'join no longer copies a field the body names',
    isset($writes[0][$display]) && 'New Display' === $writes[0][$display]
);
$t->check(
    'join with display re-hashed the stored password hash',
    isset($writes[0][$pass]) && $hash === $writes[0][$pass]
);

// 3. A server-owned field is refused like edit() refuses it.
$err = $join(['ids' => [1], 'token' => 'forged']);
$t->check(
    'join stored a server-owned field that edit() refuses '
    . "(got: '$err')",
    false !== strpos($err, 'is maintained by the server and cannot be set')
);
$t->check(
    'join wrote the user row although the body was refused',
    0 === count($writes)
);

$t->finish();
