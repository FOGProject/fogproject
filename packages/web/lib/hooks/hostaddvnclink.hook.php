<?php
/**
 * Add host VNC link.
 *
 * PHP version 5
 *
 * @category Add VNC Link
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Add host VNC link
 *
 * @category Add VNC Link
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostAddVNCLink extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'HostAddVNCLink';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Adds Host VNC Link to the Host api call';
    /**
     * The default vnc port to use for connecting.
     *
     * @var int
     */
    private static $_port = 5800;
    /**
     * Is this hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'API_MASSDATA_MAPPING',
            [$this, 'hostData']
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
        if ('host' !== strtolower($arguments['classname'])) {
            return;
        }
        foreach ($arguments['data']['data'] as $i => &$data) {
            $arguments['data']['data'][$i]['vnclink'] = sprintf(
                '<a href="vnc://%s:%d" target="_blank" title='
                . '"%s: %s">%s</a>',
                $data['name'],
                self::$_port,
                _('Open VNC Connection To'),
                $data['name'],
                _('VNC')
            );
            unset($data);
        }
    }
}
