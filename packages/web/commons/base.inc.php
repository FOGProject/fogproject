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
header('X-Frame-Options: sameorigin');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000');
header('Access-Control-Allow-Origin: *');
require 'text.php';
require 'init.php';
ob_start(array('Initiator', 'sanitizeOutput'));
