<?php
/**
 * A mac address verifier and getter.
 *
 * PHP version 5
 *
 * @category MACAddress
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
/**
 * A mac address verifier and getter.
 *
 * PHP version 5
 *
 * @category MACAddress
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
class MACAddress extends FOGBase
{
    /**
     * Pattern to validate MACAddresses.
     *
     * @var string
     */
    private static $_pattern = '';
    /**
     * This msg packet.
     *
     * @var string
     */
    private $_msg = '';
    /**
     * The host object storage.
     *
     * @var object
     */
    private $_Host = null;
    /**
     * The stored MAC Association if found.
     *
     * @var object
     */
    private $_MAC = null;
    /**
     * Individual MAC Storage.
     *
     * @var string
     */
    protected $MAC;
    /**
     * Temporary mac store.
     *
     * @var mixed
     */
    protected $tmpMAC;
    /**
     * Initializes the mac address class.
     *
     * @param string $mac the mac item(s)
     */
    public function __construct($mac)
    {
        self::$_pattern = '/^(?:[[:xdigit:]]{2}([-:]))';
        self::$_pattern .= '(?:[[:xdigit:]]{2}\1){4}[[:xdigit:]]{2}$';
        self::$_pattern .= '|^(?:[[:xdigit:]]{12})$|^(?:[[:xdigit:]]';
        self::$_pattern .= '{4}([.])){2}[[:xdigit:]]{4}$/';
        parent::__construct();
        $this->tmpMAC = $mac;
        $this->setMAC();
        $this->_MAC = self::getClass('MACAddressAssociation')
            ->set('mac', $this->__toString())
            ->load('mac');
    }
    /**
     * Sets the mac.
     *
     * @throws Exception
     *
     * @return object
     */
    protected function setMAC()
    {
        try {
            if ($this->tmpMAC instanceof self) {
                $this->MAC = self::normalizeMAC($this->tmpMAC);
            } elseif ($this->tmpMAC instanceof MACAddressAssociation) {
                $this->MAC = self::normalizeMAC($this->tmpMAC->get('mac'));
            } elseif (is_array($this->tmpMAC)) {
                $this->MAC = self::normalizeMAC($this->tmpMAC[0]);
            } else {
                $this->MAC = self::normalizeMAC($this->tmpMAC);
            }
            if (!$this->isValid()) {
                throw new Exception("#!im\n");
            }
            $splitter = str_split($this->MAC, 2);
            foreach ((array) $splitter as &$split) {
                $hwAddr .= chr(hexdec($split));
                unset($split);
            }
            $this->_msg = sprintf(
                '%s%s',
                str_repeat(chr(255), 6),
                str_repeat($hwAddr, 16)
            );
        } catch (Exception $e) {
            if (self::$debug) {
                self::$FOGCore->debug($e->getMessage().' MAC: %s', $this->MAC);
            }
        }

        return $this;
    }
    /**
     * Normalizes the mac address for us and lowers the case.
     *
     * @param string $mac The mac to normalize
     *
     * @return string
     */
    protected static function normalizeMAC($mac)
    {
        $mac = preg_grep(self::$_pattern, (array) $mac);
        if (count($mac) !== 1) {
            return '';
        }
        $mac = array_shift($mac);
        $mac = str_replace(
            array(
                '.',
                '-',
                ':',
            ),
            '',
            $mac
        );
        $mac = strtolower($mac);

        return $mac;
    }
    /**
     * Gets the first 6 characters of the mac.
     *
     * @return string
     */
    public function getMACPrefix()
    {
        $strMod = substr(
            $this->MAC,
            0,
            6
        );
        $strMod = str_split($strMod, 2);
        $strMod = implode('-', $strMod);

        return $strMod;
    }
    /**
     * How to present the mac as a string.
     *
     * @return string
     */
    public function __toString()
    {
        $strMod = str_split($this->MAC, 2);
        $strMod = implode(':', $strMod);

        return $strMod;
    }
    /**
     * Tests if the mac is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return (bool) preg_match(self::$_pattern, $this->MAC);
    }
    /**
     * Tests if mac is a pending mac.
     *
     * @return bool
     */
    public function isPending()
    {
        return (bool) $this->_MAC->isPending();
    }
    /**
     * Tests if mac is to be ignored for client.
     *
     * @return bool
     */
    public function isClientIgnored()
    {
        return (bool) $this->_MAC->isClientIgnored();
    }
    /**
     * Tests if mac is primary mac.
     *
     * @return bool
     */
    public function isPrimary()
    {
        return (bool) $this->_MAC->isPrimary();
    }
    /**
     * Tests if mac is to be ignored for imaging.
     *
     * @return bool
     */
    public function isImageIgnored()
    {
        return (bool) $this->_MAC->isImageIgnored();
    }
    /**
     * Gets mac's associated host.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->_MAC->getHost();
    }
    /**
     * Wakes this MAC address.
     *
     * @param string $ip   the ip to send to
     * @param int    $port the port to sent from
     *
     * @return bool|void
     */
    public function wake($ip, $port = 9)
    {
        $sock = @socket_create(
            AF_INET,
            SOCK_DGRAM,
            SOL_UDP
        );
        if ($sock == false) {
            return false;
        }
        $set_opt = @socket_set_option(
            $sock,
            SOL_SOCKET,
            SO_BROADCAST,
            true
        );
        if ($set_opt < 0) {
            return false;
        }
        $sendto = @socket_sendto(
            $sock,
            $this->_msg,
            strlen($this->_msg),
            0,
            $ip,
            $port
        );
        if ($sendto) {
            @socket_close($sock);
        }
    }
}
