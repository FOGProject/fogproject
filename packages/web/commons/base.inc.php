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

use FOG\Base\LoadGlobals;

// The empty needle must short-circuit to true, matching PHP 8's str_contains()
// -- "" is contained in every string. It cannot be left to strpos(), which on
// 7.4 rejects an empty needle with a warning and returns false; that is what
// made this polyfill disagree with the native function it stands in for.
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// str_starts_with() is PHP 8.0+, but _verCheck() admits 7.4 and the installer
// takes the distro-default PHP with no version floor (Ubuntu 20.04 = 7.4.3).
// SnapinClient::json() calls it, and an undefined-function fatal there is a
// zero-byte 500 the FOG Client reads as a transport failure -- snapins just
// silently never deploy. Same bug class as the `mixed` hint in init.php.
// Refs forums.fogproject.org topic 18204.
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
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
// Per-request nonce so the small pre-paint inline script (dark-mode no-flash
// resolver in management/other/index.php) can run under a strict script-src
// without opening the policy to all inline scripts. Stamped on that <script>
// via the FOG_CSP_NONCE constant.
define('FOG_CSP_NONCE', base64_encode(random_bytes(16)));
// frame-src allows browser-extension overlays (password managers such as
// Bitwarden inject chrome-extension:/moz-extension: iframes). Without it these
// fall back to default-src 'none' and are blocked, which makes those
// extensions retry/stall for minutes against the page. Arbitrary web frames
// stay blocked.
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'nonce-" . FOG_CSP_NONCE . "'; connect-src 'self' https://fogproject.org; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src chrome-extension: moz-extension:; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");

// Include required initialization script.
require 'init.php';

// Output buffering with custom output sanitization for performance and security.
ob_start(['Initiator', 'sanitizeOutput']);
Initiator::startInit();

// Load global constants and functions.
require BASEPATH . "commons/text.php";
new LoadGlobals();
