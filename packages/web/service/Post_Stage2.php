<?php
//
// Post_Stage2.php
// Triggered:	After image upload
// Actions:	Moves uploaded image to final location via FTP
//
require('../commons/base.inc.php');
try
{
	// Error checking
	// NOTE: Most of these validity checks should never fail as checks are made during Task creation - better safe than sorry!
	// MAC Address
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception(_('Invalid MAC address'));
	// Host for MAC Address
	$Host = $MACAddress->getHost();
	if (!$Host->isValid())
		throw new Exception(_('Invalid Host'));
	// Task for Host
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress));
	$TaskType = new TaskType($Task->get('typeID');
	// Get the storage group
	$StorageGroup = $Task->getStorageGroup();
	if ($TaskType->isUpload() && !$StorageGroup->isValid())
		throw new Exception(_('Invalid Storage Group'));
	// Get the storage node.
	$StorageNodes = $StorageGroup->getStorageNodes();
	if ($TaskType->isUpload() && !$StorageNodes)
		throw new Exception(_('Could not find a Storage Node. Is there one enabled within this Storage Group?'));
	// Image Name store for logging the image task later.
	$Image = new Image($Host->get('imageID'));
	$ImageName = $Image->get('name');
	// Sets the class for ftp of files and deletion as necessary.
	$ftp = $GLOBALS['FOGFTP'];
	// Sets the mac address for tftp delete later.
	$mactftp = strtolower(str_replace(':','-',$_REQUEST['mac']));
	// Sets the mac address for ftp upload later.
	$macftp = strtolower(str_replace(':','',$_REQUEST['mac']));
	// Paths for use later.  Need to pass StorageNodes through loop to access functions.
	foreach ($StorageNodes AS $StorageNode)
	{
		if ($StorageNode->get('isMaster'))
		{
			// Set the src based on the image and node path.
			$src = $StorageNode->get('path').'/dev/'.$macftp;
			// XP only, typically, had one part so only need the file part.
			if ($_REQUEST['osid'] == '1' && $_REQUEST['imgtype'] == 'n')
				$src = $StorageNode->get('path').'/dev/'.$macftp.'/'.$macftp.'.000';
			// Where is it going?
			$dest = $StorageNode->get('path').'/'.$_REQUEST['to'];
			//Attempt transfer of image file to Storage Node
			$ftp->set('host',$StorageNode->get('ip'))
				->set('username',$StorageNode->get('user'))
				->set('password',$StorageNode->get('pass'));
			if (!$ftp->connect())
				throw new Exception(_('Storage Node: '.$StorageNode->get('ip').' FTP Connection has failed!'));
			// Try to delete the file.  Doesn't hurt anything if it doesn't delete anything.
			$ftp->delete($dest);
			if ($ftp->rename($dest,$src)||$ftp->put($dest,$src))
				($_REQUEST['osid'] == '1' ? $ftp->delete($StorageNode->get('path').'/dev/'.$macftp) : null);
			else
				throw new Exception(_('Move/rename failed.'));
			$ftp->close();
		}
	}
	// If image is currently legacy, set as not legacy.
	if ($Image->get('format') == 1)
		$Image->set('format',0)->save();
	// Complete the Task.
	$Task->set('stateID','4');
	if (!$Task->save())
		throw new Exception(_('Failed to update Task'));
	// Log it
	$ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')));
	foreach($ImagingLogs AS $ImagingLog)
		$id[] = $ImagingLog->get('id');
	// Last Uploaded date of image.
	$Image = $Host->getImage();
	$Image->set('deployed',date('Y-m-d H:i:s'))->save();
	// Log
	$il = new ImagingLog(max($id));
	$il->set('finish',date('Y-m-d H:i:s'))->save();
	// Task Logging.
	$TaskLog = new TaskLog($Task);
	$TaskLog->set('taskID',$Task->get('id'))
			->set('taskStateID',$Task->get('stateID'))
			->set('createdTime',$Task->get('createdTime'))
			->set('createdBy',$Task->get('createdBy'))
			->save();
	print '##';
}
catch (Exception $e)
{
	print $e->getMessage();
}
