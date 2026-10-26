<?php
/**
 * Bad API requests answer 4xx with an `error` body, not a bodyless 500.
 *
 * WHAT BROKE (GH-1952):
 *
 *   - A create or edit body that is not a JSON object reached
 *     property_exists() as null. That throws a TypeError, which the routes'
 *     `catch (\Exception)` misses, so the client got a 500 with no body.
 *   - A create without a required field was never refused. get() answers ''
 *     for an unset field and _assertCreateRequired() tested for null, so
 *     save() failed later as a 500.
 *   - A duplicate name answered 500 `Already created`; it is a conflict, 409.
 *   - An empty name on edit answered 403, which means "not permitted"; 400.
 *   - GET /logfiles/{id} for a missing node answered 200 with no body; 404.
 *
 * WHAT THIS PINS: each case's status code, and that the body decodes to a
 * JSON object with an `error` string. An edit with a valid body is still
 * allowed through, so the guard is not a blanket refusal.
 *
 * Route reads its body from php://input, which the CLI SAPI does not fill,
 * so each case runs in a php-cgi child (same method as
 * route-write-path-guards.test.php). DB-free: FogTestHarness::fakeDb().
 *
 * Usage: php tests/api-bad-request-statuses.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$childCase = null;
foreach (array_slice(isset($argv) ? $argv : [], 1) as $arg) {
    if (0 === strpos($arg, '--case=')) {
        $childCase = substr($arg, 7);
    }
}
if (null === $childCase && isset($_GET['case'])) {
    $childCase = (string)$_GET['case'];
}
if (null !== $childCase) {
    runChild($childCase);
    exit(0);
}

/**
 * One case, in its own process. Prints one marker line; the parent judges.
 *
 * Cases are `<verb>:<class>[:<id>][:<fixture>]`. Fixture `taken` makes every
 * name look already used; `missing` makes every row lookup find nothing.
 *
 * @param string $case the case name
 *
 * @return void
 */
function runChild($case)
{
    FogTestHarness::boot('bad-request-child');
    FogTestHarness::setStatic(
        'FOGBase',
        'reqmethod',
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    );
    $parts = explode(':', $case);
    $fixture = isset($parts[3]) ? $parts[3] : '';
    $db = FogTestHarness::fakeDb();
    $db->pdo->rowCount = 'missing' === $fixture ? 0 : 1;
    // A create asks "does this name exist" as a COUNT through the PDO layer.
    $db->pdo->countValue = 'taken' === $fixture ? 1 : 0;
    // The row an edit loads. Built from $databaseFields so a schema change
    // cannot leave this describing a table that no longer exists. Stored
    // name is 'probe'.
    $row = [];
    $vars = FOGCore::getClass('OS', '', true);
    foreach ($vars['databaseFields'] as $column) {
        $row[$column] = '';
    }
    $row[$vars['databaseFields']['id']] = 100;
    $row[$vars['databaseFields']['name']] = 'probe';
    $db->responder = function ($sql, $params) use ($row, $fixture) {
        if ('missing' !== $fixture
            && false !== strpos($sql, 'FROM `os`')
            && false !== strpos($sql, ':id')
            // The name-exists COUNT carries :id too; it is not the load.
            && false === strpos($sql, 'COUNT(')
        ) {
            return $row;
        }
        // exists() reads the COUNT's `total` column.
        if ('taken' === $fixture && false !== strpos($sql, 'COUNT(')) {
            return [['total' => 1]];
        }
        return null;
    };
    $admin = (new \FOG\Items\User())->set('id', 1)->set('name', 'fog');
    foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
        FogTestHarness::setStatic($cls, 'FOGUser', $admin);
    }
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

    try {
        Route::asValue(
            function () use ($parts) {
                switch ($parts[0]) {
                    case 'edit':
                        Route::edit($parts[1], (int)$parts[2]);
                        break;
                    case 'create':
                        Route::create($parts[1]);
                        break;
                    case 'logfiles':
                        Route::logfiles((int)$parts[2]);
                        break;
                }
            }
        );
        echo "ALLOWED\n";
    } catch (\Throwable $e) {
        // sendResponse() re-raises under asValue(): code is the HTTP status,
        // message is the body. Anything else is an unanswered crash.
        if ($e instanceof \RuntimeException) {
            echo 'STATUS ' . $e->getCode() . ' ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
        } else {
            echo 'CRASH ' . get_class($e) . ' ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
        }
    }
}

/**
 * Run one case in a php-cgi child.
 *
 * @param string $case   the case name
 * @param string $body   the raw request body
 * @param string $method the request method
 *
 * @return string the marker line
 */
function child($case, $body, $method)
{
    $bin = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => $method,
        'CONTENT_TYPE' => 'application/json',
        'CONTENT_LENGTH' => (string)strlen($body),
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
    fwrite($pipes[0], $body);
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

/**
 * Asserts a case answered `$status` with a JSON object body carrying `error`.
 *
 * @param FogChecks $t      the checker
 * @param string    $label  what the case is
 * @param string    $line   the child's marker
 * @param int       $status the expected status
 *
 * @return void
 */
function expectError($t, $label, $line, $status)
{
    $ok = 1 === preg_match('/^STATUS (\d+) (.*)$/', $line, $m)
        && (int)$m[1] === $status;
    $body = $ok ? json_decode($m[2], true) : null;
    $t->check(
        "$label answers $status (got: $line)",
        $ok
    );
    $t->check(
        "$label body is a JSON object with an error string (got: $line)",
        is_array($body) && isset($body['error']) && is_string($body['error'])
        && '' !== $body['error']
    );
}

FogTestHarness::boot('bad-request');
FogTestHarness::fakeDb();
$t = new FogChecks();

if ('' === trim((string)@shell_exec('command -v php-cgi 2>/dev/null'))) {
    echo "SKIP: no php-cgi binary\n";
    exit(0);
}

// 1. A body that is not a JSON object.
foreach (['notjson', '[1,2]', '"str"', '', 'null'] as $bad) {
    $shown = '' === $bad ? '(empty)' : $bad;
    expectError(
        $t,
        "edit with body $shown",
        child('edit:os:100', $bad, 'PUT'),
        400
    );
    expectError(
        $t,
        "create with body $shown",
        child('create:os', $bad, 'POST'),
        400
    );
}

// 2. A missing or empty required field. os requires `name`.
expectError(
    $t,
    'create without the required name',
    child('create:os', json_encode(['description' => 'x']), 'POST'),
    400
);
expectError(
    $t,
    'create with an empty required name',
    child('create:os', json_encode(['name' => '']), 'POST'),
    400
);

// 3. A duplicate name is a conflict.
expectError(
    $t,
    'create with a taken name',
    child('create:os:0:taken', json_encode(['name' => 'dup']), 'POST'),
    409
);
expectError(
    $t,
    'edit to a taken name',
    child('edit:os:100:taken', json_encode(['name' => 'other']), 'PUT'),
    409
);

// 4. An empty name on edit is a bad request, not a permission failure.
expectError(
    $t,
    'edit with an empty name',
    child('edit:os:100', json_encode(['name' => '']), 'PUT'),
    400
);

// 5. A log file for a node that does not exist.
expectError(
    $t,
    'logfiles for a missing node',
    child('logfiles:storagenode:999999:missing', '', 'GET'),
    404
);

// 6. A good edit is still let through, so the guards are not blanket refusals.
$line = child('edit:os:100', json_encode(['description' => 'ok']), 'PUT');
$t->check(
    "edit with a valid body is not refused with 400/409 (got: $line)",
    0 === strpos($line, 'ALLOWED')
    || 1 !== preg_match('/^STATUS (400|409) /', $line)
);

$t->finish();
