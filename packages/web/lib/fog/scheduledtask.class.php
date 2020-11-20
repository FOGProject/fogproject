<?php
/**
 * Scheduled task class.
 *
 * PHP version 5
 *
 * @category ScheduledTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Scheduled task class.
 *
 * @category ScheduledTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ScheduledTask extends FOGController
{
    /**
     * The scheduled task table.
     *
     * @var string
     */
    protected $databaseTable = 'scheduledTasks';
    /**
     * The scheduled task fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'stID',
        'name' => 'stName',
        'description' => 'stDesc',
        'type' => 'stType',
        'taskTypeID' => 'stTaskTypeID',
        'minute' => 'stMinute',
        'hour' => 'stHour',
        'dayOfMonth' => 'stDOM',
        'month' => 'stMonth',
        'dayOfWeek' => 'stDOW',
        'isGroupTask' => 'stIsGroup',
        'hostID' => 'stGroupHostID',
        'shutdown' => 'stShutDown',
        'other1' => 'stOther1',
        'other2' => 'stOther2',
        'other3' => 'stOther3',
        'other4' => 'stOther4',
        'other5' => 'stOther5',
        'scheduleTime' => 'stDateTime',
        'isActive' => 'stActive',
        'imageID' => 'stImageID',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'type',
        'taskTypeID',
        'hostID',
    );
    /**
     * Return the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Return the group object.
     *
     * @return object
     */
    public function getGroup()
    {
        return new Group($this->get('hostID'));
    }
    /**
     * Return the image object.
     *
     * @return object
     */
    public function getImage()
    {
        return new Image($this->get('imageID'));
    }
    /**
     * Get's the timer (specific to cron really.
     *
     * @return object.
     */
    public function getTimer()
    {
        if ($this->get('type') == 'C') {
            $minute = trim($this->get('minute'));
        } else {
            $minute = trim($this->get('scheduleTime'));
        }
        $hour = trim($this->get('hour'));
        $dom = trim($this->get('dayOfMonth'));
        $month = trim($this->get('month'));
        $dow = trim($this->get('dayOfWeek'));
        return new Timer($minute, $hour, $dom, $month, $dow);
    }
    /**
     * Returns if we are multicast.
     *
     * @return bool
     */
    public function isMulticast()
    {
        return (bool)self::getClass('TaskType', $this->get('taskTypeID'))
            ->isMulticast();
    }
    /**
     * Returns the scheduled type.
     *
     * @return string
     */
    public function getScheduledType()
    {
        return $this->get('type') == 'C' ? _('Cron') : _('Delayed');
    }
    /**
     * Returns the task type object.
     *
     * @return object
     */
    public function getTaskType()
    {
        return new TaskType($this->get('taskTypeID'));
    }
    /**
     * Returns if this is group based.
     *
     * @return bool
     */
    public function isGroupBased()
    {
        return (bool)$this->get('isGroupTask') > 0;
    }
    /**
     * Returns if active.
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool)$this->get('isActive') > 0;
    }
    /**
     * Gets the next run time.
     *
     * @return string
     */
    public function getTime()
    {
        return self::niceDate()
            ->setTimestamp(
                (
                    $this->get('type') == 'C' ?
                    FOGCron::parse(
                        sprintf(
                            '%s %s %s %s %s',
                            $this->get('minute'),
                            $this->get('hour'),
                            $this->get('dayOfMonth'),
                            $this->get('month'),
                            $this->get('dayOfWeek')
                        )
                    ) :
                    $this->get('scheduleTime')
                )
            )->format('Y-m-d H:i');
    }
    /**
     * Cancels/Removes the tasking.
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->destroy();
    }
}
