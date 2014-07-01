<?php
class Service extends FOGController
{
	// Table
	public $databaseTable = 'globalSettings';
	// Name -> Database field name
	public $databaseFields = array(
		'id'				=> 'settingID',
		'name'				=> 'settingKey',
		'description'		=> 'settingDesc',
		'value'				=> 'settingValue',
		'category'			=> 'settingCategory',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'name',
	);
	//Add a directory to be cleaned
	public function addDir($dir)
	{
		if ($this->FOGCore->getClass('DirCleanerManager')->count(array('path' => addslashes($dir))) > 0)
			throw new Exception(_('Directory already exists.'));
		$NewDir = new DirCleaner(array(
			'path' => $dir,
		));
		$NewDir->save();
	}
	//Remove a directory from being cleaned
	public function remDir($dir)
	{
		$this->FOGCore->getClass('DirCleanerManager')->destroy(array('id' => $dir));
	}
	//Set the display information.
	public function setDisplay($x,$y,$r)
	{
		$keySettings = array(
			'FOG_SERVICE_DISPLAYMANAGER_X' => $x,
			'FOG_SERVICE_DISPLAYMANAGER_Y' => $y,
			'FOG_SERVICE_DISPLAYMANAGER_R' => $r,
		);
		foreach($keySettings AS $name => $value)
			$this->FOGCore->setSetting($name,$value);
	}
	//Set green fog
	public function setGreenFog($h,$m,$t)
	{
		if ($this->FOGCore->getClass('GreenFogManager')->count(array('hour' => $h,'min' => $m)) > 0)
			throw new Exception(_('Time already exists.'));
		else
		{
			$NewGreenFog = new GreenFog(array(
				'hour' => $h,
				'min' => $m,
				'action' => $t,
			));
			$NewGreenFog->save();
		}
	}
	//Remove GreenFog event
	public function remGF($gf)
	{
		$this->FOGCore->getClass('GreenFogManager')->destroy(array('id' => $gf));
	}
	//Add Users for cleanup
	public function addUser($user)
	{
		if ($this->FOGCore->getClass('UserCleanupManager')->count(array('name' => $user)) > 0)
			throw new Exception(_('User already exists.'));
		$this->FOGCore->getClass('UserCleanup',array('name' => $user))->save();
	}
	//Remove Cleanup user
	public function remUser($id)
	{
		$UC = new UserCleanup($id);
		$UC->destroy();
	}
}
