<?php
class BootTask extends Hook
{
    public $name = 'BootTask';
    public $description = 'Alter the boot task to make a custom task hook';
    public $author = 'Tom Elliott';
    public $active = false;
    public function ChangeTask($arguments)
    {
        if (!isset($arguments['ipxe']['task'])) {
            return;
        }
        $TaskType = self::getClass('TaskType')->set('name', 'trusty-install')->load('name');
        if (!$TaskType->isValid()) {
            return;
        }
        if (!in_array($TaskType->get('id'), array_keys($arguments['ipxe']['task']))) {
            return;
        }
        $arguments['ipxe']['task'][$TaskType->get('id')] = array(
            'set path /OS_IMAGES/ubuntu-14.04.3-DVD',
            'set nfs_path /images/OS_IMAGES/ubuntu-14.04.3-DVD',
            'kernel ${boot-url}${path}/install/netboot/ubuntu-installer/amd64/linux || read void',
            'initrd ${boot-url}${path}/install/netboot/ubuntu-installer/amd64/initrd.gz || read void',
            'imgargs linux root=/dev/nfs boot=casper live-installer/net-image=${boot-url}${path}/install/filesystem.squashfs ks=${boot-url}/OS_IMAGES/kickstarts/precise_ks.cfg ip=dhcp splash quiet - || read void',
            'boot || read void',
        );
        $arguments['Host']->get('task')->set('stateID', $this->getCompleteState())->save();
    }
}
$HookManager->register('IPXE_EDIT', array(new BootTask(), 'ChangeTask'));
