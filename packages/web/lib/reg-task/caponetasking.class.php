<?php
class CaponeTasking extends FOGBase {
    protected $actions = array('dmi','imagelookup');
    protected $imgTypes = array(1=>'n',2=>'mps',3=>'mpa',4=>'dd');
    public function __construct() {
        parent::__construct();
        switch (strtolower($_REQUEST['action'])) {
        case 'dmi':
            echo $this->getSetting('FOG_PLUGIN_CAPONE_DMI');
            break;
        case 'imagelookup':
            if (!$_REQUEST['key']) break;
            try {
                $strSetup = "%s|%s|%s|%s|%s";
                ob_start();
                foreach ((array)self::getClass('CaponeManager')->find(array('key'=>trim(base64_decode($_REQUEST['key'])))) AS $i => &$Capone) {
                    if (!$Capone->isValid()) continue;
                    printf("%s\n",
                        base64_encode(sprintf($strSetup,
                        $Capone->getImage()->get('path'),
                        $Capone->getOS()->get('id'),
                        $this->imgTypes[$Capone->getImage()->get('imageTypeID')],
                        $Capone->getImage()->getImagePartitionType()->get('type'),
                        $Capone->get('format')
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
