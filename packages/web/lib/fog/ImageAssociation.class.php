<?php
/** \class ImageAssociation
	Builds all the Image class attributes.  The way it pulls data from the database.
*/
class ImageAssociation extends FOGController
{
	// Table
	public $databaseTable = 'imageGroupAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'igaID',
		'imageID' => 'igaImageID',
		'storageGroupID' => 'igaStorageGroupID',
	);

	// Custom
	public function getImage()
	{
		return new Image($this->get('imageID'));
	}

	public function getStorageGroup()
	{
		return new StorageGroup($this->get('storageGroupID'));
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
