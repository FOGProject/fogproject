<?php
/**
 * A mac address verifier and getter.
 *
 * PHP version 5
 *
 * @category MACAddress
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * A mac address verifier and getter.
 *
 * @category MACAddress
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
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
        /**
         * Defines our grep/search patterns for
         * validating a MAC Address.
         */
        self::$_pattern = sprintf(
            '%s%s%s%s',
            '/^(?:[[:xdigit:]]{2}([-:]))',
            '(?:[[:xdigit:]]{2}\1){4}[[:xdigit:]]{2}$',
            '|^(?:[[:xdigit:]]{12})$|^(?:[[:xdigit:]]',
            '{4}([.])){2}[[:xdigit:]]{4}$/'
        );
        /**
         * Pull in our base initialized items.
         */
        parent::__construct();
        /**
         * Temp storage of our passed mac address.
         */
        $this->tmpMAC = $mac;
        /**
         * Makes our required changes to the mac.
         *
         * @throws Exception
         */
        $this->setMAC();
        /**
         * If the mac is already registered, sets our
         * MAC Variable to the instance of the db item.
         */
        $this->_MAC = self::getClass('MACAddressAssociation')
            ->set('mac', $this->__toString())
            ->load('mac');
    }
    /**
     * Sets the mac.
     *
     * @throws Exception
     * @return object
     */
    protected function setMAC()
    {
        try {
            /**
             * If the mac is already an instance of MACAddress,
             * MACAddressAssociation, or plain string, normalize
             * our protected MAC as lowercase without ., -, or :.
             *
             * If the mac is an array, take the first mac address and
             * perform the same as above.
             */
            if ($this->tmpMAC instanceof MACAddressAssociation) {
                $this->MAC = $this->tmpMAC->get('mac');
            } elseif (is_array($this->tmpMAC)) {
                $this->MAC = $this->tmpMAC[0];
            } else {
                $this->MAC = $this->tmpMAC;
            }
            $this->MAC = self::normalizeMAC($this->MAC);
            /**
             * If the mac address is not valid throw Invalid MAC message.
             */
            if (!$this->isValid()) {
                throw new Exception("#!im\n");
            }
            /**
             * Split the normalized mac into an array using 2 characters
             * as the split. For example:
             * 012345abcdef would become
             * ['01','23,'45','ab','cd','ef']
             */
            $splitter = str_split($this->MAC, 2);
            /**
             * This creates a portion of the message string for WOL requests.
             */
            foreach ((array) $splitter as &$split) {
                $hwAddr .= chr(hexdec($split));
                unset($split);
            }
            /**
             * This creates and combines the message string.
             * Message string is:
             * 6 times inline 0xff
             * 16 times hwAddr
             */
            $this->_msg = sprintf(
                '%s%s',
                str_repeat(chr(255), 6),
                str_repeat($hwAddr, 16)
            );
        } catch (Exception $e) {
            self::$FOGCore->debug(
                sprintf(
                    '%s MAC: %s',
                    $e->getMessage(),
                    $this->MAC
                )
            );
        }
        $hwAddr = '';
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
        /**
         * Pull out the valid mac addresses in an array format.
         */
        $mac = array_values(
            preg_grep(
                self::$_pattern,
                (array) $mac
            )
        );
        /**
         * Remove the :, -, and/or . and lowercase the
         * characters. Return the string.
         */
        return strtolower(
            str_replace(
                array(
                    ':',
                    '-',
                    '.'
                ),
                '',
                $mac[0]
            )
        );
    }
    /**
     * Gets the first 6 characters of the mac.
     *
     * @return string
     */
    public function getMACPrefix()
    {
        /**
         * Gets the prefix of the mac address.
         */
        /**
         * Splits into the segments for easier joining.
         * For example, 012345 will become:
         * ['01','23','45']
         */
        /**
         * OUI text is in format xx-xx-xx,
         * This puts the string together properly so
         * string will become:
         * 01-23-45
         */
        /**
         * Returns our string.
         */
        return implode(
            '-',
            str_split(
                substr(
                    $this->MAC,
                    0,
                    6
                ),
                2
            )
        );
    }
    /**
     * How to present the mac as a string.
     *
     * @return string
     */
    public function __toString()
    {
        /**
         * Splits the mac to be an array for joining together easier.
         * For example, mac 012345abcdef will become:
         * ['01','23','45','ab','cd','ef'].
         */
        /**
         * Joins the array with colons so our mac will be like:
         * 01:23:45:ab:cd:ef
         */
        /**
         * Returns our string
         */
        return implode(
            ':',
            str_split(
                $this->MAC,
                2
            )
        );
    }
    /**
     * Tests if the mac is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        /**
         * Tests the pattern to see if we are safe.
         * Returns as bool.
         */
        return (bool) preg_match(
            self::$_pattern,
            $this->MAC
        );
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
     * @return bool
     */
    public function wake($ip, $port = 9)
    {
        /**
         * Assume return will be true for now.
         */
        $ret = true;
        /**
         * Create our socket resource
         */
        $sock = socket_create(
            AF_INET,
            SOCK_DGRAM,
            SOL_UDP
        );
        /**
         * If failed, immediately return.
         */
        if ($sock == false) {
            return false;
        }
        /**
         * Set our coket options
         */
        $set_opt = socket_set_option(
            $sock,
            SOL_SOCKET,
            SO_BROADCAST,
            true
        );
        /**
         * If invalid close socket and return immediately.
         */
        if ($set_opt < 0) {
            socket_close($sock);
            return false;
        }
        /**
         * Send our wake up packet.
         */
        $sendto = socket_sendto(
            $sock,
            $this->_msg,
            strlen($this->_msg),
            0,
            $ip,
            $port
        );
        /**
         * If failed set return to false;
         */
        if (!$sendto) {
            $ret = false;
        }
        /**
         * Close the socket.
         */
        socket_close($sock);
        /**
         * Return value
         */
        return $ret;
    }
}
