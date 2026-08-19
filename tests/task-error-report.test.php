<?php
/**
 * The imaging-failure endpoint must not become an injection channel.
 *
 * #1206: FOS's handleError() reported nothing to the server, so a real
 * imaging failure -- bad image, mount failure, partition error -- was
 * invisible to FOG and HOST_IMAGE_FAIL could not fire for it. service/
 * taskerror.php closes that, and in doing so accepts free text from an
 * unauthenticated machine and puts it in an administrator's Slack, ntfy or
 * pushbullet message.
 *
 * So the text is bounded and flattened to one line. An embedded newline is
 * the interesting one: in a chat message it lets a caller forge what looks
 * like a second, separate notification, which is a far better lie than
 * anything they could do with markup.
 *
 * Also pinned: the endpoint answers identically whatever happened (so it
 * cannot be used to ask whether a MAC has an active imaging task), it refuses
 * to fire an imaging event for a task that is not imaging, and an error --
 * but never a warning -- moves the task to Failed (schema 339).
 *
 * And the two destinations a report gets that are not the event: a typed
 * `taskLog` row, which is what correlates the report with the task, and
 * FOG's own log file. The type matters more than it looks -- a warning is a
 * report the machine carried on after, so treating one as an error would
 * announce a failed deploy for a task that went on to succeed.
 *
 * No database, no web server. The sanitizer is pure and is called directly;
 * the rest is source-level, because the constructor needs a booted FOG, a
 * host lookup and a task.
 *
 * Usage: php tests/task-error-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require $web . '/commons/init.php';
new Initiator();

// Every TaskState::get*State() fires a hook so a plugin can move the number,
// and a hook manager only exists inside a booted application. Binding a stub
// directly is deliberate: FOGBase::_init() would drag in the database.
$hook = new class {
    public function processEvent($event, $data = [])
    {
    }
};
$bind = new \ReflectionProperty('FOG\FOGBase', 'HookManager');
$bind->setAccessible(true);
$bind->setValue(null, $hook);

$fails = [];

$sanitize = new \ReflectionMethod('FOG\TaskError', '_sanitize');
$sanitize->setAccessible(true);
$clean = function ($raw) use ($sanitize) {
    return $sanitize->invoke(null, $raw);
};
$max = (new \ReflectionClass('FOG\TaskError'))->getConstant('MAX_REASON');

// ------------------------------------------------------------- one line

foreach ([
    "Failed to mount\nHost imaging completed successfully" => 'a newline',
    "Failed to mount\r\nsecond line" => 'a CRLF',
    "Failed\tto mount" => 'a tab',
    "Failed to mount\x00truncated" => 'a NUL',
    "Failed \x1b[31mto\x1b[0m mount" => 'a terminal escape',
] as $raw => $what) {
    $out = $clean($raw);
    if (preg_match('#[\r\n\t\x00\x1b]#', $out)) {
        $fails[] = "$what survives sanitizing, so a caller can forge a second"
            . ' line in an administrator\'s notification';
    }
    if ('' === $out) {
        $fails[] = "$what makes the whole report empty, which throws away the"
            . ' only thing it carries';
    }
}

// ---------------------------------------------------------------- bounds

if (!is_int($max) || $max < 1) {
    $fails[] = 'MAX_REASON is not a positive integer, so the report is unbounded';
}
$long = str_repeat('A', $max * 3);
if (mb_strlen($clean($long)) > $max) {
    $fails[] = 'a long report is not truncated, so an unauthenticated caller'
        . ' can use a notification channel as a paste bin';
}

// A multibyte string must not be cut into an invalid sequence, and must not
// be thrown away either.
$mb = str_repeat('é', $max * 2);
$cut = $clean($mb);
if ('' === $cut) {
    $fails[] = 'a multibyte report is discarded entirely';
}
if (mb_strlen($cut) > $max) {
    $fails[] = 'a multibyte report is not truncated';
}
if ($cut !== mb_convert_encoding($cut, 'UTF-8', 'UTF-8')) {
    $fails[] = 'truncation split a multibyte character, so the message is no'
        . ' longer valid UTF-8';
}

// Invalid UTF-8 makes preg_replace return null rather than throw. Falling
// through to an empty string would silently drop every report from a machine
// with a non-UTF-8 locale.
$bad = "Failed \xC3\x28 to mount";
if ('' === $clean($bad)) {
    $fails[] = 'a report containing invalid UTF-8 is discarded, so a machine'
        . ' with the wrong locale reports nothing at all';
}

if ('' !== $clean('   ') || '' !== $clean('')) {
    $fails[] = 'an empty report is not treated as empty';
}

// --------------------------------------------------------------- the class

$src = file_get_contents($web . '/lib/reg-task/taskerror.class.php');

if (false === strpos($src, '$Task->isImagingTask()')) {
    $fails[] = 'the endpoint no longer checks the task is an imaging one, so a'
        . ' failed wipe fires HOST_IMAGE_FAIL';
}
if (false === strpos($src, "notify(\n                'HOST_IMAGE_FAIL'")) {
    $fails[] = 'the endpoint no longer notifies HOST_IMAGE_FAIL, which is the'
        . ' whole reason it exists';
}
// Every added key of #1202's payload, so a listener behaves the same whether
// the failure came from here or from TaskQueue::checkout().
foreach (['HostName', 'Host', 'Task', 'Image', 'ImageName', 'TaskType', 'Reason'] as $key) {
    if (false === strpos($src, "'" . $key . "' =>")) {
        $fails[] = "the endpoint's payload is missing $key, so a listener sees"
            . ' a different shape depending on which failure path fired';
    }
}
if (substr_count($src, "echo '##';") !== 1) {
    $fails[] = 'the endpoint does not answer identically on every path, so the'
        . ' response says whether the MAC had an active imaging task';
}
// ------------------------------------------------------------ the state

// Schema 339 gave a failed task a state of its own. Three things have to hold
// together or the state is worse than not having it.

// 1. The constant and the seeded row must agree, or a failed task points at a
//    taskStates row that does not exist: it renders blank and cannot be
//    filtered for.
$failed = \FOG\TaskState::getFailedState();
if ($failed !== 6) {
    $fails[] = "TaskState::getFailedState() returns $failed; the schema seeds"
        . ' tsID 6, and the two have to be the same number';
}
$schema = file_get_contents($web . '/commons/schema.php');
if (!preg_match('#`taskStates`.*?VALUES.*?\(' . $failed . ",'Failed'#s", $schema)) {
    $fails[] = "no schema step seeds taskStates row $failed as Failed, so the"
        . ' state the endpoint writes does not exist';
}

// 2. Only the state is written. The endpoint's whole discipline is that it
//    reports rather than decides -- writing anything else about the task
//    would make an unauthenticated caller able to edit it.
preg_match_all('#\$Task->set\(\s*\'([^\']+)\'#', $src, $written);
foreach (array_unique($written[1]) as $field) {
    if ($field !== 'stateID') {
        $fails[] = "the endpoint writes \$Task->$field; an unauthenticated"
            . ' report may set the task state and nothing else';
    }
}

// 5. A failed task has to be visible somewhere. It is not an active state,
//    so the active pane excludes it by construction, and Task Management's
//    Recent pane is the only view of finished tasks there is -- if that pane
//    does not list Failed, the state exists and nobody can see it.
$page = file_get_contents($web . '/lib/pages/taskmanagement.page.php');
if (false === strpos($page, 'self::getFailedState()')) {
    $fails[] = "Task Management's Recent pane does not know about the Failed"
        . ' state, so a failed task appears in no pane at all';
}
if (!preg_match('#\$states = \[\$complete, \$cancelled, \$failed\]#', $page)) {
    $fails[] = 'the Recent pane\'s default state set leaves out Failed, so a'
        . ' failed task is only found by picking a filter for it';
}
if (false === strpos($page, 'value="failed"')) {
    $fails[] = 'the Recent pane offers no Failed filter, so failed tasks'
        . ' cannot be listed on their own';
}

// 4. Guarded on the row existing. The web tree can be updated before the
//    schema step runs, and writing a stateID with no taskStates row behind it
//    is worse than leaving the task alone.
if (!preg_match('#getClass\(\s*\'TaskState\'.*?isValid\(\)#s', $src)) {
    $fails[] = 'the endpoint writes the Failed state without checking the row'
        . ' exists, so a server updated ahead of its schema gets tasks'
        . ' pointing at a state that is not there';
}
if (false === strpos($src, 'error_log(')) {
    $fails[] = 'the endpoint leaves no server-side trace of a failure';
}

// ------------------------------------------------------- type, row, file

$types = (new \ReflectionClass('FOG\TaskError'))->getConstant('TYPES');
if (!is_array($types)
    || ($types['warning'] ?? null) !== \FOG\TaskLog::TYPE_WARNING
    || ($types['error'] ?? null) !== \FOG\TaskLog::TYPE_ERROR
) {
    $fails[] = 'the endpoint does not map both report types onto TaskLog types';
}

$readType = new \ReflectionMethod('FOG\TaskError', '_reportedType');
$readType->setAccessible(true);
// filter_input has nothing to read under CLI, so this exercises the fallback:
// no type at all must mean error, never warning. A FOS too old to send one is
// only ever reporting a failure.
if ($readType->invoke(null) !== \FOG\TaskLog::TYPE_ERROR) {
    $fails[] = 'a report with no type is not treated as an error, so an older'
        . ' FOS reporting a real failure fires nothing';
}

// A warning must return before the notify, not fall into it.
$warnGuard = strpos($src, 'TaskLog::TYPE_ERROR !== $type');
$notify = strpos($src, "'HOST_IMAGE_FAIL'");
if (false === $warnGuard || false === $notify || $warnGuard > $notify) {
    $fails[] = 'a warning is not held back from HOST_IMAGE_FAIL, so a machine'
        . ' that carried on announces a failed deploy';
}

// The row is written before either gate -- the imaging one included -- so a
// failed wipe is still recorded against its task even though no event fires.
$row = strpos($src, '_logRow(');
$imaging = strpos($src, '$Task->isImagingTask()');
if (false === $row || false === $imaging || $row > $imaging) {
    $fails[] = 'the taskLog row is written only for imaging tasks, so a failed'
        . ' wipe or inventory leaves no trace against its task';
}
if (false === $warnGuard || $row > $warnGuard) {
    $fails[] = 'a warning writes no taskLog row, so the only reports FOG keeps'
        . ' are the ones that were already fatal';
}

// 3. A warning must not fail the task, and the state must be written for
//    non-imaging tasks too -- a Memtest the host died on is just as finished
//    as a deploy; only the notification is imaging-specific.
$mark = strpos($src, 'self::_markFailed(');
if (false === $mark) {
    // Checked separately so removing the call says so, rather than reporting
    // the two ordering failures it also produces.
    $fails[] = 'the endpoint no longer fails the task, so a host that died'
        . ' mid-image still shows as running and cannot be re-tasked';
}
if (false === $mark || false === $warnGuard || $mark < $warnGuard) {
    $fails[] = 'a warning marks the task Failed, so a machine that carried on'
        . ' has its task killed under it';
}
if (false === $mark || false === $imaging || $mark > $imaging) {
    $fails[] = 'only imaging tasks are marked Failed, so a failed Memtest or'
        . ' inventory task still shows as running forever';
}

// The model has to be able to hold what the endpoint writes.
$fields = (new \ReflectionClass('FOG\TaskLog'))
    ->getDefaultProperties()['databaseFields'] ?? [];
foreach (['type' => 'logType', 'text' => 'logText'] as $key => $column) {
    if (($fields[$key] ?? null) !== $column) {
        $fails[] = "TaskLog does not map $key onto $column, so the endpoint's"
            . ' report is dropped on save';
    }
}

// The log file goes in its own subdirectory, and that subdirectory has to be
// one FOGLogPaths knows about or the Log Viewer can neither list nor read it
// -- the two fail differently, which is why the class exists.
$subdir = (new \ReflectionClass('FOG\TaskError'))->getConstant('LOG_SUBDIR');
if (!in_array($subdir, \FOG\FOGLogPaths::FOG_SUBDIRS, true)) {
    $fails[] = "the report log lives in '$subdir', which FOGLogPaths does not"
        . ' list, so the Log Viewer cannot offer it';
}
$reachable = false;
foreach (\FOG\FOGLogPaths::readable() as $dir) {
    if (substr($dir, -strlen($subdir . DS)) === $subdir . DS) {
        $reachable = true;
    }
}
if (!$reachable) {
    $fails[] = "the report log's directory is enumerable but not readable, so"
        . ' the Log Viewer lists the file and then says Invalid Folder';
}

// The installer is what creates it, with the SELinux label: /opt/fog inherits
// usr_t and httpd_t may read it but not write it (GH-964), so a directory
// created without the relabel swallows every report on an enforcing host.
$inst = file_get_contents($root . '/lib/common/functions.sh');
if (false === strpos($inst, 'mkdir -p $servicelogs/' . $subdir)) {
    $fails[] = 'the installer does not create the report log directory, so the'
        . ' web tier has nowhere to write';
}
if (false === strpos($inst, 'setSELinuxContext "$servicelogs/' . $subdir . '" httpd_sys_rw_content_t')) {
    $fails[] = 'the report log directory is not relabelled, so every report is'
        . ' dropped by SELinux with nothing but an AVC to say so';
}

// The entry point has to be a real file: service/*.php are served directly,
// not routed, so nothing rewrites this path.
if (!is_file($web . '/service/taskerror.php')) {
    $fails[] = 'service/taskerror.php does not exist, so nothing can report';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: the FOS report is bounded, single-line, typed and recorded\n";
exit(0);
