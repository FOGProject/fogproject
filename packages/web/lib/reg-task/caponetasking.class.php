<?php
class CaponeTasking extends FOGBase
{
    protected $actions = array('dmi','imagelookup');
    protected $imgTypes = array(1=>'n',2=>'mps',3=>'mpa',4=>'dd');
    public function __construct()
    {
        parent::__construct();
        switch (strtolower($_REQUEST['action'])) {
        case 'dmi':
            echo self::getSetting('FOG_PLUGIN_CAPONE_DMI');
            break;
        case 'imagelookup':
            if (!isset($_REQUEST['key']) || empty($_REQUEST['key'])) {
                break;
            }
            try {
                $strSetup = "%s|%s|%s|%s|%s|%s|%s";
                ob_start();
                foreach ((array)self::getClass('CaponeManager')->find(array('key'=>trim(base64_decode($_REQUEST['key'])))) as $i => &$Capone) {
                    if (!$Capone->isValid()) {
                        continue;
                    }
                    $Image = $Capone->getImage();
                    if (!$Image->isValid()) {
                        continue;
                    }
                    $OS = $Image->getOS();
                    if (!$OS->isValid()) {
                        continue;
                    }
                    $StorageNode = $Image->getStorageGroup()->getOptimalStorageNode();
                    if (!$StorageNode->isValid()) {
                        continue;
                    }
                    printf("%s\n",
                        base64_encode(sprintf($strSetup,
                        $Capone->getImage()->get('path'),
                        $Capone->getOS()->get('id'),
                        $this->imgTypes[$Capone->getImage()->get('imageTypeID')],
                        $Capone->getImage()->getImagePartitionType()->get('type'),
                        $Capone->get('format') ? '1' : '0',
                        sprintf('%s:%s', $StorageNode->get('ip'), $StorageNode->get('path')),
                        $StorageNode->get('ip')
                    )));
                    unset($Capone);
                }
                throw new Exception(ob_get_contents() ? ob_get_clean() : base64_encode(null));
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            break;
        }
    }
}
