<?php
/**	Class Name: StorageManagementPage
	FOGPage lives in: {fogwebpage}/lib/fog
	Lives in: {fogwebpage}/lib/pages
	Description: This is an extension of the FOGPage Class
	This page is used to setup storage groups and storage
	names.  You can also remove groups and names.

	Useful for:
	Managing storage groups and names.
**/
class StorageManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Storage Management';
	var $node = 'storage';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// Common functions - call Storage Node functions if the default sub's are used
	public function search()
	{
		$this->index();
	}
	public function edit()
	{
		$this->edit_storage_node();
	}
	public function edit_post()
	{
		$this->edit_storage_node_post();
	}
	public function delete()
	{
		$this->delete_storage_node();
	}
	public function delete_post()
	{
		$this->delete_storage_node_post();
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Storage Nodes');
		// Find data
		$StorageNodes = $this->FOGCore->getClass('StorageNodeManager')->find();
		// Row data
		foreach ($StorageNodes AS $StorageNode)
		{
			$this->data[] = array_merge(
				(array)$StorageNode->get(),
				array(	'isMasterText'		=> ($StorageNode->get('isMaster') ? 'Yes' : 'No'),
					'isEnabledText'		=> ($StorageNode->get('isEnabled') ? 'Yes' : 'No'),
					'isGraphEnabledText'	=> ($StorageNode->get('isGraphEnabled') ? 'Yes' : 'No')
				)
			);
		}
		// Header row
		$this->headerData = array(
			_('Storage Node'),
			_('Enabled'),
			_('Graph Enabled'),
			_('Master Node'),
			''
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
			sprintf('${isEnabledText}', $this->node, $this->id),
			sprintf('${isGraphEnabledText}', $this->node, $this->id),
			sprintf('${isMasterText}', $this->node, $this->id),
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '90'),
			array('class' => 'c', 'width' => '90'),
			array('class' => 'c', 'width' => '90'),
			array('class' => 'c', 'width' => '50'),
		);
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	// STORAGE NODE
	public function add_storage_node()
	{
		// Set title
		$this->title = _('Add New Storage Node');
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
		// Fields
		$fields = array(
			_('Storage Node Name') => '<input type="text" name="name" value="${node_name}" />*',
			_('Storage Node Description') => '<textarea name="description" rows="5" cols="40">${node_desc}</textarea>',
			_('IP Address') => '<input type="text" name="ip" value="${node_ip}" />*',
			_('Max Clients') => '<input type="text" name="maxClients" value="${node_maxclient}" />*',
			_('Is Master Node') => '<input type="checkbox" name="isMaster" value="1" />&nbsp;&nbsp;${span}',
			_('Storage Group') => '${node_group}',
			_('Image Path') => '<input type="text" name="path" value="${node_path}" />',
			_('Interface') => '<input type="text" name="interface" value="${node_interface}" />',
			_('Is Enabled') => '<input type="checkbox" name="isEnabled" checked="checked" value="1" />',
			_('Is Graph Enabled').'<br /><small>('._('On Dashboard').')'  => '<input type="checkbox" name="isGraphEnabled" checked="checked" value="1" />',
			_('Management Username') => '<input type="text" name="user" value="${node_user}" />*',
			_('Management Password') => '<input type="password" name="pass" value="${node_pass}" />*',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'node_name' => $_REQUEST['name'],
				'node_desc' => $_REQUEST['description'],
				'node_ip' => $_REQUEST['ip'],
				'node_maxclient' => $_REQUEST['maxClients'] ? $_REQUEST['maxClients'] : 10,
				'span' => '<span class="icon icon-help hand" title="'. _('Use extreme caution with this setting!  This setting, if used incorrectly could potentially wipe out all of your images stored on all current storage nodes.  The \'Is Master Node\' setting defines which node is the distributor of the images.  If you add a blank node, meaning a node that has no images on it, and set it to master, it will distribute its store, which is empty, to all hosts in the group').'"></span>',
				'node_group' => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox(1, 'storageGroupID'),
				'node_path' => $_REQUEST['path'] ? $_REQUEST['path'] : '/images/',
				'node_interface' => $_REQUEST['interface'] ? $_REQUEST['interface'] : 'eth0',
				'node_user' => $_REQUEST['user'],
				'node_pass' => $_REQUEST['pass'],
			);
		}
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
	}
	public function add_storage_node_post()
	{
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_ADD_POST');
		// POST
		try
		{
			// Error checking
			if (empty($this->REQUEST['name']))
				throw new Exception( _('Storage Node Name is required') );
			if ($this->FOGCore->getClass('StorageNodeManager')->exists($this->REQUEST['name']))
				throw new Exception( _('Storage Node already exists') );
			if (empty($this->REQUEST['ip']))
				throw new Exception( _('Storage Node IP Address is required') );
			if (empty($this->REQUEST['maxClients']))
				throw new Exception( _('Storage Node Max Clients is required') );
			if (empty($this->REQUEST['interface']))
				throw new Exception( _('Storage Node Interface is required') );
			if (empty($this->REQUEST['user']))
				throw new Exception( _('Storage Node Management Username is required') );
			if (empty($this->REQUEST['pass']))
				throw new Exception( _('Storage Node Management Password is required') );
			// Create new Object
			$StorageNode = new StorageNode(array(
				'name'			=> $this->REQUEST['name'],
				'description'		=> $this->REQUEST['description'],
				'ip'			=> $this->REQUEST['ip'],
				'maxClients'		=> $this->REQUEST['maxClients'],
				'isMaster'		=> ($this->REQUEST['isMaster'] ? '1' : '0'),
				'storageGroupID'	=> $this->REQUEST['storageGroupID'],
				'path'			=> $this->REQUEST['path'],
				'interface'		=> $this->REQUEST['interface'],
				'isGraphEnabled'	=> ($this->REQUEST['isGraphEnabled'] ? '1' : '0'),
				'isEnabled'		=> ($this->REQUEST['isEnabled'] ? '1' : '0'),
				'user'			=> $this->REQUEST['user'],
				'pass'			=> $this->REQUEST['pass']
			));
			// Save
			if ($StorageNode->save())
			{
				if ($StorageNode->get('isMaster'))
				{
					// Unset other Master Nodes in this Storage Group
					foreach ((array)$this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => '1', 'storageGroupID' => $StorageNode->get('storageGroupID'))) AS $StorageNodeMaster)
					{
						if ($StorageNode->get('id') != $StorageNodeMaster->get('id'))
							$StorageNodeMaster->set('isMaster', '0')->save();
					}
				}
				// Hook
				$this->HookManager->processEvent('STORAGE_NODE_ADD_SUCCESS', array('StorageNode' => &$StorageNode));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Node created'), $StorageNode->get('id'), $StorageNode->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Storage Node created'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node'], $this->id, $StorageNode->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_ADD_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage Node'), $this->REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit_storage_node()
	{
		// Find
		$StorageNode = new StorageNode($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Edit'), $StorageNode->get('name'));
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
		// Fields
		$fields = array(
			_('Storage Node Name') => '<input type="text" name="name" value="${node_name}" />*',
			_('Storage Node Description') => '<textarea name="description" rows="5" cols="40">${node_desc}</textarea>',
			_('IP Address') => '<input type="text" name="ip" value="${node_ip}" />*',
			_('Max Clients') => '<input type="text" name="maxClients" value="${node_maxclient}" />*',
			_('Is Master Node') => '<input type="checkbox" name="isMaster" value="1" ${ismaster} />&nbsp;&nbsp;${span}',
			_('Storage Group') => '${node_group}',
			_('Image Path') => '<input type="text" name="path" value="${node_path}" />',
			_('Interface') => '<input type="text" name="interface" value="${node_interface}" />',
			_('Is Enabled') => '<input type="checkbox" name="isEnabled" value="1" ${isenabled} />',
			_('Is Graph Enabled').'<br /><small>('._('On Dashboard').')'  => '<input type="checkbox" name="isGraphEnabled" value="1" ${graphenabled} />',
			_('Management Username') => '<input type="text" name="user" value="${node_user}" />*',
			_('Management Password') => '<input type="password" name="pass" value="${node_pass}" />*',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'node_name' => $StorageNode->get('name'),
				'node_desc' => $StorageNode->get('description'),
				'node_ip' => $StorageNode->get('ip'),
				'node_maxclient' => $StorageNode->get('maxClients'),
				'ismaster' => $StorageNode->get('isMaster') == 1 ? 'checked="checked"' : '',
				'isenabled' => $StorageNode->get('isEnabled') == 1 ? 'checked="checked"' : '',
				'graphenabled' => $StorageNode->get('isGraphEnabled') == 1 ? 'checked="checked"' : '',
				'span' => '<span class="icon icon-help hand" title="'. _('Use extreme caution with this setting!  This setting, if used incorrectly could potentially wipe out all of your images stored on all current storage nodes.  The \'Is Master Node\' setting defines which node is the distributor of the images.  If you add a blank node, meaning a node that has no images on it, and set it to master, it will distribute its store, which is empty, to all hosts in the group').'"></span>',
				'node_group' => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox($StorageNode->get('storageGroupID'), 'storageGroupID'),
				'node_path' => $StorageNode->get('path'),
				'node_interface' => $StorageNode->get('interface'),
				'node_user' => $StorageNode->get('user'),
				'node_pass' => $StorageNode->get('pass'),
			);
		}
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
	}
	public function edit_storage_node_post()
	{
		// Find
		$StorageNode = new StorageNode($this->request['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_EDIT_POST', array('StorageNode' => &$StorageNode));
		// POST
		try
		{
			// Error checking
			if (empty($this->REQUEST['name']))
				throw new Exception( _('Storage Node Name is required') );
			if ($this->FOGCore->getClass('StorageNodeManager')->exists($this->REQUEST['name'], $StorageNode->get('id')))
				throw new Exception( _('Storage Node already exists') );
			if (empty($this->REQUEST['ip']))
				throw new Exception( _('Storage Node IP Address is required') );
			if (! is_numeric($this->REQUEST['maxClients']) || $this->REQUIRE['maxClients'] < 0)
				throw new Exception( _('Storage Node Max Clients is required') );
			if (empty($this->REQUEST['interface']))
				throw new Exception( _('Storage Node Interface is required') );
			if (empty($this->REQUEST['user']))
				throw new Exception( _('Storage Node Management Username is required') );
			if (empty($this->REQUEST['pass']))
				throw new Exception( _('Storage Node Management Password is required') );
			// Update Object
			$StorageNode	->set('name',		$this->REQUEST['name'])
					->set('description',	$this->REQUEST['description'])
					->set('ip',		$this->REQUEST['ip'])
					->set('maxClients',	$this->REQUEST['maxClients'])
					->set('isMaster',	($this->REQUEST['isMaster'] ? '1' : '0'))
					->set('storageGroupID',	$this->REQUEST['storageGroupID'])
					->set('path',		$this->REQUEST['path'])
					->set('interface',	$this->REQUEST['interface'])
					->set('isGraphEnabled',	($this->REQUEST['isGraphEnabled'] ? '1' : '0'))
					->set('isEnabled',	($this->REQUEST['isEnabled'] ? '1' : '0'))
					->set('user',		$this->REQUEST['user'])
					->set('pass',		$this->REQUEST['pass']);
			// Save
			if ($StorageNode->save())
			{
				if ($StorageNode->get('isMaster'))
				{
					// Unset other Master Nodes in this Storage Group
					foreach ((array)$this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => '1', 'storageGroupID' => $StorageNode->get('storageGroupID'))) AS $StorageNodeMaster)
					{
						if ($StorageNode->get('id') != $StorageNodeMaster->get('id'))
							$StorageNodeMaster->set('isMaster', '0')->save();
					}
				}
				// Hook
				$this->HookManager->processEvent('STORAGE_NODE_EDIT_SUCCESS', array('StorageNode' => &$StorageNode));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Node updated'), $StorageNode->get('id'), $StorageNode->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Storage Node updated'));
				// Redirect back to self;
				$this->FOGCore->redirect($this->formAction);
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_EDIT_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage Node'), $this->REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete_storage_node()
    {    
        // Find
        $StorageNode = new StorageNode($this->request['id']);
        // Title
        $this->title = sprintf('%s: %s', _('Remove'), $StorageNode->get('name'));
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
            _('Please confirm you want to delete').' <b>'.$StorageNode->get('name').'</b>' => '<input type="submit" value="${title}" />',
        );   
        foreach($fields AS $field => $input)
        {    
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'title' => $this->title,
            );   
        }    
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
        // Hook
        $this->HookManager->processEvent('STORAGE_NODE_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
	public function delete_storage_node_post()
	{
		// Find
		$StorageNode = new StorageNode($this->request['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_DELETE_POST', array('StorageNode' => &$StorageNode));
		// POST
		try
		{
			// Destroy
			if (!$StorageNode->destroy())
				throw new Exception(_('Failed to destroy Storage Node'));
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_DELETE_SUCCESS', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Node deleted'), $StorageNode->get('id'), $StorageNode->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Storage Node deleted'), $StorageNode->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_DELETE_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('Storage Node'), _('deleted'), $StorageNode->get('id'), $StorageNode->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	// STORAGE GROUP
	public function storage_group()
	{
		// Set title
		$this->title = _('All Storage Groups');
		// Find data
		$StorageGroups = $this->FOGCore->getClass('StorageGroupManager')->find();
		// Row data
		foreach ($StorageGroups AS $StorageGroup)
			$this->data[] = $StorageGroup->get();
		// Header row
		$this->headerData = array(
			_('Storage Group'),
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit-storage-group&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit')),
			sprintf('<a href="?node=%s&sub=edit-storage-group&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete-storage-group&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, _('Edit'), $this->node, $this->id, _('Delete'))
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '50'),
		);
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add_storage_group()
	{
		// Set title
		$this->title = _('Add New Storage Group');
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
		// Fields
		$fields = array(
			_('Storage Group Name') => '<input type="text" name="name" value="${storgrp_name}" />',
			_('Storage Group Description') => '<textarea name="description" rows="5" cols="40">${storgrp_desc}</textarea>',
			'&nbsp;' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'storgrp_name' => $_REQUEST['name'],
				'storgrp_desc' => $_REQUEST['description'],
			);
		}
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
	}
	public function add_storage_group_post()
	{
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_ADD_POST');
		// POST
		try
		{
			// Error checking
			if (empty($this->REQUEST['name']))
				throw new Exception( _('Storage Group Name is required') );
			if ($this->FOGCore->getClass('StorageGroupManager')->exists($this->REQUEST['name']))
				throw new Exception( _('Storage Group already exists') );
			// Create new Object
			$StorageGroup = new StorageGroup(array(
				'name'		=> $this->REQUEST['name'],
				'description'	=> $this->REQUEST['description']
			));
			// Save
			if ($StorageGroup->save())
			{
				// Hook
				$this->HookManager->processEvent('STORAGE_GROUP_ADD_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Group created'), $StorageGroup->get('id'), $StorageGroup->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Storage Group created'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit-storage-group&%s=%s', $this->request['node'], $this->id, $StorageGroup->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_ADD_POST_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage'), $this->REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	
	public function edit_storage_group()
	{
		// Find
		$StorageGroup = new StorageGroup($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Edit'), $StorageGroup->get('name'));
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
		// Fields
		$fields = array(
			_('Storage Group Name') => '<input type="text" name="name" value="${storgrp_name}" />',
			_('Storage Group Description') => '<textarea name="description" rows="5" cols="40">${storgrp_desc}</textarea>',
			'&nbsp;' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'storgrp_name' => $StorageGroup->get('name'),
				'storgrp_desc' => $StorageGroup->get('description'),
			);
		}
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
	}
	public function edit_storage_group_post()
	{
		// Find
		$StorageGroup = new StorageGroup($this->request['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_EDIT_POST', array('StorageGroup' => &$StorageGroup));
		// POST
		try
		{
			// Error checking
			if (empty($this->REQUEST['name']))
				throw new Exception( _('Storage Group Name is required') );
			if ($this->FOGCore->getClass('StorageGroupManager')->exists($this->REQUEST['name'], $StorageGroup->get('id')))
				throw new Exception( _('Storage Group already exists') );
			// Update Object
			$StorageGroup	->set('name',		$this->REQUEST['name'])
					->set('description',	$this->REQUEST['description']);
			// Save
			if ($StorageGroup->save())
			{
				// Hook
				$this->HookManager->processEvent('STORAGE_GROUP_EDIT_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Group updated'), $StorageGroup->get('id'), $StorageGroup->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Storage Group updated'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=storage-group', $this->request['node'], $this->id, $StorageGroup->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_EDIT_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Storage Group'), $this->REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete_storage_group()
    {    
        // Find
        $StorageGroup = new StorageGroup($this->request['id']);
        // Title
        $this->title = sprintf('%s: %s', _('Remove'), $StorageGroup->get('name'));
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
            _('Please confirm you want to delete').' <b>'.$StorageGroup->get('name').'</b>' => '<input type="submit" value="${title}" />',
        );   
        foreach($fields AS $field => $input)
        {    
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'title' => $this->title,
            );   
        }    
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
        // Hook
        $this->HookManager->processEvent('STORAGE_GROUP_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
	public function delete_storage_group_post()
	{
		// Find
		$StorageGroup = new StorageGroup($this->request['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST', array('StorageGroup' => &$StorageGroup));
		// POST
		try
		{
			// Error checking
			if ($this->FOGCore->getClass('StorageGroupManager')->count() == 1)
				throw new Exception(_('You must have at least one Storage Group'));
			// Destroy
			if (!$StorageGroup->destroy())
				throw new Exception(_('Failed to destroy User'));
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Storage Group deleted'), $StorageGroup->get('id'), $StorageGroup->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Storage Group deleted'), $StorageGroup->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s&sub=storage-group', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('Storage Group'), _('deleted'), $StorageGroup->get('id'), $StorageGroup->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
// Register page with FOGPageManager
$FOGPageManager->register(new StorageManagementPage());
