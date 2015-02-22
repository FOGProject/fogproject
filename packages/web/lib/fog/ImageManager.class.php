<?php
class ImageManager extends FOGManagerController
{
	public $loadQueryTemplate = "SELECT *,GROUP_CONCAT(DISTINCT `nfsGroups`.`ngName` ORDER BY `nfsGroups`.`ngName`) storageName,`imageTypes`.`imageTypeName` imageTypeName,`os`.`osName` imageOSName,`imagePartitionTypes`.`imagePartitionTypeName` imagePartName FROM `%s` LEFT OUTER JOIN `imageGroupAssoc` ON `imageGroupAssoc`.`igaImageID`=`images`.`imageID` LEFT OUTER JOIN `nfsGroups` ON `imageGroupAssoc`.`igaStorageGroupID`=`nfsGroups`.`ngID` LEFT OUTER JOIN `imageTypes` ON `images`.`imageTypeID`=`imageTypes`.`imageTypeID` LEFT OUTER JOIN `os` ON `os`.`osID`=`images`.`imageOSID` LEFT OUTER JOIN `imagePartitionTypes` ON `imagePartitionTypes`.`imagePartitionTypeID`=`images`.`imagePartitionTypeID` %s %s %s GROUP BY `imageName` %s %s";
}
