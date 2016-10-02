<?php
/**
 * Check out other tasks.
 *
 * PHP version 5
 *
 * @category Other_Complete
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Check out other tasks.
 *
 * @category Other_Complete
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
FOGCore::getClass('TaskQueue')
    ->checkout();
