<?php
/**
 * A refused connection proves the host is UP.
 *
 * FOGPingHosts opens a TCP connection to one port -- 445 by default -- so a
 * successful connection proves two things at once: the host is up, AND it
 * runs a service on that port. Only the first was ever being asked about,
 * and treating the pair as inseparable is what made a Linux host on 445, or
 * a Windows host on 22, look permanently switched off.
 *
 * ECONNREFUSED is the second way to learn the first fact. The host's own
 * kernel sent a TCP RST, which it can only do if it is powered on, on the
 * network, and routable from here. Nothing was listening; the MACHINE is up.
 *
 * Three things are pinned here, and they fail in three different ways:
 *
 *   1. Ping::isAlive() itself -- which errnos mean "the host answered". A
 *      mutation that widens it (say, ETIMEDOUT) silently marks every
 *      switched-off host as alive and advances its hostLastPing, which is
 *      the failure that destroys the field's whole value.
 *   2. The host grid's badge, driven for real through the column table
 *      plugins receive on CUSTOMIZE_DT_COLUMNS -- not read from the source.
 *      A grid that says "Connection refused" next to a timestamp that just
 *      advanced is the visible half of the two decisions drifting apart.
 *   3. That the service and the grid both route through isAlive() rather
 *      than each testing `=== 0` again. This third one is a source lint and
 *      is honest about being one: checks 1 and 2 cannot see a consumer that
 *      stops calling the shared helper and reimplements it correctly-for-now.
 *
 * Usage: php tests/ping-alive-codes.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('ping-alive');

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

if (!extension_loaded('sockets')) {
    fwrite(STDERR, "FAIL: ext-sockets is required by the code under test\n");
    exit(1);
}

/*
 * 1. The definition.
 */
check(
    'a completed connection is alive',
    Ping::isAlive(0) === true,
    $failures,
    $checks
);
check(
    'a REFUSED connection is alive -- the host sent the RST',
    Ping::isAlive(SOCKET_ECONNREFUSED) === true,
    $failures,
    $checks
);

// hostPingCode is varchar(20), so every value read back out of the database
// arrives as a STRING. An identity comparison against an int would answer
// false for every host on a live server while passing any test that only
// ever hands it an int.
check(
    'the code is compared numerically, not by identity',
    Ping::isAlive((string)SOCKET_ECONNREFUSED) === true
    && Ping::isAlive('0') === true,
    $failures,
    $checks
);

// Everything below is NOT alive, each for its own reason. ETIMEDOUT is the
// important one: nothing answered at all, so the host may be off or may be
// silently dropping, and "unknown" must not be recorded as "up".
$notAlive = [
    'ETIMEDOUT (nothing answered)' => SOCKET_ETIMEDOUT,
    'EHOSTUNREACH (a router said so, not the host)' => SOCKET_EHOSTUNREACH,
    'ENETUNREACH (no route)' => SOCKET_ENETUNREACH,
    'ENXIO (the name did not resolve)' => SOCKET_ENXIO,
];
foreach ($notAlive as $label => $errno) {
    check(
        "not alive: $label",
        Ping::isAlive($errno) === false,
        $failures,
        $checks
    );
}

// Never pinged is not alive. Both spellings, because the column is nullable
// AND FOGController::save() has historically written '' into columns of
// every type -- so both are on disk on real servers.
check(
    'never pinged (null) is not alive',
    Ping::isAlive(null) === false,
    $failures,
    $checks
);
check(
    "never pinged ('') is not alive",
    Ping::isAlive('') === false,
    $failures,
    $checks
);

/*
 * 2. The badge the host grid actually renders.
 *
 * Captured from the column table at the point plugins receive it, then
 * called, so this drives the real closure rather than reading the source.
 */
$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

$admin = FOGCore::getClass('User')->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/** Grabs the host column table on its way to plugins. */
class PingBadgeHook extends Hook
{
    /** @var array|null */
    public static $captured = null;

    public $name = 'PingBadgeHook';
    public $description = 'Captures the host DataTables column table';
    public $active = true;

    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register('CUSTOMIZE_DT_COLUMNS', [$this, 'grab']);
    }

    public function grab($arguments)
    {
        if (null !== self::$captured || 'host' !== $arguments['classname']) {
            return;
        }
        self::$captured = $arguments['columns'];
    }
}
new PingBadgeHook();

// Rendering a synthetic row is noise -- see route-column-contract.test.php
// for why. The table is captured before the first row is formatted, so the
// render itself is discarded.
Route::$data = [];
ob_start();
$prevLevel = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
$prevLog = ini_set('error_log', FOG_LOG_DIR . '/render-noise.log');
try {
    Route::asValue(
        function () {
            Route::listem('host', false, true);
        }
    );
} catch (\Throwable $e) {
    // Intentionally ignored; see above.
}
if (false !== $prevLog) {
    ini_set('error_log', $prevLog);
}
error_reporting($prevLevel);
ob_end_clean();
Route::$data = [];

$badge = null;
foreach ((array)PingBadgeHook::$captured as $col) {
    if (isset($col['dt'], $col['formatter']) && 'pingstatus' === $col['dt']) {
        $badge = $col['formatter'];
        break;
    }
}

check(
    'the host grid still has a pingstatus formatter',
    is_callable($badge),
    $failures,
    $checks
);

if (is_callable($badge)) {
    $render = function ($code) use ($badge) {
        return (string)$badge($code, []);
    };

    check(
        'code 0 renders as Online, in the success colour',
        false !== strpos($render(0), 'Online')
        && false !== strpos($render(0), 'bg-success'),
        $failures,
        $checks
    );

    // The fix. This used to render "Connection refused" in the same neutral
    // badge as a host that is genuinely off.
    $refused = $render((string)SOCKET_ECONNREFUSED);
    check(
        'a refused connection renders as UP, not as a failure',
        false !== stripos($refused, 'Up')
        && false === stripos($refused, 'refused'),
        $failures,
        $checks
    );
    check(
        'a refused connection is visually distinct from a plain Online',
        false === strpos($refused, 'bg-success')
        && false === strpos($refused, 'bg-secondary'),
        $failures,
        $checks
    );
    check(
        'a refused connection still says the port is shut',
        false !== stripos($refused, 'port'),
        $failures,
        $checks
    );

    $timedOut = $render((string)SOCKET_ETIMEDOUT);
    check(
        'a timed-out host is still reported as not reachable',
        false === stripos($timedOut, 'Up')
        && false === strpos($timedOut, 'bg-success')
        && false === strpos($timedOut, 'bg-info'),
        $failures,
        $checks
    );

    check(
        'a host that was never pinged still says so',
        false !== strpos($render(null), 'Not pinged')
        && false !== strpos($render(''), 'Not pinged'),
        $failures,
        $checks
    );
}

/*
 * 3. Both consumers route through the shared helper.
 *
 * A source lint, deliberately. Checks 1 and 2 both pass if a consumer stops
 * calling isAlive() and reimplements the same rule inline -- and that is
 * exactly the state the two decisions drift apart from, silently, the next
 * time one of them is edited.
 */
$service = file_get_contents(
    dirname(__DIR__) . '/packages/web/lib/service/pinghosts.class.php'
);
check(
    'the service decides lastping through Ping::isAlive()',
    false !== strpos($service, "Ping::isAlive(\$code)"),
    $failures,
    $checks
);
check(
    'the service no longer stamps lastping on a bare === 0',
    0 === preg_match(
        "#if \(\\\$code === 0\) \{\s*\\\$update\['lastping'\]#",
        $service
    ),
    $failures,
    $checks
);

$route = file_get_contents(
    dirname(__DIR__) . '/packages/web/lib/router/route.class.php'
);
check(
    'the grid formatter decides through Ping::isAlive()',
    false !== strpos($route, 'Ping::isAlive($d)'),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
