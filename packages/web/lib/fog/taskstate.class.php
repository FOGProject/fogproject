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

namespace FOG;

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
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskState', 'TaskState');
