<?php
class Plugin extends FOGController
{
	private $strName, $strDesc, $strEntryPoint, $strVersion, $strPath, $strIcon, $strIconHover, $blIsInstalled, $blIsActive;
	// Table
	public $databaseTable = 'plugins';
	// Name -> Database field name
	public $databaseFields = array(
		'id'			=> 'pID',
		'name'			=> 'pName',
		'state'			=> 'pState',
		'installed'		=> 'pInstalled',
		'version'		=> 'pVersion',
		'pAnon1'		=> 'pAnon1',
		'pAnon2'		=> 'pAnon2',
		'pAnon3'		=> 'pAnon3',
		'pAnon4'		=> 'pAnon4',
		'pAnon5'		=> 'pAnon5',
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'name',
	);
	public function getRunInclude($hash)
	{
		foreach($this->getPlugins() AS $Plugin)
		{
			if(md5(trim($Plugin->getName())) == trim($hash))
			{
				$_SESSION['fogactiveplugin']=serialize($Plugin);
				return $Plugin->getEntryPoint();
			}
		}
		return null;
	}
	public function getActivePlugs()
	{
		$Plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->getName())));
		$this->blIsActive = ($Plugin && $Plugin->isValid() ? ($Plugin->get('state') == 1 ? 1 : 0) : 0);
		$this->blIsInstalled = ($Plugin && $Plugin->isValid() ? ($Plugin->get('installed') == 1 ? 1 : 0) : 0);
	}
	private function getDirs()
	{
		$strLocation = $this->FOGCore->getSetting('FOG_PLUGINSYS_DIR').'/';
		$handle=opendir($strLocation);
		while(false !== ($file=readdir($handle)))
		{
			if(file_exists($strLocation.$file.'/plugin.config.php'))
				$files[] = $strLocation.$file.'/';
		}
		closedir($handle);
		return $files;
	}
	public function getPlugins()
	{
		$cfgfile = 'plugin.config.php';
		foreach($this->getDirs() AS $file)
		{
			include($file.$cfgfile);
			$p=new Plugin(array('name' => $fog_plugin['name']));
			$p->strPath = $file;
			$p->strName = $fog_plugin['name'];
			$p->strDesc = $fog_plugin['description'];
			$p->strEntryPoint = $file.$fog_plugin['entrypoint'];
			$p->strIcon = $file.$fog_plugin['menuicon'];
			$p->strIconHover = $file.$fog_plugin['menuicon_hover'];
			$arPlugs[] = $p;
		}
		return $arPlugs;
	}
	public function activatePlugin($plugincode)
	{
		foreach($this->getPlugins() AS $Plugin)
		{
			if(md5(trim($Plugin->getName())) == trim($plugincode))
			{
				$ME = $this->FOGCore->getClass('PluginManager')->find(array('name' => $Plugin->getName()));
				if (count($ME) > 0)
				{
					$blActive = false;
					foreach($ME AS $Me)
					{
						if($Me->get('state') != 1)
							$blActive = true;
					}
					if (!$blActive)
					{
						$this->set('state',1)
							 ->set('installed',0)
							 ->set('name',$Plugin->getName())
							 ->save();
					}
				}
				else
				{
					$ME = new self(array(
						'name' => $Plugin->getName(),
						'installed' => 0,
						'state' => 1,
					));
					$ME->save();
				}
			}
		}
	}
	public function getPath() {return $this->strPath;}
	public function getName() {return $this->strName;}
	public function getDesc() {return $this->strDesc;}
	public function getEntryPoint() {return $this->strEntryPoint;}
	public function getIcon() {return $this->strIcon;}
	public function isInstalled() {$this->getActivePlugs();return $this->blIsInstalled;}
	public function isActive() {$this->getActivePlugs();return $this->blIsActive;}
	public function getVersion() {return $this->strVersion;}
}
