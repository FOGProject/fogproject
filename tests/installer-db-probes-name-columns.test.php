<?php
/**
 * Every INSERT the installer runs names its columns.
 *
 * The fogstorage grant probe writes a throwaway row to find out whether that
 * user still holds INSERT. A failure is read as "the grants need redoing",
 * which is what sends the installer off to demand a database root password --
 * so a probe that fails for ANY other reason produces a prompt on a server
 * whose grants are perfectly correct, with nothing on screen to say why.
 *
 * It has broken that way twice, and both times the cause was the same
 * positional INSERT against a table somebody else changed:
 *
 *   schema 336 made taskLog.taskID an int(11); the marker '999test' in that
 *     column became error 1265 under STRICT_TRANS_TABLES. Repaired by moving
 *     the marker, which left the positional list in place;
 *   schema 338 added taskLog.logType and taskLog.logText, so the six-value
 *     list became error 1136, "Column count doesn't match value count".
 *
 * The repair that holds is naming the columns: a column added later takes its
 * default and the INSERT does not care. This test is what makes the next
 * schema step unable to reopen it -- the failure is invisible in review
 * (`INSERT INTO x VALUES (...)` looks fine) and invisible at install time on
 * any server that has not taken the schema change yet.
 *
 * Textual, because these are shell strings inside a 9000-line library; there
 * is nothing to execute without a database and a root password.
 *
 * Usage: php tests/installer-db-probes-name-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$src = file_get_contents($root . '/lib/common/functions.sh');

$fails = [];
$checks = 0;

// Every INSERT INTO in the installer, with whatever follows the table name.
preg_match_all(
    '#INSERT\s+INTO\s+(\S+?)\s*(\(|VALUES)#i',
    $src,
    $matches,
    PREG_SET_ORDER
);

foreach ($matches as $m) {
    $checks++;
    if ($m[2] === '(') {
        continue;
    }
    $fails[] = sprintf(
        'INSERT INTO %s has no column list, so any later ADD COLUMN on that'
        . ' table breaks it -- and when the statement is a grant probe, the'
        . ' user sees a demand for the database root password instead of an'
        . ' error',
        $m[1]
    );
}

if ($checks < 1) {
    $fails[] = 'no INSERT statements found at all; this test has stopped'
        . ' testing anything and needs its pattern checked';
}

// The grant probe specifically: name it, so a rewrite that drops the probe
// entirely does not silently pass the check above.
if (false === strpos($src, "fog-install-probe")) {
    $fails[] = 'the fogstorage grant probe marker is gone; if the probe was'
        . ' replaced, point this test at whatever replaced it';
}
if (!preg_match(
    '#INSERT INTO \$mysqldbname\.taskLog \([^)]*createdBy[^)]*\) VALUES#',
    $src
)) {
    $fails[] = 'the fogstorage grant probe no longer names its columns, which'
        . ' is the one thing that keeps a schema change from turning an'
        . ' upgrade into a root-password prompt';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: all $checks installer INSERT statement(s) name their columns\n";
exit(0);
