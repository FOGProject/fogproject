<?php
class AccesscontrolManagementPage extends FOGPage {
	public $node = 'accesscontrol';
	public function __construct($name = '') {
		$this->name = 'Access Management';
		// Call parent constructor
		parent::__construct($this->name);
		if ($_REQUEST[id]) $this->obj = $this->getClass(Accesscontrol,$_REQUEST[id]);
		// Header row
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
			_('Name'),
			_('Description'),
			_('User/Group'),
		);
		// Row templates
		$this->templates = array(
			'<input type="checkbox" name="accesscontrol[]" value="${id}" class="toggle-action" checked/>',
			'${name} ${id}',
			'${desc} ${other}',
			'${user} ${group}',
		);
		// Row Attributes
		$this->attributes = array(
			array('class'=>c,width=>16),
			array(),
			array(),
			array(),
		);
	}
	// Pages
	public function index() {
		// Set title
		$this->title = _('All Access Controls');
		if ($this->FOGCore->getSetting(FOG_DATA_RETURNED) > 0 && $this->getClass(AccesscontrolManager)->count() > $this->FOGCore->getSetting(FOG_DATA_RETURNED) && $_REQUEST[sub] != 'list') $this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER[PHP_SELF], $this->node));
		// Find data
		$AccessControls = $this->getClass(AccesscontrolManager)->find();
		// Row data
		foreach ((array)$AccessControls AS $i => &$AccessControl) {
			if ($AccessControl && $AccessControl->isValid()) {
				$this->data[] = array(
					id=>$AccessControl->get(id),
					name=>$AccessControl->get(name),
					desc=>$AccessControl->get(description),
					other=>$AccessControl->get(other),
					user=>$this->getClass(User,$AccessControl->get(userID))->get(name),
					group=>$AccessControl->get(groupID),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent(CONTROL_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
		// Output
		$this->render();
	}
	public function search_post() {
		// Variables
		$keyword = preg_replace('#%+#','%','%'.preg_replace('#[[:space:]]#','%',$_REQUEST[crit]).'%');
		// Find data -> Push data
        foreach($this->getClass(AccessControlManager)->databaseFields AS $common => &$dbField) $findWhere[$common] = $keyword;
        unset($dbField);
        $AccessControls = $this->getClass(AccessControlManager)->find($findWhere);
        foreach($AccessControls AS $i => &$AccessControl) {
            $this->data[] = array(
                id=>$AccessControl->get(id),
                name=>$AccessControl->get(name),
                desc=>$AccessControl->get(description),
                other=>$AccessControl->get(other),
                user=>$this->getClass(User,$AccessControl->get(userID))->get(name),
                group=>$AccessControl->get(groupID),
            );
        }
        unset($AccessControl);
		// Hook
		$this->HookManager->processEvent(CONTROL_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
		// Output
		$this->render();
	}
}
