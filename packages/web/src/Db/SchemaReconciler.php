<?php
/**
 * Repairs a database whose structure has fallen behind this release.
 *
 * PHP version 7.4+
 *
 * @category SchemaReconciler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Db;

use FOG\Base\FOGBase;

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
 * FOREIGN KEYS
 * ------------
 * planConstraints() is a fourth pass over commons/schema-constraints.php,
 * and it runs AFTER the three above for a reason that was measured rather
 * than assumed: the manifest's `create` strings execute in manifest order,
 * which is not dependency order -- apiTokens precedes users, groupMembers
 * precedes hosts. With constraints inlined into those CREATE statements, 34
 * of 70 tables fail with errno 150. As a separate pass of ALTERs after every
 * table exists, none do. See ADR 0031 decision 4; bin/schema-manifest.php
 * strips any CONSTRAINT clause out of what it snapshots for the same reason.
 *
 * A constraint failure is NOT treated like a structural one. ADD CONSTRAINT
 * validates existing rows, so a server holding an orphan this release did not
 * anticipate gets 1452 -- and returning that as an error would abort the
 * update and strand the server on ?node=schema over data that is otherwise
 * intact. So these are collected, logged loudly and left for
 * bin/fk-orphan-scan.php to diagnose, and the update proceeds. The distinction
 * matters: a missing column breaks the code, a missing constraint only means
 * FOG is still relying on Route::deletemass() alone, which is where it has
 * been for a decade.
 *
 * What is NOT acceptable is silence. The failures go to constraintFailures()
 * as well as the log, because a constraint that can never apply and that
 * nobody is told about is worse than one that was never declared.
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
     * Constraints the last reconcile() could not add, keyed by name.
     *
     * @var array
     */
    private static $_constraintFailures = [];

    /**
     * Errors the constraint pass treats as "already how we want it".
     *
     * 121 is the one that matters: a duplicate constraint NAME. The pass
     * skips constraints it can already see, so this only fires on a race or
     * on a name reused under a different definition, and neither is a reason
     * to fail an upgrade.
     *
     * @var array
     */
    private static $_fkskiperrs = [
        121,  // Duplicate key on write or update (constraint name exists)
        1826, // Duplicate foreign key constraint name
    ];

    /**
     * Loads the shipped constraint map.
     *
     * @return array
     */
    public static function constraints()
    {
        $file = sprintf(
            '%s%scommons%sschema-constraints.php',
            BASEPATH,
            DS,
            DS
        );
        if (!file_exists($file)) {
            return [];
        }
        $map = include $file;
        return is_array($map) ? $map : [];
    }

    /**
     * Constraints the last reconcile() declared but could not add.
     *
     * Each entry is [name, reason]. Read by the schema page and by
     * tests/foreign-keys-reconcile.test.php; empty is the normal state.
     *
     * @return array
     */
    public static function constraintFailures()
    {
        return self::$_constraintFailures;
    }

    /**
     * The foreign keys the database already carries.
     *
     * Names only -- the pass decides by name, because the name encodes the
     * child and column it covers and is unique by construction.
     *
     * @return array|null Lowercased constraint names, or null when
     *                    information_schema could not be read.
     */
    public static function constraintSnapshot()
    {
        $sql = sprintf(
            'SELECT `CONSTRAINT_NAME`'
            . ' FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`'
            . ' WHERE `CONSTRAINT_SCHEMA` = %s',
            self::$DB->escape(self::$DB->dbName())
        );
        $res = self::$DB->query($sql);
        if (false !== $res->error) {
            return null;
        }
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (!is_array($rows)) {
            return null;
        }
        $names = [];
        foreach ($rows as $row) {
            if (isset($row['CONSTRAINT_NAME'])) {
                $names[] = strtolower($row['CONSTRAINT_NAME']);
            }
        }
        return $names;
    }

    /**
     * The constraint name for one relationship.
     *
     * fk_<childTable>_<childColumn>, and not fk_<child>_<parent>:
     * tasks.taskNFSMemberID and tasks.taskLastMemberID both reference
     * nfsGroupMembers and would collide. Child plus column is unique by
     * construction, so the name is derivable from the map with no lookup.
     *
     * @param array $rel One entry from the constraint map.
     *
     * @return string
     */
    public static function constraintName($rel)
    {
        return sprintf('fk_%s_%s', $rel['child'], $rel['column']);
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
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
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
     * Works out the ADD CONSTRAINT statements the database is missing.
     *
     * Pure, like plan(), and separate from it so the two kinds of failure
     * stay separable at execution: a structural statement that fails is an
     * error, a constraint that fails is a report.
     *
     * Skips a relationship whose child or parent table is absent rather than
     * treating it as a gap. Plugin tables are created on install and dropped
     * on uninstall, so "not there" is the normal state for most of this map
     * on most servers -- see ADR 0031 decision 8.
     *
     * @param array $map        Constraint map, as constraints() returns it.
     * @param array $have       Current structure, as snapshot() returns it.
     * @param array $haveFks    Existing constraint names, lowercased.
     *
     * @return array Ordered SQL statements.
     */
    public static function planConstraints($map, $have, $haveFks)
    {
        $plan = [];
        $known = array_flip((array)$haveFks);
        foreach ((array)$map as $rel) {
            if (empty($rel['child'])
                || empty($rel['column'])
                || empty($rel['action'])
                || 'none' === $rel['action']
                || empty($rel['enabled'])
            ) {
                continue;
            }
            $child = strtolower($rel['child']);
            $parent = strtolower((string)($rel['parent'] ?? ''));
            if (!isset($have[$child]) || !isset($have[$parent])) {
                continue;
            }
            if (!in_array(strtolower($rel['column']), $have[$child])
                || !in_array(strtolower((string)$rel['pcolumn']), $have[$parent])
            ) {
                continue;
            }
            $name = self::constraintName($rel);
            if (isset($known[strtolower($name)])) {
                continue;
            }
            // ON UPDATE RESTRICT everywhere. FOG never updates a primary
            // key, and declaring CASCADE would license a rewrite nobody
            // intends to propagate across the schema.
            $plan[] = sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`)'
                . ' REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE RESTRICT',
                $rel['child'],
                $name,
                $rel['column'],
                $rel['parent'],
                $rel['pcolumn'],
                $rel['action']
            );
            $known[strtolower($name)] = true;
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
     * @param array $map      Constraint map. Defaults to the shipped
     *                        commons/schema-constraints.php. Injectable for
     *                        the same reason $manifest is: the constraint
     *                        pass only runs against a real server, so
     *                        without this the one thing a test could not
     *                        reach is whether it runs at all.
     *
     * @return bool|string
     */
    public static function reconcile($manifest = null, $map = null)
    {
        if (null === $manifest) {
            $manifest = self::manifest();
        }
        if (null === $map) {
            $map = self::constraints();
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
        // No early return on an empty structural plan. An up-to-date server
        // is the NORMAL case -- most databases are missing no table and no
        // column -- and returning here would mean the constraint pass below
        // only ever ran on a server that also happened to be missing
        // something else. The constraints would then land nowhere, silently,
        // on almost every install.
        $plan = self::plan($manifest, $have);
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
            // Worth logging: this is structure the normal indexed migrations
            // did not deliver, so a support case needs to see that it was
            // repaired and what changed.
            //
            // Goes to the PHP error log rather than to
            // BASEPATH/fog_schema_update_error.log, for two reasons. That
            // file lives in the WEB ROOT, so anything readable written there
            // is servable -- the updater keeps it safe only by chmod'ing it
            // to 0200 afterward, which also means an administrator cannot
            // read back the record of what their upgrade restructured
            // without root. And plain error_log() is what the rest of the
            // codebase uses; the schema updater's write-only file is the
            // outlier, not the pattern.
            //
            // One line per statement rather than one multi-line blob, so the
            // repairs survive a log handler that splits on newlines and stay
            // greppable.
            error_log(
                sprintf(
                    '%s: %d %s',
                    _('Schema reconcile'),
                    count($applied),
                    _('structural repair(s) applied')
                )
            );
            foreach ($applied as $sql) {
                error_log(sprintf('%s: %s', _('Schema reconcile'), $sql));
            }
        }
        // Pass 4 -- foreign keys, after every table and column exists.
        //
        // Deliberately outside the $errors path above. ADD CONSTRAINT
        // validates existing rows, so an orphan this release did not
        // anticipate returns 1452; failing the update over that would
        // strand the server on ?node=schema with its data intact and no
        // way forward from the browser. Logged and reported instead.
        self::$_constraintFailures = [];
        $haveFks = self::constraintSnapshot();
        if (null !== $haveFks) {
            // Re-read the structure: pass 1 may have created tables and
            // pass 3 added columns that a constraint now depends on, and
            // the snapshot taken above predates both.
            $after = self::snapshot();
            $fkPlan = self::planConstraints(
                $map,
                (null === $after ? $have : $after),
                $haveFks
            );
            $fkApplied = [];
            foreach ($fkPlan as $sql) {
                if (false === self::$DB->query($sql)->error) {
                    $fkApplied[] = $sql;
                    continue;
                }
                if (in_array(self::$DB->errorCode, self::$_fkskiperrs)) {
                    continue;
                }
                preg_match('/CONSTRAINT `([^`]+)`/', $sql, $m);
                // First line, capped. PDODB's error carries the whole
                // failed statement, its params and a second query's debug
                // block, which is useful at a prompt and unreadable as one
                // log line per failure. The errno and the message are what
                // identify it; bin/fk-orphan-scan.php supplies the rest.
                $reason = trim(
                    strtok((string)self::$DB->error, "\n")
                );
                if (strlen($reason) > 200) {
                    $reason = substr($reason, 0, 197) . '...';
                }
                self::$_constraintFailures[] = [
                    'name' => $m[1] ?? $sql,
                    'reason' => $reason,
                ];
            }
            foreach ($fkApplied as $sql) {
                error_log(sprintf('%s: %s', _('Schema reconcile'), $sql));
            }
            if (count(self::$_constraintFailures ?: [])) {
                // Loud, and one line each. A constraint that can never
                // apply and that nobody is told about is worse than one
                // that was never declared: FOG carries on relying on
                // Route::deletemass() alone and no one finds out until the
                // orphan does something visible.
                error_log(
                    sprintf(
                        '%s: %d %s. %s',
                        _('Schema reconcile'),
                        count(self::$_constraintFailures),
                        _('foreign key(s) could not be added'),
                        _('Run bin/fk-orphan-scan.php to find the rows.')
                    )
                );
                foreach (self::$_constraintFailures as $failure) {
                    error_log(
                        sprintf(
                            '%s: %s: %s',
                            _('Schema reconcile'),
                            $failure['name'],
                            $failure['reason']
                        )
                    );
                }
            }
        }

        if (count($errors ?: [])) {
            return implode('; ', $errors);
        }
        return true;
    }
}
