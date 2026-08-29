<?php
/**
 * Guards IpxeBootMenu::printDefault()'s PXE-menu gating for pxeID 15 ("Enroll
 * Secure Boot Key (Unattended...)", mode=enrollsb): it must stay hidden on
 * non-EFI platforms exactly like pxeID 14, and must additionally stay
 * hidden unless PK.auth/KEK.auth/db.auth all exist in service/secureboot/
 * -- without them mode=enrollsb's auto-enroll path has nothing valid to
 * write.
 *
 * Static source check (no DB, no server) -- IpxeBootMenu needs the full FOG
 * bootstrap to run, so this parses printDefault() instead.
 *
 * Usage: php tests/pxe-secureboot-menu-gating.test.php [path/to/bootmenu.class.php]
 * Exit status 0 = pass, 1 = fail.
 */

$file = $argv[1] ?? dirname(__DIR__) . '/packages/web/src/Boot/IpxeBootMenu.php';

if (!is_readable($file)) {
    fwrite(STDERR, "FAIL: cannot read $file\n");
    exit(1);
}

$src = file_get_contents($file);

$failures = [];

// Every gate below matches on pxeName, not pxeID. pxeMenu is user-writable
// with an auto_increment key, so these rows only sit at 14/15 on a pristine
// install -- keyed by id, a site whose own custom entry happened to land
// there had THAT entry hidden instead. Re-keyed by facea052b; the original
// two assertions were written against the id form and were not updated with
// it, which nothing noticed because nothing ran them.
//
// The three conditions used to carry their own array_filter() each, so the
// name they matched on was part of every assertion. They now push onto a
// shared $hide list that ONE filter applies, which is why the name check is
// asserted separately at the end: collapsing the passes moved it out of
// reach of the per-condition patterns, and an assertion that stops covering
// what it names is worse than no assertion.

// The non-EFI platform gate must hide the unattended item alongside the
// attended one.
if (!preg_match(
    "/\\\$_REQUEST\['platform'\]\s*!=\s*'efi'.{0,900}?"
    . "'fog\.enrollsecureboot'.{0,200}?'fog\.enrollsecurebootunattended'/s",
    $src
)) {
    $failures[] = "platform != efi gate does not hide "
        . "fog.enrollsecurebootunattended alongside fog.enrollsecureboot";
}

// A separate gate must hide the unattended item unless all three .auth
// files exist.
if (!preg_match(
    "/'PK\.auth'.{0,200}?'KEK\.auth'.{0,200}?'db\.auth'.{0,600}?"
    . "'fog\.enrollsecurebootunattended'/s",
    $src
)) {
    $failures[] = "no gate hiding fog.enrollsecurebootunattended unless "
        . "PK.auth/KEK.auth/db.auth all exist";
}

// Whatever those gates collect, the filter that applies it must key on the
// menu's NAME. This is the assertion the two above used to make in passing.
if (!preg_match("/array_filter\(.{0,400}?\\\$Menu->name/s", $src)) {
    $failures[] = "the menu filter no longer matches on \$Menu->name";
}

// No gate may go back to matching on the menu id.
if (preg_match("/in_array\(\s*\(int\)\\\$Menu->id/s", $src)
    || preg_match("/\(int\)\\\$Menu->id\s*!==?\s*1[45]/s", $src)
) {
    $failures[] = "a menu gate is keyed by \$Menu->id again -- pxeMenu is "
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
