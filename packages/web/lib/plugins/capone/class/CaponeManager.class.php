<<<<<<< HEAD
<?php
/** Class Name: CaponeManager
	Just gives us something to search for other
	capone's within.
	Also used to install the schema for capone
	if the user installs the plugin.
*/
class CaponeManager extends FOGManagerController
{
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	public function install($name)
    {   
        $sql = "CREATE TABLE fog.capone
        (cID INTEGER NOT NULL AUTO_INCREMENT,
        cImageID INTEGER NOT NULL,
        cOSID INTEGER NOT NULL,
        cKey VARCHAR(250) NOT NULL,
        PRIMARY KEY(cID),
        INDEX new_index (cImageID),
        INDEX new_index2 (cKey))
        ENGINE = MyISAM";
        if ($this->DB->query($sql))
        {   
            $CaponeDMI = new Service(array(
                'name' => 'FOG_PLUGIN_CAPONE_DMI',
                'description' => 'This setting is used for the capone module to set the DMI field used.',
                'value' => '', 
                'category' => 'Plugin: '.$name,
            )); 
            $CaponeDMI->save();
            $CaponeRegEx = new Service(array(
                'name' => 'FOG_PLUGIN_CAPONE_REGEX',
                'description' => 'This setting is used for the capone module to set the reg ex used.',
                'value' => '', 
                'category' => 'Plugin: '.$name,
            )); 
            $CaponeRegEx->save();
			$CaponeShutdown = new Service(array(
				'name' => 'FOG_PLUGIN_CAPONE_SHUTDOWN',
				'description' => 'This setting is used for the capone module to set the shutdown after imaging.',
				'value' => '',
				'category' => 'Plugin: '.$name,
			));
			$CaponeShutdown->save();
            return true;
        }   
        return false;
    }
	public function uninstall()
	{
		if( !$this->DB->query("DROP TABLE capone"))
			return false;
		if (!$this->FOGCore->getClass('ServiceManager')->destroy(array('name' => 'FOG_PLUGIN_CAPON_%')))
			return false;
		return true;
	}
}
=======
<?php
/** Class Name: CaponeManager
	Just gives us something to search for other
	capone's within.
	Also used to install the schema for capone
	if the user installs the plugin.
*/
class CaponeManager extends FOGManagerController
{
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	public function install($name)
    {   
        $sql = "CREATE TABLE capone
        (cID INTEGER NOT NULL AUTO_INCREMENT,
        cImageID INTEGER NOT NULL,
        cOSID INTEGER NOT NULL,
        cKey VARCHAR(250) NOT NULL,
        PRIMARY KEY(cID),
        INDEX new_index (cImageID),
        INDEX new_index2 (cKey))
        ENGINE = MyISAM";
        if ($this->DB->query($sql))
        {   
            $CaponeDMI = new Service(array(
                'name' => 'FOG_PLUGIN_CAPONE_DMI',
                'description' => 'This setting is used for the capone module to set the DMI field used.',
                'value' => '', 
                'category' => 'Plugin: '.$name,
            )); 
            $CaponeDMI->save();
            $CaponeRegEx = new Service(array(
                'name' => 'FOG_PLUGIN_CAPONE_REGEX',
                'description' => 'This setting is used for the capone module to set the reg ex used.',
                'value' => '', 
                'category' => 'Plugin: '.$name,
            )); 
            $CaponeRegEx->save();
			$CaponeShutdown = new Service(array(
				'name' => 'FOG_PLUGIN_CAPONE_SHUTDOWN',
				'description' => 'This setting is used for the capone module to set the shutdown after imaging.',
				'value' => '',
				'category' => 'Plugin: '.$name,
			));
			$CaponeShutdown->save();
            return true;
        }   
        return false;
    }
	public function uninstall()
	{
		if (!$this->DB->query("DROP TABLE capone"))
			return false;
		if (!$this->getClass('ServiceManager')->destroy(array('name' => 'FOG_PLUGIN_CAPON_%')))
			return false;
		if (!$this->getClass('PXEMenuOptionsManager')->destroy(array('name' => 'fog.capone')))
			return false;
		return true;
	}
}
>>>>>>> dev-branch
