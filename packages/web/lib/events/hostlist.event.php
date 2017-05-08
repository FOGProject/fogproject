<?php
/**
 * Host list event
 *
 * PHP version 5
 *
 * @category HostList_Event
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host list event
 *
 * @category HostList_Event
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostList extends Event
{
    public $name = 'HostListEvent';
    public $description = 'Triggers when the hosts are listed';
    public $active = false;
    /**
     * Initialize our item.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$EventManager->register(
            'HOST_LIST_EVENT',
            $this
        );
    }
}
