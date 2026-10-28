<?php
/**
 * FOGSSH can be used again after disconnect() without a warning.
 *
 * FOGSSH is one shared instance (LoadGlobals). disconnect() used to unset()
 * its declared $_link and $_sftp. PHP then routes the next read of an unset
 * property to __get(), which looks it up in $data and warns
 * 'Undefined array key "_link"'. Every daemon pass that disconnected and
 * reused the instance wrote that warning to servicemaster.log.
 *
 * disconnect() also passed a null or false link to ssh2_disconnect(). On
 * PHP 8 that is a TypeError, which @ does not suppress, so a disconnect()
 * after a failed connect() was fatal.
 *
 * Runs with or without the ssh2 extension: with it, the first disconnect()
 * on a never-connected instance is the TypeError case.
 *
 * Usage: php tests/fogssh-reuse-after-disconnect.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('fogssh-reuse-after-disconnect');

$t = new FogChecks();

$warnings = [];
set_error_handler(function ($no, $str, $file, $line) use (&$warnings) {
    $warnings[] = "$str at " . basename($file) . ":$line";
    return true;
});

$ssh = new \FOG\Net\FOGSSH();
$fatal = '';
try {
    $ssh->disconnect();
    $ssh->disconnect();
} catch (\Throwable $e) {
    $fatal = get_class($e) . ': ' . $e->getMessage();
}

restore_error_handler();

$t->check(
    'disconnect() without a live session does not throw'
    . ($fatal ? " ($fatal)" : ''),
    '' === $fatal
);
$t->check(
    'a second disconnect() reads $_link and $_sftp without a warning'
    . ($warnings ? ' (' . implode('; ', $warnings) . ')' : ''),
    [] === $warnings
);

$t->finish();
