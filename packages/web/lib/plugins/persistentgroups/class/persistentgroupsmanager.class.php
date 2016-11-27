<?php
/**
 * Enables persistent groups.
 *
 * PHP version 5
 *
 * @category PersistentGroupsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The example mass manager class.
 *
 * Enables persistent groups.
 *
 * @category PersistentGroupsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PersistentGroupsManager extends FOGManagerController
{
    /**
     * Installs the database for the plugin.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = "CREATE TRIGGER `persistentGroups` 
            AFTER INSERT ON `groupMembers` 
            FOR EACH ROW
                BEGIN

                SET @myHostID = `NEW`.`gmHostID`;
        SET @myGroupID = `NEW`.`gmGroupID`;

        SET @myTemplateID = (SELECT `hostID` FROM `groups` INNER JOIN `hosts` ON (`groupName` = `hostName`) WHERE `groupID`=@myGroupID);

        IF (@myTemplateID IS NOT NULL) AND (@myHostID <> @myTemplateID) THEN
            UPDATE `hosts` `d`, (SELECT `hostImage`, `hostBuilding`, `hostUseAD`, `hostADDomain`, `hostADOU`, 
            `hostADUser`, `hostADPass`, `hostADPassLegacy`, `hostProductKey`, `hostPrinterLevel`, `hostKernelArgs`, 
            `hostExitBios`, `hostExitEfi`, `hostEnforce` FROM `hosts` WHERE `hostID`=@myTemplateID) `s`
            SET `d`.`hostImage`=`s`.`hostImage`, `d`.`hostBuilding`=`s`.`hostBuilding`, `d`.`hostUseAD`=`s`.`hostUseAD`, `d`.`hostADDomain`=`s`.`hostADDomain`,
            `d`.`hostADOU`=`s`.`hostADOU`, `d`.`hostADUser`=`s`.`hostADUser`, `d`.`hostADPass`=`s`.`hostADPass`, `d`.`hostADPassLegacy`=`s`.`hostADPassLegacy`,
            `d`.`hostProductKey`=`s`.`hostProductKey`, `d`.`hostPrinterLevel`=`s`.`hostPrinterLevel`, `d`.`hostKernelArgs`=`s`.`hostKernelArgs`,
            `d`.`hostExitBios`=`s`.`hostExitBios`, `d`.`hostExitEfi`=`s`.`hostExitEfi`, `d`.`hostEnforce`=`s`.`hostEnforce`
            WHERE `d`.`hostID`=@myHostID;

        SET @myDBTest = (SELECT count(`table_name`) FROM information_schema.tables WHERE `table_schema` = 'fog' AND `table_name` = 'locationAssoc' LIMIT 1);
        if (@myDBTest > 0) THEN
            INSERT INTO `locationAssoc` (`laHostID`,`laLocationID`)
            SELECT @myHostID as `laHostID`,`laLocationID`
            FROM `locationAssoc` WHERE `laHostID`=@myTemplateID;
        END IF;

        INSERT INTO `printerAssoc` (`paHostID`,`paPrinterID`,`paIsDefault`,`paAnon1`,`paAnon2`,`paAnon3`,`paAnon4`)
            SELECT @myHostID as `paHostID`,`paPrinterID`,`paIsDefault`,`paAnon1`,`paAnon2`,`paAnon3`,`paAnon4`
            FROM `printerAssoc` WHERE `paHostID`=@myTemplateID;

        INSERT INTO `snapinAssoc` (`saHostID`,`saSnapinID`)
            SELECT @myHostID as `saHostID`,`saSnapinID` 
            FROM `snapinAssoc` WHERE `saHostID`=@myTemplateID;

        INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`)
            SELECT @myHostID as `msHostID`,`msModuleID`,`msState`
            FROM `moduleStatusByHost` WHERE `msHostID`=@myTemplateID;

        END IF;

        END;";
        return self::$DB->query($sql);
    }
    /**
     * Uninstalls the plugin.
     * Drops our trigger.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = 'DROP TRIGGER IF EXISTS `persistentGroups`';
        return self::$DB->query($sql);
    }
}
