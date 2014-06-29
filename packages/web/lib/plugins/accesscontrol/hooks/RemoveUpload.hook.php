<?php
class RemoveUpload extends Hook
{
	var $name = 'RemoveUpload';
	var $description = 'Removes upload links for engineers';
	var $author = 'Rowlett';
	var $active = true;
	public function UploadTableHeader($arguments)
	{
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			if ($_REQUEST['node'] == 'tasks' && $_REQUEST['sub'] == 'listhosts')
				unset($arguments['headerData'][3]);
		}
	}

	public function UploadData($arguments)
	{
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			if ($_REQUEST['node'] == 'tasks' && $_REQUEST['sub'] == 'listhosts')
				unset($arguments['templates'][3]);
		}
	}
	
	public function EditTasks($arguments)
    {
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			unset($arguments['data'][1]);
			unset($arguments['template'][1]);
		}
    }

	public function EditAdvTasks($arguments)
    {
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			unset($arguments['data'][11]);
			unset($arguments['template'][11]);
		}
    }
	
	
}
// Init AddLocation Tasks
$RemoveUpload = new RemoveUpload();
// Register hooks
$HookManager->register('HOST_DATA', array($RemoveUpload, 'UploadTableHeader'));
$HookManager->register('HOST_DATA', array($RemoveUpload, 'UploadData'));
$HookManager->register('HOST_EDIT_ADV', array($RemoveUpload, 'EditAdvTasks'));
$HookManager->register('HOST_EDIT_TASKS', array($RemoveUpload, 'EditTasks'));
