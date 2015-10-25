<?php
class HomeMobile extends FOGPage {
    public $node = 'home';
    public function __construct($name = '') {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        unset($this->headerData);
        $this->attributes = array(
            array(),
        );
        $this->templates = array(
            '${page_desc}',
        );
    }
    public function index() {
        print '<h1>'._('Welcome to FOG Mobile').'</h1>';
        $this->data[] = array(
            'page_desc' => _('Welcome to FOG - Mobile Edition!  This light weight interface for FOG allows for access via mobile, low power devices.'),
        );
        $this->HookManager->processEvent('HOMEMOBILE',array('headerData'=>&$this->headerData,'templates'=>&$this->templates,'attributes'=>&$this->attributes,'data'=>&$this->data));
        $this->render();
    }
}
