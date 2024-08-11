<?php
declare(strict_types=1);

/**
 * Redirects calls to index.php to the main page.
 *
 * PHP version 7.4+
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 * @version  1.1
 */

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');

// Perform the redirect
header('Location: ./management/index.php', true, 308);
exit;
