<?php
/**
 * Checks credentials for init based calls
 *
 * PHP version 5
 *
 * @category CheckCredentials
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Checks credentials for init based calls
 *
 * @category CheckCredentials
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $username = trim($_REQUEST['username']);
    $username = base64_decode($username);
    $username = trim($username);
    $password = trim($_REQUEST['password']);
    $password = base64_decode($password);
    $password = trim($password);
    $userTest = FOGCore::getClass('User')
        ->passwordValidate($username, $password);
    if (!$userTest) {
        throw new Exception('#!il');
    }
    echo '#!ok';
} catch (Exception $e) {
    echo $e->getMessage();
}
