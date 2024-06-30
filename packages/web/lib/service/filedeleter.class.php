<?php
/**
 * Handles file deletetion queued tasks.
 *
 * PHP version 5
 *
 * @category FileDeleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles file deletetion queued tasks.
 *
 * @category FileDeleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileDeleter extends FOGService
{
    /**
     * Is the host lookup/ping enabled
     *
     * @var int
     */
    private static $_schedOn = 0;
    /**
     * Contains the string holding the service's sleep cycle
     *
     * @var string
     */
    public static $sleeptime = 'FILEDELETEQUEUESLEEPTIME';
    /**
     * Initializes The services environment
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $schedulerkeys = [
            'FILEDELETEQUEUEDEVICEOUTPUT',
            'FILEDELETEQUEUELOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($schedulerkeys);
        static::$log = sprintf(
            '%s%s',
            self::$logpath ?
            self::$logpath :
            '/opt/fog/log/',
            $log ?
            $log :
            'fogfiledeletequeue.log'
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty3'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            14400
        );
    }
    /**
     * Makes the output for this service
     *
     * @return void
     */
    private function _commonOutput()
    {
        try {
            self::$_schedOn = self::getSetting('FILEDELETEQUEUEGLOBALENABLED');
            if (self::$_schedOn < 1) {
                throw new Exception(_(' * File delete queue is globally disabled'));
            }
            Route::active('filedeletequeue');
            $filedeletes = json_decode(Route::getData());

            $taskCount = $filedeletes->recordsFiltered;

            self::outall(
                sprintf(
                    " * %s item%s to delete found.",
                    $taskCount,
                    (
                        $taskCount === 1 ?
                        '' :
                        's'
                    )
                )
            );
            unset($taskCount);
            $currTime = time();
            foreach ($filedeletes->data as $filedelete) {
                $createdTime = strtotime($filedelete->createdTime);
                $timediff = $currTime - $createdTime;
                $toRunTime = self::$zzz - $timediff;
                if ($toRunTime > 0) {
                    self::outall(
                        sprintf(
                            "%s %d %s.\n\t - %s: %s\n\t - %s: %s\n\t - %s: %s",
                            _('Found a task that we will wait on completing for'),
                            $toRunTime,
                            $toRunTime != 1 ? _('seconds') : _('second'),
                            _('File Path Type'),
                            $filedelete->pathtype,
                            _('File Path'),
                            $filedelete->path,
                            _('Created Time'),
                            $filedelete->createdTime
                        )
                    );
                    continue;
                }
                self::outall(
                    sprintf(
                        "%s.\n\t - %s: %s\n\t - %s: %s\n\t - %s: %s",
                        _('Found a task that we will get deleted'),
                        _('File Path Type'),
                        $filedelete->pathtype,
                        _('File Path'),
                        $filedelete->path,
                        _('Created Time'),
                        $filedelete->createdTime
                    )
                );
                $Task = self::getClass('filedeletequeue', $filedelete->id)
                    ->set('stateID', self::getProgressState())
                    ->save();
                Route::listem(
                    'storagenode',
                    [
                        'storagegroupID' => $filedelete->storagegroupID,
                        'isEnabled' => 1
                    ]
                );
                $StorageNodes = json_decode(Route::getData());
                foreach ($StorageNodes->data as $StorageNode) {
                    switch ($filedelete->pathtype) {
                        case 'Image':
                        case 'image':
                            $filepath = $StorageNode->ftppath;
                            break;
                        case 'Snapin':
                        case 'snapin':
                            $filepath = $StorageNode->snapinpath;
                            break;
                    }
                    $filepath = rtrim(
                        $filepath,
                        DS
                    );
                    $deleteFile = $filepath . DS . $filedelete->path;
                    $ip = $StorageNode->ip;
                    $user = $StorageNode->user;
                    $pass = $StorageNode->pass;
                    self::$FOGSSH->username = $user;
                    self::$FOGSSH->password = $pass;
                    self::$FOGSSH->host = $ip;
                    if (!self::$FOGSSH->connect()) {
                        self::outall(
                            sprintf(
                                '%s: %s. %s.',
                                _('Failed to SSH to Storage Node Named'),
                                $StorageNode->name,
                                _('Skipping, will need manual removal')
                            )
                        );
                        continue;
                    } else if (!self::$FOGSSH->exists($deleteFile)) {
                        self::outall(
                            sprintf(
                                '%s: %s. %s: %s. %s.',
                                _('Nothing to do on Storage Node Named'),
                                $StorageNode->name,
                                _('File Path did not exist, but should have been'),
                                $deleteFile,
                                _('Skipping as there is nothing to do')
                            )
                        );
                    } else if (!self::$FOGSSH->delete($deleteFile)) {
                        self::outall(
                            sprintf(
                                '%s: %s. %s: %s. %s.',
                                _('Failed to remove File Path from Storage Node Named'),
                                $StorageNode->name,
                                _('File Path to delete was'),
                                $deleteFile,
                                _('Skipping, will need manual removal')
                            )
                        );
                    } else {
                        self::outall(
                            sprintf(
                                '%s: %s. %s: %s.',
                                _('File Path successfully removed from Storage Node Named'),
                                $StorageNode->name,
                                _('File Path removed was'),
                                $deleteFile
                            )
                        );
                    }
                    if (!self::$FOGSSH->disconnect()) {
                        continue;
                    }
                }
                $Task
                    ->set('completedTime', self::formatTime('now', 'Y-m-d H:i:s'))
                    ->set('stateID', self::getCompleteState())
                    ->save();
            }
        } catch (Exception $e) {
            self::outall($e->getMessage());
        }
    }
    /**
     * Runs the service
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_commonOutput();
        parent::serviceRun();
    }
}
