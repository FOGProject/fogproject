<?php
class HomeMobile extends FOGPage {
	public function __construct($name = '') {
		$this->name = 'Dashboard';
		$this->node = 'homes';
		$this->menu = array(
		);
		$this->subMenu = array(
		);
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
			'page_desc' => _("Welcome to FOG - Mobile Edition!  This light weight interface for FOG allows for access via mobile, low power devices."),
		);
		// Output
		$this->render();
	}
}
