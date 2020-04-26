<?php
/**
 * Snapin management page
 *
 * PHP version 5
 *
 * @category SnapinManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin management page
 *
 * @category SnapinManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinManagementPage extends FOGPage
{
    /**
     * Arg types for snapin template
     *
     * @var array
     */
    private static $_argTypes = array(
        'MSI' => array('msiexec.exe','/i','/quiet'),
        'Batch Script' => array('cmd.exe','/c'),
        'Bash Script' => array('/bin/bash'),
        'VB Script' => array('cscript.exe'),
        'Powershell (default)' => array(
            'powershell.exe',
            '-ExecutionPolicy Bypass -NoProfile -File'
        ),
        'Powershell x64' => array(
            '&quot;%SYSTEMROOT%\\sysnative\\windowspowershell'
            . '\\v1.0\\powershell.exe',
            '-ExecutionPolicy Bypass -NoProfile -File'
        ),
        'Mono' => array('mono'),
    );
    /**
     * Template for non-pack.
     *
     * @var string
     */
    private static $_template1;
    /**
     * Template for pack.
     *
     * @var string
     */
    private static $_template2;
    /**
     * The node this page operates off of.
     *
     * @var string
     */
    public $node = 'snapin';
    /**
     * Initializes the snapin page class
     *
     * @param string $name the name to pass
     *
     * @return void
     */
    public function __construct($name = '')
    {
        /**
         * The real name not using our name passer.
         */
        $this->name = 'Snapin Management';
        /**
         * Pull in the FOG Page class items.
         */
        parent::__construct($name);
        /**
         * Generate our snapin arg templates.
         */
        /**
         * Start a new buffer (last one ended anyway)
         * to create our template non-pack.
         */
        ob_start();
        printf(
            '<select name="argTypes" id="argTypes">'
            . '<option value="">- %s -</option>',
            _('Please select an option')
        );
        foreach (self::$_argTypes as $type => &$cmd) {
            printf(
                '<option value="%s" rwargs="%s" args="%s">%s</option>',
                $cmd[0],
                $cmd[1],
                $cmd[2],
                $type
            );
            unset($cmd);
        }
        echo '</select>';
        self::$_template1 = ob_get_clean();
        /**
         * Store the pack based template.
         */
        self::$_template2 = $this->_maker();
        /**
         * Get our nicer names.
         */
        global $id;
        global $sub;
        if ($id) {
            /**
             * The other sub menu items.
             */
            $this->subMenu = array(
                "$this->linkformat#snap-gen" => self::$foglang['General'],
                "$this->linkformat#snap-storage" => sprintf(
                    '%s %s',
                    self::$foglang['Storage'],
                    self::$foglang['Group']
                ),
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            /**
             * The notes for this item.
             */
            $this->notes = array(
                self::$foglang['Snapin'] => $this->obj->get('name'),
                self::$foglang['File'] => $this->obj->get('file'),
                _('Filesize') => self::formatByteSize($this->obj->get('size')),
            );
        }
        /**
         * Allow custom hooks/changes to: Submenu data via.
         *
         * Menu, submenu, id, notes, the main object,
         * linkformat, delformat, and membership information.
         */
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
        /**
         * The header data for list/search.
         */
        $this->headerData = array(
            '',
            '',
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '</label>',
            _('Snapin Name'),
            _('Is Pack'),
            _('Storage Group'),
        );
        /**
         * The template for the list/search elements.
         */
        $this->templates = array(
            '${protected}',
            '${enabled}',
            '<label for="toggler1">'
            . '<input type="checkbox" name="snapin[]" '
            . 'value="${id}" class="toggle-action" id="'
            . 'toggler1"/></label>',
            '<a href="?node='
            . $this->node
            . '&sub=edit&id=${id}" '
            . 'data-toggle="tooltip" data-placement="right" '
            . 'title="'
            . _('Edit')
            . ': ${name}">${name} - ${id}</a>',
            '${packtype}',
            '${storageGroup}',
        );
        /**
         * The attributes for the table items.
         */
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 5
            ),
            array(
                'class' => 'filter-false',
                'width' => 5
            ),
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(
                'width' => 50
            ),
            array()
        );
        /**
         * Lamda function to return data either by list or search.
         *
         * @param object $Snapin the object to use.
         *
         * @return void
         */
        self::$returnData = function (&$Snapin) {
            /**
             * Stores the items in a nicer name.
             */
            /**
             * The id.
             */
            $id = $Snapin->id;
            /**
             * The name.
             */
            $name = $Snapin->name;
            /**
             * The description.
             */
            $description = $Snapin->description;
            /**
             * The file name.
             */
            $file = $Snapin->file;
            /**
             * Tell if packtype is true or not.
             */
            if ($Snapin->packtype <= 0) {
                $packtype = _('No');
            } else {
                $packtype = _('Yes');
            }
            /**
             * The storage group name.
             */
            $storageGroup = $Snapin->storagegroupname;
            /**
             * If the snapin is not protected show
             * the unlocked symbol and title of not protected
             * otherwise set as is protected.
             */
            if ($Snapin->protected < 1) {
                $protected = '<i class="fa fa-unlock fa-1x icon hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="'
                    . _('Not protected')
                    . '"></i>';
            } else {
                $protected = '<i class="fa fa-lock fa-1x icon hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="'
                    . _('Protected')
                    . '"></i>';
            }
            /**
             * If the snapin is enabled or not.
             */
            if ($Snapin->isEnabled) {
                $enabled = '<i class="fa fa-check-circle green" '
                    . 'title="'
                    . _('Enabled')
                    . '" data-toggle="tooltip" data-placement="top">'
                    . '</i>';
            } else {
                $enabled = '<i class="fa fa-times-circle red" '
                    . 'title="'
                    . _('Disabled')
                    . '" data-toggle="tooltip" data-placement="top">'
                    . '</i>';
            }
            /**
             * Store the data.
             */
            $this->data[] = array(
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'file' => $file,
                'packtype' => $packtype,
                'storageGroup' => $storageGroup,
                'protected' => $protected,
                'enabled' => $enabled
            );
            /**
             * Cleanup.
             */
            unset(
                $id,
                $name,
                $description,
                $file,
                $packtype,
                $Snapin
            );
        };
    }
    /**
     * Generates the selector for Snapin Packs.
     *
     * @return void
     */
    private function _maker()
    {
        $args = array(
            'MSI' => array(
                'msiexec.exe',
                '/i &quot;[FOG_SNAPIN_PATH]\\MyMSI.msi&quot;'
            ),
            'MSI + MST' => array(
                'msiexec.exe',
                '/i &quot;[FOG_SNAPIN_PATH]\\MyMST.mst&quot;'
            ),
            'Batch Script' => array(
                'cmd.exe',
                '/c &quot;[FOG_SNAPIN_PATH]\\MyScript.bat&quot;'
            ),
            'Bash Script' => array(
                '/bin/bash',
                '&quot;[FOG_SNAPIN_PATH]/MyScript.sh&quot;'
            ),
            'VB Script' => array(
                'cscript.exe',
                '&quot;[FOG_SNAPIN_PATH]\\MyScript.vbs&quot;'
            ),
            'PowerShell Script' => array(
                'powershell.exe',
                '-ExecutionPolicy Bypass -File &quot;'
                .'[FOG_SNAPIN_PATH]\\MyScript.ps1&quot;'
            ),
            'PowerShell x64 Script' => array(
                '&quot;%SYSTEMROOT%\\sysnative\\windowspowershell'
                . '\\v1.0\\powershell.exe&quot;',
                '-ExecutionPolicy Bypass -File &quot;'
                .'[FOG_SNAPIN_PATH]\\MyScript.ps1&quot;'
            ),
            'EXE' => array(
                '[FOG_SNAPIN_PATH]\\MyFile.exe'
            ),
            'Mono' => array(
                'mono',
                '&quot;[FOG_SNAPIN_PATH]/MyFile.exe&quot;'
            ),
        );
        ob_start();
        printf(
            '<select id="packTypes"><option value="">- %s -</option>',
            _('Please select an option')
        );
        foreach ($args as $type => &$cmd) {
            printf(
                '<option file="%s" args="%s">%s</option>',
                $cmd[0],
                (
                    isset($cmd[1]) ?
                    $cmd[1] :
                    ''
                ),
                $type
            );
            unset($cmd);
        }
        echo '</select>';
        return ob_get_clean();
    }
    /**
     * The form to display when adding a new snapin
     * definition.
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        /**
         * Title of inital/general element.
         */
        $this->title = _('New Snapin');
        /**
         * The table attributes.
         */
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        /**
         * The table template.
         */
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $snapinfileexist = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $packtype = (int)filter_input(INPUT_POST, 'packtype');
        $rw = filter_input(INPUT_POST, 'rw');
        $rwa = filter_input(INPUT_POST, 'rwa');
        $args = filter_input(INPUT_POST, 'args');
        $timeout = filter_input(INPUT_POST, 'timeout');
        /**
         * Set the storage group to pre-select.
         */
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            $sgID = @min(self::getSubObjectIDs('StorageGroup'));
        }
        /**
         * Set our storage group object.
         */
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $sgID,
                '',
                'id'
            );
        /**
         * We'll get the files associated with the selected
         * group.
         */
        /**
         * Reset our "selected" item for our option list.
         */
        self::$selected = '';
        /**
         * If the snapin file exists, set selected item
         * to this value.
         */
        self::$selected = $snapinfileexist;
        /**
         * Initialize the filelist.
         */
        $filelist = array();
        /**
         * Get the master storage node.
         */
        $StorageNode = $StorageGroup->getMasterStorageNode();
        /**
         * Get the files on this node.
         */
        $filelist = $StorageNode->get('snapinfiles');
        /**
         * Sort our files nicely.
         *
         * Naturally sort as snapins in form:
         * 03x, 01x, 02x, or only numerically named
         * should present in human natural order.
         */
        natcasesort($filelist);
        /**
         * Filter the list.
         */
        $filelist = array_values(
            array_unique(
                array_filter($filelist)
            )
        );
        /**
         * Buffer the select box.
         */
        ob_start();
        /**
         * Build our select box based on this file list.
         */
        array_map(self::$buildSelectBox, $filelist);
        /**
         * Create our listing and store in a variable.
         */
        $selectFiles = '<select class='
            . '"snapinfileexist-input cmdlet3 form-control" '
            . 'name="snapinfileexist" id="snapinfileexist">'
            . '<option value="">- '
            . _('Please select an option')
            . ' -</option>'
            . ob_get_clean()
            . '</select>';
        /**
         * Setup the fields to be used to display.
         */
        $fields = array(
            '<label for="name">'
            . _('Snapin Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="snapinname-input form-control"/>'
            . '</div>',
            '<label for="desc">'
            . _('Snapin Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desc" class='
            . '"form-control snapindescription-input">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $StorageGroups,
            '<label for="snapinpack">'
            . _('Snapin Type')
            . '</label>' => '<select class="snapinpack-input form-control" '
            . 'name="packtype" id="snapinpack">'
            . '<option value="0"'
            . (
                $packtype == 0 ?
                ' selected' :
                ''
            )
            . '>'
            . _('Normal Snapin')
            . '</option>'
            . '<option value="1"'
            . (
                $packtype > 0 ?
                ' selected' :
                ''
            )
            . '>'
            . _('Snapin Pack')
            . '</option>'
            . '</select>',
            '<span class="hiddeninitially packnotemplate">'
            . '<label for="argTypes">'
            . _('Snapin Template')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packtemplate">'
            . '<label for="packTypes">'
            . _('Snapin Pack Template')
            . '</label>'
            . '</span>' => '<span class="hiddeninitially packnotemplate">'
            . self::$_template1
            . '</span>'
            . '<span class="hiddeninitially packtemplate">'
            . self::$_template2
            . '</span>',
            '<span class="hiddeninitially packnochangerw">'
            . '<label for="snaprw">'
            . _('Snapin Run With')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packchangerw">'
            . '<label for="snaprw">'
            . _('Snapin Pack File')
            . '</label>'
            . '</span>' => '<div class="input-group">'
            . '<input type="text" class="snapinrw-input cmdlet1 form-control" '
            . 'name="rw" id="snaprw" value="'
            . $rw
            . '"/>'
            . '</div>',
            '<span class="hiddeninitially packnochangerwa">'
            . '<label for="snaprwa">'
            . _('Snapin Run With Argument')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packchangerwa">'
            . '<label for="snaprwa">'
            . _('Snapin Pack Arguments')
            . '</label>'
            . '</span>' => '<div class="input-group">'
            . '<input type="text" class="snapinrwa-input cmdlet2 form-control" '
            . 'name="rwa" id="snaprwa" value="'
            . $rwa
            . '"/>'
            . '</div>',
            '<label for="snapinfile">'
            . _('Snapin File')
            . '<br/>'
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden cmdlet3" name="snapin" '
            . 'id="snapinfile"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp cmdlet3" readonly/>'
            . '</div>',
            (
                count($filelist) > 0 ?
                '<label for="snapinfileexist">'
                . _('Snapin File (exists)')
                . '</label>' :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            '<span class="hiddeninitially packhide">'
            . '<label for="args">'
            . _('Snapin Arguments')
            . '</label>'
            . '</span>' => '<span class="hiddeninitially packhide">'
            . '<div class="input-group">'
            . '<input type="text" name="args" id="args" class='
            . '"snapinargs-input cmdlet4 form-control" value="'
            . $args
            . '"/>'
            . '</div>'
            . '</span>',
            '<label for="isen">'
            . _('Snapin Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'class="snapinenabled-input" id="isen" checked/>',
            '<label for="isHidden">'
            . _('Snapin Arguments Hidden')
            . '</label>' => '<input type="checkbox" name="isHidden" '
            . 'class="snapinhidden-input" id="isHidden"/>',
            '<label for="timeout">'
            . _('Snapin Timeout (seconds)')
            . '</label>' => '<div class="input-group">'
            . '<input type="number" class='
            . '"snapintimeout-input form-control" name="timeout" '
            . 'id="timeout" value="'
            . $timeout
            . '"/>'
            . '</div>',
            '<label for="toRep">'
            . _('Replicate?')
            . '</label>' => '<input type="checkbox" name="toReplicate" '
            . 'class="snapinreplicate-input" id="toRep" checked/>',
            '<label for="reboot">'
            . _('Reboot after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapinreboot-input action" id="reboot" value="reboot" '
            . 'checked/>',
            '<label for="shutdown">'
            . _('Shutdown after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapinshutdown-input action" id="shutdown" value="shutdown"/>',
            '<label for="cmdletin">'
            . _('Snapin Command')
            . '<br/>'
            . _('read-only')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control snapincmd" name="snapincmd" '
            . 'id="cmdletin" readonly></textarea>',
            '<label for="add">'
            . _('Create New Snapin')
            . '</label>' => '<button type="submit" name="add" id="add" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SNAPIN_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" enctype="multipart/form-data">';
        $this->indexDivDisplay();
        echo '</form>';
        echo '</div>';
        unset(
            $fields,
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Actually sibmit the creation of the snapin.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('SNAPIN_ADD_POST');
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $packtype = filter_input(INPUT_POST, 'packtype');
        $runWith = filter_input(INPUT_POST, 'rw');
        $runWithArgs = filter_input(INPUT_POST, 'rwa');
        $storagegroup = (int)filter_input(INPUT_POST, 'storagegroup');
        $snapinfile = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        $uploadfile = basename(
            $_FILES['snapin']['name']
        );
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $hide = (int)isset($_POST['isHidden']);
        $timeout = (int)filter_input(INPUT_POST, 'timeout');
        $action = filter_input(INPUT_POST, 'action');
        $args = filter_input(INPUT_POST, 'args');
        try {
            $phpPostMaxSize = ini_get('post_max_size');
            switch (strtolower(substr($phpPostMaxSize, -1))) {
                case 'g':
                    $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
                    // no break
                case 'm':
                    $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
                    // no break
                case 'k':
                    $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
            }
            if (isset($_SERVER['CONTENT_LENGTH']) && $phpPostMaxSize > 0 &&
                    $_SERVER['CONTENT_LENGTH'] > $phpPostMaxSize) {
                throw new Exception(
                    _('Snapin file is too big, increase post_max_size in php.ini.')
                );
            }
            if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) &&
                    $_SERVER['CONTENT_LENGTH'] > 0) {
                throw new Exception(
                    _('$_POST variable is empty, check apache error log.')
                );
            }
            if (!$name) {
                throw new Exception(
                    _('A snapin name is required!')
                );
            }
            if (self::getClass('SnapinManager')->exists($name)) {
                throw new Exception(
                    _('A snapin already exists with this name!')
                );
            }
            if (!$snapinfile) {
                throw new Exception(
                    sprintf(
                        '%s, %s, %s!',
                        _('A file'),
                        _('either already selected or uploaded'),
                        _('must be specified')
                    )
                );
            }
            if (preg_match('#ssl#i', $snapinfile)) {
                throw new Exception(
                    sprintf(
                        '%s, %s.',
                        _('Please choose a different name'),
                        _('this one is reserved for FOG')
                    )
                );
            }
            $snapinfile = preg_replace('/[^-\w\.]+/', '_', $snapinfile);
            $StorageGroup = new StorageGroup($storagegroup);
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$snapinfile && $_FILES['snapin']['error'] > 0) {
                throw new UploadException($_FILES['snapin']['error']);
            }
            $src = sprintf(
                '%s/%s',
                dirname($_FILES['snapin']['tmp_name']),
                basename($_FILES['snapin']['tmp_name'])
            );
            $dest = sprintf(
                '/%s/%s',
                trim(
                    $StorageNode->get('snapinpath'),
                    '/'
                ),
                $snapinfile
            );
            set_time_limit(0);
            $hash = '';
            $size = 0;
            if ($uploadfile && file_exists($src)) {
                $hash = hash_file('sha512', $src);
                $size = self::getFilesize($src);
                self::$FOGFTP
                    ->set('host', $StorageNode->get('ip'))
                    ->set('username', $StorageNode->get('user'))
                    ->set('password', $StorageNode->get('pass'));
                if (!self::$FOGFTP->connect()) {
                    throw new Exception(
                        sprintf(
                            '%s: %s: %s.',
                            _('Storage Node'),
                            $StorageNode->get('ip'),
                            _('FTP Connection has failed')
                        )
                    );
                }
                if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                    if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) {
                        throw new Exception(
                            _('Failed to add snapin')
                        );
                    }
                }
                self::$FOGFTP->delete($dest);
                if (!self::$FOGFTP->put($dest, $src)) {
                    throw new Exception(
                        _('Failed to add/update snapin file')
                    );
                }
                self::$FOGFTP
                    ->chmod(0777, $dest)
                    ->close();
            }
            $Snapin = self::getClass('Snapin')
                ->set('name', $name)
                ->set('packtype', $packtype)
                ->set('description', $desc)
                ->set('file', $snapinfile)
                ->set('hash', $hash)
                ->set('size', $size)
                ->set('args', $args)
                ->set('reboot', $action == 'reboot')
                ->set('shutdown', $action == 'shutdown')
                ->set('runWith', $runWith)
                ->set('runWithArgs', $runWithArgs)
                ->set('isEnabled', $isEnabled)
                ->set('toReplicate', $toReplicate)
                ->set('hide', $hide)
                ->set('timeout', $timeout)
                ->addGroup($storagegroup);
            if (!$Snapin->save()) {
                throw new Exception(_('Add snapin failed!'));
            }
            /**
             * During snapin creation we only allow a single group anyway.
             * This will set it to be the primary master.
             */
            $Snapin->setPrimaryGroup($storagegroup);
            $hook = 'SNAPIN_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Snapin added!'),
                    'title' => _('Snapin Create Success')
                )
            );
        } catch (Exception $e) {
            self::$FOGFTP->close();
            $hook = 'SNAPIN_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Snapin' => &$Snapin)
            );
        unset($Snapin);
        echo $msg;
        exit;
    }
    /**
     * Display snapin general edit elements.
     *
     * @return void
     */
    public function snapinGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('Snapin General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $snapinfileexists = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        if (!$snapinfileexists) {
            $snapinfileexists = $this->obj->get('file');
        }
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $desc = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $packtype = (int)filter_input(INPUT_POST, 'packtype') ?:
            (int)$this->obj->get('packtype');
        $rw = filter_input(INPUT_POST, 'rw') ?:
            $this->obj->get('runWith');
        $rwa = filter_input(INPUT_POST, 'rwa') ?:
            $this->obj->get('runWithArgs');
        $protected = (int)isset($_POST['protected_snapin']) ?:
            (int)$this->obj->get('protected');
        $toReplicate = (int)isset($_POST['toReplicate']) ?:
            (int)$this->obj->get('toReplicate');
        $isEnabled = (int)isset($_POST['isEnabled']) ?:
            (int)$this->obj->get('isEnabled');
        $isHidden = (int)isset($_POST['isHidden']) ?:
            (int)$this->obj->get('hide');
        $timeout = (int)filter_input(INPUT_POST, 'timeout') ?:
            (int)$this->obj->get('timeout');
        $ishid = (
            $isHidden > 0 ? ' checked' : ''
        );
        $isprot = (
            $protected > 0 ? ' checked' : ''
        );
        $isen = (
            $isEnabled > 0 ? ' checked' : ''
        );
        $isrep = (
            $toReplicate > 0 ? ' checked' : ''
        );
        $action = filter_input(INPUT_POST, 'action');
        if (!$action) {
            $action = (
                $this->obj->get('shutdown') ?
                'shutdown' : (
                    $this->obj->get('reboot') ?
                    'reboot' :
                    ''
                )
            );
        }
        $reboot = (
            $action == 'reboot' ? ' checked' : ''
        );
        $shutdown = (
            $action == 'shutdown' ? ' checked' : ''
        );
        $args = filter_input(INPUT_POST, 'args') ?:
            $this->obj->get('args');
        self::$selected = $snapinfileexists;
        $filelist = self::getSubObjectIDs(
            'Snapin',
            '',
            'file'
        );
        $filelist = array_values(
            array_unique(
                array_filter(
                    $filelist
                )
            )
        );
        natcasesort($filelist);
        ob_start();
        array_map(
            self::$buildSelectBox,
            $filelist
        );
        $selectFiles = '<select class='
            . '"snapinfileexist-input cmdlet3 form-control" '
            . 'name="snapinfileexist" id="snapinfileexist">'
            . '<option value="">- '
            . _('Please select an option')
            . ' -</option>'
            . ob_get_clean()
            . '</select>';
        $fields = array(
            '<label for="name">'
            . _('Snapin Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="snapinname-input form-control"/>'
            . '</div>',
            '<label for="desc">'
            . _('Snapin Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desc" class='
            . '"form-control snapindescription-input">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="snapinpack">'
            . _('Snapin Type')
            . '</label>' => '<select class="snapinpack-input form-control" '
            . 'name="packtype" id="snapinpack">'
            . '<option value="0"'
            . (
                $packtype == 0 ?
                ' selected' :
                ''
            )
            . '>'
            . _('Normal Snapin')
            . '</option>'
            . '<option value="1"'
            . (
                $packtype > 0 ?
                ' selected' :
                ''
            )
            . '>'
            . _('Snapin Pack')
            . '</option>'
            . '</select>',
            '<span class="hiddeninitially packnotemplate">'
            . '<label for="argTypes">'
            . _('Snapin Template')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packtemplate">'
            . '<label for="packTypes">'
            . _('Snapin Pack Template')
            . '</label>'
            . '</span>' => '<span class="hiddeninitially packnotemplate">'
            . self::$_template1
            . '</span>'
            . '<span class="hiddeninitially packtemplate">'
            . self::$_template2
            . '</span>',
            '<span class="hiddeninitially packnochangerw">'
            . '<label for="snaprw">'
            . _('Snapin Run With')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packchangerw">'
            . '<label for="snaprw">'
            . _('Snapin Pack File')
            . '</label>'
            . '</span>' => '<div class="input-group">'
            . '<input type="text" class="snapinrw-input cmdlet1 form-control" '
            . 'name="rw" id="snaprw" value="'
            . $rw
            . '"/>'
            . '</div>',
            '<span class="hiddeninitially packnochangerwa">'
            . '<label for="snaprwa">'
            . _('Snapin Run With Argument')
            . '</label>'
            . '</span>'
            . '<span class="hiddeninitially packchangerwa">'
            . '<label for="snaprwa">'
            . _('Snapin Pack Arguments')
            . '</label>'
            . '</span>' => '<div class="input-group">'
            . '<input type="text" class="snapinrwa-input cmdlet2 form-control" '
            . 'name="rwa" id="snaprwa" value="'
            . $rwa
            . '"/>'
            . '</div>',
            '<label for="snapinfile">'
            . _('Snapin File')
            . '<br/>'
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden cmdlet3" name="snapin" '
            . 'id="snapinfile"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp cmdlet3" value="'
            . $snapinfileexists
            . '" readonly/>'
            . '</div>',
            (
                count($filelist) > 0 ?
                '<label for="snapinfileexist">'
                . _('Snapin File (exists)')
                . '</label>' :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            '<span class="hiddeninitially packhide">'
            . '<label for="args">'
            . _('Snapin Arguments')
            . '</label>'
            . '</span>' => '<span class="hiddeninitially packhide">'
            . '<div class="input-group">'
            . '<input type="text" name="args" id="args" class='
            . '"snapinargs-input cmdlet4 form-control" value="'
            . $args
            . '"/>'
            . '</div>'
            . '</span>',
            '<label for="isprot">'
            . _('Snapin Protected')
            . '</label>' => '<input type="checkbox" name="protected_snapin" '
            . 'class="snapinprotected-input" id="isprot"'
            . $isprot
            . '/>',
            '<label for="isen">'
            . _('Snapin Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'class="snapinenabled-input" id="isen"'
            . $isen
            . '/>',
            '<label for="isHidden">'
            . _('Snapin Arguments Hidden')
            . '</label>' => '<input type="checkbox" name="isHidden" '
            . 'class="snapinhidden-input" id="isHidden"'
            . $ishid
            . '/>',
            '<label for="timeout">'
            . _('Snapin Timeout (seconds)')
            . '</label>' => '<div class="input-group">'
            . '<input type="number" class='
            . '"snapintimeout-input form-control" name="timeout" '
            . 'id="timeout" value="'
            . $timeout
            . '"/>'
            . '</div>',
            '<label for="toRep">'
            . _('Replicate?')
            . '</label>' => '<input type="checkbox" name="toReplicate" '
            . 'class="snapinreplicate-input" id="toRep"'
            . $isrep
            . '/>',
            '<label for="reboot">'
            . _('Reboot after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapinreboot-input action" id="reboot" value="reboot"'
            . $reboot
            . '/>',
            '<label for="shutdown">'
            . _('Shutdown after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapinshutdown-input action" id="shutdown" value="shutdown"'
            . $shutdown
            . '/>',
            '<label for="cmdletin">'
            . _('Snapin Command')
            . '<br/>'
            . _('read-only')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control snapincmd" name="snapincmd" '
            . 'id="cmdletin" readonly></textarea>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        $fields = array_filter(
            $fields
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SNAPIN_EDIT',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes,
                    'templates' => &$this->templates
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="snap-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=snap-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Display snapin storage groups.
     *
     * @return void
     */
    public function snapinStoragegroups()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $this->headerData = array(
            '<label for="toggler2">'
            . '<input type="checkbox" name="toggle-checkboxgroup1" '
            . 'class="toggle-checkbox1" id="toggler2"/>'
            . '</label>',
            _('Storage Group Name')
        );
        $this->templates = array(
            '<label for="sg-${storageGroup_id}">'
            . '<input type="checkbox" name="storagegroup[]" class='
            . '"toggle-group" id="sg-${storageGroup_id}" '
            . 'value="${storageGroup_id}"/>'
            . '</label>',
            '<a href="?node=storage&editStorageGroup&id=${storageGroup_id}">'
            . '${storageGroup_name}'
            . '</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
        );
        Route::listem('storagegroup');
        $StorageGroups = json_decode(
            Route::getData()
        );
        $StorageGroups = $StorageGroups->storagegroups;
        foreach ((array)$StorageGroups as &$StorageGroup) {
            $groupinme = in_array(
                $StorageGroup->id,
                $this->obj->get('storagegroups')
            );
            if ($groupinme) {
                continue;
            }
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->id,
                'storageGroup_name' => $StorageGroup->name,
            );
            unset($StorageGroup);
        }
        self::$HookManager->processEvent(
            'SNAPIN_ADD_STORAGE_GROUP',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<!-- Storage Groups -->';
        echo '<div class="tab-pane fade" id="snap-storage">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Snapin Storage Groups');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=snap-storage" enctype="multipart/form-data">';
        if (is_array($this->data) && count($this->data)) {
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="groupMeShow">';
            echo '<input type="checkbox" name="groupMeShow" '
                . 'id="groupMeShow"/>';
            echo _('Check here to see what storage groups can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially groupNotInMe panel panel-info" '
                . 'id="groupNotInMe">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Storage Groups');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updategroups" class="control-label col-xs-4">';
            echo _('Add selected storage groups');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="updategroups" class='
                . '"btn btn-info btn-block" id="updategroups">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler3">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler3"/>'
            . '</label>',
            '',
            _('Storage Group Name')
        );
        $this->templates = array(
            '<label for="sg1-${storageGroup_id}">'
            . '<input type="checkbox" name="storagegroup-rm[]" class='
            . '"toggle-group" id="sg1-${storageGroup_id}" '
            . 'value="${storageGroup_id}"/>'
            . '</label>',
            '<div class="radio">'
            . '<input type="radio" class="default" '
            . 'name="primary" id="group${storageGroup_id}" '
            . 'value="${storageGroup_id}" ${is_primary}/>'
            . '<label for="group${storageGroup_id}">'
            . '</label>'
            . '</div>',
            '<a href="?node=storage&editStorageGroup&id=${storageGroup_id}">'
            . '${storageGroup_name}'
            . '</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
        );
        foreach ((array)$StorageGroups as &$StorageGroup) {
            $groupinme = in_array(
                $StorageGroup->id,
                $this->obj->get('storagegroups')
            );
            if (!$groupinme) {
                continue;
            }
            $this->data[] = array(
                'storageGroup_id' => $StorageGroup->id,
                'storageGroup_name' => $StorageGroup->name,
                'is_primary' => (
                    $this->obj->getPrimaryGroup($StorageGroup->id) ?
                    ' checked' :
                    ''
                )
            );
            unset($StorageGroup);
        }
        if (is_array($this->data) && count($this->data) > 0) {
            self::$HookManager->processEvent(
                'SNAPIN_EDIT_STORAGE_GROUP',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Update/Remove Storage Groups');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="primarysel" class="control-label col-xs-4">';
            echo _('Update primary group');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="primarysel" class='
                . '"btn btn-info btn-block" id="primarysel">'
                . _('Update')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label for="groupdel" class="control-label col-xs-4">';
            echo _('Remove selected groups');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="groupdel" class='
                . '"btn btn-danger btn-block" id="groupdel">'
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
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Edit this snapin
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->snapinGeneral();
        $this->snapinStoragegroups();
        echo '</div>';
    }
    /**
     * Snapin General Post
     *
     * @return void
     */
    public function snapinGeneralPost()
    {
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $packtype = filter_input(INPUT_POST, 'packtype');
        $runWith = filter_input(INPUT_POST, 'rw');
        $runWithArgs = filter_input(INPUT_POST, 'rwa');
        $snapinfile = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        $uploadfile = basename(
            $_FILES['snapin']['name']
        );
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $protected = (int)isset($_POST['protected_snapin']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $hide = (int)isset($_POST['isHidden']);
        $timeout = (int)filter_input(INPUT_POST, 'timeout');
        $action = filter_input(INPUT_POST, 'action');
        $args = filter_input(INPUT_POST, 'args');
        $phpPostMaxSize = ini_get('post_max_size');
        switch (strtolower(substr($phpPostMaxSize, -1))) {
            case 'g':
                $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
                // no break
            case 'm':
                $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
                // no break
            case 'k':
                $phpPostMaxSize = ((int)$phpPostMaxSize) * 1024;
        }
        if (isset($_SERVER['CONTENT_LENGTH']) && $phpPostMaxSize > 0 &&
                $_SERVER['CONTENT_LENGTH'] > $phpPostMaxSize) {
            throw new Exception(
                _('Snapin file is too big, increase post_max_size in php.ini.')
            );
        }
        if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) &&
                $_SERVER['CONTENT_LENGTH'] > 0) {
            throw new Exception(
                _('$_POST variable is empty, check apache error log.')
            );
        }
        if (!$name) {
            throw new Exception(
                _('A snapin name is required!')
            );
        }
        if ($this->obj->get('name') != $name
            && self::getClass('SnapinManager')->exists(
                $name,
                $this->obj->get('id')
            )
        ) {
            throw new Exception(
                _('A snapin already exists with this name!')
            );
        }
        if (!$snapinfile) {
            throw new Exception(
                sprintf(
                    '%s, %s, %s!',
                    _('A file'),
                    _('either already selected or uploaded'),
                    _('must be specified')
                )
            );
        }
        if (preg_match('#ssl#i', $snapinfile)) {
            throw new Exception(
                sprintf(
                    '%s, %s.',
                    _('Please choose a different name'),
                    _('this one is reserved for FOG')
                )
            );
        }
        $snapinfile = preg_replace('/[^-\w\.]+/', '_', $snapinfile);
        $StorageNode = $this
            ->obj
            ->getStorageGroup()
            ->getMasterStorageNode();
        if (!$snapinfile && $_FILES['snapin']['error'] > 0) {
            throw new UploadException($_FILES['snapin']['error']);
        }
        $src = sprintf(
            '%s/%s',
            dirname($_FILES['snapin']['tmp_name']),
            basename($_FILES['snapin']['tmp_name'])
        );
        set_time_limit(0);
        if ($uploadfile && file_exists($src)) {
            $hash = hash_file('sha512', $src);
            $size = self::getFilesize($src);
        } else {
            if ($snapinfile == $this->obj->get('file')) {
                $hash = $this->obj->get('hash');
                $size = $this->obj->get('size');
            } else {
                $hash = '';
                $size = 0;
            }
        }
        $dest = sprintf(
            '/%s/%s',
            trim(
                $StorageNode->get('snapinpath'),
                '/'
            ),
            $snapinfile
        );
        if ($uploadfile) {
            self::$FOGFTP
                ->set('host', $StorageNode->get('ip'))
                ->set('username', $StorageNode->get('user'))
                ->set('password', $StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) {
                throw new Exception(
                    sprintf(
                        '%s: %s: %s.',
                        _('Storage Node'),
                        $StorageNode->get('ip'),
                        _('FTP Connection has failed')
                    )
                );
            }
            if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) {
                    throw new Exception(
                        _('Failed to add snapin')
                    );
                }
            }
            self::$FOGFTP->delete($dest);
            if (!self::$FOGFTP->put($dest, $src)) {
                throw new Exception(
                    _('Failed to add/update snapin file')
                );
            }
            self::$FOGFTP
                ->chmod(0777, $dest)
                ->close();
        }
        $this->obj
            ->set('name', $name)
            ->set('packtype', $packtype)
            ->set('description', $desc)
            ->set('file', $snapinfile)
            ->set('args', $args)
            ->set('hash', $hash)
            ->set('size', $size)
            ->set('reboot', $action == 'reboot')
            ->set('shutdown', $action == 'shutdown')
            ->set('runWith', $runWith)
            ->set('runWithArgs', $runWithArgs)
            ->set('protected', $protected)
            ->set('isEnabled', $isEnabled)
            ->set('toReplicate', $toReplicate)
            ->set('hide', $hide)
            ->set('timeout', $timeout);
    }
    /**
     * Snapin Storage Group Post
     *
     * @return void
     */
    public function snapinStoragegroupsPost()
    {
        $items = filter_input_array(
            INPUT_POST,
            array(
                'storagegroup' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                ),
                'storagegroup-rm' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $storagegroup = $items['storagegroup'];
        $storagegrouprm = $items['storagegroup-rm'];
        $primary = (int)filter_input(
            INPUT_POST,
            'primary'
        );
        if (isset($_POST['updategroups'])) {
            $this->obj->addGroup($storagegroup);
        } elseif (isset($_POST['primarysel'])) {
            $this->obj->setPrimaryGroup($primary);
        } elseif (isset($_POST['groupdel'])) {
            $groupdel = count($storagegrouprm);
            $ingroups = count($this->obj->get('storagegroups'));
            if ($groupdel < 1) {
                throw new Exception(
                    _('No groups selected to be removed')
                );
            }
            if ($ingroups < 2) {
                throw new Exception(
                    _('You must have at least one group associated')
                );
            }
            $this
                ->obj
                ->removeGroup(
                    $storagegrouprm
                );
        }
    }
    /**
     * Submit for update.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager->processEvent(
            'SNAPIN_EDIT_POST',
            array(
                'Snapin' => &$this->obj
            )
        );
        global $tab;
        try {
            switch ($tab) {
            case 'snap-gen':
                $this->snapinGeneralPost();
                break;
            case 'snap-storage':
                $this->snapinStoragegroupsPost();
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Snapin update failed!'));
            }
            $hook = 'SNAPIN_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Snapin updated!'),
                    'title' => _('Snapin Update Success')
                )
            );
        } catch (Exception $e) {
            self::$FOGFTP->close();
            $hook = 'SNAPIN_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Snapin' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
