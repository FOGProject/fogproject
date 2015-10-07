<?php
abstract class FOGController extends FOGBase {
    protected $databaseTable = '';
    protected $databaseFields = array();
    protected $databaseFieldsFlipped = array();
    protected $loadQueryTemplateSingle = "SELECT * FROM %s %s WHERE %s='%s' %s";
    protected $loadQueryTemplateMultiple = 'SELECT * FROM %s %s WHERE %s %s';
    protected $insertQueryTemplate = "INSERT INTO %s (%s) VALUES ('%s') ON DUPLICATE KEY UPDATE %s";
    protected $destroyQueryTemplate = "DELETE FROM %s WHERE %s='%s'";
    protected $databaseFieldsToIgnore = array('createdBy','createdTime');
    protected $additionalFields = array();
    protected $aliasedFields = array();
    protected $databaseFieldsRequired = array();
    protected $data = array();
    protected $autoSave = false;
    protected $databaseFieldClassRelationships = array();
    public function __construct($data = '') {
        /** FOGBase Constructor */
        parent::__construct();
        /** After FOGBase is called, these variables allow
         * Display and debug to happen for better development
         */
        /** The called Table cleaned up as needed */
        $this->databaseTable = trim($this->databaseTable);
        /** The databaseFields to work from */
        $this->databaseFields = array_filter(array_unique((array)$this->databaseFields));
        try {
            // Error Checking
            if (!isset($this->databaseTable)) throw new Exception(_('No database table defined for this class'));
            if (!count($this->databaseFields)) throw new Exception(_('No database fields defined for this class'));
            if (is_numeric($data) && intval($data) < 1) throw new Exception(_('Improper data passed'));
            // Flip the generic and real table values for ease
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            // If the data called is a valid ID load the object
            if (is_numeric($data)) $this->set(id,$data)->load();
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
        return ($this->get(name) ? $this->get(name) : sprintf('%s ID: %s',get_class($this),$this->get(id)));
    }
    public function get($key = '') {
        $key = $this->key($key);
        if ($key) $this->info(_('Getting Value of Key: %s'),array($key));
        if (!$key) {
            $this->info(_('Getting All values of: %s'),array(get_class($this)));
            return $this->data;
        }
        try {
            if (!isset($this->data[$key])) throw new Exception(_('No value set'));
            if ($key) return $this->data[$key];
        } catch (Exception $e) {
            $this->debug(_('Get Failed: Key: %s, Error: %s'),array($key,$e->getMessage()));
        }
        return '';
    }
    public function set($key, $value) {
        $key = $this->key($key);
        $this->info(_('Setting Key: %s, Value: %s'),array($key, $value));
        try {
            if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) throw new Exception(_('Invalid key being set'));
            $this->data[$key] = $value;
        } catch (Exception $e) {
            $this->debug(_('Set Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function add($key, $value) {
        $key = $this->key($key);
        $this->info(_('Adding Key: %s, Values: %s'),array($key, $value));
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
        $this->info(_('Removing Key: %s, Value: %s'),array($key, $value));
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
            if ($name == 'createdBy') $val = $this->DB->sanitize($this->FOGUser instanceof User && $this->FOGUser->isLoggedIn() ? $this->FOGUser->get('name') : 'fog');
            elseif ($name == 'createdTime') $val = $this->DB->sanitize($this->formatTime('now','Y-m-d H:i:s'));
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
            if (!$this->get('id')) $this->set('id',$this->DB->insert_id());
            if ($this->binary_search('createdTime',$this->databaseFields) > -1) $this->set('createdBy',$this->formatTime('Y-m-d H:i:s'));
            if ($this->binary_search('createdBy',$this->databaseFields) > -1) $this->set('createdBy',$this->FOGUser->get('name'));
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
    public function load($field = 'id') {
        $this->info(_('Loading data to field %s'),array($field));
        try {
            if (!trim($this->get($field))) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
            // Get the query elements
            list($join, $where) = $this->buildQuery();
            foreach ((array)$field AS $i => &$key) {
                $key = $this->key($key);
                // Actually Build the real query:
                if (!is_array($this->get($key))) {
                    // Single Value
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
                    // Multiple Values
                    $query = sprintf($this->loadQueryTemplateMultiple,
                        $this->databaseTable,
                        $join,
                        implode(' OR ', $fieldData),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                }
                $vals = $this->DB->query($query)->fetch('','fetch_all')->get();
                $vals = @array_shift($vals);
                $this->setQuery($vals);
            }
        } catch (Exception $e) {
            $this->debug(_('Load failed: %s'),array($e->getMessage()));
        }
        return $this;
    }
    public function destroy($field = 'id') {
        $this->info(_('Destroying data from field %s'),array($field));
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
    protected function getSubObjectIDs($object = 'Host',$findWhere = array(),$getField = 'id') {
        if (empty($object)) $object = 'Host';
        if (empty($getField)) $getField = 'id';
        return array_filter(array_unique($this->getClass($object)->getManager()->find($findWhere,'OR','','','','','',$getField)));
    }
    protected function key(&$key) {
        $key = trim($key);
        if (array_key_exists($key, $this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
        return $key;
    }
    public function isValid() {
        try {
            foreach ($this->databaseFieldsRequired AS $i => &$field) if (is_string($this->get($field)) && !trim($this->get($field))) throw new Exception($this->foglang['RequiredDB']);
            unset($field);
            if (isset($this->databaseFields['name']) && !(trim($this->get('id')) && trim($this->get('name')))) throw new Exception(_(get_class($this).' no longer exists'));
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
        $classData = array_intersect_key((array)$queryData,(array)$this->databaseFieldsFlipped);
        foreach ((array)$classData AS $key => &$val) {
            $key = $this->key($key);
            $this->data[$key] = $val;
        }
        unset($val);
        foreach((array)$this->databaseFieldClassRelationships AS $class => &$fields) $this->set($fields[2],$this->getClass($class)->setQuery($queryData));
        unset($fields);
        return $this;
    }
    public function getManager() {
        return $this->getClass(get_class($this).'Manager');
    }
}
