<?php
/**
 * Base that commonizes the requirements of FOG.
 *
 * PHP version 5
 *
 * @category Base
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Base that commonizes the requirements of FOG.
 *
 * @category Base
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Setup our more secure friendly header information.
 */
header('X-Frame-Options: sameorigin');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000');
header(
    "Content-Security-Policy: default-src 'none';"
    . "script-src 'self' 'unsafe-eval';"
    . "connect-src 'self';"
    . "img-src 'self' data:;"
    . "style-src 'self' 'unsafe-inline';"
    . "font-src 'self';"
);
header('Access-Control-Allow-Origin: *');
/**
 * Our required files, text for language and init to initialize system.
 */
require 'text.php';
require 'init.php';
/**
 * All output should be sanitized for faster browser experience.
 */
ob_start(array('Initiator', 'sanitizeOutput'));
Initiator::sanitizeItems();
Initiator::startInit();
new LoadGlobals();
