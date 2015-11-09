<?php
class HostnameChanger extends FOGClient implements FOGClientSend {
    public function send() {
        $this->send = '#!ok';
        $password = $this->Host->get('ADPassLegacy');
        if ($this->newService) {
            $this->send .= "\n#hostname=".$this->Host->get('name')."\n";
            $password = $this->aesdecrypt($this->Host->get('ADPass'));
            $productKey = $this->aesdecrypt($this->Host->get('productKey'));
            $this->Host->setAD();
        }
        else $this->send .= '='.$this->Host->get('name')."\n";
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username,chr(92)) || strpos($username,chr(64))) $adUser = $username;
        else if ($username) $adUser = sprintf('%s\%s',$this->Host->get('ADDomain'),$username);
        else $adUser = '';
        $this->send .= '#AD='.$this->Host->get('useAD')."\n";
        if (!$this->newService || $this->Host->get('useAD')) {
            $this->send .= '#ADDom='.$this->Host->get('ADDomain')."\n";
            $this->send .= '#ADOU='.$this->Host->get('ADOU')."\n";
            $this->send .= '#ADUser='.$this->DB->sanitize($adUser)."\n";
            $this->send .= '#ADPass='.$this->DB->sanitize($password);
            if ($productKey) $this->send .= "\n#Key=".$productKey;
        }
    }
}
