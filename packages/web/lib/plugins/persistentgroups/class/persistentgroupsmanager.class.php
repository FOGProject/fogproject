<?php
/**
 * Enables persistent groups.
 *
 * PHP version 7.4+
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
        $sql = "CREATE OR REPLACE TRIGGER `persistentGroups` 
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
            FROM `locationAssoc` WHERE `laHostID`=@myTemplateID
            ON DUPLICATE KEY UPDATE `laHostID`=VALUES(`laHostID`),`laLocationID`=VALUES(`laLocationID`);
        END IF;

        INSERT INTO `printerAssoc` (`paHostID`,`paPrinterID`,`paIsDefault`,`paAnon1`,`paAnon2`,`paAnon3`,`paAnon4`,`paAnon5`)
            SELECT @myHostID as `paHostID`,`paPrinterID`,`paIsDefault`,`paAnon1`,`paAnon2`,`paAnon3`,`paAnon4`,`paAnon5`
            FROM `printerAssoc` WHERE `paHostID`=@myTemplateID
            ON DUPLICATE KEY UPDATE `paHostID`=VALUES(`paHostID`),`paPrinterID`=VALUES(`paPrinterID`),`paIsDefault`=VALUES(`paIsDefault`),`paAnon1`=VALUES(`paAnon1`),`paAnon2`=VALUES(`paAnon2`),`paAnon3`=VALUES(`paAnon3`),`paAnon4`=VALUES(`paAnon4`),`paAnon5`=VALUES(`paAnon5`);

        SET @myDBTest = (SELECT count(`saSnapinID`) FROM `snapinAssoc` WHERE `saHostID`=@myTemplateID LIMIT 1);
        IF (@myDBTest > 0) THEN
            INSERT INTO `snapinAssoc` (`saHostID`,`saSnapinID`)
                SELECT @myHostID as `saHostID`,`saSnapinID` 
                FROM `snapinAssoc` WHERE `saHostID`=@myTemplateID
                ON DUPLICATE KEY UPDATE `saHostID`=VALUES(`saHostID`),`saSnapinID`=VALUES(`saSnapinID`);

            -- Check if there's already an active snapin job for this host
            SET @existingJob = (SELECT `sjID` FROM `snapinJobs` WHERE `sjHostID`=@myHostID AND `sjStateID` IN (1,2) LIMIT 1);
            
            IF (@existingJob IS NULL) THEN
                INSERT INTO `snapinJobs` (`sjHostID`,`sjStateID`,`sjCreateTime`)
                    VALUES (@myHostID, 1, NOW());
                SET @mysjID = LAST_INSERT_ID();
            ELSE
                SET @mysjID = @existingJob;
            END IF;
            
            -- Add tasks for any snapins that don't already have tasks in this job
            INSERT INTO `snapinTasks` (`stJobID`,`stState`,`stCheckinDate`,`stSnapinID`)
                SELECT @mysjID as `stJobID`, 1 as `stState`, NOW() as `stCheckinDate`, `saSnapinID` as `stSnapinID`
                FROM `snapinAssoc` WHERE `saHostID`=@myHostID
                AND `saSnapinID` NOT IN (SELECT `stSnapinID` FROM `snapinTasks` WHERE `stJobID`=@mysjID);
        END IF;

        INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`)
            SELECT @myHostID as `msHostID`,`msModuleID`,`msState`
            FROM `moduleStatusByHost` WHERE `msHostID`=@myTemplateID
            ON DUPLICATE KEY UPDATE `msHostID`=VALUES(`msHostID`),`msModuleID`=VALUES(`msModuleID`),`msState`=VALUES(`msState`);

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
