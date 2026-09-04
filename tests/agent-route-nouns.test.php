<?php
/**
 * No /agent/v1/ route names a data-model noun.
 *
 * The agent surface grows by VALUE, not by path. A new capability, artifact
 * type or report kind is a new value of `capability` in the routes that
 * exist (poll, result, payload) and a new block in the desired state; a new
 * route is justified only by a new transport shape: a new verb, a binary
 * payload, a different trust boundary. The rule lives in the agent's
 * docs/design/protocol-v1.md ("The route rule"); this is its gate, because a
 * prose rule in a protocol document decays and this codebase's discipline
 * lives in tests. The tell that a proposal breaks the rule is a noun from the
 * data model in the path, the shape snapin/{id}/result and
 * software/{id}/result had before they were folded into result.
 *
 * The noun list is Route::$validClasses, the classes the REST API exposes,
 * so it grows with the model rather than with someone's memory. A class
 * name's plural counts too ("snapins/{id}" is the same escape). Literal
 * segments only: a {parameter} is a value, not a path.
 *
 * DB-free: the route table is built from class properties, captured on its
 * way into FastRoute by a collector that keeps every pattern it is handed.
 *
 * Usage: php tests/agent-route-nouns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-route-nouns');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Keeps the pattern of every route registered on it. Nothing is dispatched,
 * so the parent's parse is skipped: the patterns are what is under test.
 */
class NounCollector extends \FastRoute\RouteCollector
{
    /** @var string[] */
    public $paths = [];

    /**
     * @param string|string[] $httpMethod verb(s)
     * @param string          $route      the FastRoute pattern
     * @param mixed           $handler    the target
     *
     * @return void
     */
    public function addRoute($httpMethod, $route, $handler)
    {
        $this->paths[] = (string)$route;
    }
}

/**
 * The /agent/v1/ paths whose literal segments name a class.
 *
 * @param string[] $paths the route patterns
 * @param string[] $nouns lowercase class names
 *
 * @return string[] the offending paths, in table order
 */
function offendingAgentPaths(array $paths, array $nouns)
{
    $bad = [];
    foreach ($paths as $path) {
        if (0 !== strpos($path, '/agent/v1/')) {
            continue;
        }
        $segments = explode('/', substr($path, strlen('/agent/v1/')));
        foreach ($segments as $segment) {
            if ('' === $segment || false !== strpos($segment, '{')) {
                continue;
            }
            $word = strtolower($segment);
            $singular = preg_replace('/s$/', '', $word);
            if (in_array($word, $nouns, true) || in_array($singular, $nouns, true)) {
                $bad[] = $path;
                break;
            }
        }
    }
    return $bad;
}

$collector = new NounCollector(
    new \FastRoute\RouteParser\Std(),
    new \FastRoute\DataGenerator\GroupCountBased()
);
$define = new \ReflectionMethod('FOG\Router\Route', 'defineRoutes');
$define->setAccessible(true);
$define->invoke(null, $collector);

$nouns = array_map('strtolower', \FOG\Router\Route::$validClasses);
$agent = array_values(
    array_filter(
        $collector->paths,
        static function ($p) {
            return 0 === strpos($p, '/agent/v1/');
        }
    )
);

// The two inputs are real before the verdict means anything: an empty
// route capture or an empty noun list would pass the gate for nothing.
$t->check(
    'the agent surface was captured (enroll, poll, result, payload, renew)',
    count($agent) >= 5
);
$t->check(
    'the noun list is the model (host and snapin are in it)',
    in_array('host', $nouns, true) && in_array('snapin', $nouns, true)
);

$offending = offendingAgentPaths($collector->paths, $nouns);
$t->check(
    'no /agent/v1/ path names a data-model noun'
        . ([] === $offending ? '' : ': ' . implode(', ', $offending)),
    [] === $offending
);

// The gate has teeth: the shapes the fold removed, and a plural, are exactly
// what it flags; a value segment and the admin surface are not its business.
$t->check(
    'the gate flags the pre-fold shapes and nothing else',
    [
        '/agent/v1/snapin/{id}/result',
        '/agent/v1/software/{id}/result',
        '/agent/v1/snapins/{id}',
    ] === offendingAgentPaths(
        [
            '/agent/v1/snapin/{id}/result',
            '/agent/v1/software/{id}/result',
            '/agent/v1/snapins/{id}',
            '/agent/v1/payload/{capability:[0-9A-Za-z]++}/{id:[0-9]++}',
            '/agent/v1/result',
            '/agent/enrollments',
            '/host/{id:[0-9]++}',
        ],
        $nouns
    )
);

$t->finish();
