<?php
/**
 * Scheduled task manager class.
 *
 * PHP version 7.4+
 *
 * @category ScheduledTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Router\Route;

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
     * @return object|null whatever Route::deletemass() gave back
     */
    public function cancel($scheduledtaskids)
    {
        return Route::deletemass(
            'scheduledtask',
            ['id' => $scheduledtaskids]
        );
    }
}
