<?php
/**
 * FileIntegrity Manager class.
 *
 * PHP version 5
 *
 * @category FileIntegrityManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FileIntegrity Manager class.
 *
 * @category FileIntegrityManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileIntegrityManager extends FOGManagerController
{
    /**
     * Install the database and plugin
     *
     * @param string $name the name of the plugin.
     *
     * @return bool
     */
    public function install($name)
    {
        $this->uninstall();
        $sql = "CREATE TABLE `fileChecksums` ("
            . "`fcsID` INTEGER NOT NULL AUTO_INCREMENT,"
            . "`fcsStorageNodeID` INTEGER NOT NULL,"
            . "`fcsFileModTime` DATETIME NOT NULL,"
            . "`fcsFileChecksum` VARCHAR(255) NOT NULL,"
            . "`fcsFilePath` VARCHAR(255) NOT NULL,"
            . "`fcsStatus` ENUM('0','1','2') NOT NULL,"
            . "PRIMARY KEY(`fcsID`),"
            . "UNIQUE INDEX `nodeFiles` (`fcsStorageNodeID`,`fcsFilePath`)"
            . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT "
            . "CHARSET=utf8 ROW_FORMAT=DYNAMIC";
        return self::$DB->query($sql);
    }
    /**
     * Uninstall the database and plugin.
     *
     * @return bool
     */
    public function uninstall()
    {
        return self::$DB->query("DROP TABLE IF EXISTS `fileChecksums`");
    }
}
