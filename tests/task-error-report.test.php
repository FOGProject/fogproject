<?php
/**
 * The FOS report endpoint must not become an injection channel.
 *
 * Ported from working-1.6 (GH-1206). FOS's handleError() and handleWarning()
 * post to service/taskerror.php, and FOS is not branched -- a FOS carrying
 * FOGProject/fos#152 reports to 1.5 servers exactly as it does to 1.6 ones.
 * So this branch accepts free text from an unauthenticated machine and puts
 * it in an administrator's Slack or pushbullet message.
 *
 * The text is therefore bounded and flattened to one line. An embedded
 * newline is the interesting one: in a chat message it lets a caller forge
 * what looks like a second, separate notification, which is a far better lie
 * than anything they could do with markup.
 *
 * Also pinned: the endpoint answers identically whatever happened (so it
 * cannot be used to ask whether a MAC has an active imaging task), a warning
 * is held back from the imaging event, the taskLog row is written before
 * either gate, and the task's state is never written -- there is no Failed
 * state and inventing one is a separate decision.
 *
 * No database and no booted FOG. FOGBase and TaskLog are stubbed so the class
 * can be loaded and its sanitizer called for real; everything else is
 * source-level, because the constructor needs a host lookup and a task.
 *
 * Usage: php tests/task-error-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$fails = array();

// Stubs, so the file can be included without the autoloader, a database or a
// booted FOG. Only what the class needs at load time.
if (!class_exists('FOGBase')) {
    abstract class FOGBase
    {
    }
}
if (!class_exists('TaskLog')) {
    class TaskLog
    {
        const TYPE_STATE = 'state';
        const TYPE_ERROR = 'error';
        const TYPE_WARNING = 'warning';
    }
}
require_once $web . '/lib/reg-task/taskerror.class.php';

$sanitize = new ReflectionMethod('TaskError', '_sanitize');
$sanitize->setAccessible(true);
$clean = function ($raw) use ($sanitize) {
    return $sanitize->invoke(null, $raw);
};
$max = constant('TaskError::MAX_REASON');

// ------------------------------------------------------------- one line

foreach (array(
    "Failed to mount\nHost imaging completed successfully" => 'a newline',
    "Failed to mount\r\nsecond line" => 'a CRLF',
    "Failed\tto mount" => 'a tab',
    "Failed to mount\x00truncated" => 'a NUL',
    "Failed \x1b[31mto\x1b[0m mount" => 'a terminal escape',
) as $raw => $what) {
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
if (mb_strlen($clean(str_repeat('A', $max * 3))) > $max) {
    $fails[] = 'a long report is not truncated, so an unauthenticated caller'
        . ' can use a notification channel as a paste bin';
}
$cut = $clean(str_repeat('é', $max * 2));
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
if ('' === $clean("Failed \xC3\x28 to mount")) {
    $fails[] = 'a report containing invalid UTF-8 is discarded, so a machine'
        . ' with the wrong locale reports nothing at all';
}
if ('' !== $clean('   ') || '' !== $clean('')) {
    $fails[] = 'an empty report is not treated as empty';
}

// ------------------------------------------------------------ the types

$types = constant('TaskError::TYPES');
if (!is_array($types)
    || !isset($types['warning'])
    || $types['warning'] !== TaskLog::TYPE_WARNING
    || !isset($types['error'])
    || $types['error'] !== TaskLog::TYPE_ERROR
) {
    $fails[] = 'the endpoint does not map both report types onto TaskLog types';
}
$readType = new ReflectionMethod('TaskError', '_reportedType');
$readType->setAccessible(true);
// filter_input has nothing to read under CLI, so this exercises the fallback:
// no type at all must mean error, never warning. On 1.5 that is the normal
// case rather than the exotic one, because FOS is shared between the lines.
if ($readType->invoke(null) !== TaskLog::TYPE_ERROR) {
    $fails[] = 'a report with no type is not treated as an error, so an older'
        . ' FOS reporting a real failure fires nothing';
}

// --------------------------------------------------------------- ordering

$src = file_get_contents($web . '/lib/reg-task/taskerror.class.php');

$warnGuard = strpos($src, 'TaskLog::TYPE_ERROR !== $type');
$notify = strpos($src, "'HOST_IMAGE_FAIL'");
$row = strpos($src, '_logRow(');
$imaging = strpos($src, '$Task->isImagingTask()');

if (false === $notify) {
    $fails[] = 'the endpoint no longer notifies HOST_IMAGE_FAIL, which is the'
        . ' whole reason it exists';
}
if (false === $imaging) {
    $fails[] = 'the endpoint no longer checks the task is an imaging one, so a'
        . ' failed wipe fires HOST_IMAGE_FAIL';
}
if (false === $warnGuard || false === $notify || $warnGuard > $notify) {
    $fails[] = 'a warning is not held back from HOST_IMAGE_FAIL, so a machine'
        . ' that carried on announces a failed deploy';
}
if (false === $row || false === $imaging || $row > $imaging) {
    $fails[] = 'the taskLog row is written only for imaging tasks, so a failed'
        . ' wipe or inventory leaves no trace against its task';
}
if (false === $warnGuard || $row > $warnGuard) {
    $fails[] = 'a warning writes no taskLog row, so the only reports FOG keeps'
        . ' are the ones that were already fatal';
}
if (substr_count($src, "echo '##';") !== 1) {
    $fails[] = 'the endpoint does not answer identically on every path, so the'
        . ' response says whether the MAC had an active imaging task';
}

// ------------------------------------------------------------- the state

// Schema 281 gave a failed task a state of its own. Read out of the source
// rather than called: this file stubs FOGBase so the class can be included
// without a database, and TaskState needs a hook manager.
$stateSrc = file_get_contents($web . '/lib/fog/taskstate.class.php');
if (!preg_match('#function getFailedState\(\).*?\$failedState = (\d+);#s', $stateSrc, $m)) {
    $fails[] = 'TaskState has no getFailedState(), so the endpoint has no'
        . ' state to move a dead task to';
    $failed = 0;
} else {
    $failed = (int) $m[1];
}
$schema = file_get_contents($web . '/commons/schema.php');
if (!$failed
    || !preg_match('#`taskStates`.*?VALUES.*?\(' . $failed . ",'Failed'#s", $schema)
) {
    $fails[] = "no schema step seeds taskStates row $failed as Failed, so the"
        . ' state the endpoint writes does not exist';
}

// Only the state is written. The endpoint's whole discipline is that it
// reports rather than decides -- writing anything else about the task would
// make an unauthenticated caller able to edit it.
preg_match_all('#\$Task->set\(\s*\'([^\']+)\'#', $src, $written);
foreach (array_unique($written[1]) as $field) {
    if ($field !== 'stateID') {
        $fails[] = "the endpoint writes \$Task->$field; an unauthenticated"
            . ' report may set the task state and nothing else';
    }
}

$mark = strpos($src, 'self::_markFailed(');
if (false === $mark) {
    $fails[] = 'the endpoint no longer fails the task, so a host that died'
        . ' mid-image still shows as running and cannot be re-tasked';
}
// A warning must not fail the task, and the state must be written for
// non-imaging tasks too -- a Memtest the host died on is just as finished as
// a deploy; only the notification is imaging-specific.
if (false === $mark || false === $warnGuard || $mark < $warnGuard) {
    $fails[] = 'a warning marks the task Failed, so a machine that carried on'
        . ' has its task killed under it';
}
if (false === $mark || false === $imaging || $mark > $imaging) {
    $fails[] = 'only imaging tasks are marked Failed, so a failed Memtest or'
        . ' inventory task still shows as running forever';
}
// Guarded on the row existing: a web tree can be updated ahead of its
// database, and a stateID with no taskStates row behind it is worse than
// leaving the task alone.
if (!preg_match('#getClass\(\s*\'TaskState\'.*?isValid\(\)#s', $src)) {
    $fails[] = 'the endpoint writes the Failed state without checking the row'
        . ' exists, so a server updated ahead of its schema gets tasks'
        . ' pointing at a state that is not there';
}
if (false === strpos($src, 'error_log(')) {
    $fails[] = 'the endpoint leaves no fallback trace when the log file is'
        . ' unwritable, which is every server not yet re-installed';
}

// ----------------------------------------------------------- the plumbing

$model = file_get_contents($web . '/lib/fog/tasklog.class.php');
foreach (array('type' => 'logType', 'text' => 'logText') as $key => $column) {
    if (false === strpos($model, "'$key' => '$column'")) {
        $fails[] = "TaskLog does not map $key onto $column, so the endpoint's"
            . ' report is dropped on save';
    }
}

// The log directory has to appear in all three lists or the Log Viewer fails
// in two different ways -- no entry, or an entry answering "Invalid Folder".
$subdir = constant('TaskError::LOG_SUBDIR');
$lists = array(
    'lib/fog/storagenode.class.php' => "'/var/log/fog/$subdir'",
    'status/getfiles.php' => "'/var/log/fog/$subdir'",
    'status/logtoview.php' => "'/var/log/fog/$subdir/'",
);
foreach ($lists as $file => $needle) {
    if (false === strpos(file_get_contents($web . '/' . $file), $needle)) {
        $fails[] = "$file does not list $needle, so the Log Viewer cannot"
            . ' offer or read the FOS report log';
    }
}
if (false === strpos(
    file_get_contents($web . '/status/logtoview.php'),
    "'/opt/fog/log/$subdir/'"
)) {
    $fails[] = 'logtoview.php lists only the symlinked spelling of the report'
        . ' log directory; a file named by its real path is refused';
}

$inst = file_get_contents($root . '/lib/common/functions.sh');
if (false === strpos($inst, 'mkdir -p $servicelogs/' . $subdir)) {
    $fails[] = 'the installer does not create the report log directory, so the'
        . ' web tier has nowhere to write';
}
if (false === strpos(
    $inst,
    'setSELinuxContext "$servicelogs/' . $subdir . '" httpd_sys_rw_content_t'
)) {
    $fails[] = 'the report log directory is not relabelled, so every report is'
        . ' dropped by SELinux with nothing but an AVC to say so';
}

// The entry point has to be a real file: service/*.php are served directly.
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
