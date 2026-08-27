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
     * Gets the literal cancelled stated
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
     * Not Cancelled, which was the alternative and is the one thing this
     * must not be confused with -- Cancelled means an administrator stopped
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
}
