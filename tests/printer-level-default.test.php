<?php
/**
 * A host with no printer level follows the server default (fogproject#2087).
 *
 * The level stays the host's own: a group never grants it, because a host can
 * be in several groups and a grant only adds. A host that stores 0, 1 or 2
 * keeps it. A host that stores '' (the column default, never written at
 * creation) runs at FOG_CLIENT_PRINTERMANAGER_DEFAULT_LEVEL. Both clients,
 * the report and the host tab read it through Resolver::resolvePrinterLevel().
 *
 * Usage: php tests/printer-level-default.test.php
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

use FOG\Agent\PrinterSet;
use FOG\Assign\Resolver;
use FOG\Client\PrinterClient;
use FOG\Items\Host;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('printer-level-default');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();

/**
 * Set the server default as the settings cache would hold it.
 *
 * @param string $value the stored setting
 *
 * @return void
 */
function setDefault($value)
{
    FogTestHarness::setStatic('FOGBase', '_settingsCache', [
        'FOG_CLIENT_PRINTERMANAGER_DEFAULT_LEVEL' => [
            'value' => $value,
            'ts' => time()
        ]
    ]);
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
}

// ------------------------------------------------------------- the rule
setDefault('2');
// Pairs, not keys: PHP makes the keys '0' and 0 one key, and the int cases
// would never run. The ORM can hand back either type.
foreach ([['0', 0], ['1', 1], ['2', 2], [0, 0], [2, 2]] as [$stored, $want]) {
    $t->check(
        "a host that stores " . var_export($stored, true)
            . " keeps it over a default of 2",
        $want === Resolver::resolvePrinterLevel($stored)
    );
}
foreach (['', '  ', '9', null] as $stored) {
    $t->check(
        "a host that stores '" . $stored . "' follows the default",
        2 === Resolver::resolvePrinterLevel($stored)
    );
}
setDefault('1');
$t->check(
    'changing the default reaches a host with no level',
    1 === Resolver::resolvePrinterLevel('')
);
foreach (['', '7', 'x'] as $bad) {
    setDefault($bad);
    $t->check(
        "a default of '" . $bad . "' reads as 0, which changes nothing",
        0 === Resolver::printerLevelDefault()
    );
}

// --------------------------------------------------- the legacy client
/**
 * The mode the legacy endpoint sends for a stored level.
 *
 * @param mixed $stored the host's hostPrinterLevel
 *
 * @return mixed
 */
function legacyMode($stored)
{
    FogTestHarness::setStatic(
        'FOGBase',
        'Host',
        (new Host())->set('id', 11)->set('printerLevel', $stored)
    );
    $payload = (new \ReflectionClass(PrinterClient::class))
        ->newInstanceWithoutConstructor()
        ->json();

    return $payload['mode'] ?? 'missing';
}

setDefault('2');
$t->check(
    'the legacy client sends ar for a host with no level under a default of 2',
    'ar' === legacyMode('')
);
$t->check(
    'and 0 for a host that says 0 under the same default',
    0 === legacyMode('0')
);
setDefault('0');
$t->check(
    'and 0 for a host with no level under the shipped default',
    0 === legacyMode('')
);

// ---------------------------------------------------------- fog-agent
setDefault('1');
$desired = PrinterSet::desired(
    (new Host())->set('id', 11)->set('printerLevel', '')
);
$t->check(
    'fog-agent is told assigned for a host with no level under a default of 1',
    PrinterSet::MODE_ASSIGNED === ($desired['manage'] ?? null)
);
$desired = PrinterSet::desired(
    (new Host())->set('id', 11)->set('printerLevel', '2')
);
$t->check(
    'and exclusive for a host that says 2',
    PrinterSet::MODE_EXCLUSIVE === ($desired['manage'] ?? null)
);

// ------------------------------------------------------- the report
/**
 * The WHERE the deployment report runs under the current default.
 *
 * @param FogFakeDb $db the fake
 *
 * @return string
 */
function reportWhere($db)
{
    $db->log = [];
    $m = new \ReflectionMethod(
        \FOG\Reports\Printer_Deployment::class,
        'reportRows'
    );
    $m->setAccessible(true);
    $m->invoke(
        (new \ReflectionClass(\FOG\Reports\Printer_Deployment::class))
            ->newInstanceWithoutConstructor()
    );
    $sql = preg_grep('/hostSpooler/', $db->log);

    return $sql ? (string)preg_replace('/\s+/', ' ', (string)reset($sql)) : '';
}

setDefault('0');
$where = reportWhere($db);
$t->check(
    'under a default of 0 the report lists only hosts that say 1 or 2',
    false !== strpos($where, "`hostPrinterLevel` IN ('1', '2')")
);
setDefault('2');
$where = reportWhere($db);
$t->check(
    'under a default of 2 it lists every host except one that says 0',
    false !== strpos($where, "`hostPrinterLevel` <> '0'")
        && false === strpos($where, "IN ('1', '2')")
);

// ------------------------------------------- the host tab and mass edit
$hostSrc = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Pages/HostManagement.php'
);
$t->check(
    'the host tab offers "Use the server default" as a level of its own',
    false !== strpos($hostSrc, "_('Use the server default (now: %s)')")
);
$t->check(
    "the host save accepts '' and still refuses a missing level (#2046)",
    false !== strpos(
        $hostSrc,
        "in_array(\$level, array_merge([''], Resolver::PRINTER_LEVELS), true)"
    )
);
$m = new \ReflectionMethod(\FOG\Pages\HostManagement::class, 'massEditCoreFields');
$m->setAccessible(true);
$core = $m->invoke(
    (new \ReflectionClass(\FOG\Pages\HostManagement::class))
        ->newInstanceWithoutConstructor()
);
$t->check(
    "mass edit Clear on all writes '', so the hosts follow the default",
    '' === ($core['printerLevel']['empty'] ?? 'missing')
);

// -------------------------------------------------------- the setting
$schemaSrc = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/commons/schema.php'
);
$t->check(
    'a schema step seeds the default at 0, so an upgrade changes no host',
    1 === preg_match(
        "/'FOG_CLIENT_PRINTERMANAGER_DEFAULT_LEVEL'.*?'0','FOG Client - Printer Manager'/s",
        $schemaSrc
    )
);
$t->check(
    'and touches no host row',
    1 !== preg_match('/UPDATE `hosts` SET `hostPrinterLevel`/', $schemaSrc)
);

$t->finish();
