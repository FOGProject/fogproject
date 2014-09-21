<?php
class ImageManager extends FOGManagerController
{
	// Search query
	public $searchQuery = 'SELECT * FROM images 
								LEFT OUTER JOIN hosts ON (hostImage=imageID)
								WHERE imageName LIKE "%${keyword}%" OR
									hostName LIKE "%${keyword}%"
								GROUP BY
									imageName
								ORDER BY
									imageName';
}
