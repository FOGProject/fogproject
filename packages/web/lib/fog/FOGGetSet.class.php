<?php
class FOGGetSet extends FOGBase {
	protected $data = array();
	// Constructor
	public function __construct($data = array()) {foreach((array)$data AS $key => $value) $this->set($key,$value);}
	public function set($key, $value) {
		try {
			if (!array_key_exists($key, $this->data)) throw new Exception('Invalid key being set');
			$this->data[$key] = $value;
		} catch (Exception $e) {
			$this->debug('Set Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
		}
		return $this;
	}
	// Get
	public function get($key = '') {return (!empty($key) && isset($this->data[$key]) ? $this->data[$key] : (empty($key) ? $this->data : ''));}
}
