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
        if (!is_array($mac) && strpos('|',$mac)) $mac = explode('|',$mac);
        foreach ((array)$mac AS $i => &$MAC) {
            $mac = $this->getClass(MACAddress,$MAC);
            $this->arrMAC[] = strtolower($MAC);
        }
        unset($MAC);
    }
    /** send()
        Creates the packet and sends it to wake up the machine.
     */
    public function send() {
        if (!count($this->arrMAC)) throw new Exception($this->foglang[InvalidMAC]);
        foreach ((array)$this->arrMAC AS $i=>&$MAC) {
            $macHex = str_replace(':','',str_replace('-','',$MAC));
            $macBin = pack('H12',$macHex);
            $magicPacket = str_repeat(chr(0xff),6).str_repeat($macBin,16);
            // Always send to the main broadcast.
            $BroadCast[] = '255.255.255.255';
            $BroadCast[] = $this->FOGCore->getBroadcast();
            $this->HookManager->processEvent(BROADCAST_ADDR,array(broadcast=>&$BroadCast));
            foreach((array)$BroadCast AS $i => &$SendTo) {
                foreach ((array)$SendTo AS $i => &$bcaddr) {
                    if (!($sock = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP))) throw new Exception(_('Socket error'));
                    $options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
                    if ($options >= 0 && socket_sendto($sock,$magicPacket,(int)strlen($magicPacket),0,$bcaddr,9)) socket_close($sock);
                }
            }
            unset($SendTo,$BroadCast);
        }
        unset($MAC);
    }
}
