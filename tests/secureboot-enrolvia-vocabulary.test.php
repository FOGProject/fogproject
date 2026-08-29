<?php
/**
 * The enrollment-provenance vocabulary must be the same in both writers.
 *
 * `hostSbEnrollVia` records HOW a Secure Boot enrollment happened, and two
 * unrelated files write it:
 *
 *   - `service/secureboot.report.php` maps the word FOS sends onto the word
 *     the column stores;
 *   - `lib/pages/hostmanagement.page.php` whitelists what an administrator may
 *     type into the host form.
 *
 * Neither can see the other, and the failure when they drift is silent in the
 * direction that matters. If the endpoint learns to store a word the form does
 * not accept, an administrator who opens that host and presses Update --
 * having changed nothing about the enrollment -- gets an exception on a value
 * the server itself wrote. The reverse, a form word the endpoint never emits,
 * is harmless, so only one direction is a hard failure here.
 *
 * This is not hypothetical: the first cut of these two files disagreed,
 * because 'trusted' was folded into 'db' on one side and not the other.
 *
 * Both lists are read out of the SOURCE rather than by executing either file:
 * the endpoint is a machine entry point that boots all of FOG and then exits,
 * and the page's whitelist sits inside a POST handler behind a session. A
 * regex over the two literals is what is actually maintainable here, so it is
 * anchored tightly enough that a rewrite fails loudly instead of matching
 * nothing -- an empty match is a FAIL, not a pass.
 *
 * DB-free, boots nothing.
 *
 * Usage: php tests/secureboot-enrolvia-vocabulary.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$endpoint = $webroot . '/service/secureboot.report.php';
$page = $webroot . '/lib/pages/hostmanagement.page.php';

/**
 * Print whatever went wrong and stop, or return if nothing did.
 *
 * Staged rather than checked once at the end because the later assertions
 * cannot run at all if the earlier ones failed: there is nothing to compare
 * if a file would not read or a literal would not parse.
 *
 * A plain array appended to inline, rather than a counter incremented inside a
 * closure. A counter mutated through a closure or `global` is invisible to the
 * second PHPStan pass -- which analyzes all of tests/ as one unit -- so every
 * `$failures > 0` after the first one reads as "always false", and the file
 * would report a pass whatever it found.
 *
 * @param array $problems what went wrong so far
 *
 * @return void
 */
function stopOnProblems(array $problems)
{
    if (count($problems) < 1) {
        return;
    }
    foreach ($problems as $msg) {
        fwrite(STDERR, "FAIL: $msg\n");
    }
    fwrite(STDERR, "\n" . count($problems) . " check(s) FAILED\n");
    exit(1);
}

$problems = [];
foreach ([$endpoint, $page] as $file) {
    if (!is_readable($file)) {
        $problems[] = "cannot read $file";
    }
}
stopOnProblems($problems);

// The stored words are the VALUES of the endpoint's map, not its keys: the
// keys are what FOS may send ('mok' and 'staged' both mean "a request was
// staged"), and several of them deliberately collapse onto one column value.
$stored = [];
$src = (string)file_get_contents($endpoint);
if (preg_match('/\$map\s*=\s*\[(.*?)\];/s', $src, $m)) {
    if (preg_match_all("/=>\s*'([^']+)'/", $m[1], $vals)) {
        $stored = array_values(array_unique($vals[1]));
    }
}
if (count($stored) < 1) {
    $problems[] = 'could not read the $map literal out of'
        . ' service/secureboot.report.php -- if it was restructured, update'
        . ' this test rather than deleting it';
}

// The form's whitelist, read off the in_array() the POST handler validates
// against.
$allowed = [];
$src = (string)file_get_contents($page);
if (preg_match('/\$sbEnrollVia,\s*\[(.*?)\],/s', $src, $m)) {
    if (preg_match_all("/'([^']+)'/", $m[1], $vals)) {
        $allowed = $vals[1];
    }
}
if (count($allowed) < 1) {
    $problems[] = 'could not read the sbenrollvia whitelist out of'
        . ' lib/pages/hostmanagement.page.php';
}
stopOnProblems($problems);

// The hard direction: every word the server can write must survive a
// round-trip through the form that displays it.
foreach ($stored as $word) {
    if (!in_array($word, $allowed, true)) {
        $problems[] = "service/secureboot.report.php stores '$word', which the"
            . ' host form refuses. An administrator pressing Update on a host'
            . ' the server itself wrote would get an exception.';
    }
}

// 'manual' is the reason the reverse direction is not asserted as an equality:
// it exists only for the technician-with-a-USB-stick path, which nothing
// automatic can ever report. Pinned so that a future "tidy up the unused
// value" removes it deliberately rather than by accident -- it is the whole
// point of the asserted half of ADR 0029.
if (!in_array('manual', $allowed, true)) {
    $problems[] = "the host form no longer accepts 'manual'. That is the only"
        . ' value recording an enrollment done by hand at the machine, which is'
        . ' the path ADR 0029 makes the asserted half editable for.';
}

// 'mok-pending' must be storable, and a staged request must not become 'mok'.
// A staged MOK is not an enrollment (ADR 0029 decision 6), and the host grid
// keys its "pending" badge on exactly this string.
if (!in_array('mok-pending', $stored, true)) {
    $problems[] = 'service/secureboot.report.php no longer stores'
        . " 'mok-pending'. A staged MOK request recorded as an enrollment is a"
        . ' lie an administrator acts on -- they turn Secure Boot on and the'
        . ' machine stops booting.';
}
if (in_array('mok', $stored, true)) {
    $problems[] = "service/secureboot.report.php stores 'mok'. FOS can only"
        . " ever STAGE a request, so 'mok-pending' is the only honest value;"
        . " 'mok' is reserved for a human confirming it at the MokManager"
        . ' screen.';
}

stopOnProblems($problems);

fwrite(
    STDOUT,
    sprintf(
        "PASS: %d stored word(s) all accepted by the host form (%s)\n",
        count($stored),
        implode(', ', $stored)
    )
);
exit(0);
