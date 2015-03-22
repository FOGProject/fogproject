<?php
/**	Class Name: ClientManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages

	Description: This is an extension of the FOGPage Class
    This class controls locations you want FOG to associate
	with.  It's only enabled if the plugin is installed.
 
    Useful for:
    Setting up clients that may move from sight to sight.
**/
class ClientManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Client Management';
	var $node = 'client';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
	}
	// Pages
	public function index()
	{
		$this->title = _('FOG Client Installer');
		echo '<ul id="dashboard-boxes"><li><h4>'._('Client Service').'</h4><div>'._('Download the FOG client service. This service allows for advanced management of the PC, including hostname changing, etc...').'<br /><br /><a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogService.zip">'._('FOG Client Service').'</a></div></li><li><h4>'._('FOG Prep').'</h4><div>'._('Download FOG Prep which must be run on computers running Windows 7 immediately prior to image upload.').'<br /><br /><br /><div><a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogPrep.zip">'._('FOG Prep').'</a></div></li><li><h4>'._('FOG Crypt').'</h4><div>'._('Download FOG Crypt which can be used to encrypt the AD Domain Password.').'<br /><br /><br /><a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FOGCrypt.zip">'._('FOG Crypt').'</a></div></li></ul>';
	}
}
