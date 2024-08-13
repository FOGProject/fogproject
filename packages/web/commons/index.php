<?php
declare(strict_types=1);

/**
 * Redirects calls to commons/index.php to the main page.
 *
 * This script uses HTTP status codes defined in the HTTPResponseCodes class
 * to redirect users permanently to the management/index.php page.
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once 'base.inc.php';
http_response_code(HTTPResponseCodes::HTTP_PERMANENT_REDIRECT);
header('Location: ../management/index.php', true, HTTPResponseCodes::HTTP_PERMANENT_REDIRECT);
exit;
