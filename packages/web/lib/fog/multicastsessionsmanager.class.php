<?php
/**
 * Multicast session manager mass management class
 *
 * PHP version 5
 *
 * @category MulticastSessionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Multicast session manager mass management class
 *
 * @category MulticastSessionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSessionsManager extends FOGManagerController
{
    /**
     * Cancels all passed tasks
     *
     * @param mixed $multicastsessionids the id's to cancel
     *
     * @return void
     */
    public function cancel($multicastsessionids)
    {
        $findWhere = array(
            'id' => (array)$multicastsessionids
        );
        $cancelled = $this->getCancelledState();
        $this->update(
            $findWhere,
            '',
            array(
                'stateID' => $cancelled,
                'name' => ''
            )
        );
        $this->arrayChangeKey(
            $findWhere,
            'id',
            'msID'
        );
        self::getClass('MulticastSessionsAssociationManager')
            ->destroy($findWhere);
    }
}
