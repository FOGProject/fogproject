<?php
declare(strict_types=1);

/**
 * Base that commonizes the requirements of FOG.
 *
 * @category Base
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// Set security-related headers.
header('X-Frame-Options: sameorigin');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000');
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-eval' 'unsafe-inline'; connect-src 'self' https://fogproject.org; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self' data:;");

// Include required initialization script.
require 'init.php';

// Output buffering with custom output sanitization for performance and security.
ob_start(['Initiator', 'sanitizeOutput']);
Initiator::sanitizeItems();
Initiator::startInit();

// Load global constants and functions.
require BASEPATH . "commons/text.php";
new LoadGlobals();
