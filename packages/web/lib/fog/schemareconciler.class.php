<?php
/**
 * Repairs a database whose structure has fallen behind this release.
 *
 * PHP version 5
 *
 * @category SchemaReconciler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Repairs a database whose structure has fallen behind this release.
 *
 * WHY THIS EXISTS
 * ---------------
 * `schemaVersion`.`vValue` is not a version number -- it is a count of
 * applied elements of the $this->schema array. The updater consumes it as
 * array_slice($this->schema, $mySchema), so a migration's only identity is
 * its POSITION in that array.
 *
 * working-1.6 and dev-branch (1.5.x) fill the same positions with different
 * migrations from index 263 onwards, so a count carried over from a 1.5
 * install does not mean here what it meant there. A fully patched 1.5.10
 * arrives with vValue=277, the updater therefore starts at 277, and 1.6's
 * own indexes 263-276 are silently skipped -- which is where
 * groups.groupInit, the plugins pAnon1-4 renames, the multicastSessions
 * msAnon3/4 renames and the whole userAuths table live.
 *
 * Hand-patching those fourteen steps would fix today and rot tomorrow: the
 * moment either branch adds another index the offset changes again. So
 * instead of encoding "what 1.5 skipped", this compares the live database
 * against a declared manifest of the structure THIS release expects and
 * closes whatever gaps it finds. That makes it equally the repair path for
 * a half-failed update, a restored-from-old-backup database, or any future
 * index skew between the branches.
 *
 * SAFETY
 * ------
 * Strictly additive. It creates missing tables, adds missing columns, and
 * finishes a declared rename that has not been applied yet. It never drops
 * anything, never retypes a column that already exists, and never touches
 * row data. An over-specified or stale manifest therefore cannot destroy
 * anything -- the worst case is that it does nothing.
 *
 * Renames are the one thing that cannot be inferred. A manifest describes
 * the END state, so a renamed column looks exactly like a new column; a
 * purely additive pass would add an empty `pIcon` and strand the data in
 * `pAnon1`. Renames are therefore declared explicitly in the manifest and
 * applied before the column pass.
 *
 * The work is split into a pure plan() and an executing reconcile() so the
 * decision can be inspected without touching the database -- which is what
 * makes the thing testable, and what lets an administrator see exactly what
 * an upgrade is about to restructure before committing to it.
 *
 * @category SchemaReconciler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SchemaReconciler extends FOGBase
{
    /**
     * Errors that mean "the thing is already how we want it".
     *
     * Mirrors the updater's own tolerance list so a reconcile pass is safe
     * to re-run. These are the states we are actively trying to reach, so
     * hitting one is success, not failure.
     *
     * @var array
     */
    private static $_skiperrs = [
        1050, // Table already exists
        1054, // Unknown column
        1060, // Duplicate column name
        1061, // Duplicate key name
        1062, // Duplicate entry
        1091, // Can't DROP; does not exist
    ];

    /**
     * Loads the shipped manifest.
     *
     * @return array
     */
    public static function manifest()
    {
        $file = sprintf(
            '%s%scommons%sschema-expected.php',
            BASEPATH,
            DS,
            DS
        );
        if (!file_exists($file)) {
            return [];
        }
        $manifest = include $file;
        return is_array($manifest) ? $manifest : [];
    }

    /**
     * Reads the whole database structure in one query.
     *
     * DatabaseManager::tableColumns() is memoised per request and documents
     * "empty means don't know", which is the wrong contract here -- this
     * pass has to tell "table absent" from "table unreadable", and it has
     * to see its own CREATE TABLE results immediately. One information_schema
     * read covering every table sidesteps both problems.
     *
     * @return array|null Map of lowercased table => [lowercased columns],
     *                    or null when the structure could not be read.
     */
    public static function snapshot()
    {
        $sql = sprintf(
            'SELECT `TABLE_NAME`, `COLUMN_NAME`'
            . ' FROM `information_schema`.`COLUMNS`'
            . ' WHERE `TABLE_SCHEMA` = %s',
            self::$DB->escape(self::$DB->dbName())
        );
        $res = self::$DB->query($sql);
        if (false !== $res->error) {
            return null;
        }
        $rows = $res->fetch(PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (!is_array($rows)) {
            return null;
        }
        $map = [];
        foreach ($rows as $row) {
            if (!isset($row['TABLE_NAME'], $row['COLUMN_NAME'])) {
                continue;
            }
            $map[strtolower($row['TABLE_NAME'])][] = strtolower(
                $row['COLUMN_NAME']
            );
        }
        return $map;
    }

    /**
     * Works out the statements needed to bring $have up to $manifest.
     *
     * Pure: touches nothing, and carries the effect of each statement into
     * its own working copy of the structure so the plan reads exactly as
     * execution would behave.
     *
     * @param array $manifest Expected structure.
     * @param array $have     Current structure, as snapshot() returns it.
     *
     * @return array Ordered SQL statements.
     */
    public static function plan($manifest, $have)
    {
        $plan = [];
        if (empty($manifest['tables'])) {
            return $plan;
        }

        // Pass 1 -- missing tables. The manifest ships these as
        // CREATE TABLE IF NOT EXISTS, so a stale snapshot cannot hurt.
        foreach ($manifest['tables'] as $table => $def) {
            $key = strtolower($table);
            if (isset($have[$key]) || empty($def['create'])) {
                continue;
            }
            $plan[] = $def['create'];
            // The create carries every column, so the column pass below
            // must see the table as complete rather than re-adding them.
            $have[$key] = array_map(
                'strtolower',
                array_keys($def['columns'] ?? [])
            );
        }

        // Pass 2 -- declared renames, before the column pass, so a renamed
        // column is moved (keeping its data) rather than re-added empty.
        foreach ((array)($manifest['renames'] ?? []) as $rename) {
            if (empty($rename['table'])
                || empty($rename['from'])
                || empty($rename['to'])
                || empty($rename['type'])
            ) {
                continue;
            }
            $key = strtolower($rename['table']);
            if (!isset($have[$key])) {
                continue;
            }
            $from = strtolower($rename['from']);
            $to = strtolower($rename['to']);
            if (in_array($to, $have[$key]) || !in_array($from, $have[$key])) {
                // Already renamed, or the source is gone and the column
                // pass will add the target fresh.
                continue;
            }
            $plan[] = sprintf(
                'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
                $rename['table'],
                $rename['from'],
                $rename['to'],
                $rename['type']
            );
            $have[$key] = array_values(
                array_diff($have[$key], [$from])
            );
            $have[$key][] = $to;
        }

        // Pass 3 -- missing columns. AFTER keeps column order matching a
        // fresh install where it can; it is cosmetic, so a missing anchor
        // just means the column lands at the end.
        foreach ($manifest['tables'] as $table => $def) {
            $key = strtolower($table);
            if (!isset($have[$key]) || empty($def['columns'])) {
                continue;
            }
            $prev = '';
            foreach ($def['columns'] as $column => $type) {
                if (in_array(strtolower($column), $have[$key])) {
                    $prev = $column;
                    continue;
                }
                $plan[] = sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s%s',
                    $table,
                    $column,
                    $type,
                    (
                        $prev && in_array(strtolower($prev), $have[$key]) ?
                        sprintf(' AFTER `%s`', $prev) :
                        ''
                    )
                );
                $have[$key][] = strtolower($column);
                $prev = $column;
            }
        }
        return $plan;
    }

    /**
     * Brings the database up to the structure the manifest describes.
     *
     * Written to the updater's callable contract: returns true on success,
     * or an error string on failure.
     *
     * @param array $manifest Expected structure. Defaults to the shipped
     *                        commons/schema-expected.php.
     *
     * @return bool|string
     */
    public static function reconcile($manifest = null)
    {
        if (null === $manifest) {
            $manifest = self::manifest();
        }
        if (empty($manifest['tables'])) {
            // No manifest shipped. An install without one is simply
            // unrepaired, which is not a reason to fail the update.
            return true;
        }
        $have = self::snapshot();
        if (null === $have) {
            // Could not read information_schema. Firing blind from here
            // could only produce noise, so stand down.
            return true;
        }
        $plan = self::plan($manifest, $have);
        if (!count($plan ?: [])) {
            return true;
        }
        $applied = [];
        $errors = [];
        foreach ($plan as $sql) {
            if (false === self::$DB->query($sql)->error) {
                $applied[] = $sql;
                continue;
            }
            if (in_array(self::$DB->errorCode, self::$_skiperrs)) {
                continue;
            }
            $errors[] = sprintf('%s: %s', self::$DB->error, $sql);
        }
        if (count($applied ?: [])) {
            // Worth a log line: this is structure the normal indexed
            // migrations did not deliver, so a support case needs to be
            // able to see that it was repaired and what changed.
            error_log(
                sprintf(
                    "%s: %d %s\n%s\n",
                    _('Schema reconcile'),
                    count($applied),
                    _('structural repair(s) applied'),
                    implode("\n", $applied)
                ),
                3,
                BASEPATH . 'fog_schema_update_error.log'
            );
        }
        if (count($errors ?: [])) {
            return implode('; ', $errors);
        }
        return true;
    }
}
