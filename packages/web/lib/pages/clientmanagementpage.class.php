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
        $this->menu = array();
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
        $webArr = array(
            'name' => array(
                'FOG_WEB_HOST'
            )
        );
        list($ip) = self::getSubObjectIDs(
            'Service',
            $webArr,
            'value'
        );
        $url = sprintf(
            '%s://%s/fog/client/download.php',
            self::$httpproto,
            $ip
        );
        $url = filter_var(
            $url,
            FILTER_SANITIZE_URL
        );
        // Dash boxes row.
        echo '<div class="row">';
        // New Client and utilties
        echo '<div class="col-xs-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('New Client and Utilities');
        echo '</h4>';
        echo '<p class="category">';
        echo _('The installers for the fog client');
        echo '<br/>';
        echo _('Client Version');
        echo ': ';
        echo FOG_CLIENT_VERSION;
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
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
        echo '<a href="'
            . $url
            . '?newclient" data-toggle="tooltip" data-placement="right" ';
        printf(
            'title="%s. %s. %s.">',
            _('Use this for network installs'),
            _('For example, a GPO policy to push'),
            _('This file will only work on Windows')
        );
        echo '<br/>';
        echo _('MSI');
        echo ' -- ';
        echo _('Network Installer');
        echo '<br/>';
        printf(
            '<a href="%s?%s" data-toggle="tooltip" data-placement="right" '
            . 'title="%s. %s, %s, %s.">%s (%s)</a>',
            $url,
            'smartinstaller',
            _('This is the recommended installer to use now'),
            _('It can be used on Windows'),
            _('Linux'),
            _('and Mac OS X'),
            _('Smart Installer'),
            _('Recommended')
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Help and guide box
        echo '<div class="col-xs-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Help and Guide');
        echo '</h4>';
        echo '<p class="category">';
        echo _('Where to get help');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
        printf(
            '%s. %s: %s %s.<br/><br/>',
            _('Use the links below if you need assistance'),
            _('NOTE'),
            _('Forums are the most common and fastest method of getting'),
            _('help with any aspect of FOG')
        );
        echo '<br/>';
        printf(
            '<a href="'
            . 'https://wiki.fogproject.org/wiki/index.php?title=FOG_client'
            . '" data-toggle="tooltip" data-placement="right" '
            . 'title="%s. %s">%s</a><br/>',
            _('Detailed documentation'),
            _('It is primarily geared for the smart installer methodology now'),
            _('FOG Client Wiki')
        );
        printf(
            '<a href="'
            . 'https://forums.fogproject.org'
            . '" data-toggle="tooltip" data-placement="right" '
            . 'title="%s? %s. %s %s. %s.">%s</a>',
            _('Need more support'),
            _('Somebody will be able to help in some form'),
            _('Use the forums to post issues so others'),
            _('may see the issue and help and/or use the solutions'),
            _('Chat is also available on the forums for more realtime help'),
            _('FOG Forums')
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Help and guide box
        echo '<div class="col-xs-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Legacy Client and Utilities');
        echo '</h4>';
        echo '<p class="category">';
        echo _('The old client and fogcrypt, deprecated');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
        printf(
            '%s %s. %s %s.<br/>',
            _('The legacy client and fog crypt utility for those'),
            _('that are not yet using the new client'),
            _('We highly recommend you make the switch for more'),
            _('security and faster client communication and management')
        );
        printf(
            '<a href="'
            . $url
            . '?legclient" data-toggle="tooltip" data-placement="right" '
            . 'title="%s. %s %s. %s %s, %s, %s.">%s</a><br/>',
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
            '<a href="'
            . $url
            . '?fogcrypt" data-toggle="tooltip" data-placement="right" '
            . 'title="%s. %s">%s</a>',
            _('This file is used to encrypt the AD Password'),
            _('DO NOT USE THIS IF YOU ARE USING THE NEW CLIENT'),
            _('FOG Crypt')
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
