<?php
/**
 * Manager class for pushbullet
 *
 * PHP Version 5
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for pushbullet
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletManager extends FOGManagerController
{
    /**
     * Perform the database and plugin installation
     *
     * @param string $name the name of the plugin
     *
     * @return bool
     */
    public function install($name)
    {
        $this->uninstall();
        $sql = "CREATE TABLE `pushbullet` ("
            . "`pID` INTEGER NOT NULL AUTO_INCREMENT,"
            . "`pToken` VARCHAR(250) NOT NULL,"
            . "`pName` VARCHAR(250) NOT NULL,"
            . "`pEmail` VARCHAR(250) NOT NULL,"
            . "PRIMARY KEY(`pID`),"
            . "UNIQUE INDEX `token` (`pToken`)"
            . ") ENGINE=MyISAM AUTO_INCREMENT=1 "
            . "DEFAULT CHARSET=utf8 ROW FORMAT=DYNAMIC";
        return self::$DB->query($sql);
    }
    /**
     * Remove the database and uninstall
     * the plugin.
     *
     * @return bool
     */
    public function uninstall()
    {
        return self::$DB->query("DROP TABLE IF EXISTS `pushbullet`");
    }
}
