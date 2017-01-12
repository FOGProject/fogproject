<?php
/**
 * Legacy client use only just returns the users to cleanup
 *
 * PHP version 5
 *
 * @category UserCleaner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Legacy client use only just returns the users to cleanup
 *
 * @category UserCleaner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserCleaner extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'usercleanup';
    /**
     * Sends the data to the client
     *
     * @return void
     */
    public function send()
    {
        $this->send = "#!start\n";
        foreach ((array)self::getClass('UserCleanupManager')
            ->find() as &$User
        ) {
            $this->send .= sprintf(
                "%s\n",
                base64_encode($User->get('name'))
            );
            unset($User);
        }
        $this->send .= "#!end";
    }
}
