<?php
/** \class SnapinAssociation
	Builds all the Snapin class attributes.  The way it pulls data from the database.
*/
class SnapinAssociation extends FOGController
{
	// Table
	public $databaseTable = 'snapinGroupAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'sgaID',
		'snapinID' => 'sgaSnapinID',
		'storageGroupID' => 'sgaStorageGroupID',
	);

	// Custom
	public function getSnapin()
	{
		return new Snapin($this->get('snapinID'));
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
