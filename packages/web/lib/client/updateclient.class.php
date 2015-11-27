<?php
class UpdateClient extends FOGClient implements FOGClientSend {
    protected $actions = array('ask','get','list');
    protected $fileActions = array('ask','get');
    public function send() {
        $action = trim(strtolower($_REQUEST['action']));
        if (!in_array($action,$this->actions)) throw new Exception('#!er: '._('Needs action string of ask, get, or list'));
        if (in_array($action,$this->fileActions) && !$_REQUEST['file']) throw new Exception('#!er: '._('If action of ask or get, we need a file name in the request'));
        $file = base64_decode($_REQUEST['file']);
        $findWhere = array('name'=>$file);
        if ($action == 'list') $findWhere = '';
        $ClientUpdateFiles = $this->getClass('ClientUpdaterManager')->find($findWhere);
        switch ($action) {
            case 'ask':
                $ClientUpdateFile = @array_shift($ClientUpdateFiles);
                $this->send = $ClientUpdateFile->get('md5');
                if ($this->newService) $this->send = "#!ok\n#md5=$this->send";
                break;
            case 'get':
                $ClientUpdateFile = @array_shift($ClientUpdateFiles);
                $filename = basename($ClientUpdateFile->get('name'));
                if (!$this->newService) {
                    header('Cache-control: must-revalidate, post-check=0, pre-check=0');
                    header('Content-Description: File Transfer');
                    header('ContentType: application/octet-stream');
                    header("Content-Disposition: attachment; filename=$filename");
                }
                $this->send = $ClientUpdateFile->get('file');
                if ($this->newService) $this->send = "#!ok\n#filename=$filename\n#updatefile=".bin2hex($this->send);
                break;
            case 'list':
                foreach ((array)$ClientUpdateFiles AS $i => &$ClientUpdate) {
                    $filename = base64_encode($ClientUpdate->get('name'))."\n";
                    $this->send = $filename;
                    if ($this->newService) {
                        if (!$i) $this->send = "#!ok\n";
                        $this->send .= "#update$i=$filename";
                    }
                }
                unset($ClientUpdate);
                break;
        }
    }
}
