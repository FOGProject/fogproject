<?php
/**
 * The three plugin extension points, and the gates on each.
 *
 * G1 a route, G2 a session-less page node, G3 a login-page button. A plugin
 * could contribute none of them before Phase 2, which is why an OIDC provider
 * could not be a plugin at all.
 *
 * The headline is G1: a plugin may contribute a route, but not an ungated one.
 *
 * This is the check the Phase 2 plan asks to be done by hand before the seam
 * is accepted, written down so it is done every time instead: PR 2.2 puts a
 * new way to reach PHP into the plugin system, and Phase 1 spent real effort
 * closing exactly that shape of hole. The claim that would hurt most if false
 * is "a plugin-contributed route can be gated as safely as a core route", so
 * the fixture that matters most here is the one that declares nothing.
 *
 * No database: Route::pluginRoutes() only fires a hook and validates what
 * comes back, and the branches of Authorization::resolveApiPermission() this
 * exercises return before touching the registry. The hook manager is a stub
 * injected by reflection, which is also the only way to drive the seam
 * without booting the whole application.
 *
 * Usage: php tests/plugin-extension-points.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$tmp = sys_get_temp_dir() . '/fog-plugin-route-' . getmypid();
@mkdir($tmp . '/cache', 0777, true);
@mkdir($tmp . '/log', 0777, true);
@mkdir($tmp . '/plugins', 0777, true);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');

$errLog = $tmp . '/php-error.log';
ini_set('error_log', $errLog);

/*
 * A fixture plugin, laid out exactly as ADR 0035 requires:
 * <plugin>/src/<Bucket>/<Class>.php declaring
 * FOG\Plugins\<Segment>\<Bucket>\<Class>, with the directory name being the
 * lowercased segment.
 *
 * It exists so one case below can declare a route handler the way a real
 * plugin does -- ['BareName', 'method'] -- rather than the way every fixture
 * in this file used to: 'strlen', a global function. That difference is not
 * cosmetic. A global function is callable under its bare name and a plugin
 * class is not, so the whole handler arm of this gate was green against a
 * shape no plugin has ever used, and it stayed green while OIDC's two routes
 * were being dropped on every request.
 */
@mkdir($tmp . '/plugins/fixtureplug/config', 0777, true);
@mkdir($tmp . '/plugins/fixtureplug/src/Util', 0777, true);
file_put_contents(
    $tmp . '/plugins/fixtureplug/config/plugin.config.php',
    '<?php' . "\n" . '$fog_plugin[\'name\'] = \'fixtureplug\';' . "\n"
);
file_put_contents(
    $tmp . '/plugins/fixtureplug/src/Util/FixtureFlow.php',
    '<?php' . "\n"
    . 'namespace FOG\\Plugins\\FixturePlug\\Util;' . "\n"
    . 'class FixtureFlow' . "\n" . '{' . "\n"
    . '    public static function start()' . "\n" . '    {' . "\n"
    . '    }' . "\n" . '}' . "\n"
);

require $web . '/commons/init.php';
new Initiator();

$fails = [];

/**
 * Stands in for HookManager. Hands back whatever declarations the current
 * case is testing; the routes element arrives by reference, exactly as the
 * real manager passes it.
 */
class StubHookManager
{
    public $routes = [];

    public $exemptNodes = [];
    public $providers = [];

    public function processEvent($event, $arguments = [])
    {
        if ('API_PLUGIN_ROUTES' === $event && isset($arguments['routes'])) {
            $arguments['routes'] = $this->routes;
        }
        if ('PAGE_EXEMPT_NODES' === $event && isset($arguments['nodes'])) {
            $arguments['nodes'] = $this->exemptNodes;
        }
        if ('LOGIN_PAGE_PROVIDERS' === $event && isset($arguments['providers'])) {
            $arguments['providers'] = $this->providers;
        }
    }
}

$stub = new StubHookManager();
$hook = new \ReflectionProperty('FOG\Base\FOGBase', 'HookManager');
$hook->setAccessible(true);
$hook->setValue(null, $stub);

$cache = new \ReflectionProperty('FOG\Router\Route', '_pluginRoutes');
$cache->setAccessible(true);

/**
 * Declare a set of routes and get back what the router would register.
 *
 * @param array $routes Declarations as a plugin would supply them.
 *
 * @return array Normalized routes, keyed by their registered name.
 */
$offer = function (array $routes) use ($stub, $cache) {
    $stub->routes = $routes;
    $cache->setValue(null, null);
    $out = [];
    foreach (\FOG\Router\Route::pluginRoutes() as $r) {
        $out[$r['name']] = $r;
    }
    return $out;
};

// ---------------------------------------------------------- the mount point

if ('/ext/' !== \FOG\Router\Route::PLUGIN_ROUTE_PREFIX) {
    $fails[] = 'the plugin route prefix moved; the rest of this test, and the'
        . ' guarantee that plugin paths cannot collide with core ones, is'
        . ' written around /ext/';
}
$classes = new \ReflectionProperty('FOG\Router\Route', 'validClasses');
$classes->setAccessible(true);
$valid = array_map('strtolower', (array) $classes->getValue());
if (in_array(trim(\FOG\Router\Route::PLUGIN_ROUTE_PREFIX, '/'), $valid, true)) {
    $fails[] = 'an API class is now named after the plugin route mount point,'
        . ' so core CRUD routes and plugin routes share a path prefix -- the'
        . ' collision the reserved mount point exists to prevent';
}

// ------------------------------------------------------------- the fixtures

$got = $offer([
    // Declares nothing beyond the minimum. THE case: it must be registered
    // (so it answers 403 with a diagnostic, not 404 with silence) and it must
    // resolve to a permission no role can hold.
    [
        'name' => 'silent',
        'path' => '/ext/demo/silent',
        'handler' => 'strlen',
    ],
    // Properly declared, authenticated.
    [
        'name' => 'guarded',
        'path' => '/ext/demo/guarded',
        'method' => 'post',
        'handler' => 'strlen',
        'permission' => 'demo.edit',
    ],
    // Properly declared, deliberately public -- an IdP callback shape.
    [
        'name' => 'callback',
        'path' => '/ext/demo/callback',
        'handler' => 'strlen',
        'auth' => 'public',
    ],
    // Public, but with URL parameters, which the exact-match unauth test
    // cannot express. Must fall back to authenticated rather than pretend.
    [
        'name' => 'paramPublic',
        'path' => '/ext/demo/[i:id]',
        'handler' => 'strlen',
        'auth' => 'public',
    ],
    // 'auth' misspelt. Must NOT be read as anything but 'required'.
    [
        'name' => 'typo',
        'path' => '/ext/demo/typo',
        'handler' => 'strlen',
        'auth' => true,
    ],
    // Outside the mount point. Dropped.
    [
        'name' => 'escapee',
        'path' => '/system/export',
        'handler' => 'strlen',
        'auth' => 'public',
    ],
    // Traversal in the path. Dropped.
    [
        'name' => 'traversal',
        'path' => '/ext/../system/export',
        'handler' => 'strlen',
        'auth' => 'public',
    ],
    // Handler that is not callable. Dropped.
    [
        'name' => 'broken',
        'path' => '/ext/demo/broken',
        'handler' => 'no_such_function_anywhere',
    ],
    // THE shape every real plugin uses: [class, method] with the class
    // spelled BARE. is_callable() resolves a class name in a string against
    // the GLOBAL namespace, so this is not callable as written -- the router
    // has to qualify it first, exactly as getClass() does everywhere else.
    // Must be registered, with the handler normalized to the FQCN so the
    // later dispatch calls something that exists.
    [
        'name' => 'bareClass',
        'path' => '/ext/demo/bare',
        'handler' => ['FixtureFlow', 'start'],
        'auth' => 'public',
    ],
    // A [class, method] naming a class no plugin declares. Qualifying passes
    // an unknown name through unchanged, so this must still be dropped --
    // resolving bare names must not become a way to register a handler that
    // does not exist.
    [
        'name' => 'ghostClass',
        'path' => '/ext/demo/ghost',
        'handler' => ['NoSuchPluginClassAnywhere', 'start'],
    ],
]);

$expectDropped = ['ext:escapee', 'ext:traversal', 'ext:broken', 'ext:ghostClass'];
foreach ($expectDropped as $name) {
    if (isset($got[$name])) {
        $fails[] = "$name should have been rejected but was registered at "
            . $got[$name]['path'];
    }
}
foreach (['ext:silent', 'ext:guarded', 'ext:callback', 'ext:bareClass'] as $name) {
    if (!isset($got[$name])) {
        $fails[] = "$name should have been registered and was not";
    }
}
if (isset($got['ext:guarded']) && 'POST' !== $got['ext:guarded']['method']) {
    $fails[] = 'method was not normalized to upper case';
}
foreach (['ext:silent', 'ext:typo', 'ext:paramPublic'] as $name) {
    if (isset($got[$name]) && 'required' !== $got[$name]['auth']) {
        $fails[] = "$name is not authenticated; anything but the exact string"
            . " 'public' must mean authenticated";
    }
}
if (isset($got['ext:callback']) && 'public' !== $got['ext:callback']['auth']) {
    $fails[] = 'a correctly declared public route was not treated as public,'
        . ' so a callback that cannot carry credentials is unreachable';
}

/*
 * The bare [class, method] handler survives, AND comes back qualified.
 *
 * Both halves are the assertion. Registering it while leaving the class bare
 * would move the failure from validation to dispatch, where it is a fatal on
 * a live request rather than a dropped route -- so checking only that the
 * route exists would pass a fix that is still broken.
 *
 * This is the regression that took OIDC's /ext/oidc/start and
 * /ext/oidc/callback off the router entirely under ADR 0035. A signed-out
 * browser was redirected to start by LOGIN_PAGE_REDIRECT, got 401 because
 * the route was no longer registered, and had no way back in but
 * management/login.php.
 */
if (isset($got['ext:bareClass'])) {
    $handler = $got['ext:bareClass']['handler'];
    if (!is_array($handler) || !is_callable($handler)) {
        $fails[] = 'a bare [class, method] handler was registered but is not'
            . ' callable as stored, so dispatch would fatal: '
            . var_export($handler, true);
    } elseif ('FOG\\Plugins\\FixturePlug\\Util\\FixtureFlow' !== $handler[0]) {
        $fails[] = 'a bare handler class was not qualified; got '
            . var_export($handler[0], true);
    }
}

// ------------------------------------------------- what the router enforces

// Registration is where a route and its permission are decided together, so
// replay that step and then ask Authorization the question the dispatcher asks.
foreach ($got as $r) {
    if ('public' === $r['auth']) {
        \FOG\Auth\Authorization::declareRoutePermission($r['name'], null);
    } elseif (null !== $r['permission']) {
        \FOG\Auth\Authorization::declareRoutePermission($r['name'], $r['permission']);
    }
}

$resolved = function ($name) {
    return \FOG\Auth\Authorization::resolveApiPermission($name, '');
};

// THE assertion. A route that declared no permission must not be allowed.
$silent = $resolved('ext:silent');
if (null === $silent) {
    $fails[] = 'a plugin route that declared no permission resolves to NO'
        . ' CHECK. The seam is inverted: open unless declared. This is the'
        . ' failure the whole design is arranged to prevent.';
} elseif (0 !== strpos((string) $silent, 'unmapped.')) {
    $fails[] = 'a plugin route that declared no permission resolved to '
        . var_export($silent, true) . ', expected an unmapped.* permission'
        . ' that no role can be granted';
}
// The same, for a name never offered at all.
if (null === $resolved('ext:neverHeardOf')) {
    $fails[] = 'an unknown route name resolves to NO CHECK';
}
if ('demo.edit' !== $resolved('ext:guarded')) {
    $fails[] = 'a declared permission did not survive registration: got '
        . var_export($resolved('ext:guarded'), true);
}
if (null !== $resolved('ext:callback')) {
    $fails[] = 'a public route demands a permission, so the unauthenticated'
        . ' caller it exists for can never satisfy it';
}

// A plugin must not be able to redeclare a core route's permission.
\FOG\Auth\Authorization::declareRoutePermission('status', 'demo.edit');
if (null !== $resolved('status')) {
    $fails[] = "a plugin overwrote a core route's permission";
}
\FOG\Auth\Authorization::declareRoutePermission('list', null);
if (null === $resolved('list')) {
    $fails[] = 'a plugin turned off the permission check on core\'s list route';
}

// Every rejection must be explained. A dropped route is otherwise a 404 with
// no cause, which is the hardest possible thing for a plugin author to debug.
$log = is_readable($errLog) ? (string) file_get_contents($errLog) : '';
foreach (['/system/export', '/ext/demo/broken', '/ext/demo/[i:id]'] as $needle) {
    if (false === strpos($log, $needle)) {
        $fails[] = "rejecting $needle was not logged";
    }
}

// ------------------------------------------------------ G2: exempt nodes

$stub->exemptNodes = [
    'oidcstart',        // a node of its own -- allowed
    'host',             // a registered core node -- must be refused
    'about',            // a NODE_ALIASES key -- must be refused
    '  OidcStart  ',    // same as the first once trimmed and lowered
    '',                 // noise
];
$exemptCache = new \ReflectionProperty('FOG\Auth\Authorization', '_exemptNodes');
$exemptCache->setAccessible(true);
$exemptCache->setValue(null, null);
$exempt = \FOG\Auth\Authorization::exemptNodes();

foreach (\FOG\Auth\Authorization::EXEMPT_NODES as $core) {
    if (!in_array($core, $exempt, true)) {
        $fails[] = "core exempt node $core was lost when plugin entries were"
            . ' merged';
    }
}
if (!in_array('oidcstart', $exempt, true)) {
    $fails[] = 'a plugin could not exempt a node of its own, so an'
        . ' authentication provider still has nowhere to put a pre-login page';
}
if (in_array('host', $exempt, true)) {
    $fails[] = 'a plugin exempted "host" -- the permission check on every host'
        . ' page can be switched off by any registered hook';
}
if (in_array('about', $exempt, true)) {
    $fails[] = 'a plugin exempted an aliased core node';
}
if (count($exempt) !== count(array_unique($exempt))) {
    $fails[] = 'exemptNodes() returned duplicates';
}

// ------------------------------------------------- G3: login page providers

$stub->providers = [
    ['label' => 'Sign in with Acme', 'url' => '/fog/ext/oidc/start', 'icon' => 'fa fa-key'],
    ['label' => 'Remote IdP', 'url' => 'https://idp.example/start'],
    ['label' => 'XSS', 'url' => 'javascript:alert(1)'],
    ['label' => 'Data URI', 'url' => 'data:text/html;base64,PHNjcmlwdD4='],
    ['label' => 'Cleartext', 'url' => 'http://idp.example/start'],
    ['label' => 'Protocol relative', 'url' => '//evil.example/start'],
    ['label' => '<img src=x onerror=alert(1)>', 'url' => '/fog/ext/ok'],
    ['label' => 'Bad icon', 'url' => '/fog/ext/ok2', 'icon' => '" onmouseover="alert(1)'],
];
$html = \FOG\Pages\ProcessLogin::loginProviders();

foreach (['javascript:', 'data:text/html', 'http://idp.example', '//evil.example'] as $bad) {
    if (false !== strpos($html, $bad)) {
        $fails[] = "a login provider URL of $bad was rendered on the one page"
            . ' every unauthenticated visitor sees';
    }
}
if (false === strpos($html, '/fog/ext/oidc/start')
    || false === strpos($html, 'https://idp.example/start')
) {
    $fails[] = 'a legitimate provider button was not rendered';
}
if (false !== strpos($html, '<img src=x')
    || false !== strpos($html, 'onmouseover=')
) {
    $fails[] = 'provider-supplied markup reached the page unescaped';
}
$stub->providers = [];
if ('' !== \FOG\Pages\ProcessLogin::loginProviders()) {
    $fails[] = 'the login form grew a divider with no providers behind it';
}

$rmrf = function ($dir) use (&$rmrf) {
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff((array) scandir($dir), ['.', '..']) as $e) {
        $p = "$dir/$e";
        is_dir($p) ? $rmrf($p) : unlink($p);
    }
    rmdir($dir);
};
$rmrf($tmp);

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: routes, exempt nodes and login buttons are all gated\n";
exit(0);
