<?php
/**
 * TasktypeeditManager
 *
 * PHP version 5
 *
 * @category TaskypeeditManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * TasktypeeditManager
 *
 * @category TaskypeeditManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TasktypeeditManager extends TaskTypeManager
{
    /**
     * Install the plugin, table already exists.
     *
     * @return bool
     */
    public function install()
    {
        return true;
    }
    /**
     * Uninstall the plugin, but we don't uninstall real data.
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }
}
