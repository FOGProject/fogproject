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
     * Cancels the snapin job.
     *
     * @param array $snapinjobids The jobs to cancel.
     *
     * @return bool
     */
    public function cancel($snapinjobids)
    {
        $findWhere = array('id' => (array) $snapinjobids);
        $cancelled = $this->getCancelledState();

        return $this->update(
            $findWhere,
            '',
            array('stateID' => $cancelled)
        );
    }
}
