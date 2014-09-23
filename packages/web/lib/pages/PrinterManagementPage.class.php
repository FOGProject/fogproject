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
				$PrinterManager = $this->FOGCore->getClass('PrinterManager');
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
		$Printer = new Printer($this->request['id']);
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
		print "\n\t\t\t".'<!-- Hosts with this printer -->';
		$HostMan = new HostManager();
		// Get Hosts with this printer assigned
		foreach((array)$Printer->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$HostsWithMe[] = $Host->get('id');
		}
		// Get all Host IDs with a printer assigned
		foreach($this->FOGCore->getClass('PrinterAssociationManager')->find() AS $PrinterAssoc)
		{
			if ($PrinterAssoc && $PrinterAssoc->isValid())
				$HostsWithPrinter[] = $PrinterAssoc->get('hostID');
		}
		// Set the values
		foreach($HostMan->find() AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				if (!in_array($Host->get('id'),(array)$HostsWithPrinter))
					$HostNotWithPrinter[] = $Host;
				if (!in_array($Host->get('id'),(array)$HostsWithMe))
					$HostNotWithMe[] = $Host;
			}
		}
		print "\n\t\t\t".'<div id="printer-host">';
		// Create the header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxprinter1" class="toggle-checkbox1" />',
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
		// All hosts not with this set as the image
		$this->attributes = array(
			array('width' => 22, 'id' => 'host-${host_name}'),
			array('class' => 'c', 'width' => 16),
			($_SESSION['FOGPingActive'] ? array('width' => 20) : ''),
			array(),
			array(),
			array(),
		);
		// All hosts not with this printer
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
		$PrinterDataExists = false;
		if (count($this->data) > 0)
		{
			$PrinterDataExists = true;
			$this->HookManager->processEvent('PRINTER_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			print "\n\t\t\t<center>"._('Check here to see hosts not assigned with this printer').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" />';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=printer-host">';
			print "\n\t\t\t".'<div id="hostNotInMe">';
			print "\n\t\t\t".'<h2>'._('Modify printer association for').' '.$Printer->get('name').'</h2>';
			print "\n\t\t\t".'<p>'._('Add hosts to printer').' '.$Printer->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		// Reset the data for the next value
		unset($this->data);
		// Create the header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxprinter2" class="toggle-checkbox2" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Last Deployed'),
			_('Registered'),
		);
		// All hosts not with any printer
		foreach((array)$HostNotWithPrinter AS $Host)
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
			$PrinterDataExists = true;
			$this->HookManager->processEvent('PRINTER_HOST_NOT_WITH_ANY',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			print "\n\t\t\t<center>"._('Check here to see hosts with no printers').'&nbsp;&nbsp;<input type="checkbox" name="hostNoShow" id="hostNoShow" />';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=printer-host">';
			print "\n\t\t\t".'<div id="hostNoPrinter">';
			print "\n\t\t\t".'<p>'._('Hosts below have no printer associations').'</h2>';
			print "\n\t\t\t".'<p>'._('Add hosts with printer').' '.$Printer->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		if ($PrinterDataExists)
		{
			print '</br><input type="submit" value="'._('Add Printer to Host(s)').'" />';
			print "\n\t\t\t</form></center>";
		}
		unset($this->data);
		array_push($this->headerData,_('Is Default'),_('Remove Printer'));
		array_push($this->templates,'<input class="default" onclick="this.form.submit()" type="checkbox" name="default" id="host_printer${host_id}"${is_default} value="1" /><label for="host_printer${host_id}"></label><input type="hidden" name="hostid" value="${host_id}" />','<input type="checkbox" class="delid" onclick="this.form.submit()" name="hostdel" id="hostdelmem${host_id}" value="${host_id}" /><label for="hostdelmem${host_id}">'.$this->foglang['Delete']);
		array_push($this->attributes,array(),array());
		array_splice($this->headerData,1,1);
		array_splice($this->templates,1,1);
		array_splice($this->attributes,1,1);
		foreach((array)$Printer->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
					'is_default' => $Host->getDefault($Printer->get('id')) ? 'checked="checked"' : '',

				);
			}
		}
		// Hook
		$this->HookManager->processEvent('PRINTER_EDIT_HOST', array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=printer-host">';
		$this->render();
		print '</form>';
		print "\n\t\t\t\t</div>";
		print "\n\t\t\t</div>";
	}
	public function edit_post()
	{
		// Find
		$Printer = new Printer($this->request['id']);
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
					if ($PrinterManager->exists($_REQUEST['alias']))
						throw new Exception(_('Printer name already exists, please choose another'));
				break;
				case 'printer-host';
					$Printer->addHost($_REQUEST['host']);
					$Printer->updateDefault($_REQUEST['hostid'],$_REQUEST['default']);
					$Printer->removeHost($_REQUEST['hostdel']);
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
				// Redirect for user
				$this->FOGCore->redirect('?node=printer&sub=edit&id='.$Printer->get('id').'#'.$_REQUEST['tab']);
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
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
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
