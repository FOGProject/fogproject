<?php
/**
 * Base element for client services
 *
 * PHP version 5
 *
 * @category FOGClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
                self::getClass('HostManager')->update(
                    ['id' => self::$Host->get('id')],
                    '',
                    ['pingstatus' => 0]
                );
            }
            $moduleid = filter_input(INPUT_POST, 'moduleid');
            if (!$moduleid) {
                $moduleid = filter_input(INPUT_GET, 'moduleid');
            }
            if ($moduleid) {
                $this->shortName = Initiator::sanitizeItems(
                    $moduleid
                );
                switch ($this->shortName) {
                    case 'dircleaner':
                        $this->shortName = 'dircleanup';
                        break;
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
                throw new Exception('#!ng');
            }
            $find = [
                'id' => self::$Host->get('modules'),
                'shortName' => $this->shortName
            ];
            Route::ids(
                'module',
                $find,
                'shortName'
            );
            $hostModInfo = json_decode(Route::getData(), true);
            if (!in_array($this->shortName, $hostModInfo)) {
                if (!self::$Host->isValid()
                    && false === $hostnotrequired
                ) {
                    throw new Exception('#!nh');
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
                throw new Exception(_('Not allowed here'));
            }
            $jsonSub = (!isset($sub) || $sub !== 'requestClientInfo');
            if ($jsonSub && self::$json) {
                $script = strtolower(self::$scriptname);
                $script = trim($script);
                $script = basename($script);
                if ($script !== 'jobs.php') {
                    throw new Exception(
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
                'displaymanager',
                'printerclient',
                'servicemodule',
            ];
            $lowclass = strtolower(
                get_class($this)
            );
            $this->send = trim($this->send);
            if (in_array($lowclass, $nonJsonEncode)) {
                throw new Exception($this->send);
            }
            $this->sendData($this->send);
        } catch (Exception $e) {
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
