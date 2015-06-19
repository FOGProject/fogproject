<?php
class SnapinGroupAssociation extends FOGController {
    // Table
    public $databaseTable = 'snapinGroupAssoc';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'sgaID',
        'snapinID' => 'sgaSnapinID',
        'storageGroupID' => 'sgaStorageGroupID',
    );
    // Custom
    public function getSnapin() {return $this->getClass('Snapin',$this->get('snapinID'));}
    public function getStorageGroup() {return $this->getClass('StorageGroup',$this->get('storageGroupID'));}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
