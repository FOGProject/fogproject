<?php
/**
 * A stale schema must not redirect the agent API.
 *
 * While `mySchema < FOG_SCHEMA`, DatabaseManager::init() sends every request
 * that is not on a small allowlist to the schema updater. That is right for
 * a browser and wrong for fog-agent, which speaks JSON over a client
 * certificate and has nowhere to land.
 *
 * It is worse than merely useless. The redirect is RELATIVE --
 * `../management/index.php?node=schema` -- so a client that follows
 * redirects resolves it against `<webroot>/agent/v1/` and asks for
 * `<webroot>/agent/management/index.php`, which exists nowhere. Observed in
 * the lab on 2026-09-04, from nginx's own error log:
 *
 *   FastCGI sent in stderr: "Primary script unknown" ...
 *   request: "POST /fog/agent/management/index.php?node=schema",
 *   referrer: "https://10.255.20.1/fog/agent/v1/poll"
 *
 * and on every agent in the estate, once per poll interval:
 *
 *   poll: HTTP 404, body is not JSON: File not found.
 *
 * Nothing in that sentence names a schema update, which is the cause and is
 * an entirely ordinary thing to be doing.
 *
 * What this pins:
 *
 * - The agent branch is decided BEFORE the redirect, or the redirect wins.
 * - It answers JSON with a status the agent can read, not HTML and not a
 *   Location header.
 * - Both places that recognize an agent route use the ONE definition,
 *   Route::AGENT_ROUTE_SEGMENT. They cannot share an anchoring -- Route
 *   anchors to the configured webroot, and this code path cannot, because
 *   FOG_WEB_ROOT is a globalSettings lookup and the schema is by definition
 *   not current here -- so a literal in either place is a drift waiting to
 *   happen, which is exactly what the iPXE blocklist was fixed for.
 *
 * Source-level: the branch runs only when the database is behind the code,
 * which no DB-free test can stage.
 *
 * Usage: php tests/agent-api-not-redirected-on-stale-schema.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-api-not-redirected-on-stale-schema');

$t = new FogChecks();

$root = dirname(__DIR__) . '/packages/web';

/**
 * The file's CODE, with comments and docblocks stripped.
 *
 * Because the first version of this test asserted with strpos() over the raw
 * file, and its own docblock -- which names Route::AGENT_ROUTE_SEGMENT while
 * explaining why it matters -- satisfied the check. Replacing the constant
 * with a literal, the exact drift this exists to catch, left the test green.
 * A guard that passes on prose about the guard is not a guard.
 *
 * @param string $path the file to read
 *
 * @return string
 */
function codeOf($path)
{
    $out = '';
    foreach (token_get_all((string)file_get_contents($path)) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }
    return $out;
}

$dbm = codeOf($root . '/src/Db/DatabaseManager.php');
$route = codeOf($root . '/src/Router/Route.php');

// ------------------------------------------------------- the one definition

$t->check(
    'Route declares the agent route segment as a constant',
    1 === preg_match(
        "/const\s+AGENT_ROUTE_SEGMENT\s*=\s*'agent\/v1\/'/",
        $route
    )
);

$t->check(
    'Route matches the prefix through that constant, not a literal',
    false !== strpos($route, '$webrootbase . self::AGENT_ROUTE_SEGMENT')
);

$t->check(
    'DatabaseManager matches through the same constant',
    false !== strpos($dbm, 'Route::AGENT_ROUTE_SEGMENT')
);

$t->check(
    'no literal agent/v1 survives in DatabaseManager, in any quoting. Route'
        . ' spelling the paths out is its route table, not drift; the far'
        . ' end holding a copy of the prefix is exactly the drift',
    0 === preg_match_all('#agent/v1#', $dbm)
);

// ------------------------------------------------- decided before the redirect

$agentAt = strpos($dbm, '$isAgentApi');
$redirectAt = strpos($dbm, "self::redirect('../management/index.php?node=schema')");

$t->check(
    'the redirect this guards against is still spelled the way the comment'
        . ' says, so the reasoning stays checkable',
    false !== $redirectAt
);

$t->check(
    'the agent branch is decided BEFORE the redirect, or the redirect wins',
    false !== $agentAt && false !== $redirectAt && $agentAt < $redirectAt
);

// ------------------------------------------------------------ what it answers

$t->check(
    'it answers 503, not a redirect and not the 404 the estate actually saw',
    false !== strpos(
        $dbm,
        'http_response_code(HTTPResponseCodes::HTTP_SERVICE_UNAVAILABLE)'
    )
);

$t->check(
    'it answers JSON',
    false !== strpos($dbm, "header('Content-type: application/json')")
);

$t->check(
    'it names a status the agent can branch on rather than only prose',
    false !== strpos($dbm, "'status' => 'schema_update_pending'")
);

$t->check(
    'and a human sentence, because the status word alone does not tell an'
        . ' admin to go and finish the upgrade',
    false !== strpos($dbm, "'error' => 'FOG is applying a database schema")
);

// ---------------------------------------------------------- and nothing more

$t->check(
    'the branch dies rather than falling through into the rest of init()',
    1 === preg_match(
        '/\$isAgentApi\)\s*\{.*?die\s*\(.*?\}/s',
        substr($dbm, $agentAt, 1400)
    )
);

$t->finish();
