<?php
/**
 * A false tasking writes its own task log row.
 *
 * A deploy to an unregistered or pending host has no Task row, so
 * TaskLog::recordState() never sees it. TaskLog::recordFalseTasking() is the
 * only writer, and the Imaging report reads what it writes (forum 18086).
 * tests/bootmenu-ipxe-output.test.php runs the boot menu against a STUB of
 * this method, so it proves the menu calls it and not what it stores. This
 * file runs the REAL method against a fake database and reads the INSERT that
 * comes out: the bound values, matched to the columns by position.
 *
 * Usage: php tests/tasklog-false-tasking.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\TaskLog;
use FOG\Items\TaskState;
use FOG\Items\TaskType;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('tasklog-false-tasking');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * The task type and the image, resolved without a database.
 */
class FalseTaskingType extends TaskType
{
    public function get($key = '')
    {
        return 'name' === $key ? 'Deploy' : parent::get($key);
    }
}
class FalseTaskingImage extends Image
{
    // The real isValid() wants every required column of an image.
    public function isValid()
    {
        return true;
    }

    public function get($key = '')
    {
        return 'name' === $key ? 'win11-base' : parent::get($key);
    }
}

/**
 * Runs the real method and returns the INSERT as column => bound value.
 *
 * @param object $Host      the host
 * @param string $createdBy the menu user
 *
 * @return array
 */
function falseTaskingRow($Host, $createdBy)
{
    global $db;
    $captured = [];
    $db->responder = function ($sql, $params) use (&$captured) {
        if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
            $captured = ['sql' => $sql, 'params' => (array)$params];
        }
        return null;
    };
    $db->log = [];
    TaskLog::recordFalseTasking(
        $Host,
        (new FalseTaskingType())->set('id', 1),
        (new FalseTaskingImage())->set('id', 5),
        'aa:bb:cc:dd:ee:ff',
        $createdBy
    );
    $db->responder = null;
    if (!$captured) {
        return [];
    }
    // "INSERT INTO `taskLog` (`a`,`b`) VALUES (...)": names, then values.
    if (!preg_match('#\(([^)]*)\)\s*VALUES#is', $captured['sql'], $m)) {
        return [];
    }
    preg_match_all('#`([^`]+)`#', $m[1], $names);
    $values = array_values($captured['params']);
    if (count($names[1]) !== count($values)) {
        return [];
    }
    return array_combine($names[1], $values);
}

$unregistered = falseTaskingRow(new Host(), 'menuuser');
if (!$t->check('an unregistered host writes one taskLog INSERT', [] !== $unregistered)) {
    $t->finish();
}
$t->check('no Task row: taskID is 0', 0 === (int)$unregistered['taskID']);
// save() leaves a zero id out of the INSERT, so the nullable column takes its
// default. Either way no host id other than 0 or NULL is stored.
$t->check(
    'an invalid host writes host id 0 (or leaves it NULL)',
    0 === (int)($unregistered['logHostID'] ?? 0)
);
$t->check(
    'an invalid host writes an empty host name',
    '' === $unregistered['logHostName']
);
$t->check(
    'the state is In-Progress',
    TaskState::getProgressState() === (int)$unregistered['taskStateID']
);
$t->check(
    'the MAC is written',
    'aa:bb:cc:dd:ee:ff' === $unregistered['logMAC']
);
$t->check(
    'the image name is written',
    'win11-base' === $unregistered['logImageName']
);
$t->check(
    'the task type name is written',
    'Deploy' === $unregistered['logTaskTypeName']
);
$t->check(
    'createdBy is written',
    'menuuser' === $unregistered['createdBy']
);

// A pending host has a row, so its id and name go on the log row too.
$pending = falseTaskingRow(
    (new Host())->set('id', 42)->set('name', 'lab-07'),
    str_repeat('x', 40)
);
$t->check('a pending host writes one taskLog INSERT', [] !== $pending);
$t->check('a pending host writes its id', 42 === (int)($pending['logHostID'] ?? -1));
$t->check(
    'a pending host writes its name',
    'lab-07' === ($pending['logHostName'] ?? null)
);
$t->check(
    'a pending host still writes the MAC',
    'aa:bb:cc:dd:ee:ff' === ($pending['logMAC'] ?? null)
);
$t->check(
    'createdBy is cut to the 30 characters of the column',
    str_repeat('x', 30) === ($pending['createdBy'] ?? null)
);

$t->finish();
