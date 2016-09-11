<?php
/**
 * A basic interface to define how client classes should operate
 *
 * PHP version 5
 *
 * @category FOGClientSend
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * A basic interface to define how client classes should operate
 *
 * @category FOGClientSend
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
interface FOGClientSend
{
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send();
}
