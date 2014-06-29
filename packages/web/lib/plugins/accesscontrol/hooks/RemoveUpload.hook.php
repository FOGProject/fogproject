<?php
class RemoveUpload extends Hook
{
	var $name = 'RemoveUpload';
	var $description = 'Removes upload links for engineers';
	var $author = 'Rowlett';
	var $active = true;
	public function UploadData($arguments)
	{
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			if ($_REQUEST['node'] == 'tasks' && $_REQUEST['sub'] == 'listhosts')
				unset($arguments['headerData'][3],$arguments['templates'][3]);
		}
	}
	public function EditTasks($arguments)
    {
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			unset($arguments['data'][1],$arguments['template'][1]);
			unset($arguments['data'][11],$arguments['template'][11]);
		}
    }
	public function SubMenuData($arguments)
	{
		if (!in_array($this->FOGUser->get('name'),array('fog')))
		{
			$i = 0;
			foreach($arguments['submenu'][$_REQUEST['node']]['id'] AS $link => $info)
			{
				if (in_array($i,array(0,5,10,3)))
					unset($arguments['submenu'][$_REQUEST['node']]['id'][$link]);
				$i++;
			}
		}
	}
}
// Init AddLocation Tasks
$RemoveUpload = new RemoveUpload();
// Register hooks
$HookManager->register('HOST_DATA', array($RemoveUpload, 'UploadData'));
$HookManager->register('SUB_MENULINK_DATA', array($RemoveUpload, 'SubMenuData'));
$HookManager->register('HOST_EDIT_TASKS', array($RemoveUpload, 'EditTasks'));
$HookManager->register('HOST_EDIT_ADV', array($RemoveUpload, 'EditTasks'));
