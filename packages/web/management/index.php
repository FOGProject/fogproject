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

// Initialize required classes
$FOGPageManager = FOGCore::getClass('FOGPageManager');

// Get login process
FOGCore::getClass('ProcessLogin')->processMainLogin();

require '../commons/text.php';
$Page = FOGCore::getClass('Page');

// Define allowed nodes
$nodes = array(
    'schema',
    'client'
);

// Handle logout or login nodes
if (isset($node) && in_array($node, ['logout', 'login'])) {
    if ($node === 'logout') {
        $currentUser->logout();
    }
    FOGCore::redirect('../management/index.php');
    exit;
}

// Render login page if user is not valid
if (!isset($node) || (!in_array($node, $nodes) && !$currentUser->isValid())) {
    $Page
        ->setTitle($foglang['Login'])
        ->setSecTitle($foglang['ManagementLogin'])
        ->startBody();
    FOGCore::getClass('ProcessLogin')
        ->mainLoginForm();
    $Page
        ->endBody()
        ->render();
} else {
    // Handle AJAX requests
    if (FOGCore::$ajax) {
        $FOGPageManager->render();
        exit;
    }

    // Render main page content
    $Page->startBody();
    $FOGPageManager->render();
    $Page
        ->setTitle($FOGPageManager->getFOGPageTitle())
        ->setSecTitle($FOGPageManager->getFOGPageName())
        ->endBody()
        ->render();
}
