#!/usr/bin/env php
<?php
/**
 * Verifies that a plugin's install path cannot destroy the data it already has.
 *
 * PHP version 7.4+
 *
 * @category VerifyPluginInstall
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 *
 * Usage:
 *
 *   php bin/verify-plugin-install-nondestructive.php <fog-web-root> [ManagerClass]
 *
 * <fog-web-root> is a DEPLOYED tree -- /var/www/fog on most servers -- because
 * plugins ship from FOGProject/fog-plugins as a pinned release asset and are not
 * present in this repository (ADR 0009). ManagerClass defaults to
 * WolbroadcastManager.
 *
 * Point it at a plugin's OWN manager -- the one named after the plugin directory.
 * A secondary manager for an association or sub-table correctly has no schema()
 * of its own, because it is reached as a step INSIDE the primary's; running this
 * against one reports a missing schema() that is not a defect.
 *
 * WHAT IT CHECKS, and why each one is here
 *
 * A 1.6 plugin builds its table from a public createSql() used as step 0 of an
 * append-only schema(), applied by Schema::applyUpdates(). The failure this
 * catches is a manager with NO schema(): Plugin::installdb() then falls back to
 * calling install(), and the legacy install() shape begins with uninstall() --
 * a DROP -- so every install silently destroys the user's rows. wolbroadcast
 * was the last plugin doing exactly that (FOGProject/fog-plugins#24, v1.6.14).
 *
 * So it asserts, in order: createSql() is callable; schema() exists at all;
 * step 0 is a CREATE TABLE IF NOT EXISTS; and -- the part that needs a real
 * server -- re-running that statement over a populated table leaves the rows
 * alone.
 *
 * WHY IT IS IN bin/ AND NOT tests/
 *
 * tests/ is deliberately framework-free and database-free (tests/run-all.sh,
 * docs/adr/0008). This needs a live database and a deployed plugin tree, which
 * is the same reason bin/schema-manifest.php lives here. The static half of the
 * contract IS covered by CI, in fog-plugins' tests/tables-carry-column-defaults
 * .test.php; what cannot be covered there is the behaviour of the DDL against a
 * real server, which is what this is for.
 *
 * SAFETY
 *
 * The real table is never touched. The generated statement is rewritten onto a
 * zzclaude_-prefixed scratch name, and the run aborts if that rewrite does not
 * take. Booting the deployed tree is read-only. The scratch table is dropped on
 * every exit path, including failure.
 *
 * Exit status 0 = the install path is non-destructive, non-zero = it is not.
 *
 * GH-1245.
 */
$webroot = isset($argv[1]) ? rtrim($argv[1], '/') : '';
$class   = isset($argv[2]) ? $argv[2] : 'WolbroadcastManager';
if ('' === $webroot || !file_exists($webroot . '/commons/base.inc.php')) {
    fwrite(
        STDERR,
        "usage: php bin/verify-plugin-install-nondestructive.php <fog-web-root> "
        . "[ManagerClass]\n"
    );
    exit(2);
}
require_once $webroot . '/commons/base.inc.php';

/**
 * FOGBase::$DB is protected, so reach it from inside a subclass.
 *
 * @category VerifyPluginInstall
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class VerifyPluginInstallDb extends \FOG\FOGBase
{
    /**
     * The shared PDODB.
     *
     * @return object
     */
    public static function db()
    {
        return self::$DB;
    }
}

/**
 * Reports one check.
 *
 * Output goes to STDERR because commons/base.inc.php starts an output buffer
 * through Initiator::sanitizeOutput(), which collapses whitespace and would
 * fold this whole report onto one line.
 *
 * @param string $line what to say
 *
 * @return void
 */
function vpiSay($line)
{
    fwrite(STDERR, $line . "\n");
}

/**
 * Builds an INSERT that satisfies every column the table refuses to default.
 *
 * A column left bare -- NOT NULL with no DEFAULT -- is one the model declares
 * required, so it must be given a value or the insert is rejected under
 * STRICT_TRANS_TABLES. The value is chosen from the declared type rather than
 * assumed to be a string, because an ENUM takes a member and an integer column
 * takes a number.
 *
 * @param string $ddl   the CREATE TABLE statement
 * @param string $table the (scratch) table name to insert into
 *
 * @return string
 */
function vpiInsertFor($ddl, $table)
{
    $cols = [];
    $vals = [];
    preg_match_all('/`([^`]+)`\s+([^,]*?)(?=,\s*`|,?\s*PRIMARY|,?\s*UNIQUE)/i', $ddl, $m);
    foreach ($m[1] as $i => $col) {
        $decl = $m[2][$i];
        if (stripos($decl, 'NOT NULL') === false
            || stripos($decl, 'DEFAULT') !== false
            || stripos($decl, 'AUTO_INCREMENT') !== false
        ) {
            continue;
        }
        $cols[] = sprintf('`%s`', $col);
        if (preg_match("/\b(enum|set)\s*\(\s*'([^']*)'/i", $decl, $e)) {
            $vals[] = sprintf("'%s'", $e[2]);
        } elseif (preg_match('/\b(int(eger)?|bool(ean)?|decimal|float|double)\b/i', $decl)) {
            $vals[] = '1';
        } else {
            $vals[] = "'zzclaude'";
        }
    }
    if (!$cols) {
        return sprintf('INSERT INTO `%s` () VALUES ()', $table);
    }
    return sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $table,
        implode(',', $cols),
        implode(',', $vals)
    );
}

if (!class_exists($class)) {
    vpiSay(sprintf('FAIL: %s is not loaded from %s', $class, $webroot));
    exit(1);
}
$mgr = new $class();
foreach (['createSql', 'schema'] as $method) {
    if (!method_exists($mgr, $method)) {
        vpiSay(
            sprintf(
                'FAIL: %s has no public %s() -- installdb() falls back to install(), '
                . 'which DROPs the table',
                $class,
                $method
            )
        );
        exit(1);
    }
}
vpiSay(sprintf('%s::schema() returns %d step(s)', $class, count($mgr->schema())));

$sql = $mgr->createSql();
if (stripos($sql, 'IF NOT EXISTS') === false) {
    vpiSay('FAIL: step 0 is not a CREATE TABLE IF NOT EXISTS');
    exit(1);
}
if (!preg_match('/CREATE TABLE IF NOT EXISTS\s+`([^`]+)`/i', $sql, $t)) {
    vpiSay('FAIL: could not read the table name out of step 0');
    exit(1);
}
$real    = $t[1];
$scratch = 'zzclaude_' . $real;
$ddl     = str_replace('`' . $real . '`', '`' . $scratch . '`', $sql);
if ($ddl === $sql) {
    vpiSay('FAIL: could not rename the table -- refusing to touch the real one');
    exit(1);
}

$fail = 0;
$db   = VerifyPluginInstallDb::db();
try {
    $db->query(sprintf('DROP TABLE IF EXISTS `%s`', $scratch));
    $db->query($ddl);
    vpiSay(sprintf('run 1: `%s` created', $scratch));

    $db->query(vpiInsertFor($sql, $scratch));
    $before = (int)$db->query(sprintf('SELECT COUNT(*) AS c FROM `%s`', $scratch))
        ->fetch()
        ->get('c');
    vpiSay(sprintf('row inserted, count = %d', $before));

    // Exactly what a second plugin install now runs.
    $db->query($ddl);
    $after = (int)$db->query(sprintf('SELECT COUNT(*) AS c FROM `%s`', $scratch))
        ->fetch()
        ->get('c');
    vpiSay(sprintf('run 2 (re-install): count = %d', $after));

    if (1 === $before && 1 === $after) {
        vpiSay(sprintf('PASS: re-installing %s preserved the row', $class));
    } else {
        vpiSay(sprintf('FAIL: row count went %d -> %d', $before, $after));
        $fail = 1;
    }
} catch (Exception $e) {
    vpiSay('FAIL: ' . $e->getMessage());
    $fail = 1;
}
$db->query(sprintf('DROP TABLE IF EXISTS `%s`', $scratch));
vpiSay('scratch table dropped');
exit($fail);
