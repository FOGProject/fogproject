<?php
/**
 * Guards BootMenu::printDefault()'s PXE-menu gating for pxeID 15 ("Enroll
 * Secure Boot Key (Unattended...)", mode=enrollsb): it must stay hidden on
 * non-EFI platforms exactly like pxeID 14, and must additionally stay
 * hidden unless PK.auth/KEK.auth/db.auth all exist in service/secureboot/
 * -- without them mode=enrollsb's auto-enrol path has nothing valid to
 * write.
 *
 * Static source check (no DB, no server) -- BootMenu needs the full FOG
 * bootstrap to run, so this parses printDefault() instead.
 *
 * Usage: php tests/pxe-secureboot-menu-gating.test.php [path/to/bootmenu.class.php]
 * Exit status 0 = pass, 1 = fail.
 */

$file = $argv[1] ?? dirname(__DIR__) . '/packages/web/lib/fog/bootmenu.class.php';

if (!is_readable($file)) {
    fwrite(STDERR, "FAIL: cannot read $file\n");
    exit(1);
}

$src = file_get_contents($file);

$failures = [];

// The non-EFI platform filter must hide pxeID 15 alongside pxeID 14.
if (!preg_match(
    "/\\\$_REQUEST\['platform'\]\s*!=\s*'efi'.{0,400}?"
    . "in_array\(\(int\)\\\$Menu->id,\s*\[14,\s*15\],\s*true\)/s",
    $src
)) {
    $failures[] = "platform != efi filter does not hide pxeID 15 "
        . "alongside pxeID 14";
}

// A separate filter must hide pxeID 15 unless all three .auth files exist.
if (!preg_match(
    "/'PK\.auth'.{0,200}?'KEK\.auth'.{0,200}?'db\.auth'.{0,400}?"
    . "return\s*\(int\)\\\$Menu->id\s*!==\s*15/s",
    $src
)) {
    $failures[] = "no filter hiding pxeID 15 unless PK.auth/KEK.auth/"
        . "db.auth all exist";
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
