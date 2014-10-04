<?php
/** Class Name: ServiceConfigurationPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This class allows the user to setup the default server
	FOG Service configuration.  Each host still needs their
	own setup as it's ultimately upto the host to use the
	services.  However, you can globally disable services
	so no host can use them.

	Useful for:
	Globally enabling or disabling services.  Also setups up
	default information for Auto Log Out, setups the Directory
	Cleanups, a global Display resolution, and user cleanup
	information.
**/
class ServiceConfigurationPage extends FOGPage
{
	// Base variables
	var $name = 'Service Configuration';
	var $node = 'service';
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
			_('Username'),
			_('Edit'),
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit">${name}</a>', $this->node),
			sprintf('<a href="?node=%s&sub=edit"><span class="icon icon-edit"></span></a>', $this->node)
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '55'),
		);
	}
	public function home()
	{
		$this->index();
	}
	// Pages
	public function index()
	{
		print "\n\t\t\t<h2>"._('FOG Client Download').'</h2>';
		print "\n\t\t\t<p>"._('Use the following link to go to the Client page to download the FOG Client, FOG Prep, and FOG Crypt Information.').'</p>';
		print "\n\t\t\t".'<a href="?node=client">'._('Click Here').'</a>';
		print "\n\t\t\t<h2>"._('FOG Service Configuration Information').'</h2>';
		print "\n\t\t\t<p>"._('This section of the FOG management portal allows you to configure how the FOG service functions on client computers.  The settings in this section tend to be global settings that effect all hosts.  If you are looking to configure settings for a service module that is specific to a host, please see the Servicesection.  To get started editing global settings, please select an item from the left hand menu.').'</p>';
	}
	public function edit()
	{
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t".'<div id="home">';
		$this->index();
		print "\n\t\t\t</div>";
		$moduleName = array(
			'autologout' => 'FOG_SERVICE_AUTOLOGOFF_ENABLED',
			'clientupdater' => 'FOG_SERVICE_CLIENTUPDATER_ENABLED',
			'dircleanup' => 'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
			'displaymanager' => 'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
			'greenfog' => 'FOG_SERVICE_GREENFOG_ENABLED',
			'hostregister' => 'FOG_SERVICE_HOSTREGISTER_ENABLED',
			'hostnamechanger' => 'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
			'printermanager' => 'FOG_SERVICE_PRINTERMANAGER_ENABLED',
			'snapin' => 'FOG_SERVICE_SNAPIN_ENABLED',
			'snapinclient' => 'FOG_SERVICE_SNAPIN_ENABLED',
			'taskreboot' => 'FOG_SERVICE_TASKREBOOT_ENABLED',
			'usercleanup' => 'FOG_SERVICE_USERCLEANUP_ENABLED',
			'usertracker' => 'FOG_SERVICE_USERTRACKER_ENABLED',
		);
		$Modules = $this->getClass('ModuleManager')->find();
		foreach ((array)$Modules AS $Module)
		{
			unset($this->data,$this->headerData,$this->attributes,$this->templates);
			$this->attributes = array(
				array('width' => 270,'class' => 'l'),
				array('class' => 'c'),
				array('class' => 'r'),
			);
			$this->templates = array(
				'${field}',
				'${input}',
				'${span}',
			);
			$fields = array(
				_($Module->get('name').' Enabled?') => '<input type="checkbox" name="en" value="on" ${checked} />',
				($this->FOGCore->getSetting($moduleName[$Module->get('shortName')]) ? _($Module->get('name').' Enabled as default?') : null) => ($this->FOGCore->getSetting($moduleName[$Module->get('shortName')]) ? '<input type="checkbox" name="defen" value="on" ${is_on} />' : null),
			);
			$fields = array_filter($fields);
			foreach((array)$fields AS $field => $input)
			{
				$Service = current($this->getClass('ServiceManager')->find(array('name' => $moduleName[$Module->get('shortName')])));
				if ($Service && $Service->isValid())
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
						'checked' => ($this->FOGCore->getSetting($moduleName[$Module->get('shortName')]) ? 'checked="checked"' : ''),
						($this->FOGCore->getSetting($moduleName[$Module->get('shortName')]) ? 'is_on' : null) => ($this->FOGCore->getSetting($moduleName[$Module->get('shortName')]) ? ($Module->get('isDefault') ? 'checked="checked"' : null) : null),
						'span' => '<span class="icon icon-help hand" title="${module_desc}"></span>',
						'module_desc' => $Service->get('description'),
					);
				}
			}
			$this->data[] = array(
				'field' => '<input type="hidden" name="name" value="${mod_name}" />',
				'input' => '<input type="hidden" name="updatestatus" value="1" />',
				'span' => '<input type="submit" value="'._('Update').'" />',
				'mod_name' => $moduleName[$Module->get('shortName')],
			);
			print "\n\t\t\t<!-- "._($Module->get('name'))."  -->";
			print "\n\t\t\t".'<div id="'.$Module->get('shortName').'">';
			print "\n\t\t\t<h2>"._($Module->get('name')).'</h2>';
			print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
			print "\n\t\t\t<p>"._($Service->get('description')).'</p>';
			print "\n\t\t\t<h2>"._('Service Status').'</h2>';
			// Hook
			// $this->HookManager->processEvent()
			// Output
			$this->render();
			print "</form>";
			if ($Module->get('shortName') == 'autologout')
			{
				print "\n\t\t\t<h2>"._('Default Setting').'</h2>';
				print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
				print "\n\t\t\t<p>"._('Default log out time (in minutes): ').'<input type="text" name="tme" value="'.$this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN').'" /></p>';
				print "\n\t\t\t".'<p><input type="hidden" name="name" value="FOG_SERVICE_AUTOLOGOFF_MIN" /><input type="hidden" name="updatedefaults" value="1" /><input type="submit" value="'._('Update Defaults').'" /></p>';
				print "\n\t\t\t</form>";
			}
			else if ($Module->get('shortName') == 'clientupdater')
			{
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->getClass('FOGConfigurationPage')->client_updater();
			}
			else if ($Module->get('shortName') == 'dircleanup')
			{
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->headerData = array(
					_('Path'),
					_('Remove'),
				);
				$this->attributes = array(
					array('class' => 'l'),
					array(),
				);
				$this->templates = array(
					'${dir_path}',
					'<input type="checkbox" id="rmdir${dir_id}" class="delid" name="delid" onclick="this.form.submit()" value="${dir_id}" /><label for="rmdir${dir_id}">'._('Delete').'</label>',
				);
				print "\n\t\t\t<h2>"._('Add Directory').'</h2>';
				print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
				print "\n\t\t\t<p>"._('Directory Path').': <input type="text" name="adddir" /></p>';
				print "\n\t\t\t".'<p><input type="hidden" name="name" value="'.$moduleName[$Module->get('shortName')].'" /><input type="submit" value="'._('Add Directory').'" /></p>';
				print "\n\t\t\t<h2>"._('Directories Cleaned').'</h2>';
				$dirs = $this->getClass('DirCleanerManager')->find();
				foreach ((array)$dirs AS $DirCleaner)
				{
					$this->data[] = array(
						'dir_path' => $DirCleaner->get('path'),
						'dir_id' => $DirCleaner->get('id'),
					);
				}
				// Hook
				// $this->HookManager->processEvent()
				$this->render();
				print "</form>";
			}
			else if ($Module->get('shortName') == 'displaymanager')
			{
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->attributes = array(
					array(),
					array(),
				);
				$this->templates = array(
					'${field}',
					'${input}',
				);
				$fields = array(
					_('Default Width') => '<input type="text" name="width" value="${width}" />',
					_('Default Height') => '<input type="text" name="height" value="${height}" />',
					_('Default Refresh Rate') => '<input type="text" name="refresh" value="${refresh}" />',
					'<input type="hidden" name="name" value="${mod_name}" /><input type="hidden" name="updatedefaults" value="1" />' => '<input type="submit" value="'._('Update Defaults').'" />',
				);
				print "\n\t\t\t<h2>"._('Default Setting').'</h2>';
				print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
				foreach((array)$fields AS $field => $input)
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
						'width' => $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X'),
						'height' => $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y'),
						'refresh' => $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_R'),
						'mod_name' => $moduleName[$Module->get('shortName')],
					);
				}
				// Hook
				// $this->HookManager->processEvent()
				$this->render();
				print "</form>";
			}
			else if ($Module->get('shortName') == 'greenfog')
			{
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->headerData = array(
					_('Time'),
					_('Action'),
					_('Remove'),
				);
				$this->attributes = array(
					array(),
					array(),
					array(),
				);
				$this->templates = array(
					'${gf_hour}:${gf_min}',
					'${gf_action}',
					'<input type="checkbox" id="gfrem${gf_id}" class="delid" name="delid" onclick="this.form.submit()" value="${gf_id}" /><label for="gfrem${gf_id}">'._('Delete').'</label>',
				);
				print "\n\t\t\t<h2>"._('Shutdown/Reboot Schedule').'</h2>';
				print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
				print "\n\t\t\t<p>"._('Add Event (24 Hour Format):').'<input class="short" type="text" name="h" maxlength="2" value="HH" onFocus="this.value=\'\'" />:<input class="short" type="text" name="m" maxlength="2" value="MM" onFocus="this.value=\'\'" /><select name="style" size="1"><option value="">'._('Select One').'</option><option value="s">'._('Shut Down').'</option><option value="r">'._('Reboot').'</option></select></p>';
				print "\n\t\t\t".'<p><input type="hidden" name="name" value="'.$moduleName[$Module->get('shortName')].'" /><input type="hidden" name="addevent" value="1" /><input type="submit" value="'._('Add Event').'" /></p>';
				$greenfogs = $this->getClass('GreenFogManager')->find();
				foreach((array)$greenfogs AS $GreenFog)
				{
					$this->data[] = array(
						'gf_hour' => $GreenFog->get('hour'),
						'gf_min' => $GreenFog->get('min'),
						'gf_action' => ($GreenFog->get('action') == 'r' ? 'Reboot' : ($GreenFog->get('action') == 's' ? _('Shutdown') : _('N/A'))),
						'gf_id' => $GreenFog->get('id'),
					);
				}
				// Hook
				// $this->HookManager->processEvent()
				$this->render();
				print "\n\t\t\t</form>";
			}
			else if ($Module->get('shortName') == 'usercleanup')
			{
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->attributes = array(
					array(),
					array(),
				);
				$this->templates = array(
					'${field}',
					'${input}',
				);
				$fields = array(
					_('Username') => '<input type="text" name="usr" />',
					'<input type="hidden" name="name" value="${mod_name}" /><input type="hidden" name="adduser" value="1" />' => '<input type="submit" value="'._('Add User').'" />',
				);
				print "\n\t\t\t<h2>"._('Add Protected User').'</h2>';
				print "\n\t\t\t".'<form method="post" action="?node=service&sub=edit&tab='.$Module->get('shortName').'">';
				foreach((array)$fields AS $field => $input)
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
						'mod_name' => $moduleName[$Module->get('shortName')],
					);
				}
				$this->render();
				unset($this->data,$this->headerData,$this->attributes,$this->templates);
				$this->headerData = array(
					_('User'),
					_('Remove'),
				);
				$this->attributes = array(
					array(),
					array(),
				);
				$this->templates = array(
					'${user_name}',
					'${input}',
				);
				print "\n\t\t\t<h2>"._('Current Protected User Accounts').'</h2>';
				$UCs = $this->getClass('UserCleanupManager')->find();
				foreach ((array)$UCs AS $UserCleanup)
				{
					$this->data[] = array(
						'user_name' => $UserCleanup->get('name'),
						'input' => $UserCleanup->get('id') < 7 ? null : '<input type="checkbox" id="rmuser${user_id}" class="delid" name="delid" onclick="this.form.submit()" value="${user_id}" /><label for="rmuser${user_id}">'._('Delete').'</label>',
						'user_id' => $UserCleanup->get('id'),
					);
				}
				$this->render();
				print "\n\t\t\t</form>";
			}
			print "\n\t\t\t</div>";
		}
		print "\n\t\t\t</div>";
	}
	public function edit_post()
	{
		$Service = current($this->getClass('ServiceManager')->find(array('name' => $_REQUEST['name'])));
		// Hook
		$this->HookManager->processEvent('SERVICE_EDIT_POST', array('Host' => &$Service));
		//Store value of Common Values
		$onoff = ($_REQUEST['en'] == 'on' ? 1 : 0);
		//Gets the default enabling status.
		$defen = ($_REQUEST['defen'] == 'on' ? 1 : 0);
		// POST
		try
		{
			if ($_REQUEST['updatestatus'] == 1)
			{
				$Service->set('value',$onoff);
				// Finds the relevant module
				$Module = current($this->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['tab'])));
				// If the module is found and valid, it saves the default status.
				if ($Module && $Module->isValid())
					$Module->set('isDefault',$defen)->save();
			}
			switch ($this->REQUEST['tab'])
			{
				case 'autologout';
					if ($_REQUEST['updatedefaults'] == '1' && is_numeric($_REQUEST['tme']))
						$Service->set('value',$_REQUEST['tme']);
				break;
				case 'dircleanup';
					if(trim($_REQUEST['adddir']) != '')
						$Service->addDir($_REQUEST['adddir']);
					if(isset($_REQUEST['delid']))
						$Service->remDir($_REQUEST['delid']);
				break;
				case 'displaymanager';
					if($_REQUEST['updatedefaults'] == '1' && (is_numeric($_REQUEST['height']) && is_numeric($_REQUEST['width']) && is_numeric($_REQUEST['refresh'])))
						$Service->setDisplay($_REQUEST['width'],$_REQUEST['height'],$_REQUEST['refresh']);
				break;
				case 'greenfog';
					if($_REQUEST['addevent'] == '1')
					{
						if((is_numeric($_REQUEST['h']) && is_numeric($_REQUEST['m'])) && ($_REQUEST['h'] >= 0 && $_REQUEST['h'] <= '23') && ($_REQUEST['m'] >= 0 && $_REQUEST['m'] <= 59) && ($_REQUEST['style'] == 'r' || $_REQUEST['style'] == 's'))
							$Service->setGreenFog($_REQUEST['h'],$_REQUEST['m'],$_REQUEST['style']);
					}
					if(isset($_REQUEST['delid']))
						$Service->remGF($_REQUEST['delid']);
				break;
				case 'usercleanup';
					$addUser = trim($_REQUEST['usr']);
					if($_REQUEST['updatestatus'] == '1')
						$Service->set('value',$onoff);
					if(!empty($addUser))
						$Service->addUser($addUser);
					if(isset($_REQUEST['delid']))
						$Service->remUser($_REQUEST['delid']);
				break;
				case 'clientupdater';
					$this->getClass('FOGConfigurationPage')->client_updater_post();
				break;
			}
			// Save to database
			if ($Service->save())
			{
				// Hook
				$this->HookManager->processEvent('SERVICE_EDIT_SUCCESS', array('host' => &$Service));
				// Log History event
				$this->FOGCore->logHistory('Service updated: ID: '.$Service->get('id').', Name: '.$Service->get('name').', Tab: '.$this->REQUEST['tab']);
				// Set session message
				$this->FOGCore->setMessage('Service Updated!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit#%s', $this->request['node'], $_REQUEST['tab']));
			}
			else
				throw new Exception('Service update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SERVICE_EDIT_FAIL', array('Host' => &$Service));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Tab: %s, Error: %s', _('Service'), $_REQUEST['name'], $this->request['tab'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s&sub=edit#%s', $this->request['node'], $this->request['tab']));
		}
	}
	public function search()
	{
		$this->index();
	}
}
