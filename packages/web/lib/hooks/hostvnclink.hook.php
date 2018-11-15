<?php
/**
 * Displays the vnc link on hosts.
 *
 * PHP version 5
 *
 * @category HostVNCLink
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the vnc link on hosts.
 *
 * @category HostVNCLink
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostVNCLink extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'HostVNCLink';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Adds a "VNC" link to the Host Lists';
    /**
     * Is this hook active?
     *
     * @var bool
     */
    public $active = false;
    /**
     * Port to use for the link.
     *
     * @var int
     */
    public $port = 5800;
    /**
     * Iniatializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'HOST_DATA',
                array(
                    $this,
                    'hostData'
                )
            )
            ->register(
                'HOST_HEADER_DATA',
                array(
                    $this,
                    'hostTableHeader'
                )
            );
    }
    /**
     * The data to alter.
     *
     * @param mixed $arguments The items to alter.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $arguments['templates'][]
            = sprintf(
                '<a href="vnc://%s:%d" target="_blank" title='
                . '"%s: ${host_name}">VNC</a>',
                '${host_name}',
                $this->port,
                _('Open VNC connection to')
            );
        $arguments['attributes'][] = array('class' => 'c');
    }
    /**
     * The table header to alter
     *
     * @param mixed $arguments The items to alter.
     *
     * @return void
     */
    public function hostTableHeader($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $arguments['headerData'][] = _('VNC');
    }
}
