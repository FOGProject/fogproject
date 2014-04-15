<?php
class ImageManager extends FOGManagerController
{
	// Search query
	public $searchQuery = 'SELECT * FROM images WHERE imageName LIKE "%${keyword}%"';
}
