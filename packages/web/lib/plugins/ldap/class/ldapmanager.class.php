<?php
/**
 * LDAPManager
 *
 * PHP version 5
 *
 * @category LDAPManager
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAPManager
 *
 * @category LDAP
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPManager extends FOGManagerController
{
    /**
     * Install the plugin, creates the table for us.
     *
     * @param string $name the name of the plugin
     *
     * @return bool
     */
    public function install($name)
    {
        $this->uninstall();
        $sql = "CREATE TABLE `LDAPServers`
            (`lsID` INTEGER NOT NULL AUTO_INCREMENT,
            `lsName` VARCHAR(255) NOT NULL,
            `lsDesc` LONGTEXT NOT NULL,
            `lsCreatedBy` VARCHAR(40) NOT NULL,
            `lsAddress` VARCHAR(255) NOT NULL,
            `lsCreatedTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `lsUserSearchDN` LONGTEXT NOT NULL,
            `lsPort` INTEGER NOT NULL,
            `lsUserNamAttr` VARCHAR(255) NOT NULL,
            `lsGrpMemberAttr` VARCHAR(255) NOT NULL,
            `lsAdminGroup` LONGTEXT NOT NULL,
            `lsUserGroup` LONGTEXT NOT NULL,
            `lsSearchScope` ENUM('0','1','2') NOT NULL DEFAULT '0',
            `lsBindDN` LONGTEXT NOT NULL,
            `lsBindPwd` LONGTEXT NOT NULL,
            PRIMARY KEY(`lsID`),
            KEY `address` (`lsAddress`,`lsPort`),
            KEY `name` (`lsName`))
        ENGINE = MyISAM";
        return self::$DB->query($sql);
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        return self::$DB->query("DROP TABLE IF EXISTS `LDAPServers`");
    }
}
