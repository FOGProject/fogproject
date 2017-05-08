<?php
/**
 * The example mass manager class.
 *
 * PHP version 5
 *
 * @category ExampleManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The example mass manager class.
 *
 * @category ExampleManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ExampleManager extends FOGManagerController
{
    /**
     * Installs the database for the plugin.
     *
     * @param string $name the name of the plugin.
     *
     * @return bool
     */
    public function install($name)
    {
        /**
         * Add the information into the database.
         * This is commented out so we don't actually
         * create anything.
         *
         * $sql = Schema::createTable(
         *     'example',
         *     true,
         *     array(
         *         'eID',
         *         'eName',
         *         'eOther',
         *         'eHostID'
         *     ),
         *     array(
         *         'INTEGER',
         *         'VARCHAR(255)',
         *         'VARCHAR(255)',
         *         'INTEGER'
         *     ),
         *     array(
         *         false,
         *         false,
         *         false,
         *         false
         *     ),
         *     array(
         *         false,
         *         false,
         *         false,
         *         false
         *     ),
         *     array(
         *         'eID',
         *         'eHostID'
         *     ),
         *     'MyISAM',
         *     'utf8',
         *     'eID',
         *     'eID'
         * );
         * if (!self::$DB->query($sql)) {
         *     return false;
         * }
         * if (self::$DB->query($sql)) {
         *     self::getClass('Service')
         *         ->set('name', 'FOG_EXAMPLE_ONE')
         *         ->set('description', 'Example one global description')
         *         ->set('value', 'Some value')
         *         ->set('category', 'Plugin: example')
         *         ->save();
         *     return true;
         * }
         * return false;
         */
        return true;
    }
    /**
     * Uninstalls.
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }
}
