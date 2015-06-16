<?php
class ImageAssociation extends FOGController {
	/** @var $databaseTable the table to work with */
	public $databaseTable = 'imageGroupAssoc';
	/** @var $databaseFields the fields within the table */
	public $databaseFields = array(
		'id' => 'igaID',
		'imageID' => 'igaImageID',
		'storageGroupID' => 'igaStorageGroupID',
	);
	/** @function getImage() returns the image
	  * @return the image
	  */
	public function getImage() {return $this->getClass('Image',$this->get('imageID'));}
	/** @function getStorageGroup() returns the storage group
	  * @return the storage group
	  */
	public function getStorageGroup() {return $this->getClass('StorageGroup',$this->get('storageGroupID'));}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
