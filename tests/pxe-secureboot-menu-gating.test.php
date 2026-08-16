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

// Both filters below match on pxeName, not pxeID. pxeMenu is user-writable
// with an auto_increment key, so these rows only sit at 14/15 on a pristine
// install -- keyed by id, a site whose own custom entry happened to land
// there had THAT entry hidden instead. Re-keyed by facea052b; these two
// assertions were written against the id form and were not updated with it,
// which nothing noticed because nothing ran them.

// The non-EFI platform filter must hide the unattended item alongside the
// attended one.
if (!preg_match(
    "/\\\$_REQUEST\['platform'\]\s*!=\s*'efi'.{0,900}?"
    . "in_array\(\s*\\\$Menu->name,.{0,200}?'fog\.enrollsecureboot',"
    . ".{0,200}?'fog\.enrollsecurebootunattended'/s",
    $src
)) {
    $failures[] = "platform != efi filter does not hide "
        . "fog.enrollsecurebootunattended alongside fog.enrollsecureboot, "
        . "matched by pxeName";
}

// A separate filter must hide the unattended item unless all three .auth
// files exist.
if (!preg_match(
    "/'PK\.auth'.{0,200}?'KEK\.auth'.{0,200}?'db\.auth'.{0,600}?"
    . "'fog\.enrollsecurebootunattended'\s*!==\s*\\\$Menu->name/s",
    $src
)) {
    $failures[] = "no filter hiding fog.enrollsecurebootunattended unless "
        . "PK.auth/KEK.auth/db.auth all exist";
}

// Neither filter may go back to matching on the menu id.
if (preg_match("/in_array\(\s*\(int\)\\\$Menu->id/s", $src)
    || preg_match("/\(int\)\\\$Menu->id\s*!==?\s*1[45]/s", $src)
) {
    $failures[] = "a menu filter is keyed by \$Menu->id again -- pxeMenu is "
        . "user-writable, match on \$Menu->name";
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
