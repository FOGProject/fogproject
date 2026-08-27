<?php
/**
 * Route::WHOAMI_KEYS and the .fogsettings.pub writer must name the same keys.
 *
 * /api/whoami answers with exactly the keys the installer publishes into
 * .fogsettings.pub. Those two lists live in different repositories' worth of
 * different language -- a PHP const in route.class.php and a shell `for` loop
 * in writeUpdateFile() -- and nothing has ever bound them together.
 * docs/FOGSETTINGS.md says so in as many words: "They must stay in step; there
 * is no test binding them." This is that test.
 *
 * The failure it catches is quiet in the worst way. If the PHP list gains a key
 * the shell does not write, the route answers '' for it, because whoami() fills
 * every missing key with an empty string rather than dying -- so the route stays
 * 200 and a client reads a blank hostname as a fact about the server. If the
 * shell writes a key the PHP list does not name, the value is published to a
 * world-readable file for nothing.
 *
 * GH-1120 renamed all five keys, which is the first time both sides had to move
 * together, and is why this got written now rather than at the next drift.
 *
 * Static source check: neither side can be executed here. writeUpdateFile() is
 * bash and route.class.php needs the whole FOG bootstrap.
 *
 * Usage: php tests/whoami-keys-in-step.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$routeFile = $repo . '/packages/web/src/Router/Route.php';
$funcsFile = $repo . '/lib/common/functions.sh';

$fails = [];

foreach ([$routeFile, $funcsFile] as $f) {
    if (!is_readable($f)) {
        fwrite(STDERR, "FAIL: cannot read $f\n");
        exit(1);
    }
}

$route = file_get_contents($routeFile);
$funcs = file_get_contents($funcsFile);

// --- the PHP side: the const's entries, in order -------------------------
if (!preg_match('/const WHOAMI_KEYS = \[(.*?)\];/s', $route, $m)) {
    fwrite(STDERR, "FAIL: could not find Route::WHOAMI_KEYS in route.class.php\n");
    exit(1);
}
preg_match_all("/'([^']+)'/", $m[1], $pm);
$phpKeys = $pm[1];

// --- the shell side: the pub-file loop's word list -----------------------
if (!preg_match('/for pubkey in ([^;]+); do/', $funcs, $m2)) {
    fwrite(STDERR, "FAIL: could not find the .fogsettings.pub loop in functions.sh\n");
    exit(1);
}
$shellKeys = preg_split('/\s+/', trim($m2[1]), -1, PREG_SPLIT_NO_EMPTY);

if (count($phpKeys) < 1) {
    $fails[] = 'Route::WHOAMI_KEYS parsed as empty; this test has stopped'
        . ' testing anything and needs its pattern checked';
}

// Order matters as well as membership: the const's docblock says "in the order
// they are returned", and the pub file is written in the loop's order, so a
// reordering on one side alone makes the two descriptions disagree even though
// every key is still present.
if ($phpKeys !== $shellKeys) {
    $fails[] = 'Route::WHOAMI_KEYS and the .fogsettings.pub loop disagree.'
        . ' PHP: [' . implode(', ', $phpKeys) . ']'
        . ' shell: [' . implode(', ', $shellKeys) . ']';
}

// Every published key must also be a managed key, or the installer publishes a
// value it never writes into .fogsettings in the first place.
if (preg_match('/local -a managedKeys=\((.*?)\n    \)/s', $funcs, $m3)) {
    $managed = preg_split(
        '/\s+/',
        trim(preg_replace('/#[^\n]*/', '', $m3[1])),
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    foreach ($shellKeys as $k) {
        if (!in_array($k, $managed, true)) {
            $fails[] = "the pub file publishes '$k', which is not in managedKeys"
                . ' -- nothing writes it into .fogsettings, so it is published empty';
        }
    }
} else {
    $fails[] = 'could not parse managedKeys out of functions.sh';
}

if ($fails) {
    echo 'FAIL whoami-keys-in-step (' . count($fails) . " problem(s))\n";
    foreach ($fails as $f) {
        echo "  - $f\n";
    }
    exit(1);
}

echo 'ok whoami-keys-in-step: ' . count($phpKeys)
    . " keys agree across route.class.php and writeUpdateFile()\n";
exit(0);
