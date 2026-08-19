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
 * to fire an imaging event for a task that is not imaging, and it does not
 * touch the task's state -- there is no Failed state and inventing one is a
 * separate decision.
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
if (false !== strpos($src, "set('stateID'")) {
    $fails[] = 'the endpoint changes the task state; taskStates has no Failed'
        . ' and choosing one is a separate decision (#1206)';
}
if (false === strpos($src, 'error_log(')) {
    $fails[] = 'the endpoint leaves no server-side trace of a failure';
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

echo "ok: the imaging-failure report is bounded, single-line and uniform\n";
exit(0);
