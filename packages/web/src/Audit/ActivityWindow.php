<?php
/**
 * Everything that ran in a time window, across every work-item table.
 *
 * PHP version 7.4+
 *
 * @category ActivityWindow
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

use FOG\Base\FOGBase;
use FOG\Items\TaskState;

/**
 * Everything that ran in a time window, across every work-item table.
 *
 * ADR 0022 decision 4. FOG has five tables that record a unit of work with a
 * lifecycle -- `tasks`, `snapinJobs`, `snapinTasks`, `multicastSessions` and
 * `fileDeleteQueue` -- and answering "what ran in the last hour" meant asking
 * each one separately in its own vocabulary. This asks once.
 *
 * IT IS A PROJECTION, NOT AN ABSTRACTION, and that distinction is the whole
 * decision. ADR 0022 rejected a shared span base class: the five tables share
 * a SHAPE, not a concept, and unifying on shape would give a queue row and a
 * history row one setter -- so every write to a history row would look like a
 * state transition to whatever polls for one. This class has no write side,
 * no base class and no migration behind it. Each table keeps its own columns,
 * its own writers and its own vocabulary; this maps them into one column set
 * at READ time and nothing else changes.
 *
 * The column set, which is the commitment:
 *
 *   source      which table the row came from, as a stable code
 *   subjectID   the host the work is about, or 0 where the work is not
 *               about a host (a multicast session, a file deletion)
 *   startedAt   when it began
 *   endedAt     when it finished, or null
 *   state       the taskStates id
 *   label       something readable, where the table has one to give
 *
 * `endedAt` IS NULL FOR `task` AND `snapinjob`, ALWAYS, and that is the
 * schema being reported honestly rather than a gap to paper over. Neither
 * table has an end column. `taskStateChangedTime` looks like one and is not:
 * ADR 0022 decision 2 rules it out explicitly because every transition
 * overwrites it, so it records the last transition and not the end. A
 * consumer wanting "did this finish" must read `state` -- which is the same
 * decision's other half, that state is authoritative for WHAT and timestamps
 * are authoritative for WHEN.
 *
 * `label` is empty for `snapinjob` for the same reason: the table has no name
 * column and nothing to derive one from that a caller cannot derive itself
 * from `subjectID`. An empty string is what "this table cannot answer that"
 * looks like; inventing a join to fill it would cost every caller a join
 * they did not ask for.
 *
 * Bounded and ordered by `startedAt`. Schema 354 indexes that column on all
 * five tables, which is why the ADR says to add the indexes WITH this class
 * and not before: an index nothing queries is write cost on an insert-hot
 * table for no read at all.
 *
 * @category ActivityWindow
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ActivityWindow extends FOGBase
{
    /**
     * The most rows one call will return.
     *
     * A window query over five growing tables has no natural bound -- "the
     * last hour" on a server mid-rollout is thousands of rows -- and a
     * caller that forgets to pass a limit must not be the thing that runs
     * the server out of memory. Same stance as Route::MAX_ROWS.
     *
     * @var int
     */
    const MAX_ROWS = 5000;

    /**
     * How each table maps onto the shared column set.
     *
     * A method rather than a const because the state columns are compared
     * against taskStates ids that hooks may override, and because the SQL
     * has to be assembled rather than declared.
     *
     * Every value here is a literal written in this file. Nothing from a
     * request reaches the SQL: the range bounds are bound as parameters and
     * the source filter is matched against these keys.
     *
     * @return array source => [table, id, start, end, state, label, join]
     */
    private static function _map()
    {
        return [
            'task' => [
                'table' => 'tasks',
                'subjectID' => '`tasks`.`taskHostID`',
                'startedAt' => '`tasks`.`taskCreateTime`',
                // No end column. See the class docblock: this is the schema,
                // not an omission.
                'endedAt' => 'NULL',
                'state' => '`tasks`.`taskStateID`',
                'label' => '`tasks`.`taskName`',
                'join' => ''
            ],
            'snapinjob' => [
                'table' => 'snapinJobs',
                'subjectID' => '`snapinJobs`.`sjHostID`',
                'startedAt' => '`snapinJobs`.`sjCreateTime`',
                'endedAt' => 'NULL',
                'state' => '`snapinJobs`.`sjStateID`',
                'label' => "''",
                'join' => ''
            ],
            'snapintask' => [
                'table' => 'snapinTasks',
                // Reached through the job, which is where the host lives.
                // COALESCEd because the join is outer: a task whose job was
                // deleted still ran, and dropping it would make the window
                // quietly incomplete rather than visibly odd.
                'subjectID' => 'COALESCE(`snapinJobs`.`sjHostID`, 0)',
                'startedAt' => '`snapinTasks`.`stCheckinDate`',
                'endedAt' => '`snapinTasks`.`stCompleteDate`',
                'state' => '`snapinTasks`.`stState`',
                'label' => "COALESCE(`snapins`.`sName`, '')",
                'join' => 'LEFT OUTER JOIN `snapinJobs`'
                    . ' ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`'
                    . ' LEFT OUTER JOIN `snapins`'
                    . ' ON `snapinTasks`.`stSnapinID` = `snapins`.`sID`'
            ],
            'multicastsession' => [
                'table' => 'multicastSessions',
                // Not about one host: a session is many hosts by
                // definition, and its own id is what a caller follows.
                'subjectID' => '0',
                'startedAt' => '`multicastSessions`.`msStartDateTime`',
                'endedAt' => '`multicastSessions`.`msCompleteDateTime`',
                'state' => '`multicastSessions`.`msState`',
                'label' => '`multicastSessions`.`msName`',
                'join' => ''
            ],
            'filedeletequeue' => [
                'table' => 'fileDeleteQueue',
                // About a path on a storage group, not about a host.
                'subjectID' => '0',
                'startedAt' => '`fileDeleteQueue`.`fdqCreateDate`',
                'endedAt' => '`fileDeleteQueue`.`fdqCompletedDate`',
                'state' => '`fileDeleteQueue`.`fdqState`',
                'label' => '`fileDeleteQueue`.`fdqPathName`',
                'join' => ''
            ]
        ];
    }

    /**
     * The source codes this can read.
     *
     * @return array
     */
    public static function sources()
    {
        return array_keys(self::_map());
    }

    /**
     * Everything that started within a window.
     *
     * @param string $start   Inclusive lower bound, 'Y-m-d H:i:s'.
     * @param string $end     Inclusive upper bound, 'Y-m-d H:i:s'.
     * @param array  $sources Source codes to include; empty means all.
     * @param int    $limit   Row cap, clamped to MAX_ROWS.
     *
     * @return array Rows of source/subjectID/startedAt/endedAt/state/label.
     */
    public static function between(
        $start,
        $end,
        array $sources = [],
        $limit = self::MAX_ROWS
    ) {
        $map = self::_map();
        if (count($sources) > 0) {
            // Whitelisted rather than filtered into the SQL: a source name
            // becomes a table name here, so an unrecognized one is dropped
            // and never reaches the query.
            $map = array_intersect_key($map, array_flip($sources));
        }
        if (count($map) < 1) {
            return [];
        }
        $limit = (int)$limit;
        if ($limit < 1 || $limit > self::MAX_ROWS) {
            $limit = self::MAX_ROWS;
        }

        $selects = [];
        $binds = [];
        $n = 0;
        foreach ($map as $source => $m) {
            // Two placeholders per arm rather than two shared ones: a
            // prepared statement binds by name, and reusing one name across
            // arms is a portability trap that surfaces as a bound-parameter
            // count error rather than as a wrong answer.
            $lo = ':start' . $n;
            $hi = ':end' . $n;
            $src = ':src' . $n;
            $n++;
            $binds[$lo] = $start;
            $binds[$hi] = $end;
            // Bound rather than quoted, even though it is a literal from
            // _map() and can never be anything else. PDODB::sanitize()
            // quotes through the driver and returns the string UNQUOTED
            // when there is no link, which would emit `SELECT task AS
            // source` -- a column reference, not a string, and a syntax
            // error rather than a wrong answer only by luck.
            $binds[$src] = $source;
            $selects[] = sprintf(
                'SELECT %s AS `source`, %s AS `subjectID`, %s AS `startedAt`,'
                . ' %s AS `endedAt`, %s AS `state`, %s AS `label`'
                . ' FROM `%s` %s WHERE %s BETWEEN %s AND %s',
                $src,
                $m['subjectID'],
                $m['startedAt'],
                $m['endedAt'],
                $m['state'],
                $m['label'],
                $m['table'],
                $m['join'],
                $m['startedAt'],
                $lo,
                $hi
            );
        }

        // UNION ALL, not UNION. UNION de-duplicates, which means a sort of
        // the whole result to compare rows that can never be equal -- every
        // arm carries a different literal in `source`. It would be pure cost
        // and, worse, it would silently collapse two genuinely identical
        // rows from one table if a future arm ever produced them.
        $sql = '(' . implode(') UNION ALL (', $selects) . ')'
            . ' ORDER BY `startedAt` DESC'
            . ' LIMIT ' . $limit;

        $rows = self::$DB->query($sql, [], $binds)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        return (array)$rows;
    }

    /**
     * Whether a state means the work is over.
     *
     * The taskStates vocabulary is shared by all five tables (ADR 0022
     * context), and the terminal values are the three the helpers name.
     * Read through TaskState rather than compared to literals because each
     * one is hook-overridable.
     *
     * @param mixed $state The state id from a row.
     *
     * @return bool
     */
    public static function isTerminal($state)
    {
        return in_array(
            (int)$state,
            [
                (int)TaskState::getCompleteState(),
                (int)TaskState::getCancelledState(),
                (int)TaskState::getFailedState()
            ],
            true
        );
    }
}
