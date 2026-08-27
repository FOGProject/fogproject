<?php
/**
 * Client Management Page
 *
 * PHP version 7.4+
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

namespace FOG;

use FOG\Base\FOGPage;

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
        $this->name = _('Client Management');
        parent::__construct($this->name);
    }
    /**
     * This is the default method called.  Displays what we want on the
     * "home" of the relevant page.
     *
     * @return void
     */
    public function index(...$args)
    {
        $webArr = [
            'name' => [
                'FOG_WEB_HOST'
            ]
        ];
        $ip = self::getSetting('FOG_WEB_HOST');
        $url = sprintf(
            '%s://%s/%s/client/download.php',
            self::$httpproto,
            $ip,
            self::webrootPath()
        );
        $url = filter_var(
            $url,
            FILTER_SANITIZE_URL
        );
        echo '<div class="row">';
        echo '<!-- FOG Client Installers -->';
        // Dash boxes row.
        echo '<div class="col-md-6">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('FOG Client Installers');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<p class="form-text">';
        echo _('The installers for the fog client');
        echo '<br/>';
        echo _('Client Version');
        echo ': ';
        echo FOG_CLIENT_VERSION;
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
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
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Help and Guides');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<p class="form-text">';
        echo _('Where to get help and guides');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ClientManagement', 'ClientManagement');
