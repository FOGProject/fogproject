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
        $password = self::aesdecrypt(
            self::$Host->get('ADPass')
        );
        $productKey = self::aesdecrypt(
            self::$Host->get('productKey')
        );
        $username = trim(
            self::$Host->get('ADUser')
        );
        if (strpos($username, chr(92))
            || strpos($username, chr(64))
        ) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf(
                '%s\%s',
                self::$Host->get('ADDomain'),
                $username
            );
        } else {
            $adUser = '';
        }
        $AD = (bool)self::$Host->get('useAD');
        $enforce = (bool)self::$Host->get('enforce');
        $hostname = self::$Host->get('name');
        $ADDom = '';
        $ADOU = '';
        $ADUser = '';
        $ADPass = '';
        if ($AD === true) {
            $ADDom = self::$Host->get('ADDomain');
            $ADOU = str_replace(
                ';',
                '',
                self::$Host->get('ADOU')
            );
            $ADUser = $adUser;
            $ADPass = $password;
        }
        self::$Host->setAD();
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
        $password = self::$Host->get('ADPassLegacy');
        printf(
            "=%s\n",
            self::$Host->get('name')
        );
        self::$Host->setAD();
        $username = trim(
            self::$Host->get('ADUser')
        );
        if (strpos($username, chr(92))
            || strpos($username, chr(64))
        ) {
            $adUser = $username;
        } elseif ($username) {
            $adUser = sprintf(
                '%s\%s',
                self::$Host->get('ADDomain'),
                $username
            );
        } else {
            $adUser = '';
        }
        $AD = (bool)self::$Host->get('useAD');
        $hostname = self::$Host->get('name');
        $ADDom = '';
        $ADOU = '';
        $ADUser = '';
        $ADPass = '';
        if ($AD === true) {
            $AD = 1;
            $ADDom = self::$Host->get('ADDomain');
            $ADOU = str_replace(
                ';',
                '',
                self::$Host->get('ADOU')
            );
            $ADUser = $adUser;
            $ADPass = $password;
        }
        self::$Host->setAD();
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
