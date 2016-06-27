<?php
class MACAddress extends FOGBase {
    private static $pattern = '/^(?:[[:xdigit:]]{2}([-:]))(?:[[:xdigit:]]{2}\1){4}[[:xdigit:]]{2}$|^(?:[[:xdigit:]]{12})$|^(?:[[:xdigit:]]{4}([.])){2}[[:xdigit:]]{4}$/';
    protected $MAC;
    protected $tmpMAC;
    private $Host;
    public function __construct($MAC) {
        parent::__construct();
        $this->tmpMAC = $MAC;
        $this->setMAC();
    }
    protected function setMAC() {
        try {
            if ($this->tmpMAC instanceof MACAddress) $this->MAC = self::normalizeMAC($this->tmpMAC);
            else if (is_array($this->tmpMAC)) $this->MAC = self::normalizeMAC($this->tmpMAC[0]);
            else $this->MAC = self::normalizeMAC($this->tmpMAC);
            if (!$this->isValid()) throw new Exception("#!im\n");
        } catch (Exception $e) {
            if (self::$debug) self::$FOGCore->debug($e->getMessage().' MAC: %s', $this->MAC);
        }
        return $this;
    }
    protected static function normalizeMAC($MAC) {
        $MAC = preg_grep(self::$pattern,(array)$MAC);
        if (count($MAC) !== 1) return '';
        return strtolower(str_replace(array('.','-',':'),'',array_shift($MAC)));
    }
    public function getMACPrefix() {
        return join('-',str_split(substr($this->MAC,0,6),2));
    }
    public function __toString() {
        return join(':',str_split($this->MAC,2));
    }
    public function isValid() {
        return (bool)preg_match(self::$pattern,$this->MAC);
    }
    public function isPending() {
        return (bool)count(self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'pending'=>(string)1)));
    }
    public function isClientIgnored() {
        return (bool)count(self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'clientIgnore'=>(string)1)));
    }
    public function isPrimary() {
        return (bool)count(self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'primary'=>(string)1)));
    }
    public function isImageIgnored() {
        return (bool)count(self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'imageIgnore'=>(string)1)));
    }
    public function getHost() {
        return self::getClass('Host',@max(self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString()),'hostID')));
    }
}
