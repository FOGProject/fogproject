<?php
/**
 * Handles file deletetion queued tasks.
 *
 * PHP version 7.4+
 *
 * @category FileDeleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Items\FileDeleteQueue;
use FOG\Router\Route;

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
     * Fallback sleep when the globalSetting above is unset.
     *
     * @var int
     */
    public static $sleepdefault = 60;
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
            FOG_LOG_DIR . DS,
            $log ?
            $log :
            'fogfiledeletequeue.log'
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
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
     * Return more human friendly time.
     *
     * @param int    $diff the difference passed
     * @param string $unit the unit of time (minute, hour, etc...)
     *
     * @throws Exception
     *
     * @return string
     */
    private static function humanifyRun($diff, $unit)
    {
        if (!is_numeric($diff)) {
            throw new \Exception(_('Diff parameter must be numeric'));
        }
        if (!is_string($unit)) {
            throw new \Exception(_('Unit of time must be a string'));
        }
        $before = $after = '';
        if ($diff < 0) {
            $before = sprintf('%s ', _('in'));
        }
        if ($diff < 0) {
            $after = sprintf(' %s', _('from now'));
        }
        $diff = floor(abs($diff));
        if ($diff != 1) {
            $unit .= 's';
        }

        return sprintf(
            '%s%d %s%s',
            $before,
            $diff,
            $unit,
            $after
        );
    }
    private static function formatRunTime($time)
    {
        if (!$time instanceof \DateTime) {
            $time = self::niceDate($time);
        }
        $now = self::niceDate('now');
        $diff = $now->format('U') - $time->format('U');
        $absolute = abs($diff);
        if (is_nan($diff)) {
            return _('Not a number');
        }
        if (!self::validDate($time)) {
            return _('No Data');
        }
        $date = $time->format('Y/m/d');
        if ($now->format('Y/m/d') == $date) {
            if (0 <= $diff && $absolute < 60) {
                return _('moments from now');
            } elseif ($diff < 0 && $absolute < 60) {
                return _('seconds ago');
            } elseif ($absolute <= 3600) {
                return self::humanifyRun($diff / 60, 'minute');
            } elseif ($absolute <= 86400) {
                return self::humanifyRun($diff / 60 / 60, 'hour');
            } else {
                return self::humanifyRun($diff / 60 / 60 / 24, 'day');
            }
        }
    }
    /**
     * Makes the output for this service
     *
     * @return void
     */
    private function _commonOutput()
    {
        try {
            self::$_schedOn = (int) self::getSetting('FILEDELETEQUEUEGLOBALENABLED');
            if (self::$_schedOn < 1) {
                throw new \Exception(_(' * File delete queue is globally disabled'));
            }
            // asValue(), not getList(): the count below comes off the
            // envelope, so the envelope has to survive. What this buys is the
            // other half -- a failure raises instead of ending the daemon.
            $filedeletes = Route::asValue(
                function () {
                    Route::active('filedeletequeue');
                }
            );

            $taskCount = $filedeletes->recordsFiltered;

            self::outall(
                sprintf(
                    " * %s %s to delete found.",
                    $taskCount,
                    $taskCount === 1 ? _('item') : _('items')
                )
            );
            unset($taskCount);
            $currTime = time();
            foreach ($filedeletes->data as $filedelete) {
                $createdtime = strtotime($filedelete->createdTime);
                $timediff = $currTime - $createdtime;
                $toRunTime = self::$zzz - $timediff;
                $createdTime = self::niceDate($filedelete->createdTime);
                $timeDiff = clone $createdTime;
                $timeDiff->modify('+'.$toRunTime+self::$zzz.' seconds');
                if ($toRunTime > 0) {
                    self::outall(
                        sprintf(
                            "%s %s.\n\t - %s: %s\n\t - %s: %s\n\t - %s: %s",
                            _('Found an item that we will wait on completing'),
                            self::formatRunTime($timeDiff),
                            _('File Path Type'),
                            $filedelete->pathtype,
                            _('File Path Basename'),
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
                        _('Found an item that we will get deleted'),
                        _('File Path Type'),
                        $filedelete->pathtype,
                        _('File Path Basename'),
                        $filedelete->path,
                        _('Created Time'),
                        $filedelete->createdTime
                    )
                );
                $Task = (new FileDeleteQueue($filedelete->id))
                    ->set('stateID', self::getProgressState())
                    ->save();
                $StorageNodes = Route::getList(
                    'storagenode',
                    [
                        'storagegroupID' => $filedelete->storagegroupID,
                        'isEnabled' => 1
                    ]
                );
                foreach ($StorageNodes as $StorageNode) {
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
                    $queued = str_replace('\\', '/', trim((string)$filedelete->path));
                    // \.\.? matches a path component that is exactly
                    // '.' or exactly '..'. The bare '.' case was the
                    // gap: a snapin row could be persisted with
                    // file='.' (snapinfileexist took any value), and
                    // deleting it queued the snapin directory itself
                    // for a root-level recursive delete on every
                    // enabled node in the group (035 / 2.3.1).
                    // Ordinary dotted names (pkg.tar.gz) are unaffected
                    // -- the component must be only dots and bounded
                    // by '/' or the string ends.
                    if ($queued === ''
                        || preg_match('#^/#', $queued)
                        || preg_match('#(^|/)\\.\\.?(/|$)#', $queued)
                    ) {
                        self::outall(_('Skipping unsafe queued delete path'));
                        continue;
                    }
                    $deleteFile = rtrim($filepath, DS) . DS . ltrim($queued, '/');
                    $ip = $StorageNode->ip;
                    $user = $StorageNode->user;
                    $pass = $StorageNode->pass;
                    self::$FOGSSH->username = $user;
                    self::$FOGSSH->password = $pass;
                    self::$FOGSSH->host = $ip;
                    if (!self::$FOGSSH->connect()) {
                        self::outall(
                            sprintf(
                                "%s: %s.\n\t - %s.",
                                _('Failed to SSH to Storage Node Named'),
                                $StorageNode->name,
                                _('Skipping, will need manual removal')
                            )
                        );
                        continue;
                    } elseif (!self::$FOGSSH->exists($deleteFile)) {
                        self::outall(
                            sprintf(
                                "%s: %s.\n\t - %s: %s.\n\t - %s.",
                                _('Nothing to do on Storage Node Named'),
                                $StorageNode->name,
                                _('File Path did not exist, but should have been'),
                                $deleteFile,
                                _('Skipping as there is nothing to do')
                            )
                        );
                    } elseif (!self::$FOGSSH->delete($deleteFile)) {
                        // This branch was unreachable until FOGSSH::delete()
                        // started answering with a bool: it returned $this,
                        // which is always truthy, so every failed removal was
                        // reported below as a successful one and the path was
                        // dequeued regardless.
                        self::outall(
                            sprintf(
                                "%s: %s.\n\t -  %s: %s.\n\t - %s.",
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
                                "%s: %s.\n\t - %s: %s.",
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
                    ->set('completedTime', self::storageNow())
                    ->set('stateID', self::getCompleteState())
                    ->save();
            }
        } catch (\Exception $e) {
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
