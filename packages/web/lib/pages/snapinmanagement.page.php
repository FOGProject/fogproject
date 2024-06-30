<?php
/**
 * Snapin management page
 *
 * PHP version 5
 *
 * @category SnapinManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin management page
 *
 * @category SnapinManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinManagement extends FOGPage
{
    /**
     * Arg types for snapin template
     *
     * @var array
     */
    private static $_argTypes = [
        'MSI' => ['msiexec.exe','/i','/quiet'],
        'Batch Script' => ['cmd.exe','/c', ''],
        'Bash Script' => ['/bin/bash', '', ''],
        'VB Script' => ['cscript.exe', '', ''],
        'Powershell (default)' => [
            'powershell.exe',
            '-ExecutionPolicy Bypass -NoProfile -File',
            ''
        ],
        'Powershell x64' => [
            '&quot;%SYSTEMROOT%\\sysnative\\windowspowershell\\v1.0\\powershell.exe&quot;',
            '-ExecutionPolicy Bypass -NoProfile -File',
            ''
        ],
        'Mono' => ['mono', '', '']
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
         * Start a new buffer (last one ended anyway)
         * to create our template non-pack.
         */
        ob_start();
        printf(
            '<select class="form-control packnotemplate hidden" '
            . 'name="argTypes" id="argTypes">'
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
        self::$_template2 = $this->_maker();
        $this->headerData = [
            _('Snapin Name'),
            _('Protected'),
            _('Enabled'),
            _('Is Pack')
        ];
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
                '/i &quot;[FOG_SNAPIN_PATH]\\MyMSI.msi&quot;'
            ],
            'MSI + MST' => [
                'msiexec.exe',
                '/i &quot;[FOG_SNAPIN_PATH]\\MyMST.mst&quot;'
            ],
            'Batch Script' => [
                'cmd.exe',
                '/c &quot;[FOG_SNAPIN_PATH]\\MyScript.bat&quot;'
            ],
            'Bash Script' => [
                '/bin/bash',
                '&quot;[FOG_SNAPIN_PATH]/MyScript.sh&quot;'
            ],
            'VB Script' => [
                'cscript.exe',
                '&quot;[FOG_SNAPIN_PATH]\\MyScript.vbs&quot;'
            ],
            'PowerShell Script' => [
                'powershell.exe',
                '-ExecutionPolicy Bypass -NoProfile -File &quot;'
                .'[FOG_SNAPIN_PATH]\\MyScript.ps1&quot;'
            ],
            'PowerShell x64 Script' => [
                'powershell.exe &quot;%WINDIR%\\sysnative\\windowspowershell'
                . '\\v1.0\\powershell.exe&quot;',
                '-ExecutionPolicy Bypass -NoProfile -File &quot;'
                .'[FOG_SNAPIN_PATH]\\MyScript.ps1&quot;'
            ],
            'EXE' => [
                '[FOG_SNAPIN_PATH]\\MyFile.exe'
            ],
            'Mono' => [
                'mono',
                '&quot;[FOG_SNAPIN_PATH]/MyFile.exe&quot;'
            ],
        ];
        ob_start();
        printf(
            '<select class="form-control packtemplate hidden" '
            . 'id="packTypes">'
            . '<option value="">- %s -</option>',
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
        $timeout = filter_input(INPUT_POST, 'timeout');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            Route::ids('storagegroup', false);
            $sgID = @min(json_decode(Route::getData(), true));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox($sgID, '', 'id');
        self::$selected = '';
        self::$selected = $snapinfileexist;
        $filelist = [];
        $StorageNode = $StorageGroup->getMasterStorageNode();
        $filelist = $StorageNode->get('snapinfiles');
        natcasesort($filelist);
        $filelist = array_values(
            array_unique(
                array_filter($filelist)
            )
        );
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'snapin',
                _('Snapin Name')
            ) => self::makeInput(
                'form-control snapinname-input',
                'snapin',
                _('Snapin Name'),
                'text',
                'snapin',
                $snapin,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Snapin Description')
            ) => self::makeTextarea(
                'form-control snapindescription-input',
                'description',
                _('Snapin Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group')
            ) => $StorageGroups,
            self::makeLabel(
                $labelClass,
                'snapinpack',
                _('Snapin Type')
            ) => $packtypes,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'argTypes',
                _('Snapin Template')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'packTypes',
                _('Snapin Pack Template')
            ) => self::$_template1 . self::$_template2,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprw',
                _('Snapin Run With')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprw',
                _('Snapin Pack File')
            ) => self::makeInput(
                'form-control snapinrw-input cmdlet1',
                'rw',
                '',
                'text',
                'snaprw',
                $rw
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprwa',
                _('Snapin Run With Argument')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprwa',
                _('Snapin Pack Arguments')
            ) => self::makeInput(
                'form-control snapinrwa-input cmdlet2',
                'rwa',
                '',
                'text',
                'snaprwa',
                $rwa
            ),
            self::makeLabel(
                $labelClass,
                'snapinfile',
                _('Snapin File')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                'snapinfile',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'snapinfile',
                    '',
                    'file',
                    'snapinfile',
                    ''
                ) . '</span>'
            ) . self::makeInput(
                'form-control filedisp cmdlet3',
                '',
                '',
                'text',
                '',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>',
            (
                count($filelist) > 0 ?
                self::makeLabel(
                    $labelClass,
                    'snapinfileexist',
                    _('Snapin File (exists)')
                ) :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'args',
                _('Snapin Arguments')
            ) => self::makeInput(
                'form-control snapinargs-input packnotemplate cmdlet4',
                'args',
                '',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Snapin Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'toReplicate',
                _('Snapin Replicate')
            ) => self::makeInput(
                '',
                'toReplicate',
                '',
                'checkbox',
                'toReplicate',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'isHidden',
                _('Snapin Arguments Hidden')
            ) => self::makeInput(
                '',
                'isHidden',
                '',
                'checkbox',
                'isHidden'
            ),
            self::makeLabel(
                $labelClass,
                'timeout',
                _('Snapin Timeout')
                . '<br/>('
                . _('in seconds')
                . ')'
            ) => self::makeInput(
                'form-control snapintimeout-input',
                'timeout',
                '0',
                'number',
                'timeout',
                $timeout
            ),
            self::makeLabel(
                $labelClass,
                'noaction',
                _('No Action')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'noaction',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'reboot',
                _('Reboot')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'reboot',
                'reboot'
            ),
            self::makeLabel(
                $labelClass,
                'shutdown',
                _('Shutdown')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'shutdown',
                'shutdown'
            ),
            self::makeLabel(
                $labelClass,
                'cmdletin',
                _('Snapin Command')
                . '<br/>('
                . _('read-only')
                . ')'
            ) => self::makeTextarea(
                'form-control snapincmd',
                'snapincmd',
                '',
                'snapincmd',
                '',
                false,
                false,
                '',
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'SNAPIN_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Snapin' => self::getClass('Snapin')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'snapin-create-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="box box-solid" id="snapin-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Snapin');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The form to display when adding a new snapin
     * definition.
     *
     * @return void
     */
    public function addModal()
    {
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
        $timeout = filter_input(INPUT_POST, 'timeout');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            Route::ids('storagegroup', false);
            $sgID = @min(json_decode(Route::getData(), true));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = self::getClass('StorageGroupManager')
            ->buildSelectBox($sgID, '', 'id');
        self::$selected = '';
        self::$selected = $snapinfileexist;
        $filelist = [];
        $StorageNode = $StorageGroup->getMasterStorageNode();
        $filelist = $StorageNode->get('snapinfiles');
        natcasesort($filelist);
        $filelist = array_values(
            array_unique(
                array_filter($filelist)
            )
        );
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'snapin',
                _('Snapin Name')
            ) => self::makeInput(
                'form-control snapinname-input',
                'snapin',
                _('Snapin Name'),
                'text',
                'snapin',
                $snapin,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Snapin Description')
            ) => self::makeTextarea(
                'form-control snapindescription-input',
                'description',
                _('Snapin Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group')
            ) => $StorageGroups,
            self::makeLabel(
                $labelClass,
                'snapinpack',
                _('Snapin Type')
            ) => $packtypes,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'argTypes',
                _('Snapin Template')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'packTypes',
                _('Snapin Pack Template')
            ) => self::$_template1 . self::$_template2,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprw',
                _('Snapin Run With')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprw',
                _('Snapin Pack File')
            ) => self::makeInput(
                'form-control snapinrw-input cmdlet1',
                'rw',
                '',
                'text',
                'snaprw',
                $rw
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprwa',
                _('Snapin Run With Argument')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprwa',
                _('Snapin Pack Arguments')
            ) => self::makeInput(
                'form-control snapinrwa-input cmdlet2',
                'rwa',
                '',
                'text',
                'snaprwa',
                $rwa
            ),
            self::makeLabel(
                $labelClass,
                'snapinfile',
                _('Snapin File')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                'snapinfile',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'snapinfile',
                    '',
                    'file',
                    'snapinfile',
                    ''
                ) . '</span>'
            ) . self::makeInput(
                'form-control filedisp cmdlet3',
                '',
                '',
                'text',
                '',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>',
            (
                count($filelist) > 0 ?
                self::makeLabel(
                    $labelClass,
                    'snapinfileexist',
                    _('Snapin File (exists)')
                ) :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'args',
                _('Snapin Arguments')
            ) => self::makeInput(
                'form-control snapinargs-input packnotemplate cmdlet4',
                'args',
                '',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Snapin Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'toReplicate',
                _('Snapin Replicate')
            ) => self::makeInput(
                '',
                'toReplicate',
                '',
                'checkbox',
                'toReplicate',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'isHidden',
                _('Snapin Arguments Hidden')
            ) => self::makeInput(
                '',
                'isHidden',
                '',
                'checkbox',
                'isHidden'
            ),
            self::makeLabel(
                $labelClass,
                'timeout',
                _('Snapin Timeout')
                . '<br/>('
                . _('in seconds')
                . ')'
            ) => self::makeInput(
                'form-control snapintimeout-input',
                'timeout',
                '0',
                'number',
                'timeout',
                $timeout
            ),
            self::makeLabel(
                $labelClass,
                'noaction',
                _('No Action')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'noaction',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'reboot',
                _('Reboot')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'reboot',
                'reboot'
            ),
            self::makeLabel(
                $labelClass,
                'shutdown',
                _('Shutdown')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'shutdown',
                'shutdown'
            ),
            self::makeLabel(
                $labelClass,
                'cmdletin',
                _('Snapin Command')
                . '<br/>('
                . _('read-only')
                . ')'
            ) => self::makeTextarea(
                'form-control snapincmd',
                'snapincmd',
                '',
                'snapincmd',
                '',
                false,
                false,
                '',
                true
            )
        ];

        self::$HookManager->processEvent(
            'SNAPIN_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Snapin' => self::getClass('Snapin')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=snapin&sub=add',
            'post',
            'multipart/form-data',
            true
        );
        echo $rendered;
        echo '</form>';
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
            filter_input(INPUT_POST, 'snapin')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $packtype = trim(
            filter_input(INPUT_POST, 'packtype')
        );
        $runWith = trim(
            filter_input(INPUT_POST, 'rw')
        );
        $runWithArgs = trim(
            filter_input(INPUT_POST, 'rwa')
        );
        $storagegroup = (int)trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $snapinfile = basename(
            trim(
                filter_input(INPUT_POST, 'snapinfileexist')
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
        $tiemout = trim(
            filter_input(INPUT_POST, 'timeout')
        );
        $action = trim(
            filter_input(INPUT_POST, 'action')
        );
        $args = trim(
            filter_input(INPUT_POST, 'args')
        );
        $timeout = trim(
            filter_input(INPUT_POST, 'timeout')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('SnapinManager')
                ->exists($snapin);
            if ($exists) {
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
            $snapinfile = preg_replace('/[^\-\w\.]+/', '_', $snapinfile);
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
            if ($uploadfile) {
                $hash = hash_file('sha512', $src);
                $size = self::getFilesize($src);
                self::$FOGSSH->username = $StorageNode->get('user');
                self::$FOGSSH->password = $StorageNode->get('pass');
                self::$FOGSSH->host = $StorageNode->get('ip');
                if (!self::$FOGSSH->connect()) {
                    throw new Exception(
                        sprintf(
                            '%s: %s: %s.',
                            _('Storage Node'),
                            $StorageNode->get('ip'),
                            _('SSH Connection has failed')
                        )
                    );
                }
                self::$FOGSSH->sftp();
                $rdir = $StorageNode->get('snapinpath');
                if (!self::$FOGSSH->exists($rdir)) {
                    if (false === self::$FOGSSH->sftp_mkdir($rdir)) {
                        throw new Exception(
                            _('Failed to add snapin')
                            . ' ' . $rdir . ' '
                            . _('does not exist and cannot be created')
                        );
                    }
                }
                if (self::$FOGSSH->exists($dest)) {
                    if (!self::$FOGSSH->delete($dest)) {
                        throw new Exception(
                            _('Failed to delete existing snapin file')
                        );
                    }
                }
                self::$FOGSSH->put($src, $dest);
                self::$FOGSSH->disconnect();
            }
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
            Snapin::setPrimaryGroup($storagegroup, $Snapin->get('id'));
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'SNAPIN_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Snapin added!'),
                    'title' => _('Snapin Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SNAPIN_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=snapin&sub=edit&id='
        //    . $Snapin->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Snapin' => &$Snapin,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
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
        $snapin = (
            filter_input(INPUT_POST, 'snapin') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $packtype = (
            (int)filter_input(INPUT_POST, 'packtype') ?:
            $this->obj->get('packtype')
        );
        $snapinfileexists = basename(
            filter_input(INPUT_POST, 'snapinfileexist') ?:
            $this->obj->get('file')
        );
        $rw = (
            filter_input(INPUT_POST, 'rw') ?:
            $this->obj->get('runWith')
        );
        $rwa = (
            filter_input(INPUT_POST, 'rwa') ?:
            $this->obj->get('runWithArgs')
        );
        $protected = (
            (int)isset($_POST['protected']) ?:
            $this->obj->get('protected')
        );
        $toReplicate = (
            (int)isset($_POST['toReplicate']) ?:
            $this->obj->get('toReplicate')
        );
        $isEnabled = (
            (int)isset($_POST['isEnabled']) ?:
            $this->obj->get('isEnabled')
        );
        $isHidden = (
            (int)isset($_POST['isHidden']) ?:
            $this->obj->get('hide')
        );
        $ishid = ($isHidden ? 'checked' : '');
        $isprot = ($protected ? 'checked' : '');
        $isen = ($isEnabled ? 'checked' : '');
        $isrep = ($toReplicate ? 'checked' : '');
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
            $reboot = 'checked';
            break;
        case 'shutdown':
            $shutdown = 'checked';
            break;
        default:
            $noaction = 'checked';
        }
        $args = (
            filter_input(INPUT_POST, 'args') ?:
            $this->obj->get('args')
        );
        $timeout = (
            filter_input(INPUT_POST, 'timeout') ?:
            $this->obj->get('timeout')
        );

        self::$selected = $snapinfileexists;
        $StorageGroup = $this->obj->getStorageGroup();
        $StorageNode = $StorageGroup->getMasterStorageNode();
        $filelist = $StorageNode->get('snapinfiles');
        $filelist = array_values(
            array_unique(
                array_filter(
                    $filelist
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'snapin',
                _('Snapin Name')
            ) => self::makeInput(
                'form-control snapinname-input',
                'snapin',
                _('Snapin Name'),
                'text',
                'snapin',
                $snapin,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Snapin Description')
            ) => self::makeTextarea(
                'form-control snapindescription-input',
                'description',
                _('Snapin Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'snapinpack',
                _('Snapin Type')
            ) => $packtypes,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'argTypes',
                _('Snapin Template')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'packTypes',
                _('Snapin Pack Template')
            ) => self::$_template1 . self::$_template2,
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprw',
                _('Snapin Run With')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprw',
                _('Snapin Pack File')
            ) => self::makeInput(
                'form-control snapinrw-input cmdlet1',
                'rw',
                '',
                'text',
                'snaprw',
                $rw
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'snaprwa',
                _('Snapin Run With Argument')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate hidden',
                'snaprwa',
                _('Snapin Pack Arguments')
            ) => self::makeInput(
                'form-control snapinrwa-input cmdlet2',
                'rwa',
                '',
                'text',
                'snaprwa',
                $rwa
            ),
            self::makeLabel(
                $labelClass,
                'snapinfile',
                _('Snapin File')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                'snapinfile',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'snapinfile',
                    '',
                    'file',
                    'snapinfile',
                    ''
                ) . '</span>'
            ) . self::makeInput(
                'form-control filedisp cmdlet3',
                '',
                '',
                'text',
                '',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>',
            (
                count($filelist) > 0 ?
                self::makeLabel(
                    $labelClass,
                    'snapinfileexist',
                    _('Snapin File (exists)')
                ) :
                ''
            ) => (
                count($filelist) > 0 ?
                $selectFiles :
                ''
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate hidden',
                'args',
                _('Snapin Arguments')
            ) => self::makeInput(
                'form-control snapinargs-input packnotemplate cmdlet4',
                'args',
                '',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'protected',
                _('Snapin Protected')
            ) => self::makeInput(
                '',
                'protected',
                '',
                'checkbox',
                'protected',
                '',
                false,
                false,
                -1,
                -1,
                $isprot
            ),
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Snapin Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                $isen
            ),
            self::makeLabel(
                $labelClass,
                'toReplicate',
                _('Snapin Replicate')
            ) => self::makeInput(
                '',
                'toReplicate',
                '',
                'checkbox',
                'toReplicate',
                '',
                false,
                false,
                -1,
                -1,
                $isrep
            ),
            self::makeLabel(
                $labelClass,
                'isHidden',
                _('Snapin Arguments Hidden')
            ) => self::makeInput(
                '',
                'isHidden',
                '',
                'checkbox',
                'isHidden',
                '',
                false,
                false,
                -1,
                -1,
                $ishid
            ),
            self::makeLabel(
                $labelClass,
                'timeout',
                _('Snapin Timeout')
                . '<br/>('
                . _('in seconds')
                . ')'
            ) => self::makeInput(
                'form-control snapintimeout-input',
                'timeout',
                '0',
                'number',
                'timeout',
                $timeout
            ),
            self::makeLabel(
                $labelClass,
                'noaction',
                _('No Action')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'noaction',
                '',
                false,
                false,
                -1,
                -1,
                $noaction
            ),
            self::makeLabel(
                $labelClass,
                'reboot',
                _('Reboot')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'reboot',
                'reboot',
                false,
                false,
                -1,
                -1,
                $reboot
            ),
            self::makeLabel(
                $labelClass,
                'shutdown',
                _('Shutdown')
            ) => self::makeInput(
                '',
                'action',
                '',
                'radio',
                'shutdown',
                'shutdown',
                false,
                false,
                -1,
                -1,
                $shutdown
            ),
            self::makeLabel(
                $labelClass,
                'cmdletin',
                _('Snapin Command')
                . '<br/>('
                . _('read-only')
                . ')'
            ) => self::makeTextarea(
                'form-control snapincmd',
                'snapincmd',
                '',
                'snapincmd',
                '',
                false,
                false,
                '',
                true
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'SNAPIN_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Snapin' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'snapin-general-form',
            self::makeTabUpdateURL(
                'snapin-general',
                $this->obj->get('id')
            ),
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $this->deleteModal();
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
        $snapin = trim(filter_input(INPUT_POST, 'snapin'));
        $description = trim(filter_input(INPUT_POST, 'description'));
        $packtype = trim(filter_input(INPUT_POST, 'packtype'));
        $runWith = trim(filter_input(INPUT_POST, 'rw'));
        $runWithArgs = trim(filter_input(INPUT_POST, 'rwa'));
        $snapinfile = basename(
            trim(filter_input(INPUT_POST, 'snapinfileexist'))
        );
        $uploadfile = basename(
            trim($_FILES['snapinfile']['name'])
        );
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $protected = (int)isset($_POST['protected']);
        $isEnabled = (int)isset($_POST['isEnabled']);
        $toReplicate = (int)isset($_POST['toReplicate']);
        $hide = (int)isset($_POST['isHidden']);
        $timeout = trim(filter_input(INPUT_POST, 'timeout'));
        $action = trim(filter_input(INPUT_POST, 'action'));
        $args = trim(filter_input(INPUT_POST, 'args'));

        $exists = self::getClass('SnapinManager')
            ->exists($snapin);
        if ($snapin != $this->obj->get('name')
            && $exists
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
        $snapinfile = preg_replace('/[^\-\w\.]+/', '_', $snapinfile);
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
        $destpath = sprintf(
            '/%s',
            trim(
                $StorageNode->get('snapinpath'),
                '/'
            )
        );
        $dest = $destpath . '/' . $snapinfile;
        if ($uploadfile) {
            // * We must remove the prexisting file to overwrite
            // * So the only way is to phsyically delete it
            // * unforutnately.
            self::$FOGSSH->username = $StorageNode->get('user');
            self::$FOGSSH->password = $StorageNode->get('pass');
            self::$FOGSSH->host = $StorageNode->get('ip');
            if (!self::$FOGSSH->connect()) {
                throw new Exception(
                    sprintf(
                        '%s: %s: %s.',
                        _('Storage Node'),
                        $StorageNode->get('ip'),
                        _('SSH Connection has failed')
                    )
                );
            }
            self::$FOGSSH->sftp();
            $rdir = $StorageNode->get('snapinpath');
            if (!self::$FOGSSH->exists($rdir)) {
                if (false === self::$FOGSSH->sftp_mkdir($rdir)) {
                    throw new Exception(
                        _('Failed to add snapin')
                        . ' ' . $rdir . ' '
                        . _('does not exist and cannot be created')
                    );
                }
            }
            if (self::$FOGSSH->exists($dest)) {
                if (!self::$FOGSSH->delete($dest)) {
                    throw new Exception(
                        _('Failed to delete existing snapin file')
                    );
                }
            }
            self::$FOGSSH->put($src, $dest);
            self::$FOGSSH->disconnect();
            if ($snapinfile != $this->obj->get('file')) {
                // * At least here we can queue it
                // * So it could be stopped before
                // * Its actually deleted.
                Route::listem(
                    'snapin',
                    ['file' => $this->obj->get('file')]
                );
                $othersnapins = json_decode(
                    Route::getData()
                );
                $otherfiles = [];
                foreach ($othersnapins->data as $osnapin) {
                    if ($osnapin->id == $this->obj->get('id')) {
                        continue;
                    }
                    $otherfiles[] = $osnapin->file;
                }
                if (count($otherfiles ?: []) <= 0) {
                    $insert_fields = [
                        'path',
                        'pathtype',
                        'createdTime',
                        'stateID',
                        'createdBy',
                        'storagegroupID'
                    ];
                    $insert_values = [];
                    foreach ($this->obj->get('storagegroups') as $storagegroupID) {
                        $insert_values[] = [
                            $this->obj->get('file'),
                            'Snapin',
                            self::formatTime('now', 'Y-m-d H:i:s'),
                            self::getQueuedState(),
                            self::$FOGUser->get('name'),
                            $storagegroupID
                        ];
                    }
                    self::getClass('filedeletequeuemanager')->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
                }
            }
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
        // Storage Group Associations
        $this->headerData = [
            _('Storage Group Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'snapin-storagegroup',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'snapin-storagegroup-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'snapin-storagegroup-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Snapin Storage Group Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'snapin-storagegroup-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('storagegroup');
        echo '</div>';
        echo '</div>';

        // Primary Storage Group
        $buttons = self::makeButton(
            'snapin-storagegroup-primary-send',
            _('Update'),
            'btn btn-info pull-right',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Snapin Primary Storage Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<span id="storagegroupselector"></span>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Snapin storage groups post.
     *
     * @return void
     */
    public function snapinStoragegroupPost()
    {
        if (isset($_POST['confirmadd'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['additems'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->addGroup($storagegroup);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $storagegroup = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $storagegroup = $storagegroup['remitems'];
            if (count($storagegroup ?: []) > 0) {
                $this->obj->removeGroup($storagegroup);
            }
        }
        if (isset($_POST['confirmprimary'])) {
            $primary = filter_input(
                INPUT_POST,
                'primary'
            );
            $storagegroups = array_diff(
                $this->obj->get('storagegroups'),
                [$primary]
            );
            self::getClass('SnapinGroupAssociationManager')->update(
                [
                    'snapinID' => $this->obj->get('id'),
                    'storagegroupID' => $storagegroups,
                    'primary' => '1'
                ],
                '',
                ['primary' => '0']
            );
            if ($primary) {
                self::getClass('SnapinGroupAssociationManager')->update(
                    [
                        'snapinID' => $this->obj->get('id'),
                        'storagegroupID' => $primary,
                        'primary' => ['0', '']
                    ],
                    '',
                    ['primary' => 1]
                );
            }
        }
    }
    /**
     * Present the hosts list.
     *
     * @return void
     */
    public function snapinHosts()
    {
        $this->headerData = [
            _('Host Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'snapin-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'snapin-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'snapin-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Snapin Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'snapin-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update host.
     *
     * @return void
     */
    public function snapinHostPost()
    {
        if (isset($_POST['confirmadd'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            if (count($hosts ?: [])) {
                $this->obj->addHost($hosts);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: [])) {
                $this->obj->removeHost($hosts);
            }
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

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'snapin-general',
            'generator' => function () {
                $this->snapinGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Hosts'),
                        'id' => 'snapin-host',
                        'generator' => function () {
                            $this->snapinHosts();
                        }
                    ],
                    [
                        'name' => _('Storage Groups'),
                        'id' => 'snapin-storagegroup',
                        'generator' => function () {
                            $this->snapinStoragegroups();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
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
            case 'snapin-storagegroup':
                $this->snapinStoragegroupPost();
                break;
            case 'snapin-host':
                $this->snapinHostPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Snapin update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'SNAPIN_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Snapin updated!'),
                    'title' => _('Snapin Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SNAPIN_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'Snapin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
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
        $join = [
            'LEFT OUTER JOIN `snapinGroupAssoc` ON '
            . "`nfsGroups`.`ngID` = `snapinGroupAssoc`.`sgaStorageGroupID`"
            . "AND `snapinGroupAssoc`.`sgaSnapinID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'sgaStorageGroupID',
            'dt' => 'origID'
        ];
        $columns[] = [
            'db' => 'sgaPrimary',
            'dt' => 'primary'
        ];
        $columns[] = [
            'db' => 'snapinAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'storagegroup',
            'snapingroupassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Snapin -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        $join = [
            'LEFT OUTER JOIN `snapinAssoc` ON '
            . "`hosts`.`hostID` = `snapinAssoc`.`saHostID`"
            . "AND `snapinAssoc`.`saSnapinID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'snapinAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'snapinassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Gets the storage group selector for setting primary storage groups.
     *
     * @return string
     */
    public function getSnapinPrimaryStoragegroups()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        Route::ids(
            'snapingroupassociation',
            ['snapinID' => $this->obj->get('id')],
            'storagegroupID'
        );
        $storagegroupsAssigned = json_decode(Route::getData(), true);
        if (!count($storagegroupsAssigned ?: [])) {
            echo json_encode(
                [
                    'content' => _('No storagegroups assigned to this snapin'),
                    'disablebtn' => true
                ]
            );
            exit;
        }
        Route::names(
            'storagegroup',
            ['id' => $storagegroupsAssigned]
        );
        $storagegroupNames = json_decode(Route::getData());
        foreach ($storagegroupNames as &$storagegroup) {
            $storagegroups[$storagegroup->id] = $storagegroup->name;
            unset($storagegroup);
        }
        unset($storagegroupNames);
        Route::ids(
            'snapingroupassociation',
            [
                'snapinID' => $this->obj->get('id'),
                'primary' => '1'
            ],
            'storagegroupID'
        );
        $primarystoragegroup = json_decode(Route::getData(), true);
        $primarystoragegroup = array_shift($primarystoragegroup);
        $storagegroupSelector = self::selectForm(
            'storagegroup',
            $storagegroups,
            $primarystoragegroup,
            true,
            '',
            true
        );
        echo json_encode(
            [
                'content' => $storagegroupSelector,
                'disablebtn' => false
            ]
        );
        exit;
    }
}
