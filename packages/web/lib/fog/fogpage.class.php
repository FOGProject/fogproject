<?php
/**
 * Presents many defaults for the pages and is
 * the calling point by all other page items.
 *
 * PHP version 5
 *
 * @category FOGPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents many defaults for the pages and is
 * the calling point by all other page items.
 *
 * @category FOGPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGPage extends FOGBase
{
    /**
     * Name of the page
     *
     * @var string
     */
    public $name = '';
    /**
     * Node of the page
     *
     * @var string
     */
    public $node = '';
    /**
     * ID of the page
     *
     * @var string
     */
    public $id = 'id';
    /**
     * Title for segment
     *
     * @var string
     */
    public $title;
    /**
     * The menu (always display)
     *
     * @var array
     */
    public $menu = array();
    /**
     * The submenu (Object displayed menus)
     *
     * @var array
     */
    public $subMenu = array();
    /**
     * Additional notes for object
     *
     * @var array
     */
    public $notes = array();
    /**
     * Table header data
     *
     * @var array
     */
    public $headerData = array();
    /**
     * Table data
     *
     * @var array
     */
    public $data = array();
    /**
     * Template data to replace
     *
     * @var array
     */
    public $templates = array();
    /**
     * Attributes such as class, id, etc...
     *
     * @var array
     */
    public $attributes = array();
    /**
     * Pages that contain objects
     *
     * @var array
     */
    protected $PagesWithObjects = array(
        'user',
        'host',
        'image',
        'group',
        'snapin',
        'printer',
        'storage'
    );
    /**
     * The items table
     *
     * @var string
     */
    protected $databaseTable = '';
    /**
     * The items table field and common names
     *
     * @var array
     */
    protected $databaseFields = array();
    /**
     * The items required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array();
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array();
    /**
     * The items additional fields
     *
     * @var array
     */
    protected $additionalFields = array();
    /**
     * The forms action placeholder
     *
     * @var string
     */
    protected $formAction = '';
    /**
     * The forms method/action
     *
     * @var string
     */
    protected $formPostAction = '';
    /**
     * The items caller class
     *
     * @var string
     */
    protected $childClass = '';
    /**
     * The report place holder
     *
     * @var string
     */
    protected $reportString = '';
    /**
     * Is the title enabled
     *
     * @var bool
     */
    protected $titleEnabled = true;
    /**
     * Fields to data
     *
     * @var mixed
     */
    protected $fieldsToData;
    /**
     * The request
     *
     * @var array
     */
    protected $request = array();
    /**
     * PDF Place holder
     *
     * @var string
     */
    protected static $pdffile = '';
    /**
     * CSV Place holder
     *
     * @var string
     */
    protected static $csvfile = '';
    /**
     * Inventory csv head
     *
     * @var string
     */
    protected static $inventoryCsvHead = '';
    /**
     * Holder for lambda function
     *
     * @var function
     */
    protected static $returnData;
    /**
     * Initializes the page class
     *
     * @param mixed $name name of the page to initialize
     *
     * @return void
     */
    public function __construct($name = '')
    {
        parent::__construct();
        if (self::$ajax) {
            session_write_close();
            ignore_user_abort(true);
            set_time_limit(0);
        }
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );
        global $node;
        global $type;
        global $sub;
        global $tab;
        global $id;
        if ($node == 'report') {
            $f = filter_input(INPUT_GET, 'f');
        }
        if ($node !== 'service'
            && false !== stripos($sub, 'edit')
            && (!isset($id)
            || !is_numeric($id)
            || $id < 1)
        ) {
            self::setMessage(
                _('ID Must be set to edit')
            );
            self::redirect(
                "?node=$node"
            );
            exit;
        }
        $subs = array(
            'configure',
            'authorize',
            'requestClientInfo',
        );
        if (in_array($sub, $subs)) {
            return $this->{$sub}();
        }
        $this->childClass = ucfirst($this->node);
        if ($node == 'storage') {
            $ref = stripos(
                self::$httpreferer,
                'node=storage&sub=storageGroup'
            );
        }
        if (!isset($ref) || false === $ref) {
            $ref = stripos(
                $sub,
                'storageGroup'
            );
        }
        if ($ref) {
            $this->childClass .= 'Group';
        } elseif ($node == 'storage') {
            $this->childClass = 'StorageNode';
        }
        if (strtolower($this->childClass) === 'storagenodegroup') {
            $this->childClass = 'StorageGroup';
        }
        if (!empty($name)) {
            $this->name = $name;
        }
        $this->title = $this->name;
        if (in_array($this->node, $this->PagesWithObjects)) {
            $classVars = self::getClass(
                $this->childClass,
                '',
                true
            );
            $this->databaseTable
                = $classVars['databaseTable'];
            $this->databaseFields
                = $classVars['databaseFields'];
            $this->databaseFieldsRequired
                = $classVars['databaseFieldsRequired'];
            $this->databaseFieldClassRelationships
                = $classVars['databaseFieldClassRelationships'];
            $this->additionalFields
                = $classVars['additionalFields'];
            unset($classVars);
            $this->obj = self::getClass(
                $this->childClass,
                $id
            );
            if (isset($id)) {
                $link = sprintf(
                    '?node=%s&sub=%s&%s=%d',
                    $this->node,
                    '%s',
                    $this->id,
                    $id
                );
                $this->delformat = sprintf(
                    $link,
                    'delete'
                );
                $this->linkformat = sprintf(
                    $link,
                    'edit'
                );
                $this->membership = sprintf(
                    $link,
                    'membership'
                );
                if ($id === 0 || !is_numeric($id) || !$this->obj->isValid()) {
                    unset($this->obj);
                    self::setMessage(
                        sprintf(
                            _('%s ID %d is not valid'),
                            $this->childClass,
                            $id
                        )
                    );
                    self::redirect(
                        sprintf(
                            '?node=%s',
                            $this->node
                        )
                    );
                }
            }
        }
        $this->reportString = '<h2><div id="exportDiv"></div><a id="csvsub" '
            . 'href="../management/export.php?filename=%s&type=csv" alt="%s" '
            . 'title="%s" target="_blank">%s</a> <a id="pdfsub" '
            . 'href="../management/export.php?filename=%s&type=pdf" alt="%s" '
            . 'title="%s" target="_blank">%s</a></h2>';
        self::$pdffile = '<i class="fa fa-file-pdf-o fa-2x"></i>';
        self::$csvfile = '<i class="fa fa-file-excel-o fa-2x"></i>';
        self::$inventoryCsvHead = array(
            _('Host ID') => 'id',
            _('Host name') => 'name',
            _('Host MAC') => 'mac',
            _('Host Desc') => 'description',
            _('Inventory ID') => 'id',
            _('Inventory Desc') => 'description',
            _('Primary User') => 'primaryUser',
            _('Other Tag 1') => 'other1',
            _('Other Tag 2') => 'other2',
            _('System Manufacturer') => 'sysman',
            _('System Product') => 'sysproduct',
            _('System Version') => 'sysversion',
            _('System Serial') => 'sysserial',
            _('System Type') => 'systype',
            _('BIOS Version') => 'biosversion',
            _('BIOS Vendor') => 'biosvendor',
            _('BIOS Date') => 'biosdate',
            _('MB Manufacturer') => 'mbman',
            _('MB Name') => 'mbproductname',
            _('MB Version') => 'mbversion',
            _('MB Serial') => 'mbserial',
            _('MB Asset') => 'mbasset',
            _('CPU Manufacturer') => 'cpuman',
            _('CPU Version') => 'cpuversion',
            _('CPU Speed') => 'cpucurrent',
            _('CPU Max Speed') => 'cpumax',
            _('Memory') => 'mem',
            _('HD Model') => 'hdmodel',
            _('HD Firmware') => 'hdfirmware',
            _('HD Serial') => 'hdserial',
            _('Chassis Manufacturer') => 'caseman',
            _('Chassis Version') => 'casever',
            _('Chassis Serial') => 'caseser',
            _('Chassis Asset') => 'caseasset',
        );
        $this->menu = array(
            'list' => sprintf(
                self::$foglang['ListAll'],
                _(
                    sprintf(
                        '%ss',
                        $this->childClass
                    )
                )
            ),
            'add' => sprintf(
                self::$foglang['CreateNew'],
                _($this->childClass)
            ),
            'export' => sprintf(
                self::$foglang[
                    sprintf(
                        'Export%s',
                        $this->childClass
                    )
                ]
            ),
            'import' => sprintf(
                self::$foglang[
                    sprintf(
                        'Import%s',
                        $this->childClass
                    )
                ]
            ),
        );
        $this->fieldsToData = function (&$input, &$field) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            if (is_array($this->span) && count($this->span) === 2) {
                $this->data[count($this->data)-1][$this->span[0]] = $this->span[1];
            }
            unset($input);
        };
        $nodestr = $substr = $idstr = $typestr = $tabstr = false;
        $formstr = '?';
        if ($node) {
            $data['node'] = $node;
        }
        if ($sub) {
            $data['sub'] = $sub;
        }
        if ($id) {
            $data['id'] = $id;
        }
        if ($type) {
            $data['type'] = $type;
        }
        if ($f) {
            $data['f'] = $f;
        }
        if ($tab) {
            $tabstr = "#$tab";
        }
        if (count($data) > 0) {
            $formstr .= http_build_query($data);
        }
        if ($tabstr) {
            $formstr .= $tabstr;
        }
        $this->formAction = $formstr;
        self::$HookManager->processEvent(
            'SEARCH_PAGES',
            array('searchPages' => &self::$searchPages)
        );
        self::$HookManager->processEvent(
            'SUB_MENULINK_DATA',
            array(
                'menu' => &$this->menu,
                'submenu' => &$this->subMenu,
                'id' => &$this->id,
                'notes' => &$this->notes
            )
        );
    }
    /**
     * Page default index
     *
     * @return void
     */
    public function index()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('Search');
        if (in_array($this->node, self::$searchPages)) {
            $this->title = sprintf(
                '%s %s',
                _('All'),
                _("{$this->childClass}s")
            );
            global $sub;
            $manager = sprintf(
                '%sManager',
                $this->childClass
            );
            $this->data = array();
            $find = '';
            if ('Host' === $this->childClass) {
                $find = array(
                    'pending' => array(0, '')
                );
            }
            Route::listem($this->childClass);
            $items = json_decode(Route::getData());
            $type = $this->node
                . 's';
            $items = $items->$type;
            if (count($items) > 0) {
                array_walk($items, static::$returnData);
            }
            $event = sprintf(
                '%s_DATA',
                strtoupper($this->node)
            );
            self::$HookManager->processEvent(
                $event,
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
            $event = sprintf(
                '%s_HEADER_DATA',
                strtoupper($this->node)
            );
            self::$HookManager->processEvent(
                $event,
                array(
                    'headerData' => &$this->headerData
                )
            );
            $this->render();
            unset(
                $this->headerData,
                $this->data,
                $this->templates,
                $this->attributes
            );
        } else {
            $vals = function (&$value, $key) {
                return sprintf(
                    '%s : %s',
                    $key,
                    $value
                );
            };
            if (count($args) > 0) {
                array_walk($args, $vals);
            }
            printf(
                'Index page of: %s%s',
                get_class($this),
                (
                    count($args) ?
                    sprintf(
                        ', Arguments = %s',
                        implode(
                            ', ',
                            $args
                        )
                    ) :
                    ''
                )
            );
        }
    }
    /**
     * Set's value to key
     *
     * @param string $key   the key to set
     * @param mixed  $value the value to set
     *
     * @return object
     */
    public function set($key, $value)
    {
        $this->$key = $value;
        return $this;
    }
    /**
     * Gets the value in the key
     *
     * @param string $key the key to get
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->$key;
    }
    /**
     * Return the information
     *
     * @return string
     */
    public function __toString()
    {
        return $this->process();
    }
    /**
     * Print the information
     *
     * @return void
     */
    public function render()
    {
        echo $this->process();
    }
    /**
     * Process the information
     *
     * @return string
     */
    public function process()
    {
        try {
            unset($actionbox);
            global $sub;
            global $node;
            $defaultScreen = strtolower(self::$defaultscreen);
            $defaultScreens = array(
                'search',
                'list'
            );
            $actionbox = '';
            if (((!$sub
                || in_array($sub, $defaultScreens)
                || $sub === 'storageGroup')
                && in_array($node, self::$searchPages)
                && in_array($node, $this->PagesWithObjects))
            ) {
                if ($node == 'host') {
                    $actionbox .= '<div class='
                        . '"col-xs-offset-3 col-xs-9 action-boxes host '
                        . 'hiddeninitially">';
                    $actionbox .= '<div class="panel panel-info">';
                    $actionbox .= '<div class="panel-heading text-center">';
                    $actionbox .= '<h4 class="title">';
                    $actionbox .= _('Group Associations');
                    $actionbox .= '</h4>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="panel-body">';
                    $actionbox .= '<form class='
                        . '"form-horizontal" '
                        . 'method="post" '
                        . 'action="'
                        . '?node='
                        . $node
                        . '&sub=saveGroup">';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4" for=';
                    $actionbox .= '"group_new">';
                    $actionbox .= _('Create new group');
                    $actionbox .= '</label>';
                    $actionbox .= '<div class="input-group">';
                    $actionbox .= '<input type="hidden" name="hostIDArray"/>';
                    $actionbox .= '<input type="text" name="group_new" id='
                        . '"group_new" class="form-control"/>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4">';
                    $actionbox .= _('or');
                    $actionbox .= '</label>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4" for=';
                    $actionbox .= '"group">';
                    $actionbox .= _('Add to group');
                    $actionbox .= '</label>';
                    $actionbox .= '<div class="input-group">';
                    $actionbox .= self::getClass('GroupManager')->buildSelectBox();
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<span class="col-xs-4"></span>';
                    $actionbox .= '<button type="submit" class='
                        . '"btn btn-info btn-lg">';
                    $actionbox .= _('Process group changes');
                    $actionbox .= '</button>';
                    $actionbox .= '</div>';
                    $actionbox .= '</form>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                }
                if ($node != 'task') {
                    $actionbox .= '<div class='
                        . '"col-xs-offset-3 col-xs-9 action-boxes del '
                        . 'hiddeninitially">';
                    $actionbox .= '<div class="panel panel-warning">';
                    $actionbox .= '<div class="panel-heading text-center">';
                    $actionbox .= '<h4 class="title">';
                    $actionbox .= _('Delete Selected');
                    $actionbox .= '</h4>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="panel-body">';
                    $actionbox .= '<form class='
                        . '"form-horizontal" '
                        . 'method="post" '
                        . 'action="'
                        . '?node='
                        . $node
                        . '&sub=deletemulti">';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4" for='
                        . '"del-'
                        . $node
                        . '">';
                    $actionbox .= sprintf(
                        '%s %ss',
                        _('Delete selected'),
                        (
                            strtolower($node) !== 'storage' ?
                            strtolower($node) :
                            (
                                $sub === 'storageGroup' ?
                                strtolower($node) . ' group' :
                                strtolower($node) . ' node'
                            )
                        )
                    );
                    $actionbox .= '</label>';
                    $actionbox .= '<input type="hidden" name="'
                        . strtolower($node)
                        . 'IDArray"/>';
                    $actionbox .= '<button type="submit" class='
                        . '"btn btn-danger btn-lg" id="'
                        . 'del-'
                        . $node
                        . '">';
                    $actionbox .= _('Delete');
                    $actionbox .= '</button>';
                    $actionbox .= '</div>';
                    $actionbox .= '</form>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                }
            }
            self::$HookManager->processEvent(
                'ACTIONBOX',
                array('actionbox' => &$actionbox)
            );
            if (self::$ajax) {
                echo json_encode(
                    array(
                        'data' => $this->data,
                        'templates' => $this->templates,
                        'headerData' => $this->headerData,
                        'title' => $this->title,
                        'attributes' => $this->attributes,
                        'form' => $this->form,
                        'actionbox' => (
                            count($this->data) > 0 ?
                            $actionbox :
                            ''
                        ),
                    )
                );
                exit;
            }
            if (!count($this->templates)) {
                throw new Exception(
                    _('Requires templates to process')
                );
            }
            if (in_array($node, array('task'))
                && (!$sub || $sub == 'list')
            ) {
                self::redirect(
                    sprintf(
                        '?node=%s&sub=active',
                        $node
                    )
                );
            }
            ob_start();
            if (isset($this->form)) {
                printf($this->form);
            }
            if ($node != 'home') {
                echo '<div class="table-holder col-xs-9">';
            }
            echo '<table class="table">';
            if (count($this->data) < 1) {
                echo '<thead><tr class="header"></tr></thead>';
                echo '<tbody>';
                $tablestr = '<tr><td colspan="'
                    . count($this->templates)
                    . '">';
                if ($this->data['error']) {
                    $tablestr .= (
                        is_array($this->data['error']) ?
                        '<p>'
                        . implode('</p><p>', $this->data['error'])
                        : $this->data['error']
                    );
                } else {
                    $tablestr .= self::$foglang['NoResults'];
                }
                $tablestr .= '</td></tr>';
                echo $tablestr;
                echo '</tbody>';
            } else {
                if (count($this->headerData) > 0) {
                    echo '<thead>';
                    echo $this->buildHeaderRow();
                    echo '</thead>';
                }
                echo '<tbody>';
                $tablestr = '';
                foreach ($this->data as &$rowData) {
                    $tablestr .= '<tr class="'
                        . strtolower($node)
                        . '" '
                        . (
                            isset($rowData['id']) || isset($rowData[$id_field]) ?
                            'id="'
                            . $node
                            . '-'
                            . (
                                isset($rowData['id']) ?
                                $rowData['id'] . '"' :
                                $rowData[$id_field] . '"'
                            ) :
                            ''
                        )
                        . '>';
                    $tablestr .= $this->buildRow($rowData);
                    $tablestr .= '</tr>';
                    unset($rowData);
                }
                echo $tablestr;
                echo '</tbody>';
            }
            echo '</table>';
            if ($node != 'home') {
                echo '</div>';
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return ob_get_clean()
            . $actionbox;
    }
    /**
     * Sets the attributes
     *
     * @return void
     */
    private function _setAtts()
    {
        foreach ((array)$this->attributes as $index => &$attribute) {
            foreach ((array)$attribute as $name => &$val) {
                $this->atts[$index] .= sprintf(
                    ' %s="%s" ',
                    $name,
                    (
                        $this->dataFind ?
                        str_replace($this->dataFind, $this->dataReplace, $val) :
                        $val
                    )
                );
                unset($name);
            }
            unset($attribute);
        }
    }
    /**
     * Builds the header row
     *
     * @return string
     */
    public function buildHeaderRow()
    {
        unset($this->atts);
        $this->_setAtts();
        if (count($this->headerData) < 1) {
            return;
        }
        ob_start();
        echo '<tr class="header'
            . (
                count($this->data) < 1 ?
                ' hiddeninitially' :
                ''
            )
            . '">';
        foreach ($this->headerData as $index => &$content) {
            echo '<th'
                . (
                    $this->atts[$index] ?
                    ' '
                    . $this->atts[$index]
                    . ' ' :
                    ' '
                )
                . 'data-column="'
                . $index
                . '">';
            echo $content;
            echo '</th>';
            unset($content);
        }
        echo '</tr>';
        return ob_get_clean();
    }
    /**
     * Replaces the data for templated information
     *
     * @param mixed $data the data to replace
     *
     * @return string
     */
    private function _replaceNeeds($data)
    {
        unset(
            $this->dataFind,
            $this->dataReplace
        );
        global $node;
        global $sub;
        global $tab;
        $urlvars = array(
            'node' => $node,
            'sub' => $sub,
            'tab' => $tab
        );
        $arrayReplace = self::fastmerge(
            $urlvars,
            (array)$data
        );
        foreach ((array)$arrayReplace as $name => &$val) {
            $this->dataFind[] = sprintf(
                '${%s}',
                $name
            );
            $val = trim($val);
            $this->dataReplace[] = $val;
            unset($val);
        }
    }
    /**
     * Builds the row data
     *
     * @param mixed $data the data to build off
     *
     * @return string
     */
    public function buildRow($data)
    {
        unset($this->atts);
        $this->_replaceNeeds($data);
        $this->_setAtts();
        ob_start();
        foreach ((array)$this->templates as $index => &$template) {
            echo '<td'
                . (
                    $this->atts[$index] ?
                    ' ' . $this->atts[$index] . ' ' :
                    ''
                )
                . '>';
            echo str_replace(
                $this->dataFind,
                $this->dataReplace,
                $template
            );
            echo '</td>';
            unset($template);
        }
        return ob_get_clean();
    }
    /**
     * Presents the tasking items and options
     *
     * @return void
     */
    public function deploy()
    {
        global $type;
        global $id;
        try {
            if (!is_numeric($type) || $type < 1) {
                $type = 1;
            }
            $TaskType = new TaskType($type);
            $imagingTypes = $TaskType->isImagingTask();
            if ($this->obj instanceof Group) {
                if ($this->obj->getHostCount() < 1) {
                    throw new Exception(
                        _('Cannot set tasking to invalid hosts')
                    );
                }
            }
            if ($this->obj instanceof Host) {
                if ($this->obj->get('pending')) {
                    throw new Exception(
                        _('Cannot set tasking to pending hosts')
                    );
                }
            }
            if (!$this->obj instanceof Group
                && !$this->obj instanceof Host
            ) {
                throw new Exception(
                    _('Invalid object to try tasking')
                );
            }
            if ($imagingTypes
                && $this->obj instanceof Host
                && !$this->obj->getImage()->get('isEnabled')
            ) {
                throw new Exception(_('Cannot set tasking as image is not enabled'));
            }
        } catch (Exception $e) {
            self::setMessage(
                $e->getMessage()
            );
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit%s',
                    $this->node,
                    (
                        is_numeric($id) && $id > 0 ?
                        sprintf(
                            '&%s=%s',
                            $this->id,
                            $id
                        ) :
                        ''
                    )
                )
            );
        }
        unset($this->headerData);
        $this->attributes = array(
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'right',
                'title' => '${host_title}'
            ),
            array(),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'right',
                'title' => '${image_title}'
            )
        );
        $this->templates = array(
            '<a href="${host_link}">${host_name}</a>',
            '${host_mac}',
            '<a href="${image_link}">${image_name}</a>'
            . '<input type="hidden" name="taskhosts[]" value="${host_id}"/>',
        );
        if ($this->obj instanceof Host) {
            $this->data[] = array(
                'host_link' => '?node=host&sub=edit&id=${host_id}',
                'image_link' => '?node=image&sub=edit&id=${image_id}',
                'host_id' => $this->obj->get('id'),
                'image_id' => $this->obj->getImage()->get('id'),
                'host_name' => $this->obj->get('name'),
                'host_mac' => $this->obj->get('mac'),
                'image_name' => $this->obj->getImage()->get('name'),
                'host_title' => _('Edit Host'),
                'image_title' => _('Edit Image'),
            );
        } elseif ($this->obj instanceof Group) {
            foreach ((array)self::getClass('HostManager')
                ->find(
                    array('id' => $this->obj->get('hosts'))
                ) as &$Host
            ) {
                $imageID = $imageName = '';
                if ($TaskType->isImagingTask()) {
                    $Image = $Host->getImage();
                    if (!$Image->isValid()) {
                        continue;
                    }
                    if (!$Image->get('isEnabled')) {
                        continue;
                    }
                    $imageID = $Image->get('id');
                    $imageName = $Image->get('name');
                }
                $this->data[] = array(
                    'host_link' => '?node=host&sub=edit&id=${host_id}',
                    'host_title' => sprintf(
                        '%s: ${host_name}',
                        _('Edit')
                    ),
                    'host_id' => $Host->get('id'),
                    'host_name' => $Host->get('name'),
                    'host_mac' => $Host->get('mac')->__toString(),
                    'image_link' => '?node=image&sub=edit&id=${image_id}',
                    'image_title' => sprintf(
                        '%s: ${image_name}',
                        _('Edit')
                    ),
                    'image_id' => $imageID,
                    'image_name' => $imageName,
                );
                unset(
                    $index,
                    $Host,
                    $Image
                );
            }
        }
        self::$HookManager->processEvent(
            sprintf(
                '%s_DEPLOY',
                strtoupper($this->childClass)
            ),
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<div class="col-xs-offset-3 panel panel-primary">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Confirm tasking');
        echo '</h4>';
        echo '<p class="category">';
        echo _('Advanced Settings');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body fogcard">';
        echo '<div class="col-xs-10">';
        echo '<form class="form-horizontal deploy-container" method="post" action="'
            . $this->formAction
            . '">';
        if ($TaskType->get('id') == 13) {
            echo '<div class="form-group">';
            echo '<label class="control-label" for="snapin">';
            echo _('Please select the snapin you want to install');
            echo '</label>';
            echo '<div class="input-group">';
            echo self::getClass('SnapinManager')->buildSelectBox(
                '',
                'snapin'
            );
            echo '</div>';
            echo '</div>';
        }
        if ($TaskType->get('id') == 11) {
            echo '<div class="form-group">';
            echo '<label class="control-label" for="account">';
            echo _('Account name to reset');
            echo '</label>';
            echo '<div class="input-group">';
            echo '<input class="form-control" id="account" type="'
                . 'text" name="account" value="Administrator"/>';
            echo '</div>';
            echo '</div>';
        }
        if ($TaskType->isInitNeededTasking()
            && !$TaskType->isDebug()
        ) {
            echo '<div class="checkbox hideFromDebug">';
            echo '<label class="control-label" for="shutdown">';
            echo '<input type="checkbox" name='
                . '"shutdown" id="shutdown"'
                . (
                    self::getSetting('FOG_TASKING_ADV_SHUTDOWN_ENABLED') ?
                    ' checked' :
                    ''
                )
                . '/>';
            echo _('Schedule with shutdown');
            echo '</label>';
            echo '</div>';
        }
        if ($TaskType->get('id') != 14) {
            echo '<div class="checkbox">';
            echo '<label class="control-label" for="wol">';
            echo '<input type="checkbox" name='
                . '"wol" id="wol"'
                . (
                    $TaskType->isSnapinTasking() ?
                    '' :
                    (
                        self::getSetting('FOG_TASKING_ADV_WOL_ENABLED') ?
                        ' checked' :
                        ''
                    )
                )
                . '/>';
            echo _('Wake on lan?');
            echo '</label>';
            echo '</div>';
        }
        if (!$TaskType->isDebug()
            && $TaskType->get('id') != 11
        ) {
            if ($TaskType->isInitNeededTasking()
                && !($this->obj instanceof Group)
            ) {
                echo '<div class="checkbox">';
                echo '<label class="control-label" for="checkDebug">';
                echo '<input type="checkbox" name='
                    . '"isDebugTask" id="checkDebug"'
                    . (
                        self::getSetting('FOG_TASKING_ADV_DEBUG_ENABLED') ?
                        ' checked' :
                        ''
                    )
                    . '/>';
                echo _('Schedule as debug task');
                echo '</label>';
                echo '</div>';
            }
        }
        echo '<div class="radio">';
        echo '<label class="control-label" for="scheduleInstant">';
        echo '<input type="radio" name='
            . '"scheduleType" id="scheduleInstant" value="instant"'
            . 'checked/>';
        echo _('Schedule instant');
        echo '</label>';
        echo '</div>';
        if (!$TaskType->isDebug()
            && $TaskType->get('id') != 11
        ) {
            // Delayed elements
            echo '<div class="hideFromDebug">';
            echo '<div class="radio">';
            echo '<label class="control-label" for="scheduleSingle">';
            echo '<input type="radio" name='
                . '"scheduleType" id="scheduleSingle" value="single"/>';
            echo _('Schedule delayed');
            echo '</label>';
            echo '</div>';
            echo '<div class="form-group hiddeninitially">';
            echo '<label class="control-label" for="scheduleSingleTime">';
            echo _('Date and Time');
            echo '</label>';
            echo '<div class="input-group">';
            echo '<input class="form-control" type="text" name='
                . '"scheduleSingleTime" id='
                . '"scheduleSingleTime">';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            // Cron elements
            $specialCrons = array(
                ''=>_('Select a cron type'),
                'yearly'=>sprintf('%s/%s', _('Yearly'), _('Annually')),
                'monthly'=>_('Monthly'),
                'weekly'=>_('Weekly'),
                'daily'=>sprintf('%s/%s', _('Daily'), _('Midnight')),
                'hourly'=>_('Hourly'),
            );
            ob_start();
            foreach ($specialCrons as $val => &$name) {
                echo '<option value="'
                    . $val
                    . '">'
                    . $name
                    . '</option>';
                unset($name);
            }
            $cronOpts = ob_get_clean();
            echo '<div class="hideFromDebug">';
            echo '<div class="radio">';
            echo '<label class="control-label" for="scheduleCron">';
            echo '<input type="radio" name='
                . '"scheduleType" id="scheduleCron" value="cron"/>';
            echo _('Schedule cron-style');
            echo '</label>';
            echo '</div>';
            echo '<div class="form-group hiddeninitially">';
            echo '<div class="cronOptions input-group">';
            echo '<select class="form-control" id="specialCrons" name='
                . '"specialCrons">';
            echo $cronOpts;
            echo '</select>';
            echo '</div>';
            echo '<div class="col-xs-12">';
            echo '<div class="cronInputs">';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronMin" '
                . 'id="scheduleCronMin" placeholder="min" autocomplete="off" '
                . 'class="form-control cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronHour" '
                . 'id="scheduleCronHour" placeholder="hour" autocomplete="off" '
                . 'class="form-control cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronDOM" '
                . 'id="scheduleCronDOM" placeholder="dom" autocomplete="off" '
                . 'class="form-control cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronMonth" '
                . 'id="scheduleCronMonth" placeholder="month" autocomplete="off" '
                . 'class="form-control cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronDOW" '
                . 'id="scheduleCronDOW" placeholder="dow" autocomplete="off" '
                . 'class="form-control cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        if (count($this->data)) {
            echo '<button type="submit" class="btn btn-success btn-block">';
            echo _('Create')
                . ' '
                . $TaskType->get('name')
                . ' '
                . _('Tasking');
            echo '</button>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        if ($this->node != 'host') {
            echo '<div class="col-xs-offset-3 panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h2 class="title">';
            echo _('Hosts in task');
            echo '</h2>';
            echo '</div>';
            echo '<div class="panel-body fogcard">';
            $this->render();
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    /**
     * Actually create the tasking
     *
     * @return void
     */
    public function deployPost()
    {
        self::$HookManager->processEvent(
            sprintf(
                '%s_DEPLOY_POST',
                strtoupper($this->childClass)
            )
        );
        global $type;
        global $id;
        try {
            /**
             * Task type setup.
             */
            if (!(is_numeric($type) && $type > 0)) {
                $type = 1;
            }
            $TaskType = new TaskType($type);
            /**
             * Account Setup.
             */
            $passreset = filter_input(INPUT_POST, 'account');
            /**
             * Snapin Setup.
             */
            $enableSnapins = intval(filter_input(INPUT_POST, 'snapin'));
            if (0 === $enableSnapins) {
                $enableSnapins = -1;
            }
            if (17 === $type
                || $enableSnapins < -1
            ) {
                $enableSnapins = 0;
            }
            /**
             * Shutdown Setup.
             */
            $enableShutdown = false;
            $shutdown = isset($_POST['shutdown']);
            if ($shutdown) {
                $enableShutdown = true;
            }
            /**
             * Debug Setup.
             */
            $enableDebug = false;
            $debug = isset($_POST['debug']);
            $isdebug = isset($_POST['isDebugTask']);
            if ($debug || $isdebug) {
                $enableDebug = true;
            }
            /**
             * WOL Setup.
             */
            $wol = false;
            $wolon = isset($_POST['wol']);
            if (14 == $type
                || $wolon
            ) {
                $wol = true;
            }
            $imagingTasks = $TaskType->isImagingTask();
            $taskName = sprintf(
                '%s Task',
                $TaskType->get('name')
            );
            /**
             * Schedule Type Setup.
             */
            $scheduleType = strtolower(
                filter_input(INPUT_POST, 'scheduleType')
            );
            $scheduleTypes = array(
                'cron',
                'instant',
                'single',
            );
            self::$HookManager
                ->processEvent(
                    'SCHEDULE_TYPES',
                    array(
                        'scheduleTypes' => &$scheduleTypes
                    )
                );
            foreach ((array)$scheduleTypes as $ind => &$type) {
                $scheduleTypes[$ind] = trim(
                    strtolower(
                        $type
                    )
                );
                unset($type);
            }
            if (!in_array($scheduleType, $scheduleTypes)) {
                throw new Exception(_('Invalid scheduling type'));
            }
            /**
             * Schedule delayed/cron checks.
             */
            $scheduleDeployTime = self::niceDate(
                filter_input(INPUT_POST, 'scheduleSingleTime')
            );
            switch ($scheduleType) {
            case 'single':
                if ($scheduleDeployTime < self::niceDate()) {
                    throw new Exception(
                        sprintf(
                            '%s<br>%s: %s',
                            _('Scheduled date is in the past'),
                            _('Date'),
                            $scheduleDeployTime->format('Y-m-d H:i:s')
                        )
                    );
                }
                break;
            case 'cron':
                $min = strval(filter_input(INPUT_POST, 'scheduleCronMin'));
                $hour = strval(filter_input(INPUT_POST, 'scheduleCronHour'));
                $dom = strval(filter_input(INPUT_POST, 'scheduleCronDOM'));
                $month = strval(filter_input(INPUT_POST, 'scheduleCronMonth'));
                $dow = strval(filter_input(INPUT_POST, 'scheduleCronDOW'));
                $valsToSet = array(
                    'minute' => $min,
                    'hour' => $hour,
                    'dayOfMonth' => $dom,
                    'month' => $month,
                    'dayOfWeek' => $dow
                );
                if (!FOGCron::checkMinutesField($min)) {
                    throw new Exception(
                        sprintf(
                            '%s %s invalid',
                            'checkMinutesField',
                            _('minute')
                        )
                    );
                }
                if (!FOGCron::checkHoursField($hour)) {
                    throw new Exception(
                        sprintf(
                            '%s %s invalid',
                            'checkHoursField',
                            _('hour')
                        )
                    );
                }
                if (!FOGCron::checkDOMField($dom)) {
                    throw new Exception(
                        sprintf(
                            '%s %s invalid',
                            'checkDOMField',
                            _('day of month')
                        )
                    );
                }
                if (!FOGCron::checkMonthField($month)) {
                    throw new Exception(
                        sprintf(
                            '%s %s invalid',
                            'checkMonthField',
                            _('month')
                        )
                    );
                }
                if (!FOGCron::checkDOWField($dow)) {
                    throw new Exception(
                        sprintf(
                            '%s %s invalid',
                            'checkDOWField',
                            _('day of week')
                        )
                    );
                }
                break;
            }
            // The type is invalid
            if (!$TaskType->isValid()) {
                throw new Exception(
                    _('Task type is not valid')
                );
            }
            // Task is password recovery but no account to reset
            if ($TaskType->get('id') == 11
                && empty($passreset)
            ) {
                throw new Exception(
                    _('Password reset requires a user account to reset')
                );
            }
            // Is host pending, don't send
            if ($this->obj instanceof Host
                && $this->obj->get('pending')
            ) {
                throw new Exception(
                    _('Cannot set tasking to pending hosts')
                );
            } elseif ($this->obj instanceof Group) {
                if (!(isset($_POST['taskhosts'])
                    && is_array($_POST['taskhosts'])
                    && count($_POST['taskhosts']) > 0)
                ) {
                    throw new Exception(
                        _('There are no hosts to task in this group')
                    );
                }
                $this->obj->set('hosts', $_POST['taskhosts']);
            }
            if ($TaskType->isImagingTask()) {
                if ($this->obj instanceof Host) {
                    $Image = $this->obj->getImage();
                    if (!$Image->isValid()) {
                        throw new Exception(
                            _('To perform an imaging task an image must be assigned')
                        );
                    }
                    if (!$Image->get('isEnabled')) {
                        throw new Exception(
                            _('Cannot create tasking as image is not enabled')
                        );
                    }
                    if ($TaskType->isCapture()
                        && $Image->get('protected')
                    ) {
                        throw new Exception(
                            _('The assigned image is protected')
                            . ' '
                            . _('and cannot be captured')
                        );
                    }
                } elseif ($this->obj instanceof Group) {
                    if ($TaskType->isCapture()) {
                        throw new Exception(
                            _('Groups are not allowed to schedule upload tasks')
                        );
                    }
                    if ($TaskType->isMulticast()
                        && !$this->obj->doMembersHaveUniformImages()
                    ) {
                        throw new Exception(
                            _('Multicast tasks from groups')
                            . ' '
                            . _('require all hosts have the same image')
                        );
                    }
                    $imageIDs = self::getSubObjectIDs(
                        'Host',
                        array('id' => $this->obj->get('hosts')),
                        'imageID'
                    );
                    $orig_hosts = $this->get('hosts');
                    $hostIDs = self::getSubObjectIDs(
                        'Host',
                        array(
                            'id' => $this->obj->get('hosts'),
                            'imageID' => $imageIDs
                        )
                    );
                    if (count($hostIDs) < 1) {
                        throw new Exception(
                            sprintf(
                                '%s/%s.',
                                _('No valid hosts found and'),
                                _('or no valid images specified')
                            )
                        );
                    }
                    $this->obj->set('hosts', $hostIDs);
                }
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit%s',
                    $this->node,
                    (
                        is_numeric($id)
                        &&  $id > 0 ?
                        sprintf(
                            '&%s=%s',
                            $this->id,
                            $id
                        ) :
                        ''
                    )
                )
            );
        }
        try {
            try {
                $groupTask = $this->obj instanceof Group;
                $success = '';
                if ($scheduleType == 'instant') {
                    $success .= implode(
                        '</ul><ul>',
                        (array)$this->obj->createImagePackage(
                            $TaskType->get('id'),
                            $taskName,
                            $enableShutdown,
                            $enableDebug,
                            $enableSnapins,
                            $groupTask,
                            self::$FOGUser->get('name'),
                            $passreset,
                            false,
                            $wol
                        )
                    );
                } else {
                    $ScheduledTask = self::getClass('ScheduledTask')
                        ->set('taskType', $TaskType->get('id'))
                        ->set('name', $taskName)
                        ->set('hostID', $this->obj->get('id'))
                        ->set('shutdown', $enableShutdown)
                        ->set('other2', $enableSnapins)
                        ->set(
                            'type',
                            (
                                $scheduleType == 'single' ?
                                'S' :
                                'C'
                            )
                        )
                        ->set('isGroupTask', $groupTask)
                        ->set('other3', self::$FOGUser->get('name'))
                        ->set('isActive', 1)
                        ->set('other4', $wol);
                    if ($scheduleType == 'single') {
                        $ScheduledTask->set(
                            'scheduleTime',
                            $scheduleDeployTime->getTimestamp()
                        );
                    } elseif ($scheduleType == 'cron') {
                        foreach ((array)$valsToSet as $key => &$val) {
                            $ScheduledTask->set($key, $val);
                            unset($val);
                        }
                        $ScheduledTask->set('isActive', 1);
                    }
                    if (!$ScheduledTask->save()) {
                        throw new Exception(
                            _('Failed to create scheduled tasking')
                        );
                    }
                    $success .= _('Scheduled tasks successfully created');
                }
            } catch (Exception $e) {
                $error[] = sprintf(
                    '%s %s %s<br/>%s',
                    $this->obj->get('name'),
                    _('Failed to start tasking type'),
                    $TaskType->get('name'),
                    $e->getMessage()
                );
            }
            if (count($error)) {
                throw new Exception(
                    sprintf(
                        '<ul class="nav nav-pills nav-stacked">'
                        . '<li>%s</li>'
                        . '</ul>',
                        implode(
                            '</li><li>',
                            $error
                        )
                    )
                );
            }
        } catch (Exception $e) {
            printf(
                '<div class="col-xs-offset-3">'
                . '<div class="panel panel-danger">'
                . '<div class="panel-body text-center">'
                . '<p>%s</p>'
                . '<p>%s</p>'
                . '</div>'
                . '</div>'
                . '</div>',
                _('Failed to create tasking to some or all'),
                $e->getMessage()
            );
        }
        if (false == empty($success)) {
            switch ($scheduleType) {
            case 'cron':
                $time = sprintf(
                    '%s: %s %s %s %s %s',
                    _('Cron Schedule'),
                    $ScheduledTask->get('minute'),
                    $ScheduledTask->get('hour'),
                    $ScheduledTask->get('dayOfMonth'),
                    $ScheduledTask->get('month'),
                    $ScheduledTask->get('dayOfWeek')
                );
                break;
            case 'single':
                $time = sprintf(
                    '%s: %s',
                    _('Delayed Start'),
                    $scheduleDeployTime->format('Y-m-d H:i:s')
                );
                break;
            }
            printf(
                '<div class="col-xs-offset-3">'
                . '<div class="panel panel-success">'
                . '<div class="panel-body text-center">'
                . '<p>%s: %s</p>'
                . '<p>%s%s</p>'
                . '</div>'
                . '</div>'
                . '</div>',
                $TaskType->get('name'),
                _('Successfully created tasks for'),
                $time,
                sprintf(
                    '<ul class="nav nav-pills nav-stacked">%s</ul>',
                    implode((array)$success)
                )
            );
        }
    }
    /**
     * Presents the en-mass delete elements
     *
     * @return void
     */
    public function deletemulti()
    {
        global $sub;
        $this->title = sprintf(
            "%s's to remove",
            (
                $this->childClass !== 'Storage' ?
                $this->childClass :
                sprintf(
                    '%s %s',
                    $this->childClass,
                    (
                        $sub !== 'storageGroup' ?
                        'Node' :
                        'Group'
                    )
                )
            )
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
        );
        $this->templates = array(
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}">${name}</a>',
                $this->node
            ),
            '<input type="hidden" value="${id}" name="remitems[]"/>',
        );
        $this->additional = array();
        global $sub;
        global $node;
        $reqID = sprintf(
            '%sIDArray',
            $node
        );
        $reqID = explode(',', $_REQUEST[$reqID]);
        $reqID = array_unique($reqID);
        $reqID = array_filter($reqID);
        foreach ((array)self::getClass($this->childClass)
            ->getManager()
            ->find(
                array('id' => $reqID)
            ) as &$Object
        ) {
            if ($Object->get('protected')) {
                continue;
            }
            $this->data[] = array(
                'id' => $Object->get('id'),
                'name' => $Object->get('name'),
            );
            array_push(
                $this->additional,
                sprintf(
                    '<p>%s</p>',
                    $Object->get('name')
                )
            );
            unset($Object);
        }
        if (count($this->data)) {
            printf(
                '<div class="confirm-message"><p>%s:</p>'
                . '<div id="deleteDiv"></div>',
                $this->title
            );
            $this->render();
            printf(
                '<p class="c"><input type="hidden" name="storagegroup" '
                . 'value="%d"/><input type="submit" name="delete" '
                . 'value="%s?"/></p>',
                (
                    $this->childClass === 'StorageGroup' ?
                    1 :
                    0
                ),
                _('Are you sure you wish to remove these items')
            );
        } else {
            self::setMessage(
                sprintf(
                    '%s<br/>%s',
                    _('No items to delete'),
                    _('None selected or item is protected')
                )
            );
            self::redirect(
                sprintf(
                    '?node=%s',
                    $this->node
                )
            );
        }
    }
    /**
     * Actually performs the deletion actions
     *
     * @return void
     */
    public function deletemultiAjax()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $validate = self::getClass('User')
                ->passwordValidate(
                    $_REQUEST['fogguiuser'],
                    $_REQUEST['fogguipass'],
                    true
                );
            if (!$validate) {
                printf(
                    '###%s',
                    self::$foglang['InvalidLogin']
                );
                exit;
            }
        }
        self::$HookManager->processEvent(
            'MULTI_REMOVE',
            array('removing' => &$_REQUEST['remitems'])
        );
        if ((int)$_REQUEST['storagegroup'] === 1) {
            $this->childClass = 'StorageGroup';
        }
        self::getClass($this->childClass)
            ->getManager()
            ->destroy(
                array('id' => $_REQUEST['remitems'])
            );
        self::setMessage(
            _('All selected items have been deleted')
        );
        self::redirect(
            sprintf(
                '?node=%s',
                $this->node
            )
        );
    }
    /**
     * Displays the basic tasks
     *
     * @return void
     */
    public function basictasksOptions()
    {
        unset($this->headerData);
        $this->templates = array(
            sprintf(
                '<a href="?node=${node}&sub=${sub}&id='
                . '${%s_id}${task_type}"><i class="fa '
                . 'fa-${task_icon} fa-3x"></i><br/>'
                . '${task_name}</a>',
                $this->node
            ),
            '${task_desc}',
        );
        $this->attributes = array(
            array('class' => 'l'),
            array('style' => 'padding-left: 20px'),
        );
        printf("<!-- Basic Tasks -->");
        printf(
            '<div id="%s-tasks" class="tab-pane fade">'
            . '<p class="category">%s %s</p>',
            $this->node,
            $this->childClass,
            _('Tasks')
        );
        $taskTypeIterator = function (&$TaskType) {
            if (!$TaskType->isValid()) {
                return;
            }
            $this->data[] = array(
                'node' => $this->node,
                'sub'=> 'deploy',
                sprintf(
                    '%s_id',
                    $this->node
                ) =>
                $this->obj->get('id'),
                    'task_type' => sprintf(
                        '&type=%s',
                        $TaskType->get('id')
                    ),
                    'task_icon' => $TaskType->get('icon'),
                    'task_name' => $TaskType->get('name'),
                    'task_desc' => $TaskType->get('description'),
                );
            unset($TaskType);
        };
        $find = array(
            'access' => array('both', $this->node),
            'isAdvanced' => 0
        );
        foreach ((array)self::getClass('TaskTypeManager')
            ->find(
                $find,
                'AND',
                'id'
            ) as &$TaskType
        ) {
            $taskTypeIterator($TaskType);
            unset($TaskType);
        }
        $this->data[] = array(
            'node' => $this->node,
            'sub' => 'edit',
            sprintf(
                '%s_id',
                $this->node
            ) => $this->obj->get('id'),
                'task_type' => sprintf(
                    '#%s-tasks" class="advanced-tasks-link',
                    $this->node
                ),
                'task_icon' => 'bars',
                'task_name' => _('Advanced'),
                'task_desc' => sprintf(
                    '%s %s',
                    _('View advanced tasks for this'),
                    $this->node
                ),
            );
        self::$HookManager->processEvent(
            sprintf(
                '%s_EDIT_TASKS',
                strtoupper($this->childClass)
            ),
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        unset($this->data);
        printf(
            '<div class="advanced-tasks"><h2>%s</h2>',
            _('Advanced Actions')
        );
        unset($TaskTypes);
        $find = array(
            'access' => array('both', $this->node),
            'isAdvanced' => 1
        );
        foreach ((array)self::getClass('TaskTypeManager')
            ->find(
                $find,
                'AND',
                'id'
            ) as &$TaskType
        ) {
            $taskTypeIterator($TaskType);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            sprintf(
                '%s_DATA_ADV',
                strtoupper($this->node)
            ),
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        unset($TaskTypes);
        echo '</div></div>';
        unset($this->data);
    }
    /**
     * Displays the AD options
     *
     * @param mixed  $useAD        whether to use ad or not
     * @param string $ADDomain     the domain to select
     * @param string $ADOU         the ou to select
     * @param string $ADUser       the user to use
     * @param string $ADPass       the password
     * @param string $ADPassLegacy the legacy password
     * @param mixed  $enforce      enforced selected
     * @param mixed  $ownElement   do we need to be our own container
     * @param mixed  $retFields    return just the fields?
     *
     * @return void
     */
    public function adFieldsToDisplay(
        $useAD = '',
        $ADDomain = '',
        $ADOU = '',
        $ADUser = '',
        $ADPass = '',
        $ADPassLegacy = '',
        $enforce = '',
        $ownElement = true,
        $retFields = false
    ) {
        global $node;
        global $sub;
        if ($this->obj->isValid()) {
            if (empty($useAD)) {
                $useAD = $this->obj->get('useAD');
            }
            if (empty($ADDomain)) {
                $ADDomain = $this->obj->get('ADDomain');
            }
            if (empty($ADOU)) {
                $ADOU = trim($this->obj->get('ADOU'));
                $ADOU = str_replace(';', '', $ADOU);
            }
            if (empty($ADUser)) {
                $ADUser = $this->obj->get('ADUser');
            }
            if (empty($ADPass)) {
                $ADPass = $this->obj->get('ADPass');
            }
            if (empty($ADPassLegacy)) {
                $ADPassLegacy = $this->obj->get('ADPassLegacy');
            }
        }
        $OUs = array_unique(
            array_filter(
                explode(
                    '|',
                    self::getSetting('FOG_AD_DEFAULT_OU')
                )
            )
        );
        $ADOU = trim($ADOU);
        $ADOU = str_replace(';', '', $ADOU);
        $optFound = $ADOU;
        if (count($OUs) > 1) {
            ob_start();
            printf(
                '<option value="">- %s -</option>',
                self::$foglang['PleaseSelect']
            );
            foreach ((array)$OUs as &$OU) {
                $OU = trim($OU);
                $ou = str_replace(';', '', $OU);
                if (!$optFound && $ou === $ADOU) {
                    $optFound = $ou;
                }
                if (!$optFound && false !== strpos($OU, ';')) {
                    $optFound = $ou;
                }
                printf(
                    '<option value="%s"%s>%s</option>',
                    $ou,
                    (
                        $optFound === $ou ?
                        ' selected' :
                        ''
                    ),
                    $ou
                );
            }
            $OUOptions = sprintf(
                '<select id="adOU" class="form-control" name="ou">'
                . '%s</select>',
                ob_get_clean()
            );
        } else {
            $OUOptions = sprintf(
                '<input id="adOU" class="form-control" type="text" name='
                . '"ou" value="%s" autocomplete="off"/>',
                $ADOU
            );
        }
        $fields = array(
            '<label for="clearAD">'
            . _('Clear all fields?')
            . '</label>' => '<button class="btn btn-default btn-block" '
            . 'type="button" id="clearAD">'
            . _('Clear Fields')
            . '</button>',
            sprintf(
                '<label for="adEnabled">%s</label>',
                _('Join Domain after deploy')
            ) => sprintf(
                '<input id="adEnabled" type="checkbox" name="domain"%s/>',
                (
                    $useAD ?
                    ' checked' :
                    ''
                )
            ),
            sprintf(
                '<label for="adDomain">%s</label>',
                _('Domain name')
            ) => sprintf(
                '<div class="input-group">'
                . '<input id="adDomain" class="form-control" type="text" '
                . 'name="domainname" value="%s" autocomplete="off"/>'
                . '</div>',
                $ADDomain
            ),
            sprintf(
                '<label for="adOU">%s'
                . '<br/>(%s)'
                . '</label>',
                _('Organizational Unit'),
                _('Blank for default')
            ) => $OUOptions,
            sprintf(
                '<label for="adUsername">%s</label>',
                _('Domain Username')
            ) => sprintf(
                '<div class="input-group">'
                . '<input id="adUsername" class="form-control" type="text" '
                . 'name="domainuser" value="%s" autocomplete="off"/>'
                . '</div>',
                $ADUser
            ),
            sprintf(
                '<label for="adPassword">%s'
                . '<br/>(%s)'
                . '</label>',
                _('Domain Password'),
                _('Will auto-encrypt plaintext')
            ) => sprintf(
                '<div class="input-group">'
                . '<input id="adPassword" class="form-control" type='
                . '"password" '
                . 'name="domainpassword" value="%s" autocomplete="off"/>'
                . '</div>',
                $ADPass
            ),
            sprintf(
                '<label for="adPasswordLegacy">%s'
                . '<br/>(%s)'
                . '</label>',
                _('Domain Password Legacy'),
                _('Must be encrypted')
            ) => sprintf(
                '<div class="input-group">'
                . '<input id="adPasswordLegacy" class="form-control" '
                . 'type="password" name="domainpasswordlegacy" '
                . 'value="%s" autocomplete="off"/>'
                . '</div>',
                $ADPassLegacy
            ),
            sprintf(
                '<label for="ensel">'
                . '%s?'
                . '</label>',
                _('Name Change/AD Join Forced reboot')
            ) =>
            sprintf(
                '<input name="enforcesel" type="checkbox" id="'
                . 'ensel" autocomplete="off"%s/>',
                (
                    $enforce ?
                    ' checked' :
                    ''
                )
            ),
            '<label for="'
            . $node
            . '-'
            . $sub
            . '">'
            . _('Make changes?')
            . '</label>' => '<button class="'
            . 'btn btn-default btn-block" type="submit" name='
            . '"updatead" id="'
            . $node
            . '-'
            . $sub
            . '">'
            . (
                $sub == 'add' ?
                _('Add') :
                _('Update')
            )
            . '</button>'
        );
        if ($retFields) {
            return $fields;
        }
        unset(
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        echo '<!-- Active Directory -->';
        if ($ownElement) {
            echo '<div id="'
                . $node
                . '-active-directory" class="tab-pane fade">';
            echo '<form class="form-horizontal" method="post" action="'
                . $this->formAction
                . '&tab='
                . $node
                . '-active-directory'
                . '">';
        }
        echo '<h4 class="title text-center">';
        echo _('Active Directory');
        echo '</h4>';
        echo '<input type="text" name="fakeusernameremembered" class='
            . '"fakes hidden"/>';
        echo '<input type="password" name="fakepasswordremembered" class='
            . '"fakes hidden"/>';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array('class' => 'form-group'),
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            sprintf(
                '%s_EDIT_AD',
                strtoupper($this->childClass)
            ),
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'attributes' => &$this->attributes,
                'templates' => &$this->templates
            )
        );
        $this->render();
        if ($ownElement) {
            echo '</form>';
            echo '</div>';
        }
        unset($this->data);
    }
    /**
     * Get's the adinformation from ajax
     *
     * @return void
     */
    public function adInfo()
    {
        if (!self::$ajax) {
            return;
        }
        $items = array(
            'DOMAINNAME',
            'OU',
            'PASSWORD',
            'PASSWORD_LEGACY',
            'USER',
        );
        $names = array();
        foreach ((array)$items as &$item) {
            $names[] = sprintf(
                'FOG_AD_DEFAULT_%s',
                $item
            );
            unset($item);
        }
        list(
            $domainname,
            $ou,
            $password,
            $password_legacy,
            $user
        ) = self::getSubObjectIDs(
            'Service',
            array('name' => $names),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        echo json_encode(
            array(
                'domainname' => $domainname,
                'ou' => $ou,
                'domainpass' => $password,
                'domainpasslegacy' => $password_legacy,
                'domainuser' => $user,
            )
        );
        exit;
    }
    /**
     * Fetches the kernels
     *
     * @return mixed
     */
    public function kernelfetch()
    {
        try {
            if ($_SESSION['allow_ajax_kdl']
                && $_SESSION['dest-kernel-file']
                && $_SESSION['tmp-kernel-file']
                && $_SESSION['dl-kernel-file']
            ) {
                if ($_REQUEST['msg'] == 'dl') {
                    $fh = fopen(
                        $_SESSION['tmp-kernel-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new Exception(
                            _('Error: Failed to open temp file')
                        );
                    }
                    /*$test = self::$FOGURLRequests
                        ->isAvailable($_SESSION['dl-kernel-file']);
                    $test = array_shift($test);
                    if (false === $test) {
                        throw new Exception(
                            _('Error: Failed to connect to server')
                        );
                    }*/
                    self::$FOGURLRequests->process(
                        $_SESSION['dl-kernel-file'],
                        'GET',
                        false,
                        false,
                        false,
                        false,
                        $fh
                    );
                    if (!file_exists($_SESSION['tmp-kernel-file'])) {
                        throw new Exception(
                            _('Error: Failed to download kernel')
                        );
                    }
                    $filesize = self::getFilesize(
                        $_SESSION['tmp-kernel-file']
                    );
                    if (!$filesize >  1048576) {
                        throw new Exception(
                            sprintf(
                                '%s: %s: %s - %s',
                                _('Error'),
                                _('Download Failed'),
                                _('Failed'),
                                _('filesize'),
                                $filesize
                            )
                        );
                    }
                    die('##OK##');
                } elseif ($_REQUEST['msg'] == 'tftp') {
                    $destfile = $_SESSION['dest-kernel-file'];
                    $tmpfile = $_SESSION['tmp-kernel-file'];
                    unset(
                        $_SESSION['dest-kernel-file'],
                        $_SESSION['tmp-kernel-file'],
                        $_SESSION['dl-kernel-file']
                    );
                    $orig = sprintf(
                        '/%s/%s',
                        trim(self::getSetting('FOG_TFTP_PXE_KERNEL_DIR'), '/'),
                        $destfile
                    );
                    $backuppath = sprintf(
                        '/%s/backup/',
                        dirname($orig)
                    );
                    $backupfile = sprintf(
                        '%s%s_%s',
                        $backuppath,
                        $destfile,
                        self::formatTime('', 'Ymd_His')
                    );
                    list(
                        $tftpPass,
                        $tftpUser,
                        $tftpHost
                    ) = self::getSubObjectIDs(
                        'Service',
                        array(
                            'name' => array(
                                'FOG_TFTP_FTP_PASSWORD',
                                'FOG_TFTP_FTP_USERNAME',
                                'FOG_TFTP_HOST'
                            )
                        ),
                        'value',
                        false,
                        'AND',
                        'name',
                        false,
                        ''
                    );
                    self::$FOGFTP
                        ->set('host', $tftpHost)
                        ->set('username', $tftpUser)
                        ->set('password', $tftpPass)
                        ->connect();
                    if (!self::$FOGFTP->exists($backuppath)) {
                        self::$FOGFTP->mkdir($backuppath);
                    }
                    if (self::$FOGFTP->exists($orig)) {
                        self::$FOGFTP->rename($orig, $backupfile);
                    }
                    self::$FOGFTP
                        ->delete($orig)
                        ->rename($tmpfile, $orig)
                        ->chmod(0755, $orig)
                        ->close();
                    unlink($tmpfile);
                    die('##OK##');
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        self::$FOGFTP->close();
    }
    /**
     * Hands out the login information
     * such as version and number of users
     *
     * @return void
     */
    public function loginInfo()
    {
        $urls = array(
            'http://fogproject.org/globalusers',
            'http://fogproject.org/version/index.php?stable&dev&svn'
        );
        $resp = self::$FOGURLRequests->process($urls);
        $data['sites'] = $resp[0];
        $data['version'] = $resp[1];
        echo json_encode($data);
        exit;
    }
    /**
     * Gets the associated info from the mac addresses
     *
     * @return void
     */
    public function getmacman()
    {
        try {
            if (!self::getMACLookupCount()) {
                throw new Exception(
                    sprintf(
                        '<a href="?node=about&sub=maclist">%s</a>',
                        _('Load MAC Vendors')
                    )
                );
            }
            $MAC = self::getClass('MACAddress', $_REQUEST['prefix']);
            $prefix = $MAC->getMACPrefix();
            if (!$MAC->isValid() || !$prefix) {
                throw new Exception(_('Unknown'));
            }
            $OUI = self::getClass('OUIManager')->find(array('prefix'=>$prefix));
            $OUI = array_shift($OUI);
            if (!(($OUI instanceof OUI) && $OUI->isValid())) {
                throw new Exception(_('Not found'));
            }
            $Data = sprintf('<small>%s</small>', $OUI->get('name'));
        } catch (Exception $e) {
            $Data = sprintf('<small>%s</small>', $e->getMessage());
        }
        echo $Data;
        exit;
    }
    /**
     * Presents the delete page for the object
     *
     * @return void
     */
    public function delete()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Remove'),
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($this->obj instanceof Group) {
            $fieldsg = array(
                '<label class="control-label" for="massDel">'
                . _('Delete hosts within')
                . '</label>' => '<div class="input-group checkbox">'
                . '<input type="checkbox" name="massDelHosts" id="'
                . 'massDel"/>'
                . '</div>'
            );
        } elseif ($this->obj instanceof Image || $this->obj instanceof Snapin) {
            $fieldsi = array(
                '<label class="control-label" for="andFile">'
                . _('Delete files')
                . '</label>' => '<div class="input-group checkbox">'
                . '<input type="checkbox" name="andFile" id="'
                . 'andFile"/>'
                . '</div>'
            );
        }
        $fields = self::fastmerge(
            (array)$fieldsg,
            (array)$fieldsi,
            array(
                '<label class="control-label" for="delete">'
                . _('Delete')
                . ' '
                . $this->obj->get('name')
                . '</label>' => '<input type="hidden" name="remitems[]" '
                . 'value="'
                . $this->obj->get('id')
                . '"/>'
                . '<button type="submit" name="delete" id="delete" '
                . 'class="btn btn-default btn-block">'
                . '${label}'
                . '</button>'
            )
        );
        $fields = array_filter($fields);
        self::$HookManager->processEvent(
            sprintf(
                '%s_DEL_FIELDS',
                strtoupper($this->node)
            ),
            array($this->childClass => &$this->obj)
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'label' => $this->title,
            );
            unset($input, $field);
        }
        self::$HookManager->processEvent(
            sprintf(
                '%S_DEL',
                strtoupper($this->childClass)
            ),
            array($this->childClass => &$this->obj)
        );
        echo '<div id="deleteDiv"></div>';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render();
        echo '</form>';
    }
    /**
     * Sends the new client the configuration options
     *
     * @return void
     */
    public function configure()
    {
        $Services = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_CLIENT_CHECKIN_TIME',
                    'FOG_CLIENT_MAXSIZE',
                    'FOG_GRACE_TIMEOUT',
                    'FOG_TASK_FORCE_REBOOT'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        printf(
            "#!ok\n"
            . "#sleep=%d\n"
            . "#maxsize=%d\n"
            . "#promptTime=%d\n"
            . "#force=%s",
            array_shift($Services) + mt_rand(1, 91),
            array_shift($Services),
            array_shift($Services),
            array_shift($Services)
        );
        exit;
    }
    /**
     * Authorizes the client with the server
     *
     * @return void
     */
    public function authorize()
    {
        try {
            $Host = self::getHostItem(true);
            $data = array_values(
                array_map(
                    'bin2hex',
                    self::certDecrypt(
                        array(
                            $_REQUEST['sym_key'],
                            $_REQUEST['token']
                        )
                    )
                )
            );
            $key = $data[0];
            $token = $data[1];
            if ($Host->get('sec_tok')
                && $token !== $Host->get('sec_tok')
            ) {
                $Host
                    ->set(
                        'pub_key',
                        null
                    )->save()->load();
                throw new Exception('#!ist');
            }
            if ($Host->get('sec_tok')
                && !$key
            ) {
                throw new Exception('#!ihc');
            }
            $expire = self::niceDate($Host->get('sec_time'));
            if (self::niceDate() > $expire
                || !trim($Host->get('pub_key'))
            ) {
                $Host
                    ->set(
                        'sec_time',
                        self::niceDate()
                            ->modify('+30 minutes')
                            ->format('Y-m-d H:i:s')
                    )
                    ->set(
                        'sec_tok',
                        self::createSecToken()
                    );
            }
            $Host
                ->set('pub_key', $key)
                ->save();
            $vals['token'] = $Host->get('sec_tok');
            if (self::$json === true) {
                printf(
                    '#!en=%s',
                    self::certEncrypt(
                        json_encode($vals),
                        $Host
                    )
                );
                exit;
            }
            printf(
                '#!en=%s',
                self::certEncrypt(
                    "#!ok\n#token={$Host->get(sec_tok)}",
                    $Host
                )
            );
        } catch (Exception $e) {
            if (self::$json === true) {
                if ($e->getMessage() == '#!ihc') {
                    die($e->getMessage());
                }
                $err = str_replace('#!', '', $e->getMessage());
                echo json_encode(
                    array('error' => $err)
                );
                exit;
            }
            if ($e->getMessage() == '#!ist') {
                echo json_encode(
                    array('error' => 'ist')
                );
                exit;
            }
            echo  $e->getMessage();
        }
        exit;
    }
    /**
     * Used by the new client and collects
     * all the information at once. This
     * allows the client to do much less polls
     * to the server.
     *
     * @return void
     */
    public function requestClientInfo()
    {
        if (isset($_REQUEST['configure'])) {
            list(
                $bannerimg,
                $bannersha,
                $checkin,
                $maxsize,
                $pcolor,
                $coname,
                $timeout,
                $freboot
            ) = self::getSubObjectIDs(
                'Service',
                array(
                    'name' => array(
                        'FOG_CLIENT_BANNER_IMAGE',
                        'FOG_CLIENT_BANNER_SHA',
                        'FOG_CLIENT_CHECKIN_TIME',
                        'FOG_CLIENT_MAXSIZE',
                        'FOG_COMPANY_COLOR',
                        'FOG_COMPANY_NAME',
                        'FOG_GRACE_TIMEOUT',
                        'FOG_TASK_FORCE_REBOOT'
                    )
                ),
                'value',
                false,
                'AND',
                'name',
                false,
                ''
            );
            $vals = array(
                'sleep' => $checkin + mt_rand(1, 91),
                'maxsize' => $maxsize,
                'promptTime' => $timeout,
                'force' => (bool)$freboot,
                'bannerURL' => (
                    $bannerimg ?
                    sprintf(
                        '/management/other/%s',
                        $bannerimg
                    ) :
                    ''
                ),
                'bannerHash' => strtoupper($bannersha),
                'color' => "#$pcolor",
                'company' => $coname
            );
            echo json_encode($vals);
            exit;
        }
        if (isset($_REQUEST['authorize'])) {
            $this->authorize(true);
        }
        // Handles adding additional system macs for us.
        ob_start();
        self::getClass('RegisterClient')->json();
        ob_end_clean();
        try {
            $igMods = array(
                'dircleanup',
                'usercleanup',
                'clientupdater',
                'hostregister',
            );
            $globalModules = array_diff(
                self::getGlobalModuleStatus(false, true),
                array(
                    'dircleanup',
                    'usercleanup',
                    'clientupdater',
                    'hostregister'
                )
            );
            $globalInfo = self::getGlobalModuleStatus();
            $globalDisabled = array();
            foreach ((array)$globalInfo as $key => &$en) {
                if (in_array($key, $igMods)) {
                    continue;
                }
                if (!$en) {
                    $globalDisabled[] = $key;
                }
                unset($key, $en);
            }
            $this->Host = self::getHostItem(
                true,
                false,
                false,
                false,
                self::$newService || self::$json
            );
            $hostModules = self::getSubObjectIDs(
                'Module',
                array('id' => $this->Host->get('modules')),
                'shortName'
            );
            $hostEnabled = array_diff(
                (array)$hostModules,
                (array)$igMods
            );
            $hostDisabled = array_diff(
                (array)$globalModules,
                (array)$hostEnabled
            );
            $array = array();
            foreach ($globalModules as $index => &$key) {
                switch ($key) {
                case 'greenfog':
                    $class='GF';
                    continue 2;
                case 'powermanagement':
                    $class='PM';
                    break;
                case 'printermanager':
                    $class='PrinterClient';
                    break;
                case 'taskreboot':
                    $class='Jobs';
                    break;
                case 'usertracker':
                    $class='UserTrack';
                    break;
                default:
                    $class=$key;
                    break;
                }
                $disabled = in_array(
                    $key,
                    self::fastmerge(
                        (array)$globalDisabled,
                        (array)$hostDisabled
                    )
                );
                if ($disabled) {
                    if (in_array($key, $globalDisabled)) {
                        $array[$key]['error'] = 'ng';
                    } elseif (in_array($key, $hostDisabled)) {
                        $array[$key]['error'] = 'nh';
                    }
                } else {
                    $array[$key] = self::getClass(
                        $class,
                        true,
                        false,
                        false,
                        false,
                        self::$newService || self::$json
                    )->json();
                }
                unset($key);
            }
            //echo json_encode($array, JSON_UNESCAPED_UNICODE);
            $this->sendData(
                json_encode(
                    $array,
                    JSON_UNESCAPED_UNICODE
                ),
                true,
                $array
            );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }
    /**
     * Clears the Host's AES information. Used
     * by the button to clear fields and reset
     * encryption as well
     *
     * @return void
     */
    public function clearAES()
    {
        global $groupid;
        global $id;
        if (!(is_numeric($groupid) || is_numeric($id))) {
            return;
        }
        if ($id < 1 && $groupid < 1) {
            return;
        }
        if ($groupid < 1) {
            $hosts = $id;
        } else {
            $hosts = self::getClass('Group', $groupid)
                ->get('hosts');
        }
        self::getClass('HostManager')
            ->update(
                array('id' => $hosts),
                '',
                array(
                    'pub_key' => '',
                    'sec_tok' => '',
                    'sec_time' => '0000-00-00 00:00:00'
                )
            );
    }
    /**
     * Clears group Powermanagement tasks
     *
     * @return void
     */
    public function clearPMTasks()
    {
        global $groupid;
        if (!is_numeric($groupid)) {
            return;
        }
        if ($groupid < 1) {
            return;
        }
        $hosts = self::getClass('Group', $groupid)
            ->get('hosts');
        self::getClass('PowerManagementManager')
            ->destroy(
                array('hostID' => $hosts)
            );
    }
    /**
     * Perform the actual delete
     *
     * @return void
     */
    public function deletePost()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $validate = self::getClass('User')
                ->passwordValidate(
                    $_POST['fogguiuser'],
                    $_POST['fogguipass'],
                    true
                );
            if (!$validate) {
                printf(
                    '###%s',
                    self::$foglang['InvalidLogin']
                );
                exit;
            }
        }
        self::$HookManager->processEvent(
            sprintf(
                '%s_DEL_POST',
                strtoupper($this->node)
            ),
            array($this->childClass => &$this->obj)
        );
        try {
            if ($this->obj->get('protected')) {
                throw new Exception(
                    sprintf(
                        '%s %s',
                        $this->childClass,
                        _('is protected, removal not allowed')
                    )
                );
            }
            if ($this->obj instanceof Group) {
                if (isset($_REQUEST['delHostConfirm'])) {
                    self::getClass('HostManager')
                        ->destroy(
                            array('id' => $this->obj->get('hosts'))
                        );
                }
                if (isset($_REQUEST['massDelHosts'])) {
                    self::redirect(
                        "?node=group&sub=deletehosts&id={$this->obj->get(id)}"
                    );
                }
            }
            if ($_REQUEST['andFile'] === "true") {
                $this->obj->deleteFile();
            }
            if (!$this->obj->destroy()) {
                throw new Exception(
                    _('Failed to destroy')
                );
            }
            self::$HookManager->processEvent(
                sprintf(
                    '%s_DELETE_SUCCESS',
                    strtoupper($this->childClass)
                ),
                array($this->childClass => &$this->obj)
            );
            self::setMessage(
                sprintf(
                    '%s %s: %s',
                    $this->childClass,
                    _('deleted'),
                    $this->obj->get('name')
                )
            );
            self::resetRequest();
            self::redirect(
                sprintf(
                    '?node=%s',
                    $this->node
                )
            );
        } catch (Exception $e) {
            self::$HookManager->processEvent(
                sprintf(
                    '%s_DELETE_FAIL',
                    strtoupper($this->node)
                ),
                array($this->childClass => &$this->obj)
            );
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Resents the page's search elements
     *
     * @return void
     */
    public function search()
    {
        $eventClass = $this->childClass;
        if ($this->childClass == 'Task') {
            $eventClass = 'host';
        }
        $this->title = _('Search');
        self::$HookManager->processEvent(
            sprintf(
                '%s_DATA',
                strtoupper($eventClass)
            ),
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'headerData' => &$this->headerData,
                'attributes' => &$this->attributes,
                'title' => &$this->title,
            )
        );
        self::$HookManager->processEvent(
            sprintf(
                '%s_HEADER_DATA',
                strtoupper($this->childClass)
            ),
            array('headerData' => &$this->headerData)
        );
        $this->render();
    }
    /**
     * Search form submission
     *
     * @return void
     */
    public function searchPost()
    {
        $this->data = array();
        $manager = sprintf(
            '%sManager',
            $this->childClass
        );
        Route::search(
            $this->childClass,
            filter_input(INPUT_POST, 'crit')
        );
        $items = json_decode(Route::getData());
        $type = $this->node
            .'s';
        $search = $items->$type;
        if (count($search) > 0) {
            array_walk($search, static::$returnData);
        }
        $event = sprintf(
            '%s_DATA',
            strtoupper($this->node)
        );
        self::$HookManager->processEvent(
            $event,
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'headerData' => &$this->headerData
            )
        );
        $event = sprintf(
            '%s_HEADER_DATA',
            strtoupper($this->node)
        );
        self::$HookManager->processEvent(
            $event,
            array(
                'headerData' => &$this->headerData
            )
        );
        $this->render();
        unset(
            $this->headerData,
            $this->data,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Presents the membership information
     *
     * @return void
     */
    public function membership()
    {
        $objType = $this->obj instanceof Host;
        $this->data = array();
        echo '<!-- Membership -->';
        printf(
            '<div id="%s-membership">',
            $this->node
        );
        $this->headerData = array(
            sprintf(
                '<input type="checkbox" name="toggle-checkbox%s1" '
                . 'class="toggle-checkbox1" id="toggler"/>'
                . '<label for="toggler"></label>',
                $this->node
            ),
            sprintf(
                '%s %s',
                (
                    $objType ?
                    _('Group') :
                    _('Host')
                ),
                _('Name')
            ),
        );
        $this->templates = array(
            sprintf(
                '<input type="checkbox" name="host[]" value="${host_id}" '
                . 'class="toggle-%s${check_num}" id="host-${host_id}"/>'
                . '<label for="host-${host_id}"></label>',
                (
                    $objType ?
                    'group' :
                    'host'
                )
            ),
            sprintf(
                '<a href="?node=%s&sub=edit&id=${host_id}" '
                . 'title="Edit: ${host_name}">${host_name}</a>',
                (
                    $objType ?
                    'group' :
                    'host'
                )
            ),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>150,'class'=>'l'),
        );
        $ClassCall = (
            $objType ?
            'Group' :
            'Host'
        );
        extract(
            self::getSubObjectIDs(
                $ClassCall,
                array(
                    'id' => $this->obj->get(
                        sprintf(
                            '%ssnotinme',
                            strtolower($ClassCall)
                        )
                    )
                ),
                array(
                    'name',
                    'id'
                )
            )
        );
        $itemParser = function (
            &$nam,
            &$index
        ) use (&$id) {
            $this->data[] = array(
                'host_id'=>$id[$index],
                'host_name'=>$nam,
                'check_num'=>1,
            );
            unset(
                $nam,
                $id[$index],
                $index
            );
        };
        if (count($name) > 0) {
            array_walk($name, $itemParser);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(
                sprintf(
                    'OBJ_%s_NOT_IN_ME',
                    strtoupper($ClassCall)
                ),
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
            printf(
                '<form method="post" action="%s">'
                . '<p class="c">%s %ss %s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="%sMeShow" id="%sMeShow"/>'
                . '<label for="%sMeShow"></label></p>'
                . '<div id="%sNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                _('Check here to see'),
                strtolower($ClassCall),
                _('not within this'),
                $this->node,
                strtolower($ClassCall),
                strtolower($ClassCall),
                strtolower($ClassCall),
                strtolower($ClassCall),
                _('Modify Membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type="submit" '
                . 'value="%s %s(s) to %s" name="addHosts"/></p><br/>',
                _('Add'),
                (
                    $objType ?
                    _('Group') :
                    _('Host')
                ),
                $this->node
            );
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler1"/>'
            . '<label for="toggler1"></label>',
            sprintf(
                '%s %s',
                _($ClassCall),
                _('Name')
            ),
        );
        $this->templates = array(
            '<input type="checkbox" name="hostdel[]" '
            . 'value="${host_id}" class="toggle-action" id="'
            . 'host1-${host_id}"/>'
            . '<label for="host1-${host_id}"></label>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${host_id}" '
                . 'title="Edit: ${host_name}">${host_name}</a>',
                strtolower($ClassCall)
            ),
        );
        extract(
            self::getSubObjectIDs(
                $ClassCall,
                array(
                    'id' => $this->obj->get(
                        sprintf(
                            '%ss',
                            strtolower($ClassCall)
                        )
                    )
                ),
                array(
                    'name',
                    'id'
                )
            )
        );
        if (count($name) > 0) {
            array_walk($name, $itemParser);
        }
        self::$HookManager->processEvent(
            'OBJ_MEMBERSHIP',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remhosts"/></p>',
                _('Delete Selected'),
                $ClassCall,
                _('From'),
                $this->node
            );
        }
    }
    /**
     * Commonized membership actions
     *
     * @return void
     */
    public function membershipPost()
    {
        if (self::$ajax) {
            return;
        }
        if (isset($_REQUEST['addHosts'])) {
            $this->obj->addHost($_REQUEST['host']);
        }
        if (isset($_REQUEST['remhosts'])) {
            $this->obj->removeHost($_REQUEST['hostdel']);
        }
        if ($this->obj->save()) {
            self::setMessage(
                sprintf(
                    '%s %s',
                    $this->obj->get('name'),
                    _('saved successfully')
                )
            );
            self::redirect($this->formAction);
        }
    }
    /**
     * Perform wakeup stuff
     *
     * @return void
     */
    public function wakeEmUp()
    {
        $macs = self::parseMacList($_REQUEST['mac']);
        if (count($macs) < 1) {
            return;
        }
        self::getClass('WakeOnLan', implode('|', $macs))->send();
    }
    /**
     * Presents the relevant class items for export
     *
     * @return void
     */
    public function export()
    {
        $this->title = sprintf(
            'Export %s',
            $this->childClass
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            sprintf(
                "%s %s's %s.",
                _('Click the button to download the'),
                strtolower($this->childClass),
                _('table backup')
            ) => sprintf(
                '<div id="exportDiv"></div>'
                . '<input name="export" type="submit" value="%s"/>',
                _('Export')
            ),
        );
        $report = self::getClass('ReportMaker');
        self::arrayRemove('id', $this->databaseFields);
        foreach ((array)self::getClass($this->childClass)
            ->getManager()
            ->find() as &$Item
        ) {
            if ($Item instanceof Host) {
                $macs = $maccolumn = array();
                $macs[] = $Item->get('mac');
                $macs = self::fastmerge($macs, $Item->get('additionalMACs'));
                $macs = self::parseMacList($macs);
                foreach ((array)$macs as &$mac) {
                    if (!$mac->isValid()) {
                        continue;
                    }
                    $maccolumn[] = $mac->__toString();
                    unset($mac);
                }
                $report->addCSVCell(
                    implode(
                        '|',
                        $maccolumn
                    )
                );
                unset($maccolumn);
            }
            $keys = array_keys((array)$this->databaseFields);
            foreach ((array)$keys as $ind => &$field) {
                $report->addCSVCell($Item->get($field));
                unset($field);
            }
            self::$HookManager->processEvent(
                sprintf(
                    '%s_EXPORT_REPORT',
                    strtoupper($this->childClass)
                ),
                array(
                    'report' => &$report,
                    $this->childClass => &$Item
                )
            );
            $report->endCSVLine();
            unset($Item);
        }
        $_SESSION['foglastreport'] = serialize($report);
        printf(
            '<form method="post" action="export.php?type=%s">',
            strtolower($this->childClass)
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        self::$HookManager->processEvent(
            sprintf(
                '%s_EXPORT',
                strtoupper($this->childClass)
            ),
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Presents the importer elements
     *
     * @return void
     */
    public function import()
    {
        $this->title = sprintf(
            'Import %s List',
            $this->childClass
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        printf(
            '%s %s. %s %s. %s, %s, %s, %s, %s...',
            _('This page allows you to upload a CSV'),
            _('file into FOG to ease migration'),
            _('It will operate based on the fields that'),
            _('are normally required by each area'),
            _('For example'),
            _('Hosts will have macs'),
            _('name'),
            _('description'),
            _('etc')
        );
        printf(
            '<form enctype="multipart/form-data" method="post" action="%s">',
            $this->formAction
        );
        $fields = array(
            _('CSV File') => '<input class="smaller" type="file" name="file" />',
            '&nbsp;' => sprintf(
                '<input class="smaller" type="submit" value="%s"/>',
                _('Upload CSV')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        $upper = strtoupper($this->childClass);
        $event = sprintf(
            '%s_IMPORT_OUT',
            $upper
        );
        $arr = array(
            'headerData' => &$this->headerData,
            'data' => &$this->data,
            'templates' => &$this->templates,
            'attributes' => &$this->attributes
        );
        self::$HookManager->processEvent(
            $event,
            $arr
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Perform the import based on the uploaded file
     *
     * @return void
     */
    public function importPost()
    {
        try {
            $mimes = array(
                'text/csv',
                'text/anytext',
                'text/comma-separated-values',
                'application/csv',
                'application/excel',
                'application/vnd.msexcel',
                'application/vnd.ms-excel',
            );
            $fileinfo = pathinfo($_FILES['file']['name']);
            $ext = $fileinfo['extension'];
            $Item = new $this->childClass();
            $mime = $_FILES['file']['type'];
            if (!in_array($mime, $mimes)) {
                if ($ext !== 'csv') {
                    self::setMessage(_('File must be a csv'));
                    self::redirect($this->formAction);
                }
            }
            if ($_FILES['file']['error'] > 0) {
                throw new UploadException($_FILES['file']['error']);
            }
            $tmpf = pathinfo($_FILES['file']['tmp_name']);
            $file = sprintf(
                '%s%s%s',
                $tmpf['dirname'],
                DS,
                $tmpf['basename']
            );
            if (!file_exists($file)) {
                throw new Exception(_('Could not find temp filename'));
            }
            $numSuccess = $numFailed = $numAlreadExist = 0;
            $fh = fopen($file, 'rb');
            self::arrayRemove(
                'id',
                $this->databaseFields
            );
            $comma_count = count(array_keys($this->databaseFields));
            $iterator = 0;
            if ($Item instanceof Host) {
                $comma_count++;
                $iterator = 1;
            }
            $ItemMan = $Item->getManager();
            $modules = self::getSubObjectIDs(
                'Module',
                array('isDefault' => 1)
            );
            $totalRows = 0;
            while (($data = fgetcsv($fh, 1000, ',')) !== false) {
                $importCount = count($data);
                if ($importCount > 0
                    && $importCount > $comma_count
                ) {
                    throw new Exception(
                        _('Invalid data being parsed')
                    );
                }
                try {
                    $dbkeys = array_keys($this->databaseFields);
                    if ($Item instanceof Host) {
                        $macs = self::parseMacList($data[0]);
                        $Host = self::getClass('HostManager')
                            ->getHostByMacAddresses($macs);
                        if ($Host
                            && $Host->isValid()
                        ) {
                            throw new Exception(
                                _('One or more macs are associated with a host')
                            );
                        }
                        $primac = array_shift($macs);
                        $index = array_search('productKey', $dbkeys) + 1;
                        $test_encryption = self::aesdecrypt($data[$index]);
                        if ($test_base64 = base64_decode($data[$index])) {
                            $data[$index] = self::aesencrypt($test_base64);
                        } elseif (mb_detect_encoding(
                            $test_encryption,
                            'utf-8',
                            true
                        )
                        ) {
                            $data[$index] = self::aesencrypt($data[$index]);
                        }
                    }
                    if ($ItemMan->exists($data[$iterator])) {
                        throw new Exception(
                            _('This host already exists')
                        );
                    }
                    foreach ((array)$dbkeys as $ind => &$field) {
                        $ind += $iterator;
                        if ($field == 'password') {
                            $Item->set($field, $data[$ind], true);
                        } else {
                            $Item->set($field, $data[$ind]);
                        }
                        unset($field);
                    }
                    if ($Item instanceof Host) {
                        $Item
                            ->addModule($modules)
                            ->addPriMAC($primac)
                            ->addAddMAC($macs);
                    }
                    if ($Item->save()) {
                        $Item->load();
                        $totalRows++;
                        $itemCap = strtoupper($this->childClass);
                        $event = sprintf(
                            '%s_IMPORT',
                            $itemCap
                        );
                        $arr = array(
                            'data' => &$data,
                            $this->childClass => &$Item
                        );
                        self::$HookManager->processEvent(
                            $event,
                            $arr
                        );
                        $numSuccess++;
                        $Item = new $this->childClass();
                    } else {
                        $numFailed++;
                    }
                } catch (Exception $e) {
                    $numFailed++;
                    $uploadErrors .= sprintf(
                        '%s #%s: %s<br/>',
                        _('Row'),
                        $totalRows,
                        $e->getMessage()
                    );
                }
            }
            fclose($fh);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        $this->title = sprintf(
            '%s %s %s',
            _('Import'),
            $this->childClass,
            _('Results')
        );
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Total Rows') => $totalRows,
            sprintf(
                '%s %ss',
                _('Successful'),
                $this->childClass
            ) => $numSuccess,
            sprintf(
                '%s %ss',
                _('Failed'),
                $this->childClass
            ) => $numFailed,
            _('Errors') => $uploadErrors,
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        $upper = strtoupper($this->childClass);
        $event = sprintf(
            '%s_IMPORT_FIELDS',
            $upper
        );
        $arr = array(
            'headerData' => &$this->headerData,
            'data' => &$this->data,
            'templates' => &$this->templates,
            'attributes' => &$this->attributes
        );
        self::$HookManager->processEvent(
            $event,
            $arr
        );
        $this->render();
    }
    /**
     * Build select form in generic form.
     *
     * @param string $name  The name of the select item.
     * @param array  $items The items to generate.
     *
     * @return string
     */
    public static function selectForm($name, $items = array())
    {
        ob_start();
        printf(
            '<select name="%s"><option value="">- %s -</option>',
            $name,
            _('Please select an option')
        );
        foreach ($items as &$item) {
            printf(
                '<option value="%s">%s</option>',
                $item,
                $item
            );
            unset($item);
        }
        echo '</select>';
        return ob_get_clean();
    }
}
