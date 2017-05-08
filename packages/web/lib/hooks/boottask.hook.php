<?php
/**
 * Alters the boot task to make a custom entry.
 *
 * PHP version 5
 *
 * @category BootTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Alters the boot task to make a custom entry.
 *
 * @category BootTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class BootTask extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'BootTask';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Alter the boot task to make a custom task hook';
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
        self::$HookManager
            ->register(
                'IPXE_EDIT',
                array(
                    $this,
                    'changeTask'
                )
            );
    }
    /**
     * Change the task.
     *
     * @param mixed $arguments The items to alter.
     *
     * @return void
     */
    public function changeTask($arguments)
    {
        if (!isset($arguments['ipxe']['task'])) {
            return;
        }
        $TaskType = self::getClass('TaskType')
            ->set('name', 'trusty-install')
            ->load('name');
        if (!$TaskType->isValid()) {
            return;
        }
        $keys = array_keys($arguments['ipxe']['task']);
        if (!in_array($TaskType->get('id'), $keys)) {
            return;
        }
        $arguments['ipxe']['task'][$TaskType->get('id')] = array(
            'set path /OS_IMAGES/ubuntu-14.04.3-DVD',
            'set nfs_path /images/OS_IMAGES/ubuntu-14.04.3-DVD',
            'kernel ${boot-url}${path}/install/netboot/ubuntu'
            . '-installer/amd64/linux || read void',
            'initrd ${boot-url}${path}/install/netboot/ubuntu-installer'
            . '/amd64/initrd.gz || read void',
            'imgargs linux root=/dev/nfs boot=casper live-installer'
            . '/net-image=${boot-url}${path}/install/filesystem.squashfs '
            . 'ks=${boot-url}/OS_IMAGES/kickstarts/precise_ks.cfg '
            . 'ip=dhcp splash quiet - || read void',
            'boot || read void',
        );
        $arguments['Host']
            ->get('task')
            ->set(
                'stateID',
                self::getCompleteState()
            )->save();
    }
}
