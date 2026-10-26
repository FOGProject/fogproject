<?php
/**
 * API leftovers from the #1953 audit: unrouted requests, the unauth list,
 * and the pxemenuoptions list row.
 *
 * WHAT BROKE (GH-1953):
 *
 *   - A request no route matched (`GET /fog/host/abc`) answered 501, and so
 *     did a known path with the wrong verb. 501 means "not implemented";
 *     the norm is 404 for the first and 405 with an Allow header for the
 *     second, each with an `error` body like every other API error.
 *   - $unauthprefixes listed `storagegroupid` and `storagenodeid`. No route
 *     has either segment, so the entries did nothing except grant
 *     unauthenticated access to any route that might one day be added there.
 *   - The pxemenuoptions list replaced `regMenu` with its label, so the list
 *     sent a string while the single GET sent the integer.
 *
 * WHAT THIS PINS: 404 and 405 (with Allow) and the error bodies; a routed
 * request still reaches its handler instead of the 404/405 path; the dead
 * unauth entries are gone and `bandwidth` is kept; the regMenu list column
 * has no formatter and the label rides under `regMenuText`, which the grid
 * JS reads.
 *
 * setMatches() reads REQUEST_URI through filter_input(), which no CLI, CGI or
 * built-in-server SAPI lets a test set. So each case dispatches through the
 * real router itself, hands the result to Route the way setMatches() does
 * (a source check pins that line), and runs the real runMatches() in a
 * php-cgi child. DB-free: FogTestHarness::fakeDb().
 *
 * Usage: php tests/api-unrouted-and-leftovers.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$routeSrc = (string)file_get_contents($root . '/packages/web/src/Router/Route.php');

$childCase = isset($_GET['case']) ? (string)$_GET['case'] : null;
if (null !== $childCase) {
    runChild($childCase);
    exit(0);
}

/**
 * Dispatches one request the way Route::__construct() does, minus auth, and
 * prints one marker line. Under asValue() sendResponse() throws, so the
 * status and body are readable.
 *
 * @param string $case the case name (unused beyond the query string)
 *
 * @return void
 */
function runChild($case)
{
    FogTestHarness::boot('api-unrouted-child');
    FogTestHarness::fakeDb();
    $define = new \ReflectionMethod('FOG\Router\Route', 'defineRoutes');
    $define->setAccessible(true);
    $router = Route::newDispatcher(
        function (\FastRoute\RouteCollector $r) use ($define) {
            $define->invoke(null, $r);
        }
    );
    $parts = explode(' ', $case, 2);
    $result = $router->dispatch($parts[0], $parts[1]);
    if (\FastRoute\Dispatcher::FOUND === $result[0]) {
        // A routed request never reaches the 404/405 path.
        echo "ALLOWED\n";
        return;
    }
    FogTestHarness::setStatic('Route', 'matches', false);
    // Absent on a tree that predates GH-1953, where runMatches() sends 501.
    if (property_exists('FOG\Router\Route', '_unmatched')) {
        FogTestHarness::setStatic('Route', '_unmatched', $result);
    }
    try {
        Route::asValue(
            function () {
                Route::runMatches();
            }
        );
        echo "ALLOWED\n";
    } catch (\Throwable $e) {
        if ($e instanceof \RuntimeException) {
            $allow = '';
            foreach (headers_list() as $h) {
                if (0 === stripos($h, 'Allow:')) {
                    $allow = trim(substr($h, 6));
                }
            }
            echo 'STATUS ' . $e->getCode() . ' [' . $allow . '] '
                . str_replace("\n", ' ', $e->getMessage()) . "\n";
        } else {
            echo 'CRASH ' . get_class($e) . ' ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
        }
    }
}

/**
 * Run one request in a php-cgi child.
 *
 * @param string $method the request method
 * @param string $uri    the request URI
 *
 * @return string the marker line
 */
function child($method, $uri)
{
    // Routes are registered without the webroot, which setMatches() strips.
    $uri = preg_replace('#^/fog#', '', $uri);
    $bin = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => $method,
        'QUERY_STRING' => 'case=' . rawurlencode($method . ' ' . $uri),
        'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
    ];
    $pipes = [];
    $proc = proc_open(
        [$bin, '-q'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $env
    );
    if (!is_resource($proc)) {
        return 'SPAWN FAILED';
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    foreach (preg_split('/\r?\n/', $out) as $candidate) {
        if (preg_match('/^(STATUS|ALLOWED|CRASH)/', trim($candidate))) {
            return trim($candidate);
        }
    }
    return 'NO MARKER: ' . trim(str_replace("\n", ' | ', $out . ' ' . $err));
}

FogTestHarness::boot('api-unrouted');
FogTestHarness::fakeDb();
$t = new FogChecks();

if ('' === trim((string)@shell_exec('command -v php-cgi 2>/dev/null'))) {
    echo "SKIP: no php-cgi binary\n";
    exit(0);
}

// 1. Unrouted requests.
foreach (['/fog/host/abc', '/fog/host/current', '/fog/nosuchthing'] as $uri) {
    $line = child('GET', $uri);
    $ok = 1 === preg_match('/^STATUS 404 \[\] (.*)$/', $line, $m);
    $body = $ok ? json_decode($m[1], true) : null;
    $t->check("GET $uri answers 404 (got: $line)", $ok);
    $t->check(
        "GET $uri body has an error string",
        is_array($body) && is_string($body['error'] ?? null) && '' !== $body['error']
    );
}

// 2. Known path, wrong verb.
$line = child('DELETE', '/fog/system/status');
$ok = 1 === preg_match('/^STATUS 405 \[([^\]]+)\] (.*)$/', $line, $m);
$body = $ok ? json_decode($m[2], true) : null;
$t->check("DELETE /system/status answers 405 (got: $line)", $ok);
$t->check(
    'the 405 carries an Allow header naming GET',
    $ok && false !== strpos($m[1], 'GET')
);
$t->check(
    'the 405 body has an error string',
    is_array($body) && is_string($body['error'] ?? null) && '' !== $body['error']
);

// 3. A routed request is still routed.
$line = child('GET', '/fog/system/status');
$t->check(
    "GET /system/status is still routed (got: $line)",
    0 === strpos($line, 'ALLOWED')
);

// setMatches() must hand the dispatcher's verdict to runMatches().
$t->check(
    'setMatches() keeps the dispatcher result for runMatches()',
    1 === preg_match(
        '/self::\\$matches = false;\s*self::\\$_unmatched = \\$result;/',
        $routeSrc
    )
);

// 4. The dead unauthenticated prefixes are gone; bandwidth stays.
$t->check(
    'storagegroupid is not an unauth prefix',
    false === strpos($routeSrc, "'storagegroupid'")
);
$t->check(
    'storagenodeid is not an unauth prefix',
    false === strpos($routeSrc, "'storagenodeid'")
);
$t->check(
    'bandwidth is still an unauth prefix',
    false !== strpos($routeSrc, "\$webrootbase . 'bandwidth'")
);

// 5. pxemenuoptions list: regMenu stays the stored integer, label elsewhere.
$colSrc = $routeSrc;
$t->check(
    "regMenu column has no formatter under its own key",
    1 === preg_match(
        "/case 'regMenu':[^\\[]*\\\$columns\[\] = \[\s*'db' => \\\$real,\s*'dt' => \\\$common\s*\];/",
        $colSrc
    )
);
$t->check(
    'the label rides under regMenuText',
    false !== strpos($colSrc, "'dt' => 'regMenuText'")
);
$js = (string)file_get_contents(
    $root . '/packages/web/management/js/fog/ipxe/fog.ipxe.list.js'
);
$t->check(
    'the grid reads regMenuText',
    false !== strpos($js, "{data: 'regMenuText'}")
    && false === strpos($js, "{data: 'regMenu'}")
);

$t->finish();
