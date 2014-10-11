<<<<<<< HEAD
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
	var $name = 'Client Managment';
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
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('Client Service').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download the FOG client service. This service allows for advanced management of the PC, including hostname changing, etc...');
		print "\n\t\t\t\t\t\t\t".'<br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogService.zip">';
		print "\n\t\t\t\t\t\t\t\t\t"._('FOG Client Service');
		print "\n\t\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('FOG Prep').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download FOG Prep which must be run on computers running Windows 7 immediately prior to image upload.');
		print "\n\t\t\t\t\t\t\t".'<br /><br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogPrep.zip">';
		print "\n\t\t\t\t\t\t\t\t"._('FOG Prep');
		print "\n\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('FOG Crypt').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download FOG Crypt which can be used to encrypt the AD Domain Password.');
		print "\n\t\t\t\t\t\t\t".'<br /><br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FOGCrypt.zip">';
		print "\n\t\t\t\t\t\t\t\t"._('FOG Crypt');
		print "\n\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
	}
}
=======
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
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('Client Service').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download the FOG client service. This service allows for advanced management of the PC, including hostname changing, etc...');
		print "\n\t\t\t\t\t\t\t".'<br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogService.zip">';
		print "\n\t\t\t\t\t\t\t\t\t"._('FOG Client Service');
		print "\n\t\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('FOG Prep').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download FOG Prep which must be run on computers running Windows 7 immediately prior to image upload.');
		print "\n\t\t\t\t\t\t\t".'<br /><br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FogPrep.zip">';
		print "\n\t\t\t\t\t\t\t\t"._('FOG Prep');
		print "\n\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t\t".'<div class="dashbaord">';
		print "\n\t\t\t\t\t\t".'<p class="infoTitle">'._('FOG Crypt').'</p>';
		print "\n\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t"._('Download FOG Crypt which can be used to encrypt the AD Domain Password.');
		print "\n\t\t\t\t\t\t\t".'<br /><br /><br />';
		print "\n\t\t\t\t\t\t\t".'<p class="noSpace">';
		print "\n\t\t\t\t\t\t\t\t".'<a href="http://'.$this->FOGCore->getSetting('FOG_WEB_HOST').'/'.ltrim(rtrim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'),'/').'/client/FOGCrypt.zip">';
		print "\n\t\t\t\t\t\t\t\t"._('FOG Crypt');
		print "\n\t\t\t\t\t\t\t</a>";
		print "\n\t\t\t\t\t\t</p>";
		print "\n\t\t\t\t\t</div>";
	}
}
>>>>>>> dev-branch
