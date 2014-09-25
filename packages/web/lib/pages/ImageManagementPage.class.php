<?php
/**	Class Name: ImageManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages
    Description: This is an extension of the FOGPage Class
    This class controls the image management page for FOG.
    It allows creating and editing of images.

    Manages image settings such as:
    OS Association, Image type (multi part, resizable, raw),
	and the file name and node attached.
**/
class ImageManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Image Management';
	var $node = 'images';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	/** __construct($name = '')
		The basic constructor template for
		index and search functions.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		$this->headerData = array(
			_('Image Name') .'<br /><small>'._('Storage Group').': '._('O/S').'</small><br /><small>'._('Image Type').'</small><br /><small>'._('Partition').'</small>',
			_('Image Size: ON CLIENT'),
			_('Image Size: ON SERVER'),
			_('Uploaded'),
			'',
		);
		// Row templates
		$this->templates = array(
			'<a href="?node='.$this->node.'&sub=edit&'.$this->id.'=${id}" title="'._('Edit').': ${name} Last uploaded: ${deployed}">${name}</a><br /><small>${storageGroup}:${os}</small><br /><small>${image_type}</small><br /><small>${image_partition_type}</small>',
			'${size}',
			'${serv_size}',
			'${deployed}',
			'<a href="?node='.$this->node.'&sub=edit&'.$this->id.'=${id}" title="'._('Edit').'"><span class="icon icon-edit"></span></a> <a href="?node='.$this->node.'&sub=delete&'.$this->id.'=${id}" title="'._('Delete').'"><span class="icon icon-delete"></span></a>',
		);
		// Row attributes
		$this->attributes = array(
			array('width' => 50, 'class' => 'l'),
			array('width' => 50, 'class' => 'c'),
			array('width' => 50, 'class' => 'c'),
			array('width' => 50, 'class' => 'c'),
			array('width' => 50, 'class' => 'c'),
		);
	}
	// Pages
	/** index()
		The default page view for Image Management.  If search is default view, this is not displayed.
	*/
	public function index()
	{
		// Set title
		$this->title = _('All Images');
		// Find data
		$Images = $this->FOGCore->getClass('ImageManager')->find();
		// Row data
		foreach ((array)$Images AS $Image)
		{
			$imageSize = $this->FOGCore->formatByteSize((double)$Image->get('size'));
			$StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
			$servSize = $this->FOGCore->getFTPByteSize($StorageNode,($StorageNode->isValid() ? $StorageNode->get('path').'/'.$Image->get('path') : null));
			$imageType = $Image->get('imageTypeID') ? new ImageType($Image->get('imageTypeID')) : null;
			$imagePartitionType = $Image->get('imagePartitionTypeID') ? new ImagePartitionType($Image->get('imagePartitionTypeID')) : null;
			$this->data[] = array(
				'id'		=> $Image->get('id'),
				'name'		=> $Image->get('name'),
				'description'	=> $Image->get('description'),
				'storageGroup'	=> $Image->getStorageGroup()->get('name'),
				'storageGroupID'=> $Image->getStorageGroup()->get('id'),
				'osID'		=> $Image->get('osID'),
				'os'		=> $Image->getOS()->get('name'),
				'deployed' => $this->validDate($Image->get('deployed')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
				'size'		=> $imageSize,
				'serv_size' => $servSize,
				'image_type' => $imageType && $imageType->isValid() ? $imageType->get('name') : '',
				'image_partition_type' => $imagePartitionType && $imagePartitionType->isValid() ? $imagePartitionType->get('name') : '',
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('IMAGE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** search()
		Search parameter function.
	*/
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=search';
		// Hook
		$this->HookManager->processEvent('IMAGE_DATA');
		// Output
		$this->render();
	}
	/** search_post()
		Used from the search field.  If search is default view, this is how the data gets displayed based
		on what was searched for.
	*/
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Get All images based on the keyword
		$ImageMan = $this->FOGCore->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword,'id' => $keyword),'OR');
		$Images = new ImageManager();
		// Find data -> Push data
		foreach ($Images->search($keyword,'Image') AS $Image)
		{
			$imageSize = $this->FOGCore->formatByteSize((double)$Image->get('size'));
			$StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
			$servSize = $this->FOGCore->getFTPByteSize($StorageNode,($StorageNode->isValid() ? $StorageNode->get('path').'/'.$Image->get('path') : null));
			$imageType = $Image->get('imageTypeID') ? new ImageType($Image->get('imageTypeID')) : null;
			$imagePartitionType = $Image->get('imagePartitionTypeID') ? new ImagePartitionType($Image->get('imagePartitionTypeID')) : null;
			$this->data[] = array(
				'id'		=> $Image->get('id'),
				'name'		=> $Image->get('name'),
				'description'	=> $Image->get('description'),
				'storageGroup'	=> $Image->getStorageGroup()->get('name'),
				'storageGroupID'=> $Image->getStorageGroup()->get('id'),
				'osID'		=> $Image->get('osID'),
				'os'		=> $Image->getOS()->get('name'),
				'deployed' => $this->validDate($Image->get('deployed')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
				'size'		=> $imageSize,
				'serv_size' => $servSize,
				'image_type' => $imageType && $imageType->isValid() ? $imageType->get('name') : '',
				'image_partition_type' => $imagePartitionType && $imagePartitionType->isValid() ? $imagePartitionType->get('name') : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** add()
		Displays the form to create a new image object.
	*/
	public function add()
	{
		// Set title
		$this->title = _('New Image');
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Image Name') => '<input type="text" name="name" id="iName" onblur="duplicateImageName()" value="${image_name}" />',
			_('Image Description') => '<textarea name="description" rows="8" cols="40">${image_desc}</textarea>',
			_('Storage Group') => '${storage_groups}',
			_('Operating System') => '${operating_systems}',
			_('Image Path') => '${image_path}<input type="text" name="file" id="iFile" value="${image_file}" />',
			_('Image Type') => '${image_types}',
			_('Partition') => '${image_partition_types}',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" /><!--<span class="icon icon-help" title="TODO!"></span>-->',
		);
		print "\n\t\t\t<h2>"._('Add new image definition').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'image_name' => $_REQUEST['name'],
				'image_desc' => $_REQUEST['description'],
				'storage_groups' => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox(current($this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1)))->get('storageGroupID')),
				'operating_systems' => $this->FOGCore->getClass('OSManager')->buildSelectBox($_REQUEST['os']),
				'image_path' => current($this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1)))->get('path').'/&nbsp;',
				'image_file' => $_REQUEST['file'],
				'image_types' => $this->FOGCore->getClass('ImageTypeManager')->buildSelectBox($_REQUEST['imagetype'],'','id'),
				'image_partition_types' => $this->FOGCore->getClass('ImagePartitionTypeManager')->buildSelectBox($_REQUEST['imagepartitiontype'],'','id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	/** add_post()
		Actually creates the new image object.
	*/
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('IMAGE_ADD_POST');
		// POST
		try
		{
			$_REQUEST['file'] = trim($_REQUEST['file']);
			// Error checking
			if (empty($_REQUEST['name']))
				throw new Exception('An image name is required!');
			if ($this->FOGCore->getClass('ImageManager')->exists($_REQUEST['name']))
				throw new Exception('An image already exists with this name!');
			if (empty($_REQUEST['file']))
				throw new Exception('An image file name is required!');
			if ($_REQUEST['file'] == 'postdownloadscripts' && $_REQUEST['file'] == 'dev')
				throw new Exception('Please choose a different name, this one is reserved for FOG.');
			if (empty($_REQUEST['storagegroup']))
				throw new Exception('A Storage Group is required!');
			if (empty($_REQUEST['os']))
				throw new Exception('An Operating System is required!');
			if (empty($_REQUEST['imagetype']) || !is_numeric($_REQUEST['imagetype']))
				throw new Exception('An image type is required!');
			if (empty($_REQUEST['imagepartitiontype']) || !is_numeric($_REQUEST['imagepartitiontype']))
				throw new Exception('An image partition type is required!');
			// Create new Object
			$Image = new Image(array(
				'name'		=> $_REQUEST['name'],
				'description'	=> $_REQUEST['description'],
				'storageGroupID'=> $_REQUEST['storagegroup'],
				'osID'		=> $_REQUEST['os'],
				'path'		=> $_REQUEST['file'],
				'imageTypeID'	=> $_REQUEST['imagetype'],
				'imagePartitionTypeID'	=> $_REQUEST['imagepartitiontype']
			));
			// Save
			if ($Image->save())
			{
				// Hook
				$this->HookManager->processEvent('IMAGE_ADD_SUCCESS', array('Image' => &$Image));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Image created'), $Image->get('id'), $Image->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Image created'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Image->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('IMAGE_ADD_FAIL', array('Image' => &$Image));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Image'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** edit()
		Creates the form and display for editing an existing image object.
	*/
	public function edit()
	{
		// Find
		$Image = new Image($this->request['id']);
		// Title - set title for page title in window
		$this->title = sprintf('%s: %s', _('Edit'), $Image->get('name'));
		print "\n\t\t\t".'<div id="tab-container">';
		// Unset the headerData
		unset($this->headerData);
		// Set the table row information
		$this->attributes = array(
			array(),
			array(),
		);
		// Set the template information
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// Set the fields and inputs.
		$fields = array(
			_('Image Name') => '<input type="text" name="name" id="iName" onblur="duplicateImageName()" value="${image_name}" />',
			_('Image Description') => '<textarea name="description" rows="8" cols="40">${image_desc}</textarea>',
			_('Storage Group') => '${storage_groups}',
			_('Operating System') => '${operating_systems}',
			_('Image Path') => '${image_path}<input type="text" name="file" id="iFile" value="${image_file}" />',
			_('Image Type') => '${image_types}',
			_('Partition') => '${image_partition_types}',
			_('Protected') => '<input type="checkbox" name="protected_image" value="1" ${image_protected} />',
			$this->FOGCore->getSetting('FOG_FORMAT_FLAG_IN_GUI') ? _('Image Manager') : '' => $this->FOGCore->getSetting('FOG_FORMAT_FLAG_IN_GUI') ? '<select name="imagemanage"><option value="1" ${is_legacy}>'._('PartImage').'</option><option value="0" ${is_modern}>'._('PartClone').'</option></select>' : '',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Update').'" /><!--<span class="icon icon-help" title="TODO!"></span>-->',
		);
		$StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'image_name' => $Image->get('name'),
				'image_desc' => $Image->get('description'),
				'storage_groups' => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox($Image->get('storageGroupID')),
				'operating_systems' => $this->FOGCore->getClass('OSManager')->buildSelectBox($Image->get('osID')),
				'image_path' => $StorageNode && $StorageNode->isValid() ? $StorageNode->get('path').'/&nbsp;' : 'No nodes available.',
				'image_file' => $Image->get('path'),
				'image_types' => $this->FOGCore->getClass('ImageTypeManager')->buildSelectBox($Image->get('imageTypeID'),'','id'),
				'image_partition_types' => $this->FOGCore->getClass('ImagePartitionTypeManager')->buildSelectBox($Image->get('imagePartitionTypeID'),'','id'),
				'is_legacy' => $Image->get('format') == 1 ? 'selected="selected"' : '',
				'is_modern' => $Image->get('format') == 0 ? 'selected="selected"' : '',
				'image_protected' => $Image->get('protected') == 1 ? 'checked="checked"' : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		print "\n\t\t\t<!-- General -->";
		print "\n\t\t\t".'<div id="image-gen">';
		print "\n\t\t\t<h2>"._('Edit image definition').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-gen">';
		$this->render();
		print '</form>';
		print "\n\t\t\t\t</div>";
		// Reset for next tab
		unset($this->data);
		print "\n\t\t\t\t<!-- Hosts with Assigned Image -->";
		$HostMan = new HostManager();
		// Get hosts with this image assigned
		foreach((array)$Image->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$HostsWithMe[] = $Host->get('id');
		}
		// Get all Host IDs with an image assigned
		foreach($HostMan->find() AS $Host)
		{
			if ($Host && $Host->isValid() && $Host->getImage()->isValid())
				$HostWithAnyImage[] = $Host->get('id');
		}
		// Set the values
		foreach($HostMan->find() AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				if (!in_array($Host->get('id'),$HostWithAnyImage))
					$HostNotWithImage[] = $Host;
				if (!in_array($Host->get('id'),$HostsWithMe))
					$HostNotWithMe[] = $Host;
			}
		}
		print "\n\t\t\t\t".'<div id="image-host">';
		// Create the header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboximage1" class="toggle-checkbox1" />',
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
		// All hosts not with this set as the image
		foreach((array)$HostNotWithMe AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'check_num' => '1',
					'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
				);
			}
		}
		$ImageDataExists = false;
		if (count($this->data) > 0)
		{
			$ImageDataExists = true;
			$this->HookManager->processEvent('IMAGE_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			print "\n\t\t\t<center>"._('Check here to see hosts not assigned with this image').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" />';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-host">';
			print "\n\t\t\t".'<div id="hostNotInMe">';
			print "\n\t\t\t".'<h2>'._('Modify image association for').' '.$Image->get('name').'</h2>';
			print "\n\t\t\t".'<p>'._('Add hosts to image').' '.$Image->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		// Reset the data for the next value
		unset($this->data);
		// Create the header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboximage2" class="toggle-checkbox2" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Last Deployed'),
			_('Registered'),
		);
		// All hosts without an image
		foreach((array)$HostNotWithImage AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
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
			$ImageDataExists = true;
			$this->HookManager->processEvent('IMAGE_HOST_NOT_WITH_ANY',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			print "\n\t\t\t"._('Check here to see hosts not with any image associated').'&nbsp;&nbsp;<input type="checkbox" name="hostNoShow" id="hostNoShow" />';
			print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-host">';
			print "\n\t\t\t".'<div id="hostNoImage">';
			print "\n\t\t\t".'<p>'._('Hosts below have no image association').'</p>';
			print "\n\t\t\t".'<p>'._('Assign hosts with image').' '.$Image->get('name').'</p>';
			$this->render();
			print "\n\t\t\t</div>";
		}
		if ($ImageDataExists)
		{
			print '</br><input type="submit" value="'._('Add Image to Host(s)').'" />';
			print "\n\t\t\t</form></center>";
		}
		unset($this->data);
		array_push($this->headerData,_('Remove Image'));
		array_push($this->templates,'<input type="checkbox" class="delid" onclick="this.form.submit()" name="hostdel" id="hostdelmem${host_id}" value="${host_id}" /><label for="hostdelmem${host_id}">'.$this->foglang['Delete']);
		array_push($this->attributes,array());
		array_splice($this->headerData,1,1);
		array_splice($this->templates,1,1);
		array_splice($this->attributes,1,1);
		foreach((array)$Image->get('hosts') AS $Host)
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
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-host">';
		$this->render();
		print '</form>';
		print "\n\t\t\t\t</div>";
		print "\n\t\t\t</div>";
	}
	/** edit_post()
		Actually updates the image object based on what was filled out in the form.
	*/
	public function edit_post()
	{
		// Find
		$Image = new Image($this->request['id']);
		// Hook
		$this->HookManager->processEvent('IMAGE_EDIT_POST', array('Image' => &$Image));
		// POST
		try
		{
			switch ($_REQUEST['tab'])
			{
				case 'image-gen';
					// Error checking
					if (empty($_REQUEST['name']))
						throw new Exception('An image name is required!');
					if ($Image->get('name') != $_REQUEST['name'] && $this->FOGCore->getClass('ImageManager')->exists($_REQUEST['name'], $Image->get('id')))
						throw new Exception('An image already exists with this name!');
					if ($_REQUEST['file'] == 'postdownloadscripts' && $_REQUEST['file'] == 'dev')
						throw new Exception('Please choose a different name, this one is reserved for FOG.');
					if (empty($_REQUEST['file']))
						throw new Exception('An image file name is required!');
					if (empty($_REQUEST['storagegroup']))
						throw new Exception('A Storage Group is required!');
					if (empty($_REQUEST['os']))
						throw new Exception('An Operating System is required!');
					if (empty($_REQUEST['imagetype']) && $_REQUEST['imagetype'] != '0')
						throw new Exception('An image type is required!');
					if (empty($_REQUEST['imagepartitiontype']) && $_REQUEST['imagepartitiontype'] != '0')
						throw new Exception('An image partition type is required!');
					// Update Object
					$Image	->set('name',		$_REQUEST['name'])
						->set('description',	$_REQUEST['description'])
						->set('storageGroupID',	$_REQUEST['storagegroup'])
						->set('osID',		$_REQUEST['os'])
						->set('path',		$_REQUEST['file'])
						->set('imageTypeID',	$_REQUEST['imagetype'])
						->set('imagePartitionTypeID',	$_REQUEST['imagepartitiontype'])
						->set('format',isset($_REQUEST['imagemanage']) ? $_REQUEST['imagemanage'] : $Image->get('format') )
						->set('protected', $_REQUEST['protected_image']);
				break;
				case 'image-host';
					if ($_REQUEST['host'])
						$Image->addHost($_REQUEST['host']);
					if ($_REQUEST['hostdel'])
						$Image->removeHost($_REQUEST['hostdel']);
				break;
			}
			// Save
			if ($Image->save())
			{
				// Hook
				$this->HookManager->processEvent('IMAGE_UPDATE_SUCCESS', array('Image' => &$Image));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Image updated'), $Image->get('id'), $Image->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Image updated'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Image->get('id'), $_REQUEST['tab']));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('IMAGE_UPDATE_FAIL', array('Image' => &$Image));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Image'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** delete()
		Form to delete image object.
	*/
	public function delete()
	{
		// Find
		$Image = new Image($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Remove'), $Image->get('name'));
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${check}',
			'${input}',
		);
		$fields = array(
			_('Please confirm you want to delete').' <b>'.$Image->get('name').'</b>' => '<input type="submit" value="${title}" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'check' => '<input type="checkbox" name="andFile" id="andFile" value="1"><label for="andFile">${file_data}?</label>',
				'file_data' => _('file data too'),
				'title' => $this->title,
			);
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_DELETE', array('Image' => &$Image));
		// Output
		$this->render();
		print '</form>';
	}
	/** delete_post()
		Actually deletes the image object, and file if checked.
	*/
	public function delete_post()
	{
		// Find
		$Image = new Image($this->request['id']);
		// Hook
		$this->HookManager->processEvent('IMAGE_DELETE_POST', array('Image' => &$Image));
		// POST
		try
		{
			if ($Image->get('protected'))
				throw new Exception(_('Image is protected, removal not allowed'));
			// Error checking
			if ($_REQUEST['andFile'] == '1')
				$Image->deleteImageFile();
			if (!$Image->destroy())
				throw new Exception(_('Failed to destroy Object'));
			// Hook
			$this->HookManager->processEvent('IMAGE_DELETE_SUCCESS', array('Image' => &$Image));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Image deleted'), $Image->get('id'), $Image->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Image deleted'), $Image->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('IMAGE_DELETE_FAIL', array('Image' => &$Image));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('Image'), _('deleted'), $Image->get('id'), $Image->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** multicast()
		Creates the multicast session.
	*/
	public function multicast()
	{
		// Set title
		$this->title = $this->foglang['Multicast'];
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Session Name') => '<input type="text" name="name" id="iName" autocomplete="off" value="" />',
			_('Client Count') => '<input type="text" name="count" id="iCount" autocomplete="off" />',
			_('Timeout') => '<input type="text" name="timeout" id="iTimeout" autocomplete="off" />',
			_('Select Image') => '${select_image}',
			'<input type="hidden" name="start" value="1" />' => '<input type="submit" value="'._('Start').'" /><!--span class="icon icon-help" title="TODO!"></span>-->',
		);
		print "\n\t\t\t<h2>"._('Start Multicast Session').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'session_name' => $_REQUEST['name'],
				'client_count' => $_REQUEST['count'],
				'session_timeout' => $_REQUEST['timeout'],
				'select_image' => $this->FOGCore->getClass('ImageManager')->buildSelectBox($_REQUEST['image'],'','id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_MULTICAST_SESS',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		$this->headerData = array(
			_('Task Name'),
			_('Clients'),
			_('Start Time'),
			_('Percent'),
			_('State'),
			_('Stop Task'),
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
			array(),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${mc_name}<br/><small>${image_name}:${os}</small>',
			'${mc_count}',
			'<small>${mc_start}</small>',
			'${mc_percent}',
			'${mc_state}',
			'<a href="?node='.$this->node.'&sub=stop&mcid=${mc_id}" title="Remove"><img src="./images/kill.png" alt="Kill"/></a>',
		);
		$MulticastSessions = $this->FOGCore->getClass('MulticastSessionsManager')->find(array('stateID' => array(0,1,2,3)));
		foreach($MulticastSessions AS $MulticastSession)
		{
			if ($MulticastSession && $MulticastSession->isValid())
			{
				$Image = new Image($MulticastSession->get('image'));
				$TaskState = new TaskState($MulticastSession->get('stateID'));
				$this->data[] = array(
					'mc_name' => $MulticastSession->get('name'),
					'mc_count' => $MulticastSession->get('clients'),
					'image_name' => $Image->get('name'),
					'os' => $Image->getOS(),
					'mc_start' => $this->formatTime($MulticastSession->get('starttime'),'Y-m-d H:i:s'),
					'mc_percent' => $MulticastSession->get('percent'),
					'mc_state' => $TaskState->get('name'),
					'mc_id' => $MulticastSession->get('id'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('IMAGE_MULTICAST_START',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function multicast_post()
	{
		try
		{
			// Error Checking
			if (!trim($_REQUEST['name']))
				throw new Exception(_('Please input a session name'));
			if (!$_REQUEST['image'])
				throw new Exception(_('Please choose an image'));
			if ($this->FOGCore->getClass('MulticastSessionsManager')->exists(trim($_REQUEST['name'])))
				throw new Exception(_('Session with that name already exists'));
			if ($this->FOGCore->getClass('HostManager')->exists(trim($_REQUEST['name'])))
				throw new Exception(_('Session name cannot be the same as an existing hostname'));
			if (is_numeric($_REQUEST['timeout']) && $_REQUEST['timeout'] > 0)
				$this->FOGCore->setSetting('FOG_UDPCAST_MAXWAIT',$_REQUEST['timeout']);
			$countmc = $this->FOGCore->getClass('MulticastSessionsManager')->count(array('stateID' => array(0,1,2,3)));
			$countmctot = $this->FOGCore->getSetting('FOG_MULTICAST_MAX_SESSIONS');
			$Image = new Image($_REQUEST['image']);
			$StorageGroup = new StorageGroup($Image->get('storageGroupID'));
			$StorageNode = $StorageGroup->getMasterStorageNode();
			if ($countmc >= $countmctot)
				throw new Exception(_('Please wait until a slot is open<br/>There are currently '.$countmc.' tasks in queue<br/>Your server only allows '.$countmctot));
			$MulticastSession = new MulticastSessions(array(
				'name' => trim($_REQUEST['name']),
				'port' => $this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT'),
				'image' => $Image->get('id'),
				'stateID' => 1,
				'clients' => $_REQUEST['count'],
				'isDD' => $Image->get('imageTypeID'),
				'starttime' => $this->formatTime('now','Y-m-d H:i:s'),
				'interface' => $StorageNode->get('interface'),
				'logpath' => $Image->get('path'),
			));
			if (!$MulticastSession->save())
				$this->FOGCore->setMessage(_('Failed to create Session'));
			// Sets a new port number so you can create multiple Multicast Tasks.
			$randomnumber = mt_rand(24576,32766)*2;
			while ($randomnumber == $MulticastSession->get('port'))
				$randomnumber = mt_rand(24576,32766)*2;
			$this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
			$this->FOGCore->setMessage(_('Multicast session created').'<br />'.$MulticastSession->get('name').' has been started on port '.$MulticastSession->get('port'));
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
		}
		$this->FOGCore->redirect('?node='.$this->node.'&sub=multicast');
	}
	public function stop()
	{
		if (is_numeric($_REQUEST['mcid']) && $_REQUEST['mcid'] > 0)
		{
			$MulticastSession = new MulticastSessions($_REQUEST['mcid']);
			foreach((array)$this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msid' => $MulticastSession->get('id'))) AS $MulticastAssoc)
			{
				$Task = new Task($MulticastAssoc->get('taskID'));
				$Task->cancel();
			}
			$MulticastSession->set('name',null)->set('stateID',5)->save();
			$this->FOGCore->setMessage(_('Canceled task'));
			$this->FOGCore->redirect('?node='.$this->node.'&sub=multicast');
		}
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
