<<<<<<< HEAD
<?php
/**	Class Name: SnapinManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This class controls the snapin management page for FOG.
	It helps create snapins for use on hosts
	
	Useful for:
	Managing Snapins.
**/
class SnapinManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Snapin Management';
	var $node = 'snapin';
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
			_('Snapin Name'),
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '50'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Snap-ins');
		// Find data
		$Snapins = $this->FOGCore->getClass('SnapinManager')->find();
		// Row data
		foreach ((array)$Snapins AS $Snapin)
		{
			$this->data[] = array(
				'id'		=> $Snapin->get('id'),
				'name'		=> $Snapin->get('name'),
				'description'	=> $Snapin->get('description'),
				'file'		=> $Snapin->get('file')
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('SNAPIN_SEARCH');
		// Output
		$this->render();
	}
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('SnapinManager')->search($keyword) AS $Snapin)
		{
			$this->data[] = array(
				'id'		=> $Snapin->get('id'),
				'name'		=> $Snapin->get('name'),
				'description'	=> $Snapin->get('description'),
				'file'		=> $Snapin->get('file')
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	// STORAGE NODE
	public function add()
	{
		// Set title
		$this->title = _('Add New Snapin');
		// Header Data
		unset($this->headerData);
		// Attributes (cell information)
		$this->attributes = array(
			array(),
			array(),
		);
		// Template
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// See's what files are available and sorts them.
		$files = array_diff(scandir($this->FOGCore->getSetting('FOG_SNAPINDIR')), array('..', '.'));
		sort($files);
		foreach((array)$files AS $file)
			$filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST['snapinfileexist']) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<input type="file" name="snapin" value="${snapin_file}" />',
			(count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" />',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_name' => $_REQUEST['name'],
				'snapin_desc' => $_REQUEST['description'],
				'snapin_args' => $_REQUEST['args'],
				'snapin_rw' => $_REQUEST['rw'],
				'snapin_rwa' => $_REQUEST['rwa'],
				'max_size' => ini_get('post_max_size'),
				'snapin_file' => $_FILES['snapin'],
				'snapin_filesexist' => $filesFound,
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('SNAPIN_ADD_POST');
		// POST
		try
		{
			// SnapinManager
			$SnapinManager = $this->FOGCore->getClass('SnapinManager');
			// Error checking
			$snapinName = trim($_REQUEST['name']);
			if (!$snapinName)
				throw new Exception('Please enter a name to give this Snapin!');
			if ($SnapinManager->exists($snapinName))
				throw new Exception('Snapin already exists');
			if ($_POST['snapin'] != null || $_FILES['snapin']['name'] != null)
			{
				$uploadfile = rtrim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.basename($_FILES['snapin']['name']);
				if(!file_exists($this->FOGCore->getSetting('FOG_SNAPINDIR')))
					throw new Exception('Failed to add snapin, unable to locate snapin directory.');
				else if (!is_writeable($this->FOGCore->getSetting('FOG_SNAPINDIR')))
					throw new Exception('Failed to add snapin, snapin directory is not writeable.');
				else if (file_exists($uploadfile))
					throw new Exception('Failed to add snapin, file already exists.');
				else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
					throw new Exception('Failed to add snapin, file upload failed.');
			}
			else if (empty($_REQUEST['snapinfileexist']))
				throw new Exception('Failed to add snapin, no file was uploaded or selected for use');
			// Create new Object
			$Snapin = new Snapin(array(
				'name'			=> $snapinName,
				'description'	=> $_REQUEST['description'],
				'file'			=> ($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : $_FILES['snapin']['name']),
				'args'			=> $_POST['args'],
				'createdTime'	=> date('Y-m-d H:i:s'),
				'createdBy' 	=> $_SESSION['FOG_USERNAME'],
				'reboot'		=> (isset($_POST['reboot']) ? 1 : 0 ),
				'runWith'		=> $_POST['rw'],
				'runWithArgs'	=> $_POST['rwa']
			));
			// Save
			if ($Snapin->save())
			{
				// Hook
				$this->HookManager->processEvent('SNAPIN_ADD_SUCCESS', array('Snapin' => &$Snapin));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin created'), $Snapin->get('id'), $Snapin->get('name')));
				// Set session message
				$this->FOGCore->setMessage('Snapin added, Editing now!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Snapin->get('id')));
			}
			else
				// Database save failed
				throw new Exception('Add snapin failed.');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SNAPIN_ADD_FAIL', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage'), $_POST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit()
	{
		// Find
		$Snapin = new Snapin($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Edit'), $Snapin->get('name'));
		// Header Data
		unset($this->headerData);
		// Attributes (cell information)
		$this->attributes = array(
			array(),
			array(),
		);
		// Template
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// See's what files are available and sorts them.
		$files = array_diff(scandir($this->FOGCore->getSetting('FOG_SNAPINDIR')), array('..', '.'));
		sort($files);
		foreach((array)$files AS $file)
			$filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($Snapin->get('file')) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<span id="uploader">${snapin_file}<a href="#" id="snapin-upload"><img class="noBorder" src="images/upload.png" /></a></span>',
			(count($files) > 0 ? _('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><<span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" ${checked} />',
			'<input type="hidden" name="snapinid" value="${snapin_id}" /><input type="hidden" name="update" value="1" />' => '<input type="hidden" name="snapinfile" value="${snapin_file}" /><input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t\t".'<!-- General -->';
		print "\n\t\t\t\t".'<div id="snap-gen">';
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$Snapin->get('id').'&tab=snap-gen" enctype="multipart/form-data">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_id' => $Snapin->get('id'),
				'snapin_name' => $Snapin->get('name'),
				'snapin_desc' => $Snapin->get('description'),
				'snapin_rw' => $Snapin->get('runWith'),
				'snapin_rwa' => htmlentities($Snapin->get('runWithArgs')),
				'snapin_args' => $Snapin->get('args'),
				'max_size' => ini_get('post_max_size'),
				'snapin_file' => $Snapin->get('file'),
				'snapin_filesexist' => $filesFound,
				'checked' => $Snapin->get('reboot') ? 'checked="checked"' : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
		print "\n\t\t\t</div>";
		unset($this->data);
        $this->headerData = array(
            _('Host Name'),
            _('MAC'),
            _('Remove Membership?'),
        );       
        $this->attributes = array(
            array(),
            array(),
            array(),
        );       
        $this->templates = array(
            '<a href="?node=host&sub=edit&id=${host_id}" title="'._('Edit Host').':${host_name}">${host_name}</a>',
            '${host_mac}',
            '<input type="checkbox" class="delid" onclick="this.form.submit()" name="hostdel" id="hostdelmem${host_id}" value="${host_id}" /><label for="hostdelmem${host_id}">'._('Delete').'</label>',        
        );
        foreach((array)$Snapin->get('hosts') AS $Host)
            $HostIDs[] = $Host && $Host->isValid() ? $Host->get('id') : '';
        $HostStuff = $this->FOGCore->getClass('HostManager')->buildSelectBox('','host[]" multiple="multiple','',$HostIDs);
		print "\n\t\t\t\t".'<!-- Hosts Memberships -->';
		print "\n\t\t\t\t".'<div id="snap-host">';
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-host">';
        if ($HostStuff)
        {    
            print "\n\t\t\t<p>"._('The selection box below will add this snapin to the selected hosts automatically.').'</p>';
            print "\n\t\t\t<p><center>$HostStuff";
            print "\n\t\t\t".'<input type="submit" value="'._('Add to Snapin(s)').'" /></center></p>';
        }        
        unset($this->data);
        // Find Host Relationships
        $Hosts = $Snapin->get('hosts');
        foreach((array)$Hosts AS $Host)
        {    
            if ($Host && $Host->isValid())
            {    
                $this->data[] = array(
                    'host_id' => $Host->get('id'),
                    'host_name' => $Host->get('name'),
                    'host_mac' => $Host->get('mac'),
                );       
            }
        }
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        $this->render();
		print '</form>';
		print "\n\t\t\t\t".'</div>';
		print "\n\t\t\t".'</div>';
	}
	public function edit_post()
	{
		// Find
		$Snapin = new Snapin($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('SNAPIN_EDIT_POST', array('Snapin' => &$Snapin));
		// POST
		try
		{
			switch ($_REQUEST['tab'])
			{
				case 'snap-gen';
					// SnapinManager
					$SnapinManager = $this->FOGCore->getClass('SnapinManager');
					// Error checking
					if ($_POST['snapin'] != null || $_FILES['snapin']['name'] != null)
					{
						$uploadfile = rtrim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.basename($_FILES['snapin']['name']);
						if(!file_exists($this->FOGCore->getSetting('FOG_SNAPINDIR')))
							throw new Exception('Failed to add snapin, unable to locate snapin directory.');
						else if (!is_writeable($this->FOGCore->getSetting('FOG_SNAPINDIR')))
							throw new Exception('Failed to add snapin, snapin directory is not writeable.');
						else if (file_exists($uploadfile))
							throw new Exception('Failed to add snapin, file already exists.');
						else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
							throw new Exception('Failed to add snapin, file upload failed.');
					}
					if ($_POST['name'] != $Snapin->get('name') && $SnapinManager->exists($_POST['name'], $Snapin->get('id')))
						throw new Exception('Snapin already exists');
					// Update Object
					$Snapin ->set('name',			$_POST['name'])
							->set('description',	$_POST['description'])
							->set('file',($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : ($_FILES['snapin']['name'] ? $_FILES['snapin']['name'] : $Snapin->get('file'))))
							->set('args',			$_POST['args'])
							->set('reboot',			(isset($_POST['reboot']) ? 1 : 0 ))
							->set('runWith',		$_POST['rw'])
							->set('runWithArgs',	$_POST['rwa']);
				break;
				case 'snap-host';
					if ($_POST['host'])
						$Snapin->addHost($_POST['host']);
					if ($_POST['hostdel'])
						$Snapin->removeHost($_POST['hostdel']);
				break;
			}
			// Save
			if ($Snapin->save())
			{
				// Hook
				$this->HookManager->processEvent('SNAPIN_UPDATE_SUCCESS', array('Snapin' => &$Snapin));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin updated'), $Snapin->get('id'), $Snapin->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Snapin updated'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Snapin->get('id'),$_REQUEST['tab']));
			}
			else
				throw new Exception('Snapin update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SNAPIN_UPDATE_FAIL', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Snapin'), $_POST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete()
	{
		// Find
		$Snapin = new Snapin($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Remove'), $Snapin->get('name'));
		// Header
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Please confirm you want to delete').' <b>'.$Snapin->get('name').'</b>' => '<input type="submit" value="${title}" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'title' => $this->title,
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function delete_post()
	{
		// Find
		$Snapin = new Snapin($this->request['id']);
		// Hook
		$this->HookManager->processEvent('SNAPIN_DELETE_POST', array('Snapin' => &$Snapin));
		// POST
		try
		{
			// Error checking
			if (!$Snapin->destroy())
				throw new Exception(_('Failed to destroy Snapin'));
			// Remove associations
			$this->FOGCore->getClass('SnapinAssociationManager')->destroy(array('snapinID' => $Snapin->get('id')));
			// Hook
			$this->HookManager->processEvent('SNAPIN_DELETE_SUCCESS', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin deleted'), $Snapin->get('id'), $Snapin->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Snapin deleted'), $Snapin->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SNAPIN_DELETE_FAIL', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory('Snapin deleted: ID: '.$Snapin->get('id').', Name: '.$Snapin->get('name'));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
=======
<?php
/**	Class Name: SnapinManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This class controls the snapin management page for FOG.
	It helps create snapins for use on hosts
	
	Useful for:
	Managing Snapins.
**/
class SnapinManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Snapin Management';
	var $node = 'snapin';
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
			_('Snapin Name'),
			_('Storage Group'),
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
			'${storage_group}',
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '50'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Snap-ins');
		// Find data
		$Snapins = $this->getClass('SnapinManager')->find();
		// Row data
		foreach ((array)$Snapins AS $Snapin)
		{
			if ($Snapin && $Snapin->isValid())
			{
				$StorageGroup = new StorageGroup($Snapin->get('storageGroupID'));
				$this->data[] = array(
					'id'		=> $Snapin->get('id'),
					'name'		=> $Snapin->get('name'),
					'storage_group' => $StorageGroup && $StorageGroup->isValid() ? $StorageGroup->get('name') : '',
					'description'	=> $Snapin->get('description'),
					'file'		=> $Snapin->get('file')
				);
			}
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('SNAPIN_SEARCH');
		// Output
		$this->render();
	}
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		$Snapins = new SnapinManager();
		// Find data -> Push data
		foreach ($Snapins->search($keyword,'Snapin') AS $Snapin)
		{
			if ($Snapin && $Snapin->isValid())
			{
				$StorageGroup = new StorageGroup($Snapin->get('storageGroupID'));
				$this->data[] = array(
					'id'		=> $Snapin->get('id'),
					'name'		=> $Snapin->get('name'),
					'storage_group' => $StorageGroup && $StorageGroup->isValid() ? $StorageGroup->get('name') : '',
					'description'	=> $Snapin->get('description'),
					'file'		=> $Snapin->get('file')
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	// STORAGE NODE
	public function add()
	{
		// Set title
		$this->title = _('Add New Snapin');
		// Header Data
		unset($this->headerData);
		// Attributes (cell information)
		$this->attributes = array(
			array(),
			array(),
		);
		// Template
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// See's what files are available and sorts them.
		$files = array_diff(scandir($this->FOGCore->getSetting('FOG_SNAPINDIR')), array('..', '.'));
		sort($files);
		foreach((array)$files AS $file)
			$filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST['snapinfileexist']) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox($_REQUEST['storagegroup']),
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<input type="file" name="snapin" value="${snapin_file}" />',
			(count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" />',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_name' => $_REQUEST['name'],
				'snapin_desc' => $_REQUEST['description'],
				'snapin_args' => $_REQUEST['args'],
				'snapin_rw' => $_REQUEST['rw'],
				'snapin_rwa' => $_REQUEST['rwa'],
				'max_size' => ini_get('post_max_size'),
				'snapin_file' => $_FILES['snapin'],
				'snapin_filesexist' => $filesFound,
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('SNAPIN_ADD_POST');
		// POST
		try
		{
			// SnapinManager
			$SnapinManager = $this->getClass('SnapinManager');
			// Error checking
			$snapinName = trim($_REQUEST['name']);
			if (!$snapinName)
				throw new Exception(_('Please enter a name to give this Snapin'));
			if ($SnapinManager->exists($snapinName))
				throw new Exception(_('Snapin already exists'));
			if (!$_REQUEST['storagegroup'])
				throw new Exception(_('Please select a storage group for this snapin to reside in'));
			if ($_REQUEST['snapin'] || $_FILES['snapin']['name'])
			{
				if (!$_REQUEST['storagegroup'])
				{
					$uploadfile = rtrim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.basename($_FILES['snapin']['name']);
					if(!file_exists($this->FOGCore->getSetting('FOG_SNAPINDIR')))
						throw new Exception('Failed to add snapin, unable to locate snapin directory.');
					else if (!is_writeable($this->FOGCore->getSetting('FOG_SNAPINDIR')))
						throw new Exception('Failed to add snapin, snapin directory is not writeable.');
					else if (file_exists($uploadfile))
						throw new Exception('Failed to add snapin, file already exists.');
					else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
						throw new Exception('Failed to add snapin, file upload failed.');
				}
				else
				{
					// Will fail if the storage group is not assigned or found.
					$StorageNode = $this->getClass('StorageGroup',$_REQUEST['storagegroup'])->getMasterStorageNode();
					$src = $_FILES['snapin']['tmp_name'];
					$dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
					$this->FOGFTP->set('host',$StorageNode->get('ip'))
						->set('username',$StorageNode->get('user'))
						->set('password',$StorageNode->get('pass'));
					if (!$this->FOGFTP->connect())
						throw new Exception(_('Storage Node: '.$StorageNode->get('ip').' FTP Connection has failed!'));
					if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath')))
					{
						if (!$this->FOGFTP->mkdir($StorageNode->get('snapinpath')))
							throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
					}
					// Try to delete the file.
					$this->FOGFTP->delete($dest);
					if (!$this->FOGFTP->put($dest,$src))
						throw new Exception(_('Failed to add snapin'));
					$this->FOGFTP->close();
				}
			}
			else if (empty($_REQUEST['snapinfileexist']))
				throw new Exception('Failed to add snapin, no file was uploaded or selected for use');
			// Create new Object
			$Snapin = new Snapin(array(
				'name'			=> $snapinName,
				'description'	=> $_REQUEST['description'],
				'file'			=> ($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : $_FILES['snapin']['name']),
				'args'			=> $_REQUEST['args'],
				'createdTime'	=> $this->formatTime('now','Y-m-d H:i:s'),
				'createdBy' 	=> $_SESSION['FOG_USERNAME'],
				'reboot'		=> (isset($_REQUEST['reboot']) ? 1 : 0 ),
				'runWith'		=> $_REQUEST['rw'],
				'runWithArgs'	=> $_REQUEST['rwa'],
				'storageGroupID' => $_REQUEST['storagegroup'],
			));
			// Save
			if ($Snapin->save())
			{
				// Hook
				$this->HookManager->processEvent('SNAPIN_ADD_SUCCESS', array('Snapin' => &$Snapin));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin created'), $Snapin->get('id'), $Snapin->get('name')));
				// Set session message
				$this->FOGCore->setMessage('Snapin added, Editing now!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Snapin->get('id')));
			}
			else
				// Database save failed
				throw new Exception('Add snapin failed.');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SNAPIN_ADD_FAIL', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit()
	{
		// Find
		$Snapin = new Snapin($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Edit'), $Snapin->get('name'));
		// Header Data
		unset($this->headerData);
		// Attributes (cell information)
		$this->attributes = array(
			array(),
			array(),
		);
		// Template
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// See's what files are available and sorts them.
		$dots = array('.','..');
		if ($Snapin->get('storageGroupID'))
		{
			$StorageNode = $Snapin->getStorageGroup()->getMasterStorageNode();
			$ftp = $this->FOGFTP;
			$ftp->set('host',$StorageNode->get('ip'))
				->set('username',$StorageNode->get('user'))
				->set('password',$StorageNode->get('pass'))
				->connect();
			$files = array_diff((array)$ftp->nlist($StorageNode->get('snapinpath')),$dots);
		}
		else
			$files = array_diff(scandir($this->FOGCore->getSetting('FOG_SNAPINDIR')), $dots);
		if ($files && is_array($files))
			sort($files);
		foreach((array)$files AS $file)
			$filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($Snapin->get('file')) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="8" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox($Snapin->get('storageGroupID')),
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<span id="uploader">${snapin_file}<a href="#" id="snapin-upload"><img class="noBorder" src="images/upload.png" /></a></span>',
			(count($files) > 0 ? _('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><<span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" ${checked} />',
			'<input type="hidden" name="snapinid" value="${snapin_id}" /><input type="hidden" name="update" value="1" />' => '<input type="hidden" name="snapinfile" value="${snapin_file}" /><input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t\t".'<!-- General -->';
		print "\n\t\t\t\t".'<div id="snap-gen">';
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$Snapin->get('id').'&tab=snap-gen" enctype="multipart/form-data">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_id' => $Snapin->get('id'),
				'snapin_name' => $Snapin->get('name'),
				'snapin_desc' => $Snapin->get('description'),
				'snapin_rw' => $Snapin->get('runWith'),
				'snapin_rwa' => htmlentities($Snapin->get('runWithArgs')),
				'snapin_args' => $Snapin->get('args'),
				'max_size' => ini_get('post_max_size'),
				'snapin_file' => $Snapin->get('file'),
				'snapin_filesexist' => $filesFound,
				'checked' => $Snapin->get('reboot') ? 'checked="checked"' : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
		print "\n\t\t\t</div>";
		unset($this->data);
		// Get hosts with this snapin assigned
        foreach((array)$Snapin->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$HostsWithMe[] = $Host->get('id');
		}
		// Get all Host IDs with any snapin assigned
		foreach($this->getClass('SnapinAssociationManager')->find() AS $SnapAssoc)
		{
			if ($SnapAssoc && $SnapAssoc->isValid())
			{
				$SnapinMe = new Snapin($SnapAssoc->get('snapinID'));
				$Host = new Host($SnapAssoc->get('hostID'));
				if ($SnapinMe && $SnapinMe->isValid() && $Host && $Host->isValid())
					$HostWithSnapin[] = $Host->get('id');
			}
		}
		$HostWithSnapin = array_unique($HostWithSnapin);
		$HostMan = new HostManager();
		// Set the values
		foreach($HostMan->find() AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				if (!in_array($Host->get('id'),(array)$HostWithSnapin))
					$HostNotWithSnapin[] = $Host;
				if (!in_array($Host->get('id'),(array)$HostsWithMe))
					$HostNotWithMe[] = $Host;
			}
		}
		print "\n\t\t\t\t".'<!-- Hosts Memberships -->';
		print "\n\t\t\t\t".'<div id="snap-host">';
		// Create the header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxsnapin1" class="toggle-checkbox1" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Last Deployed'),
			_('Registered'),
		);
		// Create the template data:
		$this->templates = array(
			'<span class="icon icon-help hand" title="${host_desc}"></span>',
			'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
			($_SESSION['FOGPingActive'] ? '<span class="icon ping"></span>' : ''),
			'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
			'${deployed}',
			'${host_reg}',
		);
		// Create the attributes data:
		$this->attributes = array(
			array('width' => 22, 'id' => 'host-${host_name}'),
			array('class' => 'c', 'width' => 16),
			($_SESSION['FOGPingActive'] ? array('width' => 20) : ''),
			array(),
			array(),
			array(),
		);
		// All hosts not with this snapin
		foreach((array)$HostNotWithMe AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'check_num' => '1',
					'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
				);
			}
		}
		$SnapinDataExists = false;
		if (count($this->data) > 0)
		{
			$SnapinDataExists = true;
			$this->HookManager->processEvent('SNAPIN_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			print "\n\t\t\t<center>".'<label for="hostMeShow">'._('Check here to see hosts not assigned with this snapin').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label>';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-host">';
			print "\n\t\t\t".'<div id="hostNotInMe">';
			print "\n\t\t\t".'<h2>'._('Modify snapin association for').' '.$Snapin->get('name').'</h2>';
			print "\n\t\t\t".'<p>'._('Add hosts to snapin').' '.$Snapin->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		// Reset the data for the next value
		unset($this->data);
		// Recreate the header to allow checkboxsnapin2
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxsnapin2" class="toggle-checkbox2" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Last Deployed'),
			_('Registered'),
		);
		// All hosts without a snapin
		foreach((array)$HostNotWithSnapin AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'check_num' => '2',
					'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
				);
			}
		}
		if (count($this->data) > 0)
		{
			$SnapinDataExists = true;
			$this->HookManager->processEvent('SNAPIN_HOST_NOT_IN_ANY',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			print "\n\t\t\t<center>".'<label for="hostNoShow">'._('Check here to see hosts not assigned with any snapin').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostNoShow" /></label>';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-host">';
			print "\n\t\t\t".'<div id="hostNoSnapin">';
			print "\n\t\t\t".'<p>'._('Hosts below have no snapin association').'</p>';
			print "\n\t\t\t".'<p>'._('Add hosts to snapin').' '.$Snapin->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		if ($SnapinDataExists)
		{
			print '</br><input type="submit" value="'._('Add Snapin to Host(s)').'" />';
			print "\n\t\t\t</form></center>";
		}
		unset($this->data);
		array_push($this->headerData,_('Remove Snapin'));
        array_push($this->templates,'<input type="checkbox" class="delid" onclick="this.form.submit()" name="hostdel" id="hostdelmem${host_id}" value="${host_id}" /><label for="hostdelmem${host_id}">'._('Delete').'</label>');
		array_push($this->attributes,array());
		array_splice($this->headerData,1,1);
		array_splice($this->templates,1,1);
		array_splice($this->attributes,1,1);
		foreach((array)$Snapin->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : '',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
				);
			}
		}
        // Hook
        $this->HookManager->processEvent('SNAPIN_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=snap-host">';
		$this->render();
		print '</form>';
		print "\n\t\t\t\t</div>";
		print "\n\t\t\t".'</div>';
	}
	public function edit_post()
	{
		// Find
		$Snapin = new Snapin($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('SNAPIN_EDIT_POST', array('Snapin' => &$Snapin));
		// POST
		try
		{
			switch ($_REQUEST['tab'])
			{
				case 'snap-gen';
					// SnapinManager
					$SnapinManager = $this->getClass('SnapinManager');
					if (!$_REQUEST['storagegroup'])
						throw new Exception(_('Please select a group to place the Snapin on'));
					// Error checking
					if ($_REQUEST['snapin'] || $_FILES['snapin']['name'])
					{
						if (!$Snapin->get('storageGroupID'))
						{
							$uploadfile = rtrim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.basename($_FILES['snapin']['name']);
							if(!file_exists($this->FOGCore->getSetting('FOG_SNAPINDIR')))
								throw new Exception('Failed to add snapin, unable to locate snapin directory.');
							else if (!is_writeable($this->FOGCore->getSetting('FOG_SNAPINDIR')))
								throw new Exception('Failed to add snapin, snapin directory is not writeable.');
							else if (file_exists($uploadfile))
								throw new Exception('Failed to add snapin, file already exists.');
							else if (!move_uploaded_file($_FILES['snapin']['tmp_name'],$uploadfile))
								throw new Exception('Failed to add snapin, file upload failed.');
						}
						else
						{
							// Will fail if the storage group is not assigned or found.
							$StorageNode = $Snapin->getStorageGroup()->getMasterStorageNode();
							$src = $_FILES['snapin']['tmp_name'];
							$dest = rtrim($StorageNode->get('snapinpath'),'/').'/'.$_FILES['snapin']['name'];
							$this->FOGFTP->set('host',$StorageNode->get('ip'))
								->set('username',$StorageNode->get('user'))
								->set('password',$StorageNode->get('pass'));
							if (!$this->FOGFTP->connect())
								throw new Exception(_('Storage Node: '.$StorageNode->get('ip').' FTP Connection has failed!'));
							if (!$this->FOGFTP->chdir($StorageNode->get('snapinpath')))
								throw new Exception(_('Failed to add snapin, unable to locate snapin directory.'));
							// Try to delete the file.
							$this->FOGFTP->delete($dest);
							if (!$this->FOGFTP->put($dest,$src))
								throw new Exception(_('Failed to add snapin'));
							$this->FOGFTP->close();
						}
					}
					if ($_REQUEST['name'] != $Snapin->get('name') && $SnapinManager->exists($_REQUEST['name'], $Snapin->get('id')))
						throw new Exception('Snapin already exists');
					// Update Object
					$Snapin ->set('name',			$_REQUEST['name'])
							->set('description',	$_REQUEST['description'])
							->set('file',($_REQUEST['snapinfileexist'] ? $_REQUEST['snapinfileexist'] : ($_FILES['snapin']['name'] ? $_FILES['snapin']['name'] : $Snapin->get('file'))))
							->set('args',			$_REQUEST['args'])
							->set('reboot',			(isset($_REQUEST['reboot']) ? 1 : 0 ))
							->set('runWith',		$_REQUEST['rw'])
							->set('storageGroupID', $_REQUEST['storagegroup'])
							->set('runWithArgs',	$_REQUEST['rwa']);
				break;
				case 'snap-host';
					if ($_REQUEST['host'])
						$Snapin->addHost($_REQUEST['host']);
					if ($_REQUEST['hostdel'])
						$Snapin->removeHost($_REQUEST['hostdel']);
				break;
			}
			// Save
			if ($Snapin->save())
			{
				// Hook
				$this->HookManager->processEvent('SNAPIN_UPDATE_SUCCESS', array('Snapin' => &$Snapin));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Snapin updated'), $Snapin->get('id'), $Snapin->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Snapin updated'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Snapin->get('id'),$_REQUEST['tab']));
			}
			else
				throw new Exception('Snapin update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('SNAPIN_UPDATE_FAIL', array('Snapin' => &$Snapin));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Snapin'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
>>>>>>> dev-branch
