<?php
/**
 * Handles snapins for the host
 *
 * PHP version 7.4+
 *
 * @category SnapinClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Agent\Snapins;
use FOG\Items\SnapinTask;
use FOG\Items\StorageGroup;
use FOG\Items\StorageNode;
use FOG\Managers\SnapinTaskManager;
use FOG\Router\Route;

/**
 * Handles snapins for the host
 *
 * @category SnapinClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinClient extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'snapinclient';

    /**
     * Function returns data that will be translated to json
     *
     * @return array|string[]|void
     * @throws Exception
     */
    public function json()
    {
        $HostName = self::$Host->get('name');
        $Task = self::$Host->get('task');
        $SnapinJob = self::$Host->get('snapinjob');
        if ($Task->isValid() && !$Task->isSnapinTasking()) {
            return [
                'error' => 'it'
            ];
        }
        if (!$SnapinJob->isValid()) {
            return [
                'error' => 'ns'
            ];
        }
        $STaskCount = Route::getCount(
            'snapintask',
            [
                'jobID' => $SnapinJob->get('id'),
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            ]
        );
        if ($STaskCount < 1) {
            if ($Task->isValid()) {
                $Task->set('stateID', self::getCompleteState())->save();
            }
            $SnapinJob->set('stateID', self::getCompleteState())->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                [
                    'Host' => &self::$Host,
                    'HostName' => &$HostName
                ]
            );
            return ['error' => 'ns'];
        }
        $date = self::niceDate()->format('Y-m-d H:i:s');
        if ($Task->isValid()) {
            $Task
                ->set('stateID', self::getCheckedInState())
                ->set('checkInTime', $date)
                ->save();
        }
        $SnapinJob->set('stateID', self::getCheckedInState())->save();
        global $sub;
        if ($sub === 'requestClientInfo'
            || basename(self::$scriptname) === 'snapins.checkin.php'
        ) {
            $exitcode = filter_input(INPUT_POST, 'exitcode');
            if (!$exitcode) {
                $exitcode = filter_input(INPUT_GET, 'exitcode');
            }
            $exitdesc = filter_input(INPUT_POST, 'exitdesc');
            if (!$exitdesc) {
                $exitdesc = filter_input(INPUT_GET, 'exitdesc');
            }
            if (!is_numeric($exitcode)) {
                $find = [
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    ),
                    'jobID' => $SnapinJob->get('id')
                ];
                // getList() sets inputoverride, which the old call left off.
                // That is the one behavior change here and it is deliberate:
                // this runs inside the client's POST, so without it listem()
                // parses php://input -- and folds in ?length/?start -- and a
                // request carrying either would silently truncate the snapin
                // list the client is about to run.
                $SnapinTasks = Route::getList(
                    'snapintask',
                    $find,
                    'AND',
                    'sequence'
                );
                if (count($SnapinTasks) < 1) {
                    $SnapinJob
                        ->set('stateID', self::getCancelledState())
                        ->save();
                    return ['error' => _('No valid tasks found')];
                }
                $dispatchSequentially = (bool)$SnapinJob->get('abortOnFail');
                $info = [];
                $info['snapins'] = [];
                foreach ($SnapinTasks as &$SnapinTaskData) {
                    $SnapinTask = new SnapinTask($SnapinTaskData->id);
                    if (!$SnapinTask->isValid()) {
                        continue;
                    }
                    $StorageNode = $StorageGroup = null;
                    $Snapin = $SnapinTask->getSnapin();
                    if (!$Snapin->isValid()) {
                        continue;
                    }
                    self::$HookManager->processEvent(
                        'SNAPIN_GROUP',
                        [
                            'Host' => &self::$Host,
                            'Snapin' => &$Snapin,
                            'StorageGroup' => &$StorageGroup,
                        ]
                    );
                    self::$HookManager->processEvent(
                        'SNAPIN_NODE',
                        [
                            'Host' => &self::$Host,
                            'Snapin' => &$Snapin,
                            'StorageNode' => &$StorageNode,
                        ]
                    );
                    if (!($StorageGroup instanceof StorageGroup
                        && $StorageGroup->isValid())
                    ) {
                        $StorageGroup = $Snapin->getStorageGroup();
                        if (!$StorageGroup->isValid()) {
                            continue;
                        }
                    }
                    if (!($StorageNode instanceof StorageNode
                        && $StorageNode->isValid())
                    ) {
                        $StorageNode = $StorageGroup->getMasterStorageNode();
                        if (!$StorageNode->isValid()) {
                            continue;
                        }
                    }
                    if (!property_exists($StorageNode, 'location_url')) {
                        $StorageNode->location_url = sprintf(
                            '%s://%s/%s',
                            self::$httpproto,
                            $StorageNode->get('ip'),
                            $StorageNode->get('webroot')
                        );
                    }
                    if (str_starts_with($StorageNode->location_url, '://')) {
                        $StorageNode->location_url = self::$httpproto . $StorageNode->location_url;
                    }
                    $path = sprintf(
                        '/%s',
                        trim($StorageNode->get('snapinpath'), '/')
                    );
                    $file = $Snapin->get('file');
                    $filepath = sprintf(
                        '%s/%s',
                        $path,
                        $file
                    );
                    $hash = $Snapin->get('hash');
                    $SnapinTask
                        ->set('checkin', $date)
                        ->set('stateID', self::getCheckedInState())
                        ->save();
                    $action = '';
                    if ($Snapin->get('shutdown')) {
                        $action = 'shutdown';
                    } elseif ($Snapin->get('reboot')) {
                        $action = 'reboot';
                    }
                    $size = self::getFilesize($filepath);
                    $info['snapins'][] = [
                        'pack' => (bool)$Snapin->get('packtype'),
                        'hide' => (bool)$Snapin->get('hide'),
                        'timeout' => $Snapin->get('timeout'),
                        'jobtaskid' => $SnapinTask->get('id'),
                        'jobcreation' => $SnapinJob->get('createdTime'),
                        'name' => $Snapin->get('name'),
                        'args' => $Snapin->get('args'),
                        'action' => $action,
                        'filename' => $Snapin->get('file'),
                        'runwith' => $Snapin->get('runWith'),
                        'runwithargs' => $Snapin->get('runWithArgs'),
                        'hash' => strtoupper($hash),
                        'size' => $size,
                        'url' => rtrim($StorageNode->location_url ?? '', '/'),
                    ];
                    unset($Snapin, $SnapinTask);
                    if ($dispatchSequentially) {
                        // Dispatch one snapin per response when sequential tasking is
                        // enabled so failures can stop later snapins.
                        break;
                    }
                }
                if (count($info['snapins']) < 1) {
                    $SnapinJob
                        ->set('stateID', self::getCancelledState())
                        ->save();
                    return ['error' => _('No valid tasks found')];
                }
                return $info;
            } else {
                $this->_closeout($Task, $SnapinJob, $date, $HostName);
            }
        } elseif (basename(self::$scriptname) === 'snapins.file.php') {
            $this->_downloadfile($Task, $SnapinJob, $date, $HostName);
        }
    }

    /**
     * Closes out the snapin tasks
     *
     * @param object $Task the task object
     * @param object $SnapinJob the snapin job object
     * @param string $date the current date
     * @param string $HostName the hostname
     *
     * @return void
     * @throws Exception
     */
    private function _closeout(object $Task, object $SnapinJob, string $date, string $HostName)
    {
        $tID = filter_input(INPUT_POST, 'taskid');
        if (!is_numeric($tID)) {
            $tID = filter_input(INPUT_GET, 'taskid');
        }
        if (!is_numeric($tID)) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid task id sent')
                )
            );
        }
        $SnapinTask = new SnapinTask($tID);
        if (!($SnapinTask->isValid()
            && !in_array(
                $SnapinTask->get('stateID'),
                [
                    self::getCompleteState(),
                    self::getCancelledState()
                ]
            ))
        ) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin Tasking')
                )
            );
        }
        $Snapin = $SnapinTask->getSnapin();
        if (!$Snapin->isValid()) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin')
                )
            );
        }
        $exitcode = filter_input(INPUT_POST, 'exitcode');
        if (!$exitcode) {
            $exitcode = filter_input(INPUT_GET, 'exitcode');
        }
        $exitdesc = filter_input(INPUT_POST, 'exitdesc');
        if (!$exitdesc) {
            $exitdesc = filter_input(INPUT_GET, 'exitdesc');
        }
        if (!is_numeric($exitcode)) {
            $exitdesc = trim(
                sprintf(
                    '%s %s',
                    (string)$exitdesc,
                    _('Invalid exit code received; defaulted to 1')
                )
            );
            $exitcode = 1;
        } else {
            $exitcode = (int)$exitcode;
        }
        // The state changes -- task complete, abort-on-fail cancellation,
        // job and host task closed -- are the same for fog-agent and live
        // in one place, so the two clients cannot drift.
        Snapins::close(self::$Host, $SnapinTask, $exitcode, (string)$exitdesc);
    }

    /**
     * Downloads the client file
     *
     * @param object $Task the task object
     * @param object $SnapinJob the snapin job object
     * @param string $date the current date
     * @param string $HostName the hostname
     *
     * @return void
     * @throws Exception
     */
    private function _downloadfile(object $Task, object $SnapinJob, string $date, string $HostName)
    {
        $tID = filter_input(INPUT_POST, 'taskid');
        if (!is_numeric($tID)) {
            $tID = filter_input(INPUT_GET, 'taskid');
        }
        if (!is_numeric($tID)) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid task id')
                )
            );
        }
        $SnapinTask = new SnapinTask($tID);
        if (!$SnapinTask->isValid()) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin Tasking object')
                )
            );
        }
        // Aisle 009: taskid is caller-supplied and was never bound to the job of
        // the host we resolved from the MAC. Without this, any host with a single
        // queued snapin could enumerate task ids and download every snapin binary
        // on the server (the web tier streams them over its own FTP credentials),
        // while flipping other hosts' SnapinTask rows to in-progress. The genuine
        // client only ever sends back a jobtaskid it received from its own job.
        // Same message as the isValid() failure above, so this is not an oracle
        // for "exists but belongs to someone else".
        if ((int)$SnapinTask->get('jobID') !== (int)$SnapinJob->get('id')) {
            throw new \Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin Tasking object')
                )
            );
        }
        // Node choice, in-progress marking and the FTP-backed stream are
        // shared with fog-agent (Agent\Snapins::stream); does not return.
        Snapins::stream(self::$Host, $SnapinTask);
    }
}
