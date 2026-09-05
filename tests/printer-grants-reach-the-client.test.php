<?php
/**
 * UNKNOWN-4, simulated: what a mode-`ar` client is actually told.
 *
 * ADR 0038 decision 5 resolves printers LIVE on every request, and decision 9
 * wants the empty case to stop being an error. Both are blocked on one
 * observation: on printer level `ar` ("FOG Handles all printers") the list the
 * server sends is authoritative in BOTH directions -- fog-client removes every
 * installed printer that is not on it -- so a wrong list does not merely fail
 * to add a printer, it takes printers off a machine.
 *
 * tests/printer-client-resolves.test.php pins the SOURCE of that endpoint. It
 * says so in its own header, and gives the reason: reaching PrinterClient::
 * json() was thought to need a configured FOG, a real host and a client
 * session. It does not. It needs a host object and a database, and the
 * harness supplies both -- so this file drives the real method against a
 * fixture and asserts on the payload the client would receive.
 *
 * WHAT IT DOES NOT COVER, and this is the honest boundary: what fog-client
 * DOES with the payload. That is a Windows binary's behavior, and no amount
 * of server-side fixture reaches it. What was genuinely unknown, though, was
 * never the client's C# -- it was whether the server would send a host's own
 * printers back alongside the granted ones, or quietly replace them. That is
 * a question about this method, and it is answered here.
 *
 * THE FIVE ARMS:
 *
 *   1. A host with its own printer, in a group granting others, is told about
 *      ALL of them -- its own first. Under `ar` a list missing the host's own
 *      printer would UNINSTALL it.
 *   2. A printer held both directly and by grant appears ONCE. A duplicate is
 *      not cosmetic here: it is the same printer named twice in a list the
 *      client reconciles against.
 *   3. A host-direct default beats a group's; a group's is used when the host
 *      has none.
 *   4. Revoking the grant drops those printers from the list and keeps the
 *      host's own. This is the removal direction `ar` acts on, and it is the
 *      arm that would have caught a resolver that unioned but never subtracted.
 *   5. A host with nothing anywhere still gets `np` AND `noPrinters`, because
 *      an old client reads the first and a new one reads the second.
 *
 * Usage: php tests/printer-grants-reach-the-client.test.php
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

use FOG\Base\FOGCore;
use FOG\Client\PrinterClient;
use FOG\Items\Host;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('printer-grants-reach-the-client');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();

$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/** printerLevel is stored as an INDEX into PrinterClient's mode list. */
const MODES = [0 => '0 (no management)', 1 => 'a', 2 => 'ar'];

/** The printer catalog the fixture server holds. */
const CATALOG = [
    1 => 'Front Desk',
    2 => 'Warehouse',
    3 => 'Lab Color',
    4 => 'Accounts',
];

/**
 * One row of the printers table, as the database would hand it back.
 *
 * @param int $id the printer id
 *
 * @return array
 */
function printerRow($id)
{
    return [
        'pID' => $id,
        'pAlias' => CATALOG[$id],
        'pDesc' => '',
        'pPort' => 'LPT1',
        'pDefFile' => '',
        'pModel' => 'model-' . $id,
        'pConfig' => 'Network',
        'pConfigFile' => '',
        'pIP' => '10.0.0.' . $id,
    ];
}

/**
 * Runs the real endpoint against a fixture and returns its payload.
 *
 * @param FogFakeDb $db      the fake
 * @param array     $direct  [printerID => isDefault('1'|'0')] on the host
 * @param array     $grants  [groupID => [printerID => isDefault(0|1)]]
 * @param array     $groups  group ids the host belongs to
 * @param int       $level   the host's printer level index (0, 1 or 2)
 * @param int       $hostID  the host
 *
 * @return array the json() payload
 */
function askServer(
    $db,
    array $direct,
    array $grants,
    array $groups,
    $level = 2,
    $hostID = 11
) {
    $db->log = [];
    $db->responder = function ($sql, $params = []) use (
        $direct,
        $grants,
        $groups,
        $hostID
    ) {
        // The resolver's three reads, matched on the columns they project so
        // a rewritten WHERE clause does not silently stop matching.
        if (false !== strpos($sql, '`paHostID`, `paPrinterID`, `paIsDefault`')) {
            $rows = [];
            foreach ($direct as $printerID => $isDefault) {
                $rows[] = [
                    'paHostID' => $hostID,
                    'paPrinterID' => $printerID,
                    'paIsDefault' => (string)$isDefault,
                ];
            }
            return $rows;
        }
        if (false !== strpos($sql, 'FROM `groupMembers`')) {
            $rows = [];
            foreach ($groups as $groupID) {
                $rows[] = ['gmHostID' => $hostID, 'gmGroupID' => $groupID];
            }
            return $rows;
        }
        if (false !== strpos($sql, 'FROM `groupPrinterAssoc`')) {
            $rows = [];
            foreach ($grants as $groupID => $printers) {
                foreach ($printers as $printerID => $isDefault) {
                    $rows[] = [
                        'gpaGroupID' => $groupID,
                        'gpaPrinterID' => $printerID,
                        'gpaIsDefault' => $isDefault,
                    ];
                }
            }
            return $rows;
        }
        if (false !== strpos($sql, 'FROM `groups`')
            && false !== strpos($sql, '`groupOrder`')
        ) {
            // Returned in the order the ORDER BY would produce, which is what
            // decides grant precedence between two groups.
            $rows = [];
            foreach ($groups as $groupID) {
                $rows[] = ['groupID' => $groupID];
            }
            return $rows;
        }
        // getList() asks the schema what columns `printers` has before it
        // selects them. Unanswered it selects nothing, and every printer
        // comes back as a row of placeholder strings -- which looks exactly
        // like a resolver that returned the wrong ids.
        if (false !== strpos($sql, 'information_schema')
            && false !== strpos($sql, "'printers'")
        ) {
            $rows = [];
            foreach (array_keys(printerRow(1)) as $column) {
                $rows[] = ['COLUMN_NAME' => $column];
            }
            return $rows;
        }
        // The printer catalog, for both getIds() and getList().
        if (false !== strpos($sql, '`printers`')) {
            // The default-name lookup filters on `pID`, and its value is a
            // bound placeholder rather than a literal. Answering the whole
            // catalog would make count() === 1 false and silently blank
            // every default -- so the filter is honored from $params.
            $wanted = array_keys(CATALOG);
            if (false !== strpos($sql, '`pID` IN (')) {
                $wanted = array_map('intval', array_values((array)$params));
            }
            $rows = [];
            foreach ($wanted as $id) {
                if (isset(CATALOG[$id])) {
                    $rows[] = printerRow($id);
                }
            }
            return $rows;
        }
        return null;
    };

    // getList() runs through PDO::prepare(), not query(), so the responder
    // above never sees it and the fake synthesizes a row of placeholder
    // strings per column. That failure is worth naming: every printer comes
    // back named `pAlias-1`, which looks exactly like a resolver that
    // returned the wrong ids rather than like a fixture that was not wired.
    $db->pdo->rowCount = count(CATALOG);
    $db->pdo->rowFactory = function ($columns, $n) {
        if (!in_array('pID', $columns, true)) {
            return FogFakePdo::defaultRow($columns, $n);
        }
        $row = printerRow($n);
        $out = [];
        foreach ($columns as $column) {
            $out[$column] = $row[$column] ?? '';
        }
        return $out;
    };

    FogTestHarness::setStatic(
        'FOGBase',
        'Host',
        (new Host())->set('id', $hostID)->set('printerLevel', $level)
    );
    // newInstanceWithoutConstructor(), and this is the whole reason the
    // endpoint looked untestable. FOGClient's constructor resolves the host
    // from the request's MAC list and throws `#!im` when there isn't one --
    // that is the client SESSION, and it is a different question from what
    // json() computes. json() reads self::$Host and the database, both of
    // which the harness supplies directly.
    $payload = (new \ReflectionClass(PrinterClient::class))
        ->newInstanceWithoutConstructor()
        ->json();
    $db->responder = null;

    return $payload;
}

/**
 * The printer names in a payload, in the order they were sent.
 *
 * @param array $payload the json() result
 *
 * @return array
 */
function names(array $payload)
{
    $out = [];
    foreach ((array)($payload['printers'] ?? []) as $printer) {
        $out[] = $printer['name'];
    }

    return $out;
}

// ---------------------------------------------------------------
// Arm 1: the host keeps its own printer and gains the group's.
$payload = askServer(
    $db,
    [1 => '0'],
    [7 => [2 => 0, 3 => 0]],
    [7]
);
$t->check(
    'the host is told about its own printer AND the granted ones',
    ['Front Desk', 'Warehouse', 'Lab Color'] === names($payload)
);
$t->check(
    'the host-direct printer comes first, as the resolver ordered it',
    'Front Desk' === (names($payload)[0] ?? '')
);
$t->check(
    'a host with printers is not an error',
    !isset($payload['error'])
);
$t->check(
    'and says so explicitly',
    array_key_exists('noPrinters', $payload) && false === $payload['noPrinters']
);
$t->check(
    'the mode is echoed back as `ar`',
    'ar' === ($payload['mode'] ?? '')
);
$t->check(
    'the whole catalog is still offered as allPrinters',
    count((array)($payload['allPrinters'] ?? [])) === count(CATALOG)
);

// ---------------------------------------------------------------
// Arm 2: held twice, sent once.
$payload = askServer(
    $db,
    [2 => '0'],
    [7 => [2 => 0, 3 => 0]],
    [7]
);
$t->check(
    'a printer held directly AND granted appears exactly once',
    ['Warehouse', 'Lab Color'] === names($payload)
);

// ---------------------------------------------------------------
// Arm 3: defaults.
$payload = askServer(
    $db,
    [1 => '1'],
    [7 => [2 => 1]],
    [7]
);
$t->check(
    "a host's own default beats the group's",
    'Front Desk' === ($payload['default'] ?? '')
);
$payload = askServer(
    $db,
    [1 => '0'],
    [7 => [2 => 1]],
    [7]
);
$t->check(
    "the group's default is used when the host has none",
    'Warehouse' === ($payload['default'] ?? '')
);
$payload = askServer(
    $db,
    [1 => '0'],
    [7 => [2 => 0]],
    [7]
);
$t->check(
    'and no default anywhere sends an empty one, not a guess',
    '' === ($payload['default'] ?? 'unset')
);

// Two groups: the first in resolved order names the default.
$payload = askServer(
    $db,
    [],
    [7 => [2 => 1], 8 => [3 => 1]],
    [7, 8]
);
$t->check(
    'with two granting groups the first in order supplies the default',
    'Warehouse' === ($payload['default'] ?? '')
);
$payload = askServer(
    $db,
    [],
    [7 => [2 => 1], 8 => [3 => 1]],
    [8, 7]
);
$t->check(
    'and reversing that order reverses the answer',
    'Lab Color' === ($payload['default'] ?? '')
);

// ---------------------------------------------------------------
// Arm 4: revoking the grant. THE ONE THAT MATTERS UNDER `ar`.
$payload = askServer(
    $db,
    [1 => '0'],
    [],
    [7]
);
$t->check(
    'revoking the grant drops the granted printers from the list',
    ['Front Desk'] === names($payload)
);
$t->check(
    "and leaves the host's own printer on it",
    in_array('Front Desk', names($payload), true)
);
// Leaving the group entirely is the same answer by a different route.
$payload = askServer(
    $db,
    [1 => '0'],
    [7 => [2 => 0, 3 => 0]],
    []
);
$t->check(
    'leaving the group drops the granted printers too',
    ['Front Desk'] === names($payload)
);

// ---------------------------------------------------------------
// Arm 5: genuinely nothing.
$payload = askServer($db, [], [], []);
$t->check(
    'a host with nothing anywhere still gets the legacy `np`',
    'np' === ($payload['error'] ?? '')
);
$t->check(
    'and the flag beside it, so a new client need not read the error',
    array_key_exists('noPrinters', $payload) && true === $payload['noPrinters']
);
$t->check(
    'the empty case sends an empty printer list',
    [] === ($payload['printers'] ?? null)
);
// A group that grants nothing is not the same fixture as no group at all,
// and both have to reach the same answer.
$payload = askServer($db, [], [7 => []], [7]);
$t->check(
    'a group granting nothing is the empty case too',
    'np' === ($payload['error'] ?? '') && [] === ($payload['printers'] ?? null)
);

// ---------------------------------------------------------------
// The grant reaches a host on every level, not only `ar`. Mode is echoed for
// the client to act on; it does not filter the list.
foreach ([0, 1, 2] as $level) {
    $payload = askServer($db, [1 => '0'], [7 => [2 => 0]], [7], $level);
    $t->check(
        'the granted printer is sent on level ' . MODES[$level],
        in_array('Warehouse', names($payload), true)
    );
}

$t->finish();
