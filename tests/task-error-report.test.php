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
$bind = new \ReflectionProperty('FOG\Base\FOGBase', 'HookManager');
$bind->setAccessible(true);
$bind->setValue(null, $hook);

$fails = [];

$sanitize = new \ReflectionMethod('FOG\TaskHandling\TaskError', '_sanitize');
$sanitize->setAccessible(true);
$clean = function ($raw) use ($sanitize) {
    return $sanitize->invoke(null, $raw);
};
$refl = new \ReflectionClass('FOG\TaskHandling\TaskError');
$maxReason = $refl->getConstant('MAX_REASON');
$maxText = $refl->getConstant('MAX_TEXT');
$max = $maxText;

// ------------------------------------------------------------- one line

// Line breaks are the ONE control character the stored row keeps -- a trace
// is worth having in the shape FOS wrote it. Everything else still goes,
// including the carriage return, so a CRLF report does not store stray \r.
foreach ([
    "Failed\tto mount" => 'a tab',
    "Failed to mount\x00truncated" => 'a NUL',
    "Failed \x1b[31mto\x1b[0m mount" => 'a terminal escape',
    "Failed to mount\r\nsecond line" => 'a carriage return',
] as $raw => $what) {
    $out = $clean($raw);
    if (preg_match('#[\r\t\x00\x1b]#', $out)) {
        $fails[] = "$what survives sanitizing, so it reaches a log file and a"
            . ' notification that are both single-line by contract';
    }
    if ('' === $out) {
        $fails[] = "$what makes the whole report empty, which throws away the"
            . ' only thing it carries';
    }
}

$multi = $clean("Failed to mount\nArgs: -i 2\r\nexit 32");
if (2 !== substr_count($multi, "\n")) {
    $fails[] = 'the stored report does not keep its line breaks, so 8K of'
        . ' trace arrives as one unreadable line -- the reason MAX_TEXT was'
        . ' widened in the first place';
}
// Exact, because stripping the CR without NORMALIZING it first turns every
// CRLF into "space + newline": a trailing space on every line of a report
// from a machine that ends lines the DOS way, which is most of them.
if ("a\nb" !== $clean("a\r\nb")) {
    $fails[] = 'a CRLF report is not normalized to a bare newline, so every'
        . ' line of it is stored with trailing whitespace';
}
// The invalid-UTF-8 fallback has to keep the newline too. [[:cntrl:]] --
// the obvious class, and what this used to be -- includes it, so a report
// from a machine with the wrong locale would silently lose its shape while
// a well-formed one kept it.
if (false === strpos($clean("Failed \xC3\x28 to mount\nsecond line"), "\n")) {
    $fails[] = 'the invalid-UTF-8 fallback strips line breaks, so a report'
        . ' from a machine with the wrong locale is flattened while every'
        . ' other report keeps its shape';
}

// ...and the two destinations that ARE single-line by contract flatten it
// themselves. A newline in a chat message lets a caller forge a second one;
// a newline in fosreports.log breaks the one-entry-per-line property `tail`
// depends on.
$flatten = new \ReflectionMethod('FOG\TaskHandling\TaskError', '_flatten');
$flatten->setAccessible(true);
$flat = $flatten->invoke(null, $multi);
if (false !== strpos($flat, "\n")) {
    $fails[] = '_flatten() leaves line breaks in, so a caller can forge a'
        . ' second line in an administrator\'s notification';
}
if ('' === $flat) {
    $fails[] = '_flatten() empties the report';
}

// ---------------------------------------------------------------- bounds

// Two bounds, not one, and they must not collapse back into each other. The
// stored row and the push notification pull in opposite directions: a phone
// message wants to stay readable, a diagnostic wants the whole trace. If
// MAX_TEXT ever stops being the larger of the two, the split has been undone
// and widening storage has quietly started widening what gets pushed.
foreach (['MAX_REASON' => $maxReason, 'MAX_TEXT' => $maxText] as $name => $val) {
    if (!is_int($val) || $val < 1) {
        $fails[] = "$name is not a positive integer, so the report is unbounded";
    }
}
if ($maxText <= $maxReason) {
    $fails[] = 'MAX_TEXT is not larger than MAX_REASON, so splitting them'
        . ' bought nothing -- the stored report is still notification-sized';
}
// The column is TEXT: 65535 BYTES. Bounding above that would mean an
// oversized report fails its INSERT under STRICT_TRANS_TABLES and is lost
// entirely, rather than being stored short.
if ($maxText > 65535) {
    $fails[] = 'MAX_TEXT exceeds what taskLog.logText can hold, so a large'
        . ' report fails the INSERT instead of being truncated';
}

// Bytes, not characters -- that is the budget the column actually spends.
$long = str_repeat('A', $maxText * 2);
if (strlen($clean($long)) > $maxText) {
    $fails[] = 'a long report is not truncated, so an unauthenticated caller'
        . ' can write whatever it likes into the task log';
}

// A multibyte string must not be cut into an invalid sequence, must not be
// thrown away, and must not be cut by CHARACTERS: 8192 utf8mb3 characters is
// 24576 bytes, three times the budget, which the column would reject.
$mb = str_repeat('é', $maxText);
$cut = $clean($mb);
if ('' === $cut) {
    $fails[] = 'a multibyte report is discarded entirely';
}
if (strlen($cut) > $maxText) {
    $fails[] = 'a multibyte report is bounded by characters rather than bytes,'
        . ' so it can exceed the column and fail the INSERT';
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

$src = file_get_contents($web . '/src/TaskHandling/TaskError.php');

// The split only exists if the SHORT bound is applied at the notification and
// nowhere earlier. Cut at the top instead and both halves shrink together,
// which is the state this change was undoing.
if (!preg_match(
    '#\'Reason\' => mb_substr\(\s*self::_flatten\(\$text\),\s*0,\s*self::MAX_REASON\s*\)#s',
    $src
)) {
    $fails[] = 'the notification payload is not flattened and cut to'
        . ' MAX_REASON at the notify() call, so widening what is stored also'
        . ' widens -- or breaks the line discipline of -- what is pushed to'
        . ' an administrator\'s phone';
}
// The log file is the other single-line contract. Its entries are one
// timestamped line each, written by _record().
if (!preg_match('#self::_record\(\s*sprintf\((?:[^;]*?)self::_flatten\(\$text\)#s', $src)) {
    $fails[] = 'the fosreports.log line is not flattened, so one report can'
        . ' span several lines and forge entries around itself';
}
// _logRow gets the WHOLE text. If MAX_REASON reaches it, the stored row is
// notification-sized again and the extra capacity is unreachable.
// `self::` and the semicolon on purpose: without them this also matches the
// method's own SIGNATURE, `_logRow($Task, $type, $text)`, so narrowing the
// argument at the call site passed clean.
if (false === strpos($src, 'self::_logRow($Task, $type, $text);')) {
    $fails[] = 'the stored row is no longer written from the full report text';
}
// text and script arrive bounded separately, so the join has to be re-bounded
// or the column is handed twice what it was promised.
if (!preg_match(
    '#sprintf\(\'%s \(%s\)\', \$text, \$script\)#s',
    $src
) || !preg_match(
    '#\$text = self::_limit\(\s*sprintf#s',
    $src
)) {
    $fails[] = 'the composed text+script is not re-bounded, so a report with a'
        . ' script name can be twice MAX_TEXT';
}

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
$failed = \FOG\Items\TaskState::getFailedState();
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
$page = file_get_contents($web . '/src/Pages/TaskManagement.php');
if (false === strpos($page, 'self::getFailedState()')) {
    $fails[] = "Task Management's Recent pane does not know about the Failed"
        . ' state, so a failed task appears in no pane at all';
}
if (!preg_match('#\$states = \[\$complete, \$canceled, \$failed\]#', $page)) {
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
if (!preg_match('#new TaskState\(.*?isValid\(\)#s', $src)) {
    $fails[] = 'the endpoint writes the Failed state without checking the row'
        . ' exists, so a server updated ahead of its schema gets tasks'
        . ' pointing at a state that is not there';
}
if (false === strpos($src, 'error_log(')) {
    $fails[] = 'the endpoint leaves no server-side trace of a failure';
}

// ------------------------------------------------------- type, row, file

$types = (new \ReflectionClass('FOG\TaskHandling\TaskError'))->getConstant('TYPES');
if (!is_array($types)
    || ($types['warning'] ?? null) !== \FOG\Items\TaskLog::TYPE_WARNING
    || ($types['error'] ?? null) !== \FOG\Items\TaskLog::TYPE_ERROR
) {
    $fails[] = 'the endpoint does not map both report types onto TaskLog types';
}

$readType = new \ReflectionMethod('FOG\TaskHandling\TaskError', '_reportedType');
$readType->setAccessible(true);
// filter_input has nothing to read under CLI, so this exercises the fallback:
// no type at all must mean error, never warning. A FOS too old to send one is
// only ever reporting a failure.
if ($readType->invoke(null) !== \FOG\Items\TaskLog::TYPE_ERROR) {
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
$fields = (new \ReflectionClass('FOG\Items\TaskLog'))
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
$subdir = (new \ReflectionClass('FOG\TaskHandling\TaskError'))->getConstant('LOG_SUBDIR');
if (!in_array($subdir, \FOG\Util\FOGLogPaths::FOG_SUBDIRS, true)) {
    $fails[] = "the report log lives in '$subdir', which FOGLogPaths does not"
        . ' list, so the Log Viewer cannot offer it';
}
$reachable = false;
foreach (\FOG\Util\FOGLogPaths::readable() as $dir) {
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
    $fails[] = 'the report log directory is not relabeled, so every report is'
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
