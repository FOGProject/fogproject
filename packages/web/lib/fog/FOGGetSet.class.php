<?php
class FOGGetSet extends FOGBase {
    protected $data = array();
    public function __construct($data = array()) {
        foreach((array)$data AS $key => &$value) {
            $this->set($key,$value);
            unset($value);
        }
    }
    public function set($key, $value) {
        try {
            if (!array_key_exists($key, $this->data)) throw new Exception('Invalid key being set');
            $this->data[$key] = $value;
        } catch (Exception $e) {
            $this->debug('Set Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function get($key = '') {
        if (!$key) return $this->data;
        else if (!array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            return false;
        }
        if (is_object($this->data[$key])) {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Object'),$this->data[$key]->__toString()));
            return $this->data[$key];
        } else if (is_array($this->data[$key])) {
            $this->info(sprintf('%s: %s',_('Returning array within key'),$key));
            return $this->data[$key];
        } else {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Value'),html_entity_decode(mb_convert_encoding(str_replace('\r\n',"\n",$this->data[$key]),'UTF-8','UTF-8'),ENT_QUOTES,'UTF-8')));
            return html_entity_decode(mb_convert_encoding(str_replace('\r\n',"\n",$this->data[$key]),'UTF-8','UTF-8'),ENT_QUOTES,'UTF-8');
        }
    }
}
