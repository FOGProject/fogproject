<?php
/**
 * The main index presenter
 *
 * PHP version 5
 *
 * @category Index_Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The main index presenter
 *
 * @category Index_Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$FOGPageManager = FOGCore::getClass('FOGPageManager');
if (session_status() != PHP_SESSION_NONE) {
    if (isset($_SESSION['delitems'])
        && !in_array($sub, ['deletemulti', 'deleteconf'])
    ) {
        unset($_SESSION['delitems']);
    }
}
ob_start();
FOGCore::getClass('ProcessLogin')->processMainLogin();
$login = ob_get_clean();
require '../commons/text.php';
$Page = FOGCore::getClass('Page');
$nodes = [
    'schema',
    'client',
    'ipxe'
];
if ($node == 'logout') {
    $currentUser->logout();
    FOGCore::redirect('../management/index.php');
}
if ($node == 'login') {
    FOGCore::redirect('../management/index.php');
}
if (!in_array($node, $nodes)
    && !$currentUser->isValid()
) {
    $Page
        ->setTitle($foglang['Login'])
        ->setSecTitle($foglang['ManagementLogin'])
        ->startBody();
    echo $login;
    $Page
        ->endBody()
        ->render();
} else {
    if (FOGCore::$ajax) {
        $FOGPageManager->render();
        exit;
    }
    $Page->startBody();
    $FOGPageManager->render();
    $Page->setTitle($FOGPageManager->getFOGPageTitle());
    $Page->setSecTitle($FOGPageManager->getFOGPageName());
    $Page
        ->endBody()
        ->render();
}
