<?php
class HostnameChanger extends FOGClient implements FOGClientSend {
    public function send() {
        $this->send = '#!ok';
        $password = $this->Host->get(ADPassLegacy);
        if ($this->newService) {
            $this->send .= "\n#hostname=".$this->Host->get(name)."\n";
            $password = $this->aesdecrypt($this->Host->get(ADPass));
            $this->Host->setAD();
        }
        else $this->send .= '='.$this->Host->get(name)."\n";
        if (strpos($this->Host->get(ADUser),array(chr(92),chr(64)))) $adUser = $this->Host->get(ADUser);
        else if (trim($this->Host->get(ADUser))) $adUser = sprintf('%s\%s',$this->Host->get(ADDomain),trim($this->Host->get(ADUser)));
        else $adUser = '';
        $this->send .= '#AD='.$this->Host->get(useAD)."\n";
        if (!$this->newService || $this->Host->get(useAD)) {
            $this->send .= '#ADDom='.$this->Host->get(ADDomain)."\n";
            $this->send .= '#ADOU='.$this->Host->get(ADOU)."\n";
            $this->send .= '#ADUser='.$adUser."\n";
            $this->send .= '#ADPass='.$password;
            $productKey = trim(base64_decode($this->Host->get(productKey)));
            if ($productKey) $this->send .= "\n#Key=".$productKey;
        }
    }
}
