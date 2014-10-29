<?php
/**	Class Name: AccessControlManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/plugins/accesscontrol/pages

	Description: This is an extension of the FOGPage Class
    This class controls access to users based on group or individual
	access limitations determined by the sysadmins/admins of FOG.
	It's only enabled if the plugin is installed.
 
    Useful for:
	Restricting user roles and priveleges
**/
class AccesscontrolManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Access Management';
	var $node = 'accesscontrol';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		$this->headerData = array(
			_('Name'),
			_('Description'),
			_('User/Group'),
		);
		// Row templates
		$this->templates = array(
			'${name} ${id}',
			'${desc} ${other}',
			'${user} ${group}',
		);
		// Row Attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Access Controls');
		// Find data
		$AccessControls = $this->getClass('AccesscontrolManager')->find();
		// Row data
		foreach ((array)$AccessControls AS $AccessControl)
		{
			if ($AccessControl && $AccessControl->isValid())
			{
				$this->data[] = array(
					'id'	=> $AccessControl->get('id'),
					'name'  => $AccessControl->get('name'),
					'desc'	=> $AccessControl->get('description'),
					'other' => $AccessControl->get('other'),
					'user' => $this->getClass('User',$AccessControl->get('userID'))->get('name'),
					'group' => $AccessControl->get('groupID'),
				);
			}
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED'))
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('CONTROL_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}

	public function search()
	{
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=search';
		// Hook
		$this->HookManager->processEvent('CONTROL_SEARCH');
		// Output
		$this->render();
	}

	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Find data -> Push data
		$AccessControls = new AccesscontrolManager();
		foreach($AccessAcontrols->databaseFields AS $common => $dbField)
			$findWhere[$common] = $keyword;
		foreach($AccessControls->find($findWhere) AS $AccessControl)
		{
			if ($AccessControl && $AccessControl->isValid())
			{
				$this->data[] = array(
					'id' => $AccessControl->get('id'),
					'name'  => $AccessControl->get('name'),
					'desc'	=> $AccessControl->get('description'),
					'other' => $AccessControl->get('other'),
					'user' => $this->getClass('User',$AccessControl->get('userID'))->get('name'),
					'group' => $AccessControl->get('groupID'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('CONTROL_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
}
