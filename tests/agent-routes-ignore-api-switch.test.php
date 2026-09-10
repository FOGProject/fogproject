<?php
/**
 * The API switch must not switch off fog-agent.
 *
 * While FOG_API_ENABLED is off, Route answers with a 308 to the login page.
 * That check ran before anything else looked at the path, so it took
 * /agent/v1/ with it: on a server with the REST API off, every fog-agent was
 * redirected on enroll and on every poll, and could never join. Reported on
 * the forum as topic 18241:
 *
 *   POST https://192.168.1.100/fog/agent/v1/enroll
 *   HTTP/1.1 308 Permanent Redirect
 *   Location: https://192.168.1.100/fog/management/index.php
 *
 * The setting governs the REST API that people and API tokens use. The agent
 * routes are FOG's client channel and have their own gate.
 *
 * What this pins, each case in its own process because the gate exits:
 *
 * - enroll with the API off is not redirected;
 * - poll with the API off reaches the agent gate and gets its 401, which
 *   proves the request got past the switch and still needs a certificate;
 * - an ordinary API route with the API off is still redirected, so the
 *   switch still does its job.
 *
 * The request statics are preset rather than fed through a CGI environment.
 * Route's dispatcher reads REQUEST_METHOD through filter_input(), which the
 * CLI SAPI leaves empty, so no route matches and enroll's handler is never
 * reached. That is why the enroll case asserts only "not a redirect" and the
 * poll case carries the proof.
 *
 * Usage: php tests/agent-routes-ignore-api-switch.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$childCase = null;
foreach (array_slice(isset($argv) ? $argv : [], 1) as $arg) {
    if (0 === strpos($arg, '--case=')) {
        $childCase = substr($arg, 7);
    }
}
if (null !== $childCase) {
    runChild($childCase);
    exit(0);
}

/**
 * Construct Route for one request and print what it answered. Never
 * asserts -- the parent owns the verdict.
 *
 * @param string $case "<FOG_API_ENABLED> <request uri>"
 *
 * @return void
 */
function runChild($case)
{
    list($flag, $uri) = explode(' ', $case, 2);
    FogTestHarness::boot('agent-routes-ignore-api-switch');
    $db = FogTestHarness::fakeDb();
    $db->responder = function ($sql) use ($flag) {
        if (false === strpos($sql, 'globalSettings')) {
            return null;
        }
        return [
            ['settingKey' => 'FOG_API_ENABLED', 'settingValue' => $flag],
            ['settingKey' => 'FOG_API_TOKEN', 'settingValue' => 'test'],
            ['settingKey' => 'FOG_WEB_ROOT', 'settingValue' => '/fog/'],
        ];
    };
    foreach (
        [
            '_initialized' => true,
            'requesturi' => $uri,
            'reqmethod' => 'POST',
            'post' => true,
            'ajax' => false,
            'httpproto' => 'https',
            'httphost' => '192.168.1.100',
            'remoteaddr' => '192.168.102.20',
            'scriptname' => '/fog/api/index.php',
            'querystring' => '',
        ] as $property => $value
    ) {
        FogTestHarness::setStatic('FOGBase', $property, $value);
    }
    ob_start();
    register_shutdown_function(
        function () {
            $body = trim((string)ob_get_clean());
            echo 'RESULT ' . (int)http_response_code() . ' '
                . str_replace("\n", ' ', $body) . "\n";
        }
    );
    new Route();
}

/**
 * Run one request in a child process.
 *
 * @param string $flag FOG_API_ENABLED
 * @param string $uri  the request uri
 *
 * @return array [int status (0 when the child printed nothing), string body]
 */
function child($flag, $uri)
{
    $pipes = [];
    $proc = proc_open(
        [PHP_BINARY, __FILE__, '--case=' . $flag . ' ' . $uri],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($proc)) {
        return [0, 'SPAWN FAILED'];
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    if (preg_match('/^RESULT (\d+) ?(.*)$/m', (string)$out, $m)) {
        return [(int)$m[1], $m[2]];
    }
    return [0, 'NO RESULT: ' . trim(str_replace("\n", ' | ', $out . ' ' . $err))];
}

$t = new FogChecks();

list($code, $body) = child('0', '/fog/agent/v1/enroll');
$t->check(
    "enroll with the API off is not redirected (got $code $body)",
    0 !== $code && ($code < 300 || $code >= 400)
);

list($code, $body) = child('0', '/fog/agent/v1/poll');
$t->check(
    "poll with the API off reaches the agent gate and gets 401 (got $code)",
    401 === $code
);
$t->check(
    'and the agent gate is what answered, not a token check',
    false !== strpos($body, '"reason":"no_client_certificate"')
);

list($code, $body) = child('0', '/fog/host');
$t->check(
    "an API route with the API off is still redirected (got $code)",
    308 === $code
);

$t->finish();
