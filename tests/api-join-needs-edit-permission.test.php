<?php
/**
 * PUT /{class}/join is a bulk EDIT and needs {class}.edit (GH-1950).
 *
 * Both verbs of /{class}/join shared the route name 'join', which
 * API_ROUTE_ACTIONS mapped to 'create'. So a role that may create hosts but
 * not edit them could still rewrite every existing host through the bulk
 * edit. Create means net new.
 *
 * Pinned here:
 *   - the router names PUT /{class}/join 'joinupdate' and POST 'join';
 *   - 'joinupdate' resolves to {class}.edit, 'join' to {class}.create;
 *   - POST /group/join naming an EXISTING group (it adds hosts to it, which
 *     edits it) is refused without group.edit, and allowed with it.
 *
 * Usage: php tests/api-join-needs-edit-permission.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-join-edit-permission');
FogTestHarness::fakeDb();

use FOG\Auth\Authorization;
use FOG\Items\User;
use FOG\Router\Route;

$t = new FogChecks();

// The route names the router hands the permission check.
$define = new \ReflectionMethod(Route::class, 'defineRoutes');
$define->setAccessible(true);
$router = Route::newDispatcher(
    function (\FastRoute\RouteCollector $r) use ($define) {
        $define->invoke(null, $r);
    }
);
$put = $router->dispatch('PUT', '/host/join');
$post = $router->dispatch('POST', '/group/join');
$t->check('PUT /host/join is named joinupdate', 'joinupdate' === ($put[1]['name'] ?? null));
$t->check('POST /group/join is named join', 'join' === ($post[1]['name'] ?? null));

// The permission each name requires.
$t->check(
    'PUT /host/join needs host.edit',
    'host.edit' === Authorization::resolveApiPermission('joinupdate', 'host')
);
$t->check(
    'POST /group/join needs group.create',
    'group.create' === Authorization::resolveApiPermission('join', 'group')
);

// POST /group/join on a name that already exists edits that group.
$user = (new User())->set('id', 7)->set('name', 'creator');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $user);
}
$existing = new class {
    public function exists($name)
    {
        return true;
    }
};

/**
 * Run _joiningCreate() for one existing group name with the given grants.
 *
 * @param array  $perms    the permissions user 7 holds
 * @param object $existing a manager whose exists() always answers true
 *
 * @return string the refusal message, or '' when the permission check passed
 */
function joinExisting(array $perms, $existing)
{
    FogTestHarness::setStatic('Authorization', '_permCache', [7 => $perms]);
    try {
        // asValue() turns sendResponse()'s exit into a throw, so a request
        // that gets past the check and fails later on the fake DB returns.
        Route::asValue(
            function () use ($existing) {
                FogTestHarness::callStatic(
                    'Route',
                    '_joiningCreate',
                    ['group', $existing, (object)['names' => ['lab'], 'hosts' => []]]
                );
            }
        );
    } catch (\Throwable $e) {
        if (false !== stripos($e->getMessage(), 'permission')) {
            return $e->getMessage();
        }
    }
    return '';
}

$t->check(
    'existing group with only group.create: refused',
    '' !== joinExisting(['group.create'], $existing)
);
$t->check(
    'existing group with group.create and group.edit: allowed',
    '' === joinExisting(['group.create', 'group.edit'], $existing)
);

$t->finish();
