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
        $arguments['templates'][7] = '${serial}';
        $arguments['attributes'][7] = array(
            'width' => 20,
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
        $arguments['headerData'][7] = _('Serial');
    }
}
$AddHostSerial = new AddHostSerial();
$HookManager
    ->register(
        'HOST_DATA',
        array(
            $AddHostSerial,
            'hostData'
        )
    );
$HookManager
    ->register(
        'HOST_HEADER_DATA',
        array(
            $AddHostSerial,
            'hostTableHeader'
        )
    );
