<?php
/**
 * The daemons re-read the node addresses while they wait, rather than once.
 *
 * FOGService::waitInterfaceReady() holds a daemon at startup until the box's
 * own addresses line up with what the database says a storage node is. The
 * comparison has two sides and only one of them used to be live:
 * getIPAddress(true) forces a fresh read of what the machine holds, while
 * self::$knownips was filled once in the constructor and never refreshed.
 *
 * So if the two did not match when the daemon started, they could not begin
 * to match. The loop had no exit, and the daemon sat on sleep(10) forever
 * while systemd reported the unit active -- a hang that looks exactly like
 * idling. A re-IP produces it on all ten daemons at once, and the admin's
 * correct fix (editing the storagenode row) used to require restarting every
 * one of them before it took effect.
 *
 * This cannot be tested by calling the method: it sleeps in ten-second steps
 * and needs a database and a network interface, so a test that drove it would
 * be slow, flaky and then deleted. What IS checkable, and what the fix
 * actually consists of, is the SHAPE of the two methods -- so that is what is
 * pinned, with brace-accurate extraction rather than a file-wide grep that
 * would pass on the string appearing anywhere.
 *
 * The load-bearing assertion is #4: the re-read must sit INSIDE the loop.
 * Moving it one line up restores the original bug while leaving every other
 * assertion here green, which is precisely the mutation a careless refactor
 * would make.
 *
 * Verified by mutation -- see mutate_knownips_refresh.sh.
 *
 * Usage: php tests/daemon-knownips-refresh.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$file = $repo . '/packages/web/src/Service/FOGService.php';
$src = is_file($file) ? (string)file_get_contents($file) : '';

/**
 * Pull one method's body out by matching braces.
 *
 * A regex to the next `}` stops at the first nested block, and a file-wide
 * strpos() cannot tell which method it landed in -- either would let a
 * re-read placed in the wrong method pass as though it were in the right one.
 */
$body = function ($method) use ($src) {
    $at = strpos($src, 'function ' . $method . '(');
    if (false === $at) {
        return null;
    }
    $open = strpos($src, '{', $at);
    if (false === $open) {
        return null;
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return null;
};

$construct = $body('__construct');
$wait = $body('waitInterfaceReady');
$db = $body('waitDbReady');
$load = $body('loadKnownIps');

// [passed, what it means]
$results = [];

$results[] = [
    '' !== $src,
    'FOGService.php is readable',
];

// 1. The query lives in a method of its own, so it CAN be called repeatedly.
$results[] = [
    null !== $load
        && false !== strpos($load, 'storagenode')
        && false !== strpos($load, 'getIds'),
    'loadKnownIps() exists and is what queries the enabled storage nodes',
];

// 2. The constructor no longer takes a snapshot. This is the half that made
//    the value stale; leaving it would keep a second, frozen source of truth.
$results[] = [
    null !== $construct && false === strpos($construct, 'knownips'),
    'the constructor no longer snapshots the node addresses',
];

// 3. The waiting loop is what refreshes them.
$hasReread = null !== $wait
    && false !== strpos($wait, 'self::$knownips = self::loadKnownIps();');
$results[] = [
    $hasReread,
    'waitInterfaceReady() re-reads them itself',
];

// 4. THE ONE THAT MATTERS. Inside the loop, not before it -- a re-read that
//    runs once on the way in is the original bug wearing the fix's clothes.
//
//    Comments are stripped first. The prose above waitInterfaceReady()'s loop
//    explains the bug and so NAMES sleep(10) before the real call appears,
//    which made this assertion match the explanation rather than the code.
//    A gate that reads its own documentation is not a gate -- caught here on
//    this file's first run.
$code = null !== $wait
    ? (string)preg_replace('#//[^\n]*#', '', $wait)
    : '';
$loopAt = strpos($code, 'for (;;) {');
$readAt = strpos($code, 'self::$knownips = self::loadKnownIps();');
$sleepAt = strpos($code, 'sleep(10);');
$results[] = [
    '' !== $code
        && false !== $loopAt && false !== $readAt && false !== $sleepAt
        && $loopAt < $readAt && $readAt < $sleepAt,
    'and re-reads INSIDE the wait loop, so a later edit is actually seen',
];

// 5/6. Neither wait recurses. Each pass used to add a stack frame that was
//      never unwound, so waiting had an unbounded cost and a database that
//      stayed down consumed the daemon rather than merely blocking it.
$results[] = [
    null !== $wait && false === strpos($wait, '$this->waitInterfaceReady('),
    'waitInterfaceReady() loops rather than recursing',
];
$results[] = [
    null !== $db && false === strpos($db, '$this->waitDbReady('),
    'waitDbReady() loops rather than recursing',
];

$failed = 0;
foreach ($results as [$passed, $why]) {
    echo $passed ? "  ok    $why\n" : "  FAIL  $why\n";
    $failed += $passed ? 0 : 1;
}
echo "\n";
if ($failed > 0) {
    echo 'FAIL (' . $failed . ' of ' . count($results) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($results) . " assertions)\n";
