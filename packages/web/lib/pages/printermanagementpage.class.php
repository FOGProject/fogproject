<?php
/**
 * Printer management page.
 *
 * PHP version 5
 *
 * @category PrinterManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Printer management page.
 *
 * @category PrinterManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterManagementPage extends FOGPage
{
    /**
     * The node this page operates from.
     *
     * @var string
     */
    public $node = 'printer';
    /**
     * The printer config type.
     *
     * @var string
     */
    private $_config;
    /**
     * Initializes the class.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        global $id;
        $this->name = 'Printer Management';
        parent::__construct($this->name);
        if ($id) {
            $this->_config = _('TCP/IP');
            if (false === stripos($this->obj->get('config'), 'local')) {
                $this->_config = $this->obj->get('config');
            }
            $this->subMenu = array(
                "$this->linkformat#$this->node-gen" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Printer'] => $this->obj->get('name'),
                self::$foglang['Type'] => $this->_config,
            );
        }
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes,
                    'object' => &$this->obj,
                    'linkformat' => &$this->linkformat,
                    'delformat' => &$this->delformat,
                    'membership' => &$this->membership
                )
            );
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Printer Name'),
            _('Printer Type'),
            _('Model'),
            _('Port'),
            _('File'),
            _('IP'),
            _('Config File'),
        );
        $this->templates = array(
            '<span class="icon fa fa-question hand" title="${desc}"></span>',
            '<input type="checkbox" name="printer[]" value='
            . '"${id}" class="toggle-action" id="printer-${id}"/>'
            . '<label for="printer-${id}"></label>',
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${config}',
            '${model}',
            '${port}',
            '${file}',
            '${ip}',
            '${configFile}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array(
                'class' => 'filter-false'
            ),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        self::$returnData = function (&$Printer) {
            $config = _('TCP/IP');
            if (false === stripos($Printer->get('config'), 'local')) {
                $config = $Printer->get('config');
            }
            $this->data[] = array(
                'id' => $Printer->get('id'),
                'name' => $Printer->get('name'),
                'config' => $config,
                'model' => $Printer->get('model'),
                'port' => $Printer->get('port'),
                'file' => $Printer->get('file'),
                'ip' => $Printer->get('ip'),
                'configFile' => $Printer->get('configFile'),
                'desc' => $Printer->get('description'),
            );
            unset($Printer);
        };
    }
    /**
     * Gets the printer information.
     *
     * @return void
     */
    public function getPrinterInfo()
    {
        echo json_encode(
            array(
                'file' => $this->obj->get('file'),
                'port' => $this->obj->get('port'),
                'model' => $this->obj->get('model'),
                'ip' => $this->obj->get('ip'),
                'config' => strtolower($this->obj->get('config')),
                'configFile' => $this->obj->get('configFile'),
            )
        );
        exit;
    }
    /**
     * Forms for creating a new printer.
     *
     * @return void
     */
    public function add()
    {
        $this->title = 'New Printer';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!isset($_REQUEST['printertype']) || empty($_REQUEST['printertype'])) {
            $_REQUEST['printertype'] = 'Local';
        }
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        foreach ((array)$printerTypes as $short => &$long) {
            printf(
                '<option value="%s"%s>%s</option>',
                $short,
                (
                    $_REQUEST['printertype'] === $short ?
                    ' selected' :
                    ''
                ),
                $long
            );
            unset($short, $long);
        }
        $optionPrinter = ob_get_clean();
        printf('<form method="post" action="%s">', $this->formAction);
        echo '<div id="printer-copy">';
        $fields = array(
            sprintf(
                '%s',
                _('Copy from existing printer')
            ) => sprintf(
                '%s',
                self::getClass('PrinterManager')->buildSelectBox(
                    $this->obj->get('id')
                )
            ),
            _('Printer Type') => sprintf(
                '<select name="printertype">%s</select>',
                $optionPrinter
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            sprintf(
                '%s*',
                _('Printer Alias')
            ) => sprintf(
                '<input class="printername-input" type='
                . '"text" name="alias" value="%s"/>',
                $_REQUEST['alias']
            ),
            '&nbsp;' => 'e.g. \\\\printerserver\\printername',
        );
        echo '<div id="network" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        unset($fields['&nbsp;']);
        $fields = self::fastmerge(
            $fields,
            array(
                sprintf(
                    '%s*',
                    _('Printer Port')
                ) => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $_REQUEST['port']
                ),
            )
        );
        echo '<div id="iprint" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description') => sprintf(
                '<textarea class="printerdescription-input" name='
                . '"description" rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            sprintf(
                '%s*',
                _('Printer Alias')
            ) => sprintf(
                '<input class="printername-input" type='
                . '"text" name="alias" value="%s"/>',
                $_REQUEST['alias']
            ),
            sprintf(
                '%s*',
                _('Printer INF File')
            ) => sprintf(
                '<input class="printerinf-input" type='
                . '"text" name="inf" value="%s"/>',
                $_REQUEST['inf']
            ),
            sprintf(
                '%s*',
                _('Printer IP')
            ) => sprintf(
                '<input class="printerip-input" type='
                . '"text" name="ip" value="%s"/>',
                $_REQUEST['ip']
            ),
        );
        echo '<div id="cups" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = self::fastmerge(
            $fields,
            array(
                _('Printer Port') => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $_REQUEST['port']
                ),
                _('Printer Model') => sprintf(
                    '<input class="printermodel-input" type='
                    . '"text" name="model" value="%s"/>',
                    $_REQUEST['model']
                ),
                _('Printer Config File') => sprintf(
                    '<input class="printerconfigFile-input" type='
                    . '"text" name="configFile" value="%s"/>',
                    $_REQUEST['configFile']
                ),
            )
        );
        echo '<div id="local" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            '&nbsp;' => sprintf(
                '<input class="c" name="addprinter" type='
                . '"submit" value="%s"/>',
                _('Add Printer')
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        $this->render();
        echo '</form>';
        unset($this->data);
        self::$HookManager
            ->processEvent(
                'PRINTER_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
    }
    /**
     * Actually create the item.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('PRINTER_ADD_POST');
        try {
            $_REQUEST['alias'] = trim($_REQUEST['alias']);
            $_REQUEST['port'] = trim($_REQUEST['port']);
            $_REQUEST['inf'] = trim($_REQUEST['inf']);
            $_REQUEST['model'] = trim($_REQUEST['model']);
            $_REQUEST['ip'] = trim($_REQUEST['ip']);
            $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
            $_REQUEST['description'] = trim($_REQUEST['description']);
            $_REQUEST['printertype'] = trim(strtolower($_REQUEST['printertype']));
            if (empty($_REQUEST['alias'])) {
                throw new Exception(_('A name must be set'));
            }
            if (self::getClass('PrinterManager')->exists($_REQUEST['alias'])) {
                throw new Exception(_('Printer name already exists'));
            }
            switch ($_REQUEST['printertype']) {
            case 'local':
                $printertype = 'Local';
                break;
            case 'cups':
                $printertype = 'Cups';
                break;
            case 'iprint':
                $printertype = 'iPrint';
                break;
            case 'network':
                $printertype = 'Network';
                break;
            }
            $Printer = self::getClass('Printer')
                ->set('description', $_REQUEST['description'])
                ->set('name', $_REQUEST['alias'])
                ->set('config', $printertype)
                ->set('model', $_REQUEST['model'])
                ->set('port', $_REQUEST['port'])
                ->set('file', $_REQUEST['inf'])
                ->set('configFile', $_REQUEST['configFile'])
                ->set('ip', $_REQUEST['ip']);
            if (!$Printer->save()) {
                throw new Exception(_('Printer create/updated failed!'));
            }
            $hook = 'PRINTER_ADD_SUCCESS';
            $msg = json_encode(
                array('msg' => _('Printer added'))
            );
        } catch (Exception $e) {
            $hook = 'PRINTER_ADD_FAIL';
            $msg = json_encode(
                array('error' => $e->getMessage())
            );
        }
        self::$HookManager->processEvent(
            $hook,
            array('Printer' => &$Printer)
        );
        unset($Printer);
        echo $msg;
        exit;
    }
    /**
     * Edit printer object.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        echo '<div id="tab-container">';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        echo '<!-- General --><div id="printer-gen">';
        if (!isset($_REQUEST['printertype']) || empty($_REQUEST['printertype'])) {
            $_REQUEST['printertype'] = $this->obj->get('config');
        }
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        foreach ((array)$printerTypes as $short => &$long) {
            printf(
                '<option value="%s"%s>%s</option>',
                $short,
                (
                    $_REQUEST['printertype'] === $short ?
                    ' selected' :
                    ''
                ),
                $long
            );
            unset($short, $long);
        }
        $optionPrinter = ob_get_clean();
        printf(
            '<form method="post" action="%s&tab=printer-gen"><br/>',
            $this->formAction
        );
        echo '<div id="printer-copy">';
        $fields = array(
            sprintf(
                '%s',
                _('Copy from existing printer')
            ) => sprintf(
                '%s',
                self::getClass('PrinterManager')->buildSelectBox(
                    $this->obj->get('id')
                )
            ),
            _('Printer Type') => sprintf(
                '<select name="printertype">%s</select>',
                $optionPrinter
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            sprintf(
                '%s*',
                _('Printer Alias')
            ) => sprintf(
                '<input class="printername-input" type='
                . '"text" name="alias" value="%s"/>',
                $this->obj->get('name')
            ),
            '&nbsp;'=>'e.g. \\\\printerserver\\printername',
        );
        echo '<div id="network" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        unset($fields['&nbsp;']);
        $fields = self::fastmerge(
            $fields,
            array(
                sprintf(
                    '%s*',
                    _('Printer Port')
                ) => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $this->obj->get('port')
                ),
            )
        );
        echo '<div id="iprint" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            sprintf(
                '%s*',
                _('Printer Alias')
            ) => sprintf(
                '<input class="printername-input" type='
                . '"text" name="alias" value="%s"/>',
                $this->obj->get('name')
            ),
            sprintf(
                '%s*',
                _('Printer INF File')
            ) => sprintf(
                '<input class="printerinf-input" type='
                . '"text" name="inf" value="%s"/>',
                $this->obj->get('file')
            ),
            sprintf(
                '%s*',
                _('Printer IP')
            ) => sprintf(
                '<input class="printerip-input" type='
                . '"text" name="ip" value="%s"/>',
                $this->obj->get('ip')
            ),
        );
        echo '<div id="cups" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = self::fastmerge(
            $fields,
            array(
                _('Printer Port') => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $this->obj->get('port')
                ),
                _('Printer Model') => sprintf(
                    '<input class="printermodel-input" type='
                    . '"text" name="model" value="%s"/>',
                    $this->obj->get('model')
                ),
                _('Printer Config File') => sprintf(
                    '<input class="printerconfigFile-input" type='
                    . '"text" name="configFile" value="%s"/>',
                    $this->obj->get('configFile')
                ),
            )
        );
        echo '<div id="local" class="hidden">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            '&nbsp;' => sprintf(
                '<input class="c" name="updateprinter" type="submit" value="%s"/>',
                _('Update Printer')
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        self::$HookManager
            ->processEvent(
                'PRINTER_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        echo '</form></div></div>';
        unset($this->data);
    }
    /**
     * Save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'PRINTER_EDIT_POST',
                array('Printer' => &$this->obj)
            );
        try {
            switch ($_REQUEST['tab']) {
            case 'printer-type':
                self::setMessage(
                    sprintf(
                        '%s: %s',
                        _('Printer type changed to'),
                        $_REQUEST['printertype']
                    )
                );
                self::redirect($this->formAction);
                break;
            case 'printer-gen':
                $_REQUEST['alias'] = trim($_REQUEST['alias']);
                $_REQUEST['port'] = trim($_REQUEST['port']);
                $_REQUEST['inf'] = trim($_REQUEST['inf']);
                $_REQUEST['model'] = trim($_REQUEST['model']);
                $_REQUEST['ip'] = trim($_REQUEST['ip']);
                $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
                $_REQUEST['description'] = trim($_REQUEST['description']);
                $_REQUEST['printertype'] = trim(
                    strtolower($_REQUEST['printertype'])
                );
                if (empty($_REQUEST['alias'])) {
                    throw new Exception(_('A name must be set'));
                }
                switch ($_REQUEST['printertype']) {
                case 'local':
                    $printertype = 'Local';
                    break;
                case 'cups':
                    $printertype = 'Cups';
                    break;
                case 'iprint':
                    $printertype = 'iPrint';
                    break;
                case 'network':
                    $printertype = 'Network';
                    break;
                }
                if ($this->obj->get('name') != $_REQUEST['alias']
                    && $this->obj->getManager()->exists($_REQUEST['alias'])
                ) {
                    throw new Exception(_('Printer name already exists'));
                }
                $this->obj
                    ->set('description', $_REQUEST['description'])
                    ->set('name', $_REQUEST['alias'])
                    ->set('config', $printertype)
                    ->set('model', $_REQUEST['model'])
                    ->set('port', $_REQUEST['port'])
                    ->set('file', $_REQUEST['inf'])
                    ->set('configFile', $_REQUEST['configFile'])
                    ->set('ip', $_REQUEST['ip']);
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Printer update failed!'));
            }
            $hook = 'PRINTER_UPDATE_SUCCESS';
            $msg = json_encode(
                array('msg' => _('Printer updated!'))
            );
        } catch (Exception $e) {
            $hook = 'PRINTER_UPDATE_FAIL';
            $msg = json_encode(
                array('error' => $e->getMessage())
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Printer' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
