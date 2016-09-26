<?php
/**
 * Snapin Task Manager mass management class
 *
 * PHP version 5
 *
 * @category SnapinTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin Task Manager mass management class
 *
 * @category SnapinTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTaskManager extends FOGManagerController
{
    /**
     * Cancels the passed tasks
     *
     * @param mixed $snapintaskids the ids to cancel
     *
     * @return void
     */
    public function cancel($snapintaskids)
    {
        $findWhere = array(
            'id' => (array)$snapintaskids
        );
        $cancelled = $this->getCancelledState();
        $this->update(
            $findWhere,
            '',
            array(
                'stateID' => $cancelled,
                'complete'=>$this->formatTime('', 'Y-m-d H:i:s')
            )
        );
    }
}
