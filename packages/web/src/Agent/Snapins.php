<?php
/**
 * Snapins for an enrolled agent: the queue, the payload, the result.
 *
 * PHP version 7.4+
 *
 * @category Snapins
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\Snapin;
use FOG\Items\SnapinTask;
use FOG\Items\StorageGroup;
use FOG\Items\StorageNode;
use FOG\Managers\SnapinTaskManager;
use FOG\Router\Route;

/**
 * The snapin capability (design 0001 section 7: snapins as payload-only
 * software, the detection rule comes later).
 *
 * The queue is the host's snapin job as the server already builds it --
 * snapinTasks in `sequence` order, which _createSnapinTasking wrote from
 * Resolver::resolveSnapins: the host's own associations first, then its
 * groups' grants, deduplicated. The agent honors that order and never
 * re-sorts. Listing is read-only so the desired state stays idempotent;
 * a task moves to in-progress when its payload is fetched and to
 * complete when its result lands, exactly as the legacy client's
 * SnapinClient does -- stream() and close() ARE that code, shared, so
 * both clients mark tasks and end jobs identically.
 *
 * @category Snapins
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Snapins extends FOGBase
{
    /**
     * stReturnDetails is TEXT; the agent sends the last 4 KB of output and
     * this is the server's own bound on what it keeps.
     */
    const MAX_DETAILS = 4096;

    /**
     * What a reported run says about itself: the payload ran and the exit
     * code is its own, or it never ran and this names why.
     */
    const STATUS_RAN = 'ran';
    const STATUSES = ['ran', 'hash_mismatch', 'timeout', 'cannot_run'];

    /**
     * What the server decides from the exit code and the snapin's
     * return-code table, the way Intune and SCCM read installer codes.
     */
    const OUTCOME_SUCCESS = 'success';
    const OUTCOME_REBOOT = 'reboot';
    const OUTCOME_RETRY = 'retry';
    const OUTCOME_FAILED = 'failed';
    const OUTCOMES = ['success', 'reboot', 'retry', 'failed'];

    /**
     * The return-code table a snapin gets when its own is empty: Intune's
     * defaults for installer exit codes. 3010 and 1641 are the two MSI
     * "installed, reboot to finish" answers, 1618 is "another install is
     * running". Anything not listed is failed.
     */
    const DEFAULT_RETURN_CODES = [
        0 => 'success',
        1707 => 'success',
        3010 => 'reboot',
        1641 => 'reboot',
        1618 => 'retry'
    ];

    /**
     * The tasks still to run for this host, in run order. Empty when the
     * host has no live snapin job.
     *
     * @param Host $Host the principal
     *
     * @return array
     */
    public static function queue(Host $Host)
    {
        $SnapinJob = $Host->get('snapinjob');
        if (!$SnapinJob->isValid()) {
            return [];
        }
        $rows = Route::getList(
            'snapintask',
            [
                'jobID' => (int)$SnapinJob->get('id'),
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            ],
            'AND',
            'sequence'
        );
        $out = [];
        foreach ($rows as $row) {
            $SnapinTask = new SnapinTask((int)$row->id);
            if (!$SnapinTask->isValid()) {
                continue;
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                continue;
            }
            $action = '';
            if ($Snapin->get('shutdown')) {
                $action = 'shutdown';
            } elseif ($Snapin->get('reboot')) {
                $action = 'reboot';
            }
            $out[] = [
                'task' => (int)$SnapinTask->get('id'),
                'snapin' => (int)$Snapin->get('id'),
                'name' => (string)$Snapin->get('name'),
                'file' => (string)$Snapin->get('file'),
                'size' => (int)$Snapin->get('size'),
                // The hash the SnapinHash scanner maintains; the agent
                // refuses a payload that does not match it.
                'sha512' => strtolower((string)$Snapin->get('hash')),
                'args' => (string)$Snapin->get('args'),
                'run_with' => (string)$Snapin->get('runWith'),
                'run_with_args' => (string)$Snapin->get('runWithArgs'),
                'timeout' => (int)$Snapin->get('timeout'),
                'action' => $action,
                'abort_on_fail' => (bool)$SnapinJob->get('abortOnFail')
            ];
        }
        return $out;
    }

    /**
     * The task, checked to belong to this host's own job.
     *
     * @param Host $Host   the principal
     * @param int  $taskID the caller-supplied snapin task id
     *
     * @throws \RuntimeException 404 when it is not this host's live task
     *
     * @return SnapinTask
     */
    public static function ownTask(Host $Host, $taskID)
    {
        $SnapinJob = $Host->get('snapinjob');
        $SnapinTask = new SnapinTask((int)$taskID);
        // Same message for "no such task" and "someone else's task", so
        // the id space is not an oracle (the legacy client's Aisle 009
        // guard, kept here for the same reason).
        if (!$SnapinJob->isValid()
            || !$SnapinTask->isValid()
            || (int)$SnapinTask->get('jobID') !== (int)$SnapinJob->get('id')
        ) {
            throw new \RuntimeException('no such snapin task', 404);
        }
        return $SnapinTask;
    }

    /**
     * The payload of one task, for GET /agent/v1/payload/snapin/{id}: the
     * task must be the host's own, and fetching is what marks it in
     * progress. Streams and exits.
     *
     * @param Host $Host   the agent's host
     * @param int  $taskID the snapin task
     *
     * @throws \RuntimeException 404, 503 (see ownTask, stream)
     *
     * @return void
     */
    public static function payload(Host $Host, $taskID)
    {
        self::stream($Host, self::ownTask($Host, (int)$taskID));
    }

    /**
     * Streams the task's payload to the caller and marks the task, job and
     * host task in progress. Does not return.
     *
     * The bytes come over the web tier's own FTP session to the storage
     * node, as they always have: the agent trusts one certificate, the
     * server's, and never a node's.
     *
     * @param Host       $Host       the principal
     * @param SnapinTask $SnapinTask a task ownTask() returned
     *
     * @throws \RuntimeException 503 when no node can serve the file
     *
     * @return void
     */
    public static function stream(Host $Host, SnapinTask $SnapinTask)
    {
        $Snapin = $SnapinTask->getSnapin();
        if (!$Snapin->isValid()) {
            throw new \RuntimeException('no such snapin', 404);
        }
        $StorageNode = self::_node($Host, $Snapin);
        $path = sprintf('/%s', trim((string)$StorageNode->get('snapinpath'), '/'));
        $file = (string)$Snapin->get('file');
        $filepath = sprintf('%s/%s', $path, $file);
        $host = (string)$StorageNode->get('ip');
        $user = (string)$StorageNode->get('user');
        $pass = (string)$StorageNode->get('pass');
        self::$FOGFTP->username = $user;
        self::$FOGFTP->password = $pass;
        self::$FOGFTP->host = $host;
        if (!self::$FOGFTP->connect()) {
            throw new \RuntimeException('cannot connect to the storage node', 503);
        }
        $SnapinFile = sprintf('ftp://%s:%s@%s%s', $user, urlencode($pass), $host, $filepath);
        $fh = fopen($SnapinFile, 'rb');
        if (false === $fh) {
            throw new \RuntimeException('cannot read the snapin file', 503);
        }
        $date = self::niceDate()->format('Y-m-d H:i:s');
        $Task = $Host->get('task');
        if ($Task->isValid()) {
            $Task
                ->set('stateID', self::getProgressState())
                ->set('checkInTime', $date)
                ->save();
        }
        $Host->get('snapinjob')->set('stateID', self::getProgressState())->save();
        $SnapinTask
            ->set('checkin', $date)
            ->set('stateID', self::getProgressState())
            ->set('return', -1)
            ->set('details', _('Pending...'))
            ->save();
        while (ob_get_level()) {
            ob_end_clean();
        }
        header("X-Sendfile: $filepath");
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=$file");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        while (feof($fh) === false) {
            if (($line = fread($fh, 4096)) === false) {
                break;
            }
            echo $line;
            flush();
        }
        fclose($fh);
        exit;
    }

    /**
     * A snapin's return-code table: its own, one `code=class` per line,
     * or the defaults when it has none. Lines that are not a code and a
     * known class are ignored rather than failing the run.
     *
     * @param Snapin $Snapin the snapin
     *
     * @return array code => outcome
     */
    public static function returnCodes(Snapin $Snapin)
    {
        return self::parseReturnCodes(
            (string)$Snapin->get('returnCodes'),
            self::DEFAULT_RETURN_CODES
        );
    }

    /**
     * Parses a `code=class` table, one per line (commas and semicolons
     * separate too). Unknown classes are skipped; an empty table is the
     * defaults given.
     *
     * @param string $text     the table as typed
     * @param array  $defaults code => class when the text has none
     *
     * @return array code => class
     */
    public static function parseReturnCodes($text, array $defaults)
    {
        $table = [];
        foreach (preg_split('/[\r\n,;]+/', (string)$text) as $line) {
            if (!preg_match('/^\s*(-?\d+)\s*=\s*([a-z_]+)\s*$/i', (string)$line, $m)) {
                continue;
            }
            $class = strtolower($m[2]);
            if (in_array($class, self::OUTCOMES, true)) {
                $table[(int)$m[1]] = $class;
            }
        }
        return count($table) > 0 ? $table : $defaults;
    }

    /**
     * What one run came to. A payload that never ran failed, whatever
     * the number beside it; one that ran is read against the table, and
     * a code the table does not name is failed unless it is 0.
     *
     * @param Snapin $Snapin   the snapin
     * @param string $status   one of STATUSES
     * @param int    $exitcode the program's exit code
     *
     * @return string one of OUTCOMES
     */
    public static function outcome(Snapin $Snapin, $status, $exitcode)
    {
        if (self::STATUS_RAN !== $status) {
            return self::OUTCOME_FAILED;
        }
        $table = self::returnCodes($Snapin);
        $exitcode = (int)$exitcode;
        if (isset($table[$exitcode])) {
            return $table[$exitcode];
        }
        return 0 === $exitcode ? self::OUTCOME_SUCCESS : self::OUTCOME_FAILED;
    }

    /**
     * Records the result of one task and, when it was the last, ends the
     * job -- canceling the rest first when the job aborts on failure.
     *
     * The outcome comes from the snapin's return-code table. A retry puts
     * the task back in the queue with its details kept; everything else
     * completes it. The task row keeps the raw code, the status (the
     * outcome, or why the payload never ran) and the output.
     *
     * @param Host       $Host       the principal
     * @param SnapinTask $SnapinTask a task ownTask() returned
     * @param int        $exitcode   the payload's exit code
     * @param string     $details    the output tail, or the agent's reason
     * @param string     $status     one of STATUSES; the legacy client
     *                               only ever reports a run
     *
     * @throws \RuntimeException 409 when the task is already closed
     *
     * @return string the outcome, one of OUTCOMES
     */
    public static function close(Host $Host, SnapinTask $SnapinTask, $exitcode, $details, $status = self::STATUS_RAN)
    {
        if (in_array(
            (int)$SnapinTask->get('stateID'),
            [self::getCompleteState(), self::getCancelledState()],
            true
        )) {
            throw new \RuntimeException('snapin task already closed', 409);
        }
        $Snapin = $SnapinTask->getSnapin();
        if (!$Snapin->isValid()) {
            throw new \RuntimeException('no such snapin', 404);
        }
        $exitcode = (int)$exitcode;
        $details = substr(trim((string)$details), 0, self::MAX_DETAILS);
        $outcome = self::outcome($Snapin, $status, $exitcode);
        $date = self::niceDate()->format('Y-m-d H:i:s');
        $HostName = (string)$Host->get('name');
        $SnapinJob = $Host->get('snapinjob');
        $SnapinTask
            ->set('return', $exitcode)
            ->set('details', $details)
            ->set('status', self::STATUS_RAN === $status ? $outcome : $status);
        if (self::OUTCOME_RETRY === $outcome) {
            // Back to the queue, not complete: the next check-in runs it
            // again. The job stays open around it.
            $SnapinTask->set('stateID', self::getQueuedState())->save();
            return $outcome;
        }
        $SnapinTask
            ->set('stateID', self::getCompleteState())
            ->set('complete', $date)
            ->save();
        self::$EventManager->notify(
            'HOST_SNAPINTASK_COMPLETE',
            [
                'Snapin' => &$Snapin,
                'SnapinTask' => &$SnapinTask,
                'Host' => &$Host,
                'HostName' => &$HostName
            ]
        );
        $live = [
            'jobID' => (int)$SnapinJob->get('id'),
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            )
        ];
        $abortedOnFailure = false;
        if ($SnapinJob->get('abortOnFail') && self::OUTCOME_FAILED === $outcome) {
            $abortedOnFailure = true;
            (new SnapinTaskManager())->update(
                $live,
                '',
                [
                    'stateID' => self::getCancelledState(),
                    'return' => $exitcode,
                    'details' => sprintf(
                        _('Aborted due to failure of "%s" with exit code %s'),
                        $Snapin->get('name'),
                        $exitcode
                    ),
                    'complete' => $date
                ]
            );
        }
        if (Route::getCount('snapintask', $live) < 1) {
            $stateID = $abortedOnFailure ? self::getCancelledState() : self::getCompleteState();
            $Task = $Host->get('task');
            if ($Task->isValid()) {
                $Task->set('stateID', $stateID)->save();
            }
            $SnapinJob->set('stateID', $stateID)->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                [
                    'HostName' => &$HostName,
                    'Host' => &$Host
                ]
            );
        }
        return $outcome;
    }

    /**
     * The agent's report for one task: close it and leave the audit row
     * the host page shows.
     *
     * @param Host  $Host   the principal
     * @param int   $taskID the snapin task
     * @param array $body   status, exit_code, details
     *
     * @throws \RuntimeException 400 on a status that is not one
     *
     * @return string the outcome, for the agent to act on
     */
    public static function report(Host $Host, $taskID, array $body)
    {
        $SnapinTask = self::ownTask($Host, $taskID);
        $name = (string)$SnapinTask->getSnapin()->get('name');
        $status = (string)($body['status'] ?? self::STATUS_RAN);
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }
        $exitcode = (int)($body['exit_code'] ?? 0);
        $details = (string)($body['details'] ?? '');
        $outcome = self::close($Host, $SnapinTask, $exitcode, $details, $status);
        $summary = self::STATUS_RAN === $status
            ? sprintf('exit %d, %s', $exitcode, $outcome)
            : $status;
        Audit::record(
            [
                'type' => 'agent.result',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'text' => substr(
                    sprintf(
                        'snapin "%s" (task %d) %s%s',
                        $name,
                        (int)$SnapinTask->get('id'),
                        $summary,
                        '' === trim($details) ? '' : ': ' . trim($details)
                    ),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
        return $outcome;
    }

    /**
     * The node that serves this snapin to this host: a hook's choice, else
     * the master of the snapin's storage group.
     *
     * @param Host   $Host   the principal
     * @param Snapin $Snapin the snapin
     *
     * @throws \RuntimeException 503 when there is none
     *
     * @return StorageNode
     */
    private static function _node(Host $Host, Snapin $Snapin)
    {
        $HostName = (string)$Host->get('name');
        $StorageGroup = self::_hooked(
            'SNAPIN_GROUP',
            [
                'Host' => &$Host,
                'Snapin' => &$Snapin,
                'StorageGroup' => null,
                'HostName' => &$HostName
            ],
            'StorageGroup'
        );
        $StorageNode = self::_hooked(
            'SNAPIN_NODE',
            [
                'Host' => &$Host,
                'Snapin' => &$Snapin,
                'StorageNode' => null
            ],
            'StorageNode'
        );
        if (!($StorageGroup instanceof StorageGroup && $StorageGroup->isValid())) {
            $StorageGroup = $Snapin->getStorageGroup();
            if (!$StorageGroup->isValid()) {
                throw new \RuntimeException('no storage group for this snapin', 503);
            }
        }
        if (!($StorageNode instanceof StorageNode && $StorageNode->isValid())) {
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!($StorageNode instanceof StorageNode && $StorageNode->isValid())) {
                throw new \RuntimeException('no storage node for this snapin', 503);
            }
        }
        return $StorageNode;
    }

    /**
     * Fires a hook event whose listeners answer by filling one slot of the
     * payload, and returns what they left there.
     *
     * @param string $event the event
     * @param array  $args  the payload; $args[$key] is the answer slot
     * @param string $key   the slot
     *
     * @return mixed null when no listener answered
     */
    private static function _hooked($event, array $args, $key)
    {
        $answer = $args[$key];
        $args[$key] = &$answer;
        self::$HookManager->processEvent($event, $args);
        return $answer;
    }
}
