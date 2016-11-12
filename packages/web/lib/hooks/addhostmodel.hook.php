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
        $arguments['templates'][5] = '${model}';
        $arguments['attributes'][5] = array(
            'widht' => 20,
            'class' => 'c'
        );
        $items = $arguments['data'];
        $hostnames = array();
        foreach ((array)$items as &$data) {
            $hostnames[] = $data['host_name'];
            unset($data);
        }
        $Hosts = self::getClass('HostManager')
            ->find(
                array(
                    'name' => $hostnames
                )
            );
        foreach ((array)$Hosts as $i => &$Host) {
            if (!$Host->isValid()) {
                continue;
            }
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
        $arguments['headerData'][5] = _('Model');
    }
}
$AddHostModel = new AddHostModel();
$HookManager
    ->register(
        'HOST_DATA',
        array(
            $AddHostModel,
            'hostData'
        )
    );
$HookManager
    ->register(
        'HOST_HEADER_DATA',
        array(
            $AddHostModel,
            'hostTableHeader'
        )
    );
