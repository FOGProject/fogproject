<?php
class FingerprintAssociation extends FOGController
{
	// Table
	public $databaseTable = 'hostFingerprintAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'hostID',
		'fingerprint' => 'fingerprint',
	);
	public function getHost()
	{
		return new Host($this->get('hostID'));
	}
	public function setFingerprint()
	{
		$this->getHost()->set('fingerprint',$this->get('fingerprint'));
	}
}
