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
		$this->title = $this->foglang['AllSN'];
		// Find data
		$StorageNodes = $this->FOGCore->getClass('StorageNodeManager')->find();
		// Row data
		foreach ((array)$StorageNodes AS $StorageNode)
		{
			$StorageGroup = new StorageGroup($StorageNode->get('storageGroupID'));
			$this->data[] = array_merge(
				(array)$StorageNode->get(),
				array(	'isMasterText'		=> ($StorageNode->get('isMaster') ? 'Yes' : 'No'),
					'isEnabledText'		=> ($StorageNode->get('isEnabled') ? 'Yes' : 'No'),
					'isGraphEnabledText'	=> ($StorageNode->get('isGraphEnabled') ? 'Yes' : 'No'),
					'storage_group' => $StorageGroup->get('name'),
				)
			);
		}
		// Header row
		$this->headerData = array(
			$this->foglang['SN'],
			$this->foglang['SG'],
			$this->foglang['Enabled'],
			$this->foglang['GraphEnabled'],
			$this->foglang['MasterNode'],
			''
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, $this->foglang['Edit']),
			sprintf('${storage_group}', $this->node, $this->id),
			sprintf('${isEnabledText}', $this->node, $this->id),
			sprintf('${isGraphEnabledText}', $this->node, $this->id),
			sprintf('${isMasterText}', $this->node, $this->id),
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, $this->foglang['Edit'], $this->node, $this->id, $this->foglang['Delete'])
		);
		// Row attributes
		$this->attributes = array(
			array(),
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
		$this->title = $this->foglang['AddSN'];
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
			$this->foglang['SNName'] => '<input type="text" name="name" value="${node_name}" autocomplete="off" />*',
			$this->foglang['SNDesc'] => '<textarea name="description" rows="8" cols="40" autocomplete="off">${node_desc}</textarea>',
			$this->foglang['IPAdr'] => '<input type="text" name="ip" value="${node_ip}" autocomplete="off" />*',
			$this->foglang['MaxClients'] => '<input type="text" name="maxClients" value="${node_maxclient}" autocomplete="off" />*',
			$this->foglang['IsMasterNode'] => '<input type="checkbox" name="isMaster" value="1" />&nbsp;&nbsp;${span}',
			$this->foglang['SG'] => '${node_group}',
			$this->foglang['ImagePath'] => '<input type="text" name="path" value="${node_path}" autocomplete="off" />',
			$this->foglang['Interface'] => '<input type="text" name="interface" value="${node_interface}" autocomplete="off" />',
			$this->foglang['IsEnabled'] => '<input type="checkbox" name="isEnabled" checked="checked" value="1" />',
			$this->foglang['IsGraphEnabled'].'<br /><small>('.$this->foglang['OnDash'].')'  => '<input type="checkbox" name="isGraphEnabled" checked="checked" value="1" />',
			$this->foglang['ManUser'] => '<input type="text" name="user" value="${node_user}" autocomplete="off" />*',
			$this->foglang['ManPass'] => '<input type="password" name="pass" value="${node_pass}" autocomplete="off" />*',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'.$this->foglang['Add'].'" autocomplete="off" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'node_name' => $_REQUEST['name'],
				'node_desc' => $_REQUEST['description'],
				'node_ip' => $_REQUEST['ip'],
				'node_maxclient' => $_REQUEST['maxClients'] ? $_REQUEST['maxClients'] : 10,
				'span' => '<span class="icon icon-help hand" title="'.$this->foglang['CautionPhrase'].'"></span>',
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
			if (empty($_REQUEST['name']))
				throw new Exception($this->foglang['StorageNameRequired']);
			if ($this->FOGCore->getClass('StorageNodeManager')->exists($_REQUEST['name']))
				throw new Exception($this->foglang['StorageNameExists']);
			if (empty($_REQUEST['ip']))
				throw new Exception($this->foglang['StorageIPRequired']);
			if (empty($_REQUEST['maxClients']))
				throw new Exception($this->foglang['StorageClientsRequired']);
			if (empty($_REQUEST['interface']))
				throw new Exception($this->foglang['StorageIntRequired']);
			if (empty($_REQUEST['user']))
				throw new Exception($this->foglang['StorageUserRequired']);
			if (empty($_REQUEST['pass']))
				throw new Exception($this->foglang['StoragePassRequired']);
			// Create new Object
			$StorageNode = new StorageNode(array(
				'name'			=> $_REQUEST['name'],
				'description'		=> $_REQUEST['description'],
				'ip'			=> $_REQUEST['ip'],
				'maxClients'		=> $_REQUEST['maxClients'],
				'isMaster'		=> ($_REQUEST['isMaster'] ? '1' : '0'),
				'storageGroupID'	=> $_REQUEST['storageGroupID'],
				'path'			=> $_REQUEST['path'],
				'interface'		=> $_REQUEST['interface'],
				'isGraphEnabled'	=> ($_REQUEST['isGraphEnabled'] ? '1' : '0'),
				'isEnabled'		=> ($_REQUEST['isEnabled'] ? '1' : '0'),
				'user'			=> $_REQUEST['user'],
				'pass'			=> $_REQUEST['pass']
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
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SNCreated'], $StorageNode->get('id'), $StorageNode->get('name')));
				// Set session message
				$this->FOGCore->setMessage($this->foglang['SNCreated']);
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s', $_REQUEST['node'], $this->id, $StorageNode->get('id')));
			}
			else
				throw new Exception($this->foglang['DBupfailed']);
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_ADD_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', $this->foglang['SN'], $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit_storage_node()
	{
		// Find
		$StorageNode = new StorageNode($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', $this->foglang['Edit'], $StorageNode->get('name'));
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
			$this->foglang['SNName'] => '<input type="text" name="name" value="${node_name}" autocomplete="off" />*',
			$this->foglang['SNDesc'] => '<textarea name="description" rows="8" cols="40" autocomplete="off">${node_desc}</textarea>',
			$this->foglang['IPAdr'] => '<input type="text" name="ip" value="${node_ip}" autocomplete="off" />*',
			$this->foglang['MaxClients'] => '<input type="text" name="maxClients" value="${node_maxclient}" autocomplete="off" />*',
			$this->foglang['IsMasterNode'] => '<input type="checkbox" name="isMaster" value="1" ${ismaster} autocomplete="off" />&nbsp;&nbsp;${span}',
			$this->foglang['SG'] => '${node_group}',
			$this->foglang['ImagePath'] => '<input type="text" name="path" value="${node_path}" autocomplete="off"/>',
			$this->foglang['Interface'] => '<input type="text" name="interface" value="${node_interface}" autocomplete="off"/>',
			$this->foglang['IsEnabled'] => '<input type="checkbox" name="isEnabled" value="1" ${isenabled}/>',
			$this->foglang['IsGraphEnabled'].'<br /><small>('.$this->foglang['OnDash'].')'  => '<input type="checkbox" name="isGraphEnabled" value="1" ${graphenabled} />',
			$this->foglang['ManUser'] => '<input type="text" name="user" value="${node_user}" autocomplete="off" />*',
			$this->foglang['ManPass'] => '<input type="password" name="pass" value="${node_pass}" autocomplete="off" />*',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'.$this->foglang['Update'].'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
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
				'span' => '<span class="icon icon-help hand" title="'.$this->foglang['CautionPhrase'].'"></span>',
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
		$StorageNode = new StorageNode($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_EDIT_POST', array('StorageNode' => &$StorageNode));
		// POST
		try
		{
			// Error checking
			if (empty($_REQUEST['name']))
				throw new Exception($this->foglang['StorageNameRequired']);
			if ($this->FOGCore->getClass('StorageNodeManager')->exists($_REQUEST['name'], $StorageNode->get('id')))
				throw new Exception($this->foglang['StorageNameExists']);
			if (empty($_REQUEST['ip']))
				throw new Exception($this->foglang['StorageIPRequired']);
			if (! is_numeric($_REQUEST['maxClients']) || $_REQUEST['maxClients'] < 0)
				throw new Exception($this->foglang['StorageClientRequired']);
			if (empty($_REQUEST['interface']))
				throw new Exception($this->foglang['StorageIntRequired']);
			if (empty($_REQUEST['user']))
				throw new Exception($this->foglang['StorageUserRequired']);
			if (empty($_REQUEST['pass']))
				throw new Exception($this->foglang['StoragePassRequired']);
			// Update Object
			$StorageNode	->set('name',		$_REQUEST['name'])
					->set('description',	$_REQUEST['description'])
					->set('ip',		$_REQUEST['ip'])
					->set('maxClients',	$_REQUEST['maxClients'])
					->set('isMaster',	($_REQUEST['isMaster'] ? '1' : '0'))
					->set('storageGroupID',	$_REQUEST['storageGroupID'])
					->set('path',		$_REQUEST['path'])
					->set('interface',	$_REQUEST['interface'])
					->set('isGraphEnabled',	($_REQUEST['isGraphEnabled'] ? '1' : '0'))
					->set('isEnabled',	($_REQUEST['isEnabled'] ? '1' : '0'))
					->set('user',		$_REQUEST['user'])
					->set('pass',		$_REQUEST['pass']);
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
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SNUpdated'], $StorageNode->get('id'), $StorageNode->get('name')));
				// Set session message
				$this->FOGCore->setMessage($this->foglang['SNUpdated']);
				// Redirect back to self;
				$this->FOGCore->redirect($this->formAction);
			}
			else
				throw new Exception($this->foglang['DBupfailed']);
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_EDIT_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s',$this->foglang['SN'], $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete_storage_node()
    {    
        // Find
        $StorageNode = new StorageNode($_REQUEST['id']);
        // Title
        $this->title = sprintf('%s: %s', $this->foglang['Remove'], $StorageNode->get('name'));
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
        	$this->foglang['ConfirmDel'].' <b>'.$StorageNode->get('name').'</b>' => '<input type="submit" value="${title}" />',
        );   
        foreach((array)$fields AS $field => $input)
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
		$StorageNode = new StorageNode($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_NODE_DELETE_POST', array('StorageNode' => &$StorageNode));
		// POST
		try
		{
			// Destroy
			if (!$StorageNode->destroy())
				throw new Exception($this->foglang['FailDelSN']);
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_DELETE_SUCCESS', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SNDelSuccess'], $StorageNode->get('id'), $StorageNode->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', $this->foglang['SNDelSuccess'], $StorageNode->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $_REQUEST['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_NODE_DELETE_FAIL', array('StorageNode' => &$StorageNode));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', $this->foglang['SN'], $this->foglang['Deleted'], $StorageNode->get('id'), $StorageNode->get('name')));
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
		$this->title = $this->foglang['AllSG'];
		// Find data
		$StorageGroups = $this->FOGCore->getClass('StorageGroupManager')->find();
		// Row data
		foreach ((array)$StorageGroups AS $StorageGroup)
			$this->data[] = $StorageGroup->get();
		// Header row
		$this->headerData = array(
			$this->foglang['SG'],
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit-storage-group&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, $this->foglang['Edit']),
			sprintf('<a href="?node=%s&sub=edit-storage-group&%s=${id}" title="%s"><span class="icon icon-edit"></span></a> <a href="?node=%s&sub=delete-storage-group&%s=${id}" title="%s"><span class="icon icon-delete"></span></a>', $this->node, $this->id, $this->foglang['Edit'], $this->node, $this->id, $this->foglang['Delete'])
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
		$this->title = $this->foglang['AddSG'];
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
			$this->foglang['SGName'] => '<input type="text" name="name" value="${storgrp_name}" />',
			$this->foglang['SGDesc'] => '<textarea name="description" rows="8" cols="40">${storgrp_desc}</textarea>',
			'&nbsp;' => '<input type="submit" value="'.$this->foglang['Add'].'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
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
			if (empty($_REQUEST['name']))
				throw new Exception($this->foglang['SGNameReq']);
			if ($this->FOGCore->getClass('StorageGroupManager')->exists($_REQUEST['name']))
				throw new Exception($this->foglang['SGExist']);
			// Create new Object
			$StorageGroup = new StorageGroup(array(
				'name'		=> $_REQUEST['name'],
				'description'	=> $_REQUEST['description']
			));
			// Save
			if ($StorageGroup->save())
			{
				// Hook
				$this->HookManager->processEvent('STORAGE_GROUP_ADD_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SGCreated'], $StorageGroup->get('id'), $StorageGroup->get('name')));
				// Set session message
				$this->FOGCore->setMessage($this->foglang['SGCreated']);
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit-storage-group&%s=%s', $_POST['node'], $this->id, $StorageGroup->get('id')));
			}
			else
				throw new Exception($this->foglang['DBupfailed']);
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_ADD_POST_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', $this->foglang['SG'], $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	
	public function edit_storage_group()
	{
		// Find
		$StorageGroup = new StorageGroup($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', $this->foglang['Edit'], $StorageGroup->get('name'));
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
			$this->foglang['SGName'] => '<input type="text" name="name" value="${storgrp_name}" />',
			$this->foglang['SGDesc'] => '<textarea name="description" rows="8" cols="40">${storgrp_desc}</textarea>',
			'&nbsp;' => '<input type="submit" value="'.$this->foglang['Update'].'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
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
		$StorageGroup = new StorageGroup($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_EDIT_POST', array('StorageGroup' => &$StorageGroup));
		// POST
		try
		{
			// Error checking
			if (empty($_REQUEST['name']))
				throw new Exception($this->foglang['SGName']);
			if ($this->FOGCore->getClass('StorageGroupManager')->exists($_REQUEST['name'], $StorageGroup->get('id')))
				throw new Exception($this->foglang['SGExist']);
			// Update Object
			$StorageGroup	->set('name',		$_REQUEST['name'])
					->set('description',	$_REQUEST['description']);
			// Save
			if ($StorageGroup->save())
			{
				// Hook
				$this->HookManager->processEvent('STORAGE_GROUP_EDIT_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SGUpdated'], $StorageGroup->get('id'), $StorageGroup->get('name')));
				// Set session message
				$this->FOGCore->setMessage($this->foglang['SGUpdated']);
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=storage-group', $_REQUEST['node'], $this->id, $StorageGroup->get('id')));
			}
			else
				throw new Exception($this->foglang['DBupfailed']);
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_EDIT_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', $this->foglang['SG'], $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete_storage_group()
    {    
        // Find
        $StorageGroup = new StorageGroup($_REQUEST['id']);
        // Title
        $this->title = sprintf('%s: %s', $this->foglang['Remove'], $StorageGroup->get('name'));
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
            $this->foglang['ConfirmDel'].' <b>'.$StorageGroup->get('name').'</b>' => '<input type="submit" value="${title}" />',
        );   
        foreach((array)$fields AS $field => $input)
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
		$StorageGroup = new StorageGroup($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST', array('StorageGroup' => &$StorageGroup));
		// POST
		try
		{
			// Error checking
			if ($this->FOGCore->getClass('StorageGroupManager')->count() == 1)
				throw new Exception($this->foglang['OneSG']);
			// Destroy
			if (!$StorageGroup->destroy())
				throw new Exception($this->foglang['FailDelSG']);
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST_SUCCESS', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', $this->foglang['SGDelSuccess'], $StorageGroup->get('id'), $StorageGroup->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', $this->foglang['SGDelSuccess'], $StorageGroup->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s&sub=storage-group', $_REQUEST['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('STORAGE_GROUP_DELETE_POST_FAIL', array('StorageGroup' => &$StorageGroup));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', $this->foglang['SG'], $this->foglang['Deleted'], $StorageGroup->get('id'), $StorageGroup->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
