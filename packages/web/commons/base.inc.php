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
// HSTS must only be sent over HTTPS (RFC 6797 §8.1). Sending it over HTTP
// causes browsers to cache the upgrade directive and silently convert
// subsequent HTTP requests to HTTPS, breaking dual HTTP+HTTPS setups.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}
// frame-src allows browser-extension overlays (password managers such as
// Bitwarden inject chrome-extension:/moz-extension: iframes). Without it these
// fall back to default-src 'none' and are blocked, which makes those
// extensions retry/stall for minutes against the page. Arbitrary web frames
// stay blocked.
header("Content-Security-Policy: default-src 'none'; script-src 'self'; connect-src 'self' https://fogproject.org; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src chrome-extension: moz-extension:; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");

// Include required initialization script.
require 'init.php';

// Output buffering with custom output sanitization for performance and security.
ob_start(['Initiator', 'sanitizeOutput']);
Initiator::startInit();

// Load global constants and functions.
require BASEPATH . "commons/text.php";
new LoadGlobals();
