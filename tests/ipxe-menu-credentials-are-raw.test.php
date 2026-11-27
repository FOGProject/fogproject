<?php
/**
 * The iPXE menu hands authenticateOnly() the username and password exactly as
 * sent. Initiator::sanitizeItems() trims $_POST and $_GET at boot, so a read
 * through FOGBase::requestParam() would turn "pass " into "pass" and a
 * password with edge whitespace would stop working. The menu reads them with
 * FOGBase::rawRequestParam() instead.
 *
 * Runs the real IpxeBootMenu in the boot-menu harness (the harness mirror of
 * rawRequestParam() reads the unsanitized scenario request, as filter_input()
 * would) and checks what reached authenticateOnly().
 *
 * Usage: php tests/ipxe-menu-credentials-are-raw.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$renderer = __DIR__ . '/lib/bootmenu-render.php';
$classFile = $root . '/packages/web/src/Boot/IpxeBootMenu.php';

$scenario = [
    'request' => [
        'arch' => 'x86_64', 'platform' => 'bios',
        'username' => ' menuuser ', 'password' => "pa ss \t ",
    ],
    'requestMacs' => ['aa:bb:cc:dd:ee:ff'],
    'reportAuth' => true,
];
$out = (string)shell_exec(sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($renderer),
    escapeshellarg(json_encode($scenario)),
    escapeshellarg($classFile)
));

$want = '#auth ' . json_encode([' menuuser ', "pa ss \t "]);
if (false === strpos($out, $want)) {
    fwrite(STDERR, "FAIL: expected a line $want\n--- output ---\n$out\n");
    exit(1);
}

/*
 * The product key is read raw too. A key of " " must reach
 * productKeyIsValid() as sent and be refused with keychangefail. Read through
 * the boot-trimmed helper it would become '' -- which keyset() accepts as
 * "clear the key" -- and wipe the host's stored key.
 */
$keyOut = (string)shell_exec(sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($renderer),
    escapeshellarg(json_encode([
        'request' => ['arch' => 'x86_64', 'platform' => 'bios', 'key' => ' '],
        'requestMacs' => ['aa:bb:cc:dd:ee:ff'],
        'host' => ['id' => 1, 'name' => 'testhost'],
    ])),
    escapeshellarg($classFile)
));
if (false === strpos($keyOut, 'echo Failed to change key')) {
    fwrite(STDERR, "FAIL: key=' ' was not refused\n--- output ---\n$keyOut\n");
    exit(1);
}
echo "ok: authenticateOnly() received the username and password unchanged\n";
echo "ok: a key of ' ' is refused\n";
exit(0);
