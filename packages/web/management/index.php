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
if (isset($_SESSION['delitems'])
    && !in_array($sub, array('deletemulti', 'deleteconf'))
) {
    unset($_SESSION['delitems']);
}
FOGCore::getClass('ProcessLogin')->processMainLogin();
require '../commons/text.php';
$Page = FOGCore::getClass('Page');
if (!in_array($node, array('schema', 'client'))
    && ($node == 'logout' || !$currentUser->isValid())
) {
    $currentUser->logout();
    $Page->setTitle($foglang['Login']);
    $Page->setSecTitle($foglang['ManagementLogin']);
    $Page->startBody();
    FOGCore::getClass('ProcessLogin')->mainLoginForm();
    $Page->endBody();
    $Page->render();
} else {
    $_SESSION['AllowAJAXTasks'] = true;
    if (FOGCore::$ajax) {
        $FOGPageManager->render();
        exit;
    }
    $Page->startBody();
    $FOGPageManager->render();
    $Page->setTitle($FOGPageManager->getFOGPageTitle());
    $Page->setSecTitle($FOGPageManager->getFOGPageName());
    $Page->endBody();
    $Page->render();
}
foreach (array_keys($GLOBALS) as $var) {
    unset($$var);
}
