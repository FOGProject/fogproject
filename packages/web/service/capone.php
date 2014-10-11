<<<<<<< HEAD
<?php
require('../commons/base.inc.php');
try
{
	// Send the dmi information.
	if ($_REQUEST['action'] == 'dmi')
		print $FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI');
	// Get the lookup.
	else if ($_REQUEST['action'] == 'imagelookup' && $_REQUEST['key'] != null)
	{
		$key = trim(base64_decode(trim($_REQUEST['key'])));
		// Find the key association
		$Capones = $FOGCore->getClass('CaponeManager')->find();
		if ($FOGCore->getClass('CaponeManager')->count() > 0)
		{
			foreach($Capones AS $Capone)
			{
				if (stristr($key,$Capone->get('key')))
				{
					$Image = new Image($Capone->get('imageID'));
					$OS = new OS($Capone->get('osID'));
					$StorageGroup = new StorageGroup($Image->get('storageGroupID'));
					$StorageNode = $StorageGroup->getMasterStorageNode();
					switch($Image->get('imageTypeID'))
					{
						case 1:
							$imgType = 'n';
							break;
						case 2:
							$imgType = 'mps';
							break;
						case 3:
							$imgType = 'mpa';
							break;
						case 4:
							$imgType = 'dd';
							break;
					}
					$ret[] = base64_encode($Image->get('path').'|'.$OS->get('id').'|'.$imgType);
				}
			}
			throw new Exception((count($ret) > 0 ? implode("\n",$ret) : base64_encode('null')));
		}
		else
			throw new Exception(base64_encode('null'));
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
<?php
require('../commons/base.inc.php');
try
{
	// Send the dmi information.
	if ($_REQUEST['action'] == 'dmi')
		print $FOGCore->getSetting('FOG_PLUGIN_CAPONE_DMI');
	// Get the lookup.
	else if ($_REQUEST['action'] == 'imagelookup' && $_REQUEST['key'] != null)
	{
		$key = trim(base64_decode(trim($_REQUEST['key'])));
		// Find the key association
		$Capones = $FOGCore->getClass('CaponeManager')->find();
		if ($FOGCore->getClass('CaponeManager')->count() > 0)
		{
			foreach($Capones AS $Capone)
			{
				if (stristr($key,$Capone->get('key')))
				{
					$Image = new Image($Capone->get('imageID'));
					$OS = new OS($Capone->get('osID'));
					$StorageGroup = new StorageGroup($Image->get('storageGroupID'));
					$StorageNode = $StorageGroup->getMasterStorageNode();
					switch($Image->get('imageTypeID'))
					{
						case 1:
							$imgType = 'n';
							break;
						case 2:
							$imgType = 'mps';
							break;
						case 3:
							$imgType = 'mpa';
							break;
						case 4:
							$imgType = 'dd';
							break;
					}
					$imgPartitionType = $Image->getImagePartitionType()->get('type');
					$ret[] = base64_encode($Image->get('path').'|'.$OS->get('id').'|'.$imgType.'|'.$imgPartitionType);
				}
			}
			throw new Exception((count($ret) > 0 ? implode("\n",$ret) : base64_encode('null')));
		}
		else
			throw new Exception(base64_encode('null'));
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
>>>>>>> dev-branch
