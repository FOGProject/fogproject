<?php
/** Class Name: PrinterManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages

	Description: This is an extension of the FOGPage Class
    This class controls printers you want FOG to associate
	for possible installing onto clients.
    It, now, figures out the type of printer if you already
	installed it and are editing it This way you can change
	a printer's type easily.
 
    Useful for:
    Setting up printers of network, iprint, or local.
**/
class PrinterManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Printer Management';
	var $node = 'printer';
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
			'Printer Name',
			'Printer Type',
			'Model',
			'Port',
			'File',
			'IP',
			'Edit'
		);
		// Row templates
		$this->templates = array(
			'<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
			'${config}',
			'${model}',
			'${port}',
			'${file}',
			'${ip}',
			'<a href="?node=printer&sub=edit&id=${id}" title="Edit"><span class="icon icon-edit"></span></a><a href="?node=printer&sub=delete&id=${id}" title="Delete"><span class="icon icon-delete"></span></>', 
		);	
		// Row attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
			array(),
			array(),
			array('class' => 'c', 'width' => '55'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('Search');
		// Find data
		$Printers = $this->FOGCore->getClass('PrinterManager')->find();
		// Row data
		foreach ((array)$Printers AS $Printer)
		{
			$this->data[] = array(
				'id'		=> $Printer->get('id'),
				'name'		=> quotemeta($Printer->get('name')),
				'config'	=> $Printer->get('config'),
				'model'		=> $Printer->get('model'),
				'port'		=> $Printer->get('port'),
				'file'		=> $Printer->get('file'),
				'ip'		=> $Printer->get('ip')
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('PRINTER_DATA', array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search()
	{
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node=printer&sub=search';
		// Hook
		$this->HookManager->processEvent('PRINTER_SEARCH');
		// Output
		$this->render();
	}
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		$where = array(
			'id'		=> $keyword,
			'name'		=> $keyword,
			'config'	=> $keyword,
			'model'		=> $keyword,
			'port'		=> $keyword,
			'file'		=> $keyword,
			'ip'		=> $keyword
		);
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('PrinterManager')->find($where, 'OR') AS $Printer)
		{
			$this->data[] = array(
				'id'		=> $Printer->get('id'),
				'name'		=> $Printer->get('name'),
				'config'	=> $Printer->get('config'),
				'model'		=> $Printer->get('model'),
				'port'		=> $Printer->get('port'),
				'file'		=> $Printer->get('file'),
				'ip'		=> $Printer->get('ip')
			);
		}
		// Hook
		$this->HookManager->processEvent('PRINTER_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add()
	{
		// Set title
		$this->title = 'New Printer';
		// Header Data
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
		if(!isset($_GET['printertype']))
			$_GET['printertype'] = "Local";
		print "\n\t\t\t".'<form id="printerform" action="?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'" method="post" >';
		$printerTypes = array(
			'Local' => _('Local Printer'),
			'iPrint' => _('iPrint Printer'),
			'Network' => _('Network Printer'),
		);
		foreach ((array)$printerTypes AS $short => $long)
			$optionPrinter .= "\n\t\t\t\t".'<option value="'.$short.'" '.($_GET['printertype'] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
		print "\n\t\t\t".'<select name="printertype" onchange="this.form.submit()">'.$optionPrinter."\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		if ($_GET['printertype'] == 'Network')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				'e.g. '.addslashes('\\\\printerserver\\printername') => '&nbsp;',
			);
		}
		if ($_GET['printertype'] == 'iPrint')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
			);
		}
		if ($_GET['printertype'] == 'Local')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
				_('Printer Model').'*' => '<input type="text" name="model" value="${printer_model}" />',
				_('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',
				_('Printer IP (optional)') => '<input type="text" name="ip" value="${printer_ip}" />',
			);
		}
		$fields['<input type="hidden" name="printertype" value="'.$_GET['printertype'].'" />'] = '<input type="hidden" name="add" value="1" /><input type="submit" value="'._('Add Printer').'" />';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'printer_name' => $_REQUEST['alias'],
				'printer_port' => $_REQUEST['port'],
				'printer_model' => $_REQUEST['model'],
				'printer_inf' => $_REQUEST['inf'],
				'printer_ip' => $_REQUEST['ip'],
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		// Hook
		$this->HookManager->processEvent('PRINTER_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t".'</form>';
	}
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('PRINTER_ADD_POST');
		// POST
		if ($_POST['add'] != 1)
		{
			$this->FOGCore->setMessage('Printer type changed to: '.$_POST['printertype']);
			$this->FOGCore->redirect($this->formAction .'&printertype='.$_POST['printertype']);
		}
		if ($_POST['add'] == 1)
		{
			//Remove spaces from beginning and end offields needed.
			$_POST['alias'] = trim($_POST['alias']);
			$_POST['port'] = trim($_POST['port']);
			$_POST['inf'] = trim($_POST['inf']);
			$_POST['model'] = trim($_POST['model']);
			$_POST['ip'] = trim($_POST['ip']);
			try
			{
				// PrinterManager
				$PrinterManager = $this->FOGCore->getClass('PrinterManager');
				// Error checking
				if($_POST['printertype'] == "Local")
				{
					if(empty($_POST['alias'])||empty($_POST['port'])||empty($_POST['inf'])||empty($_POST['model']))
						throw new Exception('You must specify the alias, port, model, and inf. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_POST['alias'],
							'config'	=> $_POST['printertype'],
							'model'     => $_POST['model'],
							'file' 		=> $_POST['inf'],
							'port' 		=> $_POST['port'],
							'ip'		=> $_POST['ip']
						));
					}
				}
				if($_POST['printertype'] == "iPrint")
				{
					if(empty($_POST['alias'])||empty($_POST['port']))
						throw new Exception('You must specify the alias and port. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_POST['alias'],
							'config'	=> $_POST['printertype'],
							'port'		=> $_POST['port']
						));
					}
				}
				if($_POST['printertype'] == "Network")
				{
					if(empty($_POST['alias']))
						throw new Exception('You must specify the alias. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_POST['alias'],
							'config'	=> $_POST['printertype']
						));
					}
				}
				if ($PrinterManager->exists($_POST['alias']))
					throw new Exception('Printer already exists');
				// Save
				if ($Printer->save())
				{
					// Hook
					$this->HookManager->processEvent('PRINTER_ADD_SUCCESS', array('Printer' => &$Printer));
					// Log History event
					$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Printer created'), $Printer->get('id'), $Printer->get('name')));
					//Send message to user
					$this->FOGCore->setMessage('Printer was created! Editing now!');
					//Redirect to edit
					$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id'));
				}
				else
					throw new Exception('Something went wrong. Add failed');
			}
			catch (Exception $e)
			{
				// Hook
				$this->HookManager->processEvent('PRINTER_ADD_FAIL', array('Printer' => &$Printer));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('User'), $_POST['name'], $e->getMessage()));
				// Set session message
				$this->FOGCore->setMessage($e->getMessage());
				// Redirect user.
				$this->FOGCore->redirect($this->formAction);
			}
		}
	}
	public function edit()
	{
		// Find
		$Printer = new Printer($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $Printer->get('name'));
		// Header Data
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
		if(!isset($_GET['printertype']))
			$_GET['printertype'] = $Printer->get('config');
		if(!isset($_GET['printertype']))
			$_GET['printertype'] = "Local";
		print "\n\t\t\t".'<form id="printerform" action="?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id'].'" method="post" >';
		$printerTypes = array(
			'Local' => _('Local Printer'),
			'iPrint' => _('iPrint Printer'),
			'Network' => _('Network Printer'),
		);
		foreach ((array)$printerTypes AS $short => $long)
			$optionPrinter .= "\n\t\t\t\t".'<option value="'.$short.'" '.($_GET['printertype'] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
		print "\n\t\t\t".'<select name="printertype" onchange="this.form.submit()">'.$optionPrinter."\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		if ($_GET['printertype'] == 'Network')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				'e.g. '.addslashes('\\\\printerserver\\printername') => '&nbsp;',
			);
		}
		if ($_GET['printertype'] == 'iPrint')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
			);
		}
		if ($_GET['printertype'] == 'Local')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
				_('Printer Model').'*' => '<input type="text" name="model" value="${printer_model}" />',
				_('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',
				_('Printer IP (optional)') => '<input type="text" name="ip" value="${printer_ip}" />',
			);
		}
		$fields['<input type="hidden" name="printertype" value="'.$_GET['printertype'].'" />'] = '<input type="hidden" name="update" value="1" /><input type="submit" value="'._('Update Printer').'" />';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'printer_name' => addslashes($Printer->get('name')),
				'printer_port' => $Printer->get('port'),
				'printer_model' => $Printer->get('model'),
				'printer_inf' => addslashes($Printer->get('file')),
				'printer_ip' => $Printer->get('ip'),
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		// Hook
		$this->HookManager->processEvent('PRINTER_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t".'</form>';
	}
	public function edit_post()
	{
		// Find
		$Printer = new Printer($this->request['id']);
		// Hook
		$this->HookManager->processEvent('PRINTER_EDIT_POST', array('Printer' => &$Printer));
		// POST
		if ($_POST['update'] != 1)
		{
			$this->FOGCore->setMessage('Printer type changed to: '.$_POST['printertype'].'.');
			$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id').'&printertype='.$_POST['printertype']);
		}
		if ($_POST['update'] == 1)
		{
			//Remove beginning and trailing spaces
			$_POST['alias'] = trim($_POST['alias']);
			$_POST['port'] = trim($_POST['port']);
			$_POST['inf'] = trim($_POST['inf']);
			$_POST['model'] = trim($_POST['model']);
			$_POST['ip'] = trim($_POST['ip']);
			try
			{
				// PrinterManager
				$PrinterManager = $this->FOGCore->getClass('PrinterManager');
				//Error Checking
				if($_POST['printertype'] == "Local")
				{
					if(empty($_POST['alias'])||empty($_POST['port'])||empty($_POST['inf'])||empty($_POST['model']))
						throw new Exception('You must specify the alias, port, model, and inf. Unable to update!');
					else
					{
						//Update Object
						$Printer ->set('name',		$_POST['alias'])
								 ->set('config',	$_POST['printertype'])
								 ->set('model',		$_POST['model'])
								 ->set('port',		$_POST['port'])
								 ->set('file',		$_POST['inf'])
								 ->set('ip',		$_POST['ip']);
					}
				}
				if($_POST['printertype'] == "iPrint")
				{
					if(empty($_POST['alias'])||empty($_POST['port']))
						throw new Exception('You must specify the alias and port. Unable to update!');
					else
					{
						//Update Object
						$Printer ->set('name',		$_POST['alias'])
								 ->set('config',	$_POST['printertype'])
								 ->set('port',		$_POST['port']);
					}
				}
				if($_POST['printertype'] == "Network")
				{
					if(empty($_POST['alias']))
						throw new Exception('You must specify the alias. Unable to update!');
					else
					{
						//Update Object
						$Printer ->set('name',		$_POST['alias'])
								 ->set('config',	$_POST['printertype']);
					}
				}
				if ($PrinterManager->exists($_POST['alias'], $Printer->get('id')))
					throw new Exception('Printer name already exists, please choose another');
				// Save
				if ($Printer->save())
				{
					// Hook
					$this->HookManager->processEvent('PRINTER_UPDATE_SUCCESS', array('Printer' => &$Printer));
					// Log History event
					$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Printer updated'), $Printer->get('id'), $Printer->get('name')));
					// Set session message
					$this->FOGCore->setMessage('Printer updated!');
					// Redirect for user
					$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id'));
				}
					else
						throw new Exception('Printer update failed!');
			}
			catch (Exception $e)
			{
				// Hook
				$this->HookManager->processEvent('PRINTER_UPDATE_FAIL', array('Printer' => &$Printer));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Printer'), $_POST['alias'], $e->getMessage()));
				// Set session message
				$this->FOGCore->setMessage($e->getMessage());			
				// Redirect to new entry
				$this->FOGCore->redirect($this->formAction);
			}
		}
	}
	public function delete()
	{
		// Find
		$Printer = new Printer($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Remove'), $Printer->get('name'));
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
			_('Please confirm you want to delete').' <b>'.addslashes($Printer->get('name')).'</b>' => '<input type="submit" value="${title}" />',
		);
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'title' => addslashes($this->title),
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		// Hook
		$this->HookManager->processEvent('PRINTER_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function delete_post()
	{
		// Find
		$Printer = new Printer($this->request['id']);
		// Hook
		$this->HookManager->processEvent('PRINTER_DELETE_POST', array('Printer' => &$Printer));		
		// POST
		try
		{			
			// Error checking
			if (!$Printer->destroy())
			{
				throw new Exception(_('Failed to destroy Printer'));
			}	
			// Hook
			$this->HookManager->processEvent('PRINTER_DELETE_SUCCESS', array('Printer' => &$Printer));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Printer deleted'), $Printer->get('id'), $Printer->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Printer deleted'), $Printer->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('PRINTER_DELETE_FAIL', array('Printer' => &$Printer));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('User'), _('deleted'), $Printer->get('id'), $Printer->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
