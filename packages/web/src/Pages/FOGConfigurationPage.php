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

namespace FOG\Pages;

use FOG\Audit\Audit;
use FOG\Audit\Retention;
use FOG\Auth\Authorization;
use FOG\Base\FOGBase;
use FOG\Base\FOGManagerController;
use FOG\Base\FOGPage;
use FOG\Boot\SecureBootState;
use FOG\Db\Mysqldump;
use FOG\Exception\UploadException;
use FOG\Items\APIToken;
use FOG\Items\Image;
use FOG\Items\Schema;
use FOG\Items\Setting;
use FOG\Items\User;
use FOG\Managers\APITokenManager;
use FOG\Managers\KeySequenceManager;
use FOG\Managers\OUIManager;
use FOG\Managers\SettingManager;
use FOG\Managers\UserManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
        // A card inside a tab pane carries no title of its own -- the tab is
        // the title -- and an empty header renders as a bare bar above the
        // content. Nothing else can suppress it, so an empty title with
        // nothing else to put up there means no header at all.
        $hasHeader = ($title !== '' || $help !== '' || $collapse);
        if ($hasHeader) {
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
        }
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
     * Ask the PKI helper a question, as root, through sudo.
     *
     * The helper is the privilege boundary: it holds every path, takes no path
     * from here, and implements five verbs and nothing else. This side decides
     * WHO may ask -- Authorization gates the sub on system.pki -- and the
     * helper decides what may be asked for. Same split as
     * service/nodecert.php and FOGPage::secureBootSign().
     *
     * @param array $args the verb and its arguments, each escaped separately.
     *
     * @return array{ok:bool,out:string} the helper's combined output.
     */
    private static function _pkiRun(array $args)
    {
        $helper = FOG_BASE_DIR . DS . 'bin' . DS . 'fog-pki-admin';
        if (!is_executable($helper)) {
            return ['ok' => false, 'out' => ''];
        }
        // escapeshellarg on every piece, including values this method chose
        // rather than received. $reqid is hex from random_bytes and the slot
        // names are literals, so nothing here is caller-influenced today --
        // quoting anyway costs nothing and means a later change that lets one
        // become caller-influenced does not silently become a shell injection.
        // FOG_BASE_DIR is installer-written and may contain a space, which
        // would split into two arguments and no longer match the sudoers rule.
        $cmd = 'sudo -n ' . escapeshellarg($helper);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg((string) $arg);
        }
        $out = [];
        $ret = 1;
        exec($cmd . ' 2>&1', $out, $ret);
        return ['ok' => 0 === $ret, 'out' => trim(implode("\n", $out))];
    }
    /**
     * This server's PKI as the helper reports it, or null when the helper is
     * not installed.
     *
     * Cached for the request: certificates() asks several questions of it and
     * a sudo round trip per card would be four processes to answer one page.
     *
     * @return array|null
     */
    private static function _pkiStatus()
    {
        static $status = false;
        if (false !== $status) {
            return $status;
        }
        $status = null;
        $res = self::_pkiRun(['status']);
        if ($res['ok']) {
            $decoded = json_decode($res['out'], true);
            // An empty answer decodes to null rather than throwing, so it has
            // to be tested for explicitly -- a null here would otherwise kill
            // the render on first property access and leave the page blank
            // with nothing to say why.
            if (is_array($decoded)) {
                $status = $decoded;
            }
        }
        return $status;
    }
    /**
     * The staging directory the helper and the web tier exchange files in.
     *
     * @return string '' when PKI administration is not configured here.
     */
    private static function _pkiStagingDir()
    {
        $dir = FOG_BASE_DIR . DS . 'pkiadmin-staging';
        return (is_dir($dir) && is_writable($dir)) ? $dir : '';
    }
    /**
     * One row of the certificate table, by slot.
     *
     * @param array|null $status the helper's report.
     * @param string     $slot   the slot name.
     *
     * @return array|null
     */
    private static function _pkiCert($status, $slot)
    {
        foreach ((array) ($status['certificates'] ?? []) as $cert) {
            if (($cert['slot'] ?? '') === $slot) {
                return $cert;
            }
        }
        return null;
    }
    /**
     * Show this server's certificate hierarchy, and change the parts of it a
     * browser may safely change.
     *
     * ONE CARD WITH TABS, the shape nearly every other management page in FOG
     * uses, built by the same FOGPage::tabFields() they use. It was five
     * stacked cards, so reading one certificate against another meant
     * scrolling past 2400px of prose to do it.
     *
     * Inside the first tab every certificate is a ROW: subject, what signed
     * it, expiry, fingerprint, download. The root's subject and fingerprint
     * used to be prose in one card and the imported root's prose in another,
     * while the six slots that were already tabulated carried neither.
     *
     * The two ALARMS stay outside the tab card, above it. A condition an
     * administrator must act on cannot live behind a tab they have to know to
     * click; "the web server can read your CA private key" is not a topic, it
     * is an interruption.
     *
     * NOT a collapsed card, which is what shipped first and had to be undone.
     * AdminLTE binds the collapse toggle with
     * `document.querySelectorAll(...).forEach(el => el.addEventListener(...))`
     * once at DOMContentLoaded -- it is not delegated -- and FOG replaces the
     * content by AJAX on every sidebar click. So a card that arrives by
     * navigation has no handler on its toggle, and one rendered
     * `collapsed-card` is content nobody can reach. It works only on a hard
     * reload, which is exactly what a static test snapshot is.
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
     * That failure is not hypothetical. The client zone lives under
     * $snapindir, and configureSnapins() used to chown the whole tree to the
     * web user at 775 -- after the certificates were created, so it silently
     * undid them.
     *
     * The key PATHS now come from the helper. They used to be built here from
     * the storage-node record as $sslpath/CA/.fogCA.key, and the zoned PKI
     * moved every one of them: only .fogCA.pem, .fogWebCA.pem and the client
     * keypair kept compat symlinks at the old names, and .fogCA.key was not
     * among them. So the check that this whole card exists for had been
     * testing paths that no longer exist, and passing every time (GH-1121).
     *
     * @return void
     */
    public function certificates()
    {
        $this->title = _('Certificates');
        $status = self::_pkiStatus();
        $mayEdit = Authorization::can('system.pki');

        // Both alarms, emitted above the tab card. A condition an
        // administrator must act on cannot live behind a tab they have to
        // know to click.
        $alarms = $this->_certificateKeyExposure($status);
        if (null === $status) {
            // Says which of the two it is. A page that simply showed less
            // would leave an administrator comparing it against the
            // documentation and finding nothing to explain the difference.
            $alarms .= $this->_box(
                _('Certificates'),
                '<p>' . _(
                    'The certificate management helper is not installed on this '
                    . 'server, so this page can only show what it can read for '
                    . 'itself. Re-run the installer to enable it. On a storage '
                    . 'node this is expected: a node has no CA of its own.'
                ) . '</p>',
                ['color' => 'warning']
            );
        }
        echo $alarms;

        // Each builder returns '' when it has nothing to show, so a tab is
        // never rendered empty -- on a storage node that leaves the one tab
        // that needs no helper.
        $tabs = [
            ['pki-chain', _('Certificates'), $this->_certificateChain($status)],
            [
                'pki-external-root',
                _('External root CA'),
                $this->_certificateExternalRoot($status, $mayEdit)
            ],
            [
                'pki-web-leaf',
                _('Web server certificate'),
                $this->_certificateWebLeaf($status, $mayEdit)
            ],
            [
                'pki-preferences',
                _('Install preferences'),
                $this->_certificatePreferences($status, $mayEdit)
            ],
            ['pki-own', _('Using your own PKI'), $this->_certificateOwnPki($status)]
        ];
        $tabData = [];
        foreach ($tabs as $tab) {
            if ('' === $tab[2]) {
                continue;
            }
            $tabData[] = [
                'name' => $tab[1],
                'id' => $tab[0],
                // The body is already built; the generator only has to emit
                // it. tabFields() calls this inside the pane it has opened.
                'generator' => function () use ($tab) {
                    echo $tab[2];
                }
            ];
        }
        // false, not the default -1: that would ask getClass() to resolve an
        // entity for node 'about', and there is none -- the tab hooks and
        // plugin injection want a record. Reports pass false for the same
        // reason.
        echo self::tabFields($tabData, false);
    }
    /**
     * The check that matters: can this web application read a private key it
     * must not be able to read.
     *
     * @param array|null $status the helper's report.
     *
     * @return string '' when nothing is exposed, which is the normal answer.
     */
    private function _certificateKeyExposure($status)
    {
        $keys = (array) ($status['private_keys'] ?? []);
        if (!$keys) {
            return '';
        }
        $exposed = [];
        foreach ($keys as $key) {
            $path = (string) ($key['path'] ?? '');
            // The client communication key is MEANT to be readable here --
            // FOGBase::certDecrypt() opens it on every fog-client handshake --
            // so flagging every readable key would report a correct install as
            // a breach. Same for the vhost key once the leaf is managed
            // outside FOG, which _hardenPkiPermissions() deliberately leaves
            // alone because an ACME renewal writes it as its hook's user.
            if (!empty($key['expect_readable'])) {
                continue;
            }
            if ($path && file_exists($path) && is_readable($path)) {
                $exposed[(string) ($key['label'] ?? $path)] = $path;
            }
        }
        if (!$exposed) {
            return '';
        }
        $warn = '<p>' . _(
            'The web application can read the following private keys. It '
            . 'should not be able to read any of them, and anything able to '
            . 'run code in this web application can copy them.'
        ) . '</p><ul>';
        foreach ($exposed as $label => $path) {
            $warn .= '<li><strong>' . \Initiator::e(_($label))
                . '</strong> &mdash; <code>' . \Initiator::e($path)
                . '</code></li>';
        }
        $warn .= '</ul><p>' . _(
            'Re-run the installer, which restricts them to root. If this '
            . 'persists, check that nothing else is widening permissions on '
            . 'the snapins directory afterward.'
        ) . '</p>';
        return $this->_box(
            _('Private keys are readable by the web server'),
            $warn,
            ['color' => 'danger']
        );
    }
    /**
     * The Expires cell: the date, and a badge when it is worth acting on.
     *
     * A certificate table that cannot answer "is anything about to break" is
     * only a list of names, so the badge is the point of the column and the
     * date is context for it.
     *
     * Rendered in UTC rather than through FOG_TZ_INFO. That setting is the
     * zone rows are STORED in and relabeling a value with it is how dates
     * elsewhere have gone wrong before; openssl's notAfter is already GMT, so
     * saying so is both correct and one fewer thing to get wrong. A value
     * strtotime cannot read is shown verbatim rather than dropped -- the raw
     * string is still the answer to the question.
     *
     * @param array $cert one entry of the helper's certificates list.
     *
     * @return string
     */
    private static function _certExpiry(array $cert)
    {
        $raw = (string) ($cert['not_after'] ?? '');
        $ts = '' !== $raw ? strtotime($raw) : false;
        if (false === $ts) {
            return '<small>' . \Initiator::e($raw) . '</small>';
        }
        $out = '<small class="text-nowrap">'
            . \Initiator::e(gmdate('Y-m-d', $ts)) . '</small>';
        $days = (int) floor(($ts - time()) / 86400);
        if ($days < 0) {
            $out .= '<br><span class="badge text-bg-danger">'
                . _('Expired') . '</span>';
        } elseif ($days <= 30) {
            // 30 days is the window every ACME client renews inside, so a
            // certificate still amber here is one nothing is renewing.
            $out .= '<br><span class="badge text-bg-warning">' . sprintf(
                // One day left is precisely when somebody reads this badge,
                // so it is the case worth getting right.
                /* translators: %d is a number of days */
                ngettext('%d day left', '%d days left', $days),
                $days
            ) . '</span>';
        }
        return $out;
    }
    /**
     * Every certificate this server holds, one row each.
     *
     * Every file here is public: the root is already published as
     * ca.cert.der, the vhost certificate is handed to anyone who connects,
     * and the rest are what a client needs to build a chain. They are behind
     * settings.view rather than a plain link only because the page is, and
     * because the web tier cannot read most of them off disk -- pki/web/ca
     * and pki/web/leaf are 0700 root:root, so the helper fetches them.
     *
     * The fingerprint is a column rather than a card because comparing one
     * against a client's trust store is the reason anybody opens this page,
     * and it was previously shown for two of the eight slots.
     *
     * @param array|null $status the helper's report.
     *
     * @return string '' when there is nothing to tabulate.
     */
    private function _certificateChain($status)
    {
        if (null === $status) {
            return '';
        }
        $labels = [
            'root' => [
                _('Root'),
                _('The trust anchor, published as ca.cert.der. Every fog-client pins this one.')
            ],
            'webca' => [
                _('Web CA'),
                _('Signs the web server certificate and every storage node certificate.')
            ],
            'webchain' => [
                _('Web trust chain'),
                _('Web CA plus the root that anchors it -- what a client needs to verify this server.')
            ],
            'anchor' => [
                _('Trust anchor bundle'),
                _('What this server itself trusts: FOG\'s root, plus any root imported below.')
            ],
            'vhost' => [
                _('Web server certificate'),
                _('What the browser is shown. Replaced by an ACME renewal where one is configured.')
            ],
            'commleaf' => [
                _('Client communication certificate'),
                _('The public half of the keypair fog-client encrypts its check-in to.')
            ],
            'sbca' => [
                _('Secure Boot CA'),
                _('Signs the FOS kernel signing certificate. Not a web certificate.')
            ],
            'externalroot' => [
                _('Imported root'),
                _('A root CA uploaded on this page. Absent unless one was imported.')
            ]
        ];
        $rows = '';
        foreach ($labels as $slot => $meta) {
            $cert = self::_pkiCert($status, $slot);
            if (!$cert || empty($cert['present'])) {
                continue;
            }
            $count = (int) ($cert['count'] ?? 1);
            $sub = '<td><code>' . \Initiator::e($cert['subject']) . '</code>';
            // Issuer, not as its own column: a second distinguished name that
            // wide would push the fingerprint off the table on any laptop, and
            // what the reader wants from it is which row above signed this one.
            if (!empty($cert['self_signed'])) {
                $sub .= '<br><small class="text-muted">'
                    . _('Self-signed') . '</small>';
            } elseif (!empty($cert['issuer'])) {
                $sub .= '<br><small class="text-muted">' . sprintf(
                    _('Issued by %s'),
                    \Initiator::e($cert['issuer'])
                ) . '</small>';
            }
            if ($count > 1) {
                $sub .= '<br><small class="text-muted">' . sprintf(
                    _('%d certificates in this file'),
                    $count
                ) . '</small>';
            }
            $sub .= '</td>';
            $rows .= '<tr><td><strong>' . \Initiator::e($meta[0]) . '</strong>'
                . '<br><small class="text-muted">' . \Initiator::e($meta[1])
                . '</small></td>'
                . $sub
                . '<td>' . self::_certExpiry($cert) . '</td>'
                // user-select-all so one click takes the whole fingerprint --
                // it is 95 characters and it exists to be pasted somewhere
                // else. break-all rather than the text-break utility, which is
                // word-wrap and will not break a colon-separated hex run.
                . '<td><small class="font-monospace user-select-all"'
                . ' style="word-break:break-all">'
                . \Initiator::e($cert['sha256'] ?? '') . '</small></td>'
                . '<td class="text-end text-nowrap">'
                . '<a class="btn btn-sm btn-secondary" href="'
                . \Initiator::e(
                    '?node=about&sub=certificatedownload&slot=' . $slot
                ) . '">' . _('Download') . '</a></td></tr>';
        }
        if ('' === $rows) {
            return '';
        }
        $body = '<p class="form-text">' . _(
            'FOG uses certificates for three unrelated jobs: the web server, '
            . 'the encrypted fog-client check-in, and the signature on the FOS '
            . 'kernels. They are issued by separate CAs beneath one anchor, so '
            . 'replacing any one of them leaves the other two alone.'
        ) . '</p>';
        $body .= '<div class="table-responsive">'
            . '<table class="table table-sm table-striped align-middle">';
        $body .= '<thead><tr><th>' . _('Certificate') . '</th><th>' . _('Subject')
            . '</th><th>' . _('Expires') . '</th><th>' . _('SHA-256')
            . '</th><th></th></tr></thead>';
        $body .= '<tbody>' . $rows . '</tbody></table></div>';
        $body .= '<p class="form-text">' . _(
            'Each of these is a public certificate. Downloading one is how you '
            . 'add this server to another machine\'s trust store, hand an '
            . 'auditor the chain, or check what a client is actually being '
            . 'offered; the SHA-256 is what to compare against a client\'s '
            . 'trust store when working out why one stopped authenticating. '
            . 'Private keys are deliberately absent -- nothing on this page can '
            . 'read one, and nothing on it can hand one out.'
        ) . '</p>';
        return $this->_box('', $body);
    }
    /**
     * Import and remove an external root CA.
     *
     * What is imported is a ROW in the table above, so this card is only the
     * control. It used to repeat the subject, the fingerprint and the expiry
     * as prose, which is the same three facts in a second shape and the thing
     * that made the page a train of cards.
     *
     * The narrow case that --ca-root cannot express on its own: a corporate
     * or step-ca root that FOG issues nothing from and nothing chains to, but
     * which this server must accept. validateExternalCA() only runs when all
     * THREE of --ca-cert/--ca-key/--ca-root were given, so before this there
     * was no way to say "just trust this" short of editing the host trust
     * store by hand -- which the next installer run would not know about.
     *
     * @param array|null $status  the helper's report.
     * @param bool       $mayEdit does the caller hold system.pki.
     *
     * @return string '' when there is no helper to import through.
     */
    private function _certificateExternalRoot($status, $mayEdit)
    {
        if (null === $status) {
            return '';
        }
        $cert = self::_pkiCert($status, 'externalroot');
        $have = $cert && !empty($cert['present']);
        $body = '<p class="form-text">' . _(
            'Upload a root CA certificate to have this server trust it. It is '
            . 'added to the host\'s own trust store, so every HTTPS call this '
            . 'server makes will accept a certificate issued beneath it, and '
            . 'it is re-applied on every later installer run.'
        ) . '</p>';
        $body .= '<p class="form-text">' . _(
            'This does not change what FOG issues, and it does not change what '
            . 'fog-client pins. Only a self-signed CA certificate is accepted: '
            . 'an intermediate in the same file is dropped rather than trusted, '
            . 'because anchoring an intermediate would trust it as a root.'
        ) . '</p>';
        $body .= '<p class="form-text">' . ($have
            ? _('One is imported. It is the "Imported root" row in the table above.')
            : _('No external root is imported on this server.')) . '</p>';
        $store = (array) ($status['trust_store'] ?? []);
        if (empty($store['available'])) {
            $body .= '<p class="text-warning">' . _(
                'This host has no system trust store that FOG recognizes, so '
                . 'an imported root is recorded and re-applied but never '
                . 'reaches the host\'s own HTTPS clients.'
            ) . '</p>';
        }
        if (!$mayEdit) {
            return $this->_box('', $body);
        }
        $body .= '<label class="form-label" for="pki-root-file">'
            . _('Root CA certificate (PEM)') . '</label>';
        $body .= self::makeInput(
            'form-control',
            'rootca',
            '',
            'file',
            'pki-root-file',
            '',
            true,
            false,
            -1,
            -1,
            'accept=".pem,.crt,.cer"'
        );
        // The action rides in the form rather than in the button, so
        // processForm's FormData carries it with the file in one POST --
        // certificatesPost() serves three actions and has to be told which.
        $body .= self::makeInput('', 'action', '', 'hidden', 'pki-action', 'importRoot');
        $buttons = self::makeButton(
            'pki-import-root',
            _('Import'),
            'btn btn-primary float-end'
        );
        if ($have) {
            // Destructive, so left, per the button convention. Removing an
            // imported root is genuinely undoable -- the file is the admin's
            // and can be uploaded again -- but it stops this server trusting
            // whatever that CA issued, which is worth the deliberate travel.
            $buttons .= self::makeButton(
                'pki-clear-root',
                _('Remove imported root'),
                'btn btn-danger float-start'
            );
        }
        // Wrapped in its own form: the upload needs multipart, and this tab
        // is the only thing on the page that posts a file.
        return self::makeFormTag(
            '',
            'pki-root-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        )
            . $this->_box('', $body, ['footer' => $buttons])
            . '</form>';
    }
    /**
     * Bring your own web server certificate: adopt one already on the server,
     * or upload one.
     *
     * Three routes, in the order they are offered, and the order is the point.
     * The first two never send a private key to this application at all; the
     * third does, for one request, because a wildcard an administrator already
     * holds is the common case and refusing it sends them back to the command
     * line for something every other appliance does in a form.
     *
     * What this page must never accept is a CA key. The helper enforces it by
     * refusing a leaf carrying CA:TRUE, which is what makes "no CA private key
     * reaches this channel" a checked property rather than a promise. See
     * ADR 0036's 2026-09-02 amendment for the reasoning and for the blast-radius
     * difference that justifies treating a leaf differently at all.
     *
     * @param array|null $status  the helper's report.
     * @param bool       $mayEdit does the caller hold system.pki.
     *
     * @return string '' when there is no helper to drive.
     */
    private function _certificateWebLeaf($status, $mayEdit)
    {
        if (null === $status) {
            return '';
        }
        $custom = (array) ($status['custom_pki'] ?? []);
        $pending = (array) ($status['pending_csr'] ?? []);
        $dir = (string) ($custom['dir'] ?? '');
        $managed = !empty($status['externally_managed_leaf']);

        $body = '<p class="form-text">' . _(
            'FOG issues its own web server certificate. If you would rather it '
            . 'served one of yours -- from an ACME client, your corporate CA, or '
            . 'a purchased certificate -- there are three ways to say so, and the '
            . 'first two never send the private key to this web application.'
        ) . '</p>';

        if ($managed) {
            $body .= '<p class="text-info">' . _(
                'This server is already serving a certificate managed outside '
                . 'FOG, so FOG will not reissue or re-key it. That is derived '
                . 'from where the certificate actually is, not from a setting.'
            ) . '</p>';
        }

        // Route one. Reported before it is offered, because "there is a pair
        // waiting here" and "it would be refused for this reason" are both
        // things an administrator wants before clicking, not after.
        $body .= '<h5>' . _('1. A certificate already on this server') . '</h5>';
        $body .= '<p class="form-text">' . sprintf(
            _(
                'Drop %s and %s into %s and FOG adopts them -- no setting to '
                . 'edit. Add %s as well if your CA issued intermediates. The '
                . 'installer does the same thing on its next run, so this button '
                . 'only saves you the wait.'
            ),
            '<code>web-leaf.pem</code>',
            '<code>web-leaf.key</code>',
            '<code>' . \Initiator::e($dir) . '</code>',
            '<code>web-leaf-chain.pem</code>'
        ) . '</p>';

        if (empty($custom['present'])) {
            $body .= '<p class="form-text">' . _(
                'Nothing is in that directory yet.'
            ) . '</p>';
        } elseif (empty($custom['pair_ok'])) {
            $body .= '<p class="text-warning">' . _(
                'There is a certificate in that directory, but no private key '
                . 'that matches it. Both files are required and they have to be '
                . 'a genuine pair, so FOG is leaving its own certificate in '
                . 'place -- adopting a certificate the web server cannot start '
                . 'with would be the worse outcome.'
            ) . '</p>';
        } else {
            $body .= '<p class="text-success">' . sprintf(
                _('A usable pair is waiting: %s, valid until %s.'),
                '<strong>' . \Initiator::e((string) $custom['subject']) . '</strong>',
                \Initiator::e((string) $custom['not_after'])
            ) . '</p>';
            if (empty($custom['chain_verifies'])) {
                $body .= '<p class="text-warning">' . _(
                    'No trust path builds for it yet, so adopting it would be '
                    . 'refused. Supply the intermediates, and import the issuing '
                    . 'root on the External root CA tab if it is not a public CA.'
                ) . '</p>';
                if (!empty($custom['supplied_root'])) {
                    $body .= '<p class="form-text">' . sprintf(
                        _(
                            'A root was supplied alongside it (%s). FOG does not '
                            . 'trust a root just because it arrived beside a '
                            . 'certificate -- import it deliberately on the '
                            . 'External root CA tab, then adopt.'
                        ),
                        '<strong>' . \Initiator::e((string) $custom['supplied_root']) . '</strong>'
                    ) . '</p>';
                }
            }
        }

        // Route two. The key is generated by the helper, as root, and never
        // leaves the server: what travels is the request on the way out and
        // the certificate on the way back, both public. Generating is a POST
        // (it makes a new key, so it sits behind system.pki); the download is
        // a plain GET through the export slot, like every other download here.
        $body .= '<h5>' . _('2. A certificate signing request') . '</h5>';
        $body .= '<p class="form-text">' . _(
            'For sites whose policy is that a private key never moves. FOG '
            . 'generates the key on this server and it stays there: download '
            . 'the request, have your CA sign it, and upload the certificate '
            . 'that comes back. The request carries the same names FOG puts in '
            . 'its own certificate, so the result serves netboot as well as '
            . 'browsers.'
        ) . '</p>';
        if (!empty($pending['present'])) {
            $body .= '<p class="text-success">' . sprintf(
                _('A request is pending since %s for %s.'),
                \Initiator::e((string) $pending['since']),
                '<strong>' . \Initiator::e((string) $pending['subject']) . '</strong>'
            ) . '</p>';
            if (!empty($pending['names'])) {
                $body .= '<p class="form-text"><small class="font-monospace">'
                    . \Initiator::e((string) $pending['names'])
                    . '</small></p>';
            }
            $body .= '<p><a class="btn btn-sm btn-secondary" href="'
                . \Initiator::e('?node=about&sub=certificatedownload&slot=csr')
                . '">' . _('Download the request') . '</a></p>';
            $body .= '<p class="form-text">' . _(
                'Generating another request makes a new key and replaces this '
                . 'one. A certificate issued from this request would then be '
                . 'refused, so only do that if this request is not going to '
                . 'be signed.'
            ) . '</p>';
        } else {
            $body .= '<p class="form-text">' . _(
                'No request is pending.'
            ) . '</p>';
        }

        // The details, offered rather than imposed. FOG's derived names and its
        // own O/OU are right for most servers, and that is what an untouched
        // form asks for. They are not right for every CA: an internal issuing
        // policy commonly requires the organization's own O, OU and locality
        // and refuses a request carrying somebody else's -- which would send an
        // administrator back to the command line for the one route that exists
        // to keep them off it.
        //
        // Collapsed by default, because reading six fields to discover you did
        // not need them is a worse first impression than a button.
        if ($mayEdit) {
            $body .= '<details class="mb-2"><summary>'
                . _('Change what the request asks for (optional)')
                . '</summary><div class="mt-2">';
            $body .= '<p class="form-text">' . _(
                'Left as it is, FOG asks for the names it would put in its own '
                . 'certificate, under its own organization. Fill any of these in '
                . 'and the request carries yours instead. A field left empty is '
                . 'left out of the request rather than filled in with FOG\'s.'
            ) . '</p>';
            $dn = [
                'csrcn' => [_('Common name (CN)'), self::_pkiDerivedCn($status), true],
                'csro' => [_('Organization (O)'), '', false],
                'csrou' => [_('Organizational unit (OU)'), '', false],
                'csrl' => [_('City or locality (L)'), '', false],
                'csrst' => [_('State or province (ST)'), '', false],
                'csrc' => [_('Country code (C), two letters'), '', false]
            ];
            foreach ($dn as $field => $meta) {
                $body .= '<label class="form-label" for="pki-' . $field . '">'
                    . \Initiator::e($meta[0]) . '</label>';
                $body .= self::makeInput(
                    'form-control',
                    $field,
                    (string) $meta[1],
                    'text',
                    'pki-' . $field,
                    $meta[2] ? '' : _('optional'),
                    false,
                    false,
                    -1,
                    -1,
                    'maxlength="64"'
                );
            }
            $body .= '<label class="form-label" for="pki-csrnames">'
                . _('Names the certificate must cover, one per line')
                . '</label>';
            $body .= '<textarea class="form-control" id="pki-csrnames"'
                . ' name="csrnames" rows="5" spellcheck="false">'
                . \Initiator::e(self::_pkiDerivedNames($status))
                . '</textarea>';
            $body .= '<p class="form-text">' . _(
                'Pre-filled with what FOG would ask for. Prefix an address with '
                . 'IP: if it is not obvious. Removing this server\'s own name is '
                . 'allowed and warned about: netboot and FOG\'s own calls to '
                . 'itself address it by a name the certificate carries.'
            ) . '</p>';
            $body .= '</div></details>';
        }

        $bodyLeaf = '<h5>' . _('3. Upload a certificate and its key') . '</h5>';
        $bodyLeaf .= '<p class="form-text">' . _(
            'A PEM certificate with its key, or one PKCS#12 (.p12/.pfx) file '
            . 'carrying both. A full chain is fine -- put the leaf first -- and '
            . 'the intermediates are separated out for you.'
        ) . '</p>';
        $bodyLeaf .= '<p class="text-warning">' . _(
            'This is the one route that sends a private key through this web '
            . 'application. It is written to disk by root and never kept here, '
            . 'but it does pass through for the length of one request. If the '
            . 'certificate is a wildcard it covers hosts other than this one, so '
            . 'prefer route 1 or 2 where your policy allows.'
        ) . '</p>';
        $bodyLeaf .= '<p class="form-text">' . _(
            'A CA certificate is refused. Replacing FOG\'s own root is a '
            . 'migration rather than a setting -- see the Using your own PKI tab.'
        ) . '</p>';

        if (!$mayEdit) {
            return $this->_box('', $body) . $this->_box('', $bodyLeaf);
        }

        // Routes one and two share a form and a footer; route three has its
        // own, because its upload fields must not travel with a request to
        // install a signed certificate, and nested forms are not a thing.
        $buttons = '';
        if (!empty($custom['pair_ok'])) {
            $buttons .= self::makeButton(
                'pki-adopt-leaf',
                _('Adopt the certificate in that directory'),
                'btn btn-secondary float-end'
            );
        }
        $buttons .= self::makeButton(
            'pki-make-csr',
            empty($pending['present'])
                ? _('Generate a signing request')
                : _('Generate a new signing request'),
            'btn btn-secondary float-end'
        );
        if (!empty($pending['present'])) {
            $bodyLeaf .= '<label class="form-label" for="pki-csr-cert">'
                . _('The signed certificate, or full chain, leaf first (PEM)')
                . '</label>';
            $bodyLeaf .= self::makeInput(
                'form-control',
                'csrcert',
                '',
                'file',
                'pki-csr-cert',
                '',
                false,
                false,
                -1,
                -1,
                'accept=".pem,.crt,.cer"'
            );
            $bodyLeaf .= '<label class="form-label" for="pki-csr-chain">'
                . _('Intermediates, if they are in their own file (PEM, optional)')
                . '</label>';
            $bodyLeaf .= self::makeInput(
                'form-control',
                'csrchain',
                '',
                'file',
                'pki-csr-chain',
                '',
                false,
                false,
                -1,
                -1,
                'accept=".pem,.crt,.cer"'
            );
            $buttons .= self::makeButton(
                'pki-install-csr',
                _('Install the signed certificate'),
                'btn btn-primary float-end'
            );
        }
        $bodyLeaf .= self::makeInput('', 'action', '', 'hidden', 'pki-csr-action', 'installLeafCert');
        $csrBox = self::makeFormTag(
            '',
            'pki-csr-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        )
            . $this->_box('', $body, ['footer' => $buttons])
            . '</form>';

        $buttons = '';
        $bodyLeaf .= '<hr>';
        $bodyLeaf .= '<label class="form-label" for="pki-leaf-file">'
            . _('Certificate, or full chain, leaf first (PEM)') . '</label>';
        $bodyLeaf .= self::makeInput(
            'form-control',
            'leafcert',
            '',
            'file',
            'pki-leaf-file',
            '',
            false,
            false,
            -1,
            -1,
            'accept=".pem,.crt,.cer"'
        );
        $bodyLeaf .= '<label class="form-label" for="pki-leaf-key">'
            . _('Private key (PEM)') . '</label>';
        $bodyLeaf .= self::makeInput(
            'form-control',
            'leafkey',
            '',
            'file',
            'pki-leaf-key',
            '',
            false,
            false,
            -1,
            -1,
            'accept=".pem,.key"'
        );
        $bodyLeaf .= '<label class="form-label" for="pki-leaf-chain">'
            . _('Intermediates, if they are in their own file (PEM, optional)')
            . '</label>';
        $bodyLeaf .= self::makeInput(
            'form-control',
            'leafchain',
            '',
            'file',
            'pki-leaf-chain',
            '',
            false,
            false,
            -1,
            -1,
            'accept=".pem,.crt,.cer"'
        );
        $bodyLeaf .= '<label class="form-label" for="pki-leaf-p12">'
            . _('Or one PKCS#12 file carrying all of it (.p12/.pfx)') . '</label>';
        $bodyLeaf .= self::makeInput(
            'form-control',
            'leafp12',
            '',
            'file',
            'pki-leaf-p12',
            '',
            false,
            false,
            -1,
            -1,
            'accept=".p12,.pfx"'
        );
        $bodyLeaf .= '<label class="form-label" for="pki-leaf-pass">'
            . _('Passphrase, if the PKCS#12 file or the key has one') . '</label>';
        $bodyLeaf .= self::makeInput(
            'form-control',
            'leafpass',
            '',
            'password',
            'pki-leaf-pass',
            '',
            false,
            false,
            -1,
            -1,
            'autocomplete="new-password"'
        );
        $bodyLeaf .= self::makeInput('', 'action', '', 'hidden', 'pki-leaf-action', 'importLeaf');
        $buttons .= self::makeButton(
            'pki-import-leaf',
            _('Upload and use it'),
            'btn btn-primary float-end'
        );

        return $csrBox . self::makeFormTag(
            '',
            'pki-leaf-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        )
            . $this->_box('', $bodyLeaf, ['footer' => $buttons])
            . '</form>';
    }
    /**
     * The three install preferences that decide what FOG does to the web
     * certificate on its next run.
     *
     * A row each: switch, what it is, what turning it on costs. Each one
     * states its consequence beside the control, because that is the whole
     * difference between setting this here and setting it on a command line:
     * an installer flag is read back before it runs, and a checkbox is not.
     *
     * They are PREFERENCES, not switches -- nothing on this page rewrites the
     * vhost or rebuilds iPXE. Saying so at the point of change is the only
     * place an administrator will see it before wondering why nothing
     * happened.
     *
     * @param array|null $status  the helper's report.
     * @param bool       $mayEdit does the caller hold system.pki.
     *
     * @return string '' when there is no helper to record a preference.
     */
    private function _certificatePreferences($status, $mayEdit)
    {
        if (null === $status) {
            return '';
        }
        $prefs = (array) ($status['preferences'] ?? []);
        $meta = [
            'PKI_web_cert_publicly_trusted' => [
                _('The web certificate chains to a public CA'),
                _(
                    'Turn this on for a Let\'s Encrypt or purchased '
                    . 'certificate. It stops FOG re-issuing the leaf, stops it '
                    . 'locking the private key to root -- which would break the '
                    . 'renewal that writes it -- and steers netboot to HTTPS '
                    . 'with no iPXE rebuild, because upstream iPXE '
                    . 'cross-certifies public CAs already.'
                )
            ],
            'WEB_https_redirect' => [
                _('Redirect HTTP to HTTPS'),
                _(
                    'Off by default on purpose. Trust in FOG\'s CA reaches a '
                    . 'client when fog-client installs it there, so on a server '
                    . 'whose clients are not enrolled yet a forced redirect '
                    . 'breaks exactly the machines that cannot fix themselves. '
                    . 'Turn it on once trust is in place. It does NOT affect '
                    . 'netboot -- that is the separate setting below. Turning '
                    . 'it on is not fully reversible: it also sends HSTS, and a '
                    . 'browser that has seen that header refuses plain HTTP to '
                    . 'this host for six months out of its own cache, whatever '
                    . 'this server later says.'
                )
            ],
            'BOOT_rebuild_ipxe_with_my_ca' => [
                _('Rebuild iPXE with this server\'s CA embedded'),
                _(
                    'Only needed for HTTPS netboot behind a PRIVATE CA, and it '
                    . 'costs a 10-25 minute build on every install. It also '
                    . 'makes Secure Boot harder, not easier: a binary carrying '
                    . 'a private CA is not upstream\'s signed one, so the chain '
                    . 'has to hand off to a MOK-signed FOG build and the MOK '
                    . 'must be enrolled first.'
                )
            ]
        ];
        // The netboot transport, rendered after the three switches and inside
        // the same card, because it is not a fourth independent preference --
        // it is the same decision the other two steer. Kept out of $meta
        // because its domain is http|https, so it is a select rather than a
        // switch, and a checkbox would have to invent which way is "on".
        $proto = 'https' === (string) ($prefs['BOOT_url_proto'] ?? 'http')
            ? 'https' : 'http';
        $protoRow = '<tr><td class="text-nowrap">';
        $protoRow .= '<select class="form-select form-select-sm pki-pref-select"'
            . ' id="BOOT_url_proto" data-key="BOOT_url_proto"'
            . ' data-action="' . \Initiator::e($this->formAction) . '"'
            . ($mayEdit ? '' : ' disabled') . '>';
        foreach (['http' => _('http'), 'https' => _('https')] as $val => $label) {
            $protoRow .= '<option value="' . \Initiator::e($val) . '"'
                . ($proto === $val ? ' selected' : '') . '>'
                . \Initiator::e($label) . '</option>';
        }
        $protoRow .= '</select></td>';
        $protoRow .= '<td><label class="form-check-label" for="BOOT_url_proto">'
            . '<strong>' . _('Netboot fetches boot.php over') . '</strong>'
            . '<br><code class="small">BOOT_url_proto</code></label></td>';
        $protoRow .= '<td><small class="text-muted">' . _(
            'Separate from the HTTP redirect above, which never applies to the '
            . 'paths a bootloader fetches for itself. Choosing https here also '
            . 'FORCES it: the installer stops deriving the transport and keeps '
            . 'what you set. If iPXE cannot validate the certificate this '
            . 'server serves, every netboot stops at boot.php and nothing '
            . 'server-side says why -- so decide it together with the two '
            . 'settings above, not on its own.'
        ) . '</small></td></tr>';

        $rows = '';
        foreach ($meta as $key => $text) {
            $on = 'yes' === (string) ($prefs[$key] ?? 'no');
            $rows .= '<tr><td class="text-nowrap">';
            $rows .= '<div class="form-check form-switch mb-0">';
            // data-action rather than reading it off the upload form: that
            // form is only rendered when an external root can be imported, so
            // the switches would have lost their POST target on a server where
            // it is absent.
            $rows .= '<input class="form-check-input pki-pref" type="checkbox"'
                . ' id="' . \Initiator::e($key) . '"'
                . ' data-key="' . \Initiator::e($key) . '"'
                . ' data-action="' . \Initiator::e($this->formAction) . '"'
                . ($on ? ' checked' : '')
                . ($mayEdit ? '' : ' disabled')
                . '>';
            $rows .= '</div></td>';
            // The label still points at the checkbox, so the setting name is
            // the click target it always was -- it has just moved a cell.
            $rows .= '<td><label class="form-check-label" for="'
                . \Initiator::e($key) . '"><strong>'
                . \Initiator::e($text[0]) . '</strong>'
                . '<br><code class="small">' . \Initiator::e($key)
                . '</code></label></td>';
            $rows .= '<td><small class="text-muted">'
                . \Initiator::e($text[1]) . '</small></td></tr>';
        }
        $body = '<p class="form-text">' . _(
            'These decide what the NEXT installer run does. Nothing here takes '
            . 'effect until the installer runs again -- this page records the '
            . 'preference, it does not rewrite the web server configuration or '
            . 'reissue a certificate.'
        ) . '</p>';
        $body .= '<div class="table-responsive">'
            . '<table class="table table-sm align-middle">';
        $body .= '<thead><tr><th></th><th>' . _('Setting') . '</th><th>'
            . _('What it does') . '</th></tr></thead>';
        $body .= '<tbody>' . $rows . $protoRow . '</tbody></table></div>';
        // The two conditions ADR 0036's rejected alternative named, met here
        // rather than in the helper: the transport is never offered alone, and
        // the cost is stated at the point of change.
        $body .= '<div class="alert alert-warning" role="alert">';
        $body .= '<strong>' . _('HTTPS netboot is one decision, not three.')
            . '</strong> ';
        $body .= _(
            'iPXE validates TLS strictly, has no insecure mode, and will not '
            . 'chain a private CA it has never been given. So https netboot '
            . 'works only if the certificate is publicly trusted, or iPXE has '
            . 'been rebuilt with this server\'s CA embedded.'
        );
        $body .= '<ul class="mb-0 mt-2">';
        $body .= '<li>' . _(
            'Under https, the netboot URL has to address this server by a name '
            . 'the served certificate actually carries, so FOG_WEB_HOST becomes '
            . 'a record rather than a control -- rewritten on every run, and an '
            . 'edit through FOG Settings will not survive.'
        ) . '</li>';
        $body .= '<li>' . _(
            'If the served certificate carries no name the netboot URL could '
            . 'use, the install STOPS and prints the names it does carry, '
            . 'rather than writing a default.ipxe that cannot boot. Re-issue '
            . 'for the name you need, or set this back to http.'
        ) . '</li>';
        $body .= '<li>' . _(
            'Machines that PXE boot cannot fix this themselves. That is the '
            . 'whole reason this is a deliberate setting rather than a default.'
        ) . '</li>';
        $body .= '</ul></div>';
        $body .= '<p class="form-text">' . sprintf(
            '%s <a href="%s" target="_blank" rel="noopener">%s</a>.',
            _('A worked example of the first one, end to end:'),
            // NO /1.6/ segment. fog-docs carries two trees and they map to
            // two different URL shapes: docs/1.6/... publishes under
            // /en/latest/1.6/... (pki-zones, bringing-your-own-ca), while
            // docs/kb/... is shared across versions and publishes under
            // /en/latest/kb/... . This how-to is in the shared tree, so the
            // versioned form 404s -- which is what shipped first.
            \Initiator::e(
                'https://docs.fogproject.org/en/latest/kb/how-tos/lets-encrypt-setup'
            ),
            _('Let\'s Encrypt with FOG')
        ) . '</p>';
        if (!empty($status['externally_managed_leaf'])) {
            $body .= '<p class="text-info">' . _(
                'This server\'s web certificate already resolves outside FOG\'s '
                . 'own PKI, so FOG treats it as managed elsewhere and will not '
                . 'reissue it or lock its key down, whatever these say. That is '
                . 'derived from where the certificate actually is, not from a '
                . 'setting, so the two cannot disagree.'
            ) . '</p>';
        }
        return $this->_box('', $body);
    }
    /**
     * Replacing FOG's own PKI: what it costs, and the command that does it.
     *
     * Last tab. It is reference material for a migration nobody does twice,
     * and stacked in line it was the longest thing on a page whose job is to
     * say what this server's certificates are right now.
     *
     * Deliberately NOT a button. Rotating the root invalidates every
     * fog-client enrollment on the estate at once, and the recovery is to
     * re-pin every client -- so it belongs somewhere it can be read back
     * before it runs. The page composes the invocation; the administrator
     * runs it (GH-1121).
     *
     * @param array|null $status the helper's report.
     *
     * @return string
     */
    private function _certificateOwnPki($status)
    {
        $body = '<p>' . _(
            'To have your own CA issue FOG\'s web certificates, import the CA '
            . 'and its key by re-running the installer. That replaces the CA '
            . 'that signs the web server certificate and the storage node '
            . 'certificates -- and only that. The root fog-client pins is left '
            . 'alone, so no client re-enrolls.'
        ) . '</p>';
        $body .= '<pre>' . \Initiator::e(
            './installfog.sh --external-ca \\
    --web-ca-cert /path/to/intermediate.pem \\
    --web-ca-key  /path/to/intermediate.key \\
    --web-ca-root /path/to/root.pem'
        ) . '</pre>';
        $body .= '<p><strong>' . _('Not offered here, on purpose')
            . '</strong></p>';
        $body .= '<p>' . _(
            'Replacing the root itself -- so that FOG\'s anchor becomes an '
            . 'intermediate of yours -- is a migration rather than a setting. '
            . 'It changes the certificate every registered fog-client pins, so '
            . 'every one of them stops authenticating until it is re-pinned. '
            . 'That is not something a web form should be able to do in one '
            . 'click, so it is not on this page.'
        ) . '</p>';
        if ($status && empty($status['root_key_on_server'])) {
            $body .= '<p><strong>' . _('The CA private key is not on this server')
                . '</strong></p>';
            $body .= '<p>' . _(
                'That is the recommended state. Restore it only to issue a new '
                . 'intermediate or a certificate for a new storage node, then '
                . 'move it back.'
            ) . '</p>';
        } elseif ($status) {
            $body .= '<p><strong>' . _('The CA private key is on this server')
                . '</strong></p>';
            $body .= '<p>' . _(
                'It is restricted to root, which protects it from a compromise '
                . 'of this web application but not from a compromise of the '
                . 'machine. Moving it to a vault is a separate step:'
            ) . '</p>';
            $body .= '<pre>' . \Initiator::e(
                FOG_BASE_DIR . '/bin/fog-offline-ca-key /mnt/vault'
            ) . '</pre>';
            $body .= '<p>' . _(
                'Nothing needs it day to day. Restore it only to issue a new '
                . 'intermediate, or a certificate for a new storage node.'
            ) . '</p>';
        }
        return $this->_box('', $body);
    }
    /**
     * Hand back one public certificate as a file.
     *
     * The slot name is passed straight through to the helper, which holds the
     * allowlist -- so this method never learns a path and a slot it does not
     * recognize is refused on the far side of sudo rather than here. Gated on
     * settings.view: the page is, and every one of these is public.
     *
     * @return void
     */
    public function certificatedownload()
    {
        $slot = (string) filter_input(INPUT_GET, 'slot');
        $staging = self::_pkiStagingDir();
        // Generated here rather than accepted, and in the exact shape the
        // helper validates.
        $reqid = bin2hex(random_bytes(16));
        $out = $staging . DS . $reqid . '.pem';
        $res = $staging ? self::_pkiRun(['export', $slot, $reqid]) : ['ok' => false];
        $pem = ($res['ok'] && file_exists($out)) ? file_get_contents($out) : '';
        if (file_exists($out)) {
            unlink($out);
        }
        if ('' === $pem) {
            $this->title = _('Certificates');
            echo $this->_box(
                _('Certificate download failed'),
                '<p>' . _('That certificate is not available on this server.')
                . '</p>',
                ['color' => 'danger']
            );
            return;
        }
        // basename() on a value the helper already constrained to its own
        // allowlist. Belt and braces: this string lands in a header, and a
        // newline in a filename splits the response.
        $name = 'fog-' . preg_replace('/[^a-z0-9]/', '', strtolower($slot))
            . ('csr' === $slot ? '.csr' : '.pem');
        header(
            'Content-Type: '
            . ('csr' === $slot ? 'application/pkcs10' : 'application/x-pem-file')
        );
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($pem));
        // The output buffer collapses whitespace (Initiator::sanitizeOutput),
        // which would corrupt PEM's line structure and produce a file openssl
        // cannot read. Discard it rather than flushing it.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo $pem;
        exit;
    }
    /**
     * Import a root CA, remove one, or set an install preference.
     *
     * Gated on system.pki by Authorization::SUB_OVERRIDES, not on
     * settings.edit: an imported root reaches this host's trust store, and the
     * preferences are written into a file root sources as shell.
     *
     * @return void
     */
    public function certificatesPost()
    {
        self::checkAuthAndCSRF();
        $action = (string) filter_input(INPUT_POST, 'action');
        try {
            switch ($action) {
                case 'importRoot':
                    $this->_pkiImportRoot();
                    break;
                case 'clearRoot':
                    $this->_pkiClearRoot();
                    break;
                case 'adoptCustomLeaf':
                    $this->_pkiAdoptCustomLeaf();
                    break;
                case 'importLeaf':
                    $this->_pkiImportLeaf();
                    break;
                case 'makeLeafCsr':
                    $this->_pkiMakeLeafCsr();
                    break;
                case 'installLeafCert':
                    $this->_pkiInstallLeafCert();
                    break;
                case 'setPreference':
                    $this->_pkiSetPreference();
                    break;
                default:
                    throw new \Exception(_('Unknown action'));
            }
        } catch (\Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Certificate change failed')
                ]
            );
        }
    }
    /**
     * Adopt the pair sitting in the customizations directory.
     *
     * Posts nothing but the action. The verb takes no argument either -- the
     * directory comes out of the helper's own root-only config, so this is
     * stricter than ADR 0036's rule rather than an exception to it. A free-text
     * path field was the obvious alternative and is the one that ADR rejected:
     * the moment the caller names a file, the helper's job becomes proving a
     * path is safe.
     *
     * @return void
     */
    private function _pkiAdoptCustomLeaf()
    {
        $res = self::_pkiRun(['adopt-custom-leaf']);
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('That certificate was refused')
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => self::_pkiLeafMessage($res['out']),
                'title' => _('Certificate in use')
            ]
        );
    }
    /**
     * Stage an uploaded certificate, key, chain and passphrase, and hand the
     * lot to the helper under one request id.
     *
     * Nothing is validated here beyond the uploads themselves -- not the PEM
     * framing, not the key, not the pair. The helper parses all of it, refuses
     * a CA, refuses an expired certificate, refuses a chain that does not
     * build, and chooses every destination, because this side is the side that
     * might be compromised.
     *
     * The passphrase is staged as a FILE and never passed as an argument: this
     * builds a command line for sudo, and an argument is readable in /proc by
     * every local user for as long as the call lasts.
     *
     * @return void
     */
    private function _pkiImportLeaf()
    {
        $staging = self::_pkiStagingDir();
        if (!$staging) {
            throw new \Exception(
                _('Certificate management is not configured on this server')
            );
        }
        // A certificate and key are a few kilobytes; a PKCS#12 with a chain in
        // it is a few more. Capped before anything is written into the staging
        // directory rather than after.
        $fields = [
            'leafcert' => 'pem',
            'leafkey' => 'key',
            'leafchain' => 'chain',
            'leafp12' => 'p12'
        ];
        $reqid = bin2hex(random_bytes(16));
        $staged = [];
        $have = false;
        $res = ['ok' => false, 'out' => ''];
        try {
            foreach ($fields as $field => $ext) {
                if (!isset($_FILES[$field])
                    || UPLOAD_ERR_NO_FILE === ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)
                ) {
                    continue;
                }
                if (UPLOAD_ERR_OK !== ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)) {
                    throw new UploadException($_FILES[$field]['error']);
                }
                if (!is_uploaded_file($_FILES[$field]['tmp_name'] ?? '')) {
                    throw new \Exception(_('That upload did not arrive intact'));
                }
                if (($_FILES[$field]['size'] ?? 0) > 256 * 1024) {
                    throw new \Exception(
                        _('That file is too large to be a certificate or a key')
                    );
                }
                $dest = $staging . DS . $reqid . '.' . $ext;
                if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                    throw new \Exception(_('Could not stage the uploaded file'));
                }
                $staged[] = $dest;
                $have = true;
            }
            if (!$have) {
                throw new \Exception(
                    _(
                        'Upload a certificate and its key, or one PKCS#12 file '
                        . 'carrying both'
                    )
                );
            }
            $pass = (string) filter_input(INPUT_POST, 'leafpass');
            if ('' !== $pass) {
                $dest = $staging . DS . $reqid . '.pass';
                // 0600 before the bytes land, not after: the staging directory
                // is the web user's own, so the window where the group could
                // read it is the window worth not having.
                if (!touch($dest) || !chmod($dest, 0600)) {
                    throw new \Exception(_('Could not stage the passphrase'));
                }
                // Staged and restricted BEFORE the secret lands in it. fopen()
                // would create the file at the umask's mode and leave a window
                // in which it is group-readable, and the group on the staging
                // directory is the web user's own.
                $staged[] = $dest;
                if (false === file_put_contents($dest, $pass)) {
                    throw new \Exception(_('Could not stage the passphrase'));
                }
            }
            $res = self::_pkiRun(['import-leaf', $reqid]);
        } finally {
            // Unconditional, and in a finally so a throw on the way in cannot
            // leave a private key sitting in a directory the web user can read.
            foreach ($staged as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('That certificate was refused')
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => self::_pkiLeafMessage($res['out']),
                'title' => _('Certificate in use')
            ]
        );
    }
    /**
     * Generate a signing request whose key stays on the server.
     *
     * Posts nothing but the action. The helper makes a fresh key at
     * 0400 root:root in a fixed place and the request beside it; the page
     * then offers the request through the download slot. A POST rather than
     * part of the download because it creates state -- a new key replaces a
     * pending one -- and so belongs behind system.pki, where the GET is not.
     *
     * @return void
     */
    private function _pkiMakeLeafCsr()
    {
        // Nothing is validated here. The helper parses every field, refuses a
        // slash, an equals sign or a line break in a DN value, refuses a name
        // that is not a name, and re-emits its own openssl config from what
        // survived -- because this side is the side that might be compromised.
        $spec = self::_pkiCsrSpec();
        $args = ['make-leaf-csr'];
        $staged = '';
        try {
            if ('' !== $spec) {
                $staging = self::_pkiStagingDir();
                if (!$staging) {
                    throw new \Exception(
                        _('Certificate management is not configured on this server')
                    );
                }
                $reqid = bin2hex(random_bytes(16));
                $staged = $staging . DS . $reqid . '.spec';
                if (false === file_put_contents($staged, $spec)) {
                    throw new \Exception(_('Could not stage the request details'));
                }
                $args[] = $reqid;
            }
            $res = self::_pkiRun($args);
        } finally {
            if ('' !== $staged && file_exists($staged)) {
                unlink($staged);
            }
        }
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('The signing request could not be generated')
            );
        }
        // "OK <cn> <count> hostname:<true|false>"
        $parts = explode(' ', $res['out'], 4);
        $msg = sprintf(
            _(
                'A signing request for %s is ready to download. Have your CA '
                . 'sign it, then upload the certificate here.'
            ),
            $parts[1] ?? ''
        );
        if (false !== strpos($res['out'], 'hostname:false')) {
            $msg .= ' ' . _(
                'Note that the names you asked for do not include the name this '
                . 'server answers to, so netboot and FOG\'s own calls to itself '
                . 'will not accept the certificate that comes back.'
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            ['msg' => $msg, 'title' => _('Signing request generated')]
        );
    }
    /**
     * The request details the administrator typed, as the helper's spec format.
     *
     * Returns '' when the form was left alone, which is what makes the plain
     * derived request the default: no spec staged, no argument passed, and the
     * helper takes the branch it shipped with.
     *
     * Assembled as KEY=value lines and handed over as a FILE, never as
     * arguments: this builds a command line for sudo, and an argument is
     * readable in /proc by every local user for the length of the call. The
     * values are not sanitized here beyond dropping empties -- the helper
     * validates and re-emits, and a check on this side would be defense in
     * depth at best.
     *
     * @return string
     */
    private static function _pkiCsrSpec()
    {
        $fields = [
            'csrcn' => 'CN',
            'csro' => 'O',
            'csrou' => 'OU',
            'csrl' => 'L',
            'csrst' => 'ST',
            'csrc' => 'C'
        ];
        $lines = [];
        $touched = false;
        foreach ($fields as $field => $key) {
            $value = trim((string) filter_input(INPUT_POST, $field));
            if ('' === $value) {
                continue;
            }
            if ('CN' !== $key) {
                $touched = true;
            }
            $lines[] = $key . '=' . $value;
        }
        $names = (string) filter_input(INPUT_POST, 'csrnames');
        $given = [];
        foreach (preg_split('/[\r\n]+/', $names) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            if (0 === stripos($line, 'IP:')) {
                $given[] = 'IP=' . trim(substr($line, 3));
                continue;
            }
            // An address typed without the prefix is still an address. Asking
            // an administrator to remember which of their own names are IPs is
            // a rule the form would have to teach for no reason.
            $given[] = (filter_var($line, FILTER_VALIDATE_IP) ? 'IP=' : 'DNS=')
                . $line;
        }
        if ($given) {
            $touched = true;
            $lines = array_merge($lines, $given);
        }
        // A CN on its own is what the form shows when it was never edited, so
        // it does not by itself mean "use a custom request".
        return $touched ? implode("\n", $lines) . "\n" : '';
    }
    /**
     * The common name the helper would derive, for pre-filling the form.
     *
     * Read off the pending request when there is one, so an administrator
     * editing a request sees what it actually asked for; otherwise off the
     * certificate the vhost is serving. Never computed here -- the helper owns
     * that derivation, and a second copy in PHP would drift.
     *
     * @param array|null $status the helper's report.
     *
     * @return string
     */
    private static function _pkiDerivedCn($status)
    {
        $pending = (array) (($status ?? [])['pending_csr'] ?? []);
        if (!empty($pending['subject'])
            && preg_match('/CN\s*=\s*([^,\/]+)/', (string) $pending['subject'], $m)
        ) {
            return trim($m[1]);
        }
        $cert = self::_pkiCert($status, 'vhost');
        if ($cert && !empty($cert['subject'])
            && preg_match('/CN\s*=\s*([^,\/]+)/', (string) $cert['subject'], $m)
        ) {
            return trim($m[1]);
        }
        return '';
    }
    /**
     * The names the helper would put in the request, one per line.
     *
     * Taken from the pending request's own SAN list where there is one. The
     * helper reports them in openssl's config shape ("DNS.1 = host"), which is
     * not a thing to make an administrator edit, so it is flattened to the
     * bare names and IP entries are prefixed the way the form documents.
     *
     * @param array|null $status the helper's report.
     *
     * @return string
     */
    private static function _pkiDerivedNames($status)
    {
        $pending = (array) (($status ?? [])['pending_csr'] ?? []);
        $raw = (string) ($pending['names'] ?? '');
        if ('' === trim($raw)) {
            return '';
        }
        $out = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            // "DNS.1 = host", "IP.1 = 10.0.0.5", or already-bare names.
            if (preg_match('/^(DNS|IP)[.\d]*\s*=\s*(.+)$/i', $line, $m)) {
                $out[] = ('IP' === strtoupper($m[1]) ? 'IP:' : '') . trim($m[2]);
                continue;
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }
    /**
     * Stage the certificate a CA issued from the pending request, and hand it
     * to the helper.
     *
     * No key travels: the pair is the one the helper generated, and the helper
     * refuses a certificate that does not match it. Same staging shape as
     * _pkiImportLeaf(), minus the key and the passphrase.
     *
     * @return void
     */
    private function _pkiInstallLeafCert()
    {
        $staging = self::_pkiStagingDir();
        if (!$staging) {
            throw new \Exception(
                _('Certificate management is not configured on this server')
            );
        }
        $fields = [
            'csrcert' => 'pem',
            'csrchain' => 'chain'
        ];
        $reqid = bin2hex(random_bytes(16));
        $staged = [];
        $have = false;
        $res = ['ok' => false, 'out' => ''];
        try {
            foreach ($fields as $field => $ext) {
                if (!isset($_FILES[$field])
                    || UPLOAD_ERR_NO_FILE === ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)
                ) {
                    continue;
                }
                if (UPLOAD_ERR_OK !== ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)) {
                    throw new UploadException($_FILES[$field]['error']);
                }
                if (!is_uploaded_file($_FILES[$field]['tmp_name'] ?? '')) {
                    throw new \Exception(_('That upload did not arrive intact'));
                }
                if (($_FILES[$field]['size'] ?? 0) > 256 * 1024) {
                    throw new \Exception(
                        _('That file is too large to be a certificate')
                    );
                }
                $dest = $staging . DS . $reqid . '.' . $ext;
                if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                    throw new \Exception(_('Could not stage the uploaded file'));
                }
                $staged[] = $dest;
                $have = true;
            }
            if (!$have) {
                throw new \Exception(
                    _('Upload the certificate your CA issued from the request')
                );
            }
            $res = self::_pkiRun(['install-leaf-cert', $reqid]);
        } finally {
            foreach ($staged as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('That certificate was refused')
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => self::_pkiLeafMessage($res['out']),
                'title' => _('Certificate in use')
            ]
        );
    }
    /**
     * Turn the helper's "OK <subject> reload:<state>" line into something an
     * administrator can act on.
     *
     * The reload state matters enough to report rather than swallow. Unlike the
     * three install preferences, a certificate change is meant to take effect
     * at once -- so "installed, but the web server did not reload" is a
     * different situation from success, and the thing to do about it is
     * different too.
     *
     * @param string $out the helper's success line.
     *
     * @return string
     */
    private static function _pkiLeafMessage($out)
    {
        $reload = '';
        if (preg_match('/reload:([a-z]+)$/', trim($out), $m)) {
            $reload = $m[1];
        }
        if ('ok' === $reload) {
            return _(
                'This server is now serving that certificate, and the web '
                . 'server has been reloaded.'
            );
        }
        if ('failed' === $reload) {
            return _(
                'The certificate is installed and recorded, but the web server '
                . 'would not reload, so it is still serving the previous one. '
                . 'Reload it by hand, or re-run the installer.'
            );
        }
        return _(
            'The certificate is installed and recorded. Reload the web server '
            . 'for it to be served.'
        );
    }
    /**
     * Stage an uploaded root CA and hand it to the helper.
     *
     * Nothing is validated here beyond the upload itself. The helper parses
     * it, requires a self-signed CA that has not expired, keeps only the
     * self-signed certificates out of a bundle, and chooses where it lands --
     * because this side is the side that might be compromised.
     *
     * @return void
     */
    private function _pkiImportRoot()
    {
        $staging = self::_pkiStagingDir();
        if (!$staging) {
            throw new \Exception(
                _('Certificate management is not configured on this server')
            );
        }
        if (!isset($_FILES['rootca']) || !is_uploaded_file($_FILES['rootca']['tmp_name'] ?? '')) {
            throw new \Exception(_('No certificate file was uploaded'));
        }
        if (UPLOAD_ERR_OK !== ($_FILES['rootca']['error'] ?? UPLOAD_ERR_NO_FILE)) {
            throw new UploadException($_FILES['rootca']['error']);
        }
        // A root CA certificate is a couple of kilobytes; a bundle of them is
        // a few more. The cap is here so a multi-megabyte upload is refused
        // before it is written into the staging directory rather than after.
        if (($_FILES['rootca']['size'] ?? 0) > 128 * 1024) {
            throw new \Exception(
                _('That file is too large to be a root CA certificate')
            );
        }
        $reqid = bin2hex(random_bytes(16));
        $dest = $staging . DS . $reqid . '.pem';
        if (!move_uploaded_file($_FILES['rootca']['tmp_name'], $dest)) {
            throw new \Exception(_('Could not stage the uploaded certificate'));
        }
        $res = self::_pkiRun(['import-root', $reqid]);
        if (file_exists($dest)) {
            unlink($dest);
        }
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('The certificate was refused')
            );
        }
        // The helper answers "OK <kept> trust:<ok|failed|unavailable>". A
        // trust-store failure is reported rather than fatal, matching
        // _installCATrustAnchor(): the certificate did land and is re-applied
        // on every later run, so calling this a failure would be wrong and
        // saying nothing would be worse.
        $parts = explode(' ', $res['out']);
        $trust = $parts[2] ?? '';
        $msg = _('The root CA has been imported and this server now trusts it.');
        if ('trust:ok' !== $trust) {
            $msg = _(
                'The root CA has been imported, but this host\'s system trust '
                . 'store could not be updated -- so HTTPS calls made on this '
                . 'server will not accept it yet. Re-run the installer, and '
                . 'check the installation log.'
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            ['msg' => $msg, 'title' => _('Root imported')]
        );
    }
    /**
     * Stop trusting a previously imported root CA.
     *
     * The helper removes the file, clears the recorded path and rebuilds the
     * anchor, so the removal survives the next installer run the same way the
     * import did. Offered at all because an import nobody can undo from the
     * same screen is the destructive-web-form problem this page is otherwise
     * careful to avoid.
     *
     * @return void
     */
    private function _pkiClearRoot()
    {
        $res = self::_pkiRun(['clear-root']);
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('Could not remove the imported root')
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => _('The imported root CA has been removed.'),
                'title' => _('Root removed')
            ]
        );
    }
    /**
     * Set one install preference.
     *
     * Both the key and the value are passed through unexamined. The helper
     * holds the three-entry key allowlist and the ^(yes|no)$ value pattern,
     * and it has to: .fogsettings is sourced as shell by root on the next
     * installer run, so validating here -- on the side that might be
     * compromised -- would be validating in the wrong place.
     *
     * @return void
     */
    private function _pkiSetPreference()
    {
        $key = (string) filter_input(INPUT_POST, 'key');
        $raw = (string) filter_input(INPUT_POST, 'value');
        // Mapped by KEY, not by inspecting the value: the three switches post a
        // truthy flag and the netboot transport posts its own word, and keying
        // off the value would let one be talked into posting the other's
        // domain. This side deciding is a convenience either way -- the helper
        // re-checks against that key's own literal pattern, and that check is
        // the boundary, because .fogsettings is sourced as shell by root.
        if ('BOOT_url_proto' === $key) {
            $value = 'https' === $raw ? 'https' : 'http';
        } else {
            $value = $raw ? 'yes' : 'no';
        }
        $res = self::_pkiRun(['set-preference', $key, $value]);
        if (!$res['ok']) {
            throw new \Exception(
                $res['out'] ?: _('Could not save that preference')
            );
        }
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => _(
                    'Saved. It takes effect the next time the installer runs.'
                ),
                'title' => _('Preference saved')
            ]
        );
    }
    /**
     * Show the Secure Boot enrollment page.
     *
     * Displays the certificate fingerprint and links to the enrollment kit, so
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
        //
        // Read through SecureBootState rather than computed here, so that the
        // string on this page and the string each host's enrollment record is
        // compared against are the same string by construction. They agreed
        // when both were written out longhand; "they agree today" is not the
        // same property as "they cannot disagree".
        $fingerprint = SecureBootState::serverFingerprint();
        // MokManager's own "View key" screen -- the only thing shown when
        // enrolling from the PXE menu, which never runs fog-enroll-mok.sh --
        // prints a SHA-1 fingerprint, not SHA-256. Show both so either
        // enrollment route has a value on this page to check against.
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
            'Check this value against what the enrollment tool shows before '
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
        $body .= '<p><strong>' . _('Enrollment kit') . '</strong></p>';
        $body .= '<p>' . _(
            'For a live-USB enrollment, copy all three files onto a USB '
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

        // Automatic enrollment card, placed above the manual steps because it is
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
                . 'enrollment blobs (PK.auth, KEK.auth and db.auth), so '
                . 'automatic enrollment is unavailable. There are three '
                . 'reasons that happens:'
            ) . '</p>';
            $auto .= '<ul>';
            $auto .= '<li>' . _(
                'Secure Boot enrollment material is switched off for this '
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
                'The manual enrollment steps below are unaffected.'
            ) . '</p>';
            echo $this->_box(
                _('Automatic enrollment (Setup/Custom Mode)'),
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
                . 'afterward.'
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
                _('Automatic enrollment (Setup/Custom Mode)'),
                $auto,
                ['color' => 'success']
            );
        }

        // Second card: the actual procedure. The card above answers "is this
        // configured and what is the key", which is the reference half. Full
        // per-client steps for both enrollment routes live in the linked guide
        // now rather than being duplicated here -- this card is the summary
        // and the gotchas that matter regardless of which route is used.
        $steps = '<p>' . _(
            'Signing is already done on this server. The remaining work is '
            . 'per-client and has to be done by someone at the machine -- that '
            . 'is what makes enrollment a deliberate act rather than something '
            . 'a server can do to a client remotely.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Two routes are covered in the guide linked above: a live USB '
            . 'with the kit above, or PXE-booting the client and choosing '
            . 'Enroll Secure Boot Key, which now fetches MOK.der over the '
            . 'network on its own, with no USB stick needed.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Secure Boot does not need to be enabled on a client to enroll '
            . 'its key -- either route works the same way with it off, '
            . 'which lets you stage enrollment fleet-wide before ever '
            . 'turning it on.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'The Enroll Secure Boot task type does all of this for you: '
            . 'schedule it against a host or a group from Task Scheduling '
            . 'and the client boots FOS, which stages the request itself -- '
            . 'or enrolls outright with nothing to confirm, if the machine is '
            . 'in Setup Mode. The Enroll Secure Boot Key menu item stays for '
            . 'answering a pending request by hand, or for enrolling from '
            . 'local media on a machine FOS cannot boot.'
        ) . '</p>';
        $steps .= '<p>' . _(
            'Whichever route is used, MokManager -- the blue enrollment '
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
        // This used to say Secure Boot PXE and HTTPS were mutually exclusive,
        // because downloadipxesecureboot() skipped the staging entirely on an
        // HTTPS install. It no longer does, and the claim was wrong even then:
        // upstream iPXE defines CROSSCERT unconditionally, so its signed
        // binaries validate a publicly-issued certificate with no rebuild and
        // no embedded CA. Only a private CA forces a choice, and the answer
        // there is to leave netboot on HTTP -- which has nothing to do with
        // whether a signed chain is available. See downloadipxesecureboot().
        //
        // Stated rather than detected: the web request's own scheme says nothing
        // about the install's $httpproto or its netboot protocol, so guessing
        // here would be worse than telling the admin what to check.
        $steps .= '<p>' . _(
            'These binaries are staged on every install, HTTPS included. An '
            . 'HTTPS web interface has no bearing on netboot, which has its own '
            . 'protocol setting. HTTPS netboot only needs a rebuilt iPXE when '
            . 'the certificate comes from a private CA -- a publicly-issued one '
            . 'on an FQDN needs no rebuild and keeps the signed shim.'
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
    /**
     * The default-pointer settings a file of this role may be set as.
     *
     * The settings stay authoritative for what boots -- IpxeBootMenu reads
     * them and nothing here changes that. This tab is a second setter for
     * them, next to the files it is choosing between, because "put the
     * default back on the kernel that worked" is otherwise a trip to FOG
     * Settings and a filename typed from memory.
     *
     * Keyed by role, because FOG_MEMTEST_KERNEL names a Boot Payload and
     * offering it a FOS kernel is the bug this whole change set is about.
     *
     * @param string $role one of FOGPage::BOOT_ROLE_*
     *
     * @return array setting key => label
     */
    /**
     * Dispatch anchor for sub=bootfilekeep. See _postOnly().
     *
     * @return void
     */
    public function bootfilekeep()
    {
        $this->_postOnly();
    }
    /**
     * Dispatch anchor for sub=bootfiledefault. See _postOnly().
     *
     * @return void
     */
    public function bootfiledefault()
    {
        $this->_postOnly();
    }
    /**
     * Dispatch anchor for sub=bootfiledelete. See _postOnly().
     *
     * @return void
     */
    public function bootfiledelete()
    {
        $this->_postOnly();
    }
    /**
     * Resolves a posted filename to a real file in the boot directory.
     *
     * basename() and then a membership test against the directory listing,
     * rather than trusting the name: every one of these endpoints turns a
     * request parameter into a filesystem path, and "../../etc/passwd" is
     * the first thing anybody tries. The listing is the allow-list.
     *
     * @param string $name posted filename
     *
     * @return array|null the bootFileInfo() record, or null
     */
    private static function _bootFileNamed($name)
    {
        $name = basename(trim((string)$name));
        if ('' === $name || '.' === $name || '..' === $name) {
            return null;
        }
        $dir = trim((string)self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
        if ('' === $dir || !is_dir($dir)) {
            return null;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return null;
        }

        return self::bootFileInfo($path);
    }
    /**
     * Marks a boot file as one no pruner may remove, or unmarks it.
     *
     * Records the intention only. Nothing prunes boot files yet, so there is
     * nothing for this to protect it from today -- the pruner, and the copy
     * into the customizations tree that survives a rebuild of the web root,
     * arrive with the retention work. Writing the flag here means the tab
     * that shows it is also the thing that sets it.
     *
     * @return void
     */
    public function bootfilekeepPost()
    {
        self::checkAuthAndCSRF();
        if (!Authorization::can('settings.edit')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to change boot '
                        . 'files.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $info = self::_bootFileNamed(filter_input(INPUT_POST, 'name'));
        if (null === $info) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => _('No such file in the boot directory.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $keep = (bool)(int)filter_input(INPUT_POST, 'keep');
        if (!self::bootFileSetPinned($info['name'], $keep)) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                [
                    'error' => _('The record could not be written.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        Audit::record(
            [
                'type' => $keep ? 'bootfile.keep' : 'bootfile.unkeep',
                'subjectType' => 'bootfile',
                'subjectLabel' => $info['name'],
                'permission' => 'settings.edit',
                'renderable' => 1
            ]
        );
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => $keep
                    ? sprintf(_('%s will be kept.'), $info['name'])
                    : sprintf(_('%s is no longer kept.'), $info['name']),
                'title' => _('Boot File Updated')
            ]
        );
    }
    /**
     * Points one default-pointer setting at this file.
     *
     * The setting remains what boots -- this writes the same key FOG
     * Settings writes, and IpxeBootMenu keeps reading exactly one place.
     * What it adds is choosing from the files that actually exist, which is
     * how "put the default back on the kernel that worked" stops being a
     * filename typed from memory.
     *
     * The key is validated against the roles' own list, so a payload cannot
     * be installed as a boot kernel through a hand-made request.
     *
     * @return void
     */
    public function bootfiledefaultPost()
    {
        self::checkAuthAndCSRF();
        if (!Authorization::can('settings.edit')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to change boot '
                        . 'files.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $info = self::_bootFileNamed(filter_input(INPUT_POST, 'name'));
        if (null === $info) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => _('No such file in the boot directory.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $key = trim((string)filter_input(INPUT_POST, 'key'));
        $allowed = self::_defaultKeysFor($info['role']);
        if (!isset($allowed[$key])) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => sprintf(
                        _('%s cannot be used as %s.'),
                        $info['name'],
                        $key
                    ),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        self::setSetting($key, $info['name']);
        Audit::record(
            [
                'type' => 'bootfile.default',
                'subjectType' => 'setting',
                'subjectLabel' => $key,
                'permission' => 'settings.edit',
                'text' => $info['name'],
                'renderable' => 1
            ]
        );
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => sprintf(
                    _('%s is now %s.'),
                    $info['name'],
                    $allowed[$key]
                ),
                'title' => _('Boot File Updated')
            ]
        );
    }
    /**
     * Removes a boot file from the directory.
     *
     * Refused for a file a default points at and for one marked kept. The
     * row buttons already hide in those cases; this refuses it anyway,
     * because a hidden button is a courtesy and not a rule.
     *
     * Deleted over the same SSH/SFTP connection kernelfetch() uses to put
     * files there, not with unlink(). That is where the writes go, the TFTP
     * host may not be this machine, and using the write path for the delete
     * means the two cannot disagree about which directory is real.
     *
     * @return void
     */
    public function bootfiledeletePost()
    {
        self::checkAuthAndCSRF();
        if (!Authorization::can('settings.edit')) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                [
                    'error' => _('You do not have permission to change boot '
                        . 'files.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $info = self::_bootFileNamed(filter_input(INPUT_POST, 'name'));
        if (null === $info) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => _('No such file in the boot directory.'),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        if ($info['pinned']) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => sprintf(
                        _('%s is marked to be kept. Stop keeping it first.'),
                        $info['name']
                    ),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        $pointing = [];
        foreach (self::_defaultPointers() as $key => $points) {
            if ($points === $info['name']) {
                $pointing[] = $key;
            }
        }
        if (count($pointing)) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => sprintf(
                        _('%s is in use as %s. Point that at another file '
                            . 'first.'),
                        $info['name'],
                        implode(', ', $pointing)
                    ),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        try {
            self::bootFileRemove($info['name']);
        } catch (\Throwable $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Boot File Failed')
                ]
            );
        }
        Audit::record(
            [
                'type' => 'bootfile.delete',
                'subjectType' => 'bootfile',
                'subjectLabel' => $info['name'],
                'permission' => 'settings.edit',
                'renderable' => 1
            ]
        );
        $this->_jsonExit(
            HTTPResponseCodes::HTTP_SUCCESS,
            [
                'msg' => sprintf(_('%s deleted.'), $info['name']),
                'title' => _('Boot File Deleted')
            ]
        );
    }
    private static function _defaultKeysFor($role)
    {
        switch ($role) {
            case self::BOOT_ROLE_KERNEL:
                return [
                    'FOG_TFTP_PXE_KERNEL' => _('Default kernel, x86_64'),
                    'FOG_TFTP_PXE_KERNEL_32' => _('Default kernel, i386'),
                    'FOG_TFTP_PXE_KERNEL_ARM' => _('Default kernel, ARM64')
                ];
            case self::BOOT_ROLE_INIT:
                return [
                    'FOG_PXE_BOOT_IMAGE' => _('Default init, x86_64'),
                    'FOG_PXE_BOOT_IMAGE_32' => _('Default init, i386'),
                    'FOG_PXE_BOOT_IMAGE_ARM' => _('Default init, ARM64')
                ];
            case self::BOOT_ROLE_PAYLOAD:
                return ['FOG_MEMTEST_KERNEL' => _('Memtest payload')];
        }

        return [];
    }
    /**
     * Every default-pointer setting, with the filename it currently names.
     *
     * @return array setting key => filename
     */
    private static function _defaultPointers()
    {
        $keys = array_merge(
            array_keys(self::_defaultKeysFor(self::BOOT_ROLE_KERNEL)),
            array_keys(self::_defaultKeysFor(self::BOOT_ROLE_INIT)),
            array_keys(self::_defaultKeysFor(self::BOOT_ROLE_PAYLOAD))
        );
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = trim((string)self::getSetting($key));
        }

        return $out;
    }
    /**
     * The boot directory, as a table of what is there and what it is for.
     *
     * Deliberately the same listing on both the kernel and the initrd page
     * rather than one filtered per page. The Role column is what makes it
     * legible, and a file whose role is not what an admin expected is
     * exactly what they came to look at -- filtering it out of the page they
     * happened to open would hide the answer.
     *
     * A plain server-sorted table, not a DataTable: this pane is not the
     * active tab on arrival, and DataTables computes column widths wrong
     * inside a hidden pane. The row count here is tens.
     *
     * @return string
     */
    private function _bootFileTable()
    {
        $dir = trim((string)self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
        if ('' === $dir || !is_dir($dir) || !is_readable($dir)) {
            return '<div class="alert alert-warning">'
                . _('The boot directory cannot be read.')
                . ' '
                . \Initiator::e($dir)
                . ' '
                . _('Check FOG_TFTP_PXE_KERNEL_DIR.')
                . '</div>';
        }
        $names = @scandir($dir);
        if (false === $names) {
            $names = [];
        }
        $roleLabels = [
            self::BOOT_ROLE_KERNEL => _('FOS Kernel'),
            self::BOOT_ROLE_INIT => _('FOS Init'),
            self::BOOT_ROLE_PAYLOAD => _('Boot Payload'),
            self::BOOT_ROLE_OTHER => _('Unclassified')
        ];
        $roleOrder = [
            self::BOOT_ROLE_KERNEL => 0,
            self::BOOT_ROLE_INIT => 1,
            self::BOOT_ROLE_PAYLOAD => 2,
            self::BOOT_ROLE_OTHER => 3
        ];
        $pointers = self::_defaultPointers();
        $rows = [];
        foreach ($names as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) {
                continue;
            }
            $rows[] = self::bootFileInfo($path);
        }
        usort(
            $rows,
            function ($a, $b) use ($roleOrder) {
                $ao = $roleOrder[$a['role']] ?? 9;
                $bo = $roleOrder[$b['role']] ?? 9;
                if ($ao !== $bo) {
                    return $ao - $bo;
                }
                $adot = substr_count($a['name'], '.');
                $bdot = substr_count($b['name'], '.');
                if ($adot !== $bdot) {
                    return $adot - $bdot;
                }

                return strnatcasecmp($b['name'], $a['name']);
            }
        );
        if (!count($rows)) {
            return '<div class="alert alert-warning">'
                . _('No files in the boot directory.')
                . '</div>';
        }
        $mayEdit = Authorization::can('settings.edit');
        $out = '<div class="table-responsive">';
        $out .= '<table class="table table-striped fog-bootfile-table">';
        $out .= '<thead><tr>'
            . '<th>' . _('File') . '</th>'
            . '<th>' . _('Role') . '</th>'
            . '<th>' . _('Version') . '</th>'
            . '<th>' . _('FOS Release') . '</th>'
            . '<th>' . _('Size') . '</th>'
            . '<th>' . _('Modified') . '</th>'
            . '<th>' . _('In use as') . '</th>'
            . '<th>' . _('Keep') . '</th>'
            . ($mayEdit ? '<th>' . _('Actions') . '</th>' : '')
            . '</tr></thead><tbody>';
        /**
         * Two names with the same checksum are the same kernel. Saying so is
         * what stops "keep three versions" meaning three copies of one --
         * and an admin looking at bzImage next to bzImage.<release> has no
         * other way to tell whether an update actually changed anything.
         */
        $byChecksum = [];
        foreach ($rows as $row) {
            if ('' !== $row['checksum']) {
                $byChecksum[$row['checksum']][] = $row['name'];
            }
        }
        foreach ($rows as $row) {
            $name = $row['name'];
            $inUse = [];
            foreach ($pointers as $key => $points) {
                if ($points === $name) {
                    $inUse[] = $key;
                }
            }
            $same = array_diff(
                $byChecksum[$row['checksum']] ?? [],
                [$name]
            );
            $out .= '<tr data-bootfile="' . \Initiator::e($name) . '">';
            $out .= '<td><code>' . \Initiator::e($name) . '</code>'
                . (
                    count($same) ?
                    '<br><small class="text-muted">'
                    . _('same contents as')
                    . ' '
                    . \Initiator::e(implode(', ', $same))
                    . '</small>' :
                    ''
                )
                . '</td>';
            $out .= '<td>'
                . \Initiator::e($roleLabels[$row['role']] ?? $row['role'])
                . '</td>';
            $out .= '<td>'
                . (
                    '' !== $row['kernelVersion'] ?
                    \Initiator::e($row['kernelVersion']) :
                    '<span class="text-muted">' . _('not readable')
                    . '</span>'
                )
                . '</td>';
            $out .= '<td>'
                . (
                    '' !== $row['releaseTag'] ?
                    \Initiator::e($row['releaseTag']) :
                    '<span class="text-muted">'
                    . \Initiator::e($row['tagReason'])
                    . '</span>'
                )
                . '</td>';
            $out .= '<td>' . \Initiator::e(self::formatByteSize($row['size']))
                . '</td>';
            $out .= '<td>'
                . \Initiator::e(
                    self::formatTime('@' . $row['mtime'], 'Y-m-d H:i')
                )
                . '</td>';
            $out .= '<td>'
                . (
                    count($inUse) ?
                    '<span class="badge text-bg-primary">'
                    . \Initiator::e(implode('</span> <span '
                        . 'class="badge text-bg-primary">', $inUse))
                    . '</span>' :
                    '<span class="text-muted">&mdash;</span>'
                )
                . '</td>';
            $out .= '<td>'
                . (
                    $row['pinned'] ?
                    '<span class="badge text-bg-success">' . _('Kept')
                    . '</span>' :
                    '<span class="text-muted">&mdash;</span>'
                )
                . '</td>';
            if ($mayEdit) {
                $out .= '<td>' . $this->_bootFileActions($row, $inUse)
                    . '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table></div>';

        return $out;
    }
    /**
     * The per-row controls: keep, set as a default, delete.
     *
     * Delete is refused for a file a default points at and for one that is
     * kept, and the button is simply absent in those cases rather than
     * present and failing -- the POST handler refuses it too, because a
     * missing button is a UI courtesy and not a rule.
     *
     * @param array $row   one bootFileInfo() record
     * @param array $inUse setting keys currently naming this file
     *
     * @return string
     */
    private function _bootFileActions(array $row, array $inUse)
    {
        $name = \Initiator::e($row['name']);
        $keys = self::_defaultKeysFor($row['role']);
        $out = '<div class="btn-group btn-group-sm" role="group">';
        $out .= '<button type="button" class="btn btn-secondary '
            . 'fog-bootfile-keep" data-name="' . $name . '" data-keep="'
            . ($row['pinned'] ? '0' : '1') . '">'
            . ($row['pinned'] ? _('Stop keeping') : _('Keep'))
            . '</button>';
        if (count($keys)) {
            $out .= '<button type="button" class="btn btn-primary '
                . 'dropdown-toggle" data-bs-toggle="dropdown" '
                . 'aria-expanded="false">'
                . _('Set as')
                . '</button><ul class="dropdown-menu">';
            foreach ($keys as $key => $label) {
                $out .= '<li><a class="dropdown-item fog-bootfile-default" '
                    . 'href="#" data-name="' . $name . '" data-key="'
                    . \Initiator::e($key) . '">'
                    . \Initiator::e($label)
                    . '</a></li>';
            }
            $out .= '</ul>';
        }
        $out .= '</div>';
        if (!count($inUse) && !$row['pinned']) {
            $out .= ' <button type="button" class="btn btn-sm btn-danger '
                . 'fog-bootfile-delete" data-name="' . $name . '">'
                . _('Delete')
                . '</button>';
        }

        return $out;
    }
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

        /**
         * Two tabs in the card that used to hold one table. The releases
         * available to download were the only thing this page could say, so
         * "what do I already have, and what is it" had nowhere to be asked.
         *
         * The release table stays tab one, and stays the active pane on
         * arrival: its DataTable initializes at page load and computes
         * column widths, which it cannot do correctly while hidden.
         *
         * false, not the default -1: that would ask getClass() to resolve an
         * entity for node 'about', and there is none. certificates() in this
         * file passes false for the same reason.
         */
        $tabData = [
            [
                'name' => _('Available downloads'),
                'id' => $type . '-releases',
                'generator' => function () use ($buttons) {
                    echo $this->process(
                        12,
                        'dataTable',
                        $buttons,
                        'display table table-bordered table-striped'
                    );
                }
            ],
            [
                'name' => _('Local files'),
                'id' => $type . '-local',
                'generator' => function () {
                    echo $this->_bootFileTable();
                }
            ]
        ];

        echo $this->_box(
            $this->title,
            self::tabFields($tabData, false),
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
        $file = trim(base64_decode((string)filter_input(INPUT_POST, 'file')));
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
        $settingMan = new SettingManager();
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
                            $input = (new KeySequenceManager())
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
                . (new UserManager())
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
            (new APITokenManager())->visibleTo($uid) as $token
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
        $deleted = (new APITokenManager())->revokeMany(
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
        $changed = (new APITokenManager())->setEnabledMany(
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
     * createdBy, so that asymmetry is legible afterward.
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
        $user = new User($forUserID);
        $inScope = (new APITokenManager())
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
                            . 'apart from its neighbors when it comes time '
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
                ) = (new OUIManager())
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
        $osname = (new Image($imageid))->getOS()->get('name');
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
            'FOG_CLIENT_HOSTREGISTER_ENABLED' => true,
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => true,
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => true,
            'FOG_CLIENT_PRINTERMANAGER_ENABLED' => true,
            'FOG_CLIENT_SNAPIN_ENABLED' => true,
            'FOG_CLIENT_TASKREBOOT_ENABLED' => true,
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
            'FOG_CLIENT_AUTOLOGOFF_WARN' => true,
            // FOG Service - Host Register
            'FOG_QUICKREG_MAX_PENDING_MACS' => true,
            // FOG Service - Software
            'FOG_SOFTWARE_DRIFT_INTERVAL' => true,
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
            /**
             * The default kernels, inits and the memtest payload are all
             * filenames in the FOS boot directory, so offer what is actually
             * there -- the per-release siblings the installer leaves behind
             * included, which is what makes "put the default back on the
             * previous kernel" a selection rather than a typed guess.
             *
             * These cases used to live in getIpxeList(), whose $ServicesToSee
             * allow-list does not contain any of these keys -- so the switch
             * arm never ran and this page rendered the default kernel as a
             * plain text box. This is the renderer the settings page actually
             * uses.
             *
             * The role differs per key and that is the point:
             * FOG_MEMTEST_KERNEL is NAMED for a kernel but points at a boot
             * payload (memtest.bin, memdisk, grub.exe). One list serving both
             * meanings is what offered those three as a boot kernel.
             */
            case 'FOG_TFTP_PXE_KERNEL':
            case 'FOG_TFTP_PXE_KERNEL_32':
            case 'FOG_TFTP_PXE_KERNEL_ARM':
                $input = self::kernelFileSelect(
                    $row['settingID'],
                    $row['settingValue'],
                    'kernel',
                    'form-control',
                    $row['settingKey']
                );
                break;
            case 'FOG_PXE_BOOT_IMAGE':
            case 'FOG_PXE_BOOT_IMAGE_32':
            case 'FOG_PXE_BOOT_IMAGE_ARM':
                $input = self::kernelFileSelect(
                    $row['settingID'],
                    $row['settingValue'],
                    'init',
                    'form-control',
                    $row['settingKey']
                );
                break;
            case 'FOG_MEMTEST_KERNEL':
                $input = self::kernelFileSelect(
                    $row['settingID'],
                    $row['settingValue'],
                    'payload',
                    'form-control',
                    $row['settingKey']
                );
                break;
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
            case 'FOG_HOST_IDENTIFY_SMBIOS':
                $vals = [
                    _('Off - MAC address only') => 'off',
                    _('Log - MAC decides, log disagreements') => 'log',
                    _('Enforce - firmware identity wins') => 'enforce'
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
                    $current_tz = new \DateTimeZone($tz);
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
            // Initialized before the loop, like the two sibling savers above.
            // The body has three `continue` paths that skip the append, and
            // $combined can be empty, so a post that changes nothing left
            // $items undefined -- and the count below reads it, which is a
            // warning rather than a silent 0. `?:` there was papering over
            // the missing initialization; `??` would have hidden it too.
            $items = [];
            foreach ($combined as $key => &$val) {
                // Resolved into its own variable: $key stays the posted key
                // because the $_FILES lookup below is keyed by what the form
                // actually sent.
                $id = self::_settingIdFor($key);
                $Setting = Route::getItem('setting', $id);
                if (!isset($_FILES[$key]) || !$_FILES[$key]) {
                    $set = trim((string)filter_var($val));
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

        $table = (new SettingManager())->getTable();
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
     * Log Viewer, which now lives at its own node.
     *
     * Kept as a redirect rather than deleted: `?node=about&sub=logviewer` is
     * the URL this page has had for years, so it is in bookmarks and in
     * documentation. Same reasoning as History_Report, which ADR 0023 item 4
     * turned into a redirect into the Activity viewer for exactly this.
     *
     * @return void
     */
    public function logviewer()
    {
        self::redirect('?node=logviewer');
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

        // The button follows the grant, the way the Login History tab does:
        // a role that cannot export should not be shown the control that
        // fails. The check in configPost() is the gate; this is the display.
        $buttons = '';
        if (Authorization::can('system.export')) {
            $buttons = self::makeButton(
                'exportdb',
                _('Export'),
                'btn btn-primary float-end'
            );
        }
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
        $Schema = new Schema();
        $serverFault = false;
        try {
            if (isset($_POST['toExport'])) {
                // The UI's own dump is the same authority as
                // GET /system/export -- same tables, same credentials --
                // so it takes the same permission (GH-1410). Checked here
                // rather than by the node/sub map because this one POST
                // endpoint serves both export and import, and only the
                // export half is a credential census; `about` aliases to
                // `settings`, so the map would put both on settings.edit.
                if (!Authorization::can('system.export')) {
                    $this->_jsonExit(
                        HTTPResponseCodes::HTTP_FORBIDDEN,
                        [
                            'error' => _('You do not have permission to '
                                . 'export the database.'),
                            'title' => _('Export Failed')
                        ]
                    );
                }
                $backup_name = 'fog_backup_'
                    . self::formatTime('now', 'Ymd_His');
                // Not a fixed name under the system temp dir keyed on
                // $backup_name: that is guessable to the second,
                // world-readable under the default umask, and fopen()
                // follows symlinks. Same three defects as
                // Schema::exportdb() had -- see the comment there.
                $tmpfile = tempnam(sys_get_temp_dir(), 'fog_backup_');
                if (false === $tmpfile) {
                    throw new \Exception(_('Could not create tmp file.'));
                }
                chmod($tmpfile, 0600);
                $data = '';
                (new Mysqldump())->start($tmpfile);
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
                    $result = (new Schema())->importdb($dest);
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
        $settingMan = new SettingManager();
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
