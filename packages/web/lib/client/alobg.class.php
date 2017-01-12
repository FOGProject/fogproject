<?php
/**
 * Sends the auto logout background image
 * NOTE: Only used on legacy client
 *
 * PHP version 5
 *
 * @category ALOGB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends the auto logout background image
 * NOTE: Only used on legacy client
 *
 * @category ALOGB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ALOBG extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'autologout';
    /**
     * Stores the data to send
     *
     * @var string
     */
    protected $send;
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $this->send = self::getSetting('FOG_CLIENT_AUTOLOGOFF_BGIMAGE');
    }
}
