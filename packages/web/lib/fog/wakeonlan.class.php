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
        if ($this->arrMAC === false || !count($this->arrMAC)) throw new Exception($this->foglang['InvalidMAC']);
        $BroadCast = array_merge((array)'255.255.255.255',$this->FOGCore->getBroadcast());
        $this->HookManager->processEvent('BROADCAST_ADDR',array('broadcast'=>&$BroadCast));
        foreach ((array)$this->arrMAC AS $i => &$MAC) {
            $magicPacket = sprintf('%s%s',str_repeat(chr(255),6),str_repeat(pack('H12',str_replace(array('-',':'),$MAC)),16));
            foreach ((array)$BroadCast AS $i => &$SendTo) {
                if (!($sock = @socket_create(AF_INET,SOCK_DGRAM,SOL_UDP))) throw new Exception(_('Socket error'));
                $options = @socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
                if ($options >= 0 && @socket_sendto($sock,$magicPacket,strlen($magicPacket),0,$SendTo,self::WOL_UDP_PORT)) @socket_close($sock);
                if (false !== ($fp = @fsockopen(sprintf('udp://%s',$SendTo),self::WOL_UDP_PORT,$errno,$errstr,2))) {
                    @fputs($fp, $magicPacket);
                    @fclose($fp);
                }
                unset($SendTo);
            }
            unset($BroadCast,$MAC);
        }
    }
}
