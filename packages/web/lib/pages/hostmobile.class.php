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
            self::$foglang['ID'],
            self::$foglang['Name'],
            self::$foglang['MAC'],
            self::$foglang['Image'],
        );
        if ($_REQUEST['id']) $this->obj = self::getClass('Host',$_REQUEST['id']);
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $icon = self::getClass('TaskType',1)->get('icon');
        $this->templates = array(
            '${id}',
            '${host_name}',
            '${host_mac}',
            sprintf('<a href="index.php?node=${node}&sub=deploy&id=${id}"><i class="fa fa-%s fa-2x"></i></a>',$icon),
        );
        $this->returnData = function(&$Host) {
            if (!$Host->isValid()) return;
            $this->data[] = array(
                'id'=>$Host->get('id'),
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac')->__toString(),
                'node' => $this->node,
            );
            unset($Host);
        };
    }
    public function index() {
        $this->search();
    }
    public function deploy() {
        try {
            $this->title = self::$foglang['QuickImageMenu'];
            unset($this->headerData);
            $this->attributes = array(array());
            $this->templates = array('${task_started}');
            $this->data = array();
            if (!$this->obj->getImageMemberFromHostID($_REQUEST['id'])) throw new Exception(self::$foglang['ErrorImageAssoc']);
            if (!$this->obj->createImagePackage('1', "Mobile: {$this->obj->get(name)}",false,false,true,false,$_SESSION['FOG_USERNAME'])) throw new Exception(self::$foglang['FailedTask']);
            $this->data[] = array(self::$foglang['TaskStarted'],);
        } catch (Exception $e) {
            $this->data[] = array($e->getMessage());
        }
        $this->render();
        $this->redirect('?node=task');
    }
    public function search_post() {
        $this->data = array();
        array_map($this->returnData,self::getClass('HostManager')->search('',true));
        self::$HookManager->processEvent('HOST_DATA',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        self::$HookManager->processEvent('HOST_HEADER_DATA',array('headerData'=>&$this->headerData));
        $this->render();
    }
}
