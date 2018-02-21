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
    private static $_argTypes = [
        'MSI' => ['msiexec.exe','/i','/quiet'],
        'Batch Script' => ['cmd.exe','/c'],
        'Bash Script' => ['/bin/bash'],
        'VB Script' => ['cscript.exe'],
        'Powershell' => [
            'powershell.exe',
            '-ExecutionPolicy Bypass -NoProfile -File'
        ],
        'Mono' => ['mono']
    ];
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
            '<select class="form-control packnotemplate hidden" name="argTypes" id="argTypes">'
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
        $this->headerData = [
            _('Snapin Name'),
            _('Protected'),
            _('Enabled'),
            _('Is Pack')
        ];
        /**
         * The template for the list/search elements.
         */
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        /**
         * The attributes for the table items.
         */
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Generates the selector for Snapin Packs.
     *
     * @return void
     */
    private function _maker()
    {
        $args = [
            'MSI' => [
                'msiexec.exe',
                '/i &quot;[FOG_SNAPIN_PATH]\MyMSI.msi&quot;'
            ],
            'MSI + MST' => [
                'msiexec.exe',
                '/i &quot;[FOG_SNAPIN_PATH]\MyMST.mst&quot;'
            ],
            'Batch Script' => [
                'cmd.exe',
                '/c &quot;[FOG_SNAPIN_PATH]\MyScript.bat&quot;'
            ],
            'Bash Script' => [
                '/bin/bash',
                '&quot;[FOG_SNAPIN_PATH]/MyScript.sh&quot;'
            ],
            'VB Script' => [
                'cscript.exe',
                '&quot;[FOG_SNAPIN_PATH]\MyScript.vbs&quot;'
            ],
            'PowerShell Script' => [
                'powershell.exe',
                '-ExecutionPolicy Bypass -File &quot;'
                .'[FOG_SNAPIN_PATH]\MyScript.ps1&quot;'
            ],
            'EXE' => [
                '[FOG_SNAPIN_PATH]\MyFile.exe'
            ],
            'Mono' => [
                'mono',
                '&quot;[FOG_SNAPIN_PATH]/MyFile.exe&quot;'
            ],
        ];
        ob_start();
        printf(
            '<select class="form-control packtemplate hidden" id="packTypes"><option value="">- %s -</option>',
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
        $this->title = _('Create New Snapin');
        /**
         * Setup our variables for back up/incorrect settings without
         * making the user reset entirely
         */
        $snapin = filter_input(INPUT_POST, 'snapin');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $snapinfileexist = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        $packtype = (int)filter_input(INPUT_POST, 'packtype');
        $rw = filter_input(INPUT_POST, 'rw');
        $rwa = filter_input(INPUT_POST, 'rwa');
        $args = filter_input(INPUT_POST, 'args');
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
        $filelist = [];
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

        $packtypes = '<select class="form-control" '
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
            . '</select>';
        /**
         * Setup the fields to be used to display.
         */
        $fields = [
            '<label class="col-sm-2 control-label" for="snapin">'
            . _('Snapin Name')
            . '</label>' => '<input type="text" name="snapin" '
            . 'value="'
            . $snapin
            . '" class="form-control" id="snapin" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Snapin Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $StorageGroups,
            '<label class="col-sm-2 control-label" for="snapinpack">'
            . _('Snapin Type')
            . '</label>' => $packtypes,
            '<label class="packnotemplate hidden col-sm-2 control-label" for="argTypes">'
            . _('Snapin Template')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="packTypes">'
            . _('Snapin Pack Template')
            . '</label>' => self::$_template1
            . self::$_template2,
            '<label class="packnotemplate hidden col-sm-2 control-label" for="snaprw">'
            . _('Snapin Run With')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="snaprw">'
            . _('Snapin Pack File')
            . '</label>' => '<input type="text" name="rw" '
            . 'value="'
            . $rw
            . '" class="snapinrw-input cmdlet1 form-control" '
            . 'id="snaprw"/>',
            '<label class="packnotemplate hidden col-sm-2 control-label" for="snaprwa">'
            . _('Snapin Run With Argument')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="snaprwa">'
            . _('Snapin Pack Arguments')
            . '</label>' => '<input type="text" name="rwa" '
            . 'value="'
            . $rwa
            . '" class="snapinrwa-input cmdlet2 form-control" '
            . 'id="snaprwa"/>',
            '<label class="col-sm-2 control-label" for="snapinfile">'
            . _('Snapin File')
            . '<br/>('
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . ')</label>' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden cmdlet3" name="snapinfile" '
            . 'id="snapinfile"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp cmdlet3" readonly/>'
            . '</div>',
            (
                count($filelist) > 0 ?
                '<label class="col-sm-2 control-label" for="snapinfileexist">'
                . _('Snapin File (exists)')
                . '</label>' :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            '<label class="packhide hidden col-sm-2 control-label" for="args">'
            . _('Snapin Arguments')
            . '</label>'
            . '</span>' => '<input type="text" name="args" '
            . 'value="'
            . $args
            . '" class="packhide hidden snapinargs-input cmdlet4 form-control" '
            . 'id="args"/>',
            '<label class="col-sm-2 control-label" for="isen">'
            . _('Snapin Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'class="snapinenabled-input" id="isen" checked/>',
            '<label class="col-sm-2 control-label" for="isHidden">'
            . _('Snapin Arguments Hidden')
            . '</label>' => '<input type="checkbox" name="isHidden" '
            . 'class="snapinhidden-input" id="isHidden"/>',
            '<label class="col-sm-2 control-label" for="timeout">'
            . _('Snapin Timeout (seconds)')
            . '</label>' => '<input type="number" name="timeout" '
            . 'value="0" class="snapintimeout-input form-control" '
            . 'id="timeout"/>',
            '<label class="col-sm-2 control-label" for="toRep">'
            . _('Replicate')
            . '</label>' => '<input type="checkbox" '
            . 'name="toReplicate" id="toRep" checked/>',
            '<label class="col-sm-2 control-label" for="reboot">'
            . _('Reboot after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapin-action" id="reboot" value="reboot"/>',
            '<label class="col-sm-2 control-label" for="shutdown">'
            . _('Shutdown after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapin-action" id="shutdown" value="shutdown"/>',
            '<label class="col-sm-2 control-label" for="cmdletin">'
            . _('Snapin Command')
            . '<br/>'
            . _('read-only')
            . '</label>' => '<textarea class="form-control snapincmd" name="snapincmd" '
            . 'id="cmdletin" style="resize:vertical;height:50px;" readonly></textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'SNAPIN_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Snapin' => self::getClass('Snapin')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="snapin-create">';
        echo '<form id="snapin-create-form" class="form-horizontal" '
            . 'method="post" enctype="multipart/form-data" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Snapin');
        echo '</h3>';
        echo '</div>';
        echo '<!-- Snapin General -->';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Actually sibmit the creation of the snapin.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('SNAPIN_ADD_POST');
        $snapin = trim(
            filter_input(
                INPUT_POST,
                'snapin'
            )
        );
        $description = trim(
            filter_input(
                INPUT_POST,
                'description'
            )
        );
        $packtype = trim(
            filter_input(
                INPUT_POST,
                'packtype'
            )
        );
        $runWith = trim(
            filter_input(
                INPUT_POST,
                'rw'
            )
        );
        $runWithArgs = trim(
            filter_input(
                INPUT_POST,
                'rwa'
            )
        );
        $storagegroup = (int)trim(
            filter_input(
                INPUT_POST,
                'storagegroup'
            )
        );
        $snapinfile = basename(
            trim(
                filter_input(
                    INPUT_POST,
                    'snapinfileexist'
                )
            )
        );
        $uploadfile = basename(
            trim(
                $_FILES['snapinfile']['name']
            )
        );
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $hide = (int)isset($_POST['isHidden']);
        $tiemout = (int)trim(
            filter_input(
                INPUT_POST,
                'timeout'
            )
        );
        $action = trim(
            filter_input(
                INPUT_POST,
                'action'
            )
        );
        $args = trim(
            filter_input(
                INPUT_POST,
                'args'
            )
        );
        $serverFault = false;
        try {
            if (!$snapin) {
                throw new Exception(
                    _('A snapin name is required!')
                );
            }
            if (self::getClass('SnapinManager')->exists($snapin)) {
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
            if (!$snapinfile && $_FILES['snapinfile']['error'] > 0) {
                throw new UploadException($_FILES['snapinfile']['error']);
            }
            $src = sprintf(
                '%s/%s',
                dirname($_FILES['snapinfile']['tmp_name']),
                basename($_FILES['snapinfile']['tmp_name'])
            );
            set_time_limit(0);
            $hash = '';
            $size = 0;
            if ($uploadfile && file_exists($src)) {
                $hash = hash_file('sha512', $src);
                $size = self::getFilesize($src);
            }
            $dest = sprintf(
                '/%s/%s',
                trim(
                    $StorageNode->get('snapinpath'), '/'
                ),
                $snapinfile
            );
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
            $Snapin = self::getClass('Snapin')
                ->set('name', $snapin)
                ->set('description', $description)
                ->set('packtype', $packtype)
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
                $serverFault = true;
                throw new Exception(_('Add snapin failed!'));
            }
            /**
             * During snapin creation we only allow a single group anyway.
             * This will set it to be the primary master.
             */
            $Snapin->setPrimaryGroup($storagegroup);
            $code = 201;
            $hook = 'SNAPIN_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Snapin added!'),
                    'title' => _('Snapin Create Success')
                ]
            );
        } catch (Exception $e) {
            self::$FOGFTP->close();
            $code = ($serverFault ? 500 : 400);
            $hook = 'SNAPIN_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Create Fail')
                ]
            );
        }
        http_response_code($code);
        //header('Location: ../management/index.php?node=snapin&sub=edit&id=' . $Snapin->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                ['Snapin' => &$Snapin]
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
        $snapin = filter_input(INPUT_POST, 'snapin') ?:
            $this->obj->get('name');
        $description = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $packtype = (int)(filter_input(INPUT_POST, 'packtype') ?:
            $this->obj->get('packtype'));
        $snapinfileexists = basename(
            filter_input(INPUT_POST, 'snapinfileexist') ?:
            $this->obj->get('file')
        );
        $rw = filter_input(INPUT_POST, 'rw') ?:
            $this->obj->get('runWith');
        $rwa = filter_input(INPUT_POST, 'rwa') ?:
            $this->obj->get('runWithArgs');
        $protected = (int)(isset($_POST['protected']) ?:
            $this->obj->get('protected'));
        $toReplicate = (int)(isset($_POST['toReplicate']) ?:
            $this->obj->get('toReplicate'));
        $isEnabled = (int)(isset($_POST['isEnabled']) ?:
            $this->obj->get('isEnabled'));
        $isHidden = (int)(isset($_POST['isHidden']) ?:
            $this->obj->get('hide'));
        $ishid = ($isHidden ? ' checked' : '');
        $isprot = ($protected ? ' checked' : '');
        $isen = ($isEnabled ? ' checked' : '');
        $isrep = ($toReplicate ? ' checked' : '');
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
        $reboot = $shutdown = '';
        switch ($action) {
        case 'reboot':
            $reboot = ' checked';
            break;
        case 'shutdown':
            $shutdown = ' checked';
            break;
        }
        $args = filter_input(INPUT_POST, 'args') ?:
            $this->obj->get('args');
        self::$selected = $snapinfileexists;
        $filelist = array_values(
            array_unique(
                array_filter(
                    self::getSubObjectIDs(
                        'Snapin',
                        '',
                        'file'
                    )
                )
            )
        );
        natcasesort($filelist);
        ob_start();
        array_map(self::$buildSelectBox, $filelist);
        $selectFiles = '<select class='
            . '"snapinfileexist-input cmdlet3 form-control" '
            . 'name="snapinfileexist" id="snapinfileexist">'
            . '<option value="">- '
            . _('Please select an option')
            . ' -</option>'
            . ob_get_clean()
            . '</select>';

        $packtypes = '<select class="form-control" '
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
            . '</select>';
        /**
         * Setup the fields to be used to display.
         */
        $fields = [
            '<label class="col-sm-2 control-label" for="snapin">'
            . _('Snapin Name')
            . '</label>' => '<input type="text" name="snapin" '
            . 'value="'
            . $snapin
            . '" class="form-control" id="snapin" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Snapin Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $StorageGroups,
            '<label class="col-sm-2 control-label" for="snapinpack">'
            . _('Snapin Type')
            . '</label>' => $packtypes,
            '<label class="packnotemplate hidden col-sm-2 control-label" for="argTypes">'
            . _('Snapin Template')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="packTypes">'
            . _('Snapin Pack Template')
            . '</label>' => self::$_template1
            . self::$_template2,
            '<label class="packnotemplate hidden col-sm-2 control-label" for="snaprw">'
            . _('Snapin Run With')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="snaprw">'
            . _('Snapin Pack File')
            . '</label>' => '<input type="text" name="rw" '
            . 'value="'
            . $rw
            . '" class="snapinrw-input cmdlet1 form-control" '
            . 'id="snaprw"/>',
            '<label class="packnotemplate hidden col-sm-2 control-label" for="snaprwa">'
            . _('Snapin Run With Argument')
            . '</label>'
            . '<label class="packtemplate hidden col-sm-2 control-label" for="snaprwa">'
            . _('Snapin Pack Arguments')
            . '</label>' => '<input type="text" name="rwa" '
            . 'value="'
            . $rwa
            . '" class="snapinrwa-input cmdlet2 form-control" '
            . 'id="snaprwa"/>',
            '<label class="col-sm-2 control-label" for="snapinfile">'
            . _('Snapin File')
            . '<br/>('
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . ')</label>' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden cmdlet3" name="snapinfile" '
            . 'id="snapinfile"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp cmdlet3" readonly/>'
            . '</div>',
            (
                count($filelist) > 0 ?
                '<label class="col-sm-2 control-label" for="snapinfileexist">'
                . _('Snapin File (exists)')
                . '</label>' :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            '<label class="packhide hidden col-sm-2 control-label" for="args">'
            . _('Snapin Arguments')
            . '</label>'
            . '</span>' => '<input type="text" name="args" '
            . 'value="'
            . $args
            . '" class="packhide hidden snapinargs-input cmdlet4 form-control" '
            . 'id="args"/>',
            '<label class="col-sm-2 control-label" for="isprot">'
            . _('Snapin Protected')
            . '</label>' => '<input type="checkbox" name="protected" '
            . 'class="snapinprotected-input" id="isprot"'
            . $isprot
            . '/>',
            '<label class="col-sm-2 control-label" for="isen">'
            . _('Snapin Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'class="snapinenabled-input" id="isen"'
            . $isen
            . '/>',
            '<label class="col-sm-2 control-label" for="isHidden">'
            . _('Snapin Arguments Hidden')
            . '</label>' => '<input type="checkbox" name="isHidden" '
            . 'class="snapinhidden-input" id="isHidden"'
            . $ishid
            . '/>',
            '<label class="col-sm-2 control-label" for="timeout">'
            . _('Snapin Timeout (seconds)')
            . '</label>' => '<input type="number" name="timeout" '
            . 'value="0" class="snapintimeout-input form-control" '
            . 'id="timeout"/>',
            '<label class="col-sm-2 control-label" for="toRep">'
            . _('Replicate')
            . '</label>' => '<input type="checkbox" '
            . 'name="toReplicate" id="toRep"'
            . $isrep
            . '/>',
            '<label class="col-sm-2 control-label" for="reboot">'
            . _('Reboot after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapin-action" id="reboot" value="reboot"'
            . $reboot
            . '/>',
            '<label class="col-sm-2 control-label" for="shutdown">'
            . _('Shutdown after install')
            . '</label>' => '<input type="radio" name="action" '
            . 'class="snapin-action" id="shutdown" value="shutdown"'
            . $shutdown
            . '/>',
            '<label class="col-sm-2 control-label" for="cmdletin">'
            . _('Snapin Command')
            . '<br/>'
            . _('read-only')
            . '</label>' => '<textarea class="form-control snapincmd" name="snapincmd" '
            . 'id="cmdletin" style="resize:vertical;height:50px;" readonly></textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'SNAPIN_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'obj' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="snapin-general-form" class="form-horizontal" '
            . 'enctype="multipart/form-data" method="post" action="'
            . self::makeTabUpdateURL('snapin-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Snapin General Post
     *
     * @return void
     */
    public function snapinGeneralPost()
    {
        $snapin = filter_input(INPUT_POST, 'snapin');
        $description = filter_input(INPUT_POST, 'description');
        $packtype = filter_input(INPUT_POST, 'packtype');
        $runWith = filter_input(INPUT_POST, 'rw');
        $runWithArgs = filter_input(INPUT_POST, 'rwa');
        $snapinfile = basename(
            filter_input(INPUT_POST, 'snapinfileexist')
        );
        $uploadfile = basename(
            $_FILES['snapinfile']['name']
        );
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $protected = (int)isset($_POST['protected']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $hide = (int)isset($_POST['isHidden']);
        $timeout = (int)filter_input(INPUT_POST, 'timeout');
        $action = filter_input(INPUT_POST, 'action');
        $args = filter_input(INPUT_POST, 'args');
        if (!$snapin) {
            throw new Exception(
                _('A snapin name is required!')
            );
        }
        if ($this->obj->get('name') != $snapin
            && self::getClass('SnapinManager')->exists(
                $snapin,
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
        if (!$snapinfile && $_FILES['snapinfile']['error'] > 0) {
            throw new UploadException($_FILES['snapinfile']['error']);
        }
        $src = sprintf(
            '%s/%s',
            dirname($_FILES['snapinfile']['tmp_name']),
            basename($_FILES['snapinfile']['tmp_name'])
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
                $StorageNode->get('snapinpath'), '/'
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
            ->set('name', $snapin)
            ->set('description', $description)
            ->set('packtype', $packtype)
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
     * Display snapin storage groups.
     *
     * @return void
     */
    public function snapinStoragegroups()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=snapin-storagegroups" ';

        echo '<!-- Storage Groups -->';
        echo '<div class="box-group" id="storagegroups">';
        // =================================================================
        // Associated Storage Groups
        $buttons = self::makeButton(
            'storagegroups-primary',
            _('Update Primary Group'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroups-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'storagegroups-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Storage Group Name'),
            _('Storage Group Primary'),
            _('Storage Group Associated')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="updatestoragegroups" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'snapin-storagegroups-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Snapin storage groups post.
     *
     * @return void
     */
    public function snapinStoragegroupsPost()
    {
        if (isset($_POST['updatestoragegroups'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'storagegroups' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['storagegroups'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->addGroup($storagegroup);
            }
        }
        if (isset($_POST['storagegroupdel'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'storagegroupRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['storagegroupRemove'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->removeGroup($storagegroup);
            }
        }
        if (isset($_POST['primarysel'])) {
            $primary = filter_input(
                INPUT_POST,
                'primary'
            );
            self::getClass('SnapinGroupAssociationManager')->update(
                [
                    'snapinID' => $this->obj->get('id'),
                    'primary' => '1'
                ],
                '',
                [
                    'primary' => '0'
                ]
            );
            if ($primary) {
                self::getClass('SnapinGroupAssociationManager')->update(
                    [
                        'snapinID' => $this->obj->get('id'),
                        'storagegroupID' => $primary
                    ],
                    '',
                    [
                        'primary' => '1'
                    ]
                );
            }
        }
    }
    /**
     * Snapin Membership tab
     *
     * @return void
     */
    public function snapinMembership()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=snapin-membership" ';

        echo '<!-- Host Membership -->';
        echo '<div class="box-group" id="membership">';
        // =================================================================
        // Associated Hosts
        $buttons = self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Host Name'),
            _('Host Associated')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="updatemembership" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'snapin-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Snapin membership post elements
     *
     * @return void
     */
    public function snapinMembershipPost()
    {
        if (isset($_POST['updatemembership'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membership' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membership'];
            $this->obj->addHost($membership);
        }
        if (isset($_POST['membershipdel'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membershipRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membershipRemove'];
            self::getClass('SnapinAssociationManager')->destroy(
                [
                    'snapinID' => $this->obj->get('id'),
                    'hostID' => $membership
                ]
            );
        }
    }
    /**
     * Edit this snapin
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        $tabData[] = [
            'name' => _('General'),
            'id' => 'snapin-general',
            'generator' => function() {
                $this->snapinGeneral();
            }
        ];

        $tabData[] = [
            'name' => _('Storage Groups'),
            'id' => 'snapin-storagegroups',
            'generator' => function() {
                $this->snapinStoragegroups();
            }
        ];

        $tabData[] = [
            'name' => _('Host Membership'),
            'id' => 'snapin-membership',
            'generator' => function() {
                $this->snapinMembership();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * Submit for update.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'SNAPIN_EDIT_POST',
            ['Snapin' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'snapin-general':
                $this->snapinGeneralPost();
                break;
            case 'snapin-storagegroups':
                $this->snapinStoragegroupsPost();
                break;
            case 'snapin-membership':
                $this->snapinMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Snapin update failed!'));
            }
            $code = 201;
            $hook = 'SNAPIN_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Snapin updated!'),
                    'title' => _('Snapin Update Success')
                ]
            );
        } catch (Exception $e) {
            self::$FOGFTP->close();
            $code = ($serverFalt ? 500 : 400);
            $hook = 'SNAPIN_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Update Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager->processEvent(
            $hook,
            ['obj' => &$this->obj]
        );
        echo $msg;
        exit;
    }
    /**
     * Presents the storage groups list table.
     *
     * @return void
     */
    public function getStoragegroupsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $where = "`snapins`.`sID` = '"
            . $this->obj->get('id')
            . "'";

        // Workable Queries
        $storagegroupsSqlStr = "SELECT `%s`,"
            . "`sgaSnapinID` as `origID`,IF (`sgaSnapinID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `sgaSnapinID`,`sgaPrimary`,`sID`
            FROM `%s`
            CROSS JOIN `snapins`
            LEFT OUTER JOIN `snapinGroupAssoc`
            ON `nfsGroups`.`ngID` = `snapinGroupAssoc`.`sgaStorageGroupID`
            AND `snapins`.`sID` = `snapinGroupAssoc`.`sgaSnapinID`
            %s
            %s
            %s";
        $storagegroupsFilterStr = "SELECT COUNT(`%s`),"
            . "`sgaSnapinID` AS `origID`,IF (`sgaSnapinID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `sgaSnapinID`,`sgaPrimary`,`sID`
            FROM `%s`
            CROSS JOIN `snapins`
            LEFT OUTER JOIN `snapinGroupAssoc`
            ON `nfsGroups`.`ngID` = `snapinGroupAssoc`.`sgaStorageGroupID`
            AND `snapins`.`sID` = `snapinGroupAssoc`.`sgaSnapinID`
            %s";
        $storagegroupsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('StorageGroupManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'sgaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'sgaSnapinID',
            'dt' => 'association'
        ];
        $columns[] = [
            'db' => 'origID',
            'dt' => 'origID',
            'removeFromQuery' => true
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'nfsGroups',
                'ngID',
                $columns,
                $storagegroupsSqlStr,
                $storagegroupsFilterStr,
                $storagegroupsTotalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Snapin -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostsSqlStr = "SELECT `%s`,"
            . "IF(`saSnapinID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saSnapinID`
            FROM `%s`
            CROSS JOIN `snapins`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `hosts`.`hostID` = `snapinAssoc`.`saHostID`
            %s
            %s
            %s";
        $hostsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`saSnapinID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saSnapinID`
            FROM `%s`
            CROSS JOIN `snapins`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `hosts`.`hostID` = `snapinAssoc`.`saHostID`
            %s";
        $hostsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
        }
        $columns[] = [
            'db' => 'saSnapinID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'hosts',
                'hostID',
                $columns,
                $hostsSqlStr,
                $hostsFilterStr,
                $hostsTotalStr
            )
        );
        exit;
    }
}
