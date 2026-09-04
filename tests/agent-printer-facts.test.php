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

$t->finish();
