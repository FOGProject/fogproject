<?php
/**
 * Sends the client with the hostname and domain
 * information needed to perform the client actions.
 *
 * PHP version 7.4+
 *
 * @category HostnameChanger
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Agent\DirectoryJoin;

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
class HostnameChanger extends FOGClient
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
        $password = self::$Host->get('ADPass');
        $passwordtest = self::aesdecrypt($password);
        if ($test_base64 = base64_decode($passwordtest)) {
            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                $password = $test_base64;
            }
        } elseif (mb_detect_encoding($passwordtest, 'utf-8', true)) {
            $password = $passwordtest;
        }
        $productKey = self::$Host->get('productKey');
        $productKeytest = self::aesdecrypt($productKey);
        if ($test_base64 = base64_decode($productKeytest)) {
            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                $productKey = $test_base64;
            }
        } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
            $productKey = $productKeytest;
        }
        // The account for the host's domain, which a plugin may supply
        // (GH-1887). Asked only when the host joins at all, so a host with
        // useAD off never fires the event.
        $username = (string)self::$Host->get('ADUser');
        if ((bool)self::$Host->get('useAD')) {
            list($username, $password) = DirectoryJoin::account(
                self::$Host,
                $username,
                $password
            );
        }
        $username = trim($username);
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
        $val = [
            'enforce' => $enforce,
            'hostname' => $hostname,
            'AD' => (bool)$AD,
            'ADDom' => $ADDom,
            'ADOU' => $ADOU,
            'ADUser' => $ADUser,
            'ADPass' => $ADPass
        ];
        if ($productKey) {
            $val['Key'] = $productKey;
        }
        self::$HookManager->processEvent(
            'HOSTNAME_CHANGER_CLIENT',
            [
                'val' => &$val,
                'Host' => &self::$Host
            ]
        );
        return $val;
    }
}
