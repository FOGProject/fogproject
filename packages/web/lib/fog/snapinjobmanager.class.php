<?php
/**
 * The snapin job handler class.
 *
 * PHP version 5
 *
 * @category SnapinJob
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin job handler class.
 *
 * @category SnapinJob
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinJobManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'snapinJobs';
    /**
     * Cancels the snapin job.
     *
     * This used to cancel only the job's TASKS and let the job's own state
     * follow from them: it worked out the task ids, handed them to
     * SnapinTaskManager::cancel(), and returned. That delegate is what marked
     * the job cancelled, by counting the tasks each job had left once it had
     * cancelled the ones it was given.
     *
     * Which meant a job with no tasks could not be cancelled at all. Deleting
     * a snapin removes its snapintask rows and then asks for the cancel, so
     * there was nothing left to count and the job sat Queued forever against a
     * snapin that no longer exists. Both delete paths hit this --
     * Snapin::destroy() and deletemass()'s `case 'snapin'` -- so it was never
     * an API-only fault. The give-away was in the old body: $findWhere and
     * $cancelled were both computed and then thrown away, which is the direct
     * job update that was meant to be here and never was.
     *
     * So the job is now cancelled directly. That works with or without tasks,
     * and on the path where tasks do exist it is a harmless re-write of a state
     * the delegate has already set.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/885
     *
     * @param array $snapinjobids The jobs to cancel.
     *
     * @return bool
     */
    public function cancel($snapinjobids)
    {
        $snapinjobids = array_filter((array) $snapinjobids);
        if (!count($snapinjobids)) {
            return true;
        }
        $cancelled = self::getCancelledState();
        $activeStates = self::fastmerge(
            (array) self::getQueuedStates(),
            (array) self::getProgressState()
        );
        // Tasks first, where there still are any -- unchanged behaviour, and
        // what callers cancelling a live job still rely on.
        $snapintaskids = Route::getIds(
            'snapintask',
            ['jobID' => $snapinjobids]
        );
        if (count($snapintaskids ?: [])) {
            self::getClass('SnapinTaskManager')
                ->cancel($snapintaskids);
        }
        // Only jobs still queued or running, so a job that already finished is
        // not rewritten to Cancelled after the fact.
        $toCancel = Route::getIds(
            'snapinjob',
            [
                'id' => $snapinjobids,
                'stateID' => $activeStates
            ]
        );
        if (!count($toCancel ?: [])) {
            return true;
        }
        $this->update(
            ['id' => $toCancel],
            '',
            ['stateID' => $cancelled]
        );
        // A host sitting on a snapin-ONLY task has nothing left to do once its
        // job is cancelled, so the task goes with it. SnapinTaskManager already
        // does this on the path where tasks exist; mirrored here for the path
        // where they have been deleted, or the host would keep an active task
        // pointing at a cancelled job.
        //
        // Guarded rather than trusted: get('host') on a job whose host row is
        // gone returns a string, and calling into that is the same fatal that
        // breaks the snapintask list endpoint.
        $hostTasks = [];
        foreach ((array) $toCancel as $jobID) {
            $left = Route::getCount(
                'snapintask',
                [
                    'jobID' => $jobID,
                    'stateID' => $activeStates
                ]
            );
            if ($left > 0) {
                continue;
            }
            $Host = self::getClass('snapinjob', $jobID)->get('host');
            if (!is_object($Host) || !$Host->isValid()) {
                continue;
            }
            $Task = $Host->get('task');
            if (!is_object($Task) || !$Task->isValid()) {
                continue;
            }
            if (in_array($Task->get('typeID'), TaskType::SNAPINTASKS)) {
                $hostTasks[] = $Task->get('id');
            }
        }
        if (count($hostTasks ?: [])) {
            self::getClass('TaskManager')
                ->update(
                    ['id' => $hostTasks],
                    '',
                    [
                        'stateID' => $cancelled,
                        'stateChangedTime' => self::niceDate()
                            ->format('Y-m-d H:i:s')
                    ]
                );
        }
        return true;
    }
}
