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
		foreach ($Snapins AS $Snapin)
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
		foreach ($this->FOGCore->getClass('SnapinManager')->search($keyword) AS $Snapin)
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
		foreach($files AS $file)
			$filesFound .= '<option value="'.basename($file).'"'.(basename($_REQUEST['snapinfileexist']) == basename($file) ? 'selected="selected"' : '').'>'.basename($file).'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="5" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<input type="file" name="snapin" value="${snapin_file}" />',
			(count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" />',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
		foreach ($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_name' => $_REQUEST['name'],
				'snapin_desc' => $_REQUEST['description'],
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
				$uploadfile = $this->FOGCore->getSetting('FOG_SNAPINDIR').basename($_FILES['snapin']['name']);
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
		foreach($files AS $file)
			$filesFound .= '<option value="'.basename($file).'" '.(basename($file) == basename($Snapin->get('file')) ? 'selected="selected"' : '').'>'. basename($file) .'</option>';
		// Fields to work from:
		$fields = array(
			_('Snapin Name') => '<input type="text" name="name" value="${snapin_name}" />',
			_('Snapin Description') => '<textarea name="description" rows="5" cols="40" value="${snapin_desc}">${snapin_desc}</textarea>',
			_('Snapin Run With') => '<input type="text" name="rw" value="${snapin_rw}" />',
			_('Snapin Run With Argument') => '<input type="text" name="rwa" value="${snapin_rwa}" />',
			_('Snapin File').' <span class="lightColor">'._('Max Size').':${max_size}</span>' => '<span id="uploader">${snapin_file}<a href="#" id="snapin-upload"><img class="noBorder" src="images/upload.png" /></a></span>',
			(count($files) > 0 ?_('Snapin File (exists)') : null)=> (count($files) > 0 ? '<select name="snapinfileexist"><<span class="lightColor"><option value="">- '._('Please select an option').'-</option>${snapin_filesexist}</select>' : null),
			_('Snapin Arguments') => '<input type="text" name="args" value="${snapin_args}" />',
			_('Reboot after install') => '<input type="checkbox" name="reboot" />',
			'<input type="hidden" name="snapinid" value="${snapin_id}" /><input type="hidden" name="update" value="1" />' => '<input type="hidden" name="snapinfile" value="${snapin_file}" /><input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&snapinid='.$Snapin->get('id').'" enctype="multipart/form-data">';
		foreach ($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'snapin_id' => $Snapin->get('id'),
				'snapin_name' => $Snapin->get('name'),
				'snapin_desc' => $Snapin->get('description'),
				'snapin_rw' => $Snapin->get('runWith'),
				'snapin_rwa' => htmlentities($Snapin->get('runWithArgs')),
				'max_size' => ini_get('post_max_size'),
				'snapin_file' => $Snapin->get('file'),
				'snapin_filesexist' => $filesFound,
			);
		}
		// Hook
		$this->HookManager->processEvent('SNAPIN_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
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
			// SnapinManager
			$SnapinManager = $this->FOGCore->getClass('SnapinManager');
			// Error checking
			if ($_POST['snapin'] != null || $_FILES['snapin']['name'] != null)
			{
				$uploadfile = $this->FOGCore->getSetting('FOG_SNAPINDIR').basename($_FILES['snapin']['name']);
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
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Snapin->get('id')));
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
		foreach($fields AS $field => $input)
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
// Register page with FOGPageManager
$FOGPageManager->register(new SnapinManagementPage());
