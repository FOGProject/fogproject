<?php
class HomeMobile extends FOGPage {
    public $node = 'homes';
    public function __construct($name = '') {
        $this->name = 'Dashboard';
        // Call parent constructor
        parent::__construct($this->name);
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
        );
        // Templates
        $this->templates = array(
            '${page_desc}',
        );
    }
    public function index() {
        print '<h1>'._('Welcome to FOG Mobile').'</h1>';
        $this->data[] = array(
            'page_desc' => _('Welcome to FOG - Mobile Edition!  This light weight interface for FOG allows for access via mobile, low power devices.'),
        );
        // Hook
        $this->HookManager->processEvent('HOMEMOBILE',array(headerData=>&$this->headerData,templates=>&$this->templates,attributes=>&$this->attributes,data=>&$this->data));
        // Output
        $this->render();
    }
}
