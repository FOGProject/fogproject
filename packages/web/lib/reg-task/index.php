<?php
/**
 * Redirects calls to lib/reg-task/index.php to main page.
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
 * Redirects calls to lib/reg-task/index.php to main page.
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
header('Location: ../../management/index.php', true, 308);
exit;
