<?php
/**
 * A host that has already joined a multicast session cannot rejoin it.
 *
 * THE FAILURE. udpcast carries no metadata: the server chains one
 * udp-sender per image file on a shared portbase and the Nth receiver a
 * client opens gets the Nth stream. A host that reboots part way through a
 * session -- a power cut, a bad NIC, someone hitting reset -- is handed the
 * same task by the boot menu, opens its FIRST receiver against whichever
 * sender happens to be running now, and is one or more streams out of step
 * for the rest of the session. Before #1743 that silently restored one
 * partition's filesystem onto another; since #1743 FOS names the stream and
 * refuses it, but only after it has already laid down the partition table.
 * Either way the host is destroyed. Issue #1744.
 *
 * WHAT THIS PINS is the refusal in TaskQueue::checkIn(), which is where FOS
 * blocks before fog.download touches the disk -- fog.checkin POSTs
 * service/mc_checkin.php and waits for '##@GO', so a throw here costs the
 * host nothing at all.
 *
 * Both halves of the condition are load bearing, and two of the five cases
 * below exist only to hold them:
 *
 *   prior state    clients   meaning                        answer
 *   queued         0         first host, session idle       join
 *   queued         2         another host joining a live    join
 *                            session -- the normal way
 *                            multicast works
 *   checked in     0         this host retrying after a     join
 *                            transient failure later in
 *                            checkIn(); nobody got through
 *   checked in     1         REJOIN                         refuse
 *   in progress    2         REJOIN                         refuse
 *
 * State alone would lock a host out of its own retry, because taskCheckIn()
 * has already moved the state by the time anything else in checkIn() can
 * fail. A client count alone would refuse every host after the first, which
 * is multicast itself.
 *
 * These checks drive the REAL checkIn() over stand-in collaborators. The
 * database surface is stubbed; the branch, the condition and the echoed
 * answer are the shipped code.
 *
 * Usage: php tests/multicast-rejoin-is-refused.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

use FOG\TaskHandling\TaskQueue;

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

/**
 * Output escaper.
 *
 * checkIn()'s catch arm echoes through this, so it has to exist for the
 * refusal to be observable at all. Same call as the real one.
 */
class Initiator
{
    /**
     * Escapes a value for HTML output.
     *
     * @param mixed $value The value to escape.
     *
     * @return string
     */
    public static function e($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

// checkIn()'s catch arm names it globally, and this file is namespaced.
class_alias('FOG\\Initiator', 'Initiator');

/**
 * Reads a task state id out of TaskState.php.
 *
 * The states are literals in the real class rather than constants, and that
 * class cannot be loaded here (FOGController wants a database). Parsing the
 * source keeps the numbers in this harness sourced from the one place that
 * emits them instead of from a belief about what they are.
 *
 * @param string $var The local variable the getter assigns.
 *
 * @return int
 */
function stateFromSource($var)
{
    $src = (string)file_get_contents(
        dirname(__DIR__) . '/packages/web/src/Items/TaskState.php'
    );
    if (preg_match('/\$' . $var . ' = ([0-9]+);/', $src, $m)) {
        return (int)$m[1];
    }
    fwrite(STDERR, "cannot read \$$var from TaskState.php\n");
    exit(1);
}

/** @var int $QUEUED The literal queued state. */
$QUEUED = stateFromSource('queuedState');
/** @var int $CHECKEDIN The checked-in state. */
$CHECKEDIN = stateFromSource('checkedInState');
/** @var int $PROGRESS The in-progress state. */
$PROGRESS = stateFromSource('progressState');

/**
 * Stand-in for the real base class.
 *
 * checkIn() reaches the parent for the three task states, for minId(), and
 * for the current host. Everything else it needs is on collaborators.
 */
abstract class RejoinBase
{
    /** @var object|null The host being checked in. */
    public static $Host;

    /**
     * The checked-in state id.
     *
     * @return int
     */
    public static function getCheckedInState()
    {
        return stateFromSource('checkedInState');
    }

    /**
     * The in-progress state id.
     *
     * @return int
     */
    public static function getProgressState()
    {
        return stateFromSource('progressState');
    }

    /**
     * The states a task can sit in before it starts.
     *
     * Present so that a mutation swapping the refusal's list for this one
     * runs rather than fatals -- it must fail on behavior, not on a missing
     * method.
     *
     * @return array
     */
    public static function getQueuedStates()
    {
        return range(0, stateFromSource('checkedInState'));
    }

    /**
     * Smallest id in a list.
     *
     * @param array $ids The ids.
     *
     * @return int
     */
    public static function minId($ids)
    {
        return (int)min((array)$ids);
    }
}

/*
 * Declared under its own name and aliased into place. Four other tests
 * declare a flat FOG\FOGBase stub of their own, and PHPStan analyzes
 * tests/ as one program -- naming the shared class from this file would
 * measure the reference against whichever declaration it read first. The
 * alias is what tests/lib/stub-buckets.php then re-exports as
 * FOG\Base\FOGBase for the real TaskQueue to extend.
 */
class_alias('FOG\\RejoinBase', 'FOG\\FOGBase');

/**
 * Association lookup.
 */
class Route
{
    /**
     * Ids for a relation.
     *
     * checkIn() asks this for the session behind the task; one session, and
     * which id it is does not matter to anything under test.
     *
     * @param string $class  The class asked for.
     * @param array  $where  The filter.
     * @param string $field  The field wanted.
     *
     * @return array
     */
    public static function getIds($class, $where = [], $field = '')
    {
        return [1];
    }
}

/**
 * A task row.
 */
class Task
{
    /** @var array Field values. */
    public $data = [];
    /** @var bool Whether taskCheckIn() ran. */
    public $checkedIn = false;

    /**
     * Reads a field.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : '';
    }

    /**
     * Writes a field.
     *
     * @param string $key   The field.
     * @param mixed  $value The value.
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Moves the task to the checked-in state.
     *
     * Mirrors the real Task::taskCheckIn(), which sets that state on every
     * call where the task is not already in it
     * (packages/web/src/Items/Task.php:384). That move is the reason
     * checkIn() has to read the state BEFORE calling this, so the stub has
     * to make it too or the mutation is invisible.
     *
     * @return void
     */
    public function taskCheckIn()
    {
        $this->checkedIn = true;
        $this->data['stateID'] = self::checkedInState();
    }

    /**
     * The checked-in state id.
     *
     * @return int
     */
    private static function checkedInState()
    {
        return stateFromSource('checkedInState');
    }

    /**
     * Whether this is a capture.
     *
     * @return bool
     */
    public function isCapture()
    {
        return false;
    }

    /**
     * Whether this is a multicast deploy.
     *
     * @return bool
     */
    public function isMulticast()
    {
        return true;
    }

    /**
     * Whether this is a forced task.
     *
     * @return bool
     */
    public function isForced()
    {
        return false;
    }
}

/**
 * A multicast session row.
 */
class MulticastSession
{
    /** @var array Field values. */
    public $data = [];
    /** @var bool Whether save() ran. */
    public $saved = false;
    /** @var mixed What checkIn() looked the session up by. */
    public $lookedUpBy;

    /**
     * Loads the session.
     *
     * The parameter is untyped because tests/lib/bootmenu-harness.php
     * declares a stand-in of the same name that is handed a row array --
     * PHPStan analyzes tests/ as one program and measures both call sites
     * against whichever declaration it reads first.
     *
     * @param mixed $id The session id.
     */
    public function __construct($id = null)
    {
        $this->data = $GLOBALS['fogSessionRow'];
        $this->lookedUpBy = $id;
        // checkIn() builds this itself, so the harness keeps a handle on
        // the instance to read back what the method did to the row.
        $GLOBALS['fogSession'] = $this;
    }

    /**
     * Whether the session exists.
     *
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * Reads a field.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : '';
    }

    /**
     * Writes a field.
     *
     * @param string $key   The field.
     * @param mixed  $value The value.
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Persists the row.
     *
     * @return bool
     */
    public function save()
    {
        $this->saved = true;
        return true;
    }
}

/**
 * The host.
 *
 * Deliberately invalid. checkIn() throws '##@GO' for a host it cannot
 * resolve immediately after the session is joined, which is a clean stop
 * just past the code under test -- everything after it is storage-node and
 * audit work this harness has no business standing in for. So an answer of
 * '##@GO' means "the join was allowed", and it is the same token the client
 * waits for on the wire.
 */
class Host
{
    /**
     * Whether the host exists.
     *
     * @return bool
     */
    public function isValid()
    {
        return false;
    }

    /**
     * Reads a field.
     *
     * @param string $key The field.
     *
     * @return mixed
     */
    public function get($key)
    {
        return '';
    }
}

require_once __DIR__ . '/lib/stub-buckets.php';
require_once dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskingElement.php';
require_once dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskQueue.php';

/**
 * Drives checkIn() without the tasking constructor behind it.
 */
class CheckInProbe extends TaskQueue
{
    /**
     * Sets up just the state checkIn() reads.
     *
     * @param object $Task The task.
     */
    public function __construct($Task)
    {
        $this->Task = $Task;
        $this->imagingTask = true;
    }
}

/** @var string[] $failures Labels of the checks that did not hold. */
$failures = [];
/** @var int $checks How many assertions ran. */
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $label What is being asserted.
 * @param bool   $cond  Whether it held.
 *
 * @return void
 */
function check($label, $cond)
{
    global $failures, $checks;
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/**
 * Runs one check-in and reports what came back.
 *
 * @param int $priorState The task's state when the host posts.
 * @param int $clients    Hosts already in the session.
 *
 * @return array The echoed answer, and the session as checkIn() left it.
 */
function checkInWith($priorState, $clients)
{
    $GLOBALS['fogSessionRow'] = [
        'id' => 1,
        'clients' => $clients,
        'image' => 7,
        'stateID' => 1
    ];
    $Task = new Task();
    $Task->set('id', 41)->set('stateID', $priorState);
    RejoinBase::$Host = new Host();
    $Probe = new CheckInProbe($Task);
    ob_start();
    $Probe->checkIn();
    $answer = (string)ob_get_clean();
    return [$answer, $GLOBALS['fogSession']->data];
}

// --- 1. a host arriving at the session -----------------------------------

list($answer, $session) = checkInWith($QUEUED, 0);
check(
    'the first host is let in',
    '##@GO' === $answer
);
check(
    'and is counted',
    1 === (int)$session['clients']
);

// The normal shape of multicast: host two through host N all arrive at a
// session that already has clients in it. A refusal keyed on the client
// count alone would break every one of them.
list($answer, $session) = checkInWith($QUEUED, 2);
check(
    'a second host joining a live session is let in',
    '##@GO' === $answer
);
check(
    'and is counted',
    3 === (int)$session['clients']
);

// --- 2. a host coming back to a session under way ------------------------

list($answer, $session) = checkInWith($CHECKEDIN, 1);
check(
    'a host that already checked in to a running session is refused',
    false !== stripos($answer, 'cannot rejoin')
);
check(
    'and the refusal names the session, not some generic failure',
    false !== stripos($answer, 'multicast session')
);
check(
    'and the client count is left alone',
    1 === (int)$session['clients']
);

list($answer, $session) = checkInWith($PROGRESS, 2);
check(
    'a host coming back while the session is in progress is refused',
    false !== stripos($answer, 'cannot rejoin')
);
check(
    'and the client count is left alone',
    2 === (int)$session['clients']
);

// --- 3. a retry is not a rejoin -----------------------------------------

// taskCheckIn() moves the state on the FIRST call, so any failure later in
// checkIn() leaves the task looking exactly like a returning host. What
// separates them is that nobody got through: clients is still zero.
list($answer, $session) = checkInWith($CHECKEDIN, 0);
check(
    'a host retrying a session nobody has joined is let in',
    '##@GO' === $answer
);
check(
    'and is counted',
    1 === (int)$session['clients']
);

// --- 4. the refusal is worded for the person reading it ------------------

$src = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskQueue.php'
);
check(
    'the refusal tells the operator what to do about it',
    (bool)preg_match('/Cancel the task and re-create it/', $src)
);
check(
    'and the refusal is translatable',
    (bool)preg_match(
        "/throw new \\\\Exception\\(\\s*_\\('This host has already joined/",
        $src
    )
);

// --- report -------------------------------------------------------------

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - $failure\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
