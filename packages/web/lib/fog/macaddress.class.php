<?php
class MACAddress extends FOGBase {
    private $patterns = array(
        '/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/',
        '/^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$/',
        '/^[a-fA-F0-9]{12}$/',
        '/^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/',
    );
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
        $hexDigits = preg_replace('/[^[:xdigit:]]/','',$MAC);
        if (strlen($hexDigits) !== 12) throw new Exception("#!im\n");
        return strtolower($hexDigits);
    }
    public function getMACPrefix() {
        return join('-',str_split(substr($this->MAC,0,6),2));
    }
    public function __toString() {
        return join(':',str_split($this->MAC,2));
    }
    public function isValid() {
        $mac = str_replace(array(':','-','.'),'',$this->MAC);
        return strlen($mac) === 12 && ctype_xdigit($mac) && (preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', $this->MAC) || preg_match('/^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$/', $this->MAC) || preg_match('/^[a-fA-F0-9]{12}$/', $this->MAC) || preg_match('/^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/', $this->MAC));
    }
    public function isPending() {
        return (bool)count($this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'pending'=>1)));
    }
    public function isClientIgnored() {
        return (bool)count($this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'clientIgnore'=>1)));
    }
    public function isPrimary() {
        return (bool)count($this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'primary'=>1)));
    }
    public function isImageIgnored() {
        return (bool)count($this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString(),'imageIgnore'=>1)));
    }
    public function getHost() {
        return self::getClass('Host',@max($this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$this->__toString()),'hostID')));
    }
}
