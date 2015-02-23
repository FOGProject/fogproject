<?php
class GroupManager extends FOGManagerController
{
	public $loadQueryTemplate = "SELECT *,COUNT(`groupMembers`.`gmHostID`) groupMemberCount FROM `%s` %s %s %s GROUP BY `groupName` %s %s";
}
