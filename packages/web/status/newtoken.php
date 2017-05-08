<?php
/**
 * Generates a new token on ajax request.
 *
 * PHP Version 5
 *
 * @category NewToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
/**
 * Generates a new token on ajax request.
 *
 * PHP Version 5
 *
 * @category NewToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
/**
 * Lambda to create random data.
 *
 * @return string
 */
require '../commons/base.inc.php';
return print json_encode(
    base64_encode(
        FOGCore::createSecToken()
    )
);
