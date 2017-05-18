<?php
/**
 * Client Management Page
 *
 * PHP version 5
 *
 * Presents the client page where users can download the FOG Client and
 * related utilities as needed.
 *
 * @category ClientManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Client Management Page
 *
 * Presents the client page where users can download the FOG Client and
 * related utilities as needed.
 *
 * @category ClientManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ClientManagementPage extends FOGPage
{
    /**
     * The node that's related to this class
     *
     * @var string
     */
    public $node = 'client';
    /**
     * Initializes the page
     *
     * @param string $name the name to initialize with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Client Management';
        parent::__construct($this->name);
    }
    /**
     * This is the default method called.  Displays what we want on the
     * "home" of the relevant page.
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('FOG Client Installer');
        $webArr = array('name' => array('FOG_WEB_HOST','FOG_WEB_ROOT'));
        list(
            $ip,
            $curroot
        ) = self::getSubObjectIDs('Service', $webArr, 'value');
        $curroot = trim(trim($curroot, '/'));
        $webroot = sprintf(
            '/%s',
            (strlen($curroot) > 1 ? sprintf('%s', $curroot) : '')
        );
        $url = sprintf(
            'http://%s%s/client/download.php',
            $ip,
            $webroot
        );
        $url = filter_var($url, FILTER_SANITIZE_URL);
        echo '<ul class="dashboard-boxes">';
        echo '<li>';
        printf(
            '<h5>%s</h5>',
            _('New Client and Utilities')
        );
        printf(
            '<div>%s %s %s. ',
            _('The smart installer and msi for'),
            FOG_CLIENT_VERSION,
            _('of the new client')
        );
        printf(
            '%s, %s, %s, %s. ',
            _('Cross platform'),
            _('more secure'),
            _('faster'),
            _('and much easier on the server')
        );
        printf(
            '%s.',
            _('Especially when your organization has many hosts')
        );
        echo '<br/>';
        printf(
            '<a href="%s?newclient" class="icon icon-hand" '
            . 'title="%s. %s. %s.">%s -- %s</a><br/>',
            $url,
            _('Use this for network installs'),
            _('For example, a GPO policy to push'),
            _('This file will only work on Windows'),
            _('MSI'),
            _('Network Installer')
        );
        printf(
            '<a href="%s?%s" class="%s" title="%s. %s, %s, %s.">%s (%s)</a>',
            $url,
            'smartinstaller',
            'icon icon-hand',
            _('This is the recommended installer to use now'),
            _('It can be used on Windows'),
            _('Linux'),
            _('and Mac OS X'),
            _('Smart Installer'),
            _('Recommended')
        );
        echo '</div></li>';
        echo '<li>';
        printf(
            '<h5>%s</h5>',
            _('Help and Guide')
        );
        printf(
            '<div>%s. %s: %s %s.<br/><br/>',
            _('Use the links below if you need assistance'),
            _('NOTE'),
            _('Forums are the most common and fastest method of getting'),
            _('help with any aspect of FOG')
        );
        printf(
            '<a href="%s" class="icon icon-hand" '
            . 'title="%s. %s">%s</a><br/>',
            'https://wiki.fogproject.org/wiki/index.php?title=FOG_client',
            _('Detailed documentation'),
            _('It is primarily geared for the smart installer methodology now'),
            _('FOG Client Wiki')
        );
        printf(
            '<a href="%s" class="icon icon-hand" '
            . 'title="%s? %s. %s %s. %s.">%s</a>',
            'https://forums.fogproject.org',
            _('Need more support'),
            _('Somebody will be able to help in some form'),
            _('Use the forums to post issues so others'),
            _('may see the issue and help and/or use the solutions'),
            _('Chat is also available on the forums for more realtime help'),
            _('FOG Forums')
        );
        echo '</div></li>';
        echo '<li>';
        printf(
            '<h5>%s</h5>',
            _('Legacy Client and Utilities')
        );
        printf(
            '<div>%s %s. %s %s.<br/>',
            _('The legacy client and fog crypt utility for those'),
            _('that are not yet using the new client'),
            _('We highly recommend you make the switch for more'),
            _('security and faster client communication and management')
        );
        printf(
            '<a href="%s?legclient" class="icon icon-hand" '
            . 'title="%s. %s %s. %s %s, %s, %s.">%s</a><br/>',
            $url,
            _('This is the file to install the legacy client'),
            _('It is recommended to not use this file but'),
            _('you may do as you please'),
            _('This client is not being developed any further so any issues'),
            _('you may find'),
            _('or features you may request'),
            _('will not be added to this client'),
            _('Legacy FOG Client')
        );
        printf(
            '<a href="%s?fogcrypt" class="icon icon-hand" '
            . 'title="%s. %s">%s</a>',
            $url,
            _('This file is used to encrypt the AD Password'),
            _('DO NOT USE THIS IF YOU ARE USING THE NEW CLIENT'),
            _('FOG Crypt')
        );
        echo '</div></li>';
        echo '</ul>';
    }
}
