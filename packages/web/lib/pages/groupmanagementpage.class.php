<?php
/**
 * Group management page
 *
 * PHP version 5
 *
 * @category GroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group management page
 *
 * @category GroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManagementPage extends FOGPage
{
    /**
     * The node that uses this class
     *
     * @var string
     */
    public $node = 'group';
    /**
     * Initializes the group management page
     *
     * @param string $name the name to construct under
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Group Management';
        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#group-general" =>
                self::$foglang['General'],
                "$this->linkformat#group-image" =>
                self::$foglang['ImageAssoc'],
                "$this->linkformat#group-tasks" =>
                self::$foglang['BasicTasks'],
                "$this->linkformat#group-active-directory" =>
                self::$foglang['AD'],
                "$this->linkformat#group-printers" =>
                self::$foglang['Printers'],
                "$this->linkformat#group-snapins" =>
                self::$foglang['Snapins'],
                "$this->linkformat#group-service" => sprintf(
                    '%s %s',
                    self::$foglang['Service'],
                    self::$foglang['Settings']
                ),
                "$this->linkformat#group-powermanagement" =>
                self::$foglang['PowerManagement'],
                str_replace(
                    'membership',
                    'inventory',
                    $this->membership
                ) => self::$foglang['Inventory'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Group'] => $this->obj->get('name'),
                self::$foglang['Members'] => $this->obj->getHostCount(),
            );
        }
        self::$HookManager->processEvent(
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
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Name'),
            _('Members'),
            _('Tasking'),
        );
        $down = self::getClass('TaskType', 1);
        $mc = self::getClass('TaskType', 8);
        $this->templates = array(
            '<input type="checkbox" name="group[]" '
            . 'value="${id}" class="toggle-action" id="group-${id}"/>'
            . '<label for="group-${id}"></label>',
            sprintf(
                '<a href="?node=group&sub=edit&%s=${id}" '
                . 'title="Edit">${name}</a>',
                $this->id
            ),
            '${count}',
            sprintf(
                '<a href="?node=group&sub=deploy&type=1&%s=${id}">'
                . '<i class="icon fa fa-'
                . $down->get('icon')
                . '" title="'
                . $down->get('name')
                . '"></i></a> <a href="?node=group&sub=deploy&type=8&%s='
                . '${id}"><i class="icon fa fa-'
                . $mc->get('icon')
                . '" title="'
                . $mc->get('name')
                . '"></i></a> <a href="?node=group&sub=edit&%s='
                . '${id}#group-tasks"><i class="icon fa fa-arrows-alt" '
                . 'title="Goto Basic Tasks"></i></a>',
                $this->id,
                $this->id,
                $this->id,
                $this->id,
                $this->id,
                $this->id
            ),
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false'),
            array(),
            array(
                'width' => 30,
                'class' => 'c'),
            array(
                'width' => 90,
                'class' => 'c filter-false'
            ),
        );
        self::$returnData = function (&$Group) {
            $this->data[] = array(
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
                'description' => $Group->get('description'),
                'count' => $Group->getHostCount(),
            );
            unset($Group);
        };
    }
    /**
     * Create new group
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Group');
        $this->data = array();
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${formField}',
        );
        $fields = array(
            _('Group Name') => sprintf(
                '<input type="text" class="groupname-input" '
                . 'name="name" value="%s"/>',
                $_REQUEST['name']
            ),
            _('Group Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            _('Group Kernel') => sprintf(
                '<input type="text" name="kern" value="%s"/>',
                $_REQUEST['kern']
            ),
            _('Group Kernel Arguments') => sprintf(
                '<input type="text" name="args" name="%s"/>',
                $_REQUEST['args']
            ),
            _('Group Primary Disk') => sprintf(
                '<input type="text" name="dev" name="%s"/>',
                $_REQUEST['dev']
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Add')
            ),
        );
        printf('<form method="post" action="%s">', $this->formAction);
        foreach ($fields as $field => &$formField) {
            $this->data[] = array(
                'field' => $field,
                'formField' => $formField,
            );
            unset($formField, $field);
        }
        unset($fields);
        self::$HookManager->processEvent(
            'GROUP_ADD',
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
     * When submitted to add post this is what's run
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('GROUP_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) {
                throw new Exception('Group Name is required');
            }
            if (self::getClass('GroupManager')->exists($_REQUEST['name'])) {
                throw new Exception('Group Name already exists');
            }
            $Group = self::getClass('Group')
                ->set('name', $_REQUEST['name'])
                ->set('description', $_REQUEST['description'])
                ->set('kernel', $_REQUEST['kern'])
                ->set('kernelArgs', $_REQUEST['args'])
                ->set('kernelDevice', $_REQUEST['dev']);
            if (!$Group->save()) {
                throw new Exception(_('Group create failed'));
            }
            $hook = 'GROUP_ADD_SUCCESS';
            $msg = _('Group added');
        } catch (Exception $e) {
            $hook = 'GROUP_ADD_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager->processEvent(
            $hook,
            array('Group' => &$Group)
        );
        unset($Group);
        self::setMessage($msg);
        self::redirect($this->formAction);
    }
    /**
     * The group edit display method
     *
     * @return void
     */
    public function edit()
    {
        $HostCount = $this->obj->getHostCount();
        $hostids = $this->obj->get('hosts');
        $Host = new Host(@max($hostids));
        $getItems = array(
            'imageID',
            'productKey',
            'printerLevel',
            'useAD',
            'enforce',
            'ADDomain',
            'ADOU',
            'ADUser',
            'ADPass',
            'ADPassLegacy',
            'biosexit',
            'efiexit',
        );
        $tmpStorage = array();
        foreach ($getItems as &$idField) {
            $tmp = self::getClass('HostManager')
                ->distinct(
                    $idField,
                    array('id' => $hostids)
                );
            if ($tmp == 1) {
                $tmpStorage[] = true;
            } else {
                $tmpStorage[] = false;
            }
            unset($idField);
        }
        list(
            $imageIDs,
            $groupKey,
            $printerLevel,
            $aduse,
            $enforcetest,
            $adDomain,
            $adOU,
            $adUser,
            $adPass,
            $adPassLegacy,
            $biosExit,
            $efiExit
        ) = $tmpStorage;
        unset($tmpStorage);
        // Set Field Information
        $printerLevel = (
            $printerLevel ?
            $Host->get('printerLevel') :
            ''
        );
        $imageMatchID = (
            $imageIDs ?
            $Host->get('imageID') :
            ''
        );
        $useAD = (
            $aduse ?
            $Host->get('useAD') :
            ''
        );
        $enforce = (
            $enforcetest ?
            $Host->get('enforce') :
            ''
        );
        $ADDomain = (
            $adDomain ?
            $Host->get('ADDomain') :
            ''
        );
        $ADOU = (
            $adOU ?
            $Host->get('ADOU') :
            ''
        );
        $ADUser = (
            $adUser ?
            $Host->get('ADUser') :
            ''
        );
        $adPass = (
            $adPass ?
            $Host->get('ADPass') :
            ''
        );
        $ADPass = self::encryptpw($Host->get('ADPass'));
        $ADPassLegacy = (
            $adPassLegacy ?
            $Host->get('ADPassLegacy') :
            ''
        );
        $productKey = (
            $groupKey ?
            $Host->get('productKey') :
            ''
        );
        $groupKeyMatch = self::encryptpw($productKey);
        unset($productKey, $groupKey);
        $exitNorm = Service::buildExitSelector(
            'bootTypeExit',
            (
                $biosExit ?
                $Host->get('biosexit') :
                $_REQUEST['bootTypeExit']
            ),
            true
        );
        $exitEfi = Service::buildExitSelector(
            'efiBootTypeExit',
            (
                $efiExit ?
                $Host->get('efiexit') :
                $_REQUEST['efiBootTypeExit']
            ),
            true
        );
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
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
        $fields = array(
            _('Group Name') => sprintf(
                '<input type="text" class="groupname-input" '
                . 'name="name" value="%s"/>',
                $this->obj->get('name')
            ),
            _('Group Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">'
                . '%s</textarea>',
                $this->obj->get('description')
            ),
            _('Group Product Key') => sprintf(
                '<input id="productKey" type="text" name="key" value="%s"/>',
                self::aesdecrypt($groupKeyMatch)
            ),
            _('Group Kernel') => sprintf(
                '<input type="text" name="kern" value="%s"/>',
                $this->obj->get('kernel')
            ),
            _('Group Kernel Arguments') => sprintf(
                '<input type="text" name="args" value="%s"/>',
                $this->obj->get('kernelArgs')
            ),
            _('Group Primary Disk') => sprintf(
                '<input type="text" name="dev" value="%s"/>',
                $this->obj->get('kernelDevice')
            ),
            _('Group Bios Exit Type') => $exitNorm,
            _('Group EFI Exit Type') => $exitEfi,
            '&nbsp;' => sprintf(
                '<input type="submit" name="updategroup" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager->processEvent(
            'GROUP_FIELDS',
            array(
                'fields' => &$fields,
                'Group' => &$this->obj
            )
        );
        printf(
            '<form method="post" action="%s&tab=group-general">'
            . '<div id="tab-container"><!-- General -->'
            . '<div id="group-general"><h2>%s: %s</h2>'
            . '<div id="resetSecDataBox" class="hidden"></div>'
            . '<div class="c"><input type="button" id="resetSecData"/>'
            . '</div><br/>',
            $this->formAction,
            _('Modify Group'),
            $this->obj->get('name')
        );
        foreach ($fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input, $field);
        }
        unset($fields);
        self::$HookManager->processEvent(
            'GROUP_DATA_GEN',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        unset($this->data, $exitNorm, $exitEfi);
        echo '</form></div>';
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($imageMatchID, 'image');
        echo '<!-- Image Association --><div id="group-image">';
        printf(
            '<h2>%s: %s</h2><form method="post" action="%s&tab=group-image">',
            _('Image Association for'),
            $this->obj->get('name'),
            $this->formAction
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
        $this->data[] = array(
            'field' => $imageSelector,
            'input' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Update Images')
            ),
        );
        self::$HookManager->processEvent(
            'GROUP_IMAGE',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        echo '</form></div>';
        unset($this->data);
        self::$HookManager->processEvent(
            'GROUP_GENERAL_EXTRA',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'Group' => &$this->obj,
                'formAction' => &$this->formAction,
                'render' => &$this
            )
        );
        unset($this->data);
        $this->basictasksOptions();
        $this->adFieldsToDisplay(
            $useAD,
            $ADDomain,
            $ADOU,
            $ADUser,
            $ADPass,
            $ADPassLegacy,
            $enforce
        );
        echo '<!-- Printers --><div id="group-printers">';
        printf(
            '<form method="post" action="%s&tab=group-printers"><h2>%s</h2>',
            $this->formAction,
            _('Printer Management Level')
        );
        printf(
            '<p class="l"><span class="icon fa fa-question hand" '
            .' title="%s. %s %s, %s."></span>',
            _('This setting turns off all FOG Printer Management'),
            _('Although there are multiple levels already'),
            _('between host and global settings'),
            _('this is just another to ensure safety')
        );
        printf(
            '<input type="radio" name="level" value="0"%s/>%s<br/>',
            $printerLevel == 0 ? ' checked' : '',
            _('No Printer Management')
        );
        printf(
            '<span class="icon fa fa-question hand" '
            . 'title="%s %s. %s %s. %s %s."></span>',
            _('This setting only adds and removes'),
            _('printers that FOG is aware of'),
            _('Printers that are associated to the host'),
            _('will have those printers added'),
            _('Printers that are defined in FOG but'),
            _('not associated to the host will be removed')
        );
        printf(
            '<input type="radio" name="level" value="1"%s/>%s<br/>',
            $printerLevel == 1 ? ' checked' : '',
            _('FOG Managed Printers')
        );
        printf(
            '<span class="icon fa fa-question hand" '
            . 'title="%s %s. %s %s."></span>',
            _('This setting only allows the host to have'),
            _('printers associated that are assigned through FOG'),
            _('Any printer on the host that is not associated to the'),
            _('host through FOG will be removed')
        );
        printf(
            '<input type="radio" name="level" value="2"%s/>%s<br/>',
            $printerLevel == 2 ? ' checked' : '',
            _('Only FOG Printers')
        );
        echo '</p>';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" '
            . 'class="toggle-checkboxprint" id="toggler1"/>'
            . '<label for="toggler1"></label>',
            '',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="printers[]" value="${printer_id}" '
            . 'class="toggle-print" id="printer-${printer_id}"/>'
            . '<label for="printer-${printer_id}"></label>',
            '<input class="default" type="radio" name="default" '
            . 'id="printer${printer_id}" value="${printer_id}"/>'
            . '<label for="printer${printer_id}" class="icon icon-hand" '
            . 'title="'
            . _('Default Printer Selector')
            . '">&nbsp;</label><input type="hidden" name="printerid[]"/>',
            '<a href="?node=printer&sub=edit&id=${printer_id}">'
            . '${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>50,'class'=>'r'),
        );
        foreach ((array)self::getClass('PrinterManager')
            ->find() as &$Printer
        ) {
            $this->data[] = array(
                'printer_id'=>$Printer->get('id'),
                'printer_name'=>$Printer->get('name'),
                'printer_type'=>$Printer->get('config'),
            );
            unset($Printer);
        }
        $inputupdate = '';
        if (count($this->data) > 0) {
            printf(
                '<h2>%s</h2>',
                _('Printer association(s)')
            );
            $inputupdate = sprintf(
                '<p class="c"><input type="submit" value="%s" '
                . 'name="add"/>&nbsp<input type="submit" value="%s"'
                . ' name="remove"/><br/><br/><input type="submit" '
                . 'value="%s" name="update"/></p>',
                self::$foglang['Add'],
                self::$foglang['Remove'],
                _('Update')
            );
        }
        self::$HookManager->processEvent(
            'GROUP_PRINTER',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'headerData' => &$this->headerData,
                'attributes' => &$this->attributes,
                'inputupdate' => &$inputupdate
            )
        );
        $this->render();
        unset($this->data);
        echo "$inputupdate</form></div>";
        echo '<!-- Snapins --><div id="group-snapins">';
        printf('<h2>%s</h2>', _('Snapins'));
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin" '
            . 'class="toggle-checkboxsnapin" id="toggler2"/>'
            . '<label for="toggler2"></label>',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" value="${snapin_id}" '
            . 'class="toggle-snapin" id="snapin-${snapin_id}"/>'
            . '<label for="snapin-${snapin_id}"></label>',
            sprintf(
                '<a href="?node=snapin&sub=edit&id=${snapin_id}" '
                . 'title="%s">${snapin_name}</a>',
                _('Edit')
            ),
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array(),
            array('width'=>107,'class'=>'r'),
        );
        foreach ((array)self::getClass('SnapinManager')
            ->find() as &$Snapin
        ) {
            $this->data[] = array(
                'snapin_id' => $Snapin->get('id'),
                'snapin_name' => $Snapin->get('name'),
                'snapin_created' => self::formatTime(
                    $Snapin->get('createdTime'),
                    'Y-m-d H:i:s'
                ),
            );
            unset($Snapin);
        }
        self::$HookManager->processEvent(
            'GROUP_SNAPINS',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'headerData' => &$this->headerData,
                'attributes' => &$this->attributes,
                'inputupdate' => &$inputupdate
            )
        );
        if (count($this->data)) {
            printf(
                '<form method="post" action="%s&tab=group-snapins">',
                $this->formAction
            );
            $this->render();
            printf(
                '<p class="c"><input type="submit" value="%s" '
                . 'name="add"/>&nbsp<input type="submit" value="%s" '
                . 'name="remove"/></p></form>',
                self::$foglang['Add'],
                self::$foglang['Remove']
            );
        }
        unset($this->headerData, $this->data);
        echo '</div>';
        echo '<!-- Service Settings --><div id="group-service">';
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${mod_name}',
            '${input}',
            '${span}',
        );
        $this->data[] = array(
            'mod_name' => 'Select/Deselect All',
            'input' => '<input type="checkbox" class="checkboxes" '
            . 'id="checkAll" name="checkAll" value="checkAll"/>'
            . '<label for="checkAll"></label>',
            'span' => '&nbsp;',
        );
        printf(
            '<h2>%s</h2><form method="post" action="%s&tab=group-service">'
            . '<fieldset><legend>%s</legend>',
            _('Service Configuration'),
            $this->formAction,
            _('General')
        );
        $dcnote = sprintf(
            '%s. %s %s. %s %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed with'),
            _('FOG 1.2.0 and earlier'),
            _('This module did not work past Windows XP due'),
            _('to UAC introduced in Vista and up')
        );
        $gfnote = sprintf(
            '%s. %s %s. %s %s %s. %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed'),
            _('with FOG 1.2.0 and earlier'),
            _('This module has been replaced in the new client'),
            _('and the equivalent module for what Green FOG'),
            _('did is now called Power Management'),
            _('This is only here to maintain old client operations')
        );
        $ucnote = sprintf(
            '%s. %s %s. %s %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed with'),
            _('FOG 1.2.0 and earlier'),
            _('This module did not work past Windows XP due'),
            _('to UAC introduced in Vista and up')
        );
        $cunote = sprintf(
            '%s (%s) %s.',
            _('This module is only used'),
            _('with modules and config'),
            _('on the old client')
        );
        $moduleName = self::getGlobalModuleStatus();
        $ModuleOn = array_values(
            self::getSubObjectIDs(
                'ModuleAssociation',
                array(
                    'hostID' => $this->obj->get('hosts')
                ),
                'moduleID',
                false,
                'AND',
                'id',
                false,
                ''
            )
        );
        foreach ((array)self::getClass('ModuleManager')
            ->find() as &$Module
        ) {
            $note = '';
            switch ($Module->get('shortName')) {
            case 'dircleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" title="%s"></i>',
                    $dcnote
                );
                break;
            case 'greenfog':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle fa-1x '
                    . 'hand" title="%s"></i>',
                    $gfnote
                );
                break;
            case 'usercleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle fa-1x '
                    . 'hand" title="%s"></i>',
                    $ucnote
                );
                break;
            case 'clientupdater':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle fa-1x '
                    . 'hand" title="%s"></i>',
                    $cunote
                );
                break;
            default:
                $note = '';
                break;
            }
            $this->data[] = array(
                'input' => sprintf(
                    '<input id="%s" %stype="checkbox" name="modules[]" '
                    . 'value="%s"%s%s/><label for="%s"></label>',
                    $Module->get('shortName'),
                    (
                        $moduleName[$Module->get('shortName')]
                        || (
                            $moduleName[$Module->get('shortName')]
                            && $Module->get('isDefault')
                        ) ?
                        'class="checkboxes" ':
                        ''
                    ),
                    $Module->get('id'),
                    (
                        count(
                            array_keys(
                                $ModuleOn,
                                $Module->get('id')
                            )
                        ) == $HostCount ?
                        ' checked' :
                        ''
                    ),
                    (
                        !$moduleName[$Module->get('shortName')] ?
                        ' disabled' :
                        ''
                    ),
                    $Module->get('shortName')
                ),
                'span' => sprintf(
                    '%s<span class="icon fa fa-question fa-1x hand" '
                    . 'title="%s"></span>',
                    $note,
                    str_replace('"', '\"', $Module->get('description'))
                ),
                'mod_name' => $Module->get('name'),
            );
            unset($Module);
        }
        unset($moduleName, $ModuleOn);
        $this->data[] = array(
            'mod_name' => '',
            'input' => '',
            'span' => sprintf(
                '<input type="submit" name="updatestatus" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager->processEvent(
            'GROUP_MODULES',
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
            '</fieldset><fieldset><legend>%s</legend>',
            _('Group Screen Resolution')
        );
        $this->attributes = array(
            array('class'=>'l','style'=>'padding-right: 25px'),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${span}',
        );
        $find = array(
            'name' => array(
                'FOG_CLIENT_DISPLAYMANAGER_X',
                'FOG_CLIENT_DISPLAYMANAGER_Y',
                'FOG_CLIENT_DISPLAYMANAGER_R',
            )
        );
        foreach ((array)self::getClass('ServiceManager')
            ->find(
                $find,
                'OR',
                'id'
            ) as $Service
        ) {
            switch ($Service->get('name')) {
            case 'FOG_CLIENT_DISPLAYMANAGER_X':
                $name = 'x';
                $field = _('Screen Width (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_Y':
                $name = 'y';
                $field = _('Screen Height (in pixels)');
                break;
            case 'FOG_CLIENT_DISPLAYMANAGER_R':
                $name = 'r';
                $field = _('Screen Refresh Rate (in Hz)');
                break;
            }
            $this->data[] = array(
                'input' => sprintf(
                    '<input type="text" name="%s" value="%s"/>',
                    $name,
                    $Service->get('value')
                ),
                'span' => sprintf(
                    '<span class="icon fa fa-question fa-1x hand" title="%s">'
                    . '</span>',
                    $Service->get('description')
                ),
                'field' => $field,
            );
            unset($name, $field, $Service);
        }
        $this->data[] = array(
            'field'=>'',
            'input'=>'',
            'span'=>sprintf(
                '<input type="submit" name="updatedisplay" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager->processEvent(
            'GROUP_DISPLAY',
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
            '</fieldset><fieldset><legend>%s</legend>',
            _('Auto Log Out Settings')
        );
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${desc}',
        );
        $Service = self::getClass('Service')
            ->set('name', 'FOG_CLIENT_AUTOLOGOFF_MIN')
            ->load('name');
        $this->data[] = array(
            'field' => _('Auto Log Out Time (in minutes)'),
            'input' => sprintf(
                '<input type="text" name="tme" value="%s"/>',
                $Service->get('value')
            ),
            'desc' => sprintf(
                '<span class="icon fa fa-question fa-1x hand" '
                . 'title="%s"></span>',
                $Service->get('description')
            ),
        );
        unset($Service);
        $this->data[] = array(
            'field' => '',
            'input' => '',
            'desc' => sprintf(
                '<input type="submit" name="updatealo" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager->processEvent(
            'GROUP_ALO',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        unset($this->data);
        echo '</fieldset></form></div>';
        echo '<!-- Power Management Items --><div id="group-powermanagement">'
            . '<div id="delAllPMBox"></div><div class="c"><input '
            . 'type="button" id="delAllPM"/></div><br/>';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Schedule Power') => sprintf(
                '<p id="cronOptions">'
                . '<input type="text" name="scheduleCronMin" '
                . 'id="scheduleCronMin" placeholder="min" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronHour" '
                . 'id="scheduleCronHour" placeholder="hour" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronDOM" '
                . 'id="scheduleCronDOM" placeholder="dom" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronMonth" '
                . 'id="scheduleCronMonth" placeholder="month" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronDOW" '
                . 'id="scheduleCronDOW" placeholder="dow" '
                . 'autocomplete="off" value="%s"/></p>',
                $_REQUEST['scheduleCronMin'],
                $_REQUEST['scheduleCronHour'],
                $_REQUEST['scheduleCronDOM'],
                $_REQUEST['scheduleCronMonth'],
                $_REQUEST['scheduleCronDOW']
            ),
            _('Perform Immediately?') => sprintf(
                '<input type="checkbox" name="onDemand" '
                . 'id="scheduleOnDemand"%s/><label for="'
                . 'scheduleOnDemand"></label>',
                !is_array($_REQUEST['onDemand'])
                && isset($_REQUEST['onDemand']) ?
                ' checked' :
                ''
            ),
            _('Action') => self::getClass('PowerManagementManager')
            ->getActionSelect($_REQUEST['action'])
        );
        foreach ($fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input, $field);
        }
        printf(
            '<form method="post" action="%s&tab=group-powermanagement" '
            . 'class="deploy-container">',
            $this->formAction
        );
        $this->render();
        printf(
            '<center><input type="submit" name="pmsubmit" value="%s"/>'
            . '</center></form></div>',
            _('Add Option')
        );
        unset(
            $this->headerData,
            $this->templates,
            $this->data,
            $this->attributes
        );
        echo '</div>';
        unset(
            $imageID,
            $imageMatchID,
            $groupKey,
            $groupKeyMatch,
            $aduse,
            $adDomain,
            $adOU,
            $adUser,
            $adPass,
            $adPassLegacy,
            $useAD,
            $ADOU,
            $ADDomain,
            $ADUser,
            $adPass,
            $ADPass,
            $ADPassLegacy,
            $biosExit,
            $efiExit,
            $exitNorm,
            $exitEfi
        );
    }
    /**
     * Display inventory page, separated as groups can contain
     * a lot of information
     *
     * @return void
     */
    public function inventory()
    {
        $this->title = sprintf(
            '%s %s',
            _('Group'),
            self::$foglang['Inventory']
        );
        printf(
            $this->reportString,
            sprintf(
                'Group_%s_InventoryReport',
                $this->obj->get('name')
            ),
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            sprintf(
                'Group_%s_InventoryReport',
                $this->obj->get('name')
            ),
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->ReportMaker = self::getClass('ReportMaker');
        foreach (self::$inventoryCsvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet, $csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        foreach ((array)self::getClass('HostManager')
            ->find(
                array('id' => $this->obj->get('hosts'))
            ) as &$Host
        ) {
            if (!$Host->get('inventory')->isValid()) {
                continue;
            }
            $Image = $Host->getImage();
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'memory' => $Host->get('inventory')->getMem(),
                'sysprod' => $Host->get('inventory')->get('sysproduct'),
                'sysser' => $Host->get('inventory')->get('sysserial'),
            );
            foreach (self::$inventoryCsvHead as $csvHead => &$classGet) {
                switch ($csvHead) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell(
                        $Host->get('id')
                    );
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell(
                        $Host->get('name')
                    );
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell(
                        $Host->get('mac')
                    );
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell(
                        $Host->get('description')
                    );
                    break;
                case _('Host Memory'):
                    $this->ReportMaker->addCSVCell(
                        $Host->get('inventory')->getMem()
                    );
                    break;
                default:
                    $this->ReportMaker->addCSVCell(
                        $Host->get('inventory')->get($classGet)
                    );
                    break;
                }
                unset($classGet, $csvHead);
            }
            $this->ReportMaker->endCSVLine();
            unset($Host, $index);
        }
        unset($Hosts);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
        echo '</div>';
    }
    /**
     * Submit the edit function.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'GROUP_EDIT_POST',
                array('Group' => &$this->obj)
            );
        try {
            $hostids = $this->obj->get('hosts');
            switch ($_REQUEST['tab']) {
            case 'group-general':
                if (empty($_REQUEST['name'])) {
                    throw new Exception(_('Group Name is required'));
                }
                $this->obj
                    ->set('name', $_REQUEST['name'])
                    ->set('description', $_REQUEST['description'])
                    ->set('kernel', $_REQUEST['kern'])
                    ->set('kernelArgs', $_REQUEST['args'])
                    ->set('kernelDevice', $_REQUEST['dev']);
                $productKey = preg_replace(
                    '/([\w+]{5})/',
                    '$1-',
                    str_replace(
                        '-',
                        '',
                        strtoupper(
                            trim(
                                $_REQUEST['key']
                            )
                        )
                    )
                );
                $productKey = substr($productKey, 0, 29);
                self::getClass('HostManager')
                    ->update(
                        array(
                            'id' => $hostids
                        ),
                        '',
                        array(
                            'kernel' => $_REQUEST['kern'],
                            'kernelArgs' => $_REQUEST['args'],
                            'kernelDevice' => $_REQUEST['dev'],
                            'efiexit' => $_REQUEST['efiBootTypeExit'],
                            'biosexit' => $_REQUEST['bootTypeExit'],
                            'productKey' => self::encryptpw(
                                trim(
                                    $_REQUEST['key']
                                )
                            )
                        )
                    );
                break;
            case 'group-image':
                $this->obj->addImage($_REQUEST['image']);
                break;
            case 'group-active-directory':
                $useAD = isset($_REQUEST['domain']);
                $domain = $_REQUEST['domainname'];
                $ou = $_REQUEST['ou'];
                $user = $_REQUEST['domainuser'];
                $pass = $_REQUEST['domainpassword'];
                $legacy = $_REQUEST['domainpasswordlegacy'];
                $enforce = isset($_REQUEST['enforcesel']);
                $this->obj->setAD(
                    $useAD,
                    $domain,
                    $ou,
                    $user,
                    $pass,
                    $legacy,
                    $enforce
                );
                break;
            case 'group-printers':
                if (isset($_REQUEST['add'])) {
                    $this->obj->addPrinter(
                        $_REQUEST['printers'],
                        array(),
                        $_REQUEST['level']
                    );
                    $default = $_REQUEST['default'];
                    $printrs = $_REQUEST['printers'];
                    if (in_array($default, (array)$printrs)) {
                        $this->obj->updateDefault($default);
                    }
                }
                if (isset($_REQUEST['remove'])) {
                    $this->obj->addPrinter(
                        array(),
                        $_REQUEST['printers'],
                        $_REQUEST['level']
                    );
                }
                if (isset($_REQUEST['update'])) {
                    $this->obj->addPrinter(
                        array(),
                        array(),
                        $_REQUEST['level']
                    );
                    $this->obj->addPrinter(
                        $_REQUEST['default'],
                        array(),
                        $_REQUEST['level']
                    );
                    $this->obj->updateDefault($_REQUEST['default']);
                }
                break;
            case 'group-snapins':
                if (isset($_REQUEST['add'])) {
                    $this->obj->addSnapin($_REQUEST['snapin']);
                }
                if (isset($_REQUEST['remove'])) {
                    $this->obj->removeSnapin($_REQUEST['snapin']);
                }
                break;
            case 'group-service':
                list(
                    $time,
                    $r,
                    $x,
                    $y
                ) = self::getSubObjectIDs(
                    'Service',
                    array(
                        'name' => array(
                            'FOG_CLIENT_AUTOLOGOFF_MIN',
                            'FOG_CLIENT_DISPLAYMANAGER_R',
                            'FOG_CLIENT_DISPLAYMANAGER_X',
                            'FOG_CLIENT_DISPLAYMANAGER_Y'
                        )
                    ),
                    'value'
                );
                $x = (
                    is_numeric($_REQUEST['x']) ?
                    $_REQUEST['x'] :
                    $x
                );
                $y = (
                    is_numeric($_REQUEST['y']) ?
                    $_REQUEST['y'] :
                    $y
                );
                $r = (
                    is_numeric($_REQUEST['r']) ?
                    $_REQUEST['r'] :
                    $r
                );
                $time = (
                    is_numeric($_REQUEST['tme']) ?
                    $_REQUEST['tme'] :
                    $time
                );
                $mods = self::getSubObjectIDs('Module');
                $modOn = array_intersect(
                    (array)$mods,
                    (array)$_REQUEST['modules']
                );
                $modOff = array_diff(
                    (array)$mods,
                    (array)$modOn
                );
                $this->obj
                    ->addModule($modOn)
                    ->removeModule($modOff)
                    ->setDisp($x, $y, $r)
                    ->setAlo($time);
                break;
            case 'group-powermanagement':
                $min = $_REQUEST['scheduleCronMin'];
                $hour = $_REQUEST['scheduleCronHour'];
                $dom = $_REQUEST['scheduleCronDOM'];
                $month = $_REQUEST['scheduleCronMonth'];
                $dow = $_REQUEST['scheduleCronDOW'];
                $onDemand = (string)intval(isset($_REQUEST['onDemand']));
                $action = $_REQUEST['action'];
                if (!$action) {
                    throw new Exception(_('You must select an action to perform'));
                }
                $items = array();
                if (isset($_REQUEST['pmsubmit'])) {
                    if ($onDemand && $action === 'wol') {
                        $this->obj->wakeOnLAN();
                        break;
                    }
                    $hostIDs = (array)$this->obj->get('hosts');
                    $items = array();
                    foreach ((array)$hostIDs as &$hostID) {
                        $items[] = array(
                            $hostID,
                            $min,
                            $hour,
                            $dom,
                            $month,
                            $dow,
                            $onDemand,
                            $action
                        );
                        unset($hostID);
                    }
                    $fields = array(
                        'hostID',
                        'min',
                        'hour',
                        'dom',
                        'month',
                        'dow',
                        'onDemand',
                        'action'
                    );
                    if (count($items) > 0) {
                        self::getClass('PowerManagementManager')
                            ->insertBatch($fields, $items);
                    }
                }
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Database update failed'));
            }
            $hook = 'GROUP_EDIT_SUCCESS';
            $msg = _('Group information updated');
        } catch (Exception $e) {
            $hook = 'GROUP_EDIT_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Group' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
    }
    /**
     * Delete the hosts with the delete group.
     *
     * @return void
     */
    public function deletehosts()
    {
        $this->title = _('Delete Hosts');
        unset($this->data);
        $this->headerData = array(
            _('Host Name'),
            _('Last Deployed'),
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '<small>${host_deployed}</small>',
        );
        $hostids = $this->obj->get('hosts');
        foreach ((array)self::getClass('HostManager')
            ->find(
                array('id' => $hostids)
            ) as &$Host
        ) {
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'host_deployed' => self::formatTime(
                    $Host->get('deployed'),
                    'Y-m-d H:i:s'
                ),
            );
            unset($Host);
        }
        printf(
            '<p>%s</p>',
            _('Confirm you really want to delete the following hosts')
        );
        printf(
            '<form method="post" action="?node=group&sub=delete&id=%s" class="c">',
            $this->obj->get('id')
        );
        self::$HookManager
            ->processEvent(
                'GROUP_DELETE_HOST_FORM',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        printf(
            '<input type="submit" name="delHostConfirm" value="%s" /></form>',
            _('Delete listed hosts')
        );
    }
}
