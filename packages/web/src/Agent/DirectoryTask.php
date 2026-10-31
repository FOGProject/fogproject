<?php
/** Existing imaging tasks authorize one directory operation, not clone history. */
namespace FOG\Agent;

use FOG\Items\Host;
use FOG\Items\TaskType;

class DirectoryTask extends \FOG\Base\FOGBase
{
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

    public static function read(Host $host, array $state, array $observation, $lock = false)
    {
        $db = self::$DB->link();
        if ($lock) {
            $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks'")->fetchColumn();
            if (0 !== strcasecmp((string)$engine, 'InnoDB')) {
                throw new \RuntimeException('directory_task_transactional_table_required', 409);
            }
        }
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
