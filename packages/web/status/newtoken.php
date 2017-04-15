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
$randGen = function () {
    $rand = mt_rand();
    $uniq = uniqid($rand, true);

    return md5($uniq);
};
$token = sprintf(
    '%s%s',
    $randGen(),
    $randGen()
);
echo json_encode(
    base64_encode(bin2hex($token))
);
exit;
