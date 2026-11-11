<?php
/**
 * Route::asValue() must not leave its JSON Content-Type on the page.
 *
 * names(), ids() and the other route helpers start with
 * header('Content-type: application/json'), because as API endpoints they
 * answer with JSON. asValue() runs them as a VALUE inside another request.
 * The header stayed, so an HTML page that called Route::getNames() went out
 * as application/json and the browser showed its source. The Printer
 * Deployment report did that whenever a listed host had a printer.
 *
 * WHAT THIS PINS: after an internal getNames(), the request keeps the
 * Content-Type it had, and a request with none set keeps the PHP default.
 * A direct call to names(), the API path, still sets JSON.
 *
 * The CLI SAPI records no headers, so each case runs in a php-cgi child (the
 * method of api-bad-request-statuses.test.php). DB-free: fakeDb().
 *
 * Usage: php tests/route-asvalue-keeps-content-type.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$childCase = isset($_GET['case']) ? (string)$_GET['case'] : null;

if (null !== $childCase) {
    FogTestHarness::boot('asvalue-child');
    FogTestHarness::fakeDb();
    $admin = (new User())->set('id', 1)->set('name', 'fog');
    foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
        FogTestHarness::setStatic($cls, 'FOGUser', $admin);
    }
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);
    try {
        switch ($childCase) {
            case 'html-page':
                header('Content-Type: text/html; charset=UTF-8');
                Route::getNames('printer', []);
                break;
            case 'no-type':
                Route::getNames('printer', []);
                break;
            case 'api':
                Route::names('printer');
                break;
        }
        $type = '(none)';
        foreach (headers_list() as $h) {
            if (0 === stripos($h, 'content-type:')) {
                $type = trim(substr($h, 13));
            }
        }
        echo "\nTYPE " . $type . "\n";
    } catch (\Throwable $e) {
        echo "\nCRASH " . get_class($e) . ' ' . $e->getMessage() . "\n";
    }
    exit(0);
}

/**
 * Run one case in a php-cgi child and return its marker line.
 *
 * @param string $case the case name
 *
 * @return string
 */
function child($case)
{
    $bin = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => 'GET',
        'QUERY_STRING' => 'case=' . rawurlencode($case),
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
    $out = (string)stream_get_contents($pipes[1]);
    $err = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    foreach (preg_split('/\r?\n/', $out) as $line) {
        if (preg_match('/^(TYPE|CRASH)/', trim($line))) {
            return trim($line);
        }
    }

    return 'NO MARKER: ' . trim(str_replace("\n", ' | ', $out . ' ' . $err));
}

FogTestHarness::boot('route-asvalue-keeps-content-type');
$t = new FogChecks();

if ('' === trim((string)@shell_exec('command -v php-cgi 2>/dev/null'))) {
    echo "SKIP: no php-cgi binary\n";
    exit(0);
}

$line = child('html-page');
$t->check(
    'an HTML page keeps text/html after an internal getNames() (got: '
        . $line . ')',
    0 === strpos($line, 'TYPE text/html')
);
$line = child('no-type');
// Not '(none)': with no type at all, nosniff makes the browser show text.
$t->check(
    'a request with no Content-Type keeps the PHP default after getNames() (got: '
        . $line . ')',
    0 === strpos($line, 'TYPE text/html')
);
$line = child('api');
$t->check(
    'a direct names() call, the API path, still answers JSON (got: '
        . $line . ')',
    'TYPE application/json' === $line
);

$t->finish();
