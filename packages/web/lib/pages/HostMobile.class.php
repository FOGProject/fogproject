<?php
class HostMobile extends FOGPage {
    public $node = 'hosts';
    public function __construct($name = '') {
        $this->name = 'Host Management';
        // Call parent constructor
        parent::__construct($this->name);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
        // Header Data
        $this->headerData = array(
            $this->foglang[ID],
            $this->foglang[Name],
            $this->foglang[MAC],
            $this->foglang[Image],
        );
        if ($_REQUEST[id]) $this->obj = $this->getClass(Host,$_REQUEST[id]);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${host_id}',
            '${host_name}',
            '${host_mac}',
            '<a href="index.php?node=${node}&sub=deploy&id=${host_id}"><i class="fa fa-arrow-down fa-2x"></i></a>',
        );
    }
    public function index() {$this->search();}
    public function deploy() {
        try {
            // Title
            $this->title = $this->foglang[QuickImageMenu];
            unset($this->headerData);
            $this->attributes = array(
                array(),
            );
            $this->templates = array(
                '${task_started}',
            );
            if (!$this->obj->getImageMemberFromHostID($_REQUEST[id])) throw new Exception($this->foglang[ErrorImageAssoc]);
            if (!$this->obj->createImagePackage('1', "Mobile: ".$this->obj->get(name),false,false,true,false,$_SESSION[FOG_USERNAME])) throw new Exception($this->foglang[FailedTask]);
            $this->data[] = array(
                $this->foglang[TaskStarted],
            );
        } catch (Exception $e) {
            $this->data[] = array(
                $e->getMessage(),
            );
        }
        $this->render();
        $this->FOGCore->redirect('?node=tasks');
    }
    public function search_post() {
        $Hosts = $this->getClass(HostManager)->search();
        foreach ($Hosts AS $i => &$Host) {
            if ($Host->isValid()) {
                $this->data[] = array(
                    host_id => $Host->get(id),
                    host_name => $Host->get(name),
                    host_mac => $Host->get(mac),
                    node => $this->node,
                );
            }
        }
        unset($Host);
        // Ouput
        $this->render();
    }
}
