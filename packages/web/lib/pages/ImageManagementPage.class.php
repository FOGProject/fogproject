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
			_('Image Name') .'<br /><small>'._('Storage Group').': '._('O/S').'</small><br /><small>'._('Image Type').'</small>',
			_('Image Size: ON CLIENT'),
			_('Image Size: ON SERVER'),
			_('Uploaded'),
			'',
		);
		// Row templates
		$this->templates = array(
			'<a href="?node='.$this->node.'&sub=edit&'.$this->id.'=${id}" title="'._('Edit').': ${name} Last uploaded: ${deployed}">${name}</a><br /><small>${storageGroup}:${os}</small><br /><small>${image_type}</small>',
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
			$this->data[] = array(
				'id'		=> $Image->get('id'),
				'name'		=> $Image->get('name'),
				'description'	=> $Image->get('description'),
				'storageGroup'	=> $Image->getStorageGroup()->get('name'),
				'storageGroupID'=> $Image->getStorageGroup()->get('id'),
				'osID'		=> $Image->get('osID'),
				'os'		=> $Image->getOS()->get('name'),
				'deployed' => checkdate($this->FOGCore->formatTime($Image->get('deployed'),'m'),$this->FOGCore->formatTime($Image->get('deployed'),'d'),$this->FOGCore->formatTime($Image->get('deployed'),'Y')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
				'size'		=> $imageSize,
				'serv_size' => $servSize,
				'image_type' => $imageType && $imageType->isValid() ? $imageType->get('name') : '',
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
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('ImageManager')->find(array('name' => $keyword)) AS $Image)
		{
			$imageSize = $this->FOGCore->formatByteSize((double)$Image->get('size'));
			$StorageNode = $Image->getStorageGroup()->getMasterStorageNode();
			$servSize = $this->FOGCore->getFTPByteSize($StorageNode,($StorageNode->isValid() ? $StorageNode->get('path').'/'.$Image->get('path') : null));
			$imageType = $Image->get('imageTypeID') ? new ImageType($Image->get('imageTypeID')) : null;
			$this->data[] = array(
				'id'		=> $Image->get('id'),
				'name'		=> $Image->get('name'),
				'description'	=> $Image->get('description'),
				'storageGroup'	=> $Image->getStorageGroup()->get('name'),
				'storageGroupID'=> $Image->getStorageGroup()->get('id'),
				'osID'		=> $Image->get('osID'),
				'os'		=> $Image->getOS()->get('name'),
				'deployed' => checkdate($this->FOGCore->formatTime($Image->get('deployed'),'m'),$this->FOGCore->formatTime($Image->get('deployed'),'d'),$this->FOGCore->formatTime($Image->get('deployed'),'Y')) ? $this->FOGCore->formatTime($Image->get('deployed')) : 'No Data',
				'size'		=> $imageSize,
				'serv_size' => $servSize,
				'image_type' => $imageType && $imageType->isValid() ? $imageType->get('name') : '',
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
			// Create new Object
			$Image = new Image(array(
				'name'		=> $_REQUEST['name'],
				'description'	=> $_REQUEST['description'],
				'storageGroupID'=> $_REQUEST['storagegroup'],
				'osID'		=> $_REQUEST['os'],
				'path'		=> $_REQUEST['file'],
				'imageTypeID'	=> $_REQUEST['imagetype']
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
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Image'), $_POST['name'], $e->getMessage()));
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
				'is_legacy' => $Image->get('format') == 1 ? 'selected="selected"' : '',
				'is_modern' => $Image->get('format') == 0 ? 'selected="selected"' : '',
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
		$this->headerData = array(
			_('Host Name'),
			_('MAC'),
			_('Remove Image?'),
		);
		$this->templates = array(
			'<a href="?node=host&sub=edit&id=${host_id}" title="'._('Edit Host').':${host_name}">${host_name}</a>',
			'${host_mac}',
			'<input type="checkbox" class="delid" onclick="this.form.submit()" name="hostdel" id="hostdelmem${host_id}" value="${host_id}" /><label for="hostdelmem${host_id}">'._('Delete').'</label>',
		);
		foreach((array)$Image->get('hosts') AS $Host)
			$HostIDs[] = $Host && $Host->isValid() ? $Host->get('id') : '';
		$HostStuff = $this->FOGCore->getClass('HostManager')->buildSelectBox('','host[]" multiple="multiple','',$HostIDs);
		foreach((array)$Image->get('hosts') AS $Host)
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
		$this->HookManager->processEvent('IMAGE_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		print "\n\t\t\t\t<!-- Hosts with Assigned Image -->";
		print "\n\t\t\t\t".'<div id="image-host">';
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=image-host">';
		if ($HostStuff)
		{
			print "\n\t\t\t<p>"._('The selection box below will add this image to the selected hosts automatically.').'</p>';
			print "\n\t\t\t<p><center>$HostStuff";
			print "\n\t\t\t".'<input type="submit" value="'._('Add Image to Host(s)').'" /></center></p>';
		}
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
					if (empty($_POST['name']))
						throw new Exception('An image name is required!');
					if ($Image->get('name') != $_POST['name'] && $this->FOGCore->getClass('ImageManager')->exists($_POST['name'], $Image->get('id')))
						throw new Exception('An image already exists with this name!');
					if ($_REQUEST['file'] == 'postdownloadscripts' && $_REQUEST['file'] == 'dev')
						throw new Exception('Please choose a different name, this one is reserved for FOG.');
					if (empty($_POST['file']))
						throw new Exception('An image file name is required!');
					if (empty($_POST['storagegroup']))
						throw new Exception('A Storage Group is required!');
					if (empty($_POST['os']))
						throw new Exception('An Operating System is required!');
					if (empty($_POST['imagetype']) && $_POST['imagetype'] != '0')
						throw new Exception('An image type is required!');
					// Update Object
					$Image	->set('name',		$_POST['name'])
						->set('description',	$_POST['description'])
						->set('storageGroupID',	$_POST['storagegroup'])
						->set('osID',		$_POST['os'])
						->set('path',		$_POST['file'])
						->set('imageTypeID',	$_POST['imagetype'])
						->set('format',isset($_REQUEST['imagemanage']) ? $_REQUEST['imagemanage'] : $Image->get('format') );
				break;
				case 'image-host';
					if ($_POST['host'])
						$Image->addHost($_POST['host']);
					if ($_POST['hostdel'])
						$Image->removeHost($_POST['hostdel']);
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
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Image'), $_POST['name'], $e->getMessage()));
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
			// Error checking
			if ($_POST['andFile'] == '1')
			{
				if (!$Image->deleteImageFile())
					throw new Exception(_('Failed to delete file!'));
			}
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
}
