<?php
/**
 * The four paired daemons still do the same things in the same order.
 *
 * ImageReplicator/SnapinReplicator were ~90% the same file and now share
 * FOGReplicator; ImageSize/SnapinHash likewise now share FOGItemScanner. Moving a sequence into a base does not break parsing, so
 * `php -l` proves nothing here; what breaks is the ORDER of the calls or one
 * of the messages, in a daemon nobody watches, on a branch that only runs
 * when a storage group is misconfigured.
 *
 * So the whole sequence is replayed against stubs -- no database, no storage
 * node, no ftp -- and compared byte for byte with a recorded transcript.
 * tests/lib/replicator-transcript.php holds the stubs and the scenarios; it
 * takes a directory, so the same harness can be pointed at an older copy of
 * the daemons out of git and the two transcripts diffed directly. That is
 * how the fixtures below were reviewed line by line before being committed,
 * and it is how the one regression this refactor did introduce was found:
 * the base was building static::$log from $dev instead of self::$logpath,
 * which put the log in the wrong place for anyone who had set a log path.
 *
 * If this test fails, do not edit the fixture until you have run:
 *
 *   git show <before>:packages/web/src/Service/ImageReplicator.php \
 *     > /tmp/old/imagereplicator.class.php
 *   php tests/lib/replicator-transcript.php ImageReplicator /tmp/old
 *
 * and satisfied yourself the difference is one you meant.
 *
 * Usage: php tests/service-daemon-transcript.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$daemons = [
    'ImageReplicator' => 'replicator',
    'SnapinReplicator' => 'replicator',
    'ImageSize' => 'scanner',
    'SnapinHash' => 'scanner'
];

$failures = [];
$checks = 0;

foreach ($daemons as $class => $kind) {
    $checks++;
    $harness = sprintf('%s/tests/lib/%s-transcript.php', $root, $kind);
    $fixture = sprintf(
        '%s/tests/fixtures/%s-transcript.txt',
        $root,
        strtolower($class)
    );
    if (!is_readable($fixture)) {
        $failures[] = $class . ': no recorded transcript at ' . $fixture;
        continue;
    }
    $cmd = sprintf(
        '%s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($harness),
        escapeshellarg($class)
    );
    $got = (string)shell_exec($cmd);
    $want = (string)file_get_contents($fixture);
    if ($got === $want) {
        continue;
    }
    $gotLines = explode("\n", $got);
    $wantLines = explode("\n", $want);
    $detail = [];
    foreach ($wantLines as $i => $line) {
        if (($gotLines[$i] ?? null) === $line) {
            continue;
        }
        $detail[] = sprintf(
            "      line %d\n        expected: %s\n        got:      %s",
            $i + 1,
            $line,
            $gotLines[$i] ?? '<end of output>'
        );
        if (count($detail) >= 5) {
            break;
        }
    }
    if (count($gotLines) !== count($wantLines) && !$detail) {
        $detail[] = sprintf(
            '      transcript length changed: %d lines, expected %d',
            count($gotLines),
            count($wantLines)
        );
    }
    $failures[] = $class . " transcript changed:\n" . implode("\n", $detail);
}

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    exit(1);
}

printf("ok  %d checks passed\n", $checks);
exit(0);
