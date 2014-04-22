<?php
/** \class Image
	Builds all the Image class attributes.  The way it pulls data from the database.
*/
class Image extends FOGController
{
	// Table
	public $databaseTable = 'images';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'imageID',
		'name'		=> 'imageName',
		'description'	=> 'imageDesc',
		'path'		=> 'imagePath',
		'createdTime'	=> 'imageDateTime',
		'createdBy'	=> 'imageCreateBy',
		'building'	=> 'imageBuilding',
		'size'		=> 'imageSize',
		'imageTypeID'	=> 'imageTypeID',
		'storageGroupID'=> 'imageNFSGroupID',
		'osID'		=> 'imageOSID',
		'size'		=> 'imageSize', 
		'deployed'	=> 'imageLastDeploy',
		'legacy'        => 'imageLegacy',
	);
	
	// Custom functions
	/** getStorageGroup()
		Gets the relevant StorageGroup class object for the image.
	*/
	public function getStorageGroup()
	{
		return new StorageGroup($this->get('storageGroupID'));
	}
	/** getOS()
		Gets the relevant OS Class object for the image.
	*/
	public function getOS()
	{
		if ($this->get('osID'))
			return new OS($this->get('osID'));
		else
			return new OS(array('id' => '0'));
	}
	/** getImageType()
		Gets the relevant ImageType class object for the image.
	*/
	public function getImageType()
	{
		return new ImageType($this->get('imageTypeID'));
	}
	/** deleteImageFile()
		This function just deletes the image file via FTP.
		Only used if the user checks the Add File? checkbox.
	*/
	public function deleteImageFile()
	{
		$ftp = $GLOBALS['FOGFTP'];
		$SN = $this->getStorageGroup()->getMasterStorageNode();
		$SNME = ($SN && $SN->get('isEnabled') == '1' ? true : false);
		if ($SNME)
		{
			$ftphost = $SN->get('ip');
			$ftpuser = $SN->get('user');
			$ftppass = $SN->get('pass');
			$ftproot = rtrim($SN->get('path'),'/').'/'.$this->get('name');
		}
		$ftp    ->set('host',$ftphost)
			->set('username',$ftpuser)
			->set('password',$ftppass)
			->connect();
		if(!$ftp->delete($ftproot))
			return false;
		return true;
	}
}
