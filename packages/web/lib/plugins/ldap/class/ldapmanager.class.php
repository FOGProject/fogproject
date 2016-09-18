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
            `lsName` VARCHAR(250) NOT NULL,
            `lsDesc` longtext NOT NULL,
            `lsCreatedBy` VARCHAR(30) NOT NULL,
            `lsAddress` VARCHAR(30) NOT NULL,
            `lsCreatedTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `lsUserSearchDN` VARCHAR(100) NOT NULL,
            `lsPort` INTEGER NOT NULL,
            `lsUserNamAttr` VARCHAR(15) NOT NULL,
            `lsGrpMemberAttr` VARCHAR(15) NOT NULL,
            `lsAdminGroup` VARCHAR(30) NOT NULL,
            `lsUserGroup` VARCHAR(30) NOT NULL,
            `lsSearchScope` ENUM('0','1') NOT NULL DEFAULT '0',
            `lsBindDN` VARCHAR(30) NOT NULL,
            `lsBindPwd` VARCHAR(30) NOT NULL,
            PRIMARY KEY(`lsID`),
        KEY new_index (`lsName`))
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
