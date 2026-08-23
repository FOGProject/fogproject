<?php
/**
 * SnapinHash must not save a hash for a file that is not there.
 *
 * ImageSize has checked file_exists()/is_readable() before touching the file
 * for years, and records a zero when it is gone. SnapinHash never grew that
 * guard, so it went straight into hash_file() -- which returns FALSE with a
 * warning for a missing file -- and false was then SAVED as the hash.
 *
 * The consequence is entirely invisible from the server side. The daemon
 * logs " | Hash: " with nothing after it and moves on; the record now holds
 * an empty hash; and every client that downloads that snapin compares its
 * own sha512 against the empty string and fails the deployment, with
 * nothing anywhere saying why. It is reachable any time a snapin file is
 * deleted behind FOG's back, or the storage is unmounted when a pass runs.
 *
 * This replays the whole daemon against stubs -- no database, no storage
 * node -- and compares it byte for byte with a recorded transcript. Every
 * set() and save() is in that transcript, because what gets WRITTEN is the
 * thing that matters here, not what gets logged.
 *
 * tests/lib/snapinhash-transcript.php takes a directory, so the pre-fix
 * daemon can be run against the same stubs:
 *
 *   git show <before>:packages/web/lib/service/snapinhash.class.php \
 *     > /tmp/old/snapinhash.class.php
 *   php tests/lib/snapinhash-transcript.php /tmp/old
 *
 * which prints the hash_file() warning and `[set Snapin#22 hash=false]`.
 * Do not edit the fixture until you have done that and satisfied yourself
 * the difference is one you meant.
 *
 * Usage: php tests/snapinhash-missing-file.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$harness = $root . '/tests/lib/snapinhash-transcript.php';
$fixture = $root . '/tests/fixtures/snapinhash-transcript.txt';

$failures = array();
$checks = 0;

$checks++;
if (!is_readable($fixture)) {
    $failures[] = 'no recorded transcript at ' . $fixture;
} else {
    $got = (string)shell_exec(
        sprintf(
            '%s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($harness)
        )
    );
    $want = (string)file_get_contents($fixture);
    if ($got !== $want) {
        $gotLines = explode("\n", $got);
        $wantLines = explode("\n", $want);
        $detail = array();
        foreach ($wantLines as $i => $line) {
            if (isset($gotLines[$i]) && $gotLines[$i] === $line) {
                continue;
            }
            $detail[] = sprintf(
                "      line %d\n        expected: %s\n        got:      %s",
                $i + 1,
                $line,
                isset($gotLines[$i]) ? $gotLines[$i] : '<end of output>'
            );
            if (count($detail) >= 5) {
                break;
            }
        }
        $failures[] = "transcript changed:\n" . implode("\n", $detail);
    }
}

// The two specific things that must never come back, stated directly so a
// reader of a failure knows what the transcript was protecting.
$checks++;
$src = (string)file_get_contents(
    $root . '/packages/web/lib/service/snapinhash.class.php'
);
// Comments are stripped first. The guard carries an explanation that names
// hash_file(), and a non-greedy match from $filepath to the first
// "hash_file" otherwise stops inside that comment -- so the assertion fails
// on correct code, for the same reason the installer guard test had to do
// this: the prose describing a fix looks exactly like the code it replaced.
$src = preg_replace('#^\s*//.*$#m', '', $src);
$guarded = false;
if (preg_match('/\$filepath = sprintf\(.*?hash_file/s', $src, $m)) {
    $guarded = false !== strpos($m[0], 'file_exists($filepath)')
        && false !== strpos($m[0], 'is_readable($filepath)');
}
if (!$guarded) {
    $failures[] = 'hash_file() is reached without a file_exists/is_readable guard';
}

$checks++;
if (false === strpos($src, "->set('hash', '')")) {
    $failures[] = 'a missing file no longer blanks the stored hash';
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
