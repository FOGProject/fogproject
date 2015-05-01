<?php
class PrinterManagementPage extends FOGPage {
	public function __construct($name = '') {
		$this->name = 'Printer Management';
		$this->node = 'printer';
		parent::__construct($this->name);
		if ($_REQUEST[id]) {
			$this->obj = $this->getClass('Printer',$_REQUEST[id]);
			$this->subMenu = array(
				"$this->linkformat#$this->node-gen" => $this->foglang[General],
				$this->membership => $this->foglang[Membership],
				$this->delformat => $this->foglang[Delete],
			);
			$this->notes = array(
				$this->foglang[Printer] => $this->obj->get('name'),
				$this->foglang[Type] => $this->obj->get('config'),
			);
		}
		$this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu' => &$this->menu,'submenu' => &$this->subMenu,'id' => &$this->id,'notes' => &$this->notes));
		// Header row
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
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
			'<input type="checkbox" name="printer[]" value="${id}" class="toggle-action" checked/>',
			'<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
			'${config}',
			'${model}',
			'${port}',
			'${file}',
			'${ip}',
			'<a href="?node=printer&sub=edit&id=${id}" title="Edit"><i class="icon fa fa-pencil"></i></a><a href="?node=printer&sub=delete&id=${id}" title="Delete"><i class="icon fa fa-minus-circle"></i></>',
		);	
		// Row attributes
		$this->attributes = array(
			array('class' => 'c', 'width' => 16),
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
		if ($_SESSION['DataReturn'] > 0 && $_SESSION['PrinterCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list')
			$this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
		// Find data
		$Printers = $this->getClass('PrinterManager')->find();
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
		// Hook
		$this->HookManager->processEvent('PRINTER_DATA', array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search_post()
	{
		// Find data -> Push data
		foreach ($this->getClass('PrinterManager')->search() AS $Printer)
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
		if(!isset($_REQUEST['printertype']))
			$_REQUEST['printertype'] = "Local";
		print "\n\t\t\t".'<form id="printerform" action="?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'" method="post" >';
		$printerTypes = array(
			'Local' => _('Local Printer'),
			'iPrint' => _('iPrint Printer'),
			'Network' => _('Network Printer'),
		);
		foreach ((array)$printerTypes AS $short => $long)
			$optionPrinter .= "\n\t\t\t\t".'<option value="'.$short.'" '.($_REQUEST['printertype'] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
		print "\n\t\t\t".'<select name="printertype" onchange="this.form.submit()">'.$optionPrinter."\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		if ($_REQUEST['printertype'] == 'Network')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				'e.g. '.addslashes('\\\\printerserver\\printername') => '&nbsp;',
			);
		}
		if ($_REQUEST['printertype'] == 'iPrint')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
			);
		}
		if ($_REQUEST['printertype'] == 'Local')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
				_('Printer Model').'*' => '<input type="text" name="model" value="${printer_model}" />',
				_('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',
				_('Printer IP (optional)') => '<input type="text" name="ip" value="${printer_ip}" />',
			);
		}
		$fields['<input type="hidden" name="printertype" value="'.$_REQUEST['printertype'].'" />'] = '<input type="hidden" name="add" value="1" /><input type="submit" value="'._('Add Printer').'" />';
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
		if ($_REQUEST['add'] != 1)
		{
			$this->FOGCore->setMessage('Printer type changed to: '.$_REQUEST['printertype']);
			$this->FOGCore->redirect($this->formAction .'&printertype='.$_REQUEST['printertype']);
		}
		if ($_REQUEST['add'] == 1)
		{
			//Remove spaces from beginning and end offields needed.
			$_REQUEST['alias'] = trim($_REQUEST['alias']);
			$_REQUEST['port'] = trim($_REQUEST['port']);
			$_REQUEST['inf'] = trim($_REQUEST['inf']);
			$_REQUEST['model'] = trim($_REQUEST['model']);
			$_REQUEST['ip'] = trim($_REQUEST['ip']);
			try
			{
				// PrinterManager
				$PrinterManager = $this->getClass('PrinterManager');
				// Error checking
				if($_REQUEST['printertype'] == "Local")
				{
					if(empty($_REQUEST['alias'])||empty($_REQUEST['port'])||empty($_REQUEST['inf'])||empty($_REQUEST['model']))
						throw new Exception('You must specify the alias, port, model, and inf. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_REQUEST['alias'],
							'config'	=> $_REQUEST['printertype'],
							'model'     => $_REQUEST['model'],
							'file' 		=> $_REQUEST['inf'],
							'port' 		=> $_REQUEST['port'],
							'ip'		=> $_REQUEST['ip']
						));
					}
				}
				if($_REQUEST['printertype'] == "iPrint")
				{
					if(empty($_REQUEST['alias'])||empty($_REQUEST['port']))
						throw new Exception('You must specify the alias and port. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_REQUEST['alias'],
							'config'	=> $_REQUEST['printertype'],
							'port'		=> $_REQUEST['port']
						));
					}
				}
				if($_REQUEST['printertype'] == "Network")
				{
					if(empty($_REQUEST['alias']))
						throw new Exception('You must specify the alias. Unable to create!');
					else
					{
						// Create new Object
						$Printer = new Printer(array(
							'name'		=> $_REQUEST['alias'],
							'config'	=> $_REQUEST['printertype']
						));
					}
				}
				if ($PrinterManager->exists($_REQUEST['alias']))
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
				$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('User'), $_REQUEST['name'], $e->getMessage()));
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
		$Printer = $this->obj;
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $Printer->get('name'));
		print "\n\t\t\t".'<div id="tab-container">';
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
		// Output
		print "\n\t\t\t<!-- General -->";
		print "\n\t\t\t".'<div id="printer-gen">';
		if (!$_REQUEST['printertype'])
			$_REQUEST['printertype'] = $Printer->get('config');
		if (!$_REQUEST['printertype'])
			$_REQUEST['printertype'] = 'Local';
		$printerTypes = array(
			'Local' => _('Local Printer'),
			'iPrint' => _('iPrint Printer'),
			'Network' => _('Network Printer'),
		);
		foreach ((array)$printerTypes AS $short => $long)
			$optionPrinter .= "\n\t\t\t\t".'<option value="'.$short.'" '.($_REQUEST['printertype'] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
		if ($_REQUEST['printertype'] == 'Network')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				'e.g. '.addslashes('\\\\printerserver\\printername') => '&nbsp;',
				'<input type="hidden" name="update" value="1" />' => '&nbsp;',
			);
		}
		if ($_REQUEST['printertype'] == 'iPrint')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
			);
		}
		if ($_REQUEST['printertype'] == 'Local')
		{
			$fields = array(
				_('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
				_('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
				_('Printer Model').'*' => '<input type="text" name="model" value="${printer_model}" />',
				_('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',
				_('Printer IP (optional)') => '<input type="text" name="ip" value="${printer_ip}" />',
			);
		}
		$fields['<input type="hidden" name="printertype" value="'.$_REQUEST['printertype'].'" />'] = '<input type="submit" value="'._('Update Printer').'" />';
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
		// Hook
		$this->HookManager->processEvent('PRINTER_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=printer-type">';
		print "\n\t\t\t".'<select name="printertype" onchange="this.form.submit()">'.$optionPrinter."\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=printer-gen">';
		$this->render();
		print '</form>';
		print "\n\t\t\t</div>";
		unset($this->data);
		print "\n\t\t\t</div>";
	}
	public function edit_post()
	{
		// Find
		$Printer = $this->obj;
		// Hook
		$this->HookManager->processEvent('PRINTER_EDIT_POST', array('Printer' => &$Printer));
		// POST
		try
		{
			switch ($_REQUEST['tab'])
			{
				// Switch the printer type
				case 'printer-type';
					$this->FOGCore->setMessage('Printer type changed to: '.$_REQUEST['printertype']);
					$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id'));
				case 'printer-gen';
					//Remove beginning and trailing spaces
					$_REQUEST['alias'] = trim($_REQUEST['alias']);
					$_REQUEST['port'] = trim($_REQUEST['port']);
					$_REQUEST['inf'] = trim($_REQUEST['inf']);
					$_REQUEST['model'] = trim($_REQUEST['model']);
					$_REQUEST['ip'] = trim($_REQUEST['ip']);
					// Printer Manager
					$PrinterManager = new PrinterManager();
					if ($_REQUEST['printertype'] == 'Local')
					{
						if (!$_REQUEST['alias'] || !$_REQUEST['port'] || !$_REQUEST['inf'] || !$_REQUEST['model'])
							throw new Exception(_('You must specify the alias, port, model, and inf'));
						else
						{
							// Update Object
							$Printer->set('name',$_REQUEST['alias'])
									->set('config',$_REQUEST['printertype'])
									->set('model',$_REQUEST['model'])
									->set('port',$_REQUEST['port'])
									->set('file',$_REQUEST['inf'])
									->set('ip',$_REQUEST['ip']);
						}
					}
					if ($_REQUEST['printertype'] == 'iPrint')
					{
						if (!$_REQUEST['alias'] || !$_REQUEST['port'])
							throw new Exception(_('You must specify the alias and port'));
						else
						{
							$Printer->set('name',$_REQUEST['alias'])
									->set('config',$_REQUEST['printertype'])
									->set('port',$_REQUEST['port']);
						}
					}
					if ($_REQUEST['printertype'] == 'Network')
					{
						if (!$_REQUEST['alias'])
							throw new Exception(_('You must specify the alias'));
						else
							$Printer->set('name',$_REQUEST['alias'])
									->set('config',$_REQUEST['printertype']);
					}
					if ($Printer->get('name') != $_REQUEST['alias'] && $PrinterManager->exists($_REQUEST['alias']))
						throw new Exception(_('Printer name already exists, please choose another'));
				break;
			}
			// Save
			if ($Printer->save())
			{
				// Hook
				$this->HookManager->processEvent('PRINTER_UPDATE_SUCCESS', array('Printer' => &$Printer));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Printer updated'), $Printer->get('id'), $Printer->get('name')));
				// Set session message
				$this->FOGCore->setMessage('Printer updated!');
			}
			else
				throw new Exception('Printer update failed!');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('PRINTER_UPDATE_FAIL', array('Printer' => &$Printer));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Printer'), $_REQUEST['alias'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());			
		}
		// Redirect for user
		$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id').'#'.$_REQUEST['tab']);
	}
}
