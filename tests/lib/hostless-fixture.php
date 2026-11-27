<?php
/**
 * A fake `tasks` table for the hostless-task tests (forum 18086).
 *
 * Included, never run: run-all.sh globs tests/*.test.php at the top level
 * only. It answers the statements BootToken and the TaskingElement family
 * send, by reading the SQL and its bound values -- so a test that gets the
 * state list, the NULL-host clause or the MAC filter wrong in the real code
 * sees a different answer, instead of a canned one.
 *
 * Not for asserting that a query is valid SQL; see fog-test-harness.php.
 */

use FOG\Items\TaskState;

/**
 * One full `tasks` row as the joined load query returns it.
 *
 * @param array $over columns to override
 *
 * @return array
 */
function hostlessRow(array $over = [])
{
    return array_merge(
        [
            'taskID' => 7,
            'taskName' => 'Unregistered - aa:bb:cc:dd:ee:ff',
            'taskCheckIn' => null,
            'taskHostID' => null,
            'taskStateID' => TaskState::getQueuedState(),
            'taskCreateTime' => '2026-11-27 10:00:00',
            'taskCreateBy' => 'menuuser',
            'taskForce' => 0,
            'taskScheduledStartTime' => null,
            'taskTypeID' => 1,
            'taskPCT' => 0,
            'taskBPM' => '',
            'taskTimeElapsed' => '',
            'taskTimeRemaining' => '',
            'taskDataCopied' => '',
            'taskPercentText' => '',
            'taskDataTotal' => '',
            'taskNFSGroupID' => 2,
            'taskNFSMemberID' => 3,
            'taskNFSFailures' => 0,
            'taskLastMemberID' => 3,
            'taskShutdown' => 0,
            'taskPassreset' => '',
            'taskIsDebug' => 0,
            'taskImageID' => 5,
            'taskWOL' => 0,
            'taskBypassBitlocker' => 0,
            'taskStateChangedTime' => null,
            'taskMAC' => 'aa:bb:cc:dd:ee:ff',
            'taskTokenHash' => str_repeat('0', 64),
            // The joined image, so Task::getImage() is valid.
            'imageID' => 5,
            'imageName' => 'win11-base',
            'imagePath' => 'win11-base',
            'imageTypeID' => 1,
            'imageOSID' => 1,
            'imagePartitionTypeID' => 1,
            'imageArchID' => 1,
            'imageSize' => '',
            'imageEnabled' => 1,
            'imageToReplicate' => 1,
        ],
        $over
    );
}

/**
 * Builds the responder for a set of tasks.
 *
 * `$tasks` is by reference so a test can change a row (state, MAC) between
 * calls. Every statement the responder sees is appended to `$seen` as
 * [sql, params], so a test can assert on what was written.
 *
 * @param array $tasks task rows keyed by taskID, from hostlessRow()
 * @param array $seen  receives every [sql, params]
 *
 * @return callable for FogFakeDb::$responder
 */
function hostlessResponder(array &$tasks, array &$seen)
{
    return function ($sql, $params) use (&$tasks, &$seen) {
        $params = (array)$params;
        $seen[] = [$sql, $params];

        // BootToken::taskFor(): the token, the NULL host, the states, the MAC.
        if (false !== strpos($sql, '`taskTokenHash` = :hash')) {
            $states = [];
            if (preg_match('/`taskStateID` IN \(([0-9,]+)\)/', $sql, $m)) {
                $states = array_map('intval', explode(',', $m[1]));
            }
            $exact = null;
            if (preg_match('/`taskStateID` = :state/', $sql)) {
                $exact = (int)$params[':state'];
            }
            $macs = [];
            foreach ($params as $key => $value) {
                if (0 === strpos($key, ':mac')) {
                    $macs[] = $value;
                }
            }
            $out = [];
            krsort($tasks);
            foreach ($tasks as $row) {
                if (($row['taskTokenHash'] ?? '') !== ($params[':hash'] ?? null)) {
                    continue;
                }
                if (false !== strpos($sql, '`taskHostID` IS NULL')
                    && null !== $row['taskHostID']
                ) {
                    continue;
                }
                if (null !== $exact && (int)$row['taskStateID'] !== $exact) {
                    continue;
                }
                if ([] !== $states
                    && !in_array((int)$row['taskStateID'], $states, true)
                ) {
                    continue;
                }
                if (!in_array($row['taskMAC'], $macs, true)) {
                    continue;
                }
                $out[] = ['taskID' => $row['taskID']];
                break;
            }
            return $out;
        }

        // Task::load(): the joined row of one task.
        if (0 === strpos($sql, 'SELECT `tasks`.*')
            && false !== strpos($sql, 'WHERE `taskID`=:id')
        ) {
            return $tasks[(int)$params[':id']] ?? [];
        }
        // TaskType::load(): isImagingTask() reads the id, so the row has to
        // exist for `new TaskType($id)` to be a deploy at all.
        if (false !== strpos($sql, 'FROM `taskTypes`')
            && false !== strpos($sql, 'WHERE `ttID`=:id')
        ) {
            return [
                'ttID' => (int)$params[':id'],
                'ttName' => 'Deploy',
                'ttIcon' => 'download',
            ];
        }
        return null;
    };
}
