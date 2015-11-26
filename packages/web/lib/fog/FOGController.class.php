<?php
abstract class FOGController extends FOGBase {
    protected $data = array();
    protected $autoSave = false;
    protected $databaseTable = '';
    protected $databaseFields = array();
    protected $databaseFieldsRequired = array();
    protected $additionalFields = array();
    protected $databaseFieldsFlipped = array();
    protected $databaseFieldsToIgnore = array('createdBy','createdTime');
    protected $aliasedFields = array();
    protected $databaseFieldClassRelationships = array();
    protected $loadQueryTemplateSingle = "SELECT * FROM %s %s WHERE %s='%s' %s";
    protected $loadQueryTemplateMultiple = 'SELECT * FROM %s %s WHERE %s %s';
    protected $insertQueryTemplate = "INSERT INTO %s (%s) VALUES ('%s') ON DUPLICATE KEY UPDATE %s";
    protected $destroyQueryTemplate = "DELETE FROM %s WHERE %s='%s'";
    public function __construct($data = '') {
        parent::__construct();
        $this->databaseTable = trim($this->databaseTable);
        $this->databaseFields = array_filter(array_unique((array)$this->databaseFields));
        try {
            if (!isset($this->databaseTable)) throw new Exception(_('No database table defined for this class'));
            if (!count($this->databaseFields)) throw new Exception(_('No database fields defined for this class'));
            if (is_numeric($data) && intval($data) < 1) throw new Exception(_('Improper data passed'));
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            if (is_numeric($data)) $this->set('id',$data)->load();
            else if (is_array($data)) $this->setQuery($data);
        } catch (Exception $e) {
            $this->error(_('Record not found, Error: %s'),array($e->getMessage()));
        }
        return $this;
    }
    public function __destruct() {
        if ($this->autoSave) $this->save();
        return null;
    }
    public function __toString() {
        return ($this->get('name') ? $this->get('name') : sprintf('%s ID: %s',get_class($this),$this->get('id')));
    }
    public function get($key = '') {
        $key = $this->key($key);
        if (!$key) return $this->data;
        else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
            unset($this->data[$key]);
            return false;
        } else if (!$this->isLoaded($key)) $this->loadItem($key);
        if (is_object($this->data[$key])) {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Object'),$this->data[$key]->__toString()));
        } else if (is_array($this->data[$key])) {
            $this->info(sprintf('%s: %s',_('Returning array within key'),$key));
        } else {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Value'),$this->data[$key]));
        }
        return $this->data[$key];
    }
    public function set($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being set'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (is_numeric($value) && $value < ($key == 'id' ? 1 : -1)) throw new Exception(_('Invalid numeric entry'));
            if (is_object($value)) {
                $this->info(sprintf('%s: %s %s: %s',_('Setting Key'),$key,_('Object'),$value->__toString()));
            } else if (is_array($value)) {
                $this->info(sprintf('%s: %s %s',_('Setting Key'),$key,_('Array of data')));
            } else {
                $this->info(sprintf('%s: %s %s: %s',_('Setting Key'),$key,_('Value'),$value));
            }
            $this->data[$key] = $value;
        } catch (Exception $e) {
            $this->debug(_('Set Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function add($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being added'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (is_object($value)) {
                $this->info(sprintf('%s: %s, %s: %s',_('Adding Key'),$key,_('Object'),$value->__toString()));
                $this->data[$key][] = $value;
            } else if (is_array($value)) {
                $this->info(sprintf('%s: %s %s',_('Adding Key'),$key,_('Array of data')));
                $this->data[$key][] = $value;
            } else {
                $value = mb_convert_encoding($value,'UTF-8');
                $this->info(sprintf('%s: %s %s: %s',_('Adding Key'),$key,_('Value'),$value));
                $this->data[$key][] = $value;
            }
        } catch (Exception $e) {
            $this->debug(_('Add Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function remove($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being removed'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (!is_array($this->data[$key])) $this->data[$key] = array($this->data[$key]);
            asort($this->data[$key]);
            $this->data[$key] = array_unique($this->data[$key]);
            $index = $this->binary_search($value,$this->data[$key]);
            $this->info(sprintf(_('Removing Key: %s, Value: %s'),$key, $value));
            unset($this->data[$key][$index]);
            $this->data[$key] = array_values(array_filter($this->data[$key]));
        } catch (Exception $e) {
            $this->debug(_('Remove Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function save() {
        $this->info(sprintf(_('Saving data for %s object'),get_class($this)));
        try {
            $insertKeys = $insertValues = $updateData = $fieldData = array();
            if (count($this->aliasedFields)) $this->array_remove($this->aliasedFields, $this->databaseFields);
            foreach ((array)$this->databaseFields AS $name => &$field) {
                $key = sprintf('`%s`',trim($field));
                if ($name == 'createdBy' && !$this->get($name)) $val = trim($_SESSION['FOG_USERNAME'] ? $this->DB->sanitize($_SESSION['FOG_USERNAME']) : 'fog');
                else if ($name == 'createdTime' && (!$this->get('createdTime') || !$this->validDate($this->get($name)))) $val = $this->formatTime('now','Y-m-d H:i:s');
                else $val = $this->DB->sanitize($this->get($name));
                if ($name == 'id' && (empty($val) || $val == null || $val == 0 || $val == false)) continue;
                $insertKeys[] = $key;
                $insertValues[] = $val;
                $updateData[] = sprintf("%s='%s'",$key,$val);
            }
            unset($field);
            $query = sprintf($this->insertQueryTemplate,
                $this->databaseTable,
                implode(',',(array)$insertKeys),
                implode("','",(array)$insertValues),
                implode(',',(array)$updateData)
            );
            if (!$this->DB->query($query)->fetch()->get()) throw new Exception($this->DB->sqlerror());
            if (!$this->get('id')) $this->set('id',$this->DB->insert_id());
        } catch (Exception $e) {
            $this->debug(_('Database save failed: ID: %s, Error: %s'),array($this->data['id'],$e->getMessage()));
            return false;
        }
        return $this;
    }
    protected function load($field = 'id') {
        $this->info(sprintf(_('Loading data to field %s'),$field));
        try {
            if (!$this->get($field)) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
            list($join, $where) = $this->buildQuery();
            foreach ((array)$field AS $i => &$key) {
                $key = $this->key($key);
                if (!is_array($this->get($key))) {
                    $query = sprintf($this->loadQueryTemplateSingle,
                        $this->databaseTable,
                        $join,
                        $this->databaseFields[$key],
                        $this->DB->sanitize($this->get($key)),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                } else {
                    $fields = $this->get($key);
                    foreach((array)$fields AS $i => &$fieldValue) $fieldData[] = sprintf("`%s`.`%s`='%s'",$this->databaseTable,$this->databaseFields[$key],$this->DB->sanitize($fieldValue));
                    unset($fieldValue);
                    $query = sprintf($this->loadQueryTemplateMultiple,
                        $this->databaseTable,
                        $join,
                        implode(' OR ', $fieldData),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                }
                if (!($vals = $this->DB->query($query)->fetch('','fetch_all')->get())) throw new Exception($this->DB->sqlerror());
                $vals = @array_shift($vals);
                $this->setQuery($vals);
            }
        } catch (Exception $e) {
            $this->debug(_('Load failed: %s'),array($e->getMessage()));
        }
        return $this;
    }
    public function destroy($field = 'id') {
        $this->info(sprintf(_('Destroying data from field %s'),$field));
        try {
            if (!$this->get($field)) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
            if (!array_key_exists($field, $this->databaseFields) && !array_key_exists($field, $this->databaseFieldsFlipped)) throw new Exception(_('Invalid Operation Field set'));
            if (array_key_exists($field, $this->databaseFields)) $fieldToGet = $this->databaseFields[$field];
            $query = sprintf($this->destroyQueryTemplate,
                $this->databaseTable,
                $fieldToGet,
                $this->DB->sanitize($this->get($this->key($field)))
            );
            if (!$this->DB->query($query)->fetch()->get()) throw new Exception(_('Could not delete item'));
        } catch (Exception $e) {
            $this->debug(_('Destroy failed: %s'),array($e->getMessage()));
        }
        return $this;
    }
    protected function key(&$key) {
        if (!is_array($key)) {
            $key = trim($key);
            if (array_key_exists($key, $this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
            return $key;
        }
    }
    protected function loadItem($key) {
        if (!array_key_exists($key, $this->databaseFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !in_array($key, $this->additionalFields)) return $this;
        $methodCall = 'load'.ucfirst($key);
        if (method_exists($this,$methodCall)) $this->$methodCall();
        unset($methodCall);
        return $this;
    }
    public function isValid() {
        try {
            foreach ((array)$this->databaseFieldsRequired AS $i => &$field) if (!$this->get($field) === 0 && !$this->get($field)) throw new Exception($this->foglang['RequiredDB']);
            unset($field);
            if (!$this->get('id')) throw new Exception(_('Invalid ID'));
            if (array_key_exists('name',(array)$this->databaseFields) && !$this->get('name')) throw new Exception(_(get_class($this).' no longer exists'));
        } catch (Exception $e) {
            $this->debug('isValid Failed: Error: %s',array($e->getMessage()));
            return false;
        }
        return true;
    }
    public function buildQuery($not = false, $compare = '=') {
        foreach ((array)$this->databaseFieldClassRelationships AS $class => &$fields) {
            $class = $this->getClass($class);
            $join[] = sprintf(' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ',$class->databaseTable,$class->databaseTable,$class->databaseFields[$fields[0]],$this->databaseTable,$this->databaseFields[$fields[1]]);
            if ($fields[3]) {
                foreach ((array)$fields[3] AS $field => &$value) {
                    if (is_array($value)) $whereArrayAnd[] = sprintf("`%s`.`%s` IN ('%s')",$class->databaseTable,$field,implode("','",$value));
                    else $whereArrayAnd[] = sprintf("`%s`.`%s` %s '%s'",$class->databaseTable,$class->databaseFields[$field],(preg_match('#%#',$value) ? 'LIKE' : $compare), $value);
                }
                unset($value);
            }
        }
        unset($fields);
        return array(implode((array)$join),$whereArrayAnd);
    }
    public function setQuery(&$queryData) {
        $classData = array_intersect_key((array)$queryData,(array)$this->databaseFieldsFlipped);
        if (count($classData) <= 0) $classData = array_intersect_key((array)$queryData,$this->databaseFields);
        else {
            foreach ((array)$this->databaseFieldsFlipped AS $db_key => &$obj_key) {
                $this->array_change_key($classData,$db_key,$obj_key);
                unset($obj_key);
            }
        }
        $this->data = array_merge((array)$this->data,(array)$classData);
        foreach ((array)$this->databaseFieldClassRelationships AS $class => &$fields) $this->set($fields[2],$this->getClass($class)->setQuery($queryData));
        unset($fields);
        return $this;
    }
    public function getManager() {
        return $this->getClass(get_class($this).'Manager');
    }
}
