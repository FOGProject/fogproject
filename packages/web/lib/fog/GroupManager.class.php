<<<<<<< HEAD
<?php
/** \class GroupManager
	Manager for the groups.
*/
class GroupManager extends FOGManagerController
{
	// Search query
	public $searchQuery = 'SELECT * FROM groups 
						LEFT OUTER JOIN
							(SELECT * FROM hosts INNER JOIN groupMembers ON (gmHostID = hostID) WHERE hostName LIKE "%${keyword}%")
							groupMembers ON (gmGroupID=groupID)
						WHERE 
							groupID LIKE "%${keyword}%" OR
							groupName LIKE "%${keyword}%" OR
							groupDesc LIKE "%${keyword}%" OR
							hostID LIKE "%${keyword}%" OR
							hostName LIKE "%${keyword}" OR
							hostDesc LIKE "%${keyword}%"
						GROUP BY
							groupName
						ORDER BY
							groupName';
}
=======
<?php
/** \class GroupManager
	Manager for the groups.
*/
class GroupManager extends FOGManagerController
{
}
>>>>>>> dev-branch
