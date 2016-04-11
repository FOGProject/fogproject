<?php
class AccesscontrolManagementPage extends FOGPage {
    public $node = 'accesscontrol';
    public function __construct($name = '') {
        $this->name = 'Access Management';
        parent::__construct($this->name);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('Name'),
            _('Description'),
            _('User/Group'),
        );
        $this->templates = array(
            '<input type="checkbox" name="accesscontrol[]" value="${id}" class="toggle-action" checked/>',
            '${name} ${id}',
            '${desc} ${other}',
            '${user} ${group}',
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array(),
            array(),
            array(),
        );
        self::$returnData = function(&$AccessControl) {
            if (!$AccessControl->isValid()) return;
            $this->data[] = array(
                'id'=>$AccessControl->get('id'),
                'name'=>$AccessControl->get('name'),
                'desc'=>$AccessControl->get('description'),
                'other'=>$AccessControl->get('other'),
                'user'=>self::getClass('User',$AccessControl->get('userID'))->get('name'),
                'group'=>$AccessControl->get('groupID'),
            );
            unset($AccessControl);
        };
    }
    public function index() {
        $this->title = _('All Access Controls');
        if ($this->getSetting('FOG_DATA_RETURNED') > 0 && self::getClass($this->childClass)->getManager()->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('CONTROL_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('CONTROL_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
}
