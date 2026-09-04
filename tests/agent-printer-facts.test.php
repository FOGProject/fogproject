<?php
/**
 * What a host reports about its printers stays inside its bounds.
 *
 * The printer block rides the poll (design 0010 section 3) and is written by
 * a host, so these checks are about what a host can and cannot cause. Each
 * one is a way the feature could go wrong quietly rather than loudly:
 *
 * - A machine that reported no queues still writes its `hostSpooler` row.
 *   Without it the report cannot tell "CUPS, nothing installed" from "never
 *   checked in", and the host that most needs looking at is the one that
 *   disappears from the page. That is the exact failure design 0010 section
 *   6 exists to prevent, and it is invisible until someone goes looking.
 * - The set is replaced inside one transaction. A DELETE that committed
 *   before its INSERT would leave a window where the host looks like it has
 *   no printers at all.
 * - The default is resolved from the block-level NAME, not from a per-queue
 *   flag, so the stored flag and the reported name cannot disagree.
 * - A host cannot invent a subsystem. The report groups on it and it is
 *   rendered to an admin.
 * - The insert binds a distinct placeholder per value. Reusing one for the
 *   host id across rows works under emulated prepares and fails with a
 *   bound-parameter count error under real ones -- so it passes in
 *   development and breaks in production, on whichever install has
 *   emulation off.
 *
 * DB-free: the harness's fake connection stands in and the statements are
 * inspected rather than executed.
 *
 * Usage: php tests/agent-printer-facts.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-printer-facts');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Call a private static on PrinterFacts.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function pf($name, array $args)
{
    $m = new \ReflectionMethod(\FOG\Agent\PrinterFacts::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

/**
 * A host that needs no database to answer for itself.
 *
 * @param int $id the host id
 *
 * @return \FOG\Items\Host
 */
function pfHost($id)
{
    $Host = new \FOG\Items\Host();
    $Host->set('id', $id)->set('name', 'WS-014');

    return $Host;
}

/**
 * Run report() against the fake connection, returning the statements and
 * the binds each one carried.
 *
 * @param FogFakeDb $db      the fake connection
 * @param array     $block   the reported block
 * @param array     $current rows _currentNames should see
 *
 * @return array [statements, binds]
 */
function pfReport($db, array $block, array $current = [])
{
    $db->log = [];
    $binds = [];
    $db->responder = function ($sql, $params) use (&$binds, $current) {
        $binds[] = [$sql, $params];
        if (false !== strpos($sql, 'SELECT `hpName`')) {
            return $current;
        }
        return null;
    };
    \FOG\Agent\PrinterFacts::report(pfHost(7), $block);
    $db->responder = null;

    return [$db->log, $binds];
}

// ------------------------------------------------------------ the whitelist

$mapped = array_keys(
    (function () {
        $p = new \ReflectionProperty(\FOG\Items\HostPrinter::class, 'databaseFields');
        $p->setAccessible(true);
        return (array)$p->getValue(new \FOG\Items\HostPrinter());
    })()
);
$missing = array_diff(
    array_keys(\FOG\Agent\PrinterFacts::WIDTHS),
    $mapped
);
$t->check(
    'every width names a real HostPrinter property'
        . ($missing ? ': ' . implode(', ', $missing) : ''),
    empty($missing)
);

// ----------------------------------------------------------- normalization

$clean = pf(
    '_clean',
    [
        [
            ['name' => 'Accounts', 'uri' => 'socket://h:9100', 'driver' => 'd'],
            // Same name twice. Without the keying this hits the unique index
            // mid-insert and rolls back the whole poll.
            ['name' => 'Accounts', 'uri' => 'socket://other:9100'],
            // No name: nothing can act on it -- not the report, not a
            // removal, not the admin.
            ['name' => '   ', 'uri' => 'ipp://x/'],
            ['name' => 'Reception', 'uri' => 'ipp://p/ipp/print', 'driver' => ''],
            'not an array',
        ],
        'Reception',
    ]
);
$t->check(
    'a queue reported twice is stored once',
    2 === count($clean) && isset($clean['Accounts'], $clean['Reception'])
);
$t->check(
    'a queue with no name is dropped',
    !isset($clean['   ']) && !isset($clean[''])
);
$t->check(
    'the default is resolved from the block-level name',
    1 === $clean['Reception']['isDefault']
        && 0 === $clean['Accounts']['isDefault']
);
$t->check(
    'an empty driver survives, because empty means driverless',
    '' === $clean['Reception']['driver']
);

$clean = pf('_clean', [[['name' => 'Accounts']], 'Gone']);
$t->check(
    'a default naming a queue that is not installed sets no flag',
    0 === $clean['Accounts']['isDefault']
);

$long = pf(
    '_clean',
    [[['name' => str_repeat('n', 400), 'uri' => str_repeat('u', 2000)]], '']
);
$row = array_shift($long);
$t->check(
    'an overlong value is truncated here rather than failing the insert',
    255 === strlen($row['name']) && 1024 === strlen($row['uri'])
);

// -------------------------------------------------------------- the writes

list($log, $binds) = pfReport(
    $db,
    [
        'subsystem' => 'cups',
        'default' => 'Accounts',
        'installed' => [
            ['name' => 'Accounts', 'uri' => 'socket://h:9100', 'driver' => 'd'],
            ['name' => 'Reception', 'uri' => 'ipp://p/', 'driver' => ''],
        ],
    ],
    [['hpName' => 'Old']]
);
$joined = implode("\n", $log);

$t->check(
    'the replace runs inside a transaction',
    false !== strpos($joined, 'START TRANSACTION')
        && false !== strpos($joined, 'COMMIT')
);
$order = [];
foreach ($log as $sql) {
    if (0 === strpos($sql, 'START TRANSACTION')) {
        $order[] = 'begin';
    } elseif (false !== strpos($sql, 'DELETE FROM `hostPrinter`')) {
        $order[] = 'delete';
    } elseif (false !== strpos($sql, 'INSERT INTO `hostPrinter`')) {
        $order[] = 'insert';
    } elseif (false !== strpos($sql, 'INSERT INTO `hostSpooler`')) {
        $order[] = 'spooler';
    } elseif (0 === strpos($sql, 'COMMIT')) {
        $order[] = 'commit';
    }
}
$t->check(
    'delete, insert and the spooler row all land between begin and commit:'
        . ' ' . implode(' ', $order),
    ['begin', 'delete', 'insert', 'spooler', 'commit'] === $order
);

$insert = '';
$insertBinds = [];
foreach ($binds as list($sql, $params)) {
    if (false !== strpos($sql, 'INSERT INTO `hostPrinter`')) {
        $insert = $sql;
        $insertBinds = $params;
    }
}
$t->check(
    'both queues go up in one statement rather than one round trip each',
    2 === substr_count($insert, '(:r')
);
$t->check(
    'every placeholder is bound exactly once, so a driver that is not'
        . ' emulating prepares does not reject the repeat',
    count($insertBinds) === count(array_unique(array_keys($insertBinds)))
        && count($insertBinds) === substr_count($insert, ':r')
);

// ------------------------------------------- the machine that has no queues

list($log) = pfReport($db, ['subsystem' => 'cups', 'installed' => []]);
$t->check(
    'a machine reporting no queues still writes its spooler row -- without'
        . ' it the report cannot tell "nothing installed" from "never'
        . ' reported", and the host that needs looking at is the one that'
        . ' vanishes from the page',
    false !== strpos(implode("\n", $log), 'INSERT INTO `hostSpooler`')
);
$t->check(
    'and does not insert an empty printer row',
    false === strpos(implode("\n", $log), 'INSERT INTO `hostPrinter`')
);

// ---------------------------------------------------------- what a host may say

foreach (['cups' => 'cups', 'WINSPOOL' => 'winspool', '<b>evil</b>' => '',
    '' => ''] as $reported => $want) {
    $binds = [];
    $db->responder = function ($sql, $params) use (&$binds) {
        $binds[] = [$sql, $params];
        return null;
    };
    \FOG\Agent\PrinterFacts::report(
        pfHost(7),
        ['subsystem' => $reported, 'installed' => []]
    );
    $db->responder = null;
    $got = null;
    foreach ($binds as list($sql, $params)) {
        if (false !== strpos($sql, 'INSERT INTO `hostSpooler`')) {
            $got = $params[':sub'] ?? null;
        }
    }
    $t->check(
        "a reported subsystem of '$reported' stores '" . $want . "'",
        $want === $got
    );
}

$threw = 0;
try {
    \FOG\Agent\PrinterFacts::report(
        pfHost(7),
        ['installed' => array_fill(
            0,
            \FOG\Agent\PrinterFacts::MAX_PRINTERS + 1,
            ['name' => 'x']
        )]
    );
} catch (\RuntimeException $e) {
    $threw = $e->getCode();
}
$t->check(
    'a list past MAX_PRINTERS is refused with a 413 rather than being'
        . ' handed to the database',
    413 === $threw
);

// ------------------------------------------------------------- the registry

// A fact kind is a FACT_REPORTS entry and a poll block, never a route of its
// own (the route rule, protocol-v1.md). Left out, the class is dead code and
// every printer block a host sends is silently discarded.
$t->check(
    "State::FACT_REPORTS routes 'printers' to PrinterFacts",
    (\FOG\Agent\State::FACT_REPORTS['printers'] ?? null)
    === \FOG\Agent\PrinterFacts::class
);

// ---------------------------------------------------- the report's verdicts

/**
 * Call a protected static on the report.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function pd($name, array $args)
{
    $m = new \ReflectionMethod(\FOG\Reports\Printer_Deployment::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

// "never reported" is FOG not knowing, and it has to outrank everything: a
// host that has said nothing has no missing printers and no extra ones, and
// letting it fall through to 'ok' would report agreement with a machine
// nobody has heard from.
$t->check(
    'a host that never reported says so, whatever else is true of it',
    _('never reported') === pd('state', [false, ['A'], ['B'], 'boom'])
);
// Ordered by what to act on first. An error says WHY; a missing printer is
// something somebody asked for and did not get; an extra one in mode 1 is
// just somebody's own printer.
$t->check(
    'a recorded error outranks a missing printer',
    _('failed') === pd('state', [true, ['A'], [], 'boom'])
);
$t->check(
    'a missing printer outranks an extra one',
    _('missing') === pd('state', [true, ['A'], ['B'], ''])
);
$t->check(
    'an extra printer alone is reported as extra',
    _('extra') === pd('state', [true, [], ['B'], ''])
);
$t->check(
    'and a host with neither is ok',
    _('ok') === pd('state', [true, [], [], ''])
);
$t->check(
    'whitespace is not an error',
    _('ok') === pd('state', [true, [], [], '   '])
);

// The join hangs off hostSpooler, and nothing above can see it: these
// checks run against a fake connection, so the SQL is never executed. It is
// checked as source because getting it wrong is silent and expensive -- a
// join to hostPrinter loses every host that answered "nothing installed",
// which is the failure the second table exists to prevent. Proven on the lab
// database too (background_scripts/prove_printer_facts_end_to_end.php, where
// this same mutation fails nine checks); this is the half that runs in CI.
$reportSrc = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Reports/Printer_Deployment.php'
);
$t->check(
    'the report LEFT JOINs hostSpooler -- joining hostPrinter instead would'
        . ' silently drop every host that reported no queues, which is the'
        . ' one this report most needs to show',
    false !== strpos(
        $reportSrc,
        'LEFT OUTER JOIN `hostSpooler` ON `hspHostID` = `hostID`'
    )
);
$t->check(
    'and it is a LEFT join, so a host that never reported is a row rather'
        . ' than an absence',
    false === strpos($reportSrc, 'INNER JOIN `hostSpooler`')
);

// hostPrinterLevel stores 0/1/2 and the wire has always sent 0/a/ar -- two
// vocabularies for one setting, neither written down where an admin can see
// it (design 0010 section 1.3). This is the third and the only one meant for
// a person, so it has to cover every stored value including the empty string
// a 1.5-origin row carries.
foreach ([0 => 'off', 1 => 'assigned', 2 => 'exclusive', '' => 'off',
    '9' => 'off'] as $level => $want) {
    $t->check(
        "printer level '" . $level . "' reads as " . $want,
        _($want) === pd('mode', [$level])
    );
}

// ------------------------------------------- the desired set (design 0010 §5)

// Every printer created before schema 427 has an empty pURI, and every one
// created after it may still. Deriving on READ rather than backfilling once
// on upgrade is the decision this exercises: pPort is a longtext that has
// held whatever an admin typed for a decade, so some derivations WILL be
// wrong -- and a wrong answer stored in a column has to be corrected by hand
// on every install, where a wrong answer computed here is fixed for
// everybody by fixing the method.
/**
 * A printer built in memory.
 *
 * @param array $fields property => value
 *
 * @return \FOG\Items\Printer
 */
function pfPrinter(array $fields)
{
    $Printer = new \FOG\Items\Printer();
    foreach ($fields as $k => $v) {
        $Printer->set($k, $v);
    }

    return $Printer;
}

$cases = [
    'a TCP/IP port printer becomes socket:// on the RAW default port' => [
        ['config' => 'Local', 'ip' => '10.0.4.20'],
        'socket://10.0.4.20:9100',
    ],
    'a network printer\'s UNC path becomes smb://' => [
        ['config' => 'Network', 'port' => '\\\\srv\\HP4550'],
        'smb://srv/HP4550',
    ],
    'a CUPS printer keeps the lpd:// the legacy client built' => [
        ['config' => 'Cups', 'ip' => '10.0.4.20', 'name' => 'Accounts'],
        'lpd://10.0.4.20/Accounts',
    ],
    'iPrint gets a scheme of its own rather than being forced into another' => [
        ['config' => 'iPrint', 'port' => 'ipp://novell/ipp/x'],
        'iprint://ipp://novell/ipp/x',
    ],
    'an explicit URI overrides the derivation and is never second-guessed' => [
        ['config' => 'Local', 'ip' => '10.0.4.20',
            'uri' => 'ipps://printer.corp/ipp/print'],
        'ipps://printer.corp/ipp/print',
    ],
    'a Local printer with no address has no URI, and none is invented' => [
        ['config' => 'Local', 'ip' => ''],
        '',
    ],
    'an unrecognized type derives nothing rather than guessing' => [
        ['config' => 'Something', 'ip' => '10.0.4.20'],
        '',
    ],
];
foreach ($cases as $what => list($fields, $want)) {
    $got = pfPrinter($fields)->uri();
    $t->check($what . ' [' . $got . ']', $want === $got);
}

$t->check(
    'the driver is the model when there is one',
    'HP UPD PCL 6' === pfPrinter(
        ['model' => 'HP UPD PCL 6', 'file' => 'C:\\d\\x.inf']
    )->driver()
);
$t->check(
    'and falls back to the driver file',
    'C:\\d\\x.inf' === pfPrinter(['file' => 'C:\\d\\x.inf'])->driver()
);
$t->check(
    'an empty driver is a VALUE -- driverless IPP Everywhere, which FOG\'s'
        . ' four printer types cannot express at all',
    '' === pfPrinter(['config' => 'Local'])->driver()
);

// The mode the agent is sent says what it means. hostPrinterLevel stores
// 0/1/2 and the legacy wire sends 0/a/ar; neither is written down where an
// admin can see it.
$t->check(
    'the desired mode vocabulary is words, and covers every stored level',
    [0 => 'off', 1 => 'assigned', 2 => 'exclusive'] === \FOG\Agent\PrinterSet::MODES
);
$t->check(
    'a level outside 0-2 falls back to off rather than to a mode that acts',
    !isset(\FOG\Agent\PrinterSet::MODES[3])
);

// The capability is gated on FOG's EXISTING printermanager module, not a new
// switch: admins have been turning that one off for a decade and know where
// it is, so a host's current choice carries over untouched.
$t->check(
    "the printers capability is gated on the existing printermanager module",
    'printermanager' === (\FOG\Agent\State::CAPABILITIES['printers'] ?? null)
);
$t->check(
    "State::ITEM_REPORTS routes a printer result to PrinterSet",
    (\FOG\Agent\State::ITEM_REPORTS['printers'] ?? null)
    === \FOG\Agent\PrinterSet::class
);

// A success has to CLEAR the previous failure, or the report shows an error
// against a printer that is now installed -- an admin chasing a stale message
// is worse off than one chasing none. Exercised through the decision itself
// rather than by reading the source for it.
/**
 * Call a protected static on PrinterSet.
 *
 * @param string $name the method
 * @param array  $args the arguments
 *
 * @return mixed
 */
function ps($name, array $args)
{
    $m = new \ReflectionMethod(\FOG\Agent\PrinterSet::class, $name);
    $m->setAccessible(true);

    return $m->invokeArgs(null, $args);
}

foreach (\FOG\Agent\PrinterSet::STATUSES as $status) {
    $settled = in_array(
        $status,
        \FOG\Agent\PrinterSet::SETTLED_STATUSES,
        true
    );
    $got = ps('errorFor', [$status, 'lpadmin: bad device-uri']);
    $t->check(
        "'" . $status . "' " . ($settled ? 'clears' : 'keeps')
            . ' the error [' . $got . ']',
        $settled ? '' === $got : 'lpadmin: bad device-uri' === $got
    );
}
$t->check(
    'failed and unsupported are NOT settled, or an error could never be'
        . ' recorded at all',
    !in_array('failed', \FOG\Agent\PrinterSet::SETTLED_STATUSES, true)
        && !in_array('unsupported', \FOG\Agent\PrinterSet::SETTLED_STATUSES, true)
);
$t->check(
    'every settled status is a status the agent may actually report',
    [] === array_diff(
        \FOG\Agent\PrinterSet::SETTLED_STATUSES,
        \FOG\Agent\PrinterSet::STATUSES
    )
);
$t->check(
    'a provider that wrote a novel keeps a line, not a log',
    \FOG\Agent\PrinterSet::MAX_ERROR
    === strlen(ps('errorFor', ['failed', str_repeat('x', 4000)]))
);

$t->finish();
