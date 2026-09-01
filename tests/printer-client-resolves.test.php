<?php
/**
 * Pins the printer endpoint to the resolver, and pins `np` in place.
 *
 * Two separate things, and the second is the one that matters.
 *
 * 1. The list the client is told to install comes from the resolver, so a
 *    group's printer grant reaches its members. ADR 0038 decision 5: printers
 *    resolve LIVE on each request, because there is no task to hang a
 *    snapshot on and a removal has to reach the machine.
 *
 * 2. THE EMPTY CASE IS STILL SPELLED `np`. Under printer level `ar` the
 *    resolved list is authoritative in both directions -- fog-client removes
 *    every installed printer that is not on it -- and `np`, matched
 *    case-insensitively against ReturnCode, is the ONE string that triggers
 *    removal-on-empty. Decision 9 turns the empty case into an ordinary
 *    success carrying an explicit flag instead, and traces through
 *    fog-client 0.13.0 to show that is safe. That trace has not been watched
 *    happen on a mode-`ar` host with steady printers (UNKNOWN-4). Changing
 *    where the list comes from AND what the empty case means to the client in
 *    one release leaves nothing to bisect if a fleet comes back with no
 *    printers, so this test holds the wire format still while the source of
 *    the list moves.
 *
 *    When decision 9 does land, this check is expected to be REPLACED, not
 *    deleted quietly -- by one asserting the new flag alongside `np` for the
 *    release they overlap.
 *
 * Source-anchored, for the reason set out at length in
 * tests/task-creation-resolves-assignments.test.php: the resolver's behavior
 * is covered behaviorally against a real database, and reaching
 * PrinterClient::json() needs a configured FOG, a host and a client session,
 * at which point the test is an install rehearsal rather than a gate.
 *
 * Usage: php tests/printer-client-resolves.test.php
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

$root = dirname(__DIR__);
$path = $root . '/packages/web/src/Client/PrinterClient.php';
$body = @file_get_contents($path);
if (false === $body) {
    fwrite(STDERR, "FAIL: cannot read $path\n");
    exit(1);
}
$body = (string)$body;

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

$check(
    'the printer endpoint resolves through the resolver',
    false !== strpos(
        $body,
        "\$resolved = Resolver::resolvePrinters([\$hostID])[\$hostID]"
    )
);
$check(
    'PrinterClient imports the resolver',
    false !== strpos($body, 'use FOG\Assign\Resolver;')
);
$check(
    'the list sent to the client is the resolved one',
    false !== strpos($body, "\$printerIDs = \$resolved['printers'];")
);
$check(
    'the endpoint no longer sends the host-direct printers alone',
    false === strpos($body, "\$printerIDs = self::\$Host->get('printers');")
);
$check(
    'the default sent is the resolved default',
    false !== strpos($body, "if (null !== \$resolved['default']) {")
);

// The `np` guard. Anchored as the whole return, and required to sit inside
// the empty-list branch: an `np` string surviving somewhere else in the file
// would satisfy a bare search while the empty case had quietly stopped
// emitting it.
$emptyBranch = "if (\$printerCount < 1) {";
$emptyAt = strpos($body, $emptyBranch);
$npAt = strpos($body, "'error' => 'np',");
$check(
    'the empty case is still reached by a count check',
    false !== $emptyAt
);
$check(
    'the empty case still answers `np`',
    false !== $npAt && false !== $emptyAt && $npAt > $emptyAt
);
$check(
    'nothing else in the endpoint answers `np`',
    1 === substr_count($body, "'np'")
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the printer endpoint changed in a way that matters:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  printer endpoint resolves: %d checks\n", $checks);
exit(0);
