<?php
/** Class Name: CaponeManager
	Just gives us something to search for other
	capone's within.
	Also used to install the schema for capone
	if the user installs the plugin.
*/
class CaponeManager extends FOGManagerController
{
	/** addSchema($name)
		Function just creates the database
		entries used by Capone.
		\variable $name
		Sends the plugin name so things
		update appropriately.
	*/
	public function addSchema($name)
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
}
