<?php
class Ping {
    private $host;
    private $port = '445';	// Microsoft netbios port
    private $timeout;
    public function __construct($host, $timeout=2, $port = '445') {
        $this->host = $host;
        $this->timeout = $_REQUEST['timeout'];
        $this->port = $port;
    }
    public function execute() {
        if ($this->timeout > 0 && $this->host != null) return $this->fsockopenPing();
    }
    private function fsockopenPing() {
        $file = @fsockopen($this->host,$this->port,$errno,$errstr,$this->timeout);
        if ($file) @stream_set_blocking($file,false);
        $status = 0;
        !$file ? $status = 111 : @fclose($file);
        // 110 = ETIMEDOUT = Connection timed out
        // 111 = ECONNREFUSED = Connection refused
        // 112 = EHOSTDOWN = Host is down
        return ($errorCode === 0 || !in_array($errorno,array(110,111,112)) ? true : $errstr);
    }
}
