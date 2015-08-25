<?php
class MACAddress extends FOGBase {
    /** $MAC the MAC to test */
    private $MAC;
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
    public function setMAC() {
        try {
            if (is_object($this->tmpMAC)) $MAC = trim(($this->tmpMAC instanceof MACAddress) ? strtolower($this->tmpMAC) : (($this->tmpMAC instanceof MACAddressAssociation) ? strtolower($this->tmpMAC->get(mac)) : ''));
            else if (is_array($this->tmpMAC)) $MAC = trim($MAC[0]);
            else if (strlen($this->tmpMAC) == 12) {
                for ($i=0;$i<12;$i=$i+2) $newMAC[] = $this->tmpMAC{$i}.$this->tmpMAC{$i+1};
                $MAC = implode(':',$newMAC);
            } else if (strlen($this->tmpMAC) == 17) $MAC = str_replace('-', ':', $this->tmpMAC);
            else $MAC = $this->tmpMAC;
            $this->MAC = $MAC;
			if (!$this->isValid()) throw new Exception("#!im\n");
        } catch (Exception $e) {
            if ($this->debug) $this->FOGCore->debug($e->getMessage().' MAC: %s',$MAC);
        }
        return $this;
    }
    /** getMACPrefix() get the MACs prefix
     * @return the prefix
     */
    public function getMACPrefix() {
        $tmpMAC = strtolower($this->MAC);
        $tmpMAC = substr(str_replace(array(':','-'),'',$tmpMAC),0,8);
        return join('-',str_split($tmpMAC,2));
    }
    /** __toString() Magic method to return the string as defined
     * @return the mac address with colons
     */
    public function __toString() {
        $tmpMAC = strtolower($this->MAC);
        $tmpMAC = str_replace(array(':','-'),'',$tmpMAC);
        return join(':',str_split($tmpMAC,2));
    }
    /** isValid() returns if the mac is valid
     * @return true or false
     */
    public function isValid() {
        return preg_match('/([a-fA-F0-9]{2}[-:]){5}[0-9A-Fa-f]{2}|([0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4}/',$this->MAC);
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
