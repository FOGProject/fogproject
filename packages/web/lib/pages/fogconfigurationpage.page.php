<?php
/**
 * The FOG Configuration Page display.
 *
 * PHP version 7.4+
 *
 * @category FOGConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The FOG Configuration Page display.
 *
 * @category FOGConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGConfigurationPage extends FOGPage
{
    /**
     * The node this page enacts for.
     *
     * @var string
     */
    public $node = 'about';
    /**
     * Initializes the about page.
     *
     * @param string $name the name to add.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('FOG Configuration');
        parent::__construct($this->name);
    }
    /**
     * Redirects to the version when initially entering
     * this page.
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->version();
    }
    /**
     * Builds the standard AdminLTE box scaffold shared by every sub-view.
     *
     * Replaces the hand-echoed box/card-header/card-body/card-footer skeleton
     * that each view used to repeat. Returns a string so callers can compose
     * it (e.g. wrap in a <form>).
     *
     * @param string $title The box title (already translated/escaped).
     * @param string $body  The card-body HTML.
     * @param array  $opts  color, collapse, help, footer, id, bodyId,
     *                      bodyClass, bodyAttrs.
     *
     * @return string
     */
    private function _box($title, $body, array $opts = [])
    {
        $color     = $opts['color']     ?? 'solid';
        $collapse  = $opts['collapse']  ?? false;
        $help      = $opts['help']      ?? '';
        $footer    = $opts['footer']    ?? '';
        $id        = $opts['id']        ?? '';
        $bodyId    = $opts['bodyId']    ?? '';
        $bodyClass = $opts['bodyClass'] ?? '';
        $bodyAttrs = $opts['bodyAttrs'] ?? '';

        $o = '';
        if ($id !== '') {
            $o .= '<div id="' . $id . '">';
        }
        $cardClass = ($color === 'solid' || $color === '')
            ? 'card'
            : 'card card-' . $color . ' card-outline';
        $o .= '<div class="' . $cardClass . '">';
        $o .= '<div class="card-header">';
        if ($collapse) {
            $o .= '<div class="card-tools float-end">'
                . self::$FOGCollapseBox
                . '</div>';
        }
        $o .= '<h4 class="card-title">' . $title . '</h4>';
        if ($help !== '') {
            $o .= '<p class="form-text">' . $help . '</p>';
        }
        $o .= '</div>';
        $o .= '<div class="card-body'
            . ($bodyClass !== '' ? ' ' . $bodyClass : '')
            . '"'
            . ($bodyId !== '' ? ' id="' . $bodyId . '"' : '')
            . ($bodyAttrs !== '' ? ' ' . $bodyAttrs : '')
            . '>';
        $o .= $body;
        $o .= '</div>';
        if ($footer !== '') {
            $o .= '<div class="card-footer">' . $footer . '</div>';
        }
        $o .= '</div>';
        if ($id !== '') {
            $o .= '</div>';
        }
        return $o;
    }
    /**
     * Emits a JSON response and exits. Centralizes the content-type header +
     * status code + json_encode + exit pattern repeated by the *Post methods.
     *
     * @param int   $code    HTTP status code.
     * @param array $payload Data to JSON-encode.
     *
     * @return never
     */
    private function _jsonExit($code, array $payload)
    {
        header('Content-type: application/json');
        $this->jsonSend($code, json_encode($payload));
    }
    /**
     * Prints the version information for the page.
     *
     * @return void
     */
    public function version()
    {
        $this->title = _('FOG Version Information');

        // Get our storage node urls.
        $StorageNodes = Route::getList('storagenode');
        ob_start();
        foreach ($StorageNodes as &$StorageNode) {
            // getItem(), not indiv(): a node deleted between the list and the
            // fetch answers null rather than ending the page mid-render.
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode) {
                continue;
            }
            $id = str_replace(' ', '_', $StorageNode->name);
            $url = filter_var(
                sprintf(
                    '%s://%s/%s/status/kernelvers.php',
                    self::$httpproto,
                    $StorageNode->ip,
                    self::webrootPath($StorageNode->webroot ?? null)
                ),
                FILTER_SANITIZE_URL
            );
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo '<a data-bs-toggle="collapse" data-bs-parent="#nodekernvers" href="#'
                . $id
                . '">';
            echo $StorageNode->name;
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $id
                . '" class="collapse">';
            echo '<div class="card-body">';
            if (!$StorageNode->online) {
                echo '<div class="alert alert-warning">';
                echo _('Storage Node is currently unavailable');
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                continue;
            }
            echo '<div class="kernvers" urlcall="'
                . $url
                . '">';
            echo '</dl>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($StorageNode);
        }
        $renderNodes = ob_get_clean();

        // Main Grouping
        echo '<div id="fogversion">';

        // FOG Version Information. Body is filled in by fog.about.home.js via
        // the .placehere hook (it reads the vers attribute).
        echo $this->_box(
            $this->title,
            '',
            [
                'bodyClass' => 'placehere',
                'bodyAttrs' => 'vers="' . FOG_VERSION . '"'
            ]
        );

        // Per-node kernel versions. The grouping div id is the accordion parent
        // (#nodekernvers) referenced by each node panel in $renderNodes.
        echo $this->_box(
            _('Versions'),
            $renderNodes,
            ['id' => 'nodekernvers']
        );

        // End Main Grouping
        echo '</div>';
    }
    /**
     * Display the fog license information
     *
     * @return void
     */
    public function license()
    {
        $this->title = _('GNU General Public License');

        $lang = '';
        switch (self::$locale) {
            case 'de':
                $lang = 'de_DE';
                break;
            case 'en':
                $lang = 'en_US';
                break;
            case 'es':
                $lang = 'es_ES';
                break;
            case 'fr':
                $lang = 'fr_FR';
                break;
            case 'it':
                $lang = 'it_IT';
                break;
            case 'pt':
                $lang = 'pt_BR';
                break;
            case 'zh':
                $lang = 'zh_CN';
                break;
            default:
                $lang = 'en_US';
        }
        $file = BASEPATH . 'management/languages/'
            . $lang
            . '.UTF-8/gpl-3.0.txt';
        $contents = nl2br(
            file_get_contents($file)
        );
        echo $this->_box(
            $this->title,
            $contents,
            ['id' => 'license']
        );
    }
    /**
     * Show the kernel update page.
     *
     * @return void
     */
    public function kernel()
    {
        $this->_downloadView('kernel');
    }
    /**
     * Process the kernel download request.
     *
     * @return void
     */
    public function kernelPost()
    {
        $this->_downloadPost('kernel');
    }
    /**
     * Show the initrd update page.
     *
     * @return void
     */
    public function initrd()
    {
        $this->_downloadView('initrd');
    }
    /**
     * Process the initrd download request.
     *
     * @return void
     */
    public function initrdPost()
    {
        $this->_downloadPost('initrd');
    }
    /**
     * Where this server keeps its certificate material.
     *
     * Read from the storage node record rather than from .fogsettings, which
     * is root-only and deliberately unreadable here. This is the same lookup
     * FOGBase::certDecrypt() uses to find the communication key, so the two
     * can never disagree about where the PKI lives.
     *
     * @return string Path with no trailing separator, or '' if unknown.
     */
    private static function _sslPath()
    {
        $paths = Route::getIds('storagenode', [], 'sslpath');
        foreach ((array) $paths as $path) {
            if (!$path) {
                continue;
            }
            return rtrim(str_replace(['\\', '/'], [DS, DS], $path), DS);
        }
        return '';
    }
    /**
     * Show this server's certificate hierarchy and the state of its keys.
     *
     * The reason this page runs the private key check in PHP rather than
     * simply reporting what the installer did: PHP *is* the threat model. The
     * whole point of the key isolation is that a compromise of this web
     * application cannot read the CA private key, and the only test that
     * actually answers that question is one made from inside the application
     * with the web server's own credentials. An installer that claims to have
     * set 0400 and a web tier that can nonetheless open the file is precisely
     * the failure worth surfacing, and it is invisible from anywhere else.
     *
     * That failure is not hypothetical. $sslpath lives under $snapindir, and
     * configureSnapins() used to chown the whole tree to the web user at 775
     * -- after the certificates were created, so it silently undid them.
     *
     * @return void
     */
    public function certificates()
    {
        $sslpath = self::_sslPath();
        $capem = BASEPATH . 'management' . DS . 'other' . DS . 'ca.cert.pem';

        // What every fog-client pins, and now also the anchor the web
        // certificate chains to. Its fingerprint is the one value worth
        // showing: it is what an admin compares against a client's trust store
        // when working out why a client stopped authenticating.
        $body = '<p>' . _(
            'FOG uses certificates for three unrelated jobs: the web server, '
            . 'the encrypted fog-client check-in, and the signature on the FOS '
            . 'kernels. They are issued by separate CAs beneath one anchor, so '
            . 'replacing any one of them leaves the other two alone.'
        ) . '</p>';
        if (file_exists($capem)) {
            $der = openssl_x509_read(file_get_contents($capem));
            $subject = '';
            if ($der !== false) {
                $parsed = openssl_x509_parse($der);
                $subject = isset($parsed['subject']['CN'])
                    ? $parsed['subject']['CN']
                    : '';
            }
            $body .= '<p><strong>' . _('Trust anchor') . '</strong> &mdash; '
                . _('published as ca.cert.der and pinned by every fog-client')
                . '</p>';
            if ($subject) {
                $body .= '<pre>' . \Initiator::e($subject) . '</pre>';
            }
            $body .= '<p><strong>' . _('SHA-256') . '</strong></p>';
            $body .= '<pre>' . \Initiator::e(
                strtoupper(
                    implode(':', str_split(hash_file('sha256', $capem), 2))
                )
            ) . '</pre>';
        }

        // The check that matters. Every path here is one a compromised web
        // application would go looking for first.
        $keys = [];
        if ($sslpath) {
            $keys[_('CA private key')] = $sslpath . DS . 'CA' . DS . '.fogCA.key';
            $keys[_('Web CA private key')] = $sslpath . DS . 'CA' . DS . 'web'
                . DS . '.fogWebCA.key';
            $keys[_('Web server private key')] = $sslpath . DS . 'CA' . DS . 'web'
                . DS . '.webLeaf.key';
        }
        $keys[_('Secure Boot CA private key')] = FOG_BASE_DIR . DS . 'secureboot'
            . DS . 'ca' . DS . '.fogSBCA.key';
        $exposed = [];
        foreach ($keys as $label => $path) {
            if (file_exists($path) && is_readable($path)) {
                $exposed[$label] = $path;
            }
        }
        if (count($exposed) > 0) {
            $warn = '<p>' . _(
                'The web application can read the following private keys. It '
                . 'should not be able to read any of them, and anything able to '
                . 'run code in this web application can copy them.'
            ) . '</p><ul>';
            foreach ($exposed as $label => $path) {
                $warn .= '<li><strong>' . \Initiator::e($label) . '</strong> &mdash; <code>'
                    . \Initiator::e($path) . '</code></li>';
            }
            $warn .= '</ul><p>' . _(
                'Re-run the installer, which restricts them to root. If this '
                . 'persists, check that nothing else is widening permissions on '
                . 'the snapins directory afterwards.'
            ) . '</p>';
            echo $this->_box(_('Private keys are readable by the web server'), $warn, ['color' => 'danger']);
        }

        // Pseudo-offline is the shipped default and a deliberate starting
        // point, not the recommended end state. Saying so here is the only
        // place an admin who never reads the install output will see it.
        $rootkey = $sslpath ? $sslpath . DS . 'CA' . DS . '.fogCA.key' : '';
        if ($rootkey && file_exists($rootkey)) {
            $body .= '<p><strong>' . _('The CA private key is on this server')
                . '</strong></p>';
            $body .= '<p>' . _(
                'It is restricted to root, which protects it from a compromise '
                . 'of this web application but not from a compromise of the '
                . 'machine. Moving it to a vault is a separate step:'
            ) . '</p>';
            $body .= '<pre>' . \Initiator::e(FOG_BASE_DIR . '/bin/fog-offline-ca-key /mnt/vault')
                . '</pre>';
            $body .= '<p>' . _(
                'Nothing needs it day to day. Restore it only to issue a new '
                . 'intermediate, or a certificate for a new storage node.'
            ) . '</p>';
        } elseif ($rootkey) {
            $body .= '<p><strong>' . _('The CA private key is not on this server')
                . '</strong></p>';
            $body .= '<p>' . _(
                'That is the recommended state. Restore it only to issue a new '
                . 'intermediate or a certificate for a new storage node, then '
                . 'move it back.'
            ) . '</p>';
        }
        echo $this->_box(_('Certificates'), $body, ['color' => 'info']);
    }
    /**
     * Show the Secure Boot enrolment page.
     *
     * Displays the certificate fingerprint and links to the enrolment kit, so
     * a technician has something to check the key against before trusting it
     * on a machine. Nothing here is secret: the kit contains the public
     * certificate only, which is the thing you are meant to distribute.
     *
     * @return void
     */
    public function secureBoot()
    {
        $kitdir = BASEPATH . 'service/secureboot';
        $certfile = $kitdir . DS . 'MOK.der';
        $kiturl = rtrim(self::getSetting('FOG_WEB_ROOT'), '/')
            . '/service/secureboot';
        if (!file_exists($certfile)) {
            // The installer generates a signing key by default, so reaching
            // here means it was declined with --no-secure-boot or generation
            // failed -- not that the admin simply never passed the flags.
            // Pointing them at the installer log is what actually helps.
            echo $this->_box(
                _('Secure Boot'),
                '<p>' . sprintf(
                    '%s. %s <code>--no-secure-boot</code>, %s.',
                    _('Secure Boot kernel signing is not configured on this server'),
                    _('Unless it was declined with'),
                    _(
                        're-run the installer and check the installation log '
                        . 'for a key generation or signing failure'
                    )
                ) . '</p>',
                ['color' => 'info']
            );
            return;
        }
        // The SHA-256 of the DER bytes IS the certificate fingerprint, so no
        // openssl round trip is needed to show the value a technician will
        // compare against.
        $fingerprint = strtoupper(
            implode(':', str_split(hash_file('sha256', $certfile), 2))
        );
        // MokManager's own "View key" screen -- the only thing shown when
        // enrolling from the PXE menu, which never runs fog-enroll-mok.sh --
        // prints a SHA-1 fingerprint, not SHA-256. Show both so either
        // enrolment route has a value on this page to check against.
        $fingerprintSha1 = strtoupper(
            implode(':', str_split(hash_file('sha1', $certfile), 2))
        );
        $body = '<p>' . sprintf(
            '%s. %s.',
            _('FOS kernels on this server are signed for UEFI Secure Boot'),
            _(
                'Each client needs this certificate enrolled once, by someone '
                . 'physically at the machine'
            )
        ) . '</p>';
        $body .= '<p><strong>' . _('Certificate SHA-256') . '</strong></p>';
        $body .= '<pre>' . \Initiator::e($fingerprint) . '</pre>';
        $body .= '<p>' . _(
            'Check this value against what the enrolment tool shows before '
            . 'confirming, whether the certificate reached the client on a '
            . 'USB stick or over the network. That comparison is what stops '
            . 'the wrong key being trusted.'
        ) . '</p>';
        $body .= '<p><strong>' . _('Certificate SHA-1') . '</strong></p>';
        $body .= '<pre>' . \Initiator::e($fingerprintSha1) . '</pre>';
        $body .= '<p>' . _(
            'This is what MokManager\'s own View key screen shows after '
            . 'enrolling from the PXE menu -- that route never runs the '
            . 'script above, so check it against this value instead.'
        ) . '</p>';
        $body .= '<p><strong>' . _('Enrolment kit') . '</strong></p>';
        $body .= '<p>' . _(
            'For a live-USB enrolment, copy all three files onto a USB '
            . 'stick, boot the client from a stock Ubuntu or Debian live '
            . 'image with Secure Boot left ON, and run the launcher. No '
            . 'firmware changes are needed.'
        ) . '</p>';
        $body .= '<ul>';
        $kitfiles = ['MOK.der', 'fog-enroll-mok.sh', 'fog-enroll-mok.desktop'];
        foreach ($kitfiles as $file) {
            if (!file_exists($kitdir . DS . $file)) {
                continue;
            }
            $body .= sprintf(
                '<li><a href="%1$s/%2$s">%2$s</a></li>',
                \Initiator::e($kiturl),
                \Initiator::e($file)
            );
        }
        $body .= '</ul>';
        $body .= '<p>' . sprintf(
            '%s <a href="%s" target="_blank">%s</a>.',
            _(
                'Full step-by-step instructions for this and for enrolling '
                . 'straight from the PXE boot menu, with no USB stick, are '
                . 'in the Secure Boot guide:'
            ),
            \Initiator::e('https://docs.fogproject.org/en/latest/secure-boot-signing'),
            _('Secure Boot: signing FOS with your own key')
        ) . '</p>';
        echo $this->_box(_('Secure Boot'), $body, ['color' => 'info']);

        // Automatic enrolment card, placed above the manual steps because it is
        // the path that scales: it finishes on the client with nobody at the
        // keyboard. Its one precondition -- Setup Mode -- is a firmware setting
        // an admin has to know to look for, and stating it here is the only
        // place they will see it before scheduling the task against a fleet.
        $authvars = ['PK.auth', 'KEK.auth', 'db.auth'];
        $haveAuth = true;
        foreach ($authvars as $file) {
            if (!file_exists($kitdir . DS . $file)) {
                $haveAuth = false;
                break;
            }
        }
        if (!$haveAuth) {
            // Reports the CONDITION, not a cause. This used to assert that
            // efitools was missing, which is only one of three ways the blobs
            // can be absent -- and the least likely on a server that has the
            // package installed. A server with efitools present and PK/KEK on
            // disk still got told to install efitools (GH-1266). The page
            // cannot see which cause applied; the installer can, and now says
            // so on every run, so this points there instead of guessing.
            $auto = '<p>' . _(
                'This server has not published the automatic Secure Boot '
                . 'enrolment blobs (PK.auth, KEK.auth and db.auth), so '
                . 'automatic enrolment is unavailable. There are three '
                . 'reasons that happens:'
            ) . '</p>';
            $auto .= '<ul>';
            $auto .= '<li>' . _(
                'Secure Boot enrolment material is switched off for this '
                . 'install, or no signing key is configured -- so no platform '
                . 'keys were minted.'
            ) . '</li>';
            // One msgid with a placeholder rather than sentence fragments
            // glued together: a translator needs the whole sentence to word it.
            $auto .= '<li>' . sprintf(
                _(
                    'The %s package is not installed and could not be built '
                    . 'from source.'
                ),
                '<code>efitools</code>'
            ) . '</li>';
            $auto .= '<li>' . _(
                'Building the blobs failed.'
            ) . '</li>';
            $auto .= '</ul>';
            $auto .= '<p>' . _(
                'Re-run the installer and read what it prints under '
                . '"Publishing Secure Boot variable updates" -- it names '
                . 'which of the three applied here.'
            ) . '</p>';
            $auto .= '<p>' . _(
                'The manual enrolment steps below are unaffected.'
            ) . '</p>';
            echo $this->_box(
                _('Automatic enrolment (Setup/Custom Mode)'),
                $auto,
                ['color' => 'warning']
            );
        } else {
            $auto = '<p>' . sprintf(
                '%s. %s.',
                _(
                    'Clients in Setup Mode can be enrolled by scheduling the '
                    . '"Enroll Secure Boot" task -- no USB stick, no live image, '
                    . 'no MOK Manager screen and no password'
                ),
                _('The task finishes on the client with nobody at the keyboard')
            ) . '</p>';
            $auto .= '<p><strong>'
                . _('What Setup Mode means (firmware usually calls it "Custom")')
                . '</strong></p>';
            // Named both ways deliberately. "Setup Mode" is the UEFI spec's term
            // and the one the SetupMode variable uses, but Dell, HP, Lenovo and
            // AMI menus almost all label it "Custom" -- so an admin searching
            // their own firmware for "Setup Mode" does not find it.
            $auto .= '<p>' . _(
                'Setup Mode -- shown as "Custom" or "Custom mode" in most '
                . 'firmware menus -- is the state a machine is in when its '
                . 'platform key has been cleared, and it is the only state in '
                . 'which these databases can be written. Turning Secure Boot OFF '
                . 'is not the same thing and does not help: a machine with Secure '
                . 'Boot disabled still has a platform key, and still refuses the '
                . 'write. Look for "Custom mode", "Erase all Secure Boot '
                . 'settings" or "Clear Secure Boot keys" in the firmware.'
            ) . '</p>';
            $auto .= '<p>' . _(
                'That is one visit to the firmware screen per machine, once, '
                . 'and it can be the same visit that turns Secure Boot on '
                . 'afterwards.'
            ) . '</p>';
            $auto .= '<p><strong>' . _('What gets enrolled') . '</strong></p>';
            $auto .= '<p>' . _(
                'This server becomes the platform owner, so Microsoft\'s '
                . 'published CA certificates are enrolled alongside FOG\'s own. '
                . 'That is not optional: Microsoft signs the shim in FOG\'s own '
                . 'Secure Boot PXE chain, so a database without it would stop '
                . 'this server from booting the very clients it enrolled, as '
                . 'well as breaking Windows.'
            ) . '</p>';
            $auto .= '<p>' . _(
                'Clients already enforcing Secure Boot cannot run this task -- '
                . 'they will not boot FOS in the first place. Use the manual '
                . 'steps below for those.'
            ) . '</p>';
            echo $this->_box(
                _('Automatic enrolment (Setup/Custom Mode)'),
                $auto,
                ['color' => 'success']
            );
        }

        // Second card: the actual procedure. The card above answers "is this
        // configured and what is the key", which is the reference half. Full
        // per-client steps for both enrolment routes live in the linked guide
        // now rather than being duplicated here -- this card is the summary
        // and the gotchas that matter regardless of which route is used.
        $steps = '<p>' . _(
            'Signing is already done on this server. The remaining work is '
            . 'per-client and has to be done by someone at the machine -- that '
            . 'is what makes enrolment a deliberate act rather than something '
            . 'a server can do to a client remotely.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Two routes are covered in the guide linked above: a live USB '
            . 'with the kit above, or PXE-booting the client and choosing '
            . 'Enroll Secure Boot Key, which now fetches MOK.der over the '
            . 'network on its own, with no USB stick needed.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Secure Boot does not need to be enabled on a client to enrol '
            . 'its key -- either route works the same way with it off, '
            . 'which lets you stage enrolment fleet-wide before ever '
            . 'turning it on.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'The Enroll Secure Boot task type does all of this for you: '
            . 'schedule it against a host or a group from Task Scheduling '
            . 'and the client boots FOS, which stages the request itself -- '
            . 'or enrols outright with nothing to confirm, if the machine is '
            . 'in Setup Mode. The Enroll Secure Boot Key menu item stays for '
            . 'answering a pending request by hand, or for enrolling from '
            . 'local media on a machine FOS cannot boot.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Whichever route is used, MokManager -- the blue enrolment '
            . 'screen -- has its own timeouts FOG cannot change: it gives '
            . 'up and boots normally if nothing is pressed within about 10 '
            . 'seconds of appearing, and reboots if left idle partway '
            . 'through for a few minutes. Be at the console before '
            . 'starting.'
        ) . '</p>';
        $steps .= '<p><strong>' . _('To PXE boot with Secure Boot on')
            . '</strong></p>';
        $steps .= '<p>' . sprintf(
            '%s <code>secureboot/snponly-shimx64.efi</code>. %s.',
            _('Point your DHCP boot filename at'),
            _(
                'That is the signed chain staged by the installer; the default '
                . 'boot file is unsigned and a Secure Boot client will refuse it'
            )
        ) . '</p>';
        // Two signed chains are staged, mirroring the snponly/ipxe choice
        // every non-Secure-Boot install already has. shim resolves its second
        // stage from its OWN filename, so switching chains is purely a DHCP
        // change -- there is nothing to rename server-side. Documented here
        // because a site whose firmware SNP is broken otherwise has no way to
        // know a fallback exists.
        $steps .= '<p>' . sprintf(
            '%s <code>secureboot/ipxe-shimx64.efi</code> %s.',
            _(
                'If that chain loads but the network never comes up, the '
                . 'firmware\'s own UEFI network stack is at fault. Point the '
                . 'boot filename at'
            ),
            _(
                'instead, which uses iPXE\'s built-in NIC drivers rather than '
                . 'the firmware\'s. Arm64 clients use the files under '
                . 'secureboot/arm64-efi/'
            )
        ) . '</p>';
        // Stated rather than detected: the web request's own scheme says
        // nothing about the install's $httpproto, so guessing here would be
        // worse than telling the admin what to check. See
        // downloadipxesecureboot() -- an HTTPS install skips the staging
        // entirely, because a signed binary cannot be rebuilt to carry this
        // server's CA without invalidating the signature.
        $steps .= '<p>' . _(
            'Secure Boot PXE and HTTPS are mutually exclusive: on an HTTPS '
            . 'install the installer skips these binaries, because rebuilding '
            . 'them to trust this server\'s CA would invalidate the signature '
            . 'that makes them bootable.'
        ) . '</p>';
        echo $this->_box(_('What to do next'), $steps);
    }
    /**
     * Render the kernel/initrd download view.
     *
     * kernel() and initrd() were byte-for-byte identical except for the words
     * "kernel"/"initrd" and the name-input id. Both JS files target the same
     * element ids (download-send, downloadModal, confirmDownload, dataTable),
     * differing only on {type}-name, so the markup is fully shared here.
     *
     * @param string $type 'kernel' or 'initrd'.
     *
     * @return void
     */
    private function _downloadView($type)
    {
        $isKernel = ($type === 'kernel');
        $this->title = $isKernel
            ? _('Kernel Update')
            : _('initrd (Initial Ramdisk) Update');

        $this->headerData = [
            _('Tag Name'),
            _('Version'),
            _('Architecture'),
            _('Type'),
            _('Date')
        ];
        $this->attributes = [[], [], [], [], []];

        $buttons = self::makeButton(
            'download-send',
            _('Download'),
            'btn btn-primary float-end'
        );
        $confirmDownloadBtn = self::makeButton(
            'confirmDownload',
            _('Download'),
            'btn btn-primary float-end'
        );
        $cancelDownloadBtn = self::makeButton(
            'cancelDownload',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );

        if ($isKernel) {
            $confirmNew = _('Confirm you would like to download a new kernel');
            $nameForNew =
                _('Use the input below to set the name for your new kernel.');
            $help = sprintf(
                '%s %s %s. %s, %s, %s %s. %s, %s %s, %s.',
                _('This section allows you to update'),
                _('the Linux kernel which is used to'),
                _('boot the client computers'),
                _('In FOG'),
                _('this kernel holds all the drivers for the client computer'),
                _('so if you are unable to boot a client you may wish to'),
                _('update to a newer kernel which may have more drivers built in'),
                _('This installation process may take a few minutes'),
                _('as FOG will attempt to go out to the internet'),
                _('to get the requested Kernel'),
                _('so if it seems like the process is hanging please be patient')
            );
        } else {
            $confirmNew = _('Confirm you would like to download a new initrd');
            $nameForNew =
                _('Use the input below to set the name for your new initrd.');
            $help = sprintf(
                '%s %s %s. %s, %s %s, %s.',
                _('This section allows you to update'),
                _('the initrd (initial ramdisk) which is alongside the'),
                _('kernel to boot the client computers'),
                _('This installation process may take a few minutes'),
                _('as FOG will attempt to go out to the internet'),
                _('to get the requested initrd'),
                _('so if it seems like the process is hanging please be patient')
            );
        }

        $downloadModal = self::makeModal(
            'downloadModal',
            _('Confirm Download'),
            '<p class="form-text">'
            . $confirmNew
            . ' '
            . _('to your fog storage node.')
            . ' '
            . $nameForNew
            . '</p>'
            . '<div class="' . $type . '-input">'
            . self::makeInput(
                'form-control',
                $type . '-name',
                '',
                'text',
                $type . '-name',
                '',
                true
            )
            . '</div>',
            $confirmDownloadBtn . $cancelDownloadBtn,
            '',
            'info'
        );

        echo $this->_box(
            $this->title,
            $this->process(
                12,
                'dataTable',
                $buttons,
                'display table table-bordered table-striped'
            ),
            [
                'id' => $type . '-update',
                'help' => $help,
                'footer' => $downloadModal
            ]
        );
    }
    /**
     * Process a kernel/initrd download request.
     *
     * kernelPost()/initrdPost() were identical except for the session-key
     * names; those are reconstructed from $type to match the readers in
     * fogpage.class.php (allow_ajax_kdl/idl, {dest,tmp,dl}-{type}-file).
     *
     * @param string $type 'kernel' or 'initrd'.
     *
     * @return void
     */
    private function _downloadPost($type)
    {
        self::checkAuthAndCSRF();
        $dstName = filter_input(INPUT_POST, 'dstName');
        $file = trim(base64_decode(filter_input(INPUT_POST, 'file')));
        // With Secure Boot signing configured, a KERNEL download has to land in
        // the staging directory the root signing helper works on -- the helper
        // takes no arguments, so the path is how it is told what to sign.
        //
        // Kernels only. dev-branch had separate kernelPost()/initrdPost() so
        // this distinction was implicit there; here the two share one method,
        // and routing an initrd into the staging directory would hand the
        // signer a file that must never be signed. Nothing verifies the
        // initramfs under Secure Boot -- on any distribution -- so there is
        // nothing to gain and a wrong-file signature to lose.
        $stagedir = ($type === 'kernel') ? self::secureBootStagingDir() : '';
        // Unique per download. This path is carried in the session across the
        // three requests the update takes (post -> fetch dl -> fetch tftp), so
        // a name shared by every run let two concurrent updates -- two admins,
        // or two tabs -- write the same file: the second download would land
        // under the first one's destination name, and with signing on, whatever
        // happened to be there at sign time is what got signed and shipped. A
        // lock cannot cover a window that spans three requests, so the file
        // itself has to be private to each one; secureBootSign() borrows the
        // helper's fixed name for the instant it is actually signing.
        //
        // Unpredictable, not merely unique: the old system-temp name was
        // basename($dstName), so a local user could pre-plant a symlink at
        // /tmp/bzImage and redirect the web server's write.
        $unique = bin2hex(random_bytes(8));
        if ($stagedir) {
            // 'kernel-' prefix, and the helper's shared name is a bare
            // 'kernel' -- the sweep below must never match a file mid-signing.
            $tmpFile = $stagedir . DS . 'kernel-' . $unique;
            self::purgeStaleDownloads($stagedir, 'kernel-');
        } else {
            $tmpFile = sys_get_temp_dir() . DS . 'fog-' . $type . '-' . $unique;
            self::purgeStaleDownloads(sys_get_temp_dir(), 'fog-' . $type . '-');
        }
        $abbr = ($type === 'kernel') ? 'kdl' : 'idl';
        $_SESSION['allow_ajax_' . $abbr] = true;
        $_SESSION['dest-' . $type . '-file'] = basename(trim($dstName));
        $_SESSION['tmp-' . $type . '-file'] = $tmpFile;
        $_SESSION['dl-' . $type . '-file'] = $file;
        try {
            if (empty($dstName)) {
                throw new \Exception(_('A filename is required!'));
            }
            if (empty($file)) {
                throw new \Exception(
                    _('No external data to download the file from')
                );
            }
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => _('Starting download'),
                    'title' => _('Download Starting')
                ]
            );
        } catch (\Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Start Download Fail')
                ]
            );
        }
    }
    /**
     * Display the ipxe menu configurations.
     *
     * @return void
     */
    public function pxemenu()
    {
        $this->title = _('iPXE Menu Configuration');

        $this->headerData = [
            _('Setting'),
            _('Value')
        ];

        $this->attributes = [
            [],
            []
        ];

        echo $this->_box(
            $this->title,
            $this->process(
                12,
                'ipxe-table',
                '',
                'display table table-bordered table-striped'
            ),
            [
                'help' => _('For ipxe command related items (e.g. colour, cpair, etc...) click ')
                . '<a href="http://ipxe.org/cmd" target="_blank">'
                . _('here')
                . '</a>'
            ]
        );
    }
    /**
     * Ipxe Menu List getter.
     *
     * @return void
     */
    public function getIpxeList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $ServicesToSee = [
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_BOOT_EXIT_TYPE',
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_KEY_SEQUENCE',
            'FOG_NO_MENU',
            'FOG_PXE_ADVANCED',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT'
        ];
        $needstobecheckbox = [
            $ServicesToSee[0] => true,
            $ServicesToSee[11] => true,
            $ServicesToSee[14] => true
        ];
        $needstobenumeric = [
            $ServicesToSee[13] => true,
            $ServicesToSee[15] => true
        ];
        $where = "`settingKey` IN ('"
            . implode("','", $ServicesToSee)
            . "')";
        $settingMan = self::getClass('SettingManager');
        $table = $settingMan->getTable();
        $dbcolumns = $settingMan->getColumns();
        $sqlStr = $settingMan->getQueryStr();
        $filterStr = $settingMan->getFilterStr();
        $totalStr = $settingMan->getTotalStr()
            . ($where ? ' WHERE ' . $where : '');
        $columns = [];
        foreach ($dbcolumns as $common => &$real) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            // Only the value field carries the rendered input column; binding
            // it to settingValue lets the global search match values too.
            if ($common !== 'value') {
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => 'inputValue',
                'formatter' => function ($d, $row) use (
                    $needstobenumeric,
                    $needstobecheckbox
                ) {
                    switch ($row['settingKey']) {
                        case 'FOG_KEY_SEQUENCE':
                            $input = self::getClass('KeySequenceManager')
                                ->buildSelectBox(
                                    $row['settingValue'],
                                    $row['settingID']
                                );
                            break;
                        case 'FOG_BOOT_EXIT_TYPE':
                        case 'FOG_EFI_BOOT_EXIT_TYPE':
                            $input = Setting::buildExitSelector(
                                $row['settingID'],
                                $row['settingValue'],
                                false,
                                $row['settingKey']
                            );
                            break;
                            /**
                             * The default kernels are filenames in the FOS boot
                             * directory, so offer what is actually there --
                             * including the per-release siblings the installer
                             * leaves behind on every update, which is what makes
                             * "put the default back on the previous kernel" a
                             * selection rather than a typed guess.
                             */
                        case 'FOG_TFTP_PXE_KERNEL':
                        case 'FOG_TFTP_PXE_KERNEL_32':
                        case 'FOG_TFTP_PXE_KERNEL_ARM':
                        case 'FOG_MEMTEST_KERNEL':
                            $input = self::kernelFileSelect(
                                $row['settingID'],
                                $row['settingValue'],
                                'kernel',
                                'form-control',
                                $row['settingKey']
                            );
                            break;
                        case (isset($needstobecheckbox[$row['settingKey']])):
                            $input = self::makeInput(
                                '',
                                $row['settingID'],
                                '',
                                'checkbox',
                                $row['settingKey'],
                                '',
                                false,
                                false,
                                -1,
                                -1,
                                ($row['settingValue'] > 0 ? 'checked' : '')
                            );
                            break;
                        case (isset($needstobenumeric[$row['settingKey']])):
                            $input = self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                'number',
                                $row['settingKey'],
                                $row['settingValue']
                            );
                            break;
                        default:
                            $input = self::makeTextarea(
                                'form-control',
                                $row['settingID'],
                                '',
                                $row['settingKey'],
                                $row['settingValue']
                            );
                    }
                    return $input;
                }
            ];
            unset($real);
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                'settingID',
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr,
                $where
            )
        ));
    }
    /**
     * Stores the changes made.
     *
     * @return void
     */
    public function pxemenuPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('PXEMENU_POST');
        $ServicesToSee = [
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_BOOT_EXIT_TYPE',
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_KEY_SEQUENCE',
            'FOG_NO_MENU',
            'FOG_PXE_ADVANCED',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT'
        ];
        $checkbox = [
            'FOG_ADVANCED_MENU_LOGIN' => true,
            'FOG_NO_MENU' => true,
            'FOG_PXE_MENU_HIDDEN' => true
        ];
        $needstobenumeric = [
            $ServicesToSee[13] => true,
            $ServicesToSee[15] => true
        ];

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            $items = [];
            foreach ($vars as $key => &$val) {
                $id = self::_settingIdFor($key);
                $Service = Route::getItem('setting', $id);
                $set = trim($val);
                if (!$Service) {
                    continue;
                }
                $name = trim($Service->name);
                $val = trim($Service->value);
                if ($val == $set) {
                    continue;
                }
                if (isset($checkbox[$name])) {
                    $set = intval($set) < 1 ? 0 : 1;
                } elseif (isset($needstobenumeric[$name])) {
                    if (isset($needstobenumeric[$name]) && !is_numeric($set)) {
                        throw new \Exception(
                            $name . ' ' . _('value must be numeric')
                        );
                    }
                }
                unset($val);
                $items[] = [
                    $id,
                    $name,
                    $set,
                    trim((string) $Service->description),
                    trim((string) $Service->category)
                ];
                unset($Service);
                unset($val);
            }
            if (count($items) > 0) {
                $SettingMan = new SettingManager();
                /*
                 * settingDesc and settingCategory are named even though this
                 * saver never changes them, and the values are the ones just
                 * read back. globalSettings declares both longtext NOT NULL
                 * with no DEFAULT -- a longtext could not carry one on the
                 * MySQL versions FOG supports -- so an INSERT that leaves
                 * them out is error 1364 on a strict server, which is what
                 * saving any setting became once GH-1245 stopped PDODB
                 * clearing sql_mode.
                 *
                 * insertBatch() now backfills a column like this on its own,
                 * so this is belt and braces rather than the fix. It is worth
                 * having anyway: the backfill can only supply '', and if a
                 * setting row is ever genuinely absent this writes the real
                 * description and category instead of blanking them.
                 */
                $insert_fields = [
                    'id',
                    'name',
                    'value',
                    'description',
                    'category'
                ];
                if (!$SettingMan->insertBatch($insert_fields, $items)) {
                    $serverFault = true;
                    throw new \Exception(_('Settings update failed!'));
                }
                // Writes globalSettings directly rather than through
                // setSetting(), so nothing invalidated the shared settings
                // cache. See the matching flush in settingsPost().
                FOGBase::clearSettingsCache();
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('iPXE config successfully stored!'),
                    'title' => _('iPXE Config Update Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Config Update Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * The estate-wide API token inventory.
     *
     * The per-user API tab answers "what does this user have". This answers
     * the question that actually drives revocation: "what credentials exist
     * on this server, and which has nothing touched in months". Without it
     * that is only answerable by opening every user in turn, which is why
     * atLastUsed was worth recording in the first place.
     *
     * Sits under FOG Configuration because the server-wide fog-api-token
     * already lives there, so it is where an administrator already goes for
     * API credentials.
     *
     * WHAT IT CANNOT DO, because people will ask: show a token. The table
     * holds hashes. This is inventory and revocation, never recovery.
     *
     * @return void
     */
    /**
     * The estate-wide API token inventory.
     *
     * The per-user API tab answers "what does this user have". This answers
     * the question that actually drives revocation: "what credentials exist
     * on this server, and which has nothing touched in months". Without it
     * that is only answerable by opening every user in turn, which is why
     * atLastUsed was worth recording in the first place.
     *
     * Sits under FOG Configuration because the server-wide fog-api-token
     * already lives there, so it is where an administrator already goes for
     * API credentials.
     *
     * A REAL GRID, not a hand-built table. The first cut of this rendered
     * its own <table> with no sorting, no search and no paging, which is
     * not what any other list in FOG looks like and stops being usable at
     * about thirty rows. It now goes through process() like every other
     * list page, so selection, the delete/enable bulk actions and the
     * Refresh button are the shared ones rather than a second
     * implementation of them.
     *
     * It is fed by ajax (apitokenlistPost) rather than rendered inline,
     * because registerTable()'s standard Refresh button calls
     * dt.ajax.reload() -- a DOM-sourced table would render fine and then
     * throw on the button every other list page has.
     *
     * CLIENT-SIDE processing, unlike the top-level lists. Those are
     * serverSide because a host list is tens of thousands of rows; the
     * number of API tokens on a server is bounded by how many people have
     * clicked Issue, so paginating it on the server would be a second copy
     * of DataTables' search and order logic bought for nothing.
     *
     * WHAT IT CANNOT DO, because people will ask: show a token. The table
     * holds hashes. This is inventory and revocation, never recovery.
     *
     * @return void
     */
    public function apitokens()
    {
        $this->title = _('API Tokens');

        if (!Authorization::can('apitoken.view')) {
            echo $this->_box(
                _('API Tokens'),
                '<p>' . _('You do not have permission to view API tokens.')
                . '</p>',
                ['color' => 'warning']
            );
            return;
        }

        $mayEdit = Authorization::can('apitoken.edit');
        $mayDelete = Authorization::can('apitoken.delete');
        $mayCreate = Authorization::can('apitoken.create');

        $this->headerData = [
            _('User'),
            _('Name'),
            _('Created'),
            _('Created By'),
            _('Last Used'),
            _('Enabled')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            ['width' => 22]
        ];

        // Destructive left, commit right, one primary and it is the
        // rightmost -- the house rule. Enable/Disable are a genuinely
        // different operation from Issue rather than a lesser version of
        // it, but they are still not the card's commit action, so they sit
        // to its left as secondaries.
        $buttons = '';
        if ($mayDelete) {
            $buttons .= self::makeButton(
                'deleteSelected',
                _('Delete selected'),
                'btn btn-danger float-start'
            );
        }
        $buttons .= '<div class="btn-group float-end">';
        if ($mayEdit) {
            $buttons .= self::makeButton(
                'apitokenDisable',
                _('Disable selected'),
                'btn btn-secondary'
            );
            $buttons .= self::makeButton(
                'apitokenEnable',
                _('Enable selected'),
                'btn btn-secondary'
            );
        }
        if ($mayCreate) {
            $buttons .= self::makeButton(
                'issuetoken',
                _('Issue Token'),
                'btn btn-primary'
            );
        }
        $buttons .= '</div>';

        $modals = '';
        if ($mayDelete) {
            // The re-auth prompt $.deleteSelected drives when
            // FOG_DELETE_REAUTH is on. process() builds this for a page
            // whose sub is 'list' and this pane's is not, so without it
            // $.reAuth calls modal('show') on an empty jQuery set:
            // nothing opens, nothing is deleted, nothing is logged, and
            // the button looks broken rather than gated.
            // Named for this grid rather than reusing 'deleteModal', so the
            // pane never depends on being the only deletable thing on its
            // page -- the assumption that broke the same card on the user
            // edit page, where the account's own delete modal already holds
            // that id.
            $modals .= self::makeModal(
                'apitokenDeleteModal',
                _('Confirm password'),
                '<div class="input-group">'
                . self::makeInput(
                    'form-control',
                    'apitokenDeletePW',
                    _('Password'),
                    'password',
                    'apitokenDeletePassword'
                )
                . '</div>',
                self::makeButton(
                    'closeAPITokenDeleteModal',
                    _('Cancel'),
                    'btn btn-outline-secondary float-start',
                    'data-bs-dismiss="modal"'
                )
                . self::makeButton(
                    'confirmAPITokenDelete',
                    _('Delete') . ' {0} ' . _('{node}'),
                    'btn btn-outline-secondary float-end'
                ),
                '',
                'danger'
            );
        }
        if ($mayCreate) {
            $issueBody = '<p>'
                . _('For service accounts and unattended integrations. The '
                    . 'token acts with that user\'s roles, and the audit log '
                    . 'records that you issued it for them.')
                . '</p>';
            $issueBody .= '<div class="row mb-3">';
            $issueBody .= self::makeLabel(
                'col-sm-3 col-form-label',
                'issuefor',
                _('User')
            );
            $issueBody .= '<div class="col-sm-9">'
                . self::getClass('UserManager')
                    ->buildSelectBox('', 'issuefor', 'name')
                . '</div>';
            $issueBody .= '</div>';
            $issueBody .= '<div class="row mb-3">';
            $issueBody .= self::makeLabel(
                'col-sm-3 col-form-label',
                'newtokenname',
                _('Name')
                . '<br/>('
                . _('what this token is for')
                . ')'
            );
            $issueBody .= '<div class="col-sm-9">'
                . self::makeInput(
                    'form-control',
                    'newtokenname',
                    _('e.g. nightly inventory script'),
                    'text',
                    'newtokenname',
                    '',
                    true,
                    false,
                    1,
                    255
                )
                . '</div>';
            $issueBody .= '</div>';

            $modals .= self::makeModal(
                'issueTokenModal',
                _('Issue a token on behalf of a user'),
                $issueBody,
                self::makeButton(
                    'closeIssueModal',
                    _('Cancel'),
                    'btn btn-outline-secondary float-start',
                    'data-bs-dismiss="modal"'
                )
                . self::makeButton(
                    'confirmIssueToken',
                    _('Issue Token'),
                    'btn btn-primary float-end'
                ),
                '',
                'primary'
            );

            // The plaintext lands here and nowhere else. Its own modal
            // rather than an alert on the page because it has to be
            // DISMISSED: closing it is the moment the grid reloads, and a
            // banner nobody closes leaves a credential on screen behind
            // whatever the administrator does next.
            $freshBody = '<p>'
                . _('This is the only time it will be shown. Hand it to the '
                    . 'person or service it was issued for &mdash; you are '
                    . 'seeing a credential that belongs to another account.')
                . '</p>'
                . '<input type="text" class="form-control" readonly '
                . 'onclick="this.select();" id="fresh-token-value"/>';
            $modals .= self::makeModal(
                'freshTokenModal',
                _('Copy this token now'),
                $freshBody,
                self::makeButton(
                    'closeFreshToken',
                    _('Done'),
                    'btn btn-primary float-end',
                    'data-bs-dismiss="modal"'
                ),
                '',
                'success'
            );
        }

        echo $this->_box(
            _('API Tokens'),
            '<p>'
            . _('Every API token on this server. Tokens are stored hashed '
                . '&mdash; a token cannot be shown again after it is issued, '
                . 'so this page can revoke one but never recover it.')
            . '</p>'
            . $this->process(
                12,
                'dataTable',
                $buttons,
                'display table table-bordered table-striped'
            ),
            ['footer' => $modals]
        );
    }
    /**
     * Refuses a GET on an endpoint that only acts on POST.
     *
     * These exist because of how FOGPageManager::render() dispatches, and
     * that mechanism is worth stating once rather than being rediscovered:
     *
     *     if (!method_exists($class, $method)) { $method = 'index'; }
     *     ...
     *     if (self::$post && method_exists($class, $method.'Post')) { ... }
     *
     * The BASE method is looked up FIRST. A sub implemented only as
     * fooPost() never gets as far as the second test -- $method has already
     * been rewritten to 'index' -- so the request renders the node's
     * default page instead. On this node that is version(), so the endpoint
     * answers HTTP 200 with the FOG Version Information card and jQuery
     * hands $.notifyFromAPI a string: no token, no error, a toast that says
     * nothing. Nothing is logged, because as far as FOG is concerned the
     * request succeeded.
     *
     * issueAPITokenFor, cacheFlush and cacheRefresh were all shipped that
     * way. So the base methods below exist to be FOUND, and refuse the verb
     * they were never meant to serve rather than silently doing the POST's
     * work on a GET.
     *
     * @return void
     */
    private function _postOnly()
    {
        header('Content-type: application/json');
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_METHOD_NOT_ALLOWED,
            [
                'error' => _('This endpoint only accepts POST.'),
                'title' => _('API Token Failed')
            ]
        );
    }
    /**
     * Dispatch anchor for sub=apitokenlist. See _postOnly().
     *
     * This one DOES the work rather than refusing, because it is a read:
     * the grid asks for it over POST only because DataTables is configured
     * that way, and answering the same question on a GET is correct.
     *
     * @return void
     */
    public function apitokenlist()
    {
        $this->apitokenlistPost();
    }
    /**
     * Dispatch anchor for sub=apitokendelete. See _postOnly().
     *
     * @return void
     */
    public function apitokendelete()
    {
        $this->_postOnly();
    }
    /**
     * Dispatch anchor for sub=apitokenenable. See _postOnly().
     *
     * @return void
     */
    public function apitokenenable()
    {
        $this->_postOnly();
    }
    /**
     * Dispatch anchor for sub=issueAPITokenFor. See _postOnly().
     *
     * Without this the pane's Issue Token button has never worked: the
     * request returned the version page, so the JS saw no data.token and
     * returned quietly, and the modal simply did nothing.
     *
     * @return void
     */
    public function issueAPITokenFor()
    {
        $this->_postOnly();
    }
    /**
     * The grid's rows, as DataTables JSON.
     *
     * Not Route::listem(). APIToken is deliberately absent from
     * Route::$validClasses -- a token-management REST surface would let one
     * API credential mint another -- so the REST layer cannot answer this
     * and should not be taught to. The manager's own scoped query answers
     * it instead, which is the same reason it holds direct SQL.
     *
     * @return void
     */
    public function apitokenlistPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        $uid = (int)self::$FOGUser->get('id');
        $rows = [];
        foreach (
            self::getClass('APITokenManager')->visibleTo($uid) as $token
        ) {
            $rows[] = [
                'id' => $token['id'],
                'userName' => $token['userName'],
                'name' => $token['name'],
                'createdTime' => $token['createdTime'],
                'createdBy' => $token['createdBy'],
                // "Never" is a different fact from "used at the epoch", and
                // the column exists precisely to tell them apart -- so an
                // empty value is spelled out rather than left as a blank
                // cell the reader has to interpret.
                'lastUsed' => '' === $token['lastUsed']
                    ? _('Never')
                    : $token['lastUsed'],
                'enabled' => $token['enabled'] ? 1 : 0
            ];
        }

        $this->jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode(
                [
                    'draw' => (int)filter_input(INPUT_POST, 'draw') ?: 0,
                    'recordsTotal' => count($rows),
                    'recordsFiltered' => count($rows),
                    'data' => $rows
                ]
            )
        );
    }
    /**
     * Revokes every selected token.
     *
     * Shaped as remitems[] so the shared $.deleteSelected can drive it --
     * the same helper, the same re-auth prompt and the same table redraw
     * every other list page gets, rather than a second delete path that
     * would have to grow those separately.
     *
     * Every id is resolved through visibleToken(), never loaded directly:
     * the ids arrive from a form, and a scoped administrator must not be
     * able to revoke a credential belonging to a user they cannot see just
     * by posting its number.
     *
     * @return void
     */
    public function apitokendeletePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.delete')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to revoke API '
                        . 'tokens.'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        // null owner: spanning users is this pane's whole job. The
        // per-user tab passes its own id here instead.
        $deleted = self::getClass('APITokenManager')->revokeMany(
            array_map('intval', (array)($_POST['remitems'] ?? [])),
            (int)self::$FOGUser->get('id')
        );

        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => sprintf(
                    _('%d token(s) revoked.'),
                    $deleted
                ),
                'title' => _('API Tokens Revoked')
            ]
        );
    }
    /**
     * Enables or disables every selected token.
     *
     * One endpoint for both directions rather than two, because the only
     * difference is the value written and setEnabled() already decides
     * whether anything changed -- two endpoints would be two copies of the
     * scope resolution above it.
     *
     * @return void
     */
    public function apitokenenablePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.edit')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to change API '
                        . 'tokens.'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        $enabled = (int)filter_input(INPUT_POST, 'enabled') === 1;
        $changed = self::getClass('APITokenManager')->setEnabledMany(
            array_map('intval', (array)($_POST['remitems'] ?? [])),
            $enabled,
            (int)self::$FOGUser->get('id')
        );

        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => sprintf(
                    $enabled
                        ? _('%d token(s) enabled.')
                        : _('%d token(s) disabled.'),
                    $changed
                ),
                'title' => _('API Tokens Updated')
            ]
        );
    }
    /**
     * Issues a token on behalf of another user and returns it once.
     *
     * Its own permission (apitoken.create) rather than part of edit,
     * because this is the one action here that produces a plaintext
     * credential and hands it to somebody who is not its owner. The audit
     * row generate() writes carries the owner as subject and the issuer as
     * createdBy, so that asymmetry is legible afterwards.
     *
     * @return void
     */
    public function issueAPITokenForPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.create')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to issue API '
                        . 'tokens.'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        $forUserID = (int)filter_input(INPUT_POST, 'issuefor');
        $name = trim((string)filter_input(INPUT_POST, 'newtokenname'));

        // Required, and required on the SERVER rather than only by the
        // form's required attribute. A nameless token is the one nobody
        // can ever revoke with confidence: the whole point of the last-used
        // column is deciding what to delete, and "(no name), never used" is
        // not a decision anybody will act on.
        if ('' === $name) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => _('Give the token a name saying what it is for.'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        // The target must be in scope. Without this a scoped administrator
        // could mint a working credential for an account they are not
        // allowed to see, which is a privilege escalation dressed up as a
        // convenience feature.
        $user = self::getClass('User', $forUserID);
        $inScope = self::getClass('APITokenManager')
            ->userInScope($forUserID, (int)self::$FOGUser->get('id'));

        if (!$user->isValid() || !$inScope) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => _('No such user.'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        $token = APIToken::generate($forUserID, $name);
        if (APIToken::DUPLICATE_NAME === $token) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => sprintf(
                        _('%s already has a token called "%s". Names have to '
                            . 'be unique per user so a token can be told '
                            . 'apart from its neighbours when it comes time '
                            . 'to revoke one.'),
                        $user->get('name'),
                        $name
                    ),
                    'title' => _('API Token Failed')
                ]
            );
        }
        if (false === $token) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                [
                    'error' => _('Could not issue the token!'),
                    'title' => _('API Token Failed')
                ]
            );
        }

        $this->_jsonExit(
            HTTPResponseCodes::HTTP_CREATED,
            [
                'msg' => sprintf(
                    _('API token issued for %s.'),
                    $user->get('name')
                ),
                'title' => _('API Token Created'),
                'token' => $token
            ]
        );
    }
    /**
     * Presents mac listing information.
     *
     * @return void
     */
    public function maclist()
    {
        $this->title = _('MAC Address Manufacturer Listing');
        $modalupdatebtn = self::makeButton(
            'updatemacsConfirm',
            _('Confirm'),
            'btn btn-outline-secondary float-end'
        );
        $modalupdatebtn .= self::makeButton(
            'updatemacsCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start'
        );
        $modaldeletebtn = self::makeButton(
            'deletemacsConfirm',
            _('Confirm'),
            'btn btn-outline-secondary float-end'
        );
        $modaldeletebtn .= self::makeButton(
            'deletemacsCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start'
        );
        $buttons = self::makeButton(
            'updatemacs',
            _('Update MAC List'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'deletemacs',
            _('Delete MAC List'),
            'btn btn-danger float-start'
        );
        $modalupdate = self::makeModal(
            'updatemacsmodal',
            _('Update MAC Listing'),
            _('Confirm that you would like to update the MAC vendor listing'),
            $modalupdatebtn,
            '',
            'primary'
        );
        $modaldelete = self::makeModal(
            'deletemacsmodal',
            _('Delete MAC Listings'),
            _('Confirm that you would like to delete the MAC vendor listing'),
            $modaldeletebtn,
            '',
            'warning'
        );
        echo $this->_box(
            $this->title,
            _('Current Records')
            . ': '
            . '<span id="lookupcount">'
            . self::getMACLookupCount()
            . '</span>',
            [
                'help' => _('Import known mac address makers')
                . '<br>'
                . '<a href="http://standards-oui.ieee.org/oui.txt">'
                . 'http://standards-oui.ieee.org/oui.txt'
                . '</a>',
                'footer' => $buttons . $modalupdate . $modaldelete
            ]
        );
    }
    /**
     * Safes the data for real for the mac address stuff.
     *
     * @return void
     */
    public function maclistPost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['update'])) {
            $url = 'https://standards-oui.ieee.org/oui/oui.txt';
            $data = self::$FOGURLRequests->process($url);
            $data = is_array($data) ? array_shift($data) : $data;
            $items = [];
            $start = 18;
            $imported = 0;
            $pat = '#^([0-9a-fA-F]{2}[:\-]){2}([0-9a-fA-F]{2}).*$#';
            foreach (preg_split("/((\r?\n)|(\n?\r))/", (string)$data) as $line) {
                $line = trim($line);
                if (!preg_match($pat, $line)) {
                    continue;
                }
                $mac = trim(
                    substr(
                        $line,
                        0,
                        8
                    )
                );
                $mak = trim(
                    substr(
                        $line,
                        $start,
                        strlen($line) - $start
                    )
                );
                if (strlen($mac) != 8
                    || strlen($mak) < 1
                ) {
                    continue;
                }
                $items[] = [
                    $mac,
                    $mak
                ];
            }
            if (count($items) > 0) {
                // Build the refreshed list in a side table and swap it in
                // atomically, rather than truncating up front. The live table
                // keeps serving lookups for the whole import, and a failed or
                // empty download leaves it untouched instead of wiping it. A
                // fresh side table also sidesteps the install-dependent unique
                // index on the live table (present only on upgraded installs).
                $OUITable = self::getClass('OUI', '', true);
                $OUITable = $OUITable['databaseTable'];
                $tmpTable = $OUITable . '_temp';
                self::$DB->query("DROP TABLE IF EXISTS `$tmpTable`");
                self::$DB->query("CREATE TABLE `$tmpTable` LIKE `$OUITable`");
                list(
                    $first_id,
                    $affected_rows
                ) = self::getClass('OUIManager')
                ->insertBatch(
                    [
                        'prefix',
                        'name'
                    ],
                    $items,
                    $tmpTable
                );
                $imported += $affected_rows;
                if ($imported > 0) {
                    $oldTable = $OUITable . '_old';
                    self::$DB->query("DROP TABLE IF EXISTS `$oldTable`");
                    self::$DB->query(
                        "RENAME TABLE `$OUITable` TO `$oldTable`, "
                        . "`$tmpTable` TO `$OUITable`"
                    );
                    self::$DB->query("DROP TABLE IF EXISTS `$oldTable`");
                } else {
                    self::$DB->query("DROP TABLE IF EXISTS `$tmpTable`");
                }
                unset($items);
            }
            unset($first_id);
        }
        if (isset($_POST['clear'])) {
            self::clearMACLookupTable();
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            ['count' => self::getMACLookupCount()]
        ));
    }
    /**
     * Gets the osid information
     *
     * @return void
     */
    public function getOSID()
    {
        $imageid = (int)filter_input(INPUT_POST, 'image_id');
        $osname = self::getClass(
            'Image',
            $imageid
        )->getOS()->get('name');
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($osname ? $osname : _('No Image specified')));
    }
    /**
     * Single source of truth for setting validation metadata, shared by
     * settingsPost() (server-side validation) and getSettingsList() (input
     * rendering). These maps were previously duplicated in both methods and
     * had to be kept in sync by hand.
     *
     * Numeric constraints are expressed as one of:
     *   true                       any numeric value
     *   ['min' => x, 'max' => y]   integer-valued, within an inclusive range
     *   ['set' => [...]]           value matching one of an explicit list
     *
     * Expressing the large port ranges as bounds (rather than the old
     * range(1, 65535) membership arrays) avoids allocating hundreds of
     * thousands of array elements on every settings load/save.
     *
     * @return array{checkbox:array,numeric:array,ip:array}
     */
    private function _settingsMeta()
    {
        $checkbox = [
            'FOG_REGISTRATION_ENABLED' => true,
            'FOG_PXE_MENU_HIDDEN' => true,
            'FOG_QUICKREG_AUTOPOP' => true,
            'FOG_CLIENT_AUTOUPDATE' => true,
            'FOG_CLIENT_AUTOLOGOFF_ENABLED' => true,
            'FOG_CLIENT_CLIENTUPDATER_ENABLED' => true,
            'FOG_CLIENT_DIRECTORYCLEANER_ENABLED' => true,
            'FOG_CLIENT_DISPLAYMANAGER_ENABLED' => true,
            'FOG_CLIENT_GREENFOG_ENABLED' => true,
            'FOG_CLIENT_HOSTREGISTER_ENABLED' => true,
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => true,
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => true,
            'FOG_CLIENT_PRINTERMANAGER_ENABLED' => true,
            'FOG_CLIENT_SNAPIN_ENABLED' => true,
            'FOG_CLIENT_TASKREBOOT_ENABLED' => true,
            'FOG_CLIENT_USERCLEANUP_ENABLED' => true,
            'FOG_CLIENT_USERTRACKER_ENABLED' => true,
            'FOG_ADVANCED_STATISTICS' => true,
            'FOG_CHANGE_HOSTNAME_EARLY' => true,
            'FOG_DISABLE_CHKDSK' => true,
            'FOG_HOST_LOOKUP' => true,
            'FOG_CAPTUREIGNOREPAGEHIBER' => true,
            'FOG_USE_ANIMATION_EFFECTS' => true,
            'FOG_USE_LEGACY_TASKLIST' => true,
            'FOG_USE_SLOPPY_NAME_LOOKUPS' => true,
            'FOG_PLUGINSYS_ENABLED' => true,
            'FOG_FORMAT_FLAG_IN_GUI' => true,
            'FOG_NO_MENU' => true,
            'FOG_ALWAYS_LOGGED_IN' => true,
            'FOG_ADVANCED_MENU_LOGIN' => true,
            'FOG_TASK_FORCE_REBOOT' => true,
            'FOG_EMAIL_ACTION' => true,
            'FOG_FTP_IMAGE_SIZE' => true,
            'FOG_KERNEL_DEBUG' => true,
            'FOG_ENFORCE_HOST_CHANGES' => true,
            'FOG_LOGIN_INFO_DISPLAY' => true,
            'MULTICASTGLOBALENABLED' => true,
            'SCHEDULERGLOBALENABLED' => true,
            'FILEDELETEQUEUEGLOBALENABLED' => true,
            'PINGHOSTGLOBALENABLED' => true,
            // Ping Host Settings; schema 356. A 1/0 flag so it renders
            // as a checkbox here rather than a free-text box.
            'PINGHOSTUSEICMP' => true,
            'IMAGESIZEGLOBALENABLED' => true,
            'IMAGEREPLICATORGLOBALENABLED' => true,
            'SNAPINREPLICATORGLOBALENABLED' => true,
            'SNAPINHASHGLOBALENABLED' => true,
            'FOG_QUICKREG_IMG_WHEN_REG' => true,
            'FOG_QUICKREG_PROD_KEY_BIOS' => true,
            'FOG_TASKING_ADV_SHUTDOWN_ENABLED' => true,
            'FOG_TASKING_ADV_WOL_ENABLED' => true,
            'FOG_TASKING_ADV_DEBUG_ENABLED' => true,
            'FOG_API_ENABLED' => true,
            'FOG_ENABLE_SHOW_PASSWORDS' => true,
            'FOG_IMAGE_LIST_MENU' => true,
            'FOG_REAUTH_ON_DELETE' => true,
            'FOG_REAUTH_ON_EXPORT' => true,
            'FOG_LOG_INFO' => true,
            'FOG_LOG_ERROR' => true,
            'FOG_LOG_DEBUG' => true,
        ];
        self::$HookManager->processEvent(
            'NEEDSTOBECHECKBOX',
            ['needstobecheckbox' => &$checkbox]
        );

        $imageids = Route::getIds('image', false);
        $groupids = Route::getIds('group', false);

        $viewvals = [-1, 10, 25, 50, 100, 250, 500];
        $regenrange = range(0, 24, .25);
        array_shift($regenrange);

        $numeric = [
            // FOG Boot Settings
            'FOG_PXE_MENU_TIMEOUT' => true,
            'FOG_PIGZ_COMP' => ['min' => 0, 'max' => 22],
            'FOG_KEY_SEQUENCE' => ['min' => 1, 'max' => 35],
            'FOG_PXE_HIDDENMENU_TIMEOUT' => true,
            'FOG_KERNEL_LOGLEVEL' => ['min' => 0, 'max' => 7],
            'FOG_WIPE_TIMEOUT' => true,
            // FOG Linux Service Logs
            'SERVICE_LOG_SIZE' => true,
            // FOG Linux Service Sleep Times
            'PINGHOSTSLEEPTIME' => true,
            'SERVICESLEEPTIME' => true,
            'SNAPINREPSLEEPTIME' => true,
            'SCHEDULERSLEEPTIME' => true,
            'FILEDELETEQUEUESLEEPTIME' => true,
            'IMAGEREPSLEEPTIME' => true,
            'MULTICASESLEEPTIME' => true,
            // FOG Quick Registration
            'FOG_QUICKREG_IMG_ID' => ['set' => self::fastmerge((array)0, $imageids)],
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => ['set' => self::fastmerge((array)0, $groupids)],
            // FOG Service
            'FOG_CLIENT_CHECKIN_TIME' => true,
            'FOG_CLIENT_MAXSIZE' => true,
            'FOG_GRACE_TIMEOUT' => true,
            // FOG Service - Auto Log Off
            'FOG_CLIENT_AUTOLOGOFF_MIN' => true,
            // FOG Service - Display manager
            'FOG_CLIENT_DISPLAYMANAGER_X' => true,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => true,
            'FOG_CLIENT_DISPLAYMANAGER_R' => true,
            // FOG Service - Host Register
            'FOG_QUICKREG_MAX_PENDING_MACS' => true,
            // FOG View Settings
            'FOG_VIEW_DEFAULT_SCREEN' => ['set' => $viewvals],
            'FOG_DATA_RETURNED' => true,
            // General Settings
            'FOG_CAPTURERESIZEPCT' => true,
            'FOG_CHECKIN_TIMEOUT' => true,
            'FOG_MEMORY_LIMIT' => true,
            'FOG_SNAPIN_LIMIT' => true,
            'FOG_FTP_PORT' => ['min' => 1, 'max' => 65535],
            'FOG_FTP_TIMEOUT' => true,
            'FOG_BANDWIDTH_TIME' => true,
            'FOG_URL_BASE_CONNECT_TIMEOUT' => true,
            'FOG_URL_BASE_TIMEOUT' => true,
            'FOG_URL_AVAILABLE_TIMEOUT' => true,
            'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' => ['set' => self::fastmerge((array)0, range(2, 6))],
            // Login Settings
            'FOG_INACTIVITY_TIMEOUT' => ['min' => 1, 'max' => 24],
            'FOG_REGENERATE_TIMEOUT' => ['set' => $regenrange],
            // Multicast Settings
            'FOG_UDPCAST_STARTINGPORT' => ['min' => 1, 'max' => 65535],
            // Was FOG_MULTICASE_MAX_SESSIONS, which matches no setting, so
            // this bound had never actually been applied to anything.
            'FOG_MULTICAST_MAX_SESSIONS' => true,
            'FOG_UDPCAST_MAXWAIT' => true,
            // Deliberately not numeric: this is now a comma separated pool
            // of base ports. MulticastSession::portPool() drops anything
            // udp-sender could not use.
            // Proxy Settings
            'FOG_PROXY_PORT' => ['min' => 0, 'max' => 65535],
            // User Management
            'FOG_USER_MINPASSLENGTH' => true,
        ];

        $ip = [
            // Multicast Settings
            'FOG_MULTICAST_ADDRESS' => true,
            'FOG_MULTICAST_RENDEZVOUS' => true,
            // Proxy Settings
            'FOG_PROXY_IP' => true,
        ];

        // Settings whose value is baked into the page shell (other/index.php)
        // or the theme CSS loaded in the <head>. The settings page only reloads
        // its own fragment after a save, so these do not visibly apply until the
        // whole page is reloaded. Each was verified to be actively consumed:
        //   FOG_THEME              -> page.class.php loads css/$FOG_THEME
        //   FOG_VIEW_DEFAULT_SCREEN-> shell #pageLength (other/index.php)
        //   FOG_TABLE_SCROLL_MODE  -> shell #scrollMode (other/index.php)
        //   FOG_PLUGINSYS_ENABLED  -> plugin menus/pages loaded at boot
        $refresh = [
            'FOG_THEME' => true,
            'FOG_VIEW_DEFAULT_SCREEN' => true,
            'FOG_TABLE_SCROLL_MODE' => true,
            'FOG_PLUGINSYS_ENABLED' => true,
        ];
        self::$HookManager->processEvent(
            'NEEDSPAGEREFRESH',
            ['needspagerefresh' => &$refresh]
        );

        return [
            'checkbox' => $checkbox,
            'numeric' => $numeric,
            'ip' => $ip,
            'refresh' => $refresh,
        ];
    }
    /**
     * Build a standard settings <select> input.
     *
     * Replaces several byte-for-byte identical inline select/option loops in
     * the settings list formatter.
     *
     * @param int|string $id    the settingID (used as the field name)
     * @param string     $key   the settingKey (used as the element id)
     * @param mixed      $value the currently stored value (for selection)
     * @param array      $vals  map of display text => option value
     *
     * @return string
     */
    private static function _selectInput($id, $key, $value, array $vals)
    {
        $html = '<select '
            . 'class="form-control" name="'
            . $id
            . '" autocomplete="off" id="'
            . $key
            . '">';
        foreach ($vals as $text => $val) {
            $html .= '<option value="'
                . \Initiator::e($val)
                . '"'
                . (
                    $val == $value ?
                    ' selected' :
                    ''
                )
                . '>'
                . \Initiator::e($text)
                . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
    /**
     * Build a bootstrap-slider settings input.
     *
     * Replaces several near-identical inline makeInput() slider calls in the
     * settings list formatter that differed only in default/min/max/step.
     *
     * @param int|string $id      the settingID (used as the field name)
     * @param string     $key     the settingKey (used as the element id)
     * @param mixed      $value   the currently stored value
     * @param string     $default the placeholder/default value
     * @param string     $min     data-slider-min
     * @param string     $max     data-slider-max
     * @param string     $step    data-slider-step
     *
     * @return string
     */
    private static function _sliderInput($id, $key, $value, $default, $min, $max, $step)
    {
        return self::makeInput(
            'form-control slider',
            $id,
            $default,
            'text',
            $key,
            $value,
            false,
            false,
            -1,
            -1,
            'data-slider-min="' . $min . '" '
            . 'data-slider-max="' . $max . '" '
            . 'data-slider-step="' . $step . '" '
            . 'data-slider-value="' . $value . '" '
            . 'data-slider-orientation="horizontal" '
            . 'data-slider-selection="before" '
            . 'data-slider-tooltip="show" '
            . 'data-slider-id="blue"'
        );
    }
    /**
     * Save updates to the fog settings information.
     *
     * @return void
     */
    /**
     * Renders the value-side input control for a single setting row.
     *
     * Shared by the server-side settings list (getSettingsList) and the
     * server-rendered category panels (_renderSettingsPanels). $row uses the
     * real DB column names (settingID, settingKey, settingValue, ...).
     *
     * @param array $row               the setting row (real column names)
     * @param array $needstobenumeric  numeric-constraint map from _settingsMeta
     * @param array $needstobecheckbox checkbox-key map from _settingsMeta
     *
     * @return string
     */
    private static function _renderSettingInput(
        array $row,
        array $needstobenumeric,
        array $needstobecheckbox
    ) {
        switch ($row['settingKey']) {
            case 'FOG_VIEW_DEFAULT_SCREEN':
                $vals = [
                    _('10') => 10,
                    _('25') => 25,
                    _('50') => 50,
                    _('100') => 100,
                    _('All') => -1
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_TABLE_SCROLL_MODE':
                $vals = [
                    _('Infinite scroll') => 'infinite',
                    _('Paged') => 'paged'
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT':
                $vals = [
                    _('Partclone Gzip') => 0,
                    _('Partclone Gzip Split 200MiB') => 2,
                    _('Partclone Uncompressed') => 3,
                    _('Partclone Uncompressed 200MiB') => 4,
                    _('Partclone Zstd') => 5,
                    _('Partclone Zstd Split 200MiB') => 6
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_MULTICAST_DUPLEX':
                $vals = [
                    'HALF_DUPLEX' => '--half-duplex',
                    'FULL_DUPLEX' => '--full-duplex'
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_DEFAULT_LOCALE':
                $langs =& self::$foglang['Language'];
                $vals = array_flip($langs);
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_QUICKREG_IMG_ID':
            case 'FOG_QUICKREG_GROUP_ASSOC':
            case 'FOG_KEY_SEQUENCE':
                switch ($row['settingKey']) {
                    case 'FOG_QUICKREG_IMG_ID':
                        $objGetter = 'image';
                        break;
                    case 'FOG_QUICKREG_GROUP_ASSOC':
                        $objGetter = 'group';
                        break;
                    case 'FOG_KEY_SEQUENCE':
                        $objGetter = 'keysequence';
                        break;
                }
                $input = self::getClass($objGetter.'manager')->buildSelectBox(
                    $row['settingValue'],
                    $row['settingID'],
                    'name',
                    '',
                    false,
                    'id',
                    $row['settingKey']
                );
                break;
            case 'FOG_BOOT_EXIT_TYPE':
            case 'FOG_EFI_BOOT_EXIT_TYPE':
                $input = Setting::buildExitSelector(
                    $row['settingID'],
                    $row['settingValue'],
                    false,
                    $row['settingKey']
                );
                break;
            case 'FOG_TZ_INFO':
                $dt = self::niceDate('now');
                $tzIDs = \DateTimeZone::listIdentifiers();
                ob_start();
                echo '<select class="form-control" name="'
                    . $row['settingID']
                    . '" id="'
                    . $row['settingKey']
                    . '">';
                foreach ((array)$tzIDs as $i => &$tz) {
                    $current_tz = self::getClass('DateTimeZone', $tz);
                    $offset = $current_tz->getOffset($dt);
                    $transition = $current_tz->getTransitions(
                        $dt->getTimestamp(),
                        $dt->getTimestamp()
                    );
                    $abbr = $transition[0]['abbr'];
                    $offset = sprintf(
                        '%+03d:%02u',
                        floor($offset / 3600),
                        floor(abs($offset) % 3600 / 60)
                    );
                    printf(
                        '<option value="%s"%s>%s [%s %s]</option>',
                        \Initiator::e($tz),
                        (
                            $row['settingValue'] == $tz ?
                            ' selected' :
                            ''
                        ),
                        \Initiator::e($tz),
                        \Initiator::e($abbr),
                        \Initiator::e($offset)
                    );
                    unset(
                        $current_tz,
                        $offset,
                        $transition,
                        $abbr,
                        $offset,
                        $tz
                    );
                }
                echo '</select>';
                $input = ob_get_clean();
                break;
            case 'FOG_COMPANY_COLOR':
                $input = self::makeInput(
                    'jscolor {required:false} {refine: false} form-control',
                    $row['settingID'],
                    '',
                    'text',
                    $row['settingKey'],
                    $row['settingValue'],
                    false,
                    false,
                    -1,
                    6
                );
                break;
            case 'FOG_CLIENT_BANNER_SHA':
                $input = self::makeInput(
                    'form-control',
                    $row['settingID'],
                    '',
                    'text',
                    $row['settingKey'],
                    $row['settingValue'],
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                );
                break;
            case 'FOG_QUICKREG_OS_ID':
                $image = new Image(self::getSetting('FOG_QUICKREG_IMG_ID'));
                if (!$image->isValid()) {
                    $osname = _('No image specified');
                } else {
                    $osname = $image->get('os')->get('name');
                }
                $input = '<p id="'
                    . $row['settingKey']
                    . '">'
                    . $osname
                    . '</p>';
                break;
            case 'FOG_CLIENT_BANNER_IMAGE':
                $input = '<div class="input-group">'
                    . self::makeLabel(
                        'btn btn-info',
                        $row['settingKey'],
                        _('Browse')
                        . self::makeInput(
                            'd-none',
                            $row['settingID'],
                            '',
                            'file',
                            $row['settingKey'],
                            '',
                            true
                        )
                    )
                    . self::makeInput(
                        'form-control filedisp',
                        'banner',
                        '',
                        'text',
                        '',
                        $row['settingValue'],
                        false,
                        false,
                        -1,
                        -1,
                        '',
                        true
                    )
                    . '</div>';
                break;
            case 'FOG_COMPANY_TOS':
            case 'FOG_AD_DEFAULT_OU':
                $input = self::makeTextarea(
                    'form-control',
                    $row['settingID'],
                    '',
                    $row['settingKey'],
                    $row['settingValue']
                );
                break;
            case (isset($needstobecheckbox[$row['settingKey']])):
                $input = self::makeInput(
                    '',
                    $row['settingID'],
                    '',
                    'checkbox',
                    $row['settingKey'],
                    '',
                    false,
                    false,
                    -1,
                    -1,
                    ($row['settingValue'] > 0 ? 'checked' : '')
                );
                break;
            case 'FOG_API_TOKEN':
                $input = '<div class="input-group">';
                $input .= self::makeInput(
                    'form-control token',
                    $row['settingID'],
                    '',
                    'text',
                    $row['settingKey'],
                    base64_encode($row['settingValue']),
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                );
                $input .= self::makeButton(
                    'resettoken',
                    _('Reset Token'),
                    'btn btn-warning resettoken'
                );
                $input .= '</div>';
                break;
            case (preg_match('#pass#i', $row['settingKey'])
                && !preg_match('#(valid|min)#i', $row['settingKey'])):
                switch ($row['settingKey']) {
                    case 'FOG_STORAGENODE_MYSQLPASS':
                        $input = self::makeInput(
                            'form-control',
                            $row['settingID'],
                            '',
                            'text',
                            $row['settingKey'],
                            $row['settingValue']
                        );
                        break;
                    case 'FOG_AD_DEFAULT_PASSWORD':
                        $input = '<div class="input-group">'
                            . self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                'password',
                                $row['settingKey'],
                                (
                                    $row['settingValue'] ?
                                    '********************************' :
                                    ''
                                )
                            )
                            . '</div>';
                        break;
                    default:
                        $input = '<div class="input-group">'
                            . self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                'password',
                                $row['settingKey'],
                                $row['settingValue']
                            )
                            . '</div>';
                        break;
                }
                break;
            case 'FOG_PIGZ_COMP':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '6',
                    '0',
                    '22',
                    '1'
                );
                break;
            case 'FOG_KERNEL_LOGLEVEL':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '4',
                    '0',
                    '7',
                    '1'
                );
                break;
            case 'FOG_INACTIVITY_TIMEOUT':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '1',
                    '1',
                    '24',
                    '1'
                );
                break;
            case 'FOG_REGENERATE_TIMEOUT':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '0.50',
                    '0.25',
                    '24',
                    '0.25'
                );
                break;
            default:
                $type = 'text';
                if (isset($needstobenumeric[$row['settingKey']])) {
                    $type = 'number';
                }
                $input = self::makeInput(
                    'form-control',
                    $row['settingID'],
                    '',
                    $type,
                    $row['settingKey'],
                    $row['settingValue']
                );
        }
        return $input;
        return $input;
    }
    /**
     * Resolves a posted settings key to a globalSettings row id.
     *
     * The settings forms post ids, so Route::indiv() -- which loads by id --
     * has always been right for them. A post keyed by setting NAME instead
     * got a bodyless 404 and no explanation: indiv() calls sendResponse(),
     * which calls breakHead(), which exits. Both savers collect $items and
     * insertBatch once at the end, so nothing had been written yet and the
     * whole save was silently discarded. A scripted settings change looked
     * like it had worked.
     *
     * Names are resolved here rather than inside Route::indiv() because
     * indiv() is the generic single-entity loader for every class in the
     * API. Teaching it to fall back to a name lookup would change that
     * contract everywhere in order to fix it in two places.
     *
     * An unresolvable key is returned untouched, so a genuinely bogus key
     * still reaches the same 404 it always did.
     *
     * @param string $key the posted key: a row id, or a setting name
     *
     * @return string the id to load, and to write back as the row id
     */
    private static function _settingIdFor($key)
    {
        if (is_numeric($key)) {
            return $key;
        }
        // Anchored, and deliberately narrower than "not empty": this value
        // comes straight off the request body and goes into a Route filter,
        // where a '*' or '+' in a scalar value is turned into LIKE '%' and
        // would match every setting -- handing back the first id and writing
        // the posted value to a setting nobody named. Setting keys are
        // ALL_CAPS_WITH_UNDERSCORES, so anything else is not one.
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$key)) {
            return $key;
        }
        $ids = Route::getIds('setting', ['name' => $key], 'id');
        return count($ids) ? array_shift($ids) : $key;
    }
    public function settingsPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('SETTINGS_POST');
        $meta = $this->_settingsMeta();
        $checkbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
        $needstobeip = $meta['ip'];
        unset($findWhere, $setWhere);

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            $combined = $vars + $_POST + $_FILES;
            // Initialised before the loop, like the two sibling savers above.
            // The body has three `continue` paths that skip the append, and
            // $combined can be empty, so a post that changes nothing left
            // $items undefined -- and the count below reads it, which is a
            // warning rather than a silent 0. `?:` there was papering over
            // the missing initialisation; `??` would have hidden it too.
            $items = [];
            foreach ($combined as $key => &$val) {
                // Resolved into its own variable: $key stays the posted key
                // because the $_FILES lookup below is keyed by what the form
                // actually sent.
                $id = self::_settingIdFor($key);
                $Setting = Route::getItem('setting', $id);
                if (!isset($_FILES[$key]) || !$_FILES[$key]) {
                    $set = trim(filter_var($val));
                }
                if (!$Setting) {
                    continue;
                }
                $name = trim($Setting->name);
                $val = trim($Setting->value);
                if ($val && $val == ($set ?? '')) {
                    continue;
                }
                if (isset($checkbox[$name])) {
                    $set = intval($set) < 1 ? 0 : 1;
                } elseif (isset($needstobenumeric[$name])) {
                    $constraint = $needstobenumeric[$name];
                    $allowsZero = ($constraint === true)
                        ? false
                        : (isset($constraint['set'])
                            ? in_array(0, $constraint['set'])
                            : ($constraint['min'] <= 0 && $constraint['max'] >= 0));
                    if ($allowsZero && !$set) {
                        $set = 0;
                    }
                    if (!is_numeric($set)) {
                        throw new \Exception(
                            $name . ' ' . _('value must be numeric')
                        );
                    }
                    if ($constraint !== true) {
                        $inRange = isset($constraint['set'])
                            ? in_array($set, $constraint['set'])
                            : (floor($set) == $set
                                && $set >= $constraint['min']
                                && $set <= $constraint['max']);
                        if (!$inRange) {
                            throw new \Exception(
                                $name . ' ' . _('value is not in the required range')
                            );
                        }
                    }
                } elseif (isset($needstobeip[$name])) {
                    if (!filter_var($set, FILTER_VALIDATE_IP) and $set != 0 and $set) {
                        throw new \Exception(
                            $name . ' ' . _('value must be a valid IP Address')
                        );
                    }
                }
                switch ($name) {
                    case 'FOG_AD_DEFAULT_PASSWORD':
                        $set = (
                            preg_match('/^\*{32}$/', $set) ?
                            self::getSetting($name) :
                            $set
                        );
                        break;
                    case 'FOG_API_TOKEN':
                        $set = base64_decode($set);
                        break;
                    case 'FOG_MEMORY_LIMIT':
                        if ($set < 128) {
                            throw new \Exception(
                                _('Memory limit cannot be less than 128')
                            );
                        }
                        break;
                    case 'FOG_CLIENT_BANNER_SHA':
                        continue 2;
                    case 'FOG_CLIENT_BANNER_IMAGE':
                        $banner = filter_input(INPUT_POST, 'banner');
                        $set = $banner;
                        if (!$banner) {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', '');
                        }
                        if (!($_FILES[$key]['name']
                            && file_exists($_FILES[$key]['tmp_name']))
                        ) {
                            continue 2;
                        }
                        $set = preg_replace(
                            '/[^\-\w\.]+/',
                            '_',
                            trim(basename($_FILES[$key]['name']))
                        );
                        $src = sprintf(
                            '%s/%s',
                            dirname($_FILES[$key]['tmp_name']),
                            basename($_FILES[$key]['tmp_name'])
                        );
                        list(
                            $width,
                            $height,
                            $type,
                            $attr
                        ) = getimagesize($src);
                        $validExtensions = [
                            'jpg',
                            'jpeg',
                            'png',
                        ];
                        $extensionCheck = strtolower(pathinfo($set, PATHINFO_EXTENSION));
                        if (!in_array($extensionCheck, $validExtensions)) {
                            throw new \Exception(
                                _('Upload file extension must be, jpg, jpeg, or png')
                            );
                        }
                        if ($width != 650) {
                            throw new \Exception(
                                _('Width must be 650 pixels.')
                            );
                        }
                        if ($height != 120) {
                            throw new \Exception(
                                _('Height must be 120 pixels.')
                            );
                        }
                        $dest = sprintf(
                            '%s%smanagement%sother%s%s',
                            BASEPATH,
                            DS,
                            DS,
                            DS,
                            $set
                        );
                        $hash = hash_file(
                            'sha512',
                            $src
                        );
                        if (!move_uploaded_file($src, $dest)) {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', '');
                            $set = '';
                            throw new \Exception(_('Failed to install logo file'));
                        } else {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', $hash);
                        }
                }
                // ADR 0021 Decision 10, and the HARD constraint behind it:
                // anything that reduces the record must first be written to
                // the record, and if that write cannot happen the reduction
                // does not either. `settings.edit` is not the gate for this
                // -- SIX page nodes map onto that one permission -- so a
                // retention window also needs `audit.manage`, which is why
                // the field is not rendered without it either.
                if (Retention::isRetentionSetting($name)) {
                    if (!Authorization::can('audit.manage')) {
                        throw new \Exception(
                            _('Changing a retention window requires the '
                            . 'audit manage permission')
                        );
                    }
                    if (!Retention::permitSettingChange($name, $val, $set)) {
                        $serverFault = true;
                        throw new \Exception(
                            _('Refused: the change to this retention window '
                            . 'could not be recorded in the audit log, and '
                            . 'shortening a window that cannot be recorded '
                            . 'is exactly what the audit log is for')
                        );
                    }
                }
                $items[] = [
                    $id,
                    $name,
                    $set,
                    trim((string) $Setting->description),
                    trim((string) $Setting->category)
                ];
                unset($Setting);
            }
            if (count($items) > 0) {
                $SettingMan = self::getClass('SettingManager');
                /*
                 * settingDesc and settingCategory are named even though this
                 * saver never changes them, and the values are the ones just
                 * read back. globalSettings declares both longtext NOT NULL
                 * with no DEFAULT -- a longtext could not carry one on the
                 * MySQL versions FOG supports -- so an INSERT that leaves
                 * them out is error 1364 on a strict server, which is what
                 * saving any setting became once GH-1245 stopped PDODB
                 * clearing sql_mode.
                 *
                 * insertBatch() now backfills a column like this on its own,
                 * so this is belt and braces rather than the fix. It is worth
                 * having anyway: the backfill can only supply '', and if a
                 * setting row is ever genuinely absent this writes the real
                 * description and category instead of blanking them.
                 */
                $insert_fields = [
                    'id',
                    'name',
                    'value',
                    'description',
                    'category'
                ];
                if (!$SettingMan->insertBatch($insert_fields, $items)) {
                    $serverFault = true;
                    throw new \Exception(_('Settings update failed!'));
                }
                // This saver writes globalSettings directly (insertBatch /
                // Setting->save()) rather than through setSetting(), so
                // nothing invalidated the shared settings cache -- the value
                // landed in the database but every other request kept serving
                // the pre-save file cache for up to $settingsCacheTTL (300s),
                // and sibling php-fpm workers kept their in-memory copy. The
                // settings page reads globalSettings with its own SQL, so it
                // showed the NEW value while the rest of the UI acted on the
                // OLD one -- e.g. FOG_TABLE_SCROLL_MODE switched to "paged"
                // but every list still rendered infinite scroll, through a
                // hard refresh, until the TTL lapsed. Flushing here also
                // raises the cross-process signal so other workers re-read.
                FOGBase::clearSettingsCache();
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Settings successfully stored!'),
                    'title' => _('Settings Update Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Settings Update Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Flushes the per-process settings cache and raises the cross-process
     * flush signal (AJAX).
     *
     * @return void
     */
    public function cacheFlush()
    {
        $this->_postOnly();
    }
    /**
     * Empties the settings cache.
     *
     * @return void
     */
    public function cacheFlushPost()
    {
        self::checkAuthAndCSRF();
        try {
            FOGBase::clearSettingsCache();
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => _('Settings cache flushed'),
                    'title' => _('Cache Flushed')
                ]
            );
        } catch (\Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Cache Flush Failed')
                ]
            );
        }
    }
    /**
     * Reloads all settings into the cache with a single query and raises the
     * cross-process flush signal (AJAX).
     *
     * @return void
     */
    public function cacheRefresh()
    {
        $this->_postOnly();
    }
    /**
     * Rebuilds the settings cache.
     *
     * @return void
     */
    public function cacheRefreshPost()
    {
        self::checkAuthAndCSRF();
        try {
            $count = FOGBase::refreshSettingsCache();
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => sprintf(
                        _('Reloaded %d setting(s) into cache'),
                        $count
                    ),
                    'title' => _('Cache Refreshed')
                ]
            );
        } catch (\Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Cache Refresh Failed')
                ]
            );
        }
    }
    /**
     * Tablize the fog settings.
     *
     * @return void
     */
    public function settings()
    {
        $this->title = _('FOG Settings');

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('FOG Settings');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo '<div class="input-group input-group-sm settings-search-box">';
        echo '<input type="text" id="settings-search" class="form-control" '
            . 'placeholder="' . _('Search settings') . '" autocomplete="off">';
        echo '<button type="button" id="settings-search-clear" '
            . 'class="btn btn-secondary" title="' . _('Clear') . '">'
            . '<i class="fas fa-xmark"></i></button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body" id="settings-content">';
        echo $this->_renderSettings();
        echo '</div>';
        echo '<div class="card-footer">';
        echo '<button type="button" id="settings-cache-flush" '
            . 'class="btn btn-warning">'
            . _('Flush Settings Cache')
            . '</button> ';
        echo '<button type="button" id="settings-cache-refresh" '
            . 'class="btn btn-primary">'
            . _('Refresh Settings Cache')
            . '</button>';
        // Read-only cache snapshot for this request. On the web tier static
        // state is reset per request, so these counts reflect the work done to
        // render this page (boot, auth, routing, plugins); reload to re-sample.
        $stats = self::getSettingsCacheStats();
        $flushAge = $stats['flushAgeSeconds'];
        echo '<dl class="dl-horizontal dl-spaced">';
        echo '<dt>' . _('Keys cached') . '</dt><dd>'
            . (int) $stats['keysCached'] . '</dd>';
        echo '<dt>' . _('Hits / Misses / Queries') . '</dt><dd>'
            . (int) $stats['hits'] . ' / '
            . (int) $stats['misses'] . ' / '
            . (int) $stats['dbQueries']
            . ' (' . (float) $stats['hitRatePct'] . '% '
            . _('hit rate') . ', ' . _('this request') . ')</dd>';
        echo '<dt>' . _('TTL') . '</dt><dd>'
            . (int) $stats['ttl'] . 's</dd>';
        $file = $stats['fileCache'];
        echo '<dt>' . _('Persistent file') . '</dt><dd>'
            . (!$file['enabled']
                ? _('disabled')
                : ($file['exists']
                    ? _('present') . ' (' . (int) $file['ageSeconds'] . 's '
                        . _('old') . ')'
                    : _('not built yet')))
            . '</dd>';
        echo '<dt>' . _('Last flush') . '</dt><dd>'
            . ($flushAge === null
                ? _('never')
                : (int) $flushAge . 's ' . _('ago'))
            . '</dd>';
        if (count($stats['cachedKeys']) > 0) {
            // Collapsed by default: this list can run to hundreds of keys and
            // would otherwise dominate the panel (worst on mobile). The count
            // stays visible in the summary; tap/click to expand the names.
            echo '<dt>' . _('Cached keys') . '</dt><dd>'
                . '<details class="cached-keys">'
                . '<summary>' . (int) count($stats['cachedKeys'])
                . ' ' . _('keys') . '</summary>'
                . '<small class="text-muted">'
                . \Initiator::e(implode(', ', array_keys($stats['cachedKeys'])))
                . '</small></details></dd>';
        }
        echo '</dl>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Builds the category-nav + form-panel body for the settings view.
     *
     * Settings are fetched once, grouped by category, and rendered as one
     * panel per category (all server-side). The left nav and the search box
     * (client side) drive which panel/rows are visible. Reused verbatim by
     * settingsContent() so the JS can refresh the body after a save without
     * reloading the whole page.
     *
     * @return string
     */
    private function _renderSettings()
    {
        $meta = $this->_settingsMeta();
        $needstobecheckbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
        $needsrefresh = $meta['refresh'];
        $refreshtip = _(
            'Changing this setting takes effect after you reload the page. '
            . 'A hard refresh (Ctrl+F5, or Cmd+Shift+R) may be required.'
        );

        $table = self::getClass('SettingManager')->getTable();
        $sql = 'SELECT `settingID`, `settingKey`, `settingDesc`, '
            . '`settingValue`, `settingCategory` FROM `' . $table . '` '
            . 'ORDER BY `settingCategory` ASC, `settingKey` ASC';
        $rows = self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        // Hidden rather than shown-and-refused: a field that posts back an
        // error is a worse boundary than one that is not there, and the
        // category counts below are built from this same list so a hidden
        // field must not be counted either. See ADR 0021 Decision 9 for why
        // `settings.edit` is not the gate for these.
        $mayManageAudit = Authorization::can('audit.manage');
        $retentionKeys = Retention::settingKeys();

        $byCat = [];
        foreach ((array) $rows as $row) {
            if (!$mayManageAudit
                && in_array($row['settingKey'], $retentionKeys, true)
            ) {
                continue;
            }
            // Never shown, to anyone. FOG generates and consumes this
            // key itself (FOGBase::nodeApiKey(), inherited here); there is
            // no value an admin could usefully type, and printing a shared
            // secret into a form field is a leak with no upside. Rotation
            // is deleting the row -- the next request regenerates one.
            if ($row['settingKey'] === self::NODE_API_KEY_SETTING) {
                continue;
            }
            $cat = trim((string) $row['settingCategory']);
            if ($cat === '') {
                $cat = _('Uncategorized');
            }
            $byCat[$cat][] = $row;
        }
        ksort($byCat, SORT_NATURAL | SORT_FLAG_CASE);

        ob_start();
        echo '<div class="row settings-layout">';

        // Left category nav.
        echo '<div class="col-md-3 col-sm-4 settings-nav-col">';
        echo '<ul class="nav nav-pills flex-column" id="settings-nav">';
        $first = true;
        foreach ($byCat as $cat => $catRows) {
            echo '<li class="settings-nav-item' . ($first ? ' active' : '') . '">'
                . '<a href="#" data-cat="' . \Initiator::e($cat) . '">'
                . \Initiator::e($cat)
                . ' <span class="badge">' . count($catRows) . '</span>'
                . '</a></li>';
            $first = false;
        }
        echo '</ul>';
        echo '</div>';

        // Right form panels.
        echo '<div class="col-md-9 col-sm-8 settings-panel-col">';
        $first = true;
        foreach ($byCat as $cat => $catRows) {
            echo '<div class="settings-panel' . ($first ? ' active' : '') . '" '
                . 'data-cat="' . \Initiator::e($cat) . '">';
            // Doubles as a section heading on desktop and an accordion toggle
            // on mobile (see fog.about.settings.js / fog-default-ui.scss).
            echo '<h4 class="settings-panel-title" '
                . 'data-cat="' . \Initiator::e($cat) . '">'
                . '<span>' . \Initiator::e($cat) . '</span>'
                . '<i class="fas fa-chevron-down settings-panel-caret"></i>'
                . '</h4>';
            echo '<div class="settings-panel-body">';
            foreach ($catRows as $row) {
                $desc = trim((string) $row['settingDesc']);
                $wantsrefresh = isset($needsrefresh[$row['settingKey']]);
                $input = self::_renderSettingInput(
                    $row,
                    $needstobenumeric,
                    $needstobecheckbox
                );
                // Search haystack: key + description + value. Value is capped
                // so a setting holding a long blob can't bloat the attribute.
                // "refresh reload" lets the search box surface the flagged ones.
                $haystack = strtolower(
                    $row['settingKey'] . ' ' . $desc . ' '
                    . substr((string) $row['settingValue'], 0, 200)
                    . ($wantsrefresh ? ' refresh reload hard refresh' : '')
                );
                // One tooltip per label: the description, with the reload note
                // appended for flagged settings. Keeping it on the label (not a
                // nested icon) avoids two overlapping tooltips on the same row.
                $tip = $desc;
                if ($wantsrefresh) {
                    $tip .= ($tip !== '' ? '  —  ' : '') . $refreshtip;
                }
                echo '<div class="form-group settings-row" '
                    . 'data-search="'
                    . \Initiator::e($haystack)
                    . '">';
                echo '<label class="col-form-label settings-label" for="'
                    . \Initiator::e($row['settingKey']) . '"';
                if ($tip !== '') {
                    echo ' data-bs-toggle="tooltip" data-bs-placement="top" title="'
                        . \Initiator::e($tip) . '"';
                }
                echo '>' . \Initiator::e($row['settingKey']);
                if ($wantsrefresh) {
                    // Visual marker only (no own tooltip); the label tooltip
                    // above already carries the reload note.
                    echo ' <i class="fas fa-arrows-rotate text-muted settings-refresh-note"'
                        . ' aria-hidden="true"></i>';
                }
                echo '</label>';
                echo '<div class="settings-control">' . $input . '</div>';
                echo '</div>';
            }
            echo '</div>'; // .settings-panel-body
            echo '</div>'; // .settings-panel
            $first = false;
        }
        echo '<div class="settings-noresults d-none text-muted">'
            . _('No settings match your search.') . '</div>';
        echo '</div>';

        echo '</div>';
        return ob_get_clean();
    }
    /**
     * AJAX fragment: the settings body only (nav + panels).
     *
     * Used by the settings JS to refresh values/derived fields after a save
     * without a full page reload.
     *
     * @return void
     */
    public function settingsContent()
    {
        echo $this->_renderSettings();
        exit;
    }
    /**
     * Gets and displays log files.
     *
     * @return void
     */
    public function logviewer()
    {
        $StorageGroups = Route::getList('storagegroup');

        // Log selector.
        $logtype = _('error');
        $logparse = function ($log) use (
            &$files,
            &$StorageNode,
            &$logtype
        ) {
            $str = sprintf(
                _('%s %s log (%s)'),
                (
                    preg_match('#nginx#i', $log) ?
                    'NGINX' :
                    (
                        preg_match('#apache|httpd#', $log) ?
                        'Apache' :
                        (
                            preg_match('#fpm#i', $log) ?
                            'PHP-FPM' :
                            ''
                        )
                    )
                ),
                $logtype,
                basename($log)
            );
            $files[$StorageNode->name][_($str)] = $log;
        };
        foreach ($StorageGroups as &$StorageGroup) {
            if (count($StorageGroup->enablednodes ?: []) < 1) {
                continue;
            }
            $StorageNode = $StorageGroup->masternode;
            Route::logfiles($StorageNode->id);
            $fogfiles = json_decode(
                Route::getData(),
                true
            );
            try {
                $apacheerrlog = preg_grep(
                    '#(error[\_|\.]log$)#i',
                    $fogfiles
                );
                $apacheacclog = preg_grep(
                    '#(access[\_|\.]log$)#i',
                    $fogfiles
                );
                list(
                    $filedeletelogname,
                    $imagereplicatorlogname,
                    $imagesizelogname,
                    $multicastlogname,
                    $pinghostlogname,
                    $pluginrunnerlogname,
                    $retentionrunnerlogname,
                    $schedulerlogname,
                    $servicelogname,
                    $snapinhashlogname,
                    $snapinreplicatorlogname,
                ) = self::getSetting([
                    'FILEDELETEQUEUELOGFILENAME',
                    'IMAGEREPLICATORLOGFILENAME',
                    'IMAGESIZELOGFILENAME',
                    'MULTICASTLOGFILENAME',
                    'PINGHOSTLOGFILENAME',
                    'PLUGINRUNNERLOGFILENAME',
                    'RETENTIONRUNNERLOGFILENAME',
                    'SCHEDULERLOGFILENAME',
                    'SERVICEMASTERLOGFILENAME',
                    'SNAPINHASHLOGFILENAME',
                    'SNAPINREPLICATORLOGFILENAME',
                ]);
                $multicastlog = preg_grep(
                    '#('.$multicastlogname.'$)#i',
                    $fogfiles
                );
                $multicastlog = array_shift($multicastlog);
                $schedulerlog = preg_grep(
                    '#('.$schedulerlogname.'$)#i',
                    $fogfiles
                );
                $schedulerlog = array_shift($schedulerlog);
                $imgrepliclog = preg_grep(
                    '#('.$imagereplicatorlogname.'$)#i',
                    $fogfiles
                );
                $imgrepliclog = array_shift($imgrepliclog);
                $imagesizelog = preg_grep(
                    '#('.$imagesizelogname.'$)#i',
                    $fogfiles
                );
                $imagesizelog = array_shift($imagesizelog);
                $snapinreplog = preg_grep(
                    '#('.$snapinreplicatorlogname.'$)#i',
                    $fogfiles
                );
                $snapinreplog = array_shift($snapinreplog);
                $snapinhashlog = preg_grep(
                    '#('.$snapinhashlogname.'$)#i',
                    $fogfiles
                );
                $snapinhashlog = array_shift($snapinhashlog);
                $pinghostlog = preg_grep(
                    '#('.$pinghostlogname.'$)#i',
                    $fogfiles
                );
                $pinghostlog = array_shift($pinghostlog);
                $filedeletequeuelog = preg_grep(
                    '#('.$filedeletelogname.'$)#i',
                    $fogfiles
                );
                $filedeletequeuelog = array_shift($filedeletequeuelog);
                $pluginrunnerlog = preg_grep(
                    '#('.$pluginrunnerlogname.'$)#i',
                    $fogfiles
                );
                $pluginrunnerlog = array_shift($pluginrunnerlog);
                $retentionrunnerlog = preg_grep(
                    '#('.$retentionrunnerlogname.'$)#i',
                    $fogfiles
                );
                $retentionrunnerlog = array_shift($retentionrunnerlog);
                $svcmasterlog = preg_grep(
                    '#('.$servicelogname.'$)#i',
                    $fogfiles
                );
                $svcmasterlog = array_shift($svcmasterlog);
                $imgtransferlogs = preg_grep(
                    '#('.$imagereplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $snptransferlogs = preg_grep(
                    '#('.$snapinreplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $files[$StorageNode->name] = [
                    (
                        $svcmasterlog ?
                        _('Service Master') :
                        null
                    )=> (
                        $svcmasterlog ?
                        $svcmasterlog :
                        null
                    ),
                    (
                        $multicastlog ?
                        _('Multicast') :
                        null
                    ) => (
                        $multicastlog ?
                        $multicastlog :
                        null
                    ),
                    (
                        $schedulerlog ?
                        _('Scheduler') :
                        null
                    ) => (
                        $schedulerlog ?
                        $schedulerlog :
                        null
                    ),
                    (
                        $imgrepliclog ?
                        _('Image Replicator') :
                        null
                    ) => (
                        $imgrepliclog ?
                        $imgrepliclog :
                        null
                    ),
                    (
                        $imagesizelog ?
                        _('Image Size') :
                        null
                    ) => (
                        $imagesizelog ?
                        $imagesizelog :
                        null
                    ),
                    (
                        $snapinreplog ?
                        _('Snapin Replicator') :
                        null
                    ) => (
                        $snapinreplog ?
                        $snapinreplog :
                        null
                    ),
                    (
                        $snapinhashlog ?
                        _('Snapin Hash') :
                        null
                    ) => (
                        $snapinhashlog ?
                        $snapinhashlog :
                        null
                    ),
                    (
                        $pinghostlog ?
                        _('Ping Hosts') :
                        null
                    ) => (
                        $pinghostlog ?
                        $pinghostlog :
                        null
                    ),
                    (
                        $filedeletequeuelog ?
                        _('File Delete Queue') :
                        null
                    ) => (
                        $filedeletequeuelog ?
                        $filedeletequeuelog :
                        null
                    ),
                    (
                        $pluginrunnerlog ?
                        _('Plugin Runner') :
                        null
                    ) => (
                        $pluginrunnerlog ?
                        $pluginrunnerlog :
                        null
                    ),
                    (
                        $retentionrunnerlog ?
                        _('Retention Runner') :
                        null
                    ) => (
                        $retentionrunnerlog ?
                        $retentionrunnerlog :
                        null
                    ),
                ];
                array_map($logparse, (array)$apacheerrlog);
                $logtype = _('access');
                array_map($logparse, (array)$apacheacclog);
                foreach ((array)$imgtransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Image Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                foreach ((array)$snptransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Snapin Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                $files[$StorageNode->name] = array_filter(
                    (array)$files[$StorageNode->name]
                );
            } catch (\Exception $e) {
                $files[$StorageNode->name] = [
                    $e->getMessage() => null,
                ];
            }
            $ip[$StorageNode->name] = $StorageNode->ip;
            self::$HookManager->processEvent(
                'LOG_VIEWER_HOOK',
                [
                    'files' => &$files,
                    'StorageNode' => &$StorageNode
                ]
            );
            unset($StorageGroup);
        }
        unset($StorageGroups);

        ob_start();
        echo '<select name="logtype" class="fog-select2" id="logToView">';
        foreach ($files as $nodename => &$filearray) {
            $first = true;
            foreach ((array)$filearray as $value => &$file) {
                if ($first) {
                    printf(
                        '<option disabled> ------- %s ------- </option>',
                        $nodename
                    );
                    $first = false;
                }
                printf(
                    '<option value="%s||%s"%s>%s</option>',
                    \Initiator::e(base64_encode($ip[$nodename])),
                    \Initiator::e($file),
                    (
                        isset($_POST['logtype']) && $value == $_POST['logtype'] ?
                        ' selected' :
                        ''
                    ),
                    \Initiator::e($value)
                );
                unset($file);
            }
            unset($filearray);
        }
        unset($files);
        echo '</select>';
        $logSelector = ob_get_clean();

        // Line Selector
        $vals = [
            10,
            25,
            50,
            100,
            250,
            500,
            1000
        ];
        ob_start();
        echo '<select name="n" class="form-control" id="linesToView">';
        foreach ((array)$vals as $i => &$value) {
            printf(
                '<option value="%s"%s>%s</option>',
                \Initiator::e($value),
                (
                    $value == filter_input(
                        INPUT_POST,
                        'n',
                        FILTER_SANITIZE_NUMBER_INT
                    ) ?
                    ' selected' :
                    ''
                ),
                \Initiator::e($value)
            );
            unset($value);
        }
        unset($vals);
        echo '</select>';
        $lineSelector = ob_get_clean();

        $this->title = _('FOG Log Viewer');

        // One self-relabelling toggle, not a pause/resume pair -- pausing the
        // live tail destroys nothing so Pause never belonged on the left, and
        // only ever one of the two was pressable. Labels are the shared
        // "Pause/Resume Reload" pair so this button reads identically to the
        // task and multicast panes. Sole right-side button, so primary.
        $buttons = self::makeReloadToggle(
            'logreload-toggle',
            'btn btn-primary float-end'
        );

        echo self::makeFormTag(
            '',
            'logviewer-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '<hr/>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'logToView',
            _('File')
        );
        echo $logSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'linesToView',
            _('Lines')
        );
        echo $lineSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'reverse',
            _('Reverse')
            . ' '
            . self::makeInput(
                '',
                'reverse',
                '',
                'checkbox',
                'reverse'
            )
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body" id="logsGoHere">';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Present the config screen.
     *
     * @return void
     */
    public function config()
    {
        self::$HookManager->processEvent('CONFIGURATION');

        $this->title = _('Configuration Import/Export');

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'import',
                _('Import Database')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'import',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'dbFile',
                    '',
                    'file',
                    'import',
                    '',
                    true
                )
            )
            . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                'dbfiledisp',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'exportdb',
            _('Export'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'importdb',
            _('Import'),
            'btn btn-warning float-start'
        );

        self::$HookManager->processEvent(
            'IMPORT_DB_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'import-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        );
        echo $this->_box(
            $this->title,
            $rendered,
            ['footer' => $buttons]
        );
        echo '</form>';
    }
    /**
     * Process import of config data
     *
     * @return void
     */
    public function configPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('IMPORT_POST');
        $Schema = self::getClass('Schema');
        $serverFault = false;
        try {
            if (isset($_POST['toExport'])) {
                $backup_name = 'fog_backup_'
                    . self::formatTime('now', 'Ymd_His');
                $tmpfile = '/tmp/' . $backup_name;
                $data = '';
                self::getClass('Mysqldump')->start($tmpfile);
                if (!file_exists($tmpfile) || !is_readable($tmpfile)) {
                    throw new \Exception(_('Could not read file from tmp folder.'));
                }
                $fh = fopen($tmpfile, 'rb');
                while (!feof($fh)) {
                    $data .= fread($fh, 4096);
                }
                fclose($fh);
                if (file_exists($tmpfile)) {
                    unlink($tmpfile);
                }
                $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                    [
                        'title' => _('Export Success'),
                        'msg' => _('Export Complete'),
                        '_filename' => $backup_name,
                        '_content' => $data
                    ]
                ));
            } else {
                if ($_FILES['dbFile']['error'] > 0) {
                    throw new UploadException($_FILES['dbFile']['error']);
                }

                // Basic size sanity (e.g., 10 MB cap; adjust as you like)
                if (!isset($_FILES['dbFile']['size']) || $_FILES['dbFile']['size'] > (10 * 1024 * 1024)) {
                    throw new \Exception(_('Uploaded file too large.'));
                }

                // Must be an uploaded file
                if (!is_uploaded_file($_FILES['dbFile']['tmp_name'])) {
                    throw new \Exception(_('Invalid upload.'));
                }

                // Move to a safe temp file we control
                $dest = sys_get_temp_dir() . DS . 'fog_import_' . bin2hex(random_bytes(8)) . '.sql';
                if (!move_uploaded_file($_FILES['dbFile']['tmp_name'], $dest)) {
                    throw new \Exception(_('Failed to move uploaded file.'));
                }

                // Quick sniff: must look like SQL dump (CREATE/INSERT or mysqldump header)
                $head = file_get_contents($dest, false, null, 0, 4096);
                if ($head === false || !preg_match('/(CREATE\s+TABLE|INSERT\s+INTO|mysqldump)/i', $head)) {
                    @unlink($dest);
                    throw new \Exception(_('Not a recognizable SQL dump.'));
                }

                // Now import
                try {
                    $result = self::getClass('Schema')->importdb($dest);
                } finally {
                    @unlink($dest); // cleanup regardless
                }
                if (true !== $result) {
                    $serverFault = true;
                    throw new \Exception(_('Import failed!'));
                }
                $code = HTTPResponseCodes::HTTP_ACCEPTED;
                $hook = 'CONFIG_IMPORT_SUCCESS';
                $msg = json_encode(
                    [
                        'msg' => _('Imported successfully!'),
                        'title' => _('Import Database Success')
                    ]
                );
            }
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'CONFIG_IMPORT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Import Database Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Settings list tester.
     *
     * @return void
     */
    public function getSettingsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $meta = $this->_settingsMeta();
        $needstobecheckbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
        $settingMan = self::getClass('SettingManager');
        $table = $settingMan->getTable();
        $dbcolumns = $settingMan->getColumns();
        $sqlStr = $settingMan->getQueryStr();
        $filterStr = $settingMan->getFilterStr();
        $totalStr = $settingMan->getTotalStr();
        $columns = [];
        foreach ($dbcolumns as $common => &$real) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            // Only the value field carries the rendered input column; binding
            // it to settingValue lets the global search match values too.
            if ($common !== 'value') {
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => 'inputValue',
                'formatter' => function ($d, $row) use (
                    $needstobenumeric,
                    $needstobecheckbox
                ) {
                    return self::_renderSettingInput(
                        $row,
                        $needstobenumeric,
                        $needstobecheckbox
                    );
                }
            ];
            unset($real);
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                'settingID',
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr
            )
        ));
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FOGConfigurationPage', 'FOGConfigurationPage');
