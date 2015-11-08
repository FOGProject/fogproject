<?php
class HostMobile extends FOGPage {
    public $node = 'host';
    public function __construct($name = '') {
        $this->name = 'Host Management';
        parent::__construct($this->name);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
        $this->headerData = array(
            $this->foglang['ID'],
            $this->foglang['Name'],
            $this->foglang['MAC'],
            $this->foglang['Image'],
        );
        if ($_REQUEST['id']) $this->obj = $this->getClass('Host',$_REQUEST['id']);
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $icon = $this->getClass('TaskType',1)->get('icon');
        $this->templates = array(
            '${host_id}',
            '${host_name}',
            '${host_mac}',
            '<a href="index.php?node=${node}&sub=deploy&id=${host_id}"><i class="fa fa-'.$icon.' fa-2x"></i></a>',
        );
    }
    public function index() {
        $this->search();
    }
    public function deploy() {
        try {
            $this->title = $this->foglang['QuickImageMenu'];
            unset($this->headerData);
            $this->attributes = array(
                array(),
            );
            $this->templates = array(
                '${task_started}',
            );
            if (!$this->obj->getImageMemberFromHostID($_REQUEST['id'])) throw new Exception($this->foglang['ErrorImageAssoc']);
            if (!$this->obj->createImagePackage('1', "Mobile: ".$this->obj->get('name'),false,false,true,false,$_SESSION['FOG_USERNAME'])) throw new Exception($this->foglang['FailedTask']);
            $this->data[] = array(
                $this->foglang['TaskStarted'],
            );
        } catch (Exception $e) {
            $this->data[] = array(
                $e->getMessage(),
            );
        }
        $this->render();
        $this->redirect('?node=tasks');
    }
    public function search_post() {
        foreach ((array)$this->getClass('HostManager')->search('',true) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'host_id' => $Host->get('id'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'node' => $this->node,
            );
            unset($Host);
        }
        $this->render();
    }
}
