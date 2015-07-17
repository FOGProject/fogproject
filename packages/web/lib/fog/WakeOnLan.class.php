<?php
class WakeOnLan extends FOGBase {
    private $arrMAC;
    private $hwaddr;
    private $packet;
    /** __construct($mac)
        Stores the MAC of which to system to wake.
     */
    public function __construct($mac) {
        parent::__construct();
        $this->arrMAC = array();
        foreach ((array)$mac AS $i => &$MAC) {
            $mac = $this->getClass(MACAddress,$MAC);
            if ($mac->isValid()) $this->arrMAC[] = $mac->__toString();
        }
        unset($MAC);
    }
    /** send()
        Creates the packet and sends it to wake up the machine.
     */
    public function send() {
        if (!count($this->arrMAC)) throw new Exception($this->foglang[InvalidMAC]);
        foreach ((array)$this->arrMAC AS $i => &$MAC) {
            $macHex = str_replace(':','',$MAC);
            if (!ctype_xdigit($macHex)) throw new Exception($this->foglang[InvalidMAC]);
            $macBin = pack('H12',$macHex);
            $magicPacket = str_repeat(chr(0xff),6).str_repeat($macBin,16);
            unset($BroadCast,$this->hwaddr,$this->packet);
            // Always send to the main broadcast.
            $BroadCast[] = '255.255.255.255';
            $this->HookManager->processEvent(BROADCAST_ADDR,array(broadcast=>&$BroadCast));
            foreach((array)$BroadCast AS $i => &$SendTo) {
                if (!$sock = fsockopen('udp://'.$SendTo,9,$errNo,$errStr,2)) throw new Exception(_("Cannot open UDP Socket: {$errStr}"),$errNo);
                fputs($sock,$magicPacket);
                fclose($sock);
            }
            unset($SendTo);
        }
        unset($MAC);
        return true;
    }
}
