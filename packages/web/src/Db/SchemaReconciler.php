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
use FOG\Audit\Audit;

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
     * The foreign keys the database already carries, and what each one says.
     *
     * Keyed by lowercased constraint name, because the name encodes the child
     * table and column it covers and is unique by construction. The value is
     * the rest of the declaration -- referenced table, referenced column and
     * ON DELETE rule -- which is what lets planConstraints() notice that a
     * constraint the database holds no longer says what the map says.
     *
     * Reading names alone was enough while the map only ever grew. It stopped
     * being enough the moment an entry's action was corrected: the name does
     * not encode the action, so a constraint created ON DELETE CASCADE looked
     * identical to the SET NULL the map had since changed to, and the
     * reconciler skipped it forever. See ADR 0031 on the map being normative.
     *
     * A composite foreign key would return one row per column; FOG declares
     * none, and the last row would win. Worth knowing before adding one.
     *
     * @return array|null name => [parent, pcolumn, action], or null when
     *                    information_schema could not be read.
     */
    public static function constraintSnapshot()
    {
        $db = self::$DB->escape(self::$DB->dbName());
        $sql = 'SELECT `rc`.`CONSTRAINT_NAME`, `rc`.`DELETE_RULE`,'
            . ' `rc`.`REFERENCED_TABLE_NAME`, `kcu`.`REFERENCED_COLUMN_NAME`'
            . ' FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` `rc`'
            . ' JOIN `information_schema`.`KEY_COLUMN_USAGE` `kcu`'
            . ' ON `kcu`.`CONSTRAINT_SCHEMA` = `rc`.`CONSTRAINT_SCHEMA`'
            . ' AND `kcu`.`CONSTRAINT_NAME` = `rc`.`CONSTRAINT_NAME`'
            . ' AND `kcu`.`TABLE_NAME` = `rc`.`TABLE_NAME`'
            . ' WHERE `rc`.`CONSTRAINT_SCHEMA` = ' . $db;
        $res = self::$DB->query($sql);
        if (false !== $res->error) {
            return null;
        }
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (!is_array($rows)) {
            return null;
        }
        $found = [];
        foreach ($rows as $row) {
            if (!isset($row['CONSTRAINT_NAME'])) {
                continue;
            }
            $found[strtolower($row['CONSTRAINT_NAME'])] = [
                'parent' => strtolower((string)($row['REFERENCED_TABLE_NAME'] ?? '')),
                'pcolumn' => strtolower((string)($row['REFERENCED_COLUMN_NAME'] ?? '')),
                'action' => strtoupper((string)($row['DELETE_RULE'] ?? '')),
            ];
        }
        return $found;
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
     * Works out the statements that bring the database's foreign keys into
     * line with the map.
     *
     * Pure, like plan(), and separate from it so the two kinds of failure
     * stay separable at execution: a structural statement that fails is an
     * error, a constraint that fails is a report.
     *
     * Three outcomes per relationship:
     *
     * - the database has no constraint of that name, and the map wants one:
     *   ADD CONSTRAINT;
     * - the database has one that no longer says what the map says (a
     *   different action, or a different parent): DROP then ADD;
     * - the database has one the map has retired (`enabled` false, or
     *   `action` none): DROP.
     *
     * That last pair is what makes the map normative rather than merely
     * additive. Before it, correcting an entry's action was a no-op on every
     * server that had already applied the old one -- the name does not encode
     * the action, so the pass saw the name, called it done, and the database
     * kept the wrong rule forever. `nfsGroupMembers.ngmGroupID` shipped
     * CASCADE and had to become SET NULL; that is what found this.
     *
     * The only constraints it will ever drop are ones carrying the name
     * constraintName() generates, for a relationship the map lists. A
     * constraint an administrator added by hand does not carry that name and
     * is never touched.
     *
     * Skips a relationship whose child or parent table is absent rather than
     * treating it as a gap. Plugin tables are created on install and dropped
     * on uninstall, so "not there" is the normal state for most of this map
     * on most servers -- see ADR 0031 decision 8.
     *
     * @param array $map        Constraint map, as constraints() returns it.
     * @param array $have       Current structure, as snapshot() returns it.
     * @param array $haveFks    Existing constraints, as constraintSnapshot()
     *                          returns them: name => [parent, pcolumn,
     *                          action].
     * @param mixed $group      When given, only relationships carrying this
     *                          `group` are eligible to be ADDED. Retirements
     *                          and corrections are NEVER filtered: a wrong
     *                          declaration has to be fixable from any step.
     *
     * @return array Ordered SQL statements.
     */
    public static function planConstraints(
        $map,
        $have,
        $haveFks,
        $group = null,
        $nullable = null
    ) {
        $plan = [];
        $known = [];
        foreach ((array)$haveFks as $fkName => $fkDef) {
            $known[strtolower((string)$fkName)] = is_array($fkDef) ? $fkDef : [];
        }
        $done = [];
        foreach ((array)$map as $rel) {
            if (empty($rel['child']) || empty($rel['column'])) {
                continue;
            }
            $name = self::constraintName($rel);
            $lname = strtolower($name);
            if (isset($done[$lname])) {
                continue;
            }
            // `declared` is what the map says should exist at all;
            // `eligible` is whether THIS call may create it. They are
            // separate on purpose: a filtered call must not read "not my
            // group" as "retired" and drop another group's constraints.
            $declared = !empty($rel['enabled'])
                && !empty($rel['action'])
                && 'none' !== $rel['action'];
            $eligible = $declared
                && (null === $group
                    || (isset($rel['group']) && $rel['group'] === $group));
            $child = strtolower($rel['child']);
            $parent = strtolower((string)($rel['parent'] ?? ''));

            // A constraint the database already holds under this name. Keep
            // it only if it still says exactly what the map says; the
            // comparison is on the declaration, not on the name.
            if (isset($known[$lname])) {
                $existing = $known[$lname];
                $matches = $declared
                    && ($existing['parent'] ?? '') === $parent
                    && ($existing['pcolumn'] ?? '')
                        === strtolower((string)($rel['pcolumn'] ?? ''))
                    && ($existing['action'] ?? '')
                        === strtoupper((string)$rel['action']);
                if ($matches) {
                    $done[$lname] = true;
                    continue;
                }
                if ($declared && !$eligible) {
                    // Wrong declaration, but not this call's group to fix.
                    // Dropping without re-adding would open a window with
                    // no constraint at all; the unfiltered reconcile that
                    // follows every update run corrects it instead.
                    $done[$lname] = true;
                    continue;
                }
                $plan[] = sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    $rel['child'],
                    $name
                );
                unset($known[$lname]);
                if (!$declared) {
                    $done[$lname] = true;
                    continue;
                }
            } elseif (!$eligible) {
                continue;
            }

            if (!isset($have[$child]) || !isset($have[$parent])) {
                $done[$lname] = true;
                continue;
            }
            if (!in_array(strtolower($rel['column']), $have[$child])
                || !in_array(strtolower((string)$rel['pcolumn']), $have[$parent])
            ) {
                $done[$lname] = true;
                continue;
            }
            // A SET NULL constraint over a NOT NULL column is not a
            // failure, it is a PRECONDITION that has not landed yet, and
            // InnoDB refuses it outright with errno 150 "incorrectly
            // formed" -- no rows involved, so the orphan scanner the
            // failure log points at reports nothing and the trail ends.
            //
            // The preparation -- make the column nullable, convert the `0`
            // sentinel -- lives in the step that owns the group, and for a
            // plugin that is the PLUGIN's own schema(), which runs only
            // when the plugin is installed. planConstraints() decides a
            // plugin relationship is applicable from the child TABLE being
            // present, and on an upgrade from 1.5 that is not the same
            // question: 1.5's plugins lived in the web tree and left their
            // tables behind, so `location` exists, holds rows, and has had
            // no 1.6 step run against it.
            //
            // Verified on a real 1.5.10 database (2079 hosts, schema 278):
            // fk_location_lStorageNodeID was the ONE constraint of 80 that
            // an otherwise clean upgrade could not add, with zero orphans
            // and `plugins`.`pInstalled` empty for `location`.
            //
            // So it is skipped, not attempted. The constraint lands when
            // the plugin is installed and its own step has prepared the
            // column, which is the order the design already intends.
            if ('SET NULL' === strtoupper((string)$rel['action'])
                && is_array($nullable)
                && !in_array(
                    strtolower($rel['column']),
                    (array)($nullable[$child] ?? [])
                )
            ) {
                $done[$lname] = true;
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
            $done[$lname] = true;
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
        // Re-snapshots inside applyConstraints(): pass 1 may have created
        // tables and pass 3 added columns that a constraint now depends on,
        // and the structure read at the top of this method predates both.
        //
        // Deliberately outside the $errors path above. ADD CONSTRAINT
        // validates existing rows, so an orphan this release did not
        // anticipate returns 1452; failing the update over that would
        // strand the server on ?node=schema with its data intact and no
        // way forward from the browser. Logged and reported instead.
        self::applyConstraints(null, $map);

        // Reports, never repairs -- GH-1542. The four passes above all ask
        // "is this thing PRESENT?" and create it when it is not. Nothing
        // asks whether a column or an index that IS present has the right
        // SHAPE, so drift is invisible: a column whose type no longer
        // matches its parent's refuses its foreign key with errno 1005 on
        // every upgrade from now on, and a UNIQUE index that is absent for
        // any reason is never put back, taking its guarantee with it
        // silently.
        //
        // Repairing either is a bigger decision than this pass is allowed to
        // make -- MODIFY COLUMN on a populated column can truncate, and
        // ADD UNIQUE on a table that has since acquired duplicates fails
        // outright and needs a sweep in front of it, in the shape ADR 0031
        // decision 8 gives the constraint groups. So this turns an invisible
        // condition into a readable one and settles how often it actually
        // happens before anything starts issuing ALTERs.
        self::reportShapeDrift($manifest);

        if (count($errors ?: [])) {
            return implode('; ', $errors);
        }
        return true;
    }

    /**
     * Logs columns and UNIQUE indexes whose shape differs from the manifest.
     *
     * Never alters anything and never fails the update: an upgrade that
     * refused to finish because a column type had drifted would strand the
     * server on ?node=schema over data that is otherwise intact.
     *
     * @param array|null $manifest Expected structure; defaults to the
     *                             shipped one.
     *
     * @return array The drift found, for a caller that wants it.
     */
    public static function reportShapeDrift($manifest = null)
    {
        $drift = self::shapeDrift($manifest);
        foreach ($drift as $d) {
            error_log(
                sprintf(
                    '%s: %s.%s %s',
                    _('Schema shape'),
                    $d['table'],
                    $d['name'],
                    'column' === $d['kind']
                        ? sprintf(
                            _('is `%s` but the manifest says `%s`'),
                            $d['actual'],
                            $d['expected']
                        )
                        : _('is missing -- a UNIQUE index the manifest declares')
                )
            );
        }
        if (count($drift)) {
            error_log(
                sprintf(
                    '%s: %d %s',
                    _('Schema shape'),
                    count($drift),
                    _(
                        'difference(s) from the manifest. Nothing was'
                        . ' changed -- these are reported, not repaired.'
                    )
                )
            );
        }
        return $drift;
    }

    /**
     * Columns and UNIQUE indexes whose live shape differs from the manifest.
     *
     * Pure apart from the two reads. Compares:
     *
     * - a column's TYPE and its nullability. Not its DEFAULT: a drifted
     *   default changes what a new row gets and nothing else, while a
     *   drifted type is what refuses a foreign key, and mixing the two would
     *   bury the second in a list of the first. Defaults also round-trip
     *   badly through information_schema -- the quoting differs by server
     *   version -- so comparing them produces findings that are noise.
     * - UNIQUE indexes the manifest's CREATE statement declares. Non-unique
     *   ones are deliberately not reported: a missing KEY costs speed, a
     *   missing UNIQUE KEY costs a guarantee, and only the second is a
     *   correctness question.
     *
     * A table or column that is ABSENT is not drift -- plan() creates those,
     * and reporting them here would double every finding on a server that is
     * simply behind.
     *
     * @param array|null $manifest Expected structure.
     *
     * @return array list of ['table','kind','name','expected','actual']
     */
    public static function shapeDrift($manifest = null)
    {
        if (null === $manifest) {
            $manifest = self::manifest();
        }
        $tables = (array)($manifest['tables'] ?? []);
        if (!count($tables)) {
            return [];
        }
        $db = self::$DB->dbName();

        $res = self::$DB->query(
            sprintf(
                'SELECT `TABLE_NAME`, `COLUMN_NAME`, `COLUMN_TYPE`,'
                . ' `IS_NULLABLE` FROM `information_schema`.`COLUMNS`'
                . ' WHERE `TABLE_SCHEMA` = %s',
                self::$DB->escape($db)
            )
        );
        if (false !== $res->error) {
            return [];
        }
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (!is_array($rows)) {
            return [];
        }
        $live = [];
        foreach ($rows as $row) {
            $live[strtolower((string)$row['TABLE_NAME'])]
                [strtolower((string)$row['COLUMN_NAME'])] = [
                    'type' => strtolower((string)$row['COLUMN_TYPE']),
                    'null' => 'YES' === strtoupper((string)$row['IS_NULLABLE']),
                ];
        }

        $res = self::$DB->query(
            sprintf(
                'SELECT `TABLE_NAME`, `INDEX_NAME` FROM'
                . ' `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` ='
                . ' %s AND `NON_UNIQUE` = 0',
                self::$DB->escape($db)
            )
        );
        $liveIdx = [];
        if (false === $res->error) {
            $idxRows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            foreach ((array)$idxRows as $row) {
                $liveIdx[strtolower((string)$row['TABLE_NAME'])]
                    [strtolower((string)$row['INDEX_NAME'])] = true;
            }
        }

        $drift = [];
        foreach ($tables as $table => $spec) {
            $lt = strtolower((string)$table);
            if (!isset($live[$lt])) {
                // Absent entirely. plan() creates it; not drift.
                continue;
            }
            foreach ((array)($spec['columns'] ?? []) as $col => $decl) {
                $lc = strtolower((string)$col);
                if (!isset($live[$lt][$lc])) {
                    continue;
                }
                $want = self::_declShape((string)$decl);
                $have = $live[$lt][$lc];
                if ($want['type'] === $have['type']
                    && $want['null'] === $have['null']
                ) {
                    continue;
                }
                $drift[] = [
                    'table' => $table,
                    'kind' => 'column',
                    'name' => $col,
                    'expected' => $want['type']
                        . ($want['null'] ? ' NULL' : ' NOT NULL'),
                    'actual' => $have['type']
                        . ($have['null'] ? ' NULL' : ' NOT NULL'),
                ];
            }
            $create = (string)($spec['create'] ?? '');
            if ('' === $create) {
                continue;
            }
            preg_match_all(
                '/UNIQUE KEY `([^`]+)`/',
                $create,
                $m
            );
            foreach ($m[1] as $idx) {
                if (isset($liveIdx[$lt][strtolower($idx)])) {
                    continue;
                }
                $drift[] = [
                    'table' => $table,
                    'kind' => 'unique',
                    'name' => $idx,
                    'expected' => 'UNIQUE',
                    'actual' => 'absent',
                ];
            }
        }
        return $drift;
    }

    /**
     * The type and nullability a manifest column declaration describes.
     *
     * The declaration is what SHOW CREATE TABLE emits for the column minus
     * its name -- `int(11) NOT NULL`, `longtext NOT NULL DEFAULT ''`. The
     * type is everything up to the first NULL/NOT NULL/DEFAULT keyword, so a
     * multi-word type (`int(10) unsigned`) survives intact.
     *
     * Nullability follows SQL's own default: a column is nullable unless the
     * declaration says NOT NULL. Getting that backwards would report every
     * nullable column in the manifest as drifted.
     *
     * @param string $decl manifest column declaration
     *
     * @return array ['type' => string, 'null' => bool]
     */
    private static function _declShape($decl)
    {
        $decl = trim($decl);
        $notNull = (bool)preg_match('/\bNOT\s+NULL\b/i', $decl);
        $type = preg_split(
            '/\s+(?:NOT\s+NULL|NULL|DEFAULT|AUTO_INCREMENT|COMMENT|'
            . 'CHARACTER\s+SET|COLLATE|ON\s+UPDATE|GENERATED)\b/i',
            $decl
        );
        return [
            'type' => strtolower(trim((string)($type[0] ?? $decl))),
            'null' => !$notNull,
        ];
    }
    /**
     * Declares the foreign keys the map has enabled.
     *
     * Split out of reconcile() so an indexed schema step can land one
     * group without waiting for a structural repair to happen to run --
     * ADR 0031 lands the 87 constraints group by group, and each group's
     * step calls this. reconcile() then calls it again after every update
     * as the standing repair, which is what catches a constraint that was
     * dropped by hand, lost to a restore, or refused on an earlier run
     * because the rows were not clean yet.
     *
     * NEVER RETURNS AN ERROR, and that is the whole point. ADD CONSTRAINT
     * validates existing rows, so a server holding an orphan this release
     * did not anticipate answers 1452. Returning that to the updater would
     * abort the run and strand the server on ?node=schema over data that is
     * otherwise intact, with no way forward from the browser. A missing
     * constraint only means FOG is still relying on Route::deletemass()
     * alone, which is where it has been for a decade. So the failure is
     * collected into constraintFailures(), logged loudly with a pointer at
     * the scanner that can find the rows, and the update proceeds.
     *
     * Idempotent: planConstraints() skips any constraint whose declaration
     * already matches the map, so calling this twice in one update run --
     * which is exactly what a group's step plus the reconcile after it does
     * -- plans nothing the second time.
     *
     * A SCHEMA STEP MUST PASS ITS OWN GROUP. Without one, the first
     * constraint step reached in an upgrade applies every enabled
     * relationship in the map, including groups whose preconditions later
     * steps have not created yet -- a RESTRICT over a column still holding
     * the `0` sentinel, say. Nothing breaks, because a refusal is reported
     * rather than returned, but it logs a failure on every upgrade for a
     * constraint that the correct step then applies cleanly, and noise like
     * that is how people learn to stop reading the log.
     *
     * The trailing reconcile in SchemaUpdaterPage passes null, deliberately.
     * It runs after the whole update loop, so every precondition has landed
     * by then, and it is what converges a server that somehow missed a
     * step's constraints.
     *
     * @param mixed      $group Only add relationships carrying this `group`.
     *                          Null adds everything enabled. Retirements and
     *                          corrections are never filtered.
     * @param array|null $map   Relationship map; defaults to the shipped one.
     *
     * @return bool always true
     */
    public static function applyConstraints($group = null, $map = null)
    {
        if (null === $map) {
            $map = self::constraints();
        }
        self::$_constraintFailures = [];
        $haveFks = self::constraintSnapshot();
        $have = self::snapshot();
        if (null !== $haveFks && null !== $have) {
            $fkPlan = self::planConstraints(
                $map,
                $have,
                $haveFks,
                $group,
                self::nullableSnapshot()
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
                // 1452 and 1005/150 are different problems with different
                // remedies, and until now both were reported with "Run
                // bin/fk-orphan-scan.php to find the rows". For a structural
                // refusal that scan returns zero rows and the admin has
                // nowhere to go next -- the worst shape a diagnostic can
                // take, because it looks like an answer.
                //
                //   1452  rows point at a parent that is not there. The scan
                //         finds them and the sweep can remove them.
                //   1005  the column itself cannot carry the constraint --
                //   (150) its type differs from the parent's, or a SET NULL
                //         is declared over a NOT NULL column. No row is
                //         involved and no cleanup helps.
                $structural = 1005 === (int)self::$DB->errorCode;
                self::$_constraintFailures[] = [
                    'name' => $m[1] ?? $sql,
                    'reason' => $reason,
                    'structural' => $structural,
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
                $structuralCount = count(
                    array_filter(
                        self::$_constraintFailures,
                        function ($failure) {
                            return !empty($failure['structural']);
                        }
                    )
                );
                error_log(
                    sprintf(
                        '%s: %d %s. %s',
                        _('Schema reconcile'),
                        count(self::$_constraintFailures),
                        _('foreign key(s) could not be added'),
                        $structuralCount === count(self::$_constraintFailures)
                            ? _(
                                'All are structural (column type or'
                                . ' nullability), not orphan rows --'
                                . ' bin/fk-orphan-scan.php will report none.'
                            )
                            : _('Run bin/fk-orphan-scan.php to find the rows.')
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
        return true;
    }

    /**
     * Works out the statements that make a group's orphans constrainable.
     *
     * Pure. `ADD CONSTRAINT` against a table holding orphans returns 1452
     * and the constraint is simply never created, so a group whose rows
     * have not been swept declares integrity it does not have. ADR 0031
     * decision 8 makes the sweep part of applying a group rather than a
     * judgment call at each call site.
     *
     * The repair each orphan gets is decided by the COLUMN, not by the
     * action:
     *
     * - nullable  -> `UPDATE ... SET col = NULL`. The row survives and the
     *                reference becomes an honest "none". This is the only
     *                shape that keeps data.
     * - NOT NULL  -> `DELETE`. There is no value that makes the row valid;
     *                it points at a parent that does not exist and the
     *                column forbids saying so.
     *
     * Deciding on the action instead would be wrong in both directions: a
     * CASCADE relationship over a nullable column would delete rows it
     * could have kept, and a SET NULL one over a NOT NULL column would
     * write a value the column rejects.
     *
     * Nullability is read from the live server, never from
     * commons/schema-expected.php. The manifest describes the 67 core
     * tables only, so a plugin column looked up there comes back as
     * "not found" -- which would silently turn every plugin sweep into a
     * DELETE.
     *
     * Only enabled relationships carrying $group are considered. Audit and
     * history relationships are `none` or disabled by construction (ADR
     * 0021 -- the trail outlives its subject), so no call can reach one.
     *
     * @param array $map      Relationship map.
     * @param array $have     Structure, as snapshot() returns it.
     * @param array $nullable table => [column, ...] that accept NULL, as
     *                        nullableSnapshot() returns it.
     * @param mixed $group    Group to sweep. A sweep deletes rows, so it is
     *                        opt-in per group and has no "everything" mode:
     *                        the match below is isset() plus ===, and
     *                        isset() is false for null, so a null group
     *                        selects nothing without needing a guard.
     *
     * @return array Ordered SQL statements.
     */
    public static function planSweep($map, $have, $nullable, $group = null)
    {
        $plan = [];
        foreach ((array)$map as $rel) {
            if (empty($rel['child']) || empty($rel['column'])) {
                continue;
            }
            if (empty($rel['enabled'])
                || empty($rel['action'])
                || 'none' === $rel['action']
            ) {
                continue;
            }
            if (!isset($rel['group']) || $rel['group'] !== $group) {
                continue;
            }
            $child = strtolower($rel['child']);
            $parent = strtolower((string)($rel['parent'] ?? ''));
            $column = strtolower($rel['column']);
            $pcolumn = strtolower((string)($rel['pcolumn'] ?? ''));
            if (!isset($have[$child]) || !isset($have[$parent])) {
                continue;
            }
            if (!in_array($column, $have[$child])
                || !in_array($pcolumn, $have[$parent])
            ) {
                continue;
            }
            // The subquery, not a LEFT JOIN: MariaDB cannot DELETE from or
            // UPDATE a table it is also joining in the same statement
            // (error 1093), and the sentinel `0` has to be excluded by
            // hand because it is not NULL and so is not exempt from the
            // constraint either.
            $where = sprintf(
                '`%s` IS NOT NULL AND `%s` <> 0 AND `%s` NOT IN'
                . ' (SELECT `%s` FROM (SELECT `%s` FROM `%s`) `p`)',
                $rel['column'],
                $rel['column'],
                $rel['column'],
                $rel['pcolumn'],
                $rel['pcolumn'],
                $rel['parent']
            );
            $isNullable = isset($nullable[$child])
                && in_array($column, $nullable[$child]);
            $plan[] = $isNullable
                ? sprintf(
                    'UPDATE `%s` SET `%s` = NULL WHERE %s',
                    $rel['child'],
                    $rel['column'],
                    $where
                )
                : sprintf(
                    'DELETE FROM `%s` WHERE %s',
                    $rel['child'],
                    $where
                );
        }
        return $plan;
    }

    /**
     * The columns the database accepts NULL in.
     *
     * Separate from snapshot() because that one is consumed by plan() and
     * planConstraints(), neither of which cares, and widening its shape
     * would touch every test that builds one by hand.
     *
     * @return array|null table => [column, ...], or null when
     *                    information_schema could not be read.
     */
    public static function nullableSnapshot()
    {
        $sql = sprintf(
            'SELECT `TABLE_NAME`, `COLUMN_NAME`'
            . ' FROM `information_schema`.`COLUMNS`'
            . ' WHERE `TABLE_SCHEMA` = %s AND `IS_NULLABLE` = \'YES\'',
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
     * Removes the rows that would refuse a group's constraints.
     *
     * Runs planSweep() against the live server and reports what it did.
     * Every statement is logged with its row count, whether or not it
     * changed anything, because "swept 0" and "never ran" are the two
     * readings of a silent sweep and they call for different responses.
     *
     * Destructive by design and by decision: see planSweep() for which
     * orphans are deleted rather than nulled, and ADR 0031 decision 8 for
     * why an install that skips this cannot add its constraints at all.
     *
     * Written to the updater's callable contract -- true, or an error
     * string -- so a schema step can be the bare call.
     *
     * @param mixed      $group Group to sweep. Required; null sweeps
     *                          nothing.
     * @param array|null $map   Relationship map; defaults to the shipped
     *                          one.
     *
     * @return bool|string
     */
    public static function sweepOrphans($group = null, $map = null)
    {
        if (null === $group) {
            return true;
        }
        if (null === $map) {
            $map = self::constraints();
        }
        $have = self::snapshot();
        $nullable = self::nullableSnapshot();
        if (null === $have || null === $nullable) {
            return _('Could not read the database structure');
        }
        $total = 0;
        $parts = [];
        foreach (self::planSweep($map, $have, $nullable, $group) as $sql) {
            $res = self::$DB->query($sql);
            if (false !== $res->error) {
                return self::$DB->error;
            }
            $rows = (int)self::$DB->affectedRows();
            $total += $rows;
            // Logged whether or not it moved anything: "swept 0" and
            // "never ran" are the two readings of a silent sweep and they
            // call for different responses.
            error_log(
                sprintf(
                    '%s (%s): %d %s: %s',
                    _('Schema sweep'),
                    (string)$group,
                    $rows,
                    _('row(s)'),
                    $sql
                )
            );
            if ($rows > 0) {
                preg_match('/`([^`]+)`/', $sql, $m);
                $parts[] = sprintf('%s: %d', $m[1] ?? '?', $rows);
            }
        }
        if ($total > 0) {
            Audit::record(
                [
                    'type' => 'schema.orphan.sweep',
                    'subjectType' => 'schema',
                    'subjectID' => 0,
                    'subjectLabel' => _('Foreign key preparation'),
                    'outcome' => Audit::ALLOWED,
                    'affectedCount' => $total,
                    'renderable' => 1,
                    'text' => sprintf(
                        /* translators: %1$d row count, %2$s group name,
                           %3$s per-table breakdown */
                        _('Removed or cleared %1$d row(s) in the "%2$s" '
                        . 'group whose parent record no longer existed, so '
                        . 'that foreign key constraints could be declared. '
                        . 'Per table: %3$s'),
                        $total,
                        (string)$group,
                        implode(', ', $parts)
                    ),
                ]
            );
        }
        return true;
    }
}
