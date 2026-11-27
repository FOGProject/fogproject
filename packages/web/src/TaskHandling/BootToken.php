<?php
/**
 * Issues and finds the single-use boot token of a hostless task.
 *
 * PHP version 7.4+
 *
 * @category BootToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\TaskHandling;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\MulticastSession;
use FOG\Items\MulticastSessionAssociation;
use FOG\Items\StorageNode;
use FOG\Items\Task;
use FOG\Items\TaskLog;
use FOG\Items\TaskState;
use FOG\Items\TaskType;

/**
 * Issues and finds the single-use boot token of a hostless task.
 *
 * A deploy or multicast join from the iPXE menu, by a host that is
 * unregistered or pending, is a real Task with no host (taskHostID NULL). It
 * carries the MAC the machine booted with and the menu user. FOS has no host
 * row to be found by, so the boot menu hands it a token on the kernel command
 * line (`fogtoken=<raw>`) and FOS sends it back on every report (forum 18086).
 *
 * The raw token is bin2hex(random_bytes(32)). Only sha256 of it is stored
 * (tasks.taskTokenHash, indexed), so a database read does not give a usable
 * token. The token is single-use in scope, not in count: it finds exactly one
 * active hostless task, and only from a request that also names that task's
 * MAC. When the task leaves the active states the token finds nothing.
 *
 * @category BootToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class BootToken extends FOGBase
{
    /**
     * The name of the kernel argument and of the POST/GET field.
     *
     * @var string
     */
    const FIELD = 'fogtoken';
    /**
     * Issues a hostless Queued task and returns the raw token.
     *
     * The task starts Queued, not In-Progress: the slot count in
     * StorageNode counts In-Progress tasks against maxClients, and a task
     * FOS has not yet checked in must not hold a slot (it would deadlock).
     * FOS moves it to In-Progress at check-in.
     *
     * @param TaskType              $TaskType  the task type, 1 or 8.
     * @param Image                 $Image     the image to deploy.
     * @param StorageNode           $StorageNode the node the menu picked.
     * @param string                $mac       the MAC the machine booted with.
     * @param string                $createdBy the menu user.
     * @param MulticastSession|null $mc        the session being joined, which
     *                                         gets an association row.
     *
     * @return string|null the raw token, or null if no task was saved.
     */
    public static function issue(
        $TaskType,
        $Image,
        $StorageNode,
        $mac,
        $createdBy,
        $mc = null
    ) {
        try {
            $mac = strtolower(trim((string)$mac));
            $raw = bin2hex(random_bytes(32));
            $Task = (new Task())
                ->set('name', sprintf('Unregistered - %s', $mac))
                // taskCreateBy is varchar(200).
                ->set('createdBy', substr((string)$createdBy, 0, 200))
                ->set('isForced', 0)
                ->set('stateID', TaskState::getQueuedState())
                ->set('typeID', (int)$TaskType->get('id'))
                ->set('imageID', (int)$Image->get('id'))
                ->set('storagegroupID', (int)$StorageNode->get('storagegroupID'))
                ->set('storagenodeID', (int)$StorageNode->get('id'))
                ->set('NFSLastMemberID', (int)$StorageNode->get('id'))
                ->set('mac', $mac)
                ->set('tokenHash', hash('sha256', $raw))
                // The objects already in hand, so recordState() below does
                // not load the type, image and node again.
                ->set('type', $TaskType)
                ->set('image', $Image)
                ->set('storagenode', $StorageNode);
            if (!$Task->save()) {
                throw new \Exception(_('Failed to save the task'));
            }
            // A multicast join is found by its session through this row:
            // TaskQueue::checkIn() throws "Invalid Multicast Session" for a
            // multicast task without one. A registered host gets the same
            // row from Host::createImagePackage().
            if ($mc && $mc->isValid()) {
                $saved = (new MulticastSessionAssociation())
                    ->set('msID', $mc->get('id'))
                    ->set('taskID', $Task->get('id'))
                    ->save();
                if (!$saved) {
                    // A task that can never check in must not stay behind
                    // for the reaper to find a day later.
                    $Task->destroy();
                    throw new \Exception(_('Unable to create association'));
                }
            }
            // The Queued row that recordState() writes for every new task.
            TaskLog::recordState($Task);
            return $raw;
        } catch (\Exception $e) {
            // Imaging must never be blocked by this: the caller boots the
            // machine without a token, as it did before this existed.
            self::logFault(
                sprintf(
                    '%s: %s: %s',
                    _('Failed to issue a boot token'),
                    _('Error'),
                    $e->getMessage()
                )
            );
            return null;
        }
    }
    /**
     * Finds the active hostless task a token belongs to.
     *
     * The token alone is not enough: the request must also come from the
     * MAC the task was issued for. Anything else returns an empty Task, and
     * the caller carries on with the normal host flow.
     *
     * @param array  $macs the MACs the request names.
     * @param string $raw  the raw token FOS sent.
     *
     * @return Task a valid Task, or an empty (invalid) one.
     */
    public static function taskFor(array $macs, $raw)
    {
        $states = array_map(
            'intval',
            array_merge(
                (array)TaskState::getQueuedStates(),
                [TaskState::getProgressState()]
            )
        );
        $taskID = self::_findTaskID(
            $macs,
            $raw,
            '`taskStateID` IN (' . implode(',', $states) . ')',
            []
        );
        if ($taskID < 1) {
            return new Task();
        }
        $Task = new Task($taskID);
        return $Task->isValid() ? $Task : new Task();
    }
    /**
     * Finds the newest hostless task for a token, a MAC and a state clause.
     *
     * The one place that normalizes the MACs, checks the token is 64 hex
     * characters, binds the values and builds the SELECT, so taskFor() and
     * completedRecently() cannot drift. Only the state clause differs.
     *
     * @param array  $macs        the MACs the request names.
     * @param string $raw         the raw token FOS sent.
     * @param string $stateClause SQL for the state test, no user data in it.
     * @param array  $stateParams values bound for the state clause.
     *
     * @return int the task id, or 0 when nothing matches.
     */
    private static function _findTaskID(
        array $macs,
        $raw,
        $stateClause,
        array $stateParams
    ) {
        $raw = strtolower(trim((string)$raw));
        $lowered = [];
        foreach ($macs as $mac) {
            $mac = strtolower(trim((string)$mac));
            if ('' !== $mac) {
                $lowered[$mac] = $mac;
            }
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $raw) || count($lowered) < 1) {
            return 0;
        }
        $params = array_merge(
            [':hash' => hash('sha256', $raw)],
            $stateParams
        );
        $holders = [];
        foreach (array_values($lowered) as $i => $mac) {
            $holders[] = ':mac' . $i;
            $params[':mac' . $i] = $mac;
        }
        $rows = self::$DB->query(
            'SELECT `taskID` FROM `tasks` '
            . 'WHERE `taskTokenHash` = :hash '
            . 'AND `taskHostID` IS NULL '
            . 'AND ' . $stateClause . ' '
            . 'AND `taskMAC` IN (' . implode(',', $holders) . ') '
            . 'ORDER BY `taskID` DESC LIMIT 1',
            [],
            $params
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        return (int)($rows[0]['taskID'] ?? 0);
    }
    /**
     * Resolves the posted token to its hostless task, if there is one.
     *
     * Token first, then host, on every FOS endpoint: when a token was posted
     * and it finds an active task for the MAC that came with it, the normal
     * host lookup is skipped and self::$Host is an EMPTY Host, so no write
     * further down can land on a registered host by accident. When no token
     * was posted, or it finds nothing, this returns null and leaves the
     * request for the normal host flow, untouched.
     *
     * @return Task|null the hostless task, or null to use the host flow.
     */
    public static function resolve()
    {
        $raw = self::posted();
        if ('' === $raw) {
            return null;
        }
        try {
            // Parses the MACs into self::$requestMacs and does not throw for
            // an unregistered machine. The host it may find is discarded
            // below when the token wins.
            self::getHostItem(false, false, true);
        } catch (\Exception $e) {
            return null;
        }
        $Task = self::taskFor(self::$requestMacs, $raw);
        if (!$Task->isValid()) {
            return null;
        }
        self::$Host = new Host();
        return $Task;
    }
    /**
     * Tells whether the token's hostless deploy already completed.
     *
     * The token variant of TaskQueue::ackIfAlreadyComplete(): a retry whose
     * first checkout succeeded, but whose reply FOS lost, finds no active
     * task. The newest Complete hostless task with this token and MAC,
     * checked in within the last day, means the completion already happened.
     * The same one-day bound stops an old task being mistaken for this one.
     *
     * @param array  $macs the MACs the request names.
     * @param string $raw  the raw token FOS sent.
     *
     * @return bool
     */
    public static function completedRecently(array $macs, $raw)
    {
        $taskID = self::_findTaskID(
            $macs,
            $raw,
            '`taskStateID` = :state',
            [':state' => (int)self::getCompleteState()]
        );
        if ($taskID < 1) {
            return false;
        }
        $Task = new Task($taskID);
        // Only a deploy is issued a token, and only a deploy is acked here,
        // as the host variant does for type=down.
        if (!$Task->isValid()
            || !in_array((int)$Task->get('typeID'), TaskType::DEPLOYTASKS)
        ) {
            return false;
        }
        $checkin = trim((string)$Task->get('checkInTime'));
        if (!self::validDate($checkin)) {
            return false;
        }
        try {
            $elapsed = self::niceDate()->getTimestamp()
                - self::niceDate($checkin)->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }
        return $elapsed >= 0 && $elapsed <= 86400;
    }
    /**
     * Names the machine of a task for an audit row or a notification.
     *
     * A hostless task has no host name, so it is named by the MAC it was
     * issued for. A registered host keeps its name, unchanged.
     *
     * @param Task $Task the task being worked on.
     * @param Host $Host the request host (empty when the token won).
     *
     * @return string
     */
    public static function label($Task, $Host)
    {
        if ($Task->isHostless()) {
            // Untranslated on purpose: it is stored in audit rows and sent in
            // notifications, so a stored row must not depend on the viewer's
            // language. The UI label is translated in
            // TaskManagement::_unregisteredLabel().
            return sprintf('%s (unregistered)', $Task->get('mac'));
        }
        return (string)$Host->get('name');
    }
    /**
     * Returns the token FOS posted, or an empty string.
     *
     * @return string
     */
    public static function posted()
    {
        $value = filter_input(INPUT_POST, self::FIELD);
        if (null === $value || false === $value || '' === trim($value)) {
            $value = filter_input(INPUT_GET, self::FIELD);
        }
        return (null === $value || false === $value) ? '' : trim($value);
    }
}
