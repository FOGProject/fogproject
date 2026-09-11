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
 * It was a positional INSERT into taskLog, and schema 280 adds logType and
 * logText to that table, which would have made it error 1136, "Column count
 * doesn't match value count". 1.6 shipped that regression before catching it
 * (fogproject#1209) and had already been bitten once before by the same
 * statement (its schema 336 changed a column type under it). This branch gets
 * the repair and the gate together, so it is never bitten at all.
 *
 * Naming the columns is what makes it durable: a column added later takes its
 * default and the INSERT does not care. The failure is invisible in review --
 * `INSERT INTO x VALUES (...)` looks fine -- and invisible at install time on
 * any server that has not taken the schema change yet, which is why it is
 * worth a test rather than a comment.
 *
 * Textual, because these are shell strings inside a 9000-line library; there
 * is nothing to execute without a database and a root password.
 *
 * Usage: php tests/installer-db-probes-name-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$src = file_get_contents($root . '/lib/common/functions.sh');

$fails = array();
$checks = 0;

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

if (false === strpos($src, "999test")) {
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
