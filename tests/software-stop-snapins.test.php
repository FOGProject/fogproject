<?php
/**
 * Software installs before snapins, and an entry can hold the snapins
 * (forum 18322).
 *
 * The agent runs capabilities in the order the server sends them, so
 * State::CAPABILITIES must list 'software' before 'snapin'. A software
 * entry carries a "Stop snapins if this fails" flag (swStopSnapins, step
 * 463) that SoftwareSet::desired() sends as `stop_snapins`.
 *
 * DB-free: reads the constant, the field map and the source.
 *
 * Usage: php tests/software-stop-snapins.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('software-stop-snapins');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';

// Read through reflection so the order is checked at run time.
$caps = (new \ReflectionClass(\FOG\Agent\State::class))->getConstant('CAPABILITIES');
$keys = array_keys((array)$caps);
$software = array_search('software', $keys, true);
$snapin = array_search('snapin', $keys, true);
$t->check(
    'capabilities: software is listed before snapin',
    is_int($software) && is_int($snapin) && $software < $snapin
);

$ref = new \ReflectionClass(\FOG\Items\Software::class);
$fields = $ref->getDefaultProperties()['databaseFields'];
$t->check(
    'Software maps stopSnapins to swStopSnapins',
    'swStopSnapins' === ($fields['stopSnapins'] ?? null)
);

// A source pin, not a behavior check: desired() reads the host's set through
// Resolver and loads each Software row, so it cannot run without a database.
$setSrc = (string)file_get_contents($web . '/src/Agent/SoftwareSet.php');
$t->check(
    'SoftwareSet::desired() emits stop_snapins from the stopSnapins field',
    false !== strpos(
        $setSrc,
        "'stop_snapins' => (bool)\$Software->get('stopSnapins')"
    )
);

$schemaSrc = (string)file_get_contents($web . '/commons/schema.php');
$t->check(
    'schema step 463 adds swStopSnapins',
    (bool)preg_match(
        '/\/\/ 463\n\$this->schema\[\] = \[.*?ADD COLUMN `swStopSnapins` '
        . 'tinyint\(1\) NOT NULL DEFAULT 0/s',
        $schemaSrc
    )
);

$t->finish();
