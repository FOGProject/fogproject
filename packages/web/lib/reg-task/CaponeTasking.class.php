<?php
class CaponeTasking extends FOGBase {
    protected $actions = array('dmi','imagelookup');
    protected $imgTypes = array(1=>n,2=>mps,3=>mpa,4=>dd);
    public function __construct() {
        parent::__construct();
        switch (strtolower($_REQUEST[action])) {
        case 'dmi':
            echo $this->FOGCore->getSetting(FOG_PLUGIN_CAPONE_DMI);
            break;
        case 'imagelookup':
            if ($_REQUEST[key]) {
                try {
                    $strSetup = "%s|%s|%s|%s|%s";
                    $ret = array();
                    $key = trim(base64_decode($_REQUEST[key]));
                    $Capones = $this->getClass(CaponeManager)->find(array(key=>$key));
                    foreach ($Capones AS $i => &$Capone) {
                        $Image = $this->getClass(Image,$Capone->get(imageID));
                        $OS = $this->getClass(OS,$Capone->get(osID));
                        $StorageGroup = $Image->getStorageGroup();
                        $StorageNode = $StorageGroup->getMasterStorageNode();
                        $ret[] = base64_encode(sprintf($strSetup,$Image->get(path),$OS->get(id),$this->imgTypes[$Image->get(imageTypeID)],$Image->getImagePartitionType()->get(type),$Image->get(format)));
                    }
                    unset($Capone);
                    throw new Exception(count($ret) ? implode("\n",(array)$ret) : base64_encode('null'));
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
            break;
        }
    }
}
