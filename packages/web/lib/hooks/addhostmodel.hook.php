<?php
/**
 * Add' the host model to the list.
 *
 * PHP version 5
 *
 * @category AddHostModel
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Add's the host model to the list.
 *
 * @category AddHostModel
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHostModel extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddHostModel';
    /**
     * The description for this host.
     *
     * @var string
     */
    public $description = 'Adds host model to the host lists';
    /**
     * Is the hook active.
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
     * The host data to alter.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $arguments['templates'][] = '${model}';
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
            $arguments['data'][$i]['model'] = $Inventory
                ->get('sysproduct');
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
        $arguments['headerData'][] = _('Model');
    }
}
