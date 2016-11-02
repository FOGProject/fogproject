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
     * Stores the string data to send
     *
     * @var string
     */
    protected $send;
    /**
     * Stores the host item
     *
     * @var object
     */
    protected $Host;
    /**
     * Initialize the client items
     *
     * @param bool $service         if the check is from service directory
     * @param bool $encoded         if the data is base64 encoded
     * @param bool $hostnotrequired if the host object is required
     * @param bool $returnmacs      if we should only return macs
     * @param bool $override        if we are being overriden
     *
     * @return void
     */
    public function __construct(
        $service = true,
        $encoded = false,
        $hostnotrequired = false,
        $returnmacs = false,
        $override = false
    ) {
        try {
            parent::__construct();
            global $sub;
            global $json;
            $method = 'send';
            if (self::$json && method_exists($this, 'json')) {
                $method = 'json';
            }
            $this->Host = $this->getHostItem(
                $service,
                $encoded,
                $hostnotrequired,
                $returnmacs,
                $override
            );
            $validClientBrowserFiles = array(
                'jobs.php',
                'servicemodule-active.php',
                'snapins.checkin.php',
                'usertracking.report.php',
                'snapins.file.php',
                'register.php',
            );
            $scriptCheck = basename(self::$scriptname);
            $new = (self::$json || self::$newService);
            if ($new && !in_array($scriptCheck, $validClientBrowserFiles)) {
                throw new Exception(_('Not allowed here'));
            }
            $jsonSub = (!isset($sub) || $sub !== 'requestClientInfo');
            if ($jsonSub && self::$json) {
                throw new Exception(
                    json_encode(
                        $this->{$method}()
                    )
                );
            }
            if (self::$json) {
                return json_encode(
                    $this->{$method}()
                );
            }
            $this->{$method}();
            $nonJsonEncode = array(
                'autologout',
                'displaymanager',
                'printerclient',
                'servicemodule',
            );
            $lowclass = strtolower(
                get_class($this)
            );
            $this->send = trim($this->send);
            if (in_array($lowclass, $nonJsonEncode)) {
                throw new Exception($this->send);
            }
            $this->sendData($this->send);
        } catch (Exception $e) {
            if (!self::$json) {
                return print $e->getMessage();
            }
            $message = $e->getMessage();
            if (json_last_error() !== JSON_ERROR_NONE) {
                $msg = preg_replace('/^[#][!]?/', '', $message);
                $message = json_encode(
                    array('error' => $msg)
                );
            }
            $jsonSub = (!isset($sub) || $sub !== 'requestClientInfo');
            if ($jsonSub && self::$json) {
                return print $message;
            }
            return $message;
        }
    }
}
