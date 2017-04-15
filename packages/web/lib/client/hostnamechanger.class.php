<?php
/**
 * Sends the client with the hostname and domain
 * information needed to perform the client actions.
 *
 * PHP version 5
 *
 * @category HostnameChanger
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends the client with the hostname and domain
 * information needed to perform the client actions.
 *
 * @category HostnameChanger
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostnameChanger extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'hostnamechanger';
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        $password = self::aesdecrypt($this->Host->get('ADPass'));
        $productKey = self::aesdecrypt($this->Host->get('productKey'));
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username, chr(92))
            || strpos($username, chr(64))
        ) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf(
                '%s\%s',
                $this->Host->get('ADDomain'),
                $username
            );
        } else {
            $adUser = '';
        }
        $AD = (bool)$this->Host->get('useAD');
        $enforce = (bool)$this->Host->get('enforce');
        $hostname = $this->Host->get('name');
        $ADDom = '';
        $ADOU = '';
        $ADUser = '';
        $ADPass = '';
        if ($AD === true) {
            $ADDom = $this->Host->get('ADDomain');
            $ADOU = str_replace(
                ';',
                '',
                $this->Host->get('ADOU')
            );
            $ADUser = $adUser;
            $ADPass = $password;
        }
        $this->Host->setAD();
        $val = array(
            'enforce' => (bool)$enforce,
            'hostname' => $hostname,
            'AD' => (bool)$AD,
            'ADDom' => $ADDom,
            'ADOU' => $ADOU,
            'ADUser' => $ADUser,
            'ADPass' => $ADPass
        );
        if ($productKey) {
            $val['Key'] = $productKey;
        }
        return $val;
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        ob_start();
        echo '#!ok';
        $password = $this->Host->get('ADPassLegacy');
        printf(
            "=%s\n",
            $this->Host->get('name')
        );
        $this->Host->setAD();
        $username = trim($this->Host->get('ADUser'));
        if (strpos($username, chr(92))
            || strpos($username, chr(64))
        ) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf(
                '%s\%s',
                $this->Host->get('ADDomain'),
                $username
            );
        } else {
            $adUser = '';
        }
        $AD = (bool)$this->Host->get('useAD');
        $hostname = $this->Host->get('name');
        $ADDom = '';
        $ADOU = '';
        $ADUser = '';
        $ADPass = '';
        if ($AD === true) {
            $AD = 1;
            $ADDom = $this->Host->get('ADDomain');
            $ADOU = str_replace(
                ';',
                '',
                $this->Host->get('ADOU')
            );
            $ADUser = $adUser;
            $ADPass = $password;
        }
        $this->Host->setAD();
        printf(
            "#AD=%s\n#ADDom=%s\n#ADOU=%s\n#ADUser=%s\n#ADPass=%s",
            $AD,
            $ADDom,
            $ADOU,
            $ADUser,
            $ADPass
        );
        $this->send = ob_get_clean();
    }
}
