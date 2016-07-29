<?php
class WakeOnLan extends FOGBase {
    const WOL_UDP_PORT = 9;
    private $arrMAC;
    private $hwaddr;
    private $packet;
    public function __construct($mac) {
        parent::__construct();
        $this->arrMAC = $this->parseMacList($mac,true);
    }
    public function send() {
        if ($this->arrMAC === false || !count($this->arrMAC)) throw new Exception(self::$foglang['InvalidMAC']);
        $BroadCast = array_merge((array)'255.255.255.255',self::$FOGCore->getBroadcast());
        self::$HookManager->processEvent('BROADCAST_ADDR',array('broadcast'=>&$BroadCast));
        $sendWOL = function(&$SendTo) use (&$packet) {
            if (!($sock = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP))) throw new Exception(_('Socket error'));
            $options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
            if ($options >= 0 && socket_sendto($sock,$packet,strlen($packet),0,$SendTo,self::WOL_UDP_PORT)) socket_close($sock);
            unset($SendTo);
        };
        array_map(function(&$MAC) use (&$packet,$BroadCast,$sendWOL) {
            $packet = sprintf('%s%s',str_repeat(chr(255),6),str_repeat(pack('H12',str_replace(array('-',':'),'',$MAC)),16));
            array_map($sendWOL,(array)$BroadCast);
            unset($MAC);
        },(array)$this->arrMAC);
    }
}
