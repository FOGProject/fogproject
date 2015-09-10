<?php
abstract class FOGManagerController extends FOGBase {
    /** Sets the table the manager class needs to look for
        it's elements.
     */
    public $databaseTable = '';
    /** Sets the search query to pull information.
        Alternate is find().
     */
    public $searchQuery = '';
    /** For the child class */
    protected $childClass;
    /** The variables of the class */
    protected $classVariables;
    /** The aliased fields **/
    protected $aliasedFields;
    /** The database fields. */
    protected $databaseFields;
    /** The database to class relationships extra data, but not needed **/
    protected $databaseFieldClassRelationships;
    /** The query to use from the class **/
    public $loadQueryTemplate = "SELECT * FROM %s %s %s %s %s %s";
    /** The query to use from the class **/
    public $loadQueryGroupTemplate = "SELECT * FROM (SELECT * FROM %s %s %s %s %s %s) %s %s %s %s %s %s";
    /** Search Query Template **/
    private $searchQueryTemplate = "SELECT %s FROM %s %s %s %s";
    /** __construct()
        Different constructor from FOG Base
     */
    public function __construct() {
        // FOGBase contstructor
        parent::__construct();
        // Set child classes name
        $this->childClass = preg_replace('#_?Manager$#', '', get_class($this));
        // Get child class variables
        $this->classVariables = get_class_vars($this->childClass);
        // Set required child variable data
        $this->aliasedFields = $this->classVariables[aliasedFields];
        $this->databaseTable = $this->classVariables[databaseTable];
        $this->databaseFields = $this->classVariables[databaseFields];
        $this->databaseFieldsFlipped = array_flip($this->databaseFields);
        $this->databaseFieldClassRelationships = $this->classVariables[databaseFieldClassRelationships];
    }
    public function getSubObjectIDs($object,$findWhere = array(),$getField = 'id') {
        if (empty($object)) $object = 'Host';
        if (!count($findWhere)) $findWhere = '';
        if (empty($getField)) $getField = 'id';
        return array_filter(array_unique($this->getClass($object)->getManager()->find($findWhere,'OR','','','','','',$getField)));
    }
    // Search
    public function search($keyword = null) {
        try {
            if (empty($keyword)) $keyword = preg_match('#mobile#i',$_SERVER['PHP_SELF'])?$_REQUEST['host-search']:$_REQUEST[crit];
            $mac_keyword = str_replace(array('-',':'),'',$keyword);
            $mac_keyword = join(':',str_split($mac_keyword,2));
            $keyword = preg_replace('#%+#', '%', '%'.preg_replace('#[[:space:]]#', '%', $keyword).'%');
            $mac_keyword = preg_replace('#%+#', '%', '%'.preg_replace('#[[:space:]]#', '%', $mac_keyword).'%');
            $Main = $this->getClass($this->childClass);
            if ($keyword === '%') return $Main->getManager()->find();
            $_SESSION[caller] = __FUNCTION__;
            $this->array_remove($this->aliasedFields,$this->databaseFields);
            $findWhere = array_fill_keys(array_keys($this->databaseFields),$keyword);
            $itemIDs = array_filter(array_unique($Main->getManager()->find($findWhere,'OR','','','','','','id')));
            $HostIDs = $this->getSubObjectIDs('MACAddressAssociation',array(mac=>$mac_keyword,description=>$keyword),'hostID');
            $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('Inventory',array(sysserial=>$keyword,caseserial=>$keyword,mbserial=>$keyword,primaryUser=>$keyword,other1=>$keyword,other2=>$keyword,sysman=>$keyword,sysproduct=>$keyword),'hostID'));
            $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('Host',array(name=>$keyword,description=>$keyword,ip=>$keyword)));
            switch ($this->childClass) {
                case 'User';
                break;
                case 'Host';
                $ImageIDs = $this->getSubObjectIDs('Image',array(name=>$keyword));
                $GroupIDs = $this->getSubObjectIDs('Group',array(name=>$keyword));
                $SnapinIDs = $this->getSubObjectIDs('Snapin',array(name=>$keyword,'file'=>$keyword));
                $PrinterIDs = $this->getSubObjectIDs('Printer',array(name=>$keyword));
                if (count($ImageIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('Host',array(imageID=>$ImageIDs)));
                if (count($GroupIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('GroupAssociation',array(id=>$GroupIDs),'hostID'));
                if (count($SnapinIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('SnapinAssociation',array(id=>$SnapinIDs),'hostID'));
                if (count($PrinterIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('PrinterAssociation',array(id=>$PrinterIDs),'hostID'));
                $itemIDs = array_merge($itemIDs,$HostIDs);
                break;
                case 'Group';
                $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('GroupAssociation',array(groupID=>$itemIDs),'hostID'));
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('GroupAssociation',array(hostID=>$HostIDs),'groupID'));
                break;
                case 'Image';
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('Host',array(id=>$HostIDs),'imageID'));
                break;
                case 'Snapin';
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('SnapinAssociation',array(hostID=>$HostIDs),'snapinID'));
                break;
                case 'Printer';
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('PrinterAssociation',array(hostID=>$HostIDs),'printerID'));
                break;
                case 'Task';
                $TaskStateIDs = $this->getSubObjectIDs('TaskState',array(name=>$keyword));
                //print_r($TaskStateIDs);
                $TaskTypeIDs = $this->getSubObjectIDs('TaskType',array(name=>$keyword));
                $ImageIDs = $this->getSubObjectIDs('Image',array(name=>$keyword));
                $GroupIDs = $this->getSubObjectIDs('Group',array(name=>$keyword));
                $SnapinIDs = $this->getSubObjectIDs('Snapin',array(name=>$keyword,'file'=>$keyword));
                $PrinterIDs = $this->getSubObjectIDs('Printer',array(name=>$keyword));
                if (count($ImageIDs)) $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('Host',array(imageID=>$ImageIDs)));
                if (count($GroupIDs)) $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('GroupAssociation',array(id=>$GroupIDs),'hostID'));
                if (count($SnapinIDs)) $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('SnapinAssociation',array(id=>$SnapinIDs),'hostID'));
                if (count($PrinterIDs)) $HostIDs = array_merge($HostIDs,$this->getSubObjectIDs('PrinterAssociation',array(id=>$PrinterIDs),'hostID'));
                if (count($TaskStateIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('Task',array(stateID=>$TaskStateIDs)));
                if (count($TaskTypeIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('Task',array(typeID=>$TaskTypeIDs)));
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs('Task',array(hostID=>$HostIDs)));
                break;
                default;
                if (count($HostIDs)) $itemIDs = array_merge($itemIDs,$this->getSubObjectIDs($this->childClass.'Association',array(hostID=>$HostIDs),strtolower($this->childClass).'ID'));
                break;
            }
        } catch (Exception $e) {
            $this->debug('Search failed! Error: %s', array($e->getMessage()));
            return false;
        }
        return $Main->getManager()->find(array(id=>$this->getSubObjectIDs($this->childClass,array(id=>$itemIDs))));
    }
    /** find($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
        Pulls the information from the database into the resepective class file.
     */
    public function find($where = '', $whereOperator = '', $orderBy = '', $sort = '',$compare = '',$groupBy = false,$not = false,$idField = false) {
        try {
            if (empty($compare)) $compare = '=';
            // Fail safe defaults
            if (empty($where)) $where = array();
            if (empty($whereOperator)) $whereOperator = 'AND';
            if (empty($orderBy)) {
                if (array_key_exists('name',$this->databaseFields)) $orderBy = 'name';
                else $orderBy = 'id';
            } else if (!array_key_exists($orderBy,$this->databaseFields)) $orderBy = 'id';
            $not = ($not ? ' NOT ' : '');
            // Error checking
            if (empty($this->databaseTable)) throw new Exception('No database table defined');
            // Create Where Array
            if (count($where)) {
                foreach((array)$where AS $field => &$value) {
                    if (is_array($value)) $whereArray[] = sprintf("%s %s IN ('%s')", $this->databaseTable.'.'.$this->databaseFields[$field], $not,implode("', '", $value));
                    else if (!is_array($value)) $whereArray[] = sprintf("%s %s '%s'", $this->databaseTable.'.'.$this->databaseFields[$field], (preg_match('#%#', $value) ? 'LIKE' : ($not ? '!' : '').$compare), $value);
                }
                unset($value);
            }
            foreach((array)$orderBy AS $i => &$item) {
                if ($this->databaseFields[$item]) $orderArray[] = sprintf("%s",$this->databaseFields[$item]);
            }
            unset($item);
            foreach((array)$groupBy AS $i => &$item) {
                if ($this->databaseFields[$item]) $groupArray[] = sprintf("%s",$this->databaseFields[$item]);
            }
            unset($item);
            $groupImplode = implode((array)$groupArray,',');
            $orderImplode = implode((array)$orderArray,',');
            $groupByField = 'GROUP BY '.$groupImplode;
            $orderByField = 'ORDER BY '.$orderImplode;
            list($join,$whereArrayAnd) = $this->getClass($this->childClass)->buildQuery($not,$compare);
            if ($groupBy) {
                $sql = $this->loadQueryGroupTemplate;
                $fieldValues = array(
                    $this->databaseTable,
                    $join,
                    (count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
                    (count($whereArrayAnd) ? (count($whereArray) ? 'AND ' : 'WHERE ').implode(' '.$whereOperator.' ',$whereArrayAnd) : ''),
                    $orderByField,
                    $sort,
                    $this->databaseTable,
                    $join,
                    (count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
                    (count($whereArrayAnd) ? (count($whereArray) ? 'AND ' : 'WHERE ').implode(' '.$whereOperator.' ',$whereArrayAnd) : ''),
                    $groupByField,
                    $orderByField,
                    $sort
                );
            } else {
                $sql = $this->loadQueryTemplate;
                $fieldValues = array(
                    $this->databaseTable,
                    $join,
                    (count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
                    (count($whereArrayAnd) ? (count($whereArray) ? 'AND ' : 'WHERE ').implode(' '.$whereOperator.' ',$whereArrayAnd) : ''),
                    $orderByField,
                    $sort
                );
            }
            $data = array();
            $this->DB->query($sql,$fieldValues);
            // Select all
            if ($idField) {
                if (is_array($idField)) {
                    foreach((array)$idField AS $i => &$idstore) {
                        while ($id = $this->DB->fetch()->get($this->databaseFields[$idstore])) $ids[$idstore][] = $id;
                    }
                    unset($idstore);
                } else while ($id = $this->DB->fetch()->get($this->databaseFields[$idField])) $ids[] = $id;
                if ($this->DB->queryResult() instanceof mysqli_result) $this->DB->queryResult()->free_result();
                return (array)$ids;
            }
            while ($queryData = $this->DB->fetch()->get()) $data[] = $this->getClass($this->childClass)->setQuery($queryData);
            unset($id,$ids,$row,$queryData);
            if ($this->DB->queryResult() instanceof mysqli_result) $this->DB->queryResult()->free_result();
            // Return
            return (array)$data;
        } catch (Exception $e) {
            $this->debug('Find all failed! Error: %s', array($e->getMessage()));
        }
        return false;
    }
    /** count($where = array(),$whereOperator = 'AND')
        Returns the count of the database.
     */
    public function count($where = array(), $whereOperator = 'AND', $compare = '=') {
        try {
            // Fail safe defaults
            if (empty($where)) $where = array();
            if (empty($whereOperator)) $whereOperator = 'AND';
            // Error checking
            if (empty($this->databaseTable)) throw new Exception('No database table defined');
            // Create Where Array
            if (count($where)) {
                foreach((array)$where AS $field => &$value) {
                    if (is_array($value)) $whereArray[] = sprintf("%s IN ('%s')", $this->databaseFields[$field], implode("', '", $value));
                    else $whereArray[] = sprintf("%s %s '%s'", $this->databaseFields[$field], (preg_match('#%#', $value) ? 'LIKE' : $compare), $value);
                }
                unset($value);
            }
            // Count result rows
            $this->DB->query("SELECT COUNT(%s) AS total FROM %s%s LIMIT 1", array(
                $this->databaseFields[id],
                $this->databaseTable,
                (count($whereArray) ? ' WHERE ' . implode(' ' . $whereOperator . ' ', $whereArray) : '')
            ));
            // Return
            return (int)$this->DB->fetch()->get(total);
        } catch (Exception $e) {
            $this->debug('Find all failed! Error: %s', array($e->getMessage()));
        }
        return false;
    }
    /** update() Updates items in mass
     * @param $where data where to only insert data into
     * @param $insertData data to insert
     */
    public function update($where = array(),$whereOperator = 'AND',$insertData) {
        $sql = "UPDATE %s SET %s %s";
        if (empty($whereOperator)) $whereOperator = 'AND';
        if (empty($where)) $where = array();
        foreach((array)$insertData AS $field => &$value) {
            $insertKey = preg_match('#default#i',$this->databaseFields[$field]) ? '`'.$this->databaseFields[$field].'`' : $this->databaseFields[$field];
            $insertVal = $this->DB->sanitize($value);
            $insertArray[] = sprintf("%s='%s'",$insertKey,$insertVal);
        }
        unset($value);
        if (count($where)) {
            foreach((array)$where AS $field => &$value) {
                if (is_array($value)) $whereArray[] = sprintf("%s IN ('%s')", $this->databaseFields[$field], implode("','",$value));
                else $whereArray[] = sprintf("%s %s '%s'",$this->databaseFields[$field],(preg_match('#%#', $value) ? 'LIKE' : '='),$value);
            }
            unset($value);
        }
        $query = vsprintf($sql, array(
            $this->databaseTable,
            implode(',',(array)$insertArray),
            (count($whereArray) ? ' WHERE '.implode(' '.$whereOperator.' ',(array)$whereArray) : '')
        ));
        $this->DB->query($query);
    }
    // NOTE: VERY! powerful... use with care
    /** destroy($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
        Removes the relevant fields from the database.
     */
    public function destroy($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC',$compare = '',$groupBy = false,$not = false) {
        if (array_key_exists('id',$where)) $ids = $where[id];
        else $ids = $this->find($where,$whereOperator,$orderBy,$sort,$compare,$groupBy,$not,'id');
        $query = sprintf('DELETE FROM %s WHERE %s IN (%s)',$this->databaseTable,$this->databaseFields[id],implode(',',(array)$ids));
        foreach ($ids AS $i => &$id) $this->getClass($this->childClass,$id)->destroy(id);
        unset($id);
        $this->DB->query($query)->fetch()->get();
        return true;
    }
    // Blackout - 11:28 AM 22/11/2011
    /** buildSelectBox($matchID = '',$elementName = '',$orderBy = 'name')
        Builds a select box for the class values found.
     */
    public function buildSelectBox($matchID = '', $elementName = '', $orderBy = 'name', $filter = '',$templateholder = false) {
        $matchID = ($_REQUEST['node'] == 'image' ? ($matchID === 0 ? 1 : $matchID) : $matchID);
        if (empty($elementName)) $elementName = strtolower($this->childClass);
        $Objects = $this->find($filter ? array('id' => $filter) : '','',$orderBy,'','','',($filter ? true : false));
        foreach($Objects AS $i => &$Object) $listArray .= '<option value="'.$Object->get(id).'"'.($matchID == $Object->get(id) ? ' selected' : ($templateholder ? '${selected_item'.$Object->get(id).'}' : '')).'>'.$Object->get(name).' - ('.$Object->get(id).')</option>';
        unset($Object);
        return (isset($listArray) ? sprintf('<select name="%s" autocomplete="off"><option value="">%s</option>%s</select>',($templateholder ? '${selector_name}' : $elementName),'- '.$this->foglang['PleaseSelect'].' -',$listArray) : false);
    }
    // TODO: Read DB fields from child class
    /** exists($name, $id = 0)
        Finds if the item already exists in the database.
     */
    public function exists($name, $id = 0, $idfield = 'id') {
        if (empty($idfield)) $idfield = 'id';
        $this->DB->query("SELECT COUNT(%s) AS total FROM %s WHERE %s = '%s' AND %s <> '%s'",
            array(
                $this->databaseFields[$idfield],
                $this->databaseTable,
                $this->databaseFields[name],
                $name,
                $this->databaseFields[$idfield],
                $id
            )
        );
        return ($this->DB->fetch()->get(total) ? true : false);
    }
    // Key
    /** key($key)
        Returns the key's of the database fields.
     */
    public function key($key) {
        if (array_key_exists($key, $this->databaseFields)) return $this->databaseFields[$key];
        // Cannot be used until all references to acual field names are converted to common names
        if (array_key_exists($key, $this->databaseFieldsFlipped)) return $this->databaseFieldsFlipped[$key];
        return $key;
    }
}
