<?php
/**
 * Client Management Page
 *
 * PHP version 5
 *
 * Presents the client page where users can download the FOG Client and
 * related utilities as needed.
 *
 * @category ClientManagement
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
 * @category ClientManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ClientManagement extends FOGPage
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
        $webArr = [
            'name' => [
                'FOG_WEB_HOST'
            ]
        ];
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
        echo '<div class="box-group">';
        echo '<!-- FOG Client Installers -->';
        // Dash boxes row.
        echo '<div class="col-md-6">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('FOG Client Installers');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<p class="help-block">';
        echo _('The installers for the fog client');
        echo '<br/>';
        echo _('Client Version');
        echo ': ';
        echo FOG_CLIENT_VERSION;
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo _(
            'Cross platform, more secure, faster, and much easier on the server. '
            . 'Espeically when your organization has many hosts'
        );
        echo '<br/><br/>';
        echo '<a href="'
            . $url
            . '?newclient'
            . '">'
            . _('MSI -- Network Installer')
            . '</a>';
        echo '<br/>';
        echo '<a href="'
            . $url
            . '?smartinstaller">'
            . _('Smart Installer')
            . ' ('
            . _('recommended')
            . ')'
            . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Help and guide box
        echo '<!-- Where to get help -->';
        echo '<div class="col-md-6">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Help and Guides');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<p class="help-block">';
        echo _('Where to get help and guides');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo _('Use the links below if you need assistance.');
        echo '<br/>';
        echo _(
            'NOTE: Forums are the most command fastest method of '
            . 'getting help with any aspect of FOG.'
        );
        echo '<br/><br/><br/>';
        echo '<a href="https://wiki.fogproject.org/wiki/index.php?title=FOG_client">'
            . _('FOG Client Wiki')
            . '</a>';
        echo '<br/>';
        echo '<a href="https://forums.fogproject.org">'
            . _('FOG Forums')
            . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
