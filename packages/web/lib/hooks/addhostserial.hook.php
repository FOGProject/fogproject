<?php
/**
 * The host serial hook.
 *
 * PHP version 5
 *
 * @category AddHostSerial
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Greg Grammon <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The host serial hook.
 *
 * @category AddHostSerial
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Greg Grammon <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHostSerial extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddHostSerial';
    /**
     * The description of the hook.
     *
     * @var string
     */
    public $description = 'Adds host serial to the host lists';
    /**
     * Is the hook active of not.
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
        $arguments['templates'][] = '${serial}';
        $arguments['attributes'][] = array(
            'class' => 'c',
            'width' => '20',
        );
        $items = $arguments['data'];
        $hostnames = array();
        foreach ((array)$items as &$data) {
            $hostnames[] = $data['host_name'];
            unset($data);
        }
        foreach ((array)self::getClass('HostManager')
            ->find(
                array(
                    'name' => $hostnames
                )
            ) as $i => &$Host
        ) {
            $Inventory = $Host->get('inventory');
            $arguments['data'][$i]['serial'] = $Inventory
                ->get('sysserial');
            unset($Host);
        }
    }
    /**
     * Alter the table header data.
     *
     * @param mixed $arguments The arguments to alter.
     *
     * @return void
     */
    public function hostTableHeader($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $arguments['headerData'][] = _('Serial');
    }
}
