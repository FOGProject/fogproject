<?php
/**
 * Base element for client services
 *
 * PHP version 7.4+
 *
 * @category FOGClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Managers\HostManager;
use FOG\Router\Route;

/**
 * Base element for client services
 *
 * @category FOGClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGClient extends FOGBase
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName;
    /**
     * Stores the string data to send
     *
     * @var string
     */
    protected $send;

    /**
     * Initialize the client items
     *
     * @param bool $service if the check is from service directory
     * @param bool $encoded if the data is base64 encoded
     * @param bool $hostnotrequired if the host object is required
     * @param bool $returnmacs if we should only return macs
     * @param bool $override if we are being overriden
     *
     * @return void|false|string
     * @throws Exception
     */
    public function __construct(
        bool $service = true,
        bool $encoded = false,
        bool $hostnotrequired = false,
        bool $returnmacs = false,
        bool $override = false
    ) {
        try {
            parent::__construct();
            global $sub;
            $method = 'json';
            self::getHostItem(
                $service,
                $encoded,
                $hostnotrequired,
                $returnmacs,
                $override
            );
            if (!self::$Host instanceof Host) {
                self::$Host = new Host(0);
            }
            if (self::$Host->isValid()) {
                // The client just spoke to us, so by definition the host is
                // up -- hence the pingstatus reset, which predates this.
                //
                // hostLastCheckin rides the SAME update rather than getting
                // one of its own: this constructor runs on every module
                // request, so a second statement here would double the write
                // rate of the busiest query in the client protocol for a
                // value the first statement can carry for free.
                //
                // Deliberately NOT also stamping lastping. This says the
                // agent is alive and can reach the server, which is a
                // different fact from "FOGPingHosts could open a socket to
                // it", and keeping the two apart is the entire point of
                // having two columns (schema step 353).
                //
                // hostIP rides along too. It is the address this request
                // arrived FROM, which is the only address anything here
                // knows to be real -- the client never tells us its IP and
                // does not need to, so this costs no protocol change and no
                // client release.
                //
                // It is written on every check-in rather than once, because
                // its whole value is being recent: a DHCP lease moves, and a
                // stored address that is not refreshed is how a ping ends up
                // answered by whatever holds the lease now. PingHosts does
                // not trust it on age alone either -- it confirms the MAC
                // answering there is one this host registers before believing
                // the reply. See Ping::identityMatches().
                $update = [
                    'pingstatus' => 0,
                    'lastcheckin' => self::niceDate()->format('Y-m-d H:i:s')
                ];
                // REMOTE_ADDR only. X-Forwarded-For is caller-supplied and
                // would let anything that can reach this endpoint write any
                // address it liked into a field the pinger acts on.
                $peer = filter_var(
                    trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                    FILTER_VALIDATE_IP
                );
                if (false !== $peer) {
                    $update['ip'] = $peer;
                }
                (new HostManager())->update(
                    ['id' => self::$Host->get('id')],
                    '',
                    $update
                );
            }
            $moduleid = filter_input(INPUT_POST, 'moduleid');
            if (!$moduleid) {
                $moduleid = filter_input(INPUT_GET, 'moduleid');
            }
            if ($moduleid) {
                $this->shortName = \Initiator::sanitizeItems(
                    $moduleid
                );
                switch ($this->shortName) {
                    case 'snapin':
                        $this->shortName = 'snapinclient';
                        break;
                }
            }
            $globalInfo = array_intersect_key(
                self::getGlobalModuleStatus(),
                [$this->shortName => '']
            );
            if (!(isset($globalInfo[$this->shortName])
                && $globalInfo[$this->shortName])
            ) {
                throw new \Exception('#!ng');
            }
            // RESOLVED, not host-direct -- see ServiceModule. A module a
            // group grants must let the client reach its endpoint, or the
            // grant is one the server honors and the gate refuses.
            $find = [
                'id' => self::$Host->resolvedModules(),
                'shortName' => $this->shortName
            ];
            $hostModInfo = Route::getIds(
                'module',
                $find,
                'shortName'
            );
            if (!in_array($this->shortName, $hostModInfo)) {
                if (!self::$Host->isValid()
                    && false === $hostnotrequired
                ) {
                    throw new \Exception('#!nh');
                }
            }
            $validClientBrowserFiles = [
                'jobs.php',
                'servicemodule-active.php',
                'snapins.checkin.php',
                'usertracking.report.php',
                'snapins.file.php',
                'register.php',
            ];
            $scriptCheck = basename(self::$scriptname);
            $new = (self::$json || self::$newService);
            if ($new && !in_array($scriptCheck, $validClientBrowserFiles)) {
                throw new \Exception(_('Not allowed here'));
            }
            $jsonSub = (!isset($sub) || $sub !== 'requestClientInfo');
            if ($jsonSub && self::$json) {
                $script = strtolower(self::$scriptname);
                $script = trim($script);
                $script = basename($script);
                if ($script !== 'jobs.php') {
                    throw new \Exception(
                        json_encode(
                            $this->{$method}()
                        )
                    );
                } else {
                    echo json_encode(
                        $this->{$method}()
                    );
                    exit;
                }
            }
            if (self::$json) {
                return json_encode(
                    $this->{$method}()
                );
            }
            $this->{$method}();
            $nonJsonEncode = [
                'autologout',
                'printerclient',
                'servicemodule',
            ];
            // Short name: matched against the bare module names above, which
            // decide whether this module answers JSON or a raw body.
            $lowclass = strtolower(
                self::shortName($this)
            );
            $this->send = trim($this->send);
            if (in_array($lowclass, $nonJsonEncode)) {
                throw new \Exception($this->send);
            }
            $this->sendData($this->send);
        } catch (\Exception $e) {
            global $json;
            global $newService;
            if (!$json && $newService) {
                echo $this->send;
                exit;
            }
            if (!self::$json) {
                return print $e->getMessage();
            }
            $message = $e->getMessage();
            $msg = preg_replace('/^#!?/', '', $message);
            $message = json_encode(
                ['error' => $msg]
            );
            $jsonSub = (!isset($sub) || $sub !== 'requestClientInfo');
            if ($jsonSub && self::$json) {
                return $this->sendData($message);
            }
            return $message;
        }
    }
}
