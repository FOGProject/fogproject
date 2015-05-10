<?php
class FingerprintAssociation extends FOGController {
	// Table
	public $databaseTable = 'hostFingerprintAssoc';
	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'fpHostID',
		'fingerprint' => 'fingerprint',
	);
	public function getHost() {
		return $this->getClass('Host',$this->get('hostID'));
	}
	public function setFingerprint() {
		$this->getHost()->set('fingerprint',$this->get('fingerprint'));
	}
}
