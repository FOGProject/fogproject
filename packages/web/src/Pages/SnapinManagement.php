<?php
/**
 * Snapin management page
 *
 * PHP version 7.4+
 *
 * @category SnapinManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Exception\SnapinSaveException;
use FOG\Exception\UploadException;
use FOG\Items\Snapin;
use FOG\Items\StorageGroup;
use FOG\Managers\FileDeleteQueueManager;
use FOG\Managers\SnapinGroupAssociationManager;
use FOG\Managers\SnapinManager;
use FOG\Managers\StorageGroupManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
        'Mono' => ['mono', '', ''],
        // GH-356. The client always injects the downloaded snapin file as a
        // quoted positional argument BETWEEN runWithArgs and args, so a
        // template can only work if the uploaded file is something the tool
        // consumes positionally. For Chocolatey that is a packages.config:
        // `choco install <pkg|packages.config>`, where any argument ending
        // in .config is read as a package list and may be an absolute path.
        // Installing from a .nupkg path is deprecated upstream, so the
        // config file is the shape that keeps working.
        'Chocolatey (packages.config)' => [
            '%ProgramData%\\chocolatey\\bin\\choco.exe',
            'install',
            '-y -r --no-progress'
        ]
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
        $this->name = _('Snapin Management');
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
            '<select class="form-control packnotemplate d-none" '
            . 'name="argTypes" id="argTypes">'
            . '<option value="">- %s -</option>',
            _('Please select an option')
        );
        foreach (self::$_argTypes as $type => &$cmd) {
            printf(
                '<option value="%s" rwargs="%s" args="%s">%s</option>',
                \Initiator::e($cmd[0]),
                \Initiator::e($cmd[1]),
                \Initiator::e($cmd[2]),
                \Initiator::e($type)
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
            _('Batch Script') => [
                'cmd.exe',
                '/c &quot;[FOG_SNAPIN_PATH]\\MyScript.bat&quot;'
            ],
            _('Bash Script') => [
                '/bin/bash',
                '&quot;[FOG_SNAPIN_PATH]/MyScript.sh&quot;'
            ],
            _('VB Script') => [
                'cscript.exe',
                '&quot;[FOG_SNAPIN_PATH]\\MyScript.vbs&quot;'
            ],
            _('PowerShell Script') => [
                'powershell.exe',
                '-ExecutionPolicy Bypass -NoProfile -File &quot;'
                .'[FOG_SNAPIN_PATH]\\MyScript.ps1&quot;'
            ],
            _('PowerShell x64 Script') => [
                '&quot;%WINDIR%\\sysnative\\windowspowershell'
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
            // GH-356. A pack unzips on the client and nothing is appended
            // to the command, so here Chocolatey can be given package names
            // -- which is what makes the unpacked directory usable as an
            // offline `--source` holding the .nupkg files from the archive.
            _('Chocolatey (offline source)') => [
                '%ProgramData%\\chocolatey\\bin\\choco.exe',
                'install MyPackage --source=&quot;[FOG_SNAPIN_PATH]&quot; '
                . '-y -r --no-progress'
            ],
        ];
        ob_start();
        printf(
            '<select class="form-control packtemplate d-none" '
            . 'id="packTypes">'
            . '<option value="">- %s -</option>',
            _('Please select an option')
        );
        foreach ($args as $type => &$cmd) {
            printf(
                '<option file="%s" args="%s">%s</option>',
                \Initiator::e($cmd[0]),
                (
                    isset($cmd[1]) ?
                    \Initiator::e($cmd[1]) :
                    ''
                ),
                \Initiator::e($type)
            );
            unset($cmd);
        }
        echo '</select>';
        return ob_get_clean();
    }
    /**
     * Short inline-help snippets for the Snapin Pack fields.
     *
     * Keyed so _addFields() (create) and snapinGeneral() (edit) render
     * identical help. The 'packargs' note carries the packtemplate class
     * so the shared initSnapinCommandUI() toggle reveals it only when
     * "Snapin Pack" is selected.
     *
     * @return array
     */
    private static function _packHelp()
    {
        return [
            'type' => '<p class="form-text">'
                . _(
                    'Normal runs a single uploaded file. A Snapin Pack '
                    . 'unzips an uploaded archive on the client, then runs '
                    . 'a file from inside it.'
                )
                . '</p>',
            'template' => '<p class="form-text">'
                . _(
                    'Optional. Pick a type to pre-fill the command fields '
                    . 'below; you can still edit them afterward. The '
                    . 'Chocolatey entry expects the uploaded file to be a '
                    . 'Chocolatey packages.config naming the packages to '
                    . 'install.'
                )
                . '</p>',
            'packargs' => '<p class="form-text packtemplate d-none">'
                . _(
                    'Use [FOG_SNAPIN_PATH] in the arguments to point at the '
                    . 'folder where the pack is unzipped on the client.'
                )
                . '</p>',
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $snapin = filter_input(INPUT_POST, 'snapin');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $snapinfileexist = basename(
            (string)filter_input(INPUT_POST, 'snapinfileexist')
        );
        $packtype = (int)filter_input(INPUT_POST, 'packtype');
        $rw = filter_input(INPUT_POST, 'rw');
        $rwa = filter_input(INPUT_POST, 'rwa');
        $args = filter_input(INPUT_POST, 'args');
        $timeout = filter_input(INPUT_POST, 'timeout');
        $returnCodes = filter_input(INPUT_POST, 'returnCodes');
        if ($storagegroup > 0) {
            $sgID = $storagegroup;
        } else {
            $sgID = @min(Route::getIds('storagegroup', false));
        }
        $StorageGroup = new StorageGroup($sgID);
        $StorageGroups = (new StorageGroupManager())
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
            . '"snapinfileexist-input cmdlet3 form-control fog-select2" '
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

        $labelClass = 'col-sm-3 col-form-label';
        $help = self::_packHelp();

        return [
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
            ) => $packtypes . $help['type'],
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'argTypes',
                _('Snapin Template')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'packTypes',
                _('Snapin Pack Template')
            ) => self::$_template1 . self::$_template2 . $help['template'],
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'snaprw',
                _('Snapin Run With')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'snaprw',
                _('Snapin Pack Run With')
            ) => self::makeInput(
                'form-control snapinrw-input cmdlet1',
                'rw',
                '',
                'text',
                'snaprw',
                $rw
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'snaprwa',
                _('Snapin Run With Argument')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'snaprwa',
                _('Snapin Pack Arguments')
            ) => self::makeInput(
                'form-control snapinrwa-input cmdlet2',
                'rwa',
                '',
                'text',
                'snaprwa',
                $rwa
            ) . $help['packargs'],
            self::makeLabel(
                $labelClass,
                'snapinfile',
                _('Snapin File')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'snapinfile',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'snapinfile',
                    '',
                    'file',
                    'snapinfile',
                    ''
                )
            ) . self::makeInput(
                'form-control filedisp cmdlet3',
                '',
                '',
                'text',
                'snapinfiledisp',
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
                $labelClass . ' packnotemplate d-none',
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
                'returnCodes',
                _('Return Codes')
            ) => self::makeTextarea(
                'form-control snapinreturncodes-input',
                'returnCodes',
                "0=success\n1707=success\n3010=reboot\n1641=reboot\n1618=retry",
                'returnCodes',
                $returnCodes,
                false,
                false,
                'rows="5"'
            )
            . '<p class="form-text">'
            . _(
                'One per line, code=class. Classes: success, reboot '
                . '(installed, reboot to finish), retry (try again next '
                . 'check-in), failed. Empty uses the defaults shown; any '
                . 'code not listed is failed. The defaults are Windows '
                . 'codes: Linux and macOS keep only the low 8 bits of an '
                . 'exit status, so list the code the program can return.'
            )
            . '</p>',
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
                'snapincmd',
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
    }
    /**
     * The form to display when adding a new snapin
     * definition.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'snapin',
            _('Create New Snapin'),
            'SNAPIN_ADD_FIELDS',
            'Snapin',
            null,
            'multipart/form-data'
        );
    }
    /**
     * The form to display when adding a new snapin
     * definition.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'snapin',
            'SNAPIN_ADD_FIELDS',
            'Snapin',
            null,
            'multipart/form-data'
        );
    }
    /**
     * Actually sibmit the creation of the snapin.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('SNAPIN_ADD_POST');

        $serverFault = false;
        $Snapin = null;
        try {
            $Snapin = Snapin::uploadAndCreate($_POST, $_FILES);
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'SNAPIN_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Snapin added!'),
                    'title' => _('Snapin Create Success')
                ]
            );
        } catch (SnapinSaveException $e) {
            $serverFault = true;
            $code = HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            $hook = 'SNAPIN_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Snapin Create Fail')
                ]
            );
        } catch (\Exception $e) {
            // Legacy UI behavior: SSH/SFTP RuntimeExceptions and
            // InvalidArgumentException both map to HTTP 400 here.
            // Route::createSnapinWithFile maps them differently.
            // See docs/adr/0001-api-ui-http-status-divergence.md.
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
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
        // Mirrors handleAddPost(): fire the hook, then attach the created
        // object so a caller can act on the result without a second request.
        // This endpoint is hand-rolled rather than using that helper (the file
        // upload does not fit the closure), so the behavior is repeated here
        // deliberately -- a create should answer the same way whichever
        // scaffold built it.
        $args = [
            'Snapin' => &$Snapin,
            'hook' => &$hook,
            'code' => &$code,
            'msg' => &$msg,
            'serverFault' => &$serverFault
        ];
        self::$HookManager->processEvent($hook, $args);
        $msg = self::attachCreatedObject($msg, 'Snapin', $Snapin);
        $this->jsonSend($code, $msg);
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
            (string)filter_input(INPUT_POST, 'snapinfileexist') ?:
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
        $reboot = $shutdown = $noaction = '';
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
        $returnCodes = (
            filter_input(INPUT_POST, 'returnCodes') ?:
            $this->obj->get('returnCodes')
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
            . '"snapinfileexist-input cmdlet3 form-control fog-select2" '
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

        $labelClass = 'col-sm-3 col-form-label';
        $help = self::_packHelp();

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
            ) => $packtypes . $help['type'],
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'argTypes',
                _('Snapin Template')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'packTypes',
                _('Snapin Pack Template')
            ) => self::$_template1 . self::$_template2 . $help['template'],
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'snaprw',
                _('Snapin Run With')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'snaprw',
                _('Snapin Pack Run With')
            ) => self::makeInput(
                'form-control snapinrw-input cmdlet1',
                'rw',
                '',
                'text',
                'snaprw',
                $rw
            ),
            self::makeLabel(
                $labelClass . ' packnotemplate d-none',
                'snaprwa',
                _('Snapin Run With Argument')
            )
            . self::makeLabel(
                $labelClass . ' packtemplate d-none',
                'snaprwa',
                _('Snapin Pack Arguments')
            ) => self::makeInput(
                'form-control snapinrwa-input cmdlet2',
                'rwa',
                '',
                'text',
                'snaprwa',
                $rwa
            ) . $help['packargs'],
            self::makeLabel(
                $labelClass,
                'snapinfile',
                _('Snapin File')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'snapinfile',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'snapinfile',
                    '',
                    'file',
                    'snapinfile',
                    ''
                )
            ) . self::makeInput(
                'form-control filedisp cmdlet3',
                '',
                '',
                'text',
                'snapinfiledisp',
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
                $labelClass . ' packnotemplate d-none',
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
                'returnCodes',
                _('Return Codes')
            ) => self::makeTextarea(
                'form-control snapinreturncodes-input',
                'returnCodes',
                "0=success\n1707=success\n3010=reboot\n1641=reboot\n1618=retry",
                'returnCodes',
                $returnCodes,
                false,
                false,
                'rows="5"'
            )
            . '<p class="form-text">'
            . _(
                'One per line, code=class. Classes: success, reboot '
                . '(installed, reboot to finish), retry (try again next '
                . 'check-in), failed. Empty uses the defaults shown; any '
                . 'code not listed is failed. The defaults are Windows '
                . 'codes: Linux and macOS keep only the low 8 bits of an '
                . 'exit status, so list the code the program can return.'
            )
            . '</p>',
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
                'snapincmd',
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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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

        $this->renderGeneralForm(
            'snapin',
            $rendered,
            $buttons,
            'multipart/form-data'
        );
    }
    /**
     * Snapin General Post
     *
     * @return void
     */
    public function snapinGeneralPost()
    {
        self::checkAuthAndCSRF();
        $snapin = trim((string)filter_input(INPUT_POST, 'snapin'));
        $description = trim((string)filter_input(INPUT_POST, 'description'));
        $packtype = trim((string)filter_input(INPUT_POST, 'packtype'));
        $runWith = trim((string)filter_input(INPUT_POST, 'rw'));
        $runWithArgs = trim((string)filter_input(INPUT_POST, 'rwa'));
        $snapinfile = basename(
            trim((string)filter_input(INPUT_POST, 'snapinfileexist'))
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
        $timeout = trim((string)filter_input(INPUT_POST, 'timeout'));
        $returnCodes = trim((string)filter_input(INPUT_POST, 'returnCodes'));
        $action = trim((string)filter_input(INPUT_POST, 'action'));
        $args = trim((string)filter_input(INPUT_POST, 'args'));

        $exists = (new SnapinManager())
            ->exists($snapin);
        if ($snapin != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A snapin already exists with this name!')
            );
        }
        if (!$snapinfile) {
            throw new \Exception(
                sprintf(
                    '%s, %s, %s!',
                    _('A file'),
                    _('either already selected or uploaded'),
                    _('must be specified')
                )
            );
        }
        // Same chokepoint as Snapin::uploadAndCreate() -- rejects the
        // reserved 'ssl' pattern and the '.'/'..' names that used to
        // point $dest at the snapin directory itself (035 / 2.3.1).
        // Throws InvalidArgumentException, which handleEditPost's
        // catch (Exception) already surfaces to the user.
        $snapinfile = Snapin::sanitizeSnapinFileName($snapinfile);
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
                throw new \Exception(
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
                    throw new \Exception(
                        _('Failed to add snapin')
                        . ' ' . $rdir . ' '
                        . _('does not exist and cannot be created')
                    );
                }
            }
            if (self::$FOGSSH->exists($dest)) {
                // Non-recursive removal only; delete() would walk the
                // directory when the unlink fails (035 / 2.3.1).
                if (!self::$FOGSSH->unlinkFile($dest)) {
                    throw new \Exception(
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
                $othersnapins = Route::getList(
                    'snapin',
                    ['file' => $this->obj->get('file')]
                );
                $otherfiles = [];
                foreach ($othersnapins as $osnapin) {
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
                            self::storageNow(),
                            self::getQueuedState(),
                            self::$FOGUser->get('name'),
                            $storagegroupID
                        ];
                    }
                    (new FileDeleteQueueManager())->insertBatch(
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
            ->set('timeout', $timeout)
            ->set('returnCodes', $returnCodes);
    }
    /**
     * Display snapin storage groups.
     *
     * @return void
     */
    public function snapinStoragegroups()
    {
        // Storage Group Associations
        $this->renderAssocTab(
            'snapin-storagegroup',
            _('Snapin Storage Group Associations'),
            _('Storage Group Name'),
            'storagegroup',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'snapin-storagegroup',
                $this->obj->get('id')
            )
            . '" ';

        // Primary Storage Group
        $buttons = self::makeButton(
            'snapin-storagegroup-primary-send',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-info card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Snapin Primary Storage Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<span id="storagegroupselector"></span>';
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
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
            (new SnapinGroupAssociationManager())->update(
                [
                    'snapinID' => $this->obj->get('id'),
                    'storagegroupID' => $storagegroups,
                    'primary' => '1'
                ],
                '',
                ['primary' => '0']
            );
            if ($primary) {
                (new SnapinGroupAssociationManager())->update(
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
        $this->renderAssocTab(
            'snapin-host',
            _('Snapin Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update host.
     *
     * @return void
     */
    public function snapinHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Edit this snapin
     *
     * @return void
     */
    public function edit()
    {
        $this->notes = [
            _('Snapin') => $this->obj->get('name'),
            _('File') => $this->obj->get('file'),
            _('Filesize') => self::formatByteSize($this->obj->get('size'))
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('Snapin') => '#snapin'
        ];
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Submit for update.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Snapin',
            'SNAPIN_EDIT',
            _('Snapin updated!'),
            _('Snapin Update Success'),
            _('Snapin Update Fail'),
            function (&$serverFault) {
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
                    throw new \Exception(_('Snapin update failed!'));
                }
            }
        );
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
        return $this->assocItemsList(
            'host',
            'snapinassociation',
            'snapinAssoc',
            '`hosts`.`hostID`',
            '`snapinAssoc`.`saHostID`',
            '`snapinAssoc`.`saSnapinID`',
            [
                [
                    'db' => 'snapinAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
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
        $storagegroupsAssigned = Route::getIds(
            'snapingroupassociation',
            ['snapinID' => $this->obj->get('id')],
            'storagegroupID'
        );
        if (!count($storagegroupsAssigned ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No storagegroups assigned to this snapin'),
                    'disablebtn' => true
                ]
            ));
        }
        // getNames(): names() answers with its rows under a `data`
        // envelope, and this wants the rows.
        $storagegroupNames = Route::getNames(
            'storagegroup',
            ['id' => $storagegroupsAssigned]
        );
        foreach ($storagegroupNames as &$storagegroup) {
            $storagegroups[$storagegroup->id] = $storagegroup->name;
            unset($storagegroup);
        }
        unset($storagegroupNames);
        $primarystoragegroup = Route::getIds(
            'snapingroupassociation',
            [
                'snapinID' => $this->obj->get('id'),
                'primary' => '1'
            ],
            'storagegroupID'
        );
        $primarystoragegroup = array_shift($primarystoragegroup);
        $storagegroupSelector = self::selectForm(
            'storagegroup',
            $storagegroups,
            $primarystoragegroup,
            true,
            '',
            true
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $storagegroupSelector,
                'disablebtn' => false
            ]
        ));
    }
}
