<?php
/**
 * Scheduled task manager class.
 *
 * PHP version 5
 *
 * @category ScheduledTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Scheduled task manager class.
 *
 * @category ScheduledTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ScheduledTaskManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'scheduledTasks';
    /**
     * Cancels the passed tasks
     *
     * @param mixed $scheduledtaskids the ids to cancel
     *
     * @return bool
     */
    public function cancel($scheduledtaskids)
    {
        return Route::deletemass(
            'scheduledtask',
            ['id' => $scheduledtaskids]
        );
    }
}
