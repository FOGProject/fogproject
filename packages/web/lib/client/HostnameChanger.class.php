<?php
class HostnameChanger extends FOGClient implements FOGClientSend {
    public function send() {
        parent::__construct();
        sleep(15);
        $this->send = '#!ok';
        $password = $this->Host->get(ADPassLegacy);
        if ($this->newService) {
            $this->send .= "\n#hostname=".$this->Host->get(name)."\n";
            $password = $this->aesdecrypt($this->Host->get(ADPass));
            $this->Host->setAD();
        }
        else $this->send .= '='.$this->Host->get(name)."\n";
        $adUser = (strpos($this->Host->get(ADUser),chr(92)) || strpos($this->Host->get(ADUser),chr(64)) ? $this->Host->get(ADUser) : $this->Host->get(ADDomain).chr(92).$this->Host->get(ADUser));
        $this->send .= '#AD='.$this->Host->get(useAD)."\n";
        if ($this->Host->get(useAD)) {
            $this->send .= '#ADDom='.$this->Host->get(ADDomain)."\n";
            $this->send .= '#ADOU='.$this->Host->get(ADOU)."\n";
            $this->send .= '#ADUser='.$adUser."\n";
            $this->send .= '#ADPass='.$password;
            $productKey = trim(base64_decode($this->Host->get(productKey)));
            if ($productKey) $this->send .= "\n#Key=".$productKey;
        }
    }
}
