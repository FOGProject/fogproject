<?php
/**
 * The Session Timeout on the Multicast page is minutes, and is stored as
 * seconds.
 *
 * THE FAILURE. Image Management > Multicast labels the field "Session
 * Timeout (minutes)". ImageManagement::sessionCreate() stored the number
 * as typed in msMaxwait. Every reader of msMaxwait treats it as seconds:
 * MulticastTask passes it to udp-sender --max-wait, and
 * MulticastSession::isJoinable() closes the PXE join at senderstart +
 * maxwait. The other two creators (Group, Host) already store seconds. So
 * a 10-minute session waited 10 seconds. The PXE join then answered "That
 * session has already started and can no longer be joined." with no
 * client connected. Forum topic 18323.
 *
 * WHY A SOURCE CHECK. sessionCreate() reads the form with
 * filter_input(INPUT_POST). That reads the SAPI's own request body, not
 * $_POST, so a CLI test cannot give it a value. This reads the statement
 * that takes the value from the form and checks that it converts minutes
 * to seconds there.
 *
 * Usage: php tests/multicast-session-timeout-is-minutes.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$src = (string)file_get_contents(
    $root . '/packages/web/src/Pages/ImageManagement.php'
);

/*
 * The form still labels the field in minutes. If the label ever changes,
 * the conversion below is wrong and this test must be redone.
 */
check(
    'the Session Timeout field is labeled in minutes',
    (bool)preg_match(
        "/_\('Session Timeout'\)\s*\.\s*'<br\/>\('\s*\.\s*_\('minutes'\)/",
        $src
    ),
    $failures,
    $checks
);

/*
 * The statement that reads the posted value, from the assignment to its
 * semicolon. sessionCreateModal() also reads 'sessiontimeout', but only to
 * refill the form in minutes, so it is excluded by requiring the trim()
 * shape sessionCreate() uses.
 */
$read = '';
if (preg_match(
    "/\\\$sessiontimeout = [^;]*?filter_input\(INPUT_POST, 'sessiontimeout'\)[^;]*;/s",
    $src,
    $m,
    0,
    (int)strpos($src, 'function sessionCreate()')
)) {
    $read = $m[0];
}
check(
    'sessionCreate() reads the posted Session Timeout',
    '' !== $read,
    $failures,
    $checks
);
check(
    'sessionCreate() converts the minutes it reads to seconds',
    (bool)preg_match('/(\b60\s*\*|\*\s*60\b)/', $read),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
