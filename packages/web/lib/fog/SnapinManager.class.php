<?php

// Blackout - 3:10 PM 25/09/2011
class SnapinManager extends FOGManagerController
{
	public $searchQuery = 'SELECT * FROM snapins
				LEFT OUTER JOIN
				(SELECT * FROM hosts INNER JOIN snapinAssoc ON (saHostID=hostID) WHERE hostName LIKE "%${keyword}%") 
					snapinAssoc ON (saSnapinID=sID)
				WHERE
					sID LIKE "%${keyword}%" OR
					sName LIKE "%${keyword}%" OR
					sDesc LIKE "%${keyword}%" OR
					sFilePath LIKE "%${keyword}%" OR
					hostID LIKE "%${keyword}%" OR
					hostName LIKE "%${keyword}%" OR
					hostDesc LIKE "%${keyword}%"';
}
