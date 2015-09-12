<?php
class MACAddress extends FOGBase {
    private $patterns = array(
        '/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/',
        '/^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$/',
        '/^[a-fA-F0-9]{12}$/',
        '/^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/',
    );
    /** $MAC the MAC to test */
    protected $MAC;
    /** $tmpMAC the temp mac */
    protected $tmpMAC;
    /** $Host the Host if used */
    private $Host;
    /** __construct() initiates
     * @param $MAC the mac either string or object
     * @return void
     */
    public function __construct($MAC) {
        parent::__construct();
        $this->tmpMAC = $MAC;
        $this->setMAC();
    }
    /** setMAC() sets the mac
     * @return class back
     */
    protected function setMAC() {
        try {
            if ($this->tmpMAC instanceof MACAddress) $this->MAC = self::normalizeMAC($this->tmpMAC);
            else if ($this->tmpMAC instanceof MACAddressAssociation) $this->MAC = self::normalizeMAC($this->tmpMAC->get(mac));
            else if (is_array($this->tmpMAC)) $this->MAC = self::normalizeMAC($this->tmpMAC[0]);
            else $this->MAC = self::normalizeMAC($this->tmpMAC);
            if (!$this->isValid()) throw new Exception("#!im\n");
        } catch (Exception $e) {
            if ($this->debug) $this->FOGCore->debug($e->getMessage().' MAC: %s', $this->MAC);
        }
        return $this;
    }
    /** normalizeMAC()
     * @param $MAC the mac to normalize
     * @return the normalized MAC
     */
    protected static function normalizeMAC($MAC) {
        $hexDigits = preg_replace('/[^[:xdigit:]]/','',$MAC);
        if (strlen($hexDigits) !== 12) throw new Exception("#!im\n");
        return strtolower($hexDigits);
    }
    /** getMACPrefix() get the MACs prefix
     * @return the prefix
     */
    public function getMACPrefix() {
        return join('-',str_split(substr($this->MAC,0,6),2));
    }
    /** __toString() Magic method to return the string as defined
     * @return the mac address with colons
     */
    public function __toString() {
        return join(':',str_split($this->MAC,2));
    }
    /** isValid() returns if the mac is valid
     * @return true or false
     */
    public function isValid() {
        return preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', $this->MAC) || preg_match('/^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$/', $this->MAC) || preg_match('/^[a-fA-F0-9]{12}$/', $this->MAC) || preg_match('/^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/', $this->MAC) ? true : false;
    }
    public function isPending() {
        $MAC = $this->getClass(MACAddressAssociationManager)->find(array(mac=>$this->MAC));
        $MAC = @array_shift($MAC);
        return $MAC && $MAC->isValid() && $MAC->get(pending);
    }
    public function isClientIgnored() {
        $MAC = $this->getClass(MACAddressAssociationManager)->find(array(mac=>$this->MAC));
        $MAC = @array_shift($MAC);
        return $MAC && $MAC->isValid() && $MAC->get(clientIgnore);
    }
    public function isPrimary() {
        $MAC = $this->getClass(MACAddressAssociationManager)->find(array(mac=>$this->MAC));
        $MAC = @array_shift($MAC);
        return $MAC && $MAC->isValid() && $MAC->get(primary);
    }
    public function isImageIgnored() {
        $MAC = $this->getClass(MACAddressAssociationManager)->find(array(mac=>$this->MAC));
        $MAC = @array_shift($MAC);
        return $MAC && $MAC->isValid() && $MAC->get(imageIgnore);
    }
}
