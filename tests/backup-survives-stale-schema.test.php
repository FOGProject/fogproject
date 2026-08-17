<?php
/**
 * The pre-upgrade database dump must survive a stale schema.
 *
 * DatabaseManager::establish() redirects every request to the schema updater
 * while `mySchema < FOG_SCHEMA`, except for the basenames in its $okayFiles
 * allowlist. maintenance/backup_db.php has to be one of them.
 *
 * The dump exists to give an administrator something to roll back TO. A
 * stale or half-applied schema is the state it is meant to protect against,
 * so excluding it from the allowlist means the backup is skipped in exactly
 * the case it was written for -- and skipped silently, because the redirect
 * is a 308 with an empty body, which is an error to neither the web server
 * nor to the installer's `curl -skf`.
 *
 * GH-1147 is what that costs. A schema reconcile failed on a collation the
 * server did not have; that left $errors non-empty in
 * SchemaUpdaterPage::update(), which threw before $newSchema->save(), so the
 * schema version was never recorded; so this redirect fired for every later
 * request, including the installer's; so the pre-upgrade dump came back empty
 * and the operator was asked to press Enter to continue an upgrade with no
 * backup taken.
 *
 * This test does not assert the rest of the allowlist. Those four entries
 * are a different concern (endpoints the FOG client and the installer poll
 * before the schema is known good) and are free to change without this line
 * changing with them.
 *
 * Textual rather than by execution: lib/db/databasemanager.class.php cannot
 * be loaded without a database and the config constants, which is what every
 * other schema-adjacent test in this directory works around the same way.
 *
 * Usage: php tests/backup-survives-stale-schema.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$path = dirname(__DIR__)
    . '/packages/web/lib/db/databasemanager.class.php';

if (!is_readable($path)) {
    fwrite(STDERR, "FAIL: cannot read $path\n");
    exit(1);
}

$src = file_get_contents($path);

/*
 * The allowlist literal. Matched from the assignment to its closing bracket
 * so a `backup_db.php` mentioned in a comment elsewhere in the file -- there
 * is one, in this entry's own explanation -- cannot satisfy the check.
 */
$block = [];
if (!preg_match('/\$okayFiles\s*=\s*\[(.*?)\n\s*\];/s', $src, $block)) {
    fwrite(
        STDERR,
        "FAIL: could not find the \$okayFiles literal in\n"
        . "  lib/db/databasemanager.class.php. If it was renamed or moved,\n"
        . "  update this test -- but check first that backup_db.php is still\n"
        . "  exempt from the stale-schema redirect, which is what it guards.\n"
    );
    exit(1);
}

/*
 * Only the quoted entries count, not the prose. establish() compares against
 * basename(self::$scriptname), so the entry is the bare filename.
 */
$entries = [];
preg_match_all("/'([^']+\.php)'/", $block[1], $entries);

if (!in_array('backup_db.php', $entries[1], true)) {
    fwrite(
        STDERR,
        "FAIL: backup_db.php is not in \$okayFiles.\n"
        . "  DatabaseManager::establish() will 308-redirect the installer's\n"
        . "  pre-upgrade database dump to ?node=schema whenever the schema is\n"
        . "  out of date -- which is the one case the dump exists for. The\n"
        . "  installer cannot see this: a 308 passes `curl -f`, so the step\n"
        . "  reports\n"
        . "      * Backing up database...Failed\n"
        . "  with nothing in any log. See GH-1147.\n"
        . "  Found: " . implode(', ', $entries[1]) . "\n"
    );
    exit(1);
}

echo "ok  backup_db.php survives the stale-schema redirect\n";
exit(0);
