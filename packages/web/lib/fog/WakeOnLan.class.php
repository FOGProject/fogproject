<?php
class WakeOnLan extends FOGBase {
    private $arrMAC;
    private $hwaddr;
    private $packet;
    public function __construct($mac) {
        parent::__construct();
        $this->arrMAC = $this->parseMacList($mac,true);
    }
    public function send() {
        if ($this->arrMAC === false || !count($this->arrMAC)) throw new Exception($this->foglang['InvalidMAC']);
        foreach ((array)$this->arrMAC AS $i => &$MAC) {
            $macHex = explode(':',$MAC);
            $magicPacket = $hw_addr = '';
            foreach ($macHex AS $i => &$hex) $hw_addr .= chr(hexdec($hex));
            unset($hex);
            $magicPacket .= str_repeat(chr(255),6).str_repeat($hw_addr,16);
            $BroadCast[] = '255.255.255.255';
            $BroadCast[] = $this->FOGCore->getBroadcast();
            $this->HookManager->processEvent('BROADCAST_ADDR',array('broadcast'=>&$BroadCast));
            foreach((array)$BroadCast AS $i => &$SendTo) {
                foreach ((array)$SendTo AS $i => &$bcaddr) {
                    if (!($sock = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP))) throw new Exception(_('Socket error'));
                    socket_set_nonblock($sock);
                    $options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
                    if ($options >= 0 && socket_sendto($sock,$magicPacket,(int)strlen($magicPacket),0,$bcaddr,9)) socket_close($sock);
                    unset($bcaddr);
                }
                unset($SendTo);
            }
            unset($BroadCast,$MAC);
        }
    }
}
