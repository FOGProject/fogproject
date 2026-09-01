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
 * 2. THE EMPTY CASE CARRIES BOTH SPELLINGS. Under printer level `ar` the
 *    resolved list is authoritative in both directions -- fog-client removes
 *    every installed printer that is not on it -- and `np`, matched
 *    case-insensitively against ReturnCode, is the ONE string that triggers
 *    removal-on-empty. Decision 9 turns the empty case into an ordinary
 *    success carrying an explicit flag instead, and its shipping order is to
 *    send `np` ALONGSIDE that flag for a release before dropping it. Both are
 *    sent now, and this file pins both halves:
 *
 *      - `noPrinters` must appear in BOTH returns. A client cannot use a flag
 *        that shows up only when it is true, because an absent key is
 *        indistinguishable from a server too old to send it. The check counts
 *        occurrences rather than looking for one, because a single one would
 *        be satisfied by the empty branch alone -- the version nothing can
 *        consume.
 *      - `np` must stay until UNKNOWN-4 is observed. Dropping it changes what
 *        an OLD client does with this response, and no mode-`ar` host with
 *        steady printers has been watched through a poll cycle yet.
 *
 *    So the `np` check is not permanent, and is not to be deleted quietly
 *    either: when that observation exists it is REPLACED by one asserting the
 *    empty case no longer carries an `error` at all.
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

// The flag, and specifically that it is in BOTH returns. Counting is what
// makes that assertion real: a single occurrence would be satisfied by the
// empty branch alone, which is the version a client cannot consume.
$check(
    'the empty case also carries the explicit noPrinters flag',
    false !== strpos($body, "'noPrinters' => true,")
);
$check(
    'the success case carries it too, so a client can tell it apart',
    false !== strpos($body, "'noPrinters' => false,")
);
$check(
    'the flag appears exactly twice -- once per return',
    2 === substr_count($body, "'noPrinters' =>")
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
