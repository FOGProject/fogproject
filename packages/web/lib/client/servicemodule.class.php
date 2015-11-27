<?php
class ServiceModule extends FOGClient implements FOGClientSend {
    public function send() {
        $moduleID = $this->getClass('Module',@max($this->getSubObjectIDs('Module',array('shortName'=>$_REQUEST['moduleid']))));
        if (!$moduleID->isValid()) {
            switch (strtolower($_REQUEST['moduleid'])) {
                case 'dircleaner':
                case 'dircleanup':
                    $_REQUEST['moduleid'] = array('dircleanup','dircleaner');
                    break;
                case 'snapin':
                case 'snapinclient':
                    $_REQUEST['moduleid'] = array('snapin','snapinclient');
                    break;
            }
            $moduleID = $this->getClass('Module',@max($this->getSubObjectIDs('Module',array('shortName'=>$_REQUEST['moduleid']),'','','OR')));
        }
        if (!$moduleID->isValid()) throw new Exception('#!um');
        $moduleName = $this->getClass('HostManager')->getGlobalModuleStatus();
        if (!$moduleName[$moduleID->get('shortName')]) throw new Exception("#!ng\n");
        $activeIDs = $this->Host->get('modules');
        $this->send = (in_array($moduleID->get('id'),(array)$activeIDs) ? '#!ok' : '#!nh')."\n";
    }
}
