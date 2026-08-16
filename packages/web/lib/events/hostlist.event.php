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

namespace FOG;

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
    /**
     * Name of event.
     *
     * @var string
     */
    public $name = 'HostListEvent';
    /**
     * Description of event.
     *
     * @var string
     */
    public $description = 'Triggers when the hosts are listed';
    /**
     * Status of event.
     *
     * @var string
     */
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\HostList', 'HostList');
