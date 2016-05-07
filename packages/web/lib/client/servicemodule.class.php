<?php
class ServiceModule extends FOGClient implements FOGClientSend {
    public function send() {
        $mod = strtolower(mb_convert_encoding($_REQUEST['moduleid'],'html-entities','utf-8'));
        switch ($mod) {
        case 'dircleaner':
            $mod = 'dircleanup';
            break;
        case 'snapin':
            $mod = 'snapinclient';
            break;
        }
        if (!in_array($mod,$this->getGlobalModuleStatus(false,true))) throw new Exception('#!um');
        $moduleName = $this->getGlobalModuleStatus();
        if (!$moduleName[$mod]) throw new Exception("#!ng\n");
        $modID = self::getSubObjectIDs('Module',array('shortName'=>$mod));
        $activeIDs = $this->Host->get('modules');
        $this->send = sprintf("%s\n",in_array(array_shift($modID),(array)$this->Host->get('modules')) ? '#!ok' : '#!nh');
    }
}
