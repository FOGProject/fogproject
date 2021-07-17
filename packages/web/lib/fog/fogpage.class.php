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
    public $formAction = '';
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
            'requestClientInfo'
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
                $this->name .= ' '
                    . _('Edit')
                    . ': '
                    . $this->obj->get('name');
            }
        }
        $this->reportString = '<h4 class="title">'
            . '<div id="exportDiv"></div>'
            . '<a id="csvsub" href="../management/export.php?filename=%s&type=csv" '
            . 'alt="%s" title="%s" target="_blank" data-toggle="tooltip" '
            . 'data-placement="top">%s</a> '
            . '<a id="pdfsub" href="../management/export.php?filename=%s&type=pdf" '
            . 'alt="%s" title="%s" target="_blank" data-toggle="tooltip" '
            . 'data-placement="top">%s</a>'
            . '</h4>';
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
        if (is_array($data) && count($data) > 0) {
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
            global $node;
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
            $type = $node.'s';
            $items = $items->$type;
            if (is_array($items) && count($items) > 0) {
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
            echo '<div class="col-xs-9">';
            $this->indexDivDisplay();
            echo '</div>';
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
            if (is_array($args) && count($args) > 0) {
                array_walk($args, $vals);
            }
            printf(
                'Index page of: %s%s',
                get_class($this),
                (
                    (is_array($args) && count($args)) ?
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
     * @param int $colsize Col size
     *
     * @return void
     */
    public function render($colsize = 9)
    {
        echo $this->process($colsize);
    }
    /**
     * Process the information
     *
     * @param int $colsize Col Size
     *
     * @return string
     */
    public function process($colsize = 9)
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
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class='
                        . '"action-boxes host '
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
                    $actionbox .= '<div class="col-xs-8">';
                    $actionbox .= '<div class="input-group">';
                    $actionbox .= '<input type="hidden" name="hostIDArray"/>';
                    $actionbox .= '<input type="text" name="group_new" id='
                        . '"group_new" class="form-control"/>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<div class="text-center">';
                    $actionbox .= '<label class="control-label">';
                    $actionbox .= _('or');
                    $actionbox .= '</label>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4" for=';
                    $actionbox .= '"group">';
                    $actionbox .= _('Add to group');
                    $actionbox .= '</label>';
                    $actionbox .= '<div class="col-xs-8">';
                    $actionbox .= self::getClass('GroupManager')->buildSelectBox();
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '<div class="form-group">';
                    $actionbox .= '<label class="control-label col-xs-4" for=';
                    $actionbox .= '"process">';
                    $actionbox .= _('Make changes?');
                    $actionbox .= '</label>';
                    $actionbox .= '<div class="col-xs-8">';
                    $actionbox .= '<button type="submit" class='
                        . '"btn btn-info btn-block" name="process" id="process">';
                    $actionbox .= _('Update');
                    $actionbox .= '</button>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '</form>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                    $actionbox .= '</div>';
                }
                if ($node != 'task') {
                    if (!$actionbox) {
                        $actionbox .= '</div>';
                        $actionbox .= '</div>';
                    }
                    $actionbox .= '<div class='
                        . '"action-boxes del hiddeninitially">';
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
                    $actionbox .= '<div class="col-xs-8">';
                    $actionbox .= '<input type="hidden" name="'
                        . strtolower($node)
                        . 'IDArray"/>';
                    $actionbox .= '<button type="submit" class='
                        . '"btn btn-danger btn-block" id="'
                        . 'del-'
                        . $node
                        . '">';
                    $actionbox .= _('Delete');
                    $actionbox .= '</button>';
                    $actionbox .= '</div>';
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
                            (is_array($this->data) && count($this->data) > 0) ?
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
                echo '<div class="table-holder col-xs-'
                    . $colsize
                    . '">';
            }
            echo '<table class="table table-responsive'
                . (
                    is_array($this->data) && count($this->data) < 1 ?
                    ' noresults' :
                    ''
                )
                . '">';
            if (is_array($this->data) && count($this->data) < 1) {
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
                if (is_array($this->headerData) && count($this->headerData) > 0) {
                    echo '<thead>';
                    echo $this->buildHeaderRow();
                    echo '</thead>';
                }
                echo '<tbody>';
                $tablestr = '';
                foreach ((array)$this->data as &$rowData) {
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
        if (is_array($this->headerData) && count($this->headerData) < 1) {
            return;
        }
        ob_start();
        echo '<tr class="header'
            . (
                is_array($this->data) && count($this->data) < 1 ?
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
            ob_start();
            echo '<select class="form-control input-group" name="snapin" id="'
                . 'snapin" autocomplete="off">';
            echo '<option value="">- ';
            echo self::$foglang['PleaseSelect'];
            echo ' -</option>';
            echo '<option disabled>';
            echo '---------- '
                . _('Host Associated Snapins')
                . ' ----------';
            echo '</option>';
            Route::listem(
                'snapin',
                'name',
                false,
                array('id' => $this->obj->get('snapins'))
            );
            $snapins = json_decode(
                Route::getData()
            );
            $snapins = $snapins->snapins;
            foreach ((array)$snapins as &$Snapin) {
                echo '<option value="'
                    . $Snapin->id
                    . '">';
                echo $Snapin->name;
                echo ' - (';
                echo $Snapin->id;
                echo ')';
                echo '</option>';
                unset($Snapin);
            }
            unset($snapins);
            echo '<option disabled>';
            echo '---------- '
                . _('Host Unassociated Snapins')
                . ' ----------';
            echo '</option>';
            Route::listem(
                'snapin',
                'name',
                false,
                array('id' => $this->obj->get('snapinsnotinme'))
            );
            $snapins = json_decode(
                Route::getData()
            );
            $snapins = $snapins->snapins;
            foreach ((array)$snapins as &$Snapin) {
                echo '<option value="'
                    . $Snapin->id
                    . '">';
                echo $Snapin->name;
                echo ' - (';
                echo $Snapin->id;
                echo ')';
                echo '</option>';
                unset($Snapin);
            }
            unset($snapins);
            $snapselector = ob_get_clean();
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
            $snapselector = self::getClass('SnapinManager')->buildSelectBox();
            Route::listem(
                'host',
                'name',
                false,
                ['id' => $this->obj->get('hosts')]
            );
            $Hosts = json_decode(
                Route::getData()
            );
            $Hosts = $Hosts->hosts;
            foreach ((array)$Hosts as &$Host) {
                $imageID = $imageName = '';
                if ($TaskType->isImagingTask()) {
                    $Image = $Host->image;
                    if (!$Image->isEnabled) {
                        continue;
                    }
                    $imageID = $Image->id;
                    $imageName = $Image->name;
                }
                $this->data[] = array(
                    'host_link' => '?node=host&sub=edit&id=${host_id}',
                    'host_title' => sprintf(
                        '%s: ${host_name}',
                        _('Edit')
                    ),
                    'host_id' => $Host->id,
                    'host_name' => $Host->name,
                    'host_mac' => $Host->primac,
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Confirm tasking');
        echo '</h4>';
        if ($this->obj instanceof Host) {
            if ($this->obj->getImage()->isValid()) {
                echo '<h5 class="title">';
                echo _('Image Associated: ');
                echo $this->obj->getImage()->get('name');
                echo '</h5>';
            }
        }
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Advanced Settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if ($TaskType->get('id') == 13) {
            echo '<div class="form-group">';
            echo '<label class="control-label" for="snapin">';
            echo _('Please select the snapin you want to install');
            echo '</label>';
            echo '<div class="input-group">';
            echo $snapselector;
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
            echo '<label for="shutdown">';
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
            echo '<label for="wol">';
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
                echo '<label for="checkDebug">';
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
        echo '<label for="scheduleInstant">';
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
            echo '<label for="scheduleSingle">';
            echo '<input type="radio" name='
                . '"scheduleType" id="scheduleSingle" value="single"/>';
            echo _('Schedule delayed');
            echo '</label>';
            echo '</div>';
            echo '<div class="form-group hiddeninitially">';
            echo '<label for="scheduleSingleTime">';
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
            echo '<label for="scheduleCron">';
            echo '<input type="radio" name='
                . '"scheduleType" id="scheduleCron" value="cron"/>';
            echo _('Schedule cron-style');
            echo '</label>';
            echo '</div>';
            echo '<div class="form-group hiddeninitially">';
            echo '<div class="cronOptions input-group">';
            echo FOGCron::buildSpecialCron('specialCrons');
            echo '</div>';
            echo '<div class="col-xs-12">';
            echo '<div class="cronInputs">';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronMin" '
                . 'placeholder="min" autocomplete="off" '
                . 'class="form-control scheduleCronMin cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronHour" '
                . 'placeholder="hour" autocomplete="off" '
                . 'class="form-control scheduleCronHour cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronDOM" '
                . 'placeholder="dom" autocomplete="off" '
                . 'class="form-control scheduleCronDOM cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronMonth" '
                . 'placeholder="month" autocomplete="off" '
                . 'class="form-control scheduleCronMonth cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-2">';
            echo '<div class="input-group">';
            echo '<input type="text" name="scheduleCronDOW" '
                . 'placeholder="dow" autocomplete="off" '
                . 'class="form-control scheduleCronDOW cronInput"/>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        if (is_array($this->data) && count($this->data)) {
            echo '<div class="col-xs-12">';
            echo '<label class="control-label col-xs-4" for="taskingbtn">';
            echo _('Create');
            echo ' ';
            echo $TaskType->get('name');
            echo ' ';
            echo _('Tasking');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" class="btn btn-info btn-block" id='
                . '"taskingbtn">';
            echo _('Task');
            echo '</button>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        if ($this->node != 'host') {
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Hosts in task');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body text-center">';
            $this->render(12);
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
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
            $enableSnapins = (int)filter_input(INPUT_POST, 'snapin');
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
            if ($this->obj instanceof Host) {
                if ($this->obj->get('pending')) {
                    throw new Exception(
                        _('Cannot set tasking to pending hosts')
                    );
                }
            } elseif ($this->obj instanceof Group) {
                if (!(isset($_POST['taskhosts'])
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
                    if (is_array($hostIDs) && count($hostIDs) < 1) {
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
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-danger">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Tasking Failed');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body text-center">';
            echo '<div class="row">';
            echo _('Failed to create tasking');
            echo '</div>';
            echo '<div class="row">';
            echo $e->getMessage();
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
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
                        ->set('taskTypeID', $TaskType->get('id'))
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
            if (is_array($error) && count($error)) {
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
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-danger">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Tasking Failed');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body text-center">';
            echo '<div class="row">';
            echo _('Failed to create tasking');
            echo '</div>';
            echo '<div class="row">';
            echo $e->getMessage();
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
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
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-success">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Tasked Successfully');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body text-center">';
            echo _('Task');
            echo ' ';
            echo $TaskType->get('name');
            echo ' ';
            echo _('Successfully created');
            echo '!';
            echo '</div>';
            echo '</div>';
            echo '<div class="panel panel-success">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Created Tasks For');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body text-center">';
            echo '<ul class="nav nav-pills nav-stacked">';
            echo implode((array)$success);
            echo '</ul>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
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
        global $node;
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
        if ('Storage' === $this->childClass) {
            if ('storageGroup' === $sub) {
                $this->childClass = 'StorageGroup';
            } else {
                $this->childClass = 'StorageNode';
            }
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $reqID = $node
            . 'IDArray';
        $items = filter_input(
            INPUT_POST,
            $reqID
        );
        $reqID = array_values(
            array_filter(
                array_unique(
                    explode(',', $items)
                )
            )
        );
        Route::listem(
            $this->childClass,
            'name',
            false,
            ['id' => $reqID]
        );
        $items = json_decode(
            Route::getData()
        );
        $getme = strtolower($this->childClass).'s';
        $items = $items->$getme;
        foreach ((array)$items as &$object) {
            if ($getme == 'plugins') {
                if (!in_array($object->id, $reqID)) {
                    continue;
                }
            }
            if ($object->protected) {
                continue;
            }
            $this->data[] = array(
                'field' => '<input type="hidden" value="'
                . $object->id
                . '" name="remitems[]"/>',
                'input' => '<a href="?node='
                . $node
                . '&sub=edit&id='
                . $object->id
                . '">'
                . $object->name
                . '</a>'
            );
            unset($object);
        }
        if (is_array($this->data) && count($this->data) < 1) {
            self::redirect('?node=' . $node);
        }
        $this->data[] = array(
            'field' => '<label for="delete">'
            . _('Remove these items?')
            . '</label>',
            'input' => '<button class="btn btn-danger btn-block" type="submit" '
            . 'name="delete" id="delete">'
            . _('Delete')
            . '</button>',
        );
        echo '<!-- Delete Items -->';
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if ($node == 'image') {
            echo '<div id="deleteHint"><b>Hint: Be aware that deleting image(s) this way won\'t actually remove the data files from your server to prevent from accidential data loss. If you want the image data files removed as well you need to use the "Delete" tab found in the settings of each image.</b></div><div>&nbsp;</div>';
        }
        echo '<div id="deleteDiv"></div>';
        echo '<form class="form-horizontal" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '<input type="hidden" name="storagegroup" value="'
            . (
                $this->childClass === 'StorageGroup' ?
                1 :
                0
            )
            . '"/>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually performs the deletion actions
     *
     * @return void
     */
    public function deletemultiAjax()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $user = filter_input(INPUT_POST, 'fogguiuser');
            $pass = filter_input(INPUT_POST, 'fogguipass');
            $validate = self::getClass('User')
                ->passwordValidate(
                    $user,
                    $pass,
                    true
                );
            if (!$validate) {
                echo json_encode(
                    array(
                        'error' => self::$foglang['InvalidLogin'],
                        'title' => _('Unable to Authenticate')
                    )
                );
                exit;
            }
        }
        $remitems = filter_input_array(
            INPUT_POST,
            array(
                'remitems' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $remitems = $remitems['remitems'];
        self::$HookManager->processEvent(
            'MULTI_REMOVE',
            array('removing' => &$remitems)
        );
        if ((int)$_POST['storagegroup'] === 1) {
            $this->childClass = 'StorageGroup';
        }
        self::getClass($this->childClass)
            ->getManager()
            ->destroy(
                array('id' => $remitems)
            );
        echo json_encode(
            array(
                'msg' => _('Successfully deleted'),
                'title' => _('Delete Success')
            )
        );
        exit;
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
            '<a href="?node='
            . $this->node
            . '&sub=deploy&id=${'
            . $this->node
            . '_id}${task_id}"><i class="fa '
            . 'fa-${task_icon} fa-3x"></i><br/>'
            . '${task_name}</a>',
            '${task_desc}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8')
        );
        $taskTypeIterator = function (&$TaskType) use (&$access, &$advanced) {
            if (!in_array($TaskType->access, $access)) {
                return;
            }
            if ($advanced != $TaskType->isAdvanced) {
                return;
            }
            $this->data[] = array(
                $this->node.'_id' => $this->obj->get('id'),
                'task_id' => '&type='.$TaskType->id,
                'task_icon' => $TaskType->icon,
                'task_name' => $TaskType->name,
                'task_desc' => $TaskType->description,
            );
            unset($TaskType);
        };
        Route::listem('tasktype', 'id');
        $items = json_decode(Route::getData());
        $items = $items->tasktypes;
        $advanced = 0;
        $access = array(
            'both',
            $this->node
        );
        foreach ((array)$items as $TaskType) {
            $taskTypeIterator($TaskType);
            unset($TaskType);
        }
        $this->data[] = array(
            $this->node.'_id' => $this->obj->get('id'),
            'task_id' => '#'
            . $this->node
            . '-tasks" class="advanced-tasks-link',
            'task_icon' => 'bars',
            'task_name' => _('Advanced'),
            'task_desc' => _('View advanced tasks for this')
            . ' '
            . $this->node
            . '.'
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
        echo '<!-- Taskings -->';
        echo '<div class="tab-pane fade" id="'
            . $this->node
            . '-tasks">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->childClass;
        echo ' ';
        echo _('Tasks');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '<div class="panel panel-info advanced-tasks">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Advanced Actions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        unset($this->data);
        $advanced = 1;
        foreach ((array)$items as &$TaskType) {
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
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset($TaskTypes);
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
                $ADPass = (
                    $this->obj->get('ADPass') ?
                    '********************************' :
                    ''
                );
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
        if (is_array($OUs) && count($OUs) > 1) {
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
            . '</label>' => '<button class="btn btn-warning btn-block" '
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
                . '</label>',
                _('Domain Password')
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
            . 'btn btn-info btn-block" type="submit" name='
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
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
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
        echo '<!-- Active Directory -->';
        if ($ownElement) {
            echo '<div id="'
                . $node
                . '-active-directory" class="tab-pane fade">';
        }
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title text-center">';
        echo _('Active Directory');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if ($ownElement) {
            echo '<form class="form-horizontal" method="post" action="'
                . $this->formAction
                . '&tab='
                . $node
                . '-active-directory'
                . '">';
        }
        echo '<input type="text" name="fakeusernameremembered" class='
            . '"fakes hidden"/>';
        echo '<input type="password" name="fakepasswordremembered" class='
            . '"fakes hidden"/>';
        $this->render(12);
        if ($ownElement) {
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        if ($ownElement) {
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
        $OUs = array_unique(
            array_filter(
                explode(
                    '|',
                    $ou
                )
            )
        );
        if (count($OUs ?: []) > 1) {
            foreach ($OUs as &$OU) {
                if (false !== strpos($OU, ';')) {
                    $ou = str_replace(';', '', $OU);
                    unset($OU);
                    break;
                }
                unset($OU);
            }
        }
        if (count($OUs ?: []) == 1) {
            $ou = array_shift($OUs);
        }
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
            $msg = filter_input(INPUT_POST, 'msg');
            if ($_SESSION['allow_ajax_kdl']
                && $_SESSION['dest-kernel-file']
                && $_SESSION['tmp-kernel-file']
                && $_SESSION['dl-kernel-file']
            ) {
                if ($msg == 'dl') {
                    $destFilename = $_SESSION['dest-kernel-file'];
                    if (preg_match('/\./', $destFilename)) {
                        throw new Exception(_('Dot in Filename not allowed!'));
                    }
                    $dlUrl = $_SESSION['dl-kernel-file'];
                    if (!(0 === stripos($dlUrl, 'https://fogproject.org/') ||
                        0 === stripos($dlUrl, 'https://github.com/FOGProject/'))
                    ) {
                        throw new Exception(_('Specified download URL not allowed!'));
                    }
                    $fh = fopen(
                        $_SESSION['tmp-kernel-file'],
                        'wb'
                    );
                    if ($fh === false) {
                        throw new Exception(
                            _('Error: Failed to open temp file')
                        );
                    }
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
                    if ($filesize <  1048576) {
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
                } elseif ($msg == 'tftp') {
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
        $stable = '';
        $development = '';
        $urls = array(
            'https://fogproject.org/globalusers',
            'https://api.github.com/repos/fogproject/fogproject/tags',
            'https://raw.githubusercontent.com/FOGProject/fogproject/dev-branch/packages/web/lib/fog/system.class.php'
        );
        $resp = self::$FOGURLRequests->process($urls);
        $data['sites'] = array_shift($resp);
        $tags = json_decode(array_shift($resp));
        $systemclass = array_shift($resp);
        foreach ($tags as $tag) {
            if (preg_match('/^[0-9]\.[0-9]\.[0-9]$/', $tag->name)) {
                $stable = $tag->name;
                break;
            }
        }
        if (preg_match("/FOG_VERSION', '([0-9.RCalphbet-]*)'/", $systemclass, $fogver)) {
            $development = $fogver[1];
        }
        $data['version'] = json_encode(array('stable' => $stable, 'dev' => $development));
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
            $pref = filter_input(INPUT_GET, 'prefix');
            $MAC = self::getClass('MACAddress', $pref);
            $prefix = $MAC->getMACPrefix();
            if (!$MAC->isValid() || !$prefix) {
                throw new Exception(_('Unknown'));
            }
            $OUI = self::getClass('OUIManager')->find(array('prefix'=>$prefix));
            $OUI = array_shift($OUI);
            if (!(($OUI instanceof OUI) && $OUI->isValid())) {
                throw new Exception(_('Not found'));
            }
            $Data = sprintf('%s', $OUI->get('name'));
        } catch (Exception $e) {
            $Data = sprintf('%s', $e->getMessage());
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
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($this->obj instanceof Group) {
            $fieldsg = array(
                '<label for="massDel">'
                . _('Delete hosts within')
                . '</label>' => '<div class="input-group checkbox">'
                . '<input type="checkbox" name="massDelHosts" id="'
                . 'massDel"/>'
                . '</div>'
            );
        } elseif ($this->obj instanceof Image || $this->obj instanceof Snapin) {
            $fieldsi = array(
                '<label for="andFile">'
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
                '<label for="delete">'
                . $this->title
                . '</label>' => '<input type="hidden" name="remitems[]" '
                . 'value="'
                . $this->obj->get('id')
                . '"/>'
                . '<button type="submit" name="delete" id="delete" '
                . 'class="btn btn-danger btn-block">'
                . _('Delete')
                . '</button>'
            )
        );
        $fields = array_filter($fields);
        self::$HookManager->processEvent(
            sprintf(
                '%s_DEL_FIELDS',
                strtoupper($this->node)
            ),
            array(
                'fields' => &$fields,
                $this->childClass => &$this->obj
            )
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            sprintf(
                '%S_DEL',
                strtoupper($this->childClass)
            ),
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                $this->childClass => &$this->obj
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div id="deleteDiv"></div>';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
            self::getHostItem(true);
            $sym_key = filter_input(INPUT_POST, 'sym_key');
            if (!$sym_key) {
                $sym_key = filter_input(INPUT_GET, 'sym_key');
            }
            $token = filter_input(INPUT_POST, 'token');
            if (!$token) {
                $token = filter_input(INPUT_GET, 'token');
            }
            $data = array_values(
                array_map(
                    'bin2hex',
                    self::certDecrypt(
                        array(
                            $sym_key,
                            $token
                        )
                    )
                )
            );
            $key = $data[0];
            $token = $data[1];
            if (self::$Host->get('sec_tok')
                && $token !== self::$Host->get('sec_tok')
            ) {
                self::$Host
                    ->set(
                        'pub_key',
                        null
                    )->save()->load();
                throw new Exception('#!ist');
            }
            if (self::$Host->get('sec_tok')
                && !$key
            ) {
                throw new Exception('#!ihc');
            }
            $expire = self::niceDate(self::$Host->get('sec_time'));
            if (self::niceDate() > $expire
                || !trim(self::$Host->get('pub_key'))
            ) {
                self::$Host
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
            self::$Host
                ->set('pub_key', $key)
                ->save();
            $vals['token'] = self::$Host->get('sec_tok');
            if (self::$json === true) {
                printf(
                    '#!en=%s',
                    self::certEncrypt(
                        json_encode($vals)
                    )
                );
                exit;
            }
            printf(
                '#!en=%s',
                self::certEncrypt(
                    "#!ok\n#token=" . self::$Host->get('sec_tok')
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
        if (isset($_POST['configure'])
            || isset($_GET['configure'])
        ) {
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
        if (isset($_POST['authorize'])
            || isset($_GET['authorize'])
        ) {
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
            self::getHostItem(
                true,
                false,
                false,
                false,
                self::$newService || self::$json
            );
            $hostModules = self::getSubObjectIDs(
                'Module',
                array('id' => self::$Host->get('modules')),
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

            self::$HookManager->processEvent(
                'REQUEST_CLIENT_INFO',
                array(
                     'repFields' => &$array,
                     'Host' => self::$Host
                 )
            );

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
                echo json_encode(
                    array(
                        'error' => self::$foglang['InvalidLogin']
                    )
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
                if (isset($_POST['massDelHosts'])) {
                    self::getClass('HostManager')
                        ->destroy(
                            array('id' => $this->obj->get('hosts'))
                        );
                }
            }
            if (isset($_POST['andFile'])) {
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
            self::resetRequest();
            echo json_encode(
                array(
                    'msg' => sprintf(
                        '%s %s: %s',
                        $this->childClass,
                        _('deleted'),
                        $this->obj->get('name')
                    ),
                    'title' => _('Delete Success')
                )
            );
            exit;
        } catch (Exception $e) {
            self::$HookManager->processEvent(
                sprintf(
                    '%s_DELETE_FAIL',
                    strtoupper($this->node)
                ),
                array($this->childClass => &$this->obj)
            );
            echo json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Delete Fail')
                )
            );
            exit;
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
        $this->title = _('Search')
            . ' '
            . $this->node
            . "s";
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
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
        if (is_array($search) && count($search) > 0) {
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
        $objType = $this->obj instanceof Host ? 'group' : 'host';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '1" class="toggle-checkbox1" id="toggler"/>'
            . '</label>',
            _(ucfirst($objType) . ' Name')
        );
        $this->templates = array(
            '<label for="host-${host_id}">'
            . '<input type="checkbox" name="host[]" class="toggle-'
            . $objType
            . '${check_num}" id="host-${host_id}" '
            . 'value="${host_id}"/>'
            . '</label>',
            '<a href="?node='
            . $objType
            . '&sub=edit&id=${host_id}">${host_name}</a>'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'parser-false filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${host_name}'
            )
        );
        $getType = $objType . 's';
        $getter = $getType . 'notinme';
        Route::names(
            $objType,
            ['id' => $this->obj->get($getter)]
        );
        $namesnotinme = json_decode(
            Route::getData()
        );
        Route::names(
            $objType,
            ['id' => $this->obj->get($getType)]
        );
        $namesinme = json_decode(
            Route::getData()
        );
        foreach ($namesnotinme as &$item) {
            $this->data[] = [
                'host_id' => $item->id,
                'host_name' => $item->name,
                'check_num' => 1
            ];
            unset($item);
        }
        echo '<!-- Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membership">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->childClass
            . ' '
            . _('Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data ?: []) > 0) {
            $notInMe = $meShow = $objType;
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '">';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _("Check here to see what $getType can be added");
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add')
                . ' '
                . ucfirst($getType);
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="update'
                . $getType
                . '" class="control-label col-xs-4">';
            echo _("Add selected $getType");
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addHosts" '
                . 'id="update'
                . $getType
                . '" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates
        );
        $this->headerData = array(
            '<label for="toggler1">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler1"/></label>',
            _(ucfirst($objType) . ' Name')
        );
        $this->templates = array(
            '<label for="hostrm-${host_id}">'
            . '<input type="checkbox" name="hostdel[]" '
            . 'value="${host_id}" class="toggle-action" id="'
            . 'hostrm-${host_id}"/>'
            . '</label>',
            '<a href="?node='
            . $objType
            . '&sub=edit&id=${host_id}">${host_name}</a>'
        );
        foreach ((array)$namesinme as $item) {
            $this->data[] = [
                'host_id' => $item->id,
                'host_name' => $item->name,
                'check_num' => 1
            ];
            unset($item);
        }
        if (count($this->data ?: []) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove ' . ucfirst($getType));
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remhosts" class="control-label col-xs-4">';
            echo _('Remove selected ' . $getType);
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remhosts" class='
                . '"btn btn-danger btn-block" id="remhosts">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'host' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                ),
                'hostdel' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $host = $reqitems['host'];
        $hostdel = $reqitems['hostdel'];
        if (isset($_POST['addHosts'])) {
            $this->obj->addHost($host);
        }
        if (isset($_POST['remhosts'])) {
            $this->obj->removeHost($hostdel);
        }
        if ($this->obj->save()) {
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
        $mac = filter_input(INPUT_POST, 'mac');
        if (!$mac) {
            $mac = filter_input(INPUT_GET, 'mac');
        }
        $macs = self::parseMacList($mac);
        if (is_array($macs) && count($macs) < 1) {
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="exportbtn">'
            . _('Export CSV')
            . '</label>' => '<div id="exportDiv"></div>'
            . '<button name="export" type="submit" class="'
            . 'btn btn-info btn-block" id="exportbtn">'
            . _('Export')
            . '</button>'
        );
        $report = self::getClass('ReportMaker');
        self::arrayRemove('id', $this->databaseFields);
        if ($this->node == 'host') {
            self::arrayRemove('pingstatus', $this->databaseFields);
        }
        Route::listem($this->node);
        $Items = json_decode(
            Route::getData()
        );
        $items = $this->node
            . 's';
        $Items = $Items->$items;
        foreach ((array)$Items as &$Item) {
            if ($this->node == 'host') {
                $macs = implode(
                    '|',
                    $Item->macs
                );
                $report->addCSVCell($macs);
                unset($macs);
            }
            $keys = array_keys((array)$this->databaseFields);
            foreach ((array)$keys as $ind => &$field) {
                $report->addCSVCell($Item->$field);
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
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            strtoupper($this->node) . '_EXPORT',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="export.php?type='
            . $this->node
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Presents the importer elements
     *
     * @return void
     */
    public function import()
    {
        $this->title = _('Import')
            . ' '
            . $this->childClass
            . ' '
            . _('List');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->data[] = array(
            'field' => '<label for="import">'
            . _('Import CSV')
            . '<br/>'
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>',
            'input' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden" name="file" id="import"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp" readonly/>'
            . '</div>'
        );
        $this->data[] = array(
            'field' => '<label for="importbtn">'
            . _('Import CSV?')
            . '</label>',
            'input' => '<button type="submit" name="importbtn" class="'
            . 'btn btn-info btn-block" id="importbtn">'
            . _('Import')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IMPORT_CSV_'
            . strtoupper($this->node),
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" enctype="multipart/form-data">';
        echo _('This page allows you to upload a CSV file into FOG to ease')
            . ' '
            . _('migration or mass import new items')
            . '. '
            . _('It will operate based on the fields the area typically requires')
            . '.';
        echo '<hr/>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
                        self::$Host = $Item;
                        self::getClass('HostManager')
                            ->getHostByMacAddresses($macs);
                        if (self::$Host->isValid()) {
                            throw new Exception(
                                _('One or more macs are associated with a host')
                            );
                        }
                        $primac = array_shift($macs);
                        $index = array_search('productKey', $dbkeys) + 1;
                        $test_encryption = self::aesdecrypt($data[$index]);
                        $test_base64 = base64_decode($data[$index]);
                        $mb_str = mb_detect_encoding(
                            $test_base64,
                            'utf-8',
                            true
                        );
                        $mb_enc = mb_detect_encoding(
                            $test_encryption,
                            'utf-8',
                            true
                        );
                        if ($test_base64 && $mb_str) {
                            $data[$index] = $test_base64;
                        } elseif ($mb_enc) {
                            $data[$index] = $test_encryption;
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
                            ->set('modules', $modules)
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
     * @param string $name     The name of the select item.
     * @param array  $items    The items to generate.
     * @param string $selected The item to select.
     * @param bool   $useidsel Use id of array as selector/value.
     * @param string $addClass Add additional Classes.
     *
     * @return string
     */
    public static function selectForm(
        $name,
        $items = array(),
        $selected = '',
        $useidsel = false,
        $addClass = ''
    ) {
        ob_start();
        printf(
            '<select class="form-control'
            . (
                $addClass ?
                " $addClass" :
                ''
            )
            . '" id="%s" name="%s">'
            . '<option value="">- %s -</option>',
            $name,
            $name,
            _('Please select an option')
        );
        foreach ($items as $id => &$item) {
            printf(
                '<option value="%s"%s>%s</option>',
                (
                    $useidsel ?
                    $id :
                    $item
                ),
                (
                    $useidsel ? (
                        $id == $selected ?
                        ' selected' :
                        ''
                    ) : (
                        $item == $selected ?
                        ' selected' :
                        ''
                    )
                ),
                $item
            );
            unset($item);
        }
        echo '</select>';
        return ob_get_clean();
    }
    /**
     * Displays "add" powermanagement item
     *
     * @return void
     */
    public function newPMDisplay()
    {
        // New data
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->data
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
        );
        $fields = array(
            '<label for="specialCrons">'
            . _('Schedule Power')
            . '</label>' => '<div class="cronOptions input-group">'
            . FOGCron::buildSpecialCron('specialCrons')
            . '</div>'
            . '<div class="col-xs-12">'
            . '<div class="cronInputs">'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronMin" '
            . 'placeholder="min" autocomplete="off" '
            . 'class="form-control scheduleCronMin cronInput"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronHour" '
            . 'placeholder="hour" autocomplete="off" '
            . 'class="form-control scheduleCronHour cronInput"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronDOM" '
            . 'placeholder="dom" autocomplete="off" '
            . 'class="form-control scheduleCronDOM cronInput"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronMonth" '
            . 'placeholder="month" autocomplete="off" '
            . 'class="form-control scheduleCronMonth cronInput"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronDOW" '
            . 'placeholder="dow" autocomplete="off" '
            . 'class="form-control scheduleCronDOW cronInput"/>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>',
            '<label for="scheduleOnDemand">'
            . _('Perform Immediately?')
            . '</label>' => '<input type="checkbox" name="onDemand" id='
            . '"scheduleOnDemand"'
            . (
                isset($_POST['onDemand']) ?
                ' checked' :
                ''
            )
            . '/>',
            '<label for="action">'
            . _('Action')
            . '</label>' => self::getClass(
                'PowerManagementManager'
            )->getActionSelect(
                filter_input(INPUT_POST, 'action'),
                false,
                'action'
            ),
            '<label for="pmsubmit">'
            . _('Create new PM Schedule')
            . '</label>' => '<button type="submit" name="pmsubmit" id='
            . '"pmsubmit" class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('New power management task');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="deploy-container form-horizontal" '
            . 'method="post" action="'
            . $this->formAction
            . '&tab='
            . $this->node
            . '-powermanagement">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Index page is already common, but other pages
     * might want to do similar after minor changes. This allows
     * it to happen.
     *
     * @param bool        $delNeeded If we need to be able to delete items.
     * @param bool|string $storage   If storage, set node or group.
     * @param bool        $actionbox If we need to label as action box.
     *
     * @return void
     */
    public function indexDivDisplay(
        $delNeeded = false,
        $storage = false,
        $actionbox = false
    ) {
        ob_start();
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo $this->render(12);
        echo '</div>';
        echo '</div>';
        if (!$delNeeded) {
            $items = ob_get_clean();
            self::$HookManager->processEvent(
                'INDEX_DIV_DISPLAY_CHANGE',
                array(
                    'items' => &$items,
                    'childClass' => &$this->childClass,
                    'main' => &$this,
                    'delNeeded' => &$delNeeded
                )
            );
            echo $items;
            return;
        }
        if ($actionbox) {
            echo '<div class="action-boxes del hiddeninitially">';
        }
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Delete Selected');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $formAction = $this->formAction;
        $components = parse_url($formAction);
        parse_str($components['query'], $vars);
        $vars['sub'] = 'deletemulti';
        $formAction = '?'.http_build_query($vars);
        echo '<form class="form-horizontal" method="post" action="'
            . $formAction
            . '">';
        echo '<div class="form-group">';
        echo '<label class="control-label col-xs-4" for="del-'
            . $this->node
            . '">';
        echo _('Delete selected');
        echo ' ';
        echo(
            $storage ?
            $this->node . ' ' . $storage :
            $this->node
        );
        echo 's';
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="hidden" name="'
            . $this->node
            . 'IDArray"/>';
        echo '<button type="submit" class='
            . '"btn btn-danger btn-block" id="'
            . 'del-'
            . $this->node
            . '">';
        echo _('Delete');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        if ($actionbox) {
            echo '</div>';
        }
        $items = ob_get_clean();
        self::$HookManager->processEvent(
            'INDEX_DIV_DISPLAY_CHANGE',
            array(
                'items' => &$items,
                'childClass' => &$this->childClass,
                'main' => &$this,
                'delNeeded' => &$delNeeded
            )
        );
        echo $items;
    }
}
