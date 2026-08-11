<?php
/**
 * Guards the two pxeMenu schema changes for unattended Secure Boot enrol:
 *  - pxeID 14 ("Enroll Secure Boot Key") gets relabeled to distinguish it
 *    from the new unattended item, via a new UPDATE step (321 already ran
 *    on existing installs, so it cannot be edited in place).
 *  - pxeID 15 is inserted for the unattended path: `mode=enrollsb` is task
 *    type 25's kernel arg (schema step 323), exposed directly on the menu.
 *
 * Static source check (no DB) -- schema.php runs inside a loader method
 * context ($this/self::) and cannot be required standalone.
 *
 * Usage: php tests/pxe-secureboot-menu-schema.test.php [path/to/schema.php]
 * Exit status 0 = pass, 1 = fail.
 */

$file = $argv[1] ?? dirname(__DIR__) . '/packages/web/commons/schema.php';

if (!is_readable($file)) {
    fwrite(STDERR, "FAIL: cannot read $file\n");
    exit(1);
}

$src = file_get_contents($file);

// Collapse PHP string concatenations split across lines ("...".\n."...")
// into one contiguous string, the same way PHP itself would at parse time,
// so a literal can be checked for even when its source wraps.
$flat = preg_replace('/"\s*\r?\n\s*\.\s*"/', '', $src);

$failures = [];

if (strpos(
    $flat,
    "UPDATE `pxeMenu` SET `pxeDesc`='Enroll Secure Boot Key "
    . "(MOK attended setup)' WHERE `pxeID`=14"
) === false) {
    $failures[] = "no UPDATE renaming pxeID 14 to "
        . "'Enroll Secure Boot Key (MOK attended setup)'";
}

if (strpos(
    $flat,
    "(15, 'fog.enrollsecurebootunattended', 'Enroll Secure Boot Key "
    . "(Unattended - secure boot in setup mode required)', '0', '2', "
    . "'mode=enrollsb')"
) === false) {
    $failures[] = "no pxeID 15 INSERT for fog.enrollsecurebootunattended "
        . "with the expected desc/default/regOnly/args";
}

if (count($failures) > 0) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}

echo "PASS\n";
exit(0);
