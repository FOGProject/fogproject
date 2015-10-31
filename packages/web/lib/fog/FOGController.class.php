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
            else if (is_array($data)) {
                foreach ($data AS $key => &$val) {
                    $key = $this->key($key);
                    $this->set($key, $val);
                }
                unset($val);
            }
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
        if (!$key) {
            $this->info(sprintf(_('Getting All values of: %s'),get_class($this)));
            return $this->data;
        }
        try {
            $key = $this->key($key);
            if (!$this->isLoaded($key)) $this->loadItem($key);
            if ($key) $this->info(sprintf(_('Getting Value of Key: %s, Value: %s'),$key, $this->data[$key]));
            return $this->data[$key];
        } catch (Exception $e) {
            $this->debug(sprintf(_('Get Failed: Key: %s, Error: %s'),$key,$e->getMessage()));
        }
        return '';
    }
    public function set($key, $value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        $this->info(sprintf(_('Setting Key: %s, Value: %s'),$key, $value));
        try {
            if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) throw new Exception(_('Invalid key being set'));
            if (is_numeric($value) && $value < ($key == 'id' ? 1 : -1)) throw new Exception(_('Invalid numeric entry'));
            $this->data[$key] = $value;
        } catch (Exception $e) {
            unset($this->data);
            $this->debug(_('Set Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function add($key, $value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        $this->info(sprintf(_('Adding Key: %s, Values: %s'),$key, $value));
        try {
            if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) throw new Exception(_('Invalid key being added'));
            $this->data[$key][] = $value;
        } catch (Exception $e) {
            $this->debug(_('Add Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function remove($key, $value) {
        $key = $this->key($key);
        if (!$this->isLoaded($key)) $this->loadItem($key);
        $this->info(sprintf(_('Removing Key: %s, Value: %s'),$key, $value));
        try {
            if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) throw new Exception(_('Invalid key being removed'));
            if (!is_array($this->data[$key])) $this->data[$key] = array($this->data[$key]);
            asort($this->data[$key]);
            $this->data[$key] = array_unique($this->data[$key]);
            $index = $this->binary_search($value,$this->data[$key]);
            unset($this->data[$key][$index]);
            $this->data[$key] = array_values(array_filter($this->data[$key]));
        } catch (Exception $e) {
            $this->debug(_('Remove Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function save() {
        $insertKeys = $insertValues = $updateData = $fieldData = array();
        if (count($this->aliasedFields)) $this->array_remove($this->aliasedFields, $this->databaseFields);
        foreach((array)$this->databaseFields AS $name => &$field) {
            $key = sprintf('`%s`',$this->DB->sanitize($field));
            if ($name == 'createdBy' && !$this->get($name)) $val = $this->DB->sanitize(trim($_SESSION['FOG_USERNAME']) ? trim($_SESSION['FOG_USERNAME']) : 'fog');
            else if ($name == 'createdTime' && (!$this->get('createdTime') || !$this->validDate($this->get($name)))) $val = $this->DB->sanitize($this->formatTime('now','Y-m-d H:i:s'));
            else $val = $this->DB->sanitize($this->get($name));
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
        try {
            if (!$this->DB->query($query)) throw new Exception($this->DB->sqlerror());
            if (!$this->get('id')) {
                if ($this->DB->insert_id() < 1) {
                    $this->destroy();
                    throw new Exception(_('Insert id is invalid'));
                }
                $this->set('id',$this->DB->insert_id());
            }
            if (!$this->isValid()) {
                $this->destroy();
                throw new Exception(sprintf('%s: %s %s',_('Object'),get_class($this),_('is not valid')));
            }
        } catch (Exception $e) {
            $this->debug(_('Database save failed: ID: %s, Error: %s'),array($this->data['id'],$e->getMessage()));
            return false;
        }
        return $this;
    }
    protected function load($field = 'id') {
        $this->info(sprintf(_('Loading data to field %s'),$field));
        try {
            if (!trim($this->get($field))) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
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
                    $fieldData = array();
                    $fields = $this->get($key);
                    foreach((array)$fields AS $i => &$fieldValue) $fieldData[] = sprintf("%s='%s'",$this->databaseFields[$key],$this->DB->sanitize($fieldValue));
                    $query = sprintf($this->loadQueryTemplateMultiple,
                        $this->databaseTable,
                        $join,
                        implode(' OR ', $fieldData),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                }
                $vals = $this->DB->query($query)->fetch()->get();
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
            if (!trim($this->get($field))) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
            $query = sprintf($this->destroyQueryTemplate,
                $this->databaseTable,
                $this->databaseFields[$field],
                $this->DB->sanitize($this->get($field))
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
            foreach ($this->databaseFieldsRequired AS $i => &$field) if (!trim($this->get($field)) === 0 && !trim($this->get($field))) throw new Exception($this->foglang['RequiredDB']);
            unset($field);
            if (!$this->get('id')) throw new Exception(_('Invalid ID'));
            if (array_key_exists('name',(array)$this->databaseFields) && !trim($this->get('name'))) throw new Exception(_(get_class($this).' no longer exists'));
        } catch (Exception $e) {
            $this->debug('isValid Failed: Error: %s',array($e->getMessage()));
            return false;
        }
        return true;
    }
    public function buildQuery($not = false, $compare = '=') {
        foreach ((array)$this->databaseFieldClassRelationships AS $class => &$fields) {
            $class = $this->getClass($class);
            $join[] = sprintf(' LEFT OUTER JOIN %s ON %s.%s=%s.%s ',(string)$class->databaseTable,(string)$class->databaseTable,(string)$class->databaseFields[$fields[0]],(string)$this->databaseTable,(string)$this->databaseFields[$fields[1]]);
            if ($fields[3]) {
                foreach ((array)$fields[3] AS $field => &$value) {
                    if (is_array($value)) $whereArrayAnd[] = sprintf("%s.%s IN ('%s')",$this->DB->sanitize($class->databaseTable),$this->DB->sanitize($class->databaseFields[$field]),implode("','",$value));
                    else $whereArrayAnd[] = sprintf("%s.%s %s '%s'",$this->DB->sanitize($class->databaseTable),$this->DB->sanitize($class->databaseFields[$field]),(preg_match('#%#',$value) ? 'LIKE' : $compare), $this->DB->sanitize($value));
                }
                unset($value);
            }
        }
        unset($fields);
        return array(implode((array)$join),$whereArrayAnd);
    }
    public function setQuery(&$queryData) {
        unset($this->data);
        $classData = array_intersect_key((array)$queryData,(array)$this->databaseFieldsFlipped);
        foreach ($this->databaseFieldsFlipped AS $db_key => &$obj_key) {
            $this->array_change_key($classData,$db_key,$obj_key);
            unset($obj_key);
        }
        $this->data = $classData;
        foreach((array)$this->databaseFieldClassRelationships AS $class => &$fields) $this->set($fields[2],$this->getClass($class)->setQuery($queryData));
        unset($fields);
        return $this;
    }
    public function getManager() {
        return $this->getClass(get_class($this).'Manager');
    }
}
