<?php
/**
 * The rejoin start route has an explicit authentication-only permission
 * mapping (the client certificate gate in Route is its authority) and stays
 * attached to its actual POST route.
 *
 * Usage: php tests/agent-rejoin-start-route-permissions.test.php
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-rejoin-start-route-permissions');
FogTestHarness::fakeDb();

$t = new FogChecks();

$t->check(
    'agentrejoinstart resolves to authentication-only permission',
    null === FOG\Auth\Authorization::resolveApiPermission('agentrejoinstart', '')
);

/** Captures the actual method, pattern and route name passed to FastRoute. */
class AgentRejoinStartRouteCollector extends \FastRoute\RouteCollector
{
    public $routes = [];

    public function addRoute($httpMethod, $route, $handler)
    {
        $this->routes[] = [
            'methods' => (array)$httpMethod,
            'path' => (string)$route,
            'name' => is_array($handler) ? ($handler['name'] ?? null) : null
        ];
    }
}

$collector = new AgentRejoinStartRouteCollector(
    new \FastRoute\RouteParser\Std(),
    new \FastRoute\DataGenerator\GroupCountBased()
);
$defineRoutes = new \ReflectionMethod(FOG\Router\Route::class, 'defineRoutes');
$defineRoutes->setAccessible(true);
$defineRoutes->invoke(null, $collector);

$found = [];
foreach ($collector->routes as $route) {
    if ('agentrejoinstart' === $route['name']) {
        $found[] = $route;
    }
}
$t->check(
    'agentrejoinstart is registered once with its expected HTTP method and path',
    1 === count($found)
    && in_array('POST', $found[0]['methods'], true)
    && '/agent/v1/directory/rejoin/start' === $found[0]['path']
);

$t->finish();
