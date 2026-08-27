<?php
/**
 * A FOS report stays readable after its task, and its host, are gone.
 *
 * The point of GH-1206 is that a failure message is stored and findable
 * later instead of arriving as a phone photo of a wrapped console. taskLog
 * stored no host and no task type of its own and reached both through LEFT
 * OUTER JOINs against `tasks` -- and while nothing deletes taskLog rows,
 * Route::deletemass('host') cascades to `task` and taskLog is in no cascade
 * at all. So deleting a host destroyed its tasks and left the reports behind
 * with NULL where the host name had been, at the same moment the host row
 * that could have supplied it went too. On the install this was written
 * against, 9 of 56 rows were already orphaned that way.
 *
 * Schema 341 copies hostID, host name and task type name onto the row at
 * write time, and TaskManagement::_logQueryFrom() prefers the copy. Two
 * things are asserted here and they fail differently:
 *
 *   the WRITER puts the identity on the row       (TaskError::_logRow)
 *   the READER prefers it and falls back cleanly  (_logQueryFrom)
 *
 * The reader half runs the real statement -- the method exists so this test
 * cannot drift from a copy of the SQL -- against TEMPORARY tables, which
 * live on one connection and vanish with it. Every CREATE is asserted to be
 * TEMPORARY before it is issued, because a plain CREATE TABLE here would
 * leave a table behind in whatever database FOG_TEST_DSN names.
 *
 * Needs a database only for that half: the join semantics ARE the fix, and a
 * fake connection cannot show them. Skips without FOG_TEST_DSN, the same
 * convention tests/schema-executes.test.php uses. Unlike that one this needs
 * no CREATE DATABASE, so an ordinary FOG database user can run it.
 *
 * Usage:
 *   php tests/tasklog-report-retention.test.php
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;dbname=fog' FOG_TEST_USER=... \
 *     FOG_TEST_PASS=... php tests/tasklog-report-retention.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

use FOG\Base\FOGCore;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('tasklog-retention');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/*
 * 1. The writer. Driven for real against a fake connection, so this half
 *    needs no database: what is asserted is the INSERT that comes out.
 *
 *    TaskError::_logRow() is private static and TaskError's constructor IS
 *    the endpoint -- it reads the request, writes, notifies and exits -- so
 *    the method is invoked directly rather than by building the class.
 */
$t->check(
    'TaskLog declares the three identity fields',
    (function () {
        $p = new \ReflectionProperty('FOG\Items\TaskLog', 'databaseFields');
        $p->setAccessible(true);
        $fields = $p->getValue(FOGCore::getClass('TaskLog'));
        return isset($fields['hostID'], $fields['hostName'], $fields['taskTypeName'])
            && 'logHostID' === $fields['hostID']
            && 'logHostName' === $fields['hostName']
            && 'logTaskTypeName' === $fields['taskTypeName'];
    })()
);

$host = FOGCore::getClass('Host')
    ->set('id', 42)
    ->set('name', 'lab-07');

/**
 * A Task whose type resolves without a database.
 *
 * Task::getTaskTypeText() reads through the TaskType relationship, which a
 * fake connection cannot populate. Overridden so this half stays about what
 * _logRow() DOES with the type, not about how a task finds one.
 */
class RetentionTask extends \FOG\Items\Task
{
    public function getTaskTypeText()
    {
        return 'Deploy';
    }
}

$task = (new RetentionTask())
    ->set('id', 7)
    ->set('stateID', 3);

FogTestHarness::setStatic('FOGBase', 'Host', $host);

// The values arrive as bound parameters, so the SQL text alone cannot show
// that the right thing was written. The responder sees both.
$insert = '';
$bound = [];
$db->responder = function ($sql, $params) use (&$insert, &$bound) {
    if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
        $insert = $sql;
        $bound = (array)$params;
    }
    return null;
};

$m = new \ReflectionMethod('FOG\TaskHandling\TaskError', '_logRow');
$m->setAccessible(true);
$m->invoke(null, $task, 'error', 'partclone died');
$db->responder = null;

if ($t->check('_logRow() issues an INSERT against taskLog', '' !== $insert)) {
    $expected = [
        'logHostID' => 42,
        'logHostName' => 'lab-07',
        'logTaskTypeName' => 'Deploy',
    ];
    foreach ($expected as $column => $value) {
        $t->check(
            "the INSERT names `$column`",
            false !== strpos($insert, '`' . $column . '`')
        );
        $t->check(
            "`$column` is written as " . var_export($value, true),
            in_array($value, $bound, false)
            && in_array($value, $bound, true)
        );
    }
}

/*
 * 2. The reader.
 */
$dsn = getenv('FOG_TEST_DSN');
if (false === $dsn || '' === $dsn) {
    echo "SKIP  no FOG_TEST_DSN set; the read path was not exercised\n";
    // The writer checks above still ran; report them rather than throwing
    // the work away, but do not fail the suite for a missing database.
    if (count($t->failures)) {
        $t->finish();
    }
    echo 'ok  ' . $t->count . " checks passed (writer half only)\n";
    exit(0);
}
if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: FOG_TEST_DSN is set but pdo_mysql is missing.\n");
    exit(1);
}
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$pdo = new \PDO(
    $dsn,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);

/**
 * Issues one statement, refusing any CREATE that is not TEMPORARY.
 *
 * The guard is not decoration. The manifest's create string is a plain
 * CREATE TABLE, and running it here as-is would leave a real `taskLog`
 * shadow behind in whatever database the DSN names -- which may well be a
 * live one, since this test deliberately needs no CREATE DATABASE right.
 *
 * @param \PDO   $pdo the connection
 * @param string $sql the statement
 *
 * @return void
 */
function retentionExec($pdo, $sql)
{
    if (preg_match('/^\s*CREATE\s+(?!TEMPORARY\b)/i', $sql)) {
        fwrite(STDERR, "FAIL: refusing a non-TEMPORARY CREATE:\n$sql\n");
        exit(1);
    }
    $pdo->exec($sql);
}

// taskLog comes from the manifest, so the columns under test are the ones
// the release actually ships rather than a hand-written copy.
$manifest = include dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
$create = $manifest['tables']['taskLog']['create'] ?? '';
$create = preg_replace('/^CREATE TABLE IF NOT EXISTS /i', 'CREATE TEMPORARY TABLE ', $create);
$t->check(
    'the manifest create for taskLog could be made TEMPORARY',
    0 === stripos($create, 'CREATE TEMPORARY TABLE')
);

// The joined tables only need the columns the query touches.
retentionExec($pdo, 'CREATE TEMPORARY TABLE `taskStates` ('
    . '`tsID` int(11) NOT NULL, `tsName` varchar(30) NOT NULL,'
    . '`tsIcon` varchar(255) NOT NULL)');
retentionExec($pdo, 'CREATE TEMPORARY TABLE `tasks` ('
    . '`taskID` int(11) NOT NULL, `taskHostID` int(11) NOT NULL,'
    . '`taskTypeID` mediumint(9) NOT NULL)');
retentionExec($pdo, 'CREATE TEMPORARY TABLE `hosts` ('
    . '`hostID` int(11) NOT NULL, `hostName` varchar(16) NOT NULL)');
// ttName is UNIQUE on the real table and the reportType join relies on it.
retentionExec($pdo, 'CREATE TEMPORARY TABLE `taskTypes` ('
    . '`ttID` mediumint(9) NOT NULL, `ttName` varchar(30) NOT NULL UNIQUE,'
    . '`ttIcon` varchar(30) NOT NULL)');
retentionExec($pdo, $create);

$pdo->exec("INSERT INTO `taskStates` VALUES (3,'In Progress','fa-play'),(7,'Failed','fa-times')");
$pdo->exec("INSERT INTO `taskTypes` VALUES (1,'Deploy','fa-download'),(2,'Upload','fa-upload')");
$pdo->exec("INSERT INTO `hosts` VALUES (100,'alpha'),(102,'gamma')");
$pdo->exec("INSERT INTO `tasks` VALUES (10,100,1)");
$pdo->exec(
    "INSERT INTO `taskLog`"
    . " (`taskID`,`taskStateID`,`ip`,`createdBy`,`logType`,`logText`,"
    . "`logHostID`,`logHostName`,`logTaskTypeName`) VALUES"
    // task alive, host alive
    . " (10,7,'127.0.0.1','fos','error','live',100,'alpha','Deploy'),"
    // task deleted, host still there -- the link must survive
    . " (55,7,'127.0.0.1','fos','error','task gone',102,'gamma','Upload'),"
    // task deleted and host deleted -- the report is all that is left
    . " (56,7,'127.0.0.1','fos','error','both gone',999,'deadhost','Deploy'),"
    // written before schema 341: nothing stored, joins must still answer
    . " (10,7,'127.0.0.1','fos','error','pre-341',NULL,'',''),"
    // a state row written before schema 373: no identity of its own, because
    // 341's backfill excluded logType='state' explicitly
    . " (10,3,'127.0.0.1','fog','state',NULL,NULL,'',''),"
    // a state row written by TaskLog::recordState(): carries its own identity,
    // and here BOTH its task (11) and its host (101) are gone. This is the row
    // 341 said could not exist and 373 exists to produce -- the log pane shows
    // state rows and the dashboard counts them, so one that cannot name its
    // host or say whether it was a capture is not readable.
    . " (11,3,'127.0.0.1','fog','state',NULL,101,'beta','Upload'),"
    // the host has since been RENAMED: host 100 is 'alpha' now and the
    // report was written when it was 'oldname'. The stored copy has to win,
    // or the fallback is really just a null-check and the record is not
    // historical at all.
    . " (10,7,'127.0.0.1','fos','error','renamed',100,'oldname','Deploy')"
);

$fromMethod = new \ReflectionMethod('FOG\TaskManagement', '_logQueryFrom');
$fromMethod->setAccessible(true);
$from = sprintf($fromMethod->invoke(null), 'v');
$rows = $pdo->query(
    'SELECT `logText`,`logStateName`,`logTypeName`,`logTypeIcon`,'
    . '`logHostID`,`logHostName` ' . $from . ' ORDER BY `id`'
)->fetchAll(\PDO::FETCH_ASSOC);

$by = [];
foreach ($rows as $row) {
    $by[$row['logText']] = $row;
}

$t->check('all six fixture rows came back', 6 === count($rows));

// The ordering inside the COALESCE, not just its presence.
$t->check(
    'a report keeps the host name it was written with, not the current one',
    'oldname' === ($by['renamed']['logHostName'] ?? null)
);
$t->check(
    'a renamed host still links, because the id did not change',
    100 === (int)($by['renamed']['logHostID'] ?? 0)
);

// The case the whole change exists for.
$t->check(
    'a report whose task is gone keeps its host name',
    'gamma' === ($by['task gone']['logHostName'] ?? null)
);
$t->check(
    'a report whose task is gone keeps its task type and icon',
    'Upload' === ($by['task gone']['logTypeName'] ?? null)
    && 'fa-upload' === ($by['task gone']['logTypeIcon'] ?? null)
);
$t->check(
    'a report whose task is gone still links to its host',
    102 === (int)($by['task gone']['logHostID'] ?? 0)
);

// Host deleted too: the name is all that is left, and it must not pretend
// to be a link.
$t->check(
    'a report whose host is gone keeps the name it had',
    'deadhost' === ($by['both gone']['logHostName'] ?? null)
);
$t->check(
    'a report whose host is gone offers no host link',
    // array_key_exists, not ??: the column IS there and its value is NULL,
    // which is the whole point, and ?? cannot tell those apart.
    array_key_exists('logHostID', (array)($by['both gone'] ?? []))
    && null === $by['both gone']['logHostID']
);

// Falling back has to keep working, or this is a fix that breaks the rows it
// was meant to protect.
$t->check(
    'a pre-341 report still resolves through the joins',
    'alpha' === ($by['pre-341']['logHostName'] ?? null)
    && 'Deploy' === ($by['pre-341']['logTypeName'] ?? null)
    && 100 === (int)($by['pre-341']['logHostID'] ?? 0)
);
$t->check(
    'a live report reads the same as it always did',
    'alpha' === ($by['live']['logHostName'] ?? null)
    && 'Deploy' === ($by['live']['logTypeName'] ?? null)
    && 'Failed' === ($by['live']['logStateName'] ?? null)
);
// taskLog carries taskStateID itself, so this one never needed the task.
// Indexed by position: the state row's logText is NULL, which is not a key.
$t->check(
    'a state row still resolves its state',
    'In Progress' === ($rows[4]['logStateName'] ?? null)
);

// The state row that carries its own identity. Found by that identity rather
// than by position, because its logText is NULL and cannot key $by.
$ownIdentity = null;
foreach ($rows as $row) {
    if ('beta' === ($row['logHostName'] ?? null)) {
        $ownIdentity = $row;
    }
}
if ($t->check('the recordState() state row is returned', null !== $ownIdentity)) {
    // Neither its task nor its host exists, so every one of these can only
    // come from the row's own columns.
    $t->check(
        'a state row names its host after task AND host are gone',
        'beta' === $ownIdentity['logHostName']
    );
    $t->check(
        'a state row says whether it was a capture or a deploy',
        'Upload' === $ownIdentity['logTypeName']
    );
    $t->check(
        'a state row keeps its state icon through the reportType join',
        '' !== (string)($ownIdentity['logTypeIcon'] ?? '')
    );
    // logHostID comes from the `hosts` join by design -- the grid links the
    // name with it, and a link to a deleted host is worse than no link.
    $t->check(
        'but it does NOT invent a host link for a deleted host',
        null === ($ownIdentity['logHostID'] ?? null)
    );
}

$t->finish();
