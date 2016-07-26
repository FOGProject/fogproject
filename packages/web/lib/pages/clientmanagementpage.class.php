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
        printf('<li><h4>%s</h4><div>%s<br/><br/><a href="%s?newclient" class="icon icon-hand" title="%s">%s</a><br/><a href="%s?smartinstaller" class="icon icon-hand" title="%s">%s</a></div></li>',_('New Client and Utilities'),_('The smart installer and msi for '.FOG_CLIENT_VERSION.' of the new client.  Cross platform, more secure, faster, and much easier on the server, especially when your organization has many hosts.'),$url,_('Use this for network installs. For example a GPO policy to push.  This file will only work on Windows.'),_('MSI -- Network Deployment'),$url,_('This is the recommended installer to use now. It can be used on Windows, Linux, and Mac OS X.'),_('Smart Installer (Recommended)'));
        printf('<li><h4>%s</h4><div>%s<br/><br/><br/><a href="https://wiki.fogproject.org/wiki/index.php?title=FOG_Client" class="icon icon-hand" title="%s">%s</a><br/><a href="https://forums.fogproject.org/" class="icon icon-hand" title="%s">%s</a></div></li>',_('Help and Guide'),_('Use the links below if you need assistance. NOTE: Forums are the most common and fastest method of getting help with any aspect of FOG.'),_('Detailed documentation. It is primarily geared for the smart installer methodology now.'),_('FOG Client Wiki'),_('Need more support? Somebody who is able to help is almost always available in some form. Use the forums to post issues so others who may see the issue and use the solutions provided.  Chat is also available on the forums to get more realtime support as needed.'),_('FOG Forums'));
        printf('<li><h4>%s</h4><div>%s<br/><br/><a href="%s?legclient" class="icon icon-hand" title="%s">%s</a><br/><a href="%s?fogcrypt" class="icon icon-hand" title="%s">%s</a></div></li>',_('Legacy Client and Utilities'),_('The legacy client and fog crypt utility for those that are not yet using the new client.  We highly recommend you make the switch for more security and faster client communication and management.'),$url,_('This is the file to install the legacy client. It is highly recommended to NOT use this file but you may do as you please. This client is not being developed any further so any issues you may find, or features you may request, will not be added to this client.'),_('Legacy FOG Client'),$url,_('This file is used to encrypt the AD Password.  DO NOT USE THIS IF USING THE NEW CLIENT.'),_('FOG Crypt'));
        echo '</ul>';
    }
}
