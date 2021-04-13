<?php
/**
 * Redirects calls to commons/index.php to main page.
 *
 * PHP version 5
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Redirects calls to commons/index.php to main page.
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
http_response_code(HTTPResponseCodes::HTTP_PERMANENT_REDIRECT);
header('Location: ../management/index.php', true, 308);
exit;
