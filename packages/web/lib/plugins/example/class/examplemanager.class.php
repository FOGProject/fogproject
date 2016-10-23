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
         * $sql = "CREATE TABLE `example` ("
         *     . "`eID` INTEGER NOT NULL AUTO_INCREMENT,"
         *     . "`eName` VARCHAR(250) NOT NULL,"
         *     . "`eOther` VARCHAR(250) NOT NULL,"
         *     . "`eHostID` INTEGER NOT NULL,"
         *     . "PRIMARY KEY(`eID`),"
         *     . "INDEX `new_index` (`eHostID`)"
         *     . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT '
         *     . 'CHARSET=utf8 ROW_FORMAT=DYNAMIC';
         * // ACTUALLY CREATES THE DATABASE TABLE FROM ABOVE
         * if (self::$DB->query($sql)) {
         *     // IF NEEDED CREATE GLOBAL ENTRIES
         *     $Example1 = new Service(
         *         array(
         *             'name' => 'FOG_EXAMPLE_ONE',
         *             'description' => 'Example one global entry.',
         *             'value' => '',
         *             'category' => 'Plugin: '.$name,
         *         )
         *     );
         *     // SAVE THE NEW ENTRY
         *     $Example1->save();
         *     return true;
         * }
         * return false;
         */
        return true;
    }
    /**
     * Uninstalls the plugin.
     * Typically used to remove database and other entries needed.
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }
}
