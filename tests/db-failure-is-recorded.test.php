<?php
/**
 * A failed database operation must leave a trace even when nobody is signed in.
 *
 * PORT of the working-1.6 test of the same name. Same defects, same fix; the
 * differences are noted where they matter.
 *
 * FOGController::save(), destroy() and load() record a failure by calling
 * FOGBase::logHistory(), and logHistory() returns without doing anything
 * unless self::$FOGUser is a valid User. Nothing in packages/web/service/,
 * packages/web/lib/reg-task/ or the daemons ever sets one -- they are matched
 * to a HOST by MAC or token, not to a user -- so on every one of those paths
 * the failure branch runs and writes nowhere.
 *
 * debug() is not a second chance on THIS branch, and this is where 1.5 is
 * worse than 1.6: it writes to no file at all. It printf()s into the page and
 * returns immediately when self::$service or self::$ajax is set, which on a
 * machine endpoint is always. So a failed write on a service path had
 * literally no possible output. (1.6's at least reaches a file, behind a
 * globalSetting that ships off.)
 *
 * There are two distinct defects here and this test pins both, on the write
 * path and on the read path.
 *
 * 1. NOT REPORTED. PDODB::$throwOnQueryError defaults false, so query()
 *    catches the PDOException and returns normally. save() therefore never
 *    enters its own catch. For a NEW row the missing insertId() is caught by
 *    the "no valid ID was assigned" throw; for an EXISTING row -- every
 *    progress update, every task state change, every inventory write against
 *    a known host -- there is nothing to catch on, so save() logs the SUCCESS
 *    message and returns $this. `if (!$x->save())` is not merely unrecorded
 *    there, it is answered "fine".
 *
 * 2. NOT RECORDED. Even on the path that does return false, nothing is
 *    written anywhere a human can read.
 *
 * The read path has only the second defect, and is quieter for it. There is
 * no return value to get wrong -- load() answers $this whether or not the row
 * was read, and must, or `new Host(42)` would become fatal on a database blip
 * -- so an object that could NOT be read is indistinguishable from a row that
 * genuinely holds no data. The log line is the entire fix there, which is why
 * assertion 5 matters as much as assertion 4: load()'s catch also handles its
 * ordinary control flow, and a fault log full of "Operation field not set"
 * would bury the one line worth having.
 *
 * TaskError::_logRow() is driven at its real call site rather than asserted
 * about in the abstract. It discards save()'s return BY DESIGN -- it is the
 * sink FOS failure reports land in, so there is nothing above it to report to
 * -- which is why the fix had to go in the framework and not in the callers.
 *
 * Asserts the OUTCOME, not a spelling: "a durable record naming the failure
 * exists". A fix is free to choose the file.
 *
 * DB-free: reuses tests/lib/scope-harness.php, which boots FOG against a fake
 * database and points FOG_LOG_DIR at a temp directory.
 *
 * Usage: php tests/db-failure-is-recorded.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';
require __DIR__ . '/lib/scope-harness.php';
scopeHarnessBoot($web, 'none');

/**
 * A database whose statements fail the way a real one does.
 *
 * PDODB::query() catches PDOException, calls debug()/error(), records the
 * message on ->error and returns $this. It rethrows only when
 * $throwOnQueryError is true, which nothing sets. insertId() then answers 0
 * because no row was inserted. That is all this reproduces.
 */
class FogRejectingDb extends ScopeFakeDB
{
    const REJECTION = "SQLSTATE[22007]: Invalid datetime format: 1292 "
        . "Incorrect datetime value: '' for column 'taskLogCreated'";

    /** @var int how many writes were attempted */
    public $writes = 0;

    /**
     * @var bool reject SELECTs too. Off by default so the write assertions
     *           are not perturbed by the lazy loads FOGController does on
     *           the way to a save.
     */
    public $rejectReads = false;

    public function query($sql, $a = array(), $p = array())
    {
        if (preg_match('/^\s*(INSERT|UPDATE|REPLACE|DELETE)\b/i', $sql)) {
            $this->writes++;
            $this->error = self::REJECTION;
            return $this;
        }
        if ($this->rejectReads && preg_match('/^\s*SELECT\b/i', $sql)) {
            $this->error = self::REJECTION;
            return $this;
        }
        $this->error = false;
        return parent::query($sql, $a, $p);
    }

    public function insertId()
    {
        return 0;
    }
}

$set = function ($class, $prop, $value) {
    $p = new ReflectionProperty($class, $prop);
    $p->setAccessible(true);
    $p->setValue(null, $value);
};

$db = new FogRejectingDb();
$set('FOGBase', 'DB', $db);

// The condition every machine-facing request boots into: LoadGlobals builds
// `new User(0)` because there is no session to read FOG_USER from.
$anon = new User(0);
$GLOBALS['currentUser'] = $anon;
$set('FOGBase', 'FOGUser', $anon);
if ($anon->isValid()) {
    fwrite(STDERR, "FAIL: the anonymous user is valid; this test would "
        . "pass vacuously because logHistory() would record after all\n");
    exit(1);
}

// What the installer creates (lib/common/functions.sh, "Creating FOG fault
// log directory"). Made here rather than by the harness because its ABSENCE
// is also a supported state -- a web tree updated ahead of its installer --
// and the fallback that covers it is asserted below.
$faultDir = FOG_LOG_DIR . DIRECTORY_SEPARATOR . 'faults';
@mkdir($faultDir, 0700, true);

// PHP's own channel is logFault()'s fallback. Pointed inside FOG_LOG_DIR so
// that a fallback still satisfies "a durable record exists" -- the assertions
// pin the outcome, not which file -- and so the fallback does not spray the
// suite's output onto stderr.
ini_set('error_log', FOG_LOG_DIR . DIRECTORY_SEPARATOR . 'php-fallback.log');

/**
 * Every file under FOG_LOG_DIR, flattened to one string.
 *
 * @return string
 */
function fogLogContents()
{
    if (!is_dir(FOG_LOG_DIR)) {
        return '';
    }
    $out = '';
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            FOG_LOG_DIR,
            FilesystemIterator::SKIP_DOTS
        )
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            $out .= (string) @file_get_contents($f->getPathname());
        }
    }
    return $out;
}

$failures = array();

// ---------------------------------------------------------------------
// 1. An existing row whose write was rejected must not report success.
// ---------------------------------------------------------------------
$existing = new TaskLog(0);
$existing->set('id', 4242)
    ->set('taskID', 7)
    ->set('text', 'db-failure existing row');
$result = $existing->save();
if (false !== $result) {
    $failures[] = 'save() on an EXISTING row answered '
        . (is_object($result) ? get_class($result) : var_export($result, true))
        . ' after the write was rejected -- a truthy answer, so every '
        . '`if (!$obj->save())` on a machine path treats a lost write as a '
        . 'completed one';
}

// ---------------------------------------------------------------------
// 2. A failed write must leave a record with no user signed in.
// ---------------------------------------------------------------------
$before = fogLogContents();
$newRow = new TaskLog(0);
$newRow->set('taskID', 9)->set('text', 'db-failure new row');
$newRow->save();
if (fogLogContents() === $before) {
    $failures[] = 'a rejected write left nothing under FOG_LOG_DIR with no '
        . 'user signed in -- logHistory() returned early, and debug() on this '
        . 'branch writes to no file at all';
}

// ---------------------------------------------------------------------
// 3. The sink itself: TaskError::_logRow() discards save()'s return, so
//    only a fix inside the framework can cover it.
// ---------------------------------------------------------------------
$host = new Host(0);
$host->set('id', 11);
$set('FOGBase', 'Host', $host);

$task = new Task(0);
$task->set('id', 33)->set('stateID', 3);
// getTaskTypeText() dereferences the loaded TaskType object; the fake
// database answers lazy loads with nothing, so it is seeded directly.
$task->set('type', (new TaskType(0))->set('id', 1)->set('name', 'Deploy'));

$before = fogLogContents();
$writesBefore = $db->writes;
try {
    $m = new ReflectionMethod('TaskError', '_logRow');
    $m->setAccessible(true);
    $m->invokeArgs(null, array($task, TaskLog::TYPE_ERROR, 'db-failure report'));
} catch (Throwable $e) {
    $failures[] = 'TaskError::_logRow() could not be driven: '
        . $e->getMessage();
}
if ($db->writes === $writesBefore) {
    $failures[] = 'TaskError::_logRow() attempted no write, so this '
        . 'assertion proves nothing -- has the call site changed?';
} elseif (fogLogContents() === $before) {
    $failures[] = "TaskError::_logRow()'s rejected write left no record. "
        . 'It discards save()\'s return by design -- it IS the failure sink '
        . '-- so a report of a FOS failure that cannot be stored is lost '
        . 'entirely, which is the one thing that path exists to prevent';
}

// ---------------------------------------------------------------------
// 4. A rejected SELECT must be recorded too.
// ---------------------------------------------------------------------
$db->rejectReads = true;
$before = fogLogContents();
$reader = new TaskLog(0);
$reader->set('id', 8080)->load('id');
if (fogLogContents() === $before) {
    $failures[] = 'a rejected SELECT in load() left no record -- an object '
        . 'that could not be read is reported to its caller as a row with no '
        . 'data in it';
}

// The manager's reads. exists() gives the most expensive wrong answer in the
// class: callers use it to decide whether to CREATE, so an unreadable
// database becomes a duplicate row rather than an error.
$manager = new TaskLogManager();
$before = fogLogContents();
// idField is 'name' by default and taskLog has no such column; hostName is a
// real one, so the probe fails on the DATABASE rather than on the model.
$manager->exists('anything', 0, 'hostName');
if (fogLogContents() === $before) {
    $failures[] = 'a rejected existence check left no record -- it answers '
        . '"does not exist" for a read that never ran, and callers create on '
        . 'the strength of that';
}

// ---------------------------------------------------------------------
// 5. ...and load()'s ORDINARY control flow must stay quiet.
//
//    "Operation field not set" fires on every object built without an id,
//    which is constant traffic. If that reached the fault log, the line
//    assertion 4 pins would be buried under thousands that mean nothing.
//    This is why the fault is recorded at the error check and not in the
//    catch that handles both.
// ---------------------------------------------------------------------
$before = fogLogContents();
(new TaskLog(0))->load('id');
if (fogLogContents() !== $before) {
    $failures[] = 'loading an object with no id wrote a fault line -- that is '
        . "load()'s normal control flow, not a database failure, and at that "
        . 'volume it would bury the failures that matter';
}
$db->rejectReads = false;

// ---------------------------------------------------------------------
// 6. FOGManagerController::update() answered `(bool) $DB->query(...)`,
//    which is an OBJECT cast to bool -- true however the server answered.
// ---------------------------------------------------------------------
$before = fogLogContents();
$mass = $manager->update(array('id' => 1), 'AND', array('text' => 'x'));
if (false !== $mass) {
    $failures[] = 'FOGManagerController::update() answered '
        . var_export($mass, true) . ' after the server rejected the UPDATE -- '
        . 'it returned the PDODB object cast to bool, so it could never '
        . 'report anything but true';
}
if (fogLogContents() === $before) {
    $failures[] = 'a rejected mass update left no record';
}

// ---------------------------------------------------------------------
// 7. With the directory the installer creates present, the fault line must
//    land THERE rather than falling back. The fallback is a safety net for
//    a web tree updated ahead of its installer, not the normal destination.
// ---------------------------------------------------------------------
if (!glob($faultDir . DIRECTORY_SEPARATOR . '*.log')) {
    $failures[] = 'nothing was written into faults/ even though the directory '
        . 'exists and is writable, so every fault is taking the error_log() '
        . 'fallback';
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . "):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  rejected reads and writes are reported and recorded with no user "
    . "signed in\n";
exit(0);
