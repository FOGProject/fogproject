<?php
/**
 * Existing imaging tasks authorize one directory operation.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Agent;

use FOG\Items\Host;
use FOG\Items\TaskType;

/**
 * Reads a host's latest imaging and deploy tasks and says whether the latest
 * deploy task may trigger one directory operation. A task counts once: the
 * state keeps a high-water task id, and a task at or below it is consumed.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryTask extends \FOG\Base\FOGBase
{
    /**
     * Pure decision over the two task rows, so tests need no database.
     *
     * @param array $imaging     the host's latest deploy or capture task row
     * @param array $deploy      the host's latest deploy task row
     * @param array $state       the stored directory state
     * @param array $observation the agent's observation (its boot time matters)
     * @param int   $now         the current Unix time
     *
     * @return array status (none, in_progress, consumed, expired, baseline,
     *               unknown or eligible) and task_id
     */
    public static function decision(array $imaging, array $deploy, array $state, array $observation, $now)
    {
        $high = (int)($state['last_deploy_task_id'] ?? 0);
        $initial = !array_key_exists('last_deploy_task_id', $state)
            && (empty($state['attempt_id']) || 'verified' === ($state['phase'] ?? ''));
        $id = (int)($deploy['taskID'] ?? 0);
        $answer = static function ($status, $task = 0) {
            return ['status' => $status, 'task_id' => (int)$task];
        };
        if (!empty($imaging) && (int)($imaging['taskStateID'] ?? -1) >= 0
            && (int)$imaging['taskStateID'] <= 3) {
            return $answer('in_progress', $id);
        }
        if (!$deploy) {
            return $answer('none');
        }
        if ($id < 1 || !in_array((int)($deploy['taskTypeID'] ?? 0), TaskType::DEPLOYTASKS, true)) {
            return $answer('unknown', $id);
        }
        if ($id <= $high) {
            return $answer('consumed', $id);
        }
        if ((int)($deploy['taskStateID'] ?? -1) >= 0 && (int)$deploy['taskStateID'] <= 3) {
            return $answer('in_progress', $id);
        }
        // A cancelled Deploy (state 5) never authorizes an operation, and
        // plan() never recorded it. Answering unknown here blocked the host
        // and held placement for good. It is no trigger, so answer none.
        // plan() then records it as the baseline when none exists yet.
        if (5 === (int)($deploy['taskStateID'] ?? -1)) {
            return $answer('none', $id);
        }
        if (4 !== (int)($deploy['taskStateID'] ?? -1) || empty($deploy['taskStateChangedTime'])) {
            return $answer('unknown', $id);
        }
        try {
            $end = new \DateTimeImmutable($deploy['taskStateChangedTime'], self::storageTimeZone());
            $age = $now - $end->getTimestamp();
            if ($age < 0) {
                return $answer('unknown', $id);
            }
            if ($age > 86400) {
                return $answer($initial ? 'baseline' : 'expired', $id);
            }
            $started = $observation['boot']['started'] ?? '';
            if (!is_string($started) || '' === $started) {
                return $answer('unknown', $id);
            }
            $boot = new \DateTimeImmutable($started);
            if ($boot->getTimestamp() < $end->getTimestamp() || $boot->getTimestamp() > $now + 60) {
                return $answer('unknown', $id);
            }
        } catch (\Throwable $e) {
            return $answer('unknown', $id);
        }
        return $answer('eligible', $id);
    }

    /**
     * Reads the two task rows and decides.
     *
     * @param Host  $host        the host
     * @param array $state       the stored directory state
     * @param array $observation the agent's observation
     * @param bool  $lock        whether to lock the task rows (inside a claim)
     *
     * @return array see decision()
     */
    public static function read(Host $host, array $state, array $observation, $lock = false)
    {
        $db = self::$DB->link();
        $imaging = array_unique(array_merge(TaskType::DEPLOYTASKS, TaskType::CAPTURETASKS));
        $rows = [];
        foreach ([$imaging, TaskType::DEPLOYTASKS] as $types) {
            $q = $db->prepare('SELECT taskID,taskHostID,taskStateID,taskTypeID,taskStateChangedTime FROM tasks WHERE taskHostID=? AND taskTypeID IN ('
                .implode(',', array_map('intval', $types)).') ORDER BY taskID DESC LIMIT 1'.($lock ? ' FOR UPDATE' : ''));
            $q->execute([(int)$host->get('id')]);
            $rows[] = $q->fetch(\PDO::FETCH_ASSOC) ?: [];
            $q->closeCursor();
        }
        return self::decision($rows[0], $rows[1], $state, $observation, time());
    }
}
