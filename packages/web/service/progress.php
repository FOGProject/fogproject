<?php
/** This file interprets the progress information from FOG.
  * File polled from is on the client at: /tmp/status.fog
  * This data is specifically for partclone at the moment.
  * Will add reference to add partclone to end of file so
  * it can poll between Partimage and Partclone.
  */
require('../commons/base.inc.php');
try
{
	// Get the mac
	$MACAddress = new MACAddress($_REQUEST['mac']);
	if (!$MACAddress->isValid())
		throw new Exception($foglang['InvalidMAC']);
	// get the host
	$Host = $MACAddress->getHost();
	if (!$Host->isValid())
		throw new Exception(_('Invalid host'));
	// get the image (for image size)
	$Image = $Host->getImage();
	if (!$Image->isValid())
		throw new Exception(_('Invalid image'));
	// get the task
	$Task = current($Host->get('task'));
	if (!$Task->isValid())
		throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'),$MACAddress));
	// break apart the received data
	$str = explode('@',base64_decode($_REQUEST['status']));
	// The types that get progress info: Down (1), Up (2), MultiCast (8), Down Debug (15), Up Debug (16), Down No Snap (17)
	$imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17,24));
	if ($imagingTasks)
	{
		// If the subsets all exist, write the data, otherwise leave it alone.
		if ($str[0] && $str[1] && $str[2] && $str[3] && $str[4] && $str[5])
		{
			$Task->set('bpm', $str[0])
				 ->set('timeElapsed', $str[1])
				 ->set('timeRemaining', $str[2])
				 ->set('dataCopied', $str[3])
				 ->set('dataTotal', $str[4])
				 ->set('percent',trim($str[5]))
				 ->set('pct',trim($str[5]))
				 ->save();
			// Suppose I could just add the data together, but easier to just
			// Use the largest partition on the system as the file representation.
			if ($str[6] > (int)$Image->get('size'))
				$Image->set('size',$str[6])->save();
		}
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
