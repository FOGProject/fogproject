<?php
class HostnameChanger extends FOGClient implements FOGClientSend
{
    public function json()
    {
        $password = $this->aesdecrypt($this->Host->get('ADPass'));
        $productKey = $this->aesdecrypt($this->Host->get('productKey'));
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username, chr(92)) || strpos($username, chr(64))) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf('%s\%s', $this->Host->get('ADDomain'), $username);
        } else {
            $adUser = '';
        }
        $this->Host->setAD();
        $val = array(
            'enforce' => (bool)$this->Host->get('enforce'),
            'hostname' => (string)$this->Host->get('name'),
            'AD' => (bool)$this->Host->get('useAD'),
            'ADDom' => $this->Host->get('useAD') ? (string)$this->Host->get('ADDomain') : '',
            'ADOU' => $this->Host->get('useAD') ? str_replace(';', '', $this->Host->get('ADOU')) : '',
            'ADUser' => $this->Host->get('useAD') ? (string)$adUser : '',
            'ADPass' => $this->Host->get('useAD') ? (string)$password : '',
        );
        if ($productKey) {
            $val['Key'] = $productKey;
        }
        return $val;
    }
    public function send()
    {
        ob_start();
        echo '#!ok';
        $productKey = $this->aesdecrypt($this->Host->get('productKey'));
        if ($this->newService) {
            $password = $this->aesdecrypt($this->Host->get('ADPass'));
            printf("\n#hostname=%s\n", $this->Host->get('name'));
        } else {
            $password = $this->Host->get('ADPassLegacy');
            printf("=%s\n", $this->Host->get('name'));
        }
        $this->Host->setAD();
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username, chr(92)) || strpos($username, chr(64))) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf('%s\%s', $this->Host->get('ADDomain'), $username);
        } else {
            $adUser = '';
        }
        printf(
            "#AD=%s\n#ADDom=%s\n#ADOU=%s\n#ADUser=%s\n#ADPass=%s%s",
            $this->Host->get('useAD'),
            $this->Host->get('ADDomain'),
            str_replace(';', '', $this->Host->get('ADOU')),
            $adUser,
            $password,
            $this->newService ? sprintf("\n#enforce=%s", $this->Host->get('enforce')) : ''
        );
        if ($productKey) {
            printf("\n#Key=%s", $productKey);
        }
        $this->send = ob_get_clean();
    }
}
