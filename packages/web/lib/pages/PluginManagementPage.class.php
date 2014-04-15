<?php
/**	Class Name: PluginManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages

	Description: This is an extension of the FOGPage Class
    This class controls plugins you want installed with FOG.
 
    Useful for:
    Installing Plugins for FOG's use.
>
	Note:
	Only two plugins exist right now.  Location and Capone
**/
class PluginManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Plugin Management';
	var $node = 'plugin';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		$this->headerData = array(
			_('Plugin Name'),
			_('Description'),
			_('Location'),
			$_REQUEST['sub'] == 'installed' || $_REQUEST['sub'] == 'install' ? _('Remove') : null,
		);
		//Row templates
		$this->templates = array(
			'<a href="?node=plugin&sub=${type}&run=${encname}&${type}=${encname}" title="Plugin: ${name}"><img alt="${name}" src="${icon}"/></a>',
			'${desc}',
			'${location}',
			$_REQUEST['sub'] == 'installed' || $_REQUEST['sub'] == 'install' ? '<a href="?node=plugin&sub=removeplugin&rmid=${pluginid}"><span class="icon icon-kill" title="Remove Plugin"></span></a>' : null,
		);
		//Row attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
			$_REQUEST['sub'] == 'installed' || $_REQUEST['sub'] == 'install' ? array() : null,
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = $this->name;
	}

	public function home()
	{
		$this->index();
	}

	public function activate()
	{
		// Set title
		$this->title = _('Activate Plugins');
		$Plugins = new Plugin(array('name' => null)); 
		// Find data
		foreach ($Plugins->getPlugins() AS $Plugin)
		{
			if(!$Plugin->isActive())
			{
				$this->data[] = array(
					'name' => $Plugin->getName(),
					'type' => 'activate',
					'encname' => md5(trim($Plugin->getName())),
					'location' => $Plugin->getPath(),
					'desc' => $Plugin->getDesc(),
					'icon' => $Plugin->getIcon(),
				);
			}
		}
		//Hook
		$this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=> &$this->headerData,
			'data' => &$this->data,
			'templates' => &$this->templates,
			'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Activate plugin if it's not already!
		if (!empty($_GET['activate'])&&$_GET['sub'] == 'activate')
		{
			$Plugin->activatePlugin($_GET['activate']);
			$this->FOGCore->setMessage('Successfully added Plugin!');
			$this->FOGCore->redirect('?node=plugin&sub=activate');
		}

	}
	public function install()
	{
		$this->title = 'Install Plugins';
		$Plugins = new Plugin(array('name' => null)); 
		// Find data
		foreach ($Plugins->getPlugins() AS $Plugin)
		{
			$PluginMan = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $Plugin->getName())));
			if($Plugin->isActive() && !$Plugin->isInstalled())
			{
				$this->data[] = array(
					'name' => $Plugin->getName(),
					'type' => 'install',
					'encname' => md5($Plugin->getName()),
					'location' => $Plugin->getPath(),
					'desc' => $Plugin->getDesc(),
					'icon' => $Plugin->getIcon(),
					'pluginid' => $PluginMan ? $PluginMan->get('id') : '',
				);
			}
		}
		//Hook
		$this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=> &$this->headerData,
			'data' => &$this->data,
			'templates' => &$this->templates,
			'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function installed()
	{
		$this->title = _('Installed Plugins');
		$Plugins = new Plugin(array('name' => null)); 
		// Find data
		foreach ($Plugins->getPlugins() AS $Plugin)
		{
			$PluginMan = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $Plugin->getName())));
			if($Plugin->isActive())
			{
				$this->data[] = array(
					'name' => $Plugin->getName(),
					'type' => 'installed',
					'encname' => md5($Plugin->getName()),
					'location' => $Plugin->getPath(),
					'desc' => $Plugin->getDesc(),
					'icon' => $Plugin->getIcon(),
					'pluginid' => $PluginMan ? $PluginMan->get('id') : '',
				);
			}
		}
		//Hook
		$this->HookManager->processEvent('PLUGIN_DATA',array('headerData'=> &$this->headerData,
			'data' => &$this->data,
			'templates' => &$this->templates,
			'attributes' => &$this->attributes));
		// Output
		$this->render();
		if(!empty($_REQUEST['run']))
		{
			$runner = $Plugin->getRunInclude($_REQUEST['run']);
			if($runner != null && $_REQUEST['sub'] == 'installed' && !$Plugin->isInstalled())
				$this->run();
			if ($Plugin->isInstalled() && file_exists($runner))
				require_once($runner);
		}
	}
	public function run()
	{
		$plugin = unserialize($_SESSION['fogactiveplugin']);
		try
		{
			if ($plugin == null)
				throw new Exception('Unable to determine plugin details.');
			$this->title = _('Plugin').': '.$plugin->getName();
			print "\n\t\t\t<p>"._('Plugin Description').': '.$plugin->getDesc().'</p>';
			if ($plugin->isInstalled() && $plugin->getName() == 'capone')
			{
				$dmiFields = array(
					"bios-vendor",
					"bios-version",
					"bios-release-date",
					"system-manufacturer",
					"system-product-name",
					"system-version",
					"system-serial-number",
					"system-uuid",
					"baseboard-manufacturer",
					"baseboard-product-name",
					"baseboard-version",
					"baseboard-serial-number",
					"baseboard-asset-tag",
					"chassis-manufacturer",
					"chassis-type",
					"chassis-version",
					"chassis-serial-number",
					"chassis-asset-tag",
					"processor-family",
					"processor-manufacturer",
					"processor-version",
					"processor-frequency"
				);
				print "\n\t\t\t".'<p class="titleBottomLeft">'._('Settings').'</p>';
				unset($this->headerData,$this->data);
				$this->templates = array(
					'${field}',
					'${input}',
				);
				$this->attributes = array(
					array(),
					array(),
				);
				foreach($dmiFields AS $dmifield)
				{
					$checked = $this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI') == $dmifield ? 'selected="selected"' : '';
					$dmiOpts[] = '<option value="'.$dmifield.'" label="'.$dmifield.'" '.$checked.'>'.$dmifield.'</option>';
				}
				$ShutdownFields = array(
					_('Reboot after deploy'),
					_('Shutdown after deploy'),
				);
				$shutOpts[] = '<option value="0" '.(!$this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN') ? 'selected="selected"' : ''). '>'._('Reboot after deploy').'</option>';
				$shutOpts[] = '<option value="1" '.($this->FOGCore->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN') ? 'selected="selected"' : ''). '>'._('Shutdown after deploy').'</option>';
				$fields = array(
					_('DMI Field').':' => "\n\t\t\t\t\t\t\t".'<select name="dmifield" size="1">'."\n\t\t\t\t\t\t\t\t".'<option value="">- '._('Please select an option').' -</option>'."\n\t\t\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t\t\t",$dmiOpts)."\n\t\t\t\t\t\t\t</select>\n\t\t\t\t\t\t",
					_('Shutdown').':' => "\n\t\t\t\t\t\t\t".'<select name="shutdown" size="1">'."\n\t\t\t\t\t\t\t\t".'<option value="">- '._('Please select an option').' -</option>'."\n\t\t\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t\t\t",$shutOpts)."\n\t\t\t\t\t\t\t</select>\n\t\t\t\t\t\t",
					'<input type="hidden" name="basics" value="1" />' => '<input style="margin-top: 7px;" type="submit" value="'._('Update Settings').'" />',
				);
				foreach ($fields AS $field => $input)
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
					);
				}
				print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
				$this->render();
				print "</form>";
				unset($this->headerData,$this->data,$fields);
				print "\n\t\t\t".'<p class="titleBottomLeft">'._('Add Image to DMI Associations').'</p>';
				$fields = array(
					_('Image Definition').':' => $this->FOGCore->getClass('ImageManager')->buildSelectBox(),
					_('DMI Result').':' => '<input type="text" name="key" />',
					'<input type="hidden" name="addass" value="1" />' => '<input type="submit" style="margin-top: 7px;" value="'._('Add Association').'" />',
				);
				foreach($fields AS $field => $input)
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
					);
				}
				print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
				$this->render();
				print "</form>";
				unset($this->headerData,$this->data,$fields);
				$Capones = $this->FOGCore->getClass('CaponeManager')->find();
				print "\n\t\t\t".'<p class="titleBottomLeft">'._('Current Image to DMI Associations').'</p>';
				$this->headerData = array(
					_('Image Name'),
					_('OS Name'),
					_('DMI Key'),
					_('Clear'),
				);
				$this->templates = array(
					'${image_name}',
					'${os_name}',
					'${capone_key}',
					'<input type="checkbox" name="kill" value="${capone_id}" onclick="this.form.submit()" class="delvid" id="rmcap${capone_id}"/><a href="#"><label for="rmcap${capone_id}"><span class="icon icon-kill" title="'._('Remove Association').'"></span></label></a>',
				);
				$this->attributes = array(
					array(),
					array(),
					array(),
					array(),
				);
				foreach($Capones AS $Capone)
				{
					$Image = new Image($Capone->get('imageID'));
					$OS = $Image->getOS();
					$this->data[] = array(
						'image_name' => $Image->get('name'),
						'os_name' => $OS->get('name'),
						'capone_key' => $Capone->get('key'),
						'link' => $this->formAction . '&kill=${capone_id}',
						'capone_id' => $Capone->get('id'),
					);
				}
				print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
				$this->render();
				print "</form>";
			}
			else if ($plugin->isInstalled() && !$plugin->getname() == 'capone')
				$this->FOGCore->setMessage(_('Already installed!'));
			else if (!$plugin->isInstalled())
			{
				print "\n\t\t\t".'<p class="titleBottomLeft">'._('Plugin Installation').'</p>';
				print "\n\t\t\t<p>"._('This plugin is currently not installed, would you like to install it now?').'</p>';
				print "\n\t\t\t<div>";
				print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'">';
				print "\n\t\t\t\t".'<input type="hidden" name="install" value="1" />';
				print "\n\t\t\t\t".'<input type="submit" name="Install Plugin" />';
				print "\n\t\t\t\t".'</form>';
				print "\n\t\t\t</div>";
			}
		}
		catch (Exception $e)
		{
			print $this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect('?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&run='.$_REQUEST['run']);
		}
	}
	public function installed_post()
	{
		$plugin = unserialize($_SESSION['fogactiveplugin']);
		if ($_REQUEST['install'] == 1)
		{
			if($this->FOGCore->getClass(ucfirst($plugin->getName()).'Manager')->addSchema($plugin->getName()))
			{
				$Plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $plugin->getName())));
				$Plugin->set('installed',1)
					   ->set('version',1);
				if ($Plugin->save())
					$this->FOGCore->setMessage(_('Plugin Installed!'));
				else
					$this->FOGCore->setMessage(_('Plugin Installation Failed!'));
			}
			else
				$this->FOGCore->setMessage(_('Failed to install schema!'));
			$this->FOGCore->redirect('?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&run='.$_REQUEST['run']);
		}
		if ($_REQUEST['basics'])
		{
			$this->FOGCore->setSetting('FOG_PLUGIN_CAPONE_DMI',$_REQUEST['dmifield']);
			$this->FOGCore->setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN',$_REQUEST['shutdown']);
		}
		if($_REQUEST['addass'])
		{
			$Capone = new Capone(array(
				'imageID' => $_REQUEST['image'],
				'osID'	  => $this->FOGCore->getClass('Image',$_REQUEST['image'])->get('osID'),
				'key'	  => $_REQUEST['key']
			));
			$Capone->save();
		}
		if($_REQUEST['kill'])
		{
			$Capone = new Capone($_REQUEST['kill']);
			$Capone->destroy();
		}
		$this->FOGCore->setMessage('Plugin updated!');
		$this->FOGCore->redirect($this->formAction);
	}
	public function removeplugin()
	{
		if ($_REQUEST['rmid'])
			$Plugin = new Plugin($_REQUEST['rmid']);
		if ($Plugin)
		{
			if ($Plugin->get('name') == 'location')
			{
				$this->DB->query("DROP TABLE location");
				$this->DB->query("DROP TABLE locationAssoc");
			}
			else if ($Plugin->get('name') == 'capone')
			{
				$this->DB->query("DROP TABLE capone");
				$this->FOGCore->getClass('ServiceManager')->destroy(array('name' => 'FOG_PLUGIN_CAPON_%'));
			}
			if ($Plugin->destroy())
			{
				$this->FOGCore->setMessage('Plugin Removed');
				$this->FOGCore->redirect('?node=plugin&sub=installed');
			}
		}
	}
}
// Register page with FOGPageManager
$FOGPageManager->register(new PluginManagementPage());
