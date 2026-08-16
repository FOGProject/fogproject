<?php
/**
 * Guards the two contracts Route's result wrappers rest on.
 *
 * 1. objectify() must produce exactly what json_decode(json_encode($x)) would.
 *    The idiom getList()/getItem() replace ends in json_decode(), so its rows
 *    arrive as stdClass and roughly 190 call sites read `$row->field`. If the
 *    conversion ever drifts, a migrated caller reads null off an array rather
 *    than erroring -- the same silent shape as the envelope bug the wrappers
 *    exist to end.
 *
 * 2. sendResponse() must raise rather than exit while a wrapper is on the
 *    stack. That is the whole reason a daemon may call one: eleven classes
 *    under lib/service back the CLI daemons, where breakHead()'s exit ends the
 *    process. See ADR 0011, and multicasttask.class.php's hand-rolled guard
 *    against the same hazard (#907).
 *
 * DB-free: loads the autoloader and pokes the statics by reflection. It never
 * calls listem() or indiv(), so no database is contacted.
 *
 * Usage: php tests/route-result-wrappers.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';
$init = $web . '/commons/init.php';

if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-route-wrappers-' . getmypid();
foreach (['cache', 'log', 'plugins', 'sessions'] as $dir) {
    if (!is_dir($tmp . '/' . $dir)) {
        mkdir($tmp . '/' . $dir, 0777, true);
    }
}
// Forced to a temp dir unconditionally: the class scan writes filelist.*.json
// under FOG_CACHE_DIR, and a test must never rebuild a real server's cache.
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');
ini_set('session.save_path', $tmp . '/sessions');

require $init;
new Initiator();

$failures = [];

/**
 * Records a failed expectation.
 *
 * @param array  $failures Collected failures, by reference.
 * @param string $label    What was being checked.
 * @param mixed  $want     Expected value.
 * @param mixed  $got      Actual value.
 *
 * @return void
 */
function expect(array &$failures, $label, $want, $got)
{
    if ($want !== $got) {
        $failures[] = sprintf(
            '%s: expected %s, got %s',
            $label,
            var_export($want, true),
            var_export($got, true)
        );
    }
}

if (!class_exists('Route')) {
    fwrite(STDERR, "FAIL: Route did not autoload\n");
    exit(1);
}

$ref = new \ReflectionClass('Route');

foreach (['getList', 'getItem', 'objectify'] as $method) {
    if (!$ref->hasMethod($method)) {
        $failures[] = "Route::$method() is missing";
    }
}
if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

// ---- 1. objectify() matches the json round-trip -------------------------

$objectify = $ref->getMethod('objectify');
$objectify->setAccessible(true);

$shapes = [
    'empty array'            => [],
    'list of scalars'        => [1, 2, 3],
    'flat row'               => ['id' => '1', 'name' => 'fog.local'],
    'list of rows'           => [['id' => '1'], ['id' => '2']],
    'nested object'          => ['id' => '1', 'meta' => ['a' => 'b']],
    'nested list'            => ['id' => '1', 'tags' => ['x', 'y']],
    'list of nested rows'    => [['id' => '1', 'm' => ['k' => 'v']]],
    'null value'             => ['id' => '1', 'desc' => null],
    'bool and int'           => ['on' => true, 'n' => 7],
    'scalar passthrough'     => 'plain',
    'null passthrough'       => null,
    'empty nested'           => ['rows' => [], 'meta' => []],
    'numeric string keys'    => ['0' => 'a', '1' => 'b'],
    'sparse keys'            => [3 => 'a', 7 => 'b'],
];

foreach ($shapes as $label => $input) {
    $want = json_decode(json_encode($input));
    $got = $objectify->invoke(null, $input);
    if ($want != $got) {
        $failures[] = sprintf(
            'objectify(%s) diverged from json_decode(json_encode()): '
            . 'expected %s, got %s',
            $label,
            json_encode($want),
            json_encode($got)
        );
    }
    // == compares structure and value; also pin the top-level PHP type, which
    // is what decides whether a call site writes -> or [].
    expect(
        $failures,
        "objectify($label) top-level type",
        gettype($want),
        gettype($got)
    );
}

// ---- 2. the rethrow guard ------------------------------------------------

$depth = $ref->getProperty('_rethrowDepth');
$depth->setAccessible(true);
expect($failures, 'rethrow depth starts at 0', 0, $depth->getValue());

// Run the probe in a child process, deliberately. If the guard is ever
// removed, sendResponse() reaches breakHead(), which echoes and calls exit --
// and exit(0) in THIS process would end the test early with a success status
// and no output, which the runner would read as a pass. A child lets the
// absence of the marker be observed instead of ending the observer.
$probe = <<<'PROBE'
$tmp = sys_get_temp_dir() . '/fog-route-probe-' . getmypid();
foreach (['cache', 'log', 'plugins'] as $d) { @mkdir($tmp . '/' . $d, 0777, true); }
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');
ini_set('session.save_path', $tmp);
require %s;
new Initiator();
$r = new ReflectionClass('Route');
$d = $r->getProperty('_rethrowDepth');
$d->setAccessible(true);
$d->setValue(null, 1);
try {
    Route::sendResponse(406, 'probe');
    echo 'MARKER:noraise';
} catch (RuntimeException $e) {
    echo 'MARKER:raised:' . $e->getMessage() . ':' . $e->getCode();
} catch (Throwable $e) {
    echo 'MARKER:wrongtype:' . get_class($e);
}
PROBE;

$out = [];
exec(
    'php -r ' . escapeshellarg(sprintf($probe, var_export($init, true))) . ' 2>&1',
    $out
);
$got = implode('', $out);

if (strpos($got, 'MARKER:raised:probe:406') === false) {
    if (strpos($got, 'MARKER:') === false) {
        $failures[] = 'sendResponse() ended the process while a wrapper was '
            . 'on the stack -- the guard is gone. Child produced: '
            . var_export(substr($got, 0, 120), true);
    } else {
        $failures[] = 'sendResponse() did not raise as expected: '
            . var_export(substr($got, 0, 120), true);
    }
}

// A wrapper must leave the depth where it found it, including after a failure.
expect($failures, 'rethrow depth restored', 0, $depth->getValue());

// ---- report --------------------------------------------------------------

@array_map('unlink', (array)glob($tmp . '/cache/*'));
foreach (['cache', 'log', 'plugins', 'sessions'] as $dir) {
    @rmdir($tmp . '/' . $dir);
}
@rmdir($tmp);

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

printf(
    "route-result-wrappers: %d shapes match json_decode(), rethrow guard live\n",
    count($shapes)
);
echo "PASS\n";
exit(0);
