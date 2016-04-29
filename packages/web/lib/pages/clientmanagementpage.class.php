<?php
class ClientManagementPage extends FOGPage {
    public $node = 'client';
    public function __construct($name = '') {
        $this->name = 'Client Management';
        parent::__construct($this->name);
    }
    public function index() {
        $this->title = _('FOG Client Installer');
        $curroot = trim(trim(self::getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s',$curroot) : ''));
        $url = filter_var(sprintf('http://%s%s/client/download.php',self::getSetting('FOG_WEB_HOST'),$webroot),FILTER_SANITIZE_URL);
        echo '<ul id="dashboard-boxes">';
        printf('<li><h4>%s</h4><div>%s<br/><br/><br/><a href="%s?newclient">%s</a><br/><a href="%s?smartinstaller">%s</a></div></li>',_('New Client and Utilities'),_('The new client and smart installer for 0.10.0 of the new clients.  More secure, faster, and much easier on the server when dealing with many hosts.'),$url,_('New FOG Client (Recommended)'),$url,_('Smart Installer'));
        echo'<li><h4></h4><div></div></li>';
        printf('<li><h4>%s</h4><div>%s<br/><br/><a href="%s?legclient">%s</a><br/><a href="%s?fogcrypt">%s</a></div></li>',_('Legacy Client and Utilities'),_('The legacy client and fog crypt utility for those that are not yet using the new client.  We highly recommend you make the switch for more security and faster client communication and management.'),$url,_('Legacy FOG Client'),$url,_('FOG Crypt'));
        echo '</ul>';
    }
}
