<?php
/**
 * This presents the advanced menu
 *
 * PHP version 7.4+
 *
 * @category Advanced
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * This presents the advanced menu
 *
 * @category Advanced
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../../commons/base.inc.php';
header('Content-type: text/plain');
/**
 * Parses the statements to print the advanced menu
 *
 * @param array $Send the data to be parsed
 *
 * @return void
 */
$parseMe = function ($Send) {
    foreach ($Send as $ipxe => $val) {
        printf("%s\n", implode("\n", (array)$val));
    }
};
/**
 * Prompt for a credential and re-chain carrying it.
 *
 * iPXE holds no cookie, so there is no session in which a previous login
 * could be remembered. The credential and the decision to emit the menu
 * therefore have to happen in the SAME request -- which is why the old
 * 'loginsuccess' branch, which chained back here with no credential at
 * all, could never have gated anything.
 *
 * @return void
 */
$promptForLogin = function () use ($parseMe) {
    $parseMe(
        [
            'loginstuff' => [
                '#!ipxe',
                'clear username',
                'clear password',
                'login',
                'params',
                'param username ${username}',
                'param password ${password}',
                'chain ${boot-url}/service/ipxe/advanced.php##params',
            ]
        ]
    );
    exit;
};
$login = isset($_REQUEST['login']);
$user = trim($_REQUEST['username'] ?? '');
$pass = trim($_REQUEST['password'] ?? '');
if ($login) {
    $promptForLogin();
}
/**
 * FOG_ADVANCED_MENU_LOGIN is what the admin sets to mean "the advanced
 * menu requires a login". It defaults to 0 (schema.php:1871), so the menu
 * is open unless it was deliberately turned on, and that default is
 * preserved here.
 *
 * It was, however, never enforced by the file that actually serves the
 * menu: the printf below sat outside every conditional, so FOG_PXE_ADVANCED
 * was emitted to any caller regardless of the setting or of any credential.
 * The login was decorative in three separate ways -- the setting was never
 * read here, attemptLogin() returns a User OBJECT on both paths so the old
 * `if ($tmp)` could never be false, and the success branch built $Send but
 * never passed it to $parseMe.
 */
if (FOGCore::getSetting('FOG_ADVANCED_MENU_LOGIN')) {
    if ('' === $user) {
        $promptForLogin();
    }
    // authenticateOnly, not attemptLogin: nothing here can carry a session
    // cookie, so establishing one would leave an authenticated session that
    // no request will ever present back. isValid() is the real test.
    if (!FOGCore::authenticateOnly($user, $pass)->isValid()) {
        $parseMe(
            [
                'loginfail' => [
                    '#!ipxe',
                    'clear username',
                    'clear password',
                    'echo Invalid login!',
                    'sleep 3',
                    'chain -ar ${boot-url}/service/ipxe/advanced.php',
                ]
            ]
        );
        exit;
    }
}
printf(
    "#!ipxe\n%s",
    FOGCore::getSetting('FOG_PXE_ADVANCED')
);
