<?php
/**
 * FOGSSH and FOGFTP must not call password_hash().
 *
 * Both classes used to bcrypt their connection data on every connect() and
 * their link id on every login(), then compare the result with the previous
 * one to skip a repeat dial or login. password_hash() adds a random salt, so
 * two hashes of the same input never compare equal. The skip was never taken.
 * The only effect was about 85 ms of bcrypt (cost 11) per call, in daemon
 * loops that call connect() and login() many times. (GH-2067)
 *
 * A salted hash can never serve as a cache key. A future reuse check needs a
 * deterministic key, and it changes login behavior, so it needs its own
 * review. This test stops the dead pattern from coming back.
 *
 * The scan uses the tokenizer, so a mention in a comment is not a hit.
 *
 * Usage: php tests/net-no-dead-reuse-hash.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);

$files = [
    'packages/web/src/Net/FOGSSH.php',
    'packages/web/src/Net/FOGFTP.php',
];

// On PHP 8 a fully qualified call (`\password_hash(`) is one token of this
// type, not T_STRING. PHP 7.4 has no such token and splits off the backslash.
$nameTokens = [T_STRING];
if (defined('T_NAME_FULLY_QUALIFIED')) {
    $nameTokens[] = constant('T_NAME_FULLY_QUALIFIED');
}

$fail = false;
$hits = [];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    if (!is_readable($path)) {
        // A missing file would otherwise report a clean pass.
        fwrite(STDERR, "FAIL: cannot read $file\n");
        $fail = true;
        continue;
    }
    $tokens = token_get_all(file_get_contents($path));
    $count = count($tokens);
    $sawConnect = false;
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || !in_array($token[0], $nameTokens, true)) {
            continue;
        }
        if ('connect' === $token[1]) {
            $sawConnect = true;
        }
        if ('password_hash' !== strtolower(ltrim($token[1], '\\'))) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count
            && is_array($tokens[$j])
            && T_WHITESPACE === $tokens[$j][0]
        ) {
            $j++;
        }
        if (isset($tokens[$j]) && '(' === $tokens[$j]) {
            $hits[] = $file . ':' . $token[2];
        }
    }
    if (!$sawConnect) {
        // The scan found no connect() method, so it scanned the wrong thing.
        fwrite(STDERR, "FAIL: no connect() found in $file\n");
        $fail = true;
    }
}

foreach ($hits as $where) {
    fwrite(
        STDERR,
        "FAIL: password_hash() call at $where"
        . " (a salted hash can never match as a reuse key)\n"
    );
    $fail = true;
}

if ($fail) {
    exit(1);
}

echo 'PASS: no password_hash() call in ' . implode(', ', $files) . "\n";
exit(0);
