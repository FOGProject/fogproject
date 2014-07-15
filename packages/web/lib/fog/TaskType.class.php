<?php

// Blackout - 12:23 PM 8/01/2012
class TaskType extends FOGController
{
	// Table
	public $databaseTable = 'taskTypes';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'			=> 'ttID',
		'name'			=> 'ttName',
		'description'		=> 'ttDescription',
		'icon'			=> 'ttIcon',
		'kernel'		=> 'ttKernel',
		'kernelArgs'		=> 'ttKernelArgs',
		'type'			=> 'ttType',		// fog or user
		'isAdvanced'		=> 'ttIsAdvanced',
		'access'		=> 'ttIsAccess'		// both, host or group
	);
<<<<<<< HEAD
	
	// Custom functions
	public function isUpload()
	{
		return preg_match('#type=(2|12|13|16|up)#i', $this->get('kernelArgs'));
=======
	// Custom functions
	public function isUpload()
	{
		return preg_match('#type=(2|16|up)#i', $this->get('kernelArgs'));
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	}
	
	public function isDownload()
	{
<<<<<<< HEAD
		return preg_match('#type=(1|[3-11]|14-15|[17-22]|down)#i', $this->get('kernelArgs'));
=======
		return preg_match('#type=(1|8|15|17|down)#i', $this->get('kernelArgs'));
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	}
	
	public function isMulticast()
	{
		return preg_match('#(type=8|mc=yes)#i', $this->get('kernelArgs'));
	}
	
	public function isDebug()
	{
		return (preg_match('#mode=debug#i', $this->get('kernelArgs')) || preg_match('#mode=onlydebug#i', $this->get('kernelArgs')) ? true : false);
	}
}
