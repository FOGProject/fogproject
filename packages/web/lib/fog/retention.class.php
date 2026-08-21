<?php
/**
 * Ages rows out of the tables that record what happened.
 *
 * PHP version 7.4+
 *
 * @category Retention
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Ages rows out of the tables that record what happened.
 *
 * ONE MECHANISM, NOT FOUR. Four tables need ageing out and they arrived from
 * three directions -- `auditLog` from ADR 0021, `history` and `userTracking`
 * from ADR 0023, `imagingLog` from ADR 0022, which defers here explicitly.
 * Built per-table that would be four sweeps ageing four tables with four
 * bugs, so what exists is a registry of table => setting => date column and
 * one sweep that walks it. A fifth table is a registry entry.
 *
 * `0` MEANS KEEP FOREVER, and it is what every setting holds unless an
 * administrator chooses otherwise -- including on upgrade. ADR 0023 Decision
 * 7: silently deleting on upgrade is wrong for a specific reason rather than
 * a squeamish one, which is that the administrator never chose to hold this
 * data OR to delete it, and some of them are under a legal obligation to
 * retain it. A privacy control that destroys evidence somebody is required to
 * keep is not a privacy win.
 *
 * EVERY DELETION IS AUDITED FIRST, AND REFUSED IF IT CANNOT BE. That is ADR
 * 0021 Decision 10 taken literally: anything that reduces the record must
 * first write to the record, and if the write does not store, the shrink does
 * not happen. It applies in two places -- the sweep itself, and an
 * administrator shortening a window through the settings page. The honest
 * limit, stated in the ADR and worth repeating here: this protects against
 * the trail being turned down quietly through FOG's own UI. It does not
 * protect against `DELETE FROM auditLog` at the MySQL prompt, and nothing at
 * this layer can.
 *
 * @category Retention
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Retention extends FOGBase
{
    /**
     * The audit event a sweep writes.
     */
    const SWEEP = 'audit.retention.sweep';
    /**
     * The audit event a window change writes.
     */
    const WINDOW_CHANGE = 'audit.retention.window';
    /**
     * How many rows one table may lose in a single pass.
     *
     * The first pass after an administrator sets a window on a table that has
     * been filling since the install has years of rows to remove, and an
     * unbounded DELETE holds locks for the length of it. Bounded, the sweep
     * takes several passes to catch up and the table stays usable throughout
     * -- and every pass is audited, so the catch-up is legible rather than
     * one row claiming a million deletions.
     */
    const MAX_PER_PASS = 5000;
    /**
     * The memoized registry, post-hook.
     *
     * @var array|null
     */
    private static $_registry = null;
    /**
     * The tables core itself ages out.
     *
     * `date` is the column the window is measured against, and the choice
     * matters on one of them: imagingLog is aged by ilStartTime rather than
     * ilFinishTime because a task that never finished has no finish time,
     * and ageing by a column that can be empty would keep those rows forever
     * -- which is exactly the set somebody is most likely to be looking for.
     *
     * `children` are rows in another table that point at these and are in no
     * foreign key, so nothing else would remove them. auditChange is the only
     * one today; without it a swept auditLog leaves its change rows behind as
     * permanent orphans holding the values the header explained.
     *
     * @return array table => [setting, date, id, children]
     */
    public static function coreRegistry()
    {
        return [
            'auditLog' => [
                'setting' => 'FOG_AUDIT_RETENTION_DAYS',
                'date' => 'alCreatedTime',
                'id' => 'alID',
                'children' => [
                    ['table' => 'auditChange', 'key' => 'acAuditID'],
                ],
            ],
            'history' => [
                'setting' => 'FOG_HISTORY_RETENTION_DAYS',
                'date' => 'hTime',
                'id' => 'hID',
            ],
            'userTracking' => [
                'setting' => 'FOG_USERTRACKING_RETENTION_DAYS',
                'date' => 'utDateTime',
                'id' => 'utID',
            ],
            'imagingLog' => [
                'setting' => 'FOG_IMAGINGLOG_RETENTION_DAYS',
                'date' => 'ilStartTime',
                'id' => 'ilID',
            ],
        ];
    }
    /**
     * The registry a plugin has had its say on.
     *
     * Same shape as Authorization::registry(): core's entries, then the hook.
     * A plugin that ships its own log table registers it here and gets the
     * sweep, the setting gate and the audit row without writing any of them.
     *
     * @return array
     */
    public static function registry()
    {
        if (null !== self::$_registry) {
            return self::$_registry;
        }
        $registry = self::coreRegistry();
        if (self::$HookManager instanceof HookManager) {
            self::$HookManager->processEvent(
                'RETENTION_REGISTRY_DATA',
                ['registry' => &$registry]
            );
        }

        return self::$_registry = $registry;
    }
    /**
     * The registry entry a setting key belongs to, or null.
     *
     * @param string $key the globalSettings key
     *
     * @return array|null [table, entry] or null
     */
    public static function entryForSetting($key)
    {
        foreach (self::registry() as $table => $entry) {
            if (($entry['setting'] ?? '') === $key) {
                return [$table, $entry];
            }
        }

        return null;
    }
    /**
     * Is this setting key one that governs a retention window?
     *
     * @param string $key the globalSettings key
     *
     * @return bool
     */
    public static function isRetentionSetting($key)
    {
        return null !== self::entryForSetting($key);
    }
    /**
     * Every retention setting key, for the pages that gate on them.
     *
     * @return array
     */
    public static function settingKeys()
    {
        $keys = [];
        foreach (self::registry() as $entry) {
            if (!empty($entry['setting'])) {
                $keys[] = $entry['setting'];
            }
        }

        return $keys;
    }
    /**
     * Does moving from $old to $new days REDUCE what is kept?
     *
     * 0 means keep forever, so it is larger than any number of days and not
     * smaller than all of them -- which is the comparison a naive integer
     * test gets backwards, turning "start deleting after a year" into a
     * growth and letting it through unrecorded.
     *
     * @param mixed $old the current value
     * @param mixed $new the proposed value
     *
     * @return bool
     */
    public static function isShrink($old, $new)
    {
        $old = (int)$old;
        $new = (int)$new;
        if ($new === $old) {
            return false;
        }
        if ($new < 1) {
            // Anything to "keep forever" only ever grows the record.
            return false;
        }
        if ($old < 1) {
            // Forever to a bounded window: the sharpest shrink there is.
            return true;
        }

        return $new < $old;
    }
    /**
     * May this retention window change go ahead?
     *
     * ADR 0021 Decision 10, and the HARD constraint behind it: anything that
     * reduces the record must first be written to the record, and if that
     * write cannot happen the reduction does not either. A change that grows
     * the window is recorded on the same terms but never blocked -- refusing
     * it would protect nothing and would leave an administrator unable to
     * turn retention OFF while the audit table was unwritable.
     *
     * @param string $key the globalSettings key
     * @param mixed  $old the current value
     * @param mixed  $new the proposed value
     *
     * @return bool false only when a shrink could not be recorded
     */
    public static function permitSettingChange($key, $old, $new)
    {
        $found = self::entryForSetting($key);
        if (null === $found) {
            return true;
        }
        if ((int)$old === (int)$new) {
            return true;
        }
        $shrink = self::isShrink($old, $new);
        $audit = Audit::record([
            'type' => self::WINDOW_CHANGE,
            'subjectType' => 'setting',
            'subjectLabel' => $key,
            'permission' => 'audit.manage',
            'renderable' => 1
        ]);
        $stored = false;
        if ($audit) {
            // Through the change mechanism rather than as a sentence: the
            // reader builds the sentence, in its own locale, from the parts
            // (ADR 0020 Decision 5). It also puts the old and new windows in
            // the same columns every other before/after lands in.
            $stored = Audit::changes(
                $audit,
                'Setting',
                (int)$audit->get('id'),
                [$key => [(int)$old, (int)$new]]
            ) > 0;
        }
        if ($shrink && !$stored) {
            return false;
        }

        return true;
    }
    /**
     * Ages out every registered table whose window is set.
     *
     * Counts before deleting, because Decision 10 puts the audit row BEFORE
     * the delete and the row has to say how many. A table with nothing to
     * remove writes no row at all -- an hourly "deleted 0" would bury the
     * passes that did something under the ones that did not.
     *
     * @return array table => rows removed, for the caller's log line
     */
    public static function sweep()
    {
        $removed = [];
        foreach (self::registry() as $table => $entry) {
            $days = (int)self::getSetting($entry['setting'] ?? '');
            if ($days < 1) {
                continue;
            }
            $cutoff = self::niceDate('-' . $days . ' days')
                ->format('Y-m-d H:i:s');
            $pass = self::_pass($table, $entry, $cutoff);
            if ($pass['count'] < 1) {
                continue;
            }
            $count = $pass['count'];
            $audit = Audit::record([
                'type' => self::SWEEP,
                'createdBy' => Audit::MACHINE_ACTOR,
                'authSource' => 'retention',
                'subjectType' => strtolower($table),
                'subjectLabel' => $table,
                'permission' => 'audit.manage',
                'affectedCount' => $count,
                'renderable' => 1,
                'text' => $cutoff
            ]);
            if (!$audit) {
                // The refusal. Nothing is deleted this pass; the next pass
                // tries again, and until it succeeds the tables grow rather
                // than shrinking unrecorded.
                $removed[$table] = false;
                continue;
            }
            $removed[$table] = self::_delete($table, $entry, $cutoff, $pass['max']);
        }

        return $removed;
    }
    /**
     * The rows this pass would remove: how many, and the highest id in them.
     *
     * One query for both, and both come from the SAME bounded set -- which is
     * what makes the audit row's count the truth about the delete that
     * follows rather than a number taken a moment earlier from a different
     * set. The id boundary is then what both DELETEs below are cut to, so a
     * parent and its children always go together.
     *
     * @param string $table  the table name
     * @param array  $entry  its registry entry
     * @param string $cutoff 'Y-m-d H:i:s'
     *
     * @return array ['count' => int, 'max' => int]
     */
    private static function _pass($table, array $entry, $cutoff)
    {
        // LIMIT inside the derived table rather than inside an IN(...):
        // MySQL rejects LIMIT in an IN subquery outright ("This version of
        // MySQL doesn't yet support LIMIT & IN/ALL/ANY/SOME subquery"), and a
        // sweep that only fails on some servers is worse than one that fails
        // on all of them.
        $sql = sprintf(
            'SELECT COUNT(*) AS `c`, MAX(`t`.`%s`) AS `m` FROM '
            . '(SELECT `%s` FROM `%s` WHERE `%s` < :cutoff '
            . 'ORDER BY `%s` ASC LIMIT %d) `t`',
            self::_ident($entry['id']),
            self::_ident($entry['id']),
            self::_ident($table),
            self::_ident($entry['date']),
            self::_ident($entry['id']),
            self::MAX_PER_PASS
        );
        $row = self::$DB->query($sql, [], [':cutoff' => $cutoff])
            ->fetch()
            ->get();

        return [
            'count' => (int)($row['c'] ?? 0),
            'max' => (int)($row['m'] ?? 0)
        ];
    }
    /**
     * Removes the counted rows, children first.
     *
     * Children before parents: a child row is identified by the join to its
     * parent, so removing the parent first leaves it unfindable and therefore
     * permanent. auditChange is in no foreign key -- deliberately, so that
     * dropping a constraint cannot cascade the audit trail away -- which
     * means nothing else would ever collect it.
     *
     * Both statements are cut to the same id boundary, so a header and its
     * change rows can never be split across two passes.
     *
     * @param string $table  the table name
     * @param array  $entry  its registry entry
     * @param string $cutoff 'Y-m-d H:i:s'
     * @param int    $maxId  the highest id this pass removes
     *
     * @return int rows removed from the parent table
     */
    private static function _delete($table, array $entry, $cutoff, $maxId)
    {
        $parent = self::_ident($table);
        $date = self::_ident($entry['date']);
        $id = self::_ident($entry['id']);
        foreach ((array)($entry['children'] ?? []) as $child) {
            $sql = sprintf(
                'DELETE `c` FROM `%s` `c` JOIN `%s` `p` ON `c`.`%s` = `p`.`%s`'
                . ' WHERE `p`.`%s` < :cutoff AND `p`.`%s` <= :maxid',
                self::_ident($child['table']),
                $parent,
                self::_ident($child['key']),
                $id,
                $date,
                $id
            );
            self::$DB->query(
                $sql,
                [],
                [':cutoff' => $cutoff, ':maxid' => $maxId]
            );
        }
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `%s` < :cutoff AND `%s` <= :maxid',
            $parent,
            $date,
            $id
        );
        self::$DB->query(
            $sql,
            [],
            [':cutoff' => $cutoff, ':maxid' => $maxId]
        );

        return (int)self::$DB->affectedRows();
    }
    /**
     * Rejects anything that is not a plain identifier.
     *
     * Table and column names cannot be bound, and one source of them is a
     * plugin's hook contribution -- so they are checked rather than trusted.
     * A backtick or a space here would be a plugin handing FOG a DELETE of
     * its choosing, run by the daemon, against every table in the database.
     *
     * @param string $name the identifier
     *
     * @return string
     * @throws \Exception when it is not one
     */
    private static function _ident($name)
    {
        $name = (string)$name;
        if (1 !== preg_match('#^[A-Za-z0-9_]+$#', $name)) {
            throw new \Exception(
                sprintf('%s: %s', _('Invalid retention identifier'), $name)
            );
        }

        return $name;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Retention', 'Retention');
