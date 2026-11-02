<?php
/**
 * The agent feature probe and identity check have explicit
 * authentication-only permission mappings and stay attached to their actual
 * GET/POST routes.
 *
 * Usage: php tests/agent-identity-route-permissions.test.php
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-identity-route-permissions');
FogTestHarness::fakeDb();

$t = new FogChecks();

foreach ([
    'agentfeatures',
    'agentidentitycheck'
] as $name) {
    $t->check(
        $name . ' resolves to authentication-only permission',
        null === FOG\Auth\Authorization::resolveApiPermission($name, '')
    );
}
$t->check(
    'agent enrollments keeps host.view',
    'host.view' === FOG\Auth\Authorization::resolveApiPermission(
        'agentenrollments',
        ''
    )
);
$t->check(
    'agent enrollment decisions keep host.edit',
    'host.edit' === FOG\Auth\Authorization::resolveApiPermission(
        'agentenrollmentdecide',
        ''
    )
);
$t->check(
    'unknown route names remain unmapped and denied',
    'unmapped.route.agent-identity-test-unknown'
        === FOG\Auth\Authorization::resolveApiPermission(
            'agent-identity-test-unknown',
            ''
        )
);

/** Captures the actual method, pattern and route name passed to FastRoute. */
class AgentIdentityPermissionRouteCollector extends \FastRoute\RouteCollector
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

$collector = new AgentIdentityPermissionRouteCollector(
    new \FastRoute\RouteParser\Std(),
    new \FastRoute\DataGenerator\GroupCountBased()
);
$defineRoutes = new \ReflectionMethod(FOG\Router\Route::class, 'defineRoutes');
$defineRoutes->setAccessible(true);
$defineRoutes->invoke(null, $collector);

$expectedRoutes = [
    'agentfeatures' => ['GET', '/agent/v1/enroll'],
    'agentidentitycheck' => ['POST', '/agent/v1/identity/check']
];
foreach ($expectedRoutes as $name => $expected) {
    $found = [];
    foreach ($collector->routes as $route) {
        if ($route['name'] === $name) {
            $found[] = $route;
        }
    }
    $t->check(
        $name . ' is registered once with its expected HTTP method and path',
        1 === count($found)
        && in_array($expected[0], $found[0]['methods'], true)
        && $expected[1] === $found[0]['path']
    );
}

$t->finish();
