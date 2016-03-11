<?php
class HostnameChanger extends FOGClient implements FOGClientSend {
    public function send() {
        ob_start();
        echo '#!ok';
        $productKey = $this->aesdecrypt($this->Host->get('productKey'));
        if ($this->newService) {
            $password = $this->aesdecrypt($this->Host->get('ADPass'));
            printf("\n#hostname=%s\n",$this->Host->get('name'));
        } else {
            $password = $this->Host->get('ADPassLegacy');
            printf("=%s\n",$this->Host->get('name'));
        }
        $this->Host->setAD();
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username,chr(92)) || strpos($username,chr(64))) $adUser = $username;
        else if ($username) $adUser = sprintf('%s\%s',$this->Host->get('ADDomain'),$username);
        else $adUser = '';
        printf("#AD=%s\n#ADDom=%s\n#ADOU=%s\n#ADUser=%s\n#ADPass=%s",
            $this->Host->get('useAD'),
            $this->Host->get('ADDomain'),
            $this->Host->get('ADOU'),
            $adUser,
            $password
        );
        if ($productKey) printf("\n#Key=%s",$productKey);
        $this->send = ob_get_clean();
        if ($this->json) {
            $val = array(
                'enforce' => (bool)$this->Host->get('enforce'),
                'hostname' => $this->Host->get('name'),
                'AD' => $this->Host->get('useAD'),
                'ADDom' => $this->Host->get('useAD') ? $this->Host->get('ADDomain') : '',
                'ADOU' => $this->Host->get('useAD') ? $this->Host->get('ADOU') : '',
                'ADUser' => $this->Host->get('useAD') ? $adUser : '',
                'ADPass' => $this->Host->get('useAD') ? $password : '',
            );
            return $val;
        }
    }
}
