<?php
/**
 * Image manager mass management class
 *
 * PHP version 7.4+
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Image manager mass management class
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'images';
}
