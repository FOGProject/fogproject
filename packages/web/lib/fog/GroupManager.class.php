<?php
class GroupManager extends FOGManagerController
{
	public $loadQueryTemplate = "SELECT *,COUNT(`groupMembers`.`gmHostID`) groupMemberCount FROM `%s` %s %s %s GROUP BY `groupName` %s %s";
	public $loadQueryGroupTemplate = "SELECT *,COUNT(`groupMembers`.`gmHostID`) groupMemberCount FROM (SELECT %s,COUNT(`groupMembers`.`gmHostID`) groupMemberCount FROM `%s` %s %s %s %s %s) `%s` %s %s %s GROUP BY `groupname` %s %s";
}
