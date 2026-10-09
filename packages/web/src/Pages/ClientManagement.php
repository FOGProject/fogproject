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

namespace FOG\Pages;

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
        // The address the agent is given is the web UI's own, without
        // /management -- the same base the legacy installer links use.
        $server = \Initiator::e(
            sprintf('%s://%s/%s', self::$httpproto, $ip, self::webrootPath())
        );
        echo '<div class="row">';
        // 1.6 hosts are meant to run the FOG Agent, so it comes first. The
        // examples carry this server's address; the fingerprint and token
        // stay placeholders, since this page is reachable signed out.
        echo '<!-- FOG Agent -->';
        echo '<div class="col-12">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('FOG Agent');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<p class="form-text">';
        echo _('The agent replaces the FOG Client. Installing it removes the legacy client.');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<a class="btn btn-primary" href="https://github.com/FOGProject/fog-agent/releases/latest"'
            . ' target="_blank" rel="noopener noreferrer">';
        echo _('Download the FOG Agent');
        echo '</a>';
        echo '<h5 class="mt-4">';
        echo _('Windows: silent MSI install');
        echo '</h5>';
        echo '<pre class="user-select-all"><code>'
            . 'msiexec /i fog-agent-&lt;version&gt;-x64.msi /qn SERVER='
            . $server
            . ' CAFINGERPRINT=&lt;fingerprint&gt; TOKEN=&lt;token&gt;'
            . ' /l*v C:\fog-agent-install.log'
            . '</code></pre>';
        echo '<h5 class="mt-4">';
        echo _('Enroll from the command line');
        echo '</h5>';
        echo '<p class="mb-1">' . _('Windows, from an administrator prompt (installs the service):') . '</p>';
        echo '<pre class="user-select-all"><code>'
            . 'fog-agent.exe service install --server '
            . $server
            . ' --ca-fingerprint &lt;fingerprint&gt; --token &lt;token&gt;'
            . '</code></pre>';
        echo '<p class="mb-1">' . _('Linux and macOS, as root:') . '</p>';
        echo '<pre class="user-select-all"><code>'
            . 'fog-agent enroll --server '
            . $server
            . ' --ca-fingerprint &lt;fingerprint&gt; --token &lt;token&gt;'
            . '</code></pre>';
        echo '<ul class="mb-0">';
        echo '<li>'
            . _('fingerprint: the SHA-256 of the Root row under FOG Configuration, Certificates.')
            . ' '
            . _('Leave it out when the web UI uses a public or corporate certificate the host already trusts.')
            . '</li>';
        echo '<li>'
            . _('token: optional. Create one under Hosts, Agent Enrollment Tokens.')
            . ' '
            . _('Without one, the host waits under Hosts, Pending Agents for an admin to approve it.')
            . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
