<?php
/**
 * The task state class.
 *
 * PHP version 7.4+
 *
 * @category TaskState
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The task state class.
 *
 * @category TaskState
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskState extends FOGController
{
    /**
     * The task state table.
     *
     * @var string
     */
    protected $databaseTable = 'taskStates';
    /**
     * The task state fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'tsID',
        'name' => 'tsName',
        'description' => 'tsDescription',
        'order' => 'tsOrder',
        'icon' => 'tsIcon'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Gets the icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->get('icon');
    }
    /**
     * Gets the queued states.
     *
     * @return array
     */
    public static function getQueuedStates()
    {
        $queuedStates = range(0, 2);
        self::$HookManager->processEvent(
            'QUEUED_STATES',
            ['queuedStates' => &$queuedStates]
        );
        return $queuedStates;
    }
    /**
     * Gets the literal queued state.
     *
     * @return int
     */
    public static function getQueuedState()
    {
        $queuedState = 1;
        self::$HookManager->processEvent(
            'QUEUED_STATE',
            ['queuedState' => &$queuedState]
        );
        return $queuedState;
    }
    /**
     * Gets the literal checked in state.
     *
     * @return int
     */
    public static function getCheckedInState()
    {
        $checkedInState = 2;
        self::$HookManager->processEvent(
            'CHECKEDIN_STATE',
            ['checkedInState' => &$checkedInState]
        );
        return $checkedInState;
    }
    /**
     * Gets the literal progres state.
     *
     * @return int
     */
    public static function getProgressState()
    {
        $progressState = 3;
        self::$HookManager->processEvent(
            'PROGRESS_STATE',
            ['progressState' => &$progressState]
        );
        return $progressState;
    }
    /**
     * Gets the literal complete state
     *
     * @return int
     */
    public static function getCompleteState()
    {
        $completeState = 4;
        self::$HookManager->processEvent(
            'COMPLETE_STATE',
            ['completeState' => &$completeState]
        );
        return $completeState;
    }
    /**
     * Gets the literal canceled stated
     *
     * @return int
     */
    public static function getCancelledState()
    {
        $cancelledState = 5;
        self::$HookManager->processEvent(
            'CANCELLED_STATE',
            ['cancelledState' => &$cancelledState]
        );
        return $cancelledState;
    }
    /**
     * Gets the literal failed state
     *
     * Added at schema 339. Until then a task the host had died on stayed
     * Queued or In-Progress forever: the report was recorded and announced
     * (GH-1206) but the task list still said the machine was working on it,
     * and the host could not be re-tasked because it still held an active
     * task.
     *
     * Not Canceled, which was the alternative and is the one thing this
     * must not be confused with -- Canceled means an administrator stopped
     * it. Losing the difference between "somebody stopped this" and "this
     * broke" is exactly the distinction an operator needs at the moment
     * they are looking at the task list.
     *
     * Adding a sixth state is safe here because every "is this task live"
     * test in the tree is an ALLOWLIST -- Host::loadTask() and
     * getActiveTaskCount() both ask for getQueuedStates() plus
     * getProgressState() -- so a state nobody listed is inactive by
     * construction. That is what lets this land without touching the 156
     * call sites that enumerate states.
     *
     * @return int
     */
    public static function getFailedState()
    {
        $failedState = 6;
        self::$HookManager->processEvent(
            'FAILED_STATE',
            ['failedState' => &$failedState]
        );
        return $failedState;
    }
    /**
     * The No Report state id, resolved once per request. Null until read.
     *
     * @var int|null
     */
    private static $_noReportId = null;
    /**
     * Gets the literal no report state
     *
     * A task for a host with no host row (an unregistered host) is ended
     * here when 24 hours pass and the FOS image never reported on it. An
     * older FOS init does not report for such a host (forum 18086). Not
     * Failed, because nothing is known to have broken; the server only
     * never heard back.
     *
     * Resolved by NAME, not a fixed id. tsID is AUTO_INCREMENT and an
     * administrator can add states, so a fixed id may already belong to
     * one of theirs; the schema step seeds the row by name (tsName is
     * UNIQUE) and takes whatever id it gets. The lookup is cached for the
     * request.
     *
     * Returns 0 when no such row exists (the schema step has not run).
     * No task has state 0, so a caller that uses it matches nothing
     * rather than guessing at another state's id. The hook can still
     * override the value.
     *
     * Like Failed, it is inactive by construction: every "is this task
     * live" test is an allowlist of queued and progress states.
     *
     * @return int
     */
    public static function getNoReportState()
    {
        // Only a found id is cached: a daemon that started before the
        // schema step must see the row once it exists.
        if (!self::$_noReportId) {
            self::$_noReportId = (int)(new self())
                ->set('name', 'No Report')
                ->load('name')
                ->get('id');
        }
        $noReportState = self::$_noReportId;
        self::$HookManager->processEvent(
            'NO_REPORT_STATE',
            ['noReportState' => &$noReportState]
        );
        return $noReportState;
    }
    /**
     * Gets the states after which nothing more happens to a task.
     *
     * One list for every "is this finished" test, so a new terminal state
     * is added in one place. No Report is left out while it does not
     * resolve (0), so a missing row cannot make state 0 count as finished.
     *
     * @return array of int
     */
    public static function getTerminalStates()
    {
        return array_values(array_filter(
            [
                (int)self::getCompleteState(),
                (int)self::getCancelledState(),
                (int)self::getFailedState(),
                (int)self::getNoReportState()
            ],
            function ($id) {
                return $id > 0;
            }
        ));
    }
}
