<?php
/**	Class Name: FOGConfigurationPage 
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This class controls the FOG Configuration Page of FOG.
	It, now, allows a place for users to configure FOG Settings,
	Services, Active Directory settings, Version infro, Kernel
	updates, PXE Menu, Service Client updates, MAC lists, and
	has an ssh viewer for actual terminal based management of the
	server.  These controls are globally to my understanding.

	Manages server settings..

	Useful for:
	Making configuration changes to the server, PXE, kernel, etc....
**/
class FOGConfigurationPage extends FOGPage
{
	// Base variables
	var $name = 'FOG Configuration';
	var $node = 'about';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// Pages
	/** index()
		Displays the configuration page.  Right now it redirects to display
		whether the user is on the current version.
	*/
	public function index()
	{
		$this->version();
	}
	// Version
	/** version()
		Pulls the current version from the internet.
	*/
	public function version()
	{
		// Set title
		$this->title = _('FOG Version Information');
		print "\n\t\t\t<p>"._('Version: ').FOG_VERSION.'</p>';
		print "\n\t\t\t".'<p><div class="sub">'.$this->FOGCore->FetchURL("http://freeghost.sourceforge.net/version/index.php?version=".FOG_VERSION).'</div></p>';
	}
	// Licence
	/** license()
		Displays the GNU License to the user.  Currently Version 3.
	*/
	public function license()
	{
		// Set title
		$this->title = _('FOG License Information');
		print "\n\t\t\t<pre>".file_get_contents('./other/gpl-3.0.txt').'</pre>';
	}
    // Kernel Sub pointing to properly
	/** kernel()
		Redirects as the sub information is currently incorrect.
		This is because the class files go to post, but it only
		tries to kernel_post.  The sub is kernel_update though.
	*/
    public function kernel()
    {
        $this->kernel_update_post();
    }
	// Kernel Update
	/** kernel_update()
		Display's the published kernels for update.
		This information is obtained from the internet.
		Displays the default of Published kernels.
	*/
	public function kernel_update()
	{
		$this->kernelselForm('pk');
		print $this->FOGCore->FetchURL('http://freeghost.sourceforge.net/kernelupdates/index.php?version='.FOG_VERSION);
	}
	/** kernelselForm($type)
		Gives the user the option to select between:
		Published Kernels (from sourceforge)
		Unofficial Kernels (from mastacontrola.com)
	*/
	public function kernelselForm($type)
	{
		print "\n\t\t\t".'<div class="hostgroup">';
		print _("This section allows you to update the Linux kernel which is used to boot the client computers.  In FOG, this kernel holds all the drivers for the client computer, so if you are unable to boot a client you may wish to update to a newer kernel which may have more drivers built in.  This installation process may take a few minutes, as FOG will attempt to go out to the internet to get the requested Kernel, so if it seems like the process is hanging please be patient.");
		print "\n\t\t\t</div>";
		print "\n\t\t\t<div>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t".'<select name="kernelsel" onchange="this.form.submit()">';
		print "\n\t\t\t".'<option value="pk"'.($type == 'pk' ? ' selected="selected"' : '').'>'._('Published Kernels').'</option>';
		print "\n\t\t\t".'<option value="uk"'.($type == 'uk' ? ' selected="selected"' : '').'>'._('Unofficial Kernels').'</option>';
		print "\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
	}
	// Kernel Update POST
	/** kernel_update_post()
		Displays the kernel based on the list selected.
		Defaults to published kernels.
	*/
	public function kernel_update_post()
	{
		if ($_REQUEST['sub'] == 'kernel-update')
		{
			switch ($_REQUEST['kernelsel'])
			{
				case 'pk':
					$this->kernelselForm('pk');
					print $this->FOGCore->FetchURL("http://freeghost.sourceforge.net/kernelupdates/index.php?version=" . FOG_VERSION);
					break;
				case 'uk':
					$this->kernelselForm('uk');
					print $this->FOGCore->FetchURL("http://mastacontrola.com/fogboot/kernel/index.php?version=" . FOG_VERSION);
					break;
				default:
					$this->kernelselForm('pk');
					print $this->FOGCore->FetchURL("http://freeghost.sourceforge.net/kernelupdates/index.php?version=" . FOG_VERSION);
					break;
			}
		}
		else if ( $_REQUEST["install"] == "1"  )
		{
			$_SESSION["allow_ajax_kdl"] = true;
			$_SESSION["dest-kernel-file"] = trim($_POST["dstName"]);
			$_SESSION["tmp-kernel-file"] = rtrim(sys_get_temp_dir(), '/') . '/' . basename( $_SESSION["dest-kernel-file"] );
			$_SESSION["dl-kernel-file"] = base64_decode($_REQUEST["file"]);
			if (file_exists($_SESSION["tmp-kernel-file"]))
				@unlink( $_SESSION["tmp-kernel-file"] );
			print "\n\t\t\t".'<div id="kdlRes">';
			print "\n\t\t\t".'<p id="currentdlstate">'._("Starting process...").'</p>';
			print "\n\t\t\t".'<img id="img" src="./images/loader.gif" />';
			print "\n\t\t\t</div>";
		}
		else
		{
			print "\n\t\t\t".'<form method="post" action="?node='.$_REQUEST['node'].'&sub=kernel&install=1&file='.$_REQUEST['file'].'">';
			print "\n\t\t\t<p>"._('New Kernel name:').'<input class="smaller" type="text" name="dstName" value="bzImage" /></p>';
			print "\n\t\t\t".'<p><input class="smaller" type="submit" value="Next" /></p>';
			print "\n\t\t\t</form>";
		}
	}
	// PXE Menu
	/** pxemenu()
		Displays the pxe/ipxe menu selections.
		Hidden menu requires user login from FOG GUI login.
		Also, hidden menu enforces a key press to access the menu.
		If none is selected, defaults to esc key.  Otherwise you 
		need to use the key combination chosen.
		Also used to setup the default timeout.  This time out is
		the timeout it uses to boot to the system.  If hidden menu
		is selected it sets both the hidden menu timeout and the menu,
		if none is selected, and the menu items.
	*/
	public function pxemenu()
	{
		// Set title
		$this->title = _('FOG PXE Boot Menu Configuration');
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('No Menu') => '<input type="checkbox" name="nomenu" ${noMenu} value="1" /><span class="icon icon-help hand" title="Option sets if there will even be the presence of a menu to the client systems.  If there is not a task set, it boots to the first device, if there is a task, it performs that task."></span>',
			_('Hide Menu') => '<input type="checkbox" name="hidemenu" ${checked} value="1" /><span class="icon icon-help hand" title="Option below sets the key sequence.  If none is specified, ESC is defaulted. Login with the FOG credentials and you will see the menu.  Otherwise it will just boot like normal."></span>',
			_('Boot Key Sequence') => '${boot_keys}',
			_('Menu Timeout (in seconds)').':*' => '<input type="text" name="timeout" value="${timeout}" id="timeout" />',
			_('Exit to Hard Drive Type') => '<select name="bootTypeExit"><option value="sanboot" '.($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'sanboot' ? 'selected="selected"' : '').'>Sanboot style</option><option value="exit" '.($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'exit' ? 'selected="selected"' : '').'>Exit style</option><option value="grub" '.($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'grub' ? 'selected="selected"' : '').'>Grub style</option></select>',
			'<a href="#" onload="$(\'#advancedTextArea\').hide();" onclick="$(\'#advancedTextArea\').toggle();" id="pxeAdvancedLink">Advanced Configuration Options</a>' => '<div id="advancedTextArea" class="hidden"><div class="lighterText tabbed">Add any custom text you would like included added as part of your <i>default</i> file.</div><textarea rows="5" cols="40" name="adv">${adv}</textarea></div>',
			'&nbsp;' => '<input type="submit" value="'._('Save PXE MENU').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'checked' => ($this->FOGCore->getSetting('FOG_PXE_MENU_HIDDEN') ? 'checked="checked"' : ''),
				'boot_keys' => $this->FOGCore->getClass('KeySequenceManager')->buildSelectBox($this->FOGCore->getSetting('FOG_KEY_SEQUENCE')),
				'timeout' => $this->FOGCore->getSetting('FOG_PXE_MENU_TIMEOUT'),
				'adv' => $this->FOGCore->getSetting('FOG_PXE_ADVANCED'),
				'noMenu' => ($this->FOGCore->getSetting('FOG_NO_MENU') ? 'checked="checked"' : ''),
			);
		}
		// Hook
		$this->HookManager->processEvent('PXE_BOOT_MENU', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	// PXE Menu: POST
	/** pxemenu_post()
		Performs the updates for the form sent from pxemenu().
	*/
	public function pxemenu_post()
	{
		try
		{
			$timeout = trim($_POST['timeout']);
			$timeout = (!empty($timeout) && is_numeric($timeout) && $timeout >= 0 ? true : false);
			if (!$timeout)
				throw new Exception(_("Invalid Timeout Value."));
			else
				$timeout = trim($_POST['timeout']);
			if ($this->FOGCore->setSetting('FOG_PXE_MENU_HIDDEN',$_REQUEST['hidemenu']) && $this->FOGCore->setSetting('FOG_PXE_MENU_TIMEOUT',$timeout) && $this->FOGCore->setSetting('FOG_PXE_ADVANCED',$_REQUEST['adv']) && $this->FOGCore->setSetting('FOG_KEY_SEQUENCE',$_REQUEST['keysequence']) && $this->FOGCore->setSetting('FOG_NO_MENU',$_REQUEST['nomenu']) && $this->FOGCore->setSetting('FOG_BOOT_EXIT_TYPE',$_REQUEST['bootTypeExit']))
				throw new Exception("PXE Menu has been updated!");
			else
				throw new Exception("PXE Menu update failed!");
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	// Client Updater
	/** client_updater()
		You update the client files through here.
		This is used for the Host systems with FOG Service installed.
		Here is where you can update the files an push these files to
		the client.
	*/
	public function client_updater()
	{
		// Set title
		$this->title = _("FOG Client Service Updater");
		$this->headerData = array(
			_('Module Name'),
			_('Module MD5'),
			_('Module Type'),
			_('Delete'),
		);
		$this->templates = array(
			'<form method="post" action="${action}"><input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED" />${name}',
			'${module}',
			'${type}',
			'<input type="checkbox" onclick="this.form.submit()" name="delcu" class="delid" id="delcuid${client_id}" value="${client_id}" /><label for="delcuid${client_id}">Delete</label></form>',
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
		);
		print "\n\t\t\t".'<div class="hostgroup">';
		print _("This section allows you to update the modules and config files that run on the client computers.  The clients will checkin with the server from time to time to see if a new module is published.  If a new module is published the client will download the module and use it on the next time the service is started.");
		print "\n\t\t\t</div>";
		$ClientUpdates = $this->FOGCore->getClass('ClientUpdaterManager')->find('','name');
		foreach ((array)$ClientUpdates AS $ClientUpdate)
		{
			$this->data[] = array(
				'action' => $this->formAction.'&tab=clientupdater',
				'name' => $ClientUpdate->get('name'),
				'module' => $ClientUpdate->get('md5'),
				'type' => $ClientUpdate->get('type'),
				'client_id' => $ClientUpdate->get('id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('CLIENT_UPDATE', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// reset for next element
		unset($this->headerData,$this->attributes,$this->templates,$this->data);
		$this->headerData = array(
			_('Upload a new client module/configuration file'),
			''
		);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			'<input type="file" name="module[]" value="" multiple/> <span class="lightColor">'._('Max Size:').ini_get('post_max_size').'</span>' => '<input type="submit" value="'._('Upload File').'" />',
		);
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=clientupdater" enctype="multipart/form-data">';
		print "\n\t\t\t\t".'<input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED" />';
		// Hook
		$this->HookManager->processEvent('CLIENT_UPDATE', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	// Client Updater: POST
	/** client_updater_post()
		Just updates the values set in client_updater().
	*/
	public function client_updater_post()
	{
		$Service = current($this->FOGCore->getClass('ServiceManager')->find(array('name' => $_REQUEST['name'])));
		if ($_REQUEST['en'])
			$Service && $Service->isValid() ? $Service->set('value',$_REQUEST['en'])->save() : null;
		if ($_REQUEST['delcu'])
		{
			$ClientUpdater = new ClientUpdater($_REQUEST['delcu']);
			$ClientUpdater->destroy();
			$this->FOGCore->setMessage(_('Client module update deleted!'));
		}
		if ($_FILES['module'])
		{
			foreach((array)$_FILES['module']['tmp_name'] AS $index => $tmp_name)
			{
				if (file_exists($_FILES['module']['tmp_name'][$index]))
				{
					$ClientUpdater = current($this->FOGCore->getClass('ClientUpdaterManager')->find(array('name' => $_FILES['module']['name'][$index])));
					if(file_get_contents($_FILES['module']['tmp_name'][$index]))
					{
						if ($ClientUpdater)
						{
							$ClientUpdater->set('name',basename($_FILES['module']['name'][$index]))
								->set('md5',md5(file_get_contents($_FILES['module']['tmp_name'][$index])))
								->set('type',($this->FOGCore->endsWith($_FILES['module']['name'][$index],'.ini') ? 'txt' : 'bin'))
								->set('file',file_get_contents($_FILES['module']['tmp_name'][$index]));
						}
						else
						{
							$ClientUpdater = new ClientUpdater(array(
								'name' => basename($_FILES['module']['name'][$index]),
								'md5' => md5(file_get_contents($_FILES['module']['tmp_name'][$index])),
								'type'=> ($this->FOGCore->endsWith($_FILES['module']['name'][$index],'.ini') ? 'txt' : 'bin'),
								'file' => file_get_contents($_FILES['module']['tmp_name'][$index]),
							));
						}
						if ($ClientUpdater->save())
							$this->FOGCore->setMessage('Modules Added/Updated!');
					}
				}
			}
		}
		$this->FOGCore->redirect(sprintf('?node=%s&sub=%s#%s', $_REQUEST['node'], $_REQUEST['sub'], $_REQUEST['tab']));
	}
	// MAC Address List
	/** mac_list()
		This is where you update the mac address listing.
		If you choose to update, it downloads the latest oui.txt file
		from http://standards.ieee.org/regauth/oui/oui.txt.
		
		Then it updates the database with these values.
	*/
	public function mac_list()
	{
		// Set title
		$this->title = _("MAC Address Manufacturer Listing");
        // Allow the updating and deleting of the mac-lists.
        $this->mac_list_post();
		print "\n\t\t\t".'<div class="hostgroup">';
		print "\n\t\t\t\t"._('This section allows you to import known mac address makers into the FOG database for easier identification.');
		print "\n\t\t\t</div>";
		print "\n\t\t\t<div>";
		print "\n\t\t\t\t<p>"._('Current Records: ').$this->FOGCore->getMACLookupCount().'</p>';
		print "\n\t\t\t\t<p>".'<input type="button" id="delete" value="'._('Delete Current Records').'" onclick="clearMacs()" /><input style="margin-left: 20px" type="button" id="update" value="'._('Update Current Listing').'" onclick="updateMacs()" /></p>';
		print "\n\t\t\t\t<p>"._('MAC address listing source: ').'<a href="http://standards.ieee.org/regauth/oui/oui.txt">http://standards.ieee.org/regauth/oui/oui.txt</a></p>';
		print "\n\t\t\t</div>";
	}
	// MAC Address List: POST
	/** mac_list_post()
		This just performs the actions when mac_list() is updated.
	*/
	public function mac_list_post()
	{
		if ( $_GET["update"] == "1" )
		{
			$f = "./other/oui.txt";
			exec('rm -rf '.BASEPATH.'/management/other/oui.txt');
			exec('wget -P '.BASEPATH.'/management/other/ http://standards.ieee.org/develop/regauth/oui/oui.txt');
			if ( file_exists($f) )
			{
				$handle = fopen($f, "r");
				$start = 18;
				$imported = 0;
				while (!feof($handle)) 
				{
					$line = trim(fgets($handle));
					if ( preg_match( "#^([0-9a-fA-F][0-9a-fA-F][:-]){2}([0-9a-fA-F][0-9a-fA-F]).*$#", $line ) )
					{
						$macprefix = substr( $line, 0, 8 );					
						$maker = substr( $line, $start, strlen( $line ) - $start );
						try
						{
							if ( strlen(trim( $macprefix ) ) == 8 && strlen($maker) > 0 )
							{
								if ( $this->FOGCore->addUpdateMACLookupTable( $macprefix, $maker ) )
									$imported++;
							}
						}
						catch ( Exception $e )
						{
							print ($e->getMessage()."<br />");
						}
						
					}
				}
				fclose($handle);
				$this->FOGCore->setMessage($imported._(' mac addresses updated!'));
			}
			else
				print (_("Unable to locate file: $f"));
		}
		else if ($_GET["clear"] == "1")
			$this->FOGCore->clearMACLookupTable();
	}
	// FOG System Settings
	/** settings()
		This is where you set the values for FOG itself.  You can update
		both the default service information and global information beyond
		services.  The default kernel, the fog user information, etc...
		Major things of note is that the system is now more user friendly.
		Meaning, off/on values are checkboxes, items that are more specific
		(e.g. image setting, default view,) are now select boxes.  This should
		help limit typos in the old text based system.
		Passwords are blocked with the password form field.
	*/
	public function settings()
	{
		$ServiceNames = array(
			'FOG_PXE_MENU_HIDDEN',
			'FOG_QUICKREG_AUTOPOP',
			'FOG_SERVICE_AUTOLOGOFF_ENABLED',
			'FOG_SERVICE_CLIENTUPDATER_ENABLED',
			'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
			'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
			'FOG_SERVICE_GREENFOG_ENABLED',
			'FOG_SERVICE_HOSTREGISTER_ENABLED',
			'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
			'FOG_SERVICE_PRINTERMANAGER_ENABLED',
			'FOG_SERVICE_SNAPIN_ENABLED',
			'FOG_SERVICE_TASKREBOOT_ENABLED',
			'FOG_SERVICE_USERCLEANUP_ENABLED',
			'FOG_SERVICE_USERTRACKER_ENABLED',
			'FOG_ADVANCED_STATISTICS',
			'FOG_CHANGE_HOSTNAME_EARLY',
			'FOG_DISABLE_CHKDSK',
			'FOG_HOST_LOOKUP',
			'FOG_UPLOADIGNOREPAGEHIBER',
			'FOG_USE_ANIMATION_EFFECTS',
			'FOG_USE_LEGACY_TASKLIST',
			'FOG_USE_SLOPPY_NAME_LOOKUPS',
			'FOG_PLUGINSYS_ENABLED',
			'FOG_FORMAT_FLAG_IN_GUI',
			'FOG_NO_MENU',
			'FOG_MINING_ENABLE',
			'FOG_MINING_FULL_RUN_ON_WEEKEND',
			'FOG_ALWAYS_LOGGED_IN',
		);
		// Set title
		$this->title = _("FOG System Settings");
		print "\n\t\t\t".'<p class="hostgroup">'._("This section allows you to customize or alter the way in which FOG operates.  Please be very careful changing any of the following settings, as they can cause issues that are difficult to troubleshoot.").'</p>';
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t\t".'<div id="tab-container-1">';
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array('width' => 270,'height' => 35),
			array(),
			array('class' => 'r'),
		);
		// Templates
		$this->templates = array(
			'${service_name}',
			'${input_type}',
			'${span}',
		);
		$ServiceCats = $this->FOGCore->getClass('ServiceManager')->getSettingCats();
		foreach ((array)$ServiceCats AS $ServiceCAT)
		{
			
			$divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',$ServiceCAT));
			print "\n\t\t\t\t\t\t".'<a id="'.$divTab.'" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$ServiceCAT.'</h3></a>';
			print "\n\t\t\t".'<div id="'.$divTab.'">';
			$ServMan = $this->FOGCore->getClass('ServiceManager')->find(array('category' => $ServiceCAT),'AND','id');
			foreach ((array)$ServMan AS $Service)
			{
				if ($Service->get('name') == 'FOG_PIGZ_COMP')
					$type = '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
				else if ($Service->get('name') == 'FOG_INACTIVITY_TIMEOUT')
					$type = '<div id="inact" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValInAct" maxsize="2" style="width: 15px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
				else if ($Service->get('name') == 'FOG_REGENERATE_TIMEOUT')
					$type = '<div id="regen" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showValRegen" maxsize="5" style="width: 25px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
				else if (preg_match('#(pass|PASS)#i',$Service->get('name')) && !preg_match('#(VALID|MIN)#i',$Service->get('name')))
					$type = '<input type="password" name="${service_id}" value="${service_value}" autocomplete="off" />';
				else if ($Service->get('name') == 'FOG_VIEW_DEFAULT_SCREEN')
				{
					foreach(array('SEARCH','LIST') AS $viewop)
						$options[] = '<option value="'.strtolower($viewop).'" '.($Service->get('value') == strtolower($viewop) ? 'selected="selected"' : '').'>'.$viewop.'</option>';
					$type = "\n\t\t\t".'<select name="${service_id}" style="width: 220px" autocomplete="off">'."\n\t\t\t\t".implode("\n",$options)."\n\t\t\t".'</select>';
					unset($options);
				}
				else if ($Service->get('name') == 'FOG_BOOT_EXIT_TYPE')
				{
					foreach(array('sanboot','grub','exit') AS $viewop)
						$options[] = '<option value="'.$viewop.'" '.($Service->get('value') == $viewop ? 'selected="selected"' : '').'>'.strtoupper($viewop).'</option>';
					$type = "\n\t\t\t".'<select name="${service_id}" style="width: 220px" autocomplete="off">'."\n\t\t\t\t".implode("\n",$options)."\n\t\t\t".'</select>';
					unset($options);
				}
				else if (in_array($Service->get('name'),$ServiceNames))
					$type = '<input type="checkbox" name="${service_id}" value="1" '.($Service->get('value') ? 'checked="checked"' : '').' />';
				else if ($Service->get('name') == 'FOG_DEFAULT_LOCALE')
				{
					foreach((array)$GLOBALS['foglang']['Language'] AS $lang => $humanreadable)
					{
						if ($lang == 'en')
							$lang = 'en_US.UTF-8';
						else if ($lang == 'zh')
							$lang = 'zh_CN.UTF-8';
						else if ($lang == 'it')
							$lang = 'it_IT.UTF-8';
						else if ($lang == 'fr')
							$lang = 'fr_FR.UTF-8';
						else if ($lang == 'es')
							$lang = 'es_ES.UTF-8';
						$options2[] = '<option value="'.$lang.'" '.($this->FOGCore->getSetting('FOG_DEFAULT_LOCALE') == $lang ? 'selected="selected"' : '').'>'.$humanreadable.'</option>';
					}
					$type = "\n\t\t\t".'<select name="${service_id}" autocomplete="off" style="width: 220px">'."\n\t\t\t\t".implode("\n",$options2)."\n\t\t\t".'</select>';
				}
				else if ($Service->get('name') == 'FOG_QUICKREG_IMG_ID')
					$type = $this->FOGCore->getClass('ImageManager')->buildSelectBox($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID'),$Service->get('id'));
				else if ($Service->get('name') == 'FOG_QUICKREG_GROUP_ASSOC')
					$type = $this->FOGCore->getClass('GroupManager')->buildSelectBox($this->FOGCore->getSetting('FOG_QUICKREG_GROUP_ASSOC'),$Service->get('id'));
				else if ($Service->get('name') == 'FOG_KEY_SEQUENCE')
					$type = $this->FOGCore->getClass('KeySequenceManager')->buildSelectBox($this->FOGCore->getSetting('FOG_KEY_SEQUENCE'),$Service->get('id'));
				else if ($Service->get('name') == 'FOG_QUICKREG_OS_ID')
				{
					if ($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID') > 0)
						$Image = new Image($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID'));
					$type = '<p>'.($Image && $Image->isValid() ? $Image->getOS()->get('name') : _('No image specified')).'</p>';
				}
				else
					$type = '<input type="text" name="${service_id}" value="${service_value}" autocomplete="off" />';
				$this->data[] = array(
					'service_name' => $Service->get('name'),
					'input_type' => (count(explode(chr(10),$Service->get('value'))) <= 1 ? $type : '<textarea name="${service_id}">${service_value}</textarea>'),
					'span' => '<span class="icon icon-help hand" title="${service_desc}"></span>',
					'service_id' => $Service->get('id'),
					'service_value' => $Service->get('value'),
					'service_desc' => $Service->get('description'),
				);
			}
			$this->data[] = array(
				'span' => '&nbsp;',
				'service_name' => '<input type="hidden" value="1" name="update" />',
				'input_type' => '<input type="submit" value="'._('Save Changes').'" />',
			);
			// Hook
			$this->HookManager->processEvent('CLIENT_UPDATE_'.$ServiceCAT, array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			$this->render();
			print "\n\t\t\t\t\t</div>";
			unset($this->data);
		}
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t</form>";
	}
	// FOG System Settings: POST
	/** settings_post()
		Updates the settings set from the fields.
	*/
	public function settings_post()
	{
		$ServiceMan = $this->FOGCore->getClass('ServiceManager')->find();
		foreach ((array)$ServiceMan AS $Service)
			$key[] = $Service->get('id');
		foreach ((array)$key AS $key)
		{
			$Service = new Service($key);
			if ($Service->get('name') == 'FOG_QUICKREG_IMG_ID' && empty($_REQUEST[$key]))
				$Service->set('value',-1)->save();
			else if ($Service->get('name') == 'FOG_USER_VALIDPASSCHARS')
				$Service->set('value',addslashes($_REQUEST[$key]))->save();
			else
				$Service->set('value',$_REQUEST[$key])->save();
		}
		$this->FOGCore->setMessage('Settings Successfully stored!');
		$this->FOGCore->redirect(sprintf('?node=%s&sub=%s',$_REQUEST['node'],$_REQUEST['sub']));
	}
	// Log Viewer
	/** log()
		Views the log files for the FOG Services on the server (FOGImageReplicator, FOGTaskScheduler, FOGMulticastManager).
		Just used to view these logs.  Can be used for more than this as well with some tweeking.
	*/
	public function log()
	{
		// Set title
		$this->title = "FOG Log Viewer";
		print "\n\t\t\t<p>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';

		print "\n\t\t\t<p>"._('File:');
		foreach (array('Multicast','Scheduler','Replicator') AS $value)
			$options3[] = "\n\t\t\t\t".'<option '.($value == $_POST['logtype'] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
		print "\n\t\t\t".'<select name="logtype">'.implode("\n\t\t\t\t",$options3)."\n\t\t\t".'</select>';
		print "\n\t\t\t"._('Number of lines:');
		foreach (array(20, 50, 100, 200, 400, 500, 1000) AS $value)
			$options4[] = '<option '.($value == $_POST['n'] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
		print "\n\t\t\t".'<select name="n">'.implode("\n\t\t\t\t",$options4)."\n\t\t\t".'</select>';
		print "\n\t\t\t".'<input type="submit" value="'._('Refresh').'" />';
		print "\n\t\t\t</p>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<div class="sub l">';
		print "\n\t\t\t\t<pre>";
		$n = 20;
		if ( $_POST["n"] != null && is_numeric($_POST["n"]) )
			$n = $_POST["n"];
		$t = trim($_POST["logtype"]);
		$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/multicast.log";
		if ( $t == "Multicast" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/multicast.log";
		else if ( $t == "Scheduler" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/fogscheduler.log";
		else if ( $t == "Replicator" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/fogreplicator.log";				
		system("tail -n $n \"$logfile\"");
		print "\n\t\t\t\t</pre>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t</p>";
	}
	/** config()
		This feature is relatively new.  It's a means for the user to save the fog database
		and/or replace the current one with your own, say if it's a fresh install, but you want
		the old information restored.
	*/
	public function config()
	{
		$this->HookManager->processEvent('IMPORT');
		$this->title='Configuration Import/Export';
		$report = new ReportMaker();
		$_SESSION['foglastreport']=serialize($report);
		unset($this->data,$this->headerData);
		$this->attributes = array(
			array(),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->data[0] = array(
			'field' => _('Click the button to export the database.'),
			'input' => '<input type="hidden" name="backup" value="1" /><input type="submit" value="'._('Export').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="export.php?type=sql">';
		$this->render();
		unset($this->data);
		print '</form>';
		$this->data[0] = array(
			'field' => _('Import a previous backup file.'),
			'input' => '<span class="lightColor">Max Size: ${size}</span><input type="file" name="dbFile" />',
			'size' => ini_get('post_max_size'),
		);
		$this->data[1] = array(
			'field' => null,
			'input' => '<input type="submit" value="'._('Import').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
		$this->render();
		unset($this->data);
		print "</form>";
	}
	/** config_post()
		Imports the file and installs the file as needed.
	*/
	public function config_post()
	{
		$this->HookManager->processEvent('IMPORT_POST');
		//POST
		try
		{
			if($_FILES['dbFile'] != null)
			{
				$dbFileName = BASEPATH.'/management/other/'.basename($_FILES['dbFile']['name']);
				if(move_uploaded_file($_FILES['dbFile']['tmp_name'], $dbFileName))
					print "\n\t\t\t<h2>"._('File Import successful!').'</h2>';
				else
					throw new Exception('Could not upload file!');
				exec('mysql -u' . DATABASE_USERNAME . ' -p' . DATABASE_PASSWORD . ' -h'.DATABASE_HOST.' '.DATABASE_NAME.' < '.$dbFileName);
				print "\n\t\t\t<h2>"._('Database Added!').'</h2>';
				exec('rm -rf '.$dbFileName);
			}
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
