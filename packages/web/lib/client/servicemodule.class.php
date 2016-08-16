<?php
class ServiceModule extends FOGClient implements FOGClientSend
{
    public function send()
    {
        $mods = $this->getGlobalModuleStatus(false, true);
        $mod = strtolower(htmlspecialchars($_REQUEST['moduleid'], ENT_QUOTES, 'utf-8'));
        switch ($mod) {
        case 'dircleaner':
            $mod = 'dircleanup';
            break;
        case 'snapin':
            $mod = 'snapinclient';
            break;
        }
        if (!in_array($mod, $mods)) {
            throw new Exception('#!um');
        }
        $globalModules = (!$this->newService ? $this->getGlobalModuleStatus(false, true) : array_diff($this->getGlobalModuleStatus(false, true), array('dircleanup', 'usercleanup', 'clientupdater')));
        $globalInfo = $this->getGlobalModuleStatus();
        $globalDisabled = array();
        array_walk($globalInfo, function (&$en, &$key) use (&$globalDisabled) {
            if ($this->newService && in_array($key, array('dircleanup', 'usercleanup', 'clientupdater'))) {
                return;
            }
            if (!$en) {
                $globalDisabled[] = $key;
            }
        });
        $hostModules = self::getSubObjectIDs('Module', array('id'=>$this->Host->get('modules')), 'shortName');
        $hostEnabled = ($this->newService ? array_diff((array)$hostModules, array('dircleanup', 'usercleanup', 'clientupdater')) : $hostModules);
        $hostDisabled = array_diff((array)$globalModules, $hostEnabled);
        if (in_array($mod, array_merge((array)$globalDisabled, (array)$hostDisabled))) {
            throw new Exception(sprintf("#!n%s\n", in_array($mod, $globalDisabled) ? 'g' : 'h'));
        }
        $this->send = "#!ok\n";
    }
}
