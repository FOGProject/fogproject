<?php
/**
 * Software management page
 *
 * PHP version 7.4+
 *
 * @category SoftwareManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Items\Software;
use FOG\Managers\SoftwareManager;
use FOG\Router\Route;

/**
 * Software management page
 *
 * Software is desired state, not a task: an entry says a package from a
 * backend (Chocolatey today) should be present (at a version policy) or
 * absent on a host, and the agent converges toward it and reports the
 * truth back (design 0003). This page is the CRUD surface for that entry,
 * modeled on SnapinManagement/PrinterManagement but with none of the
 * snapin's file-upload, storage-group, pack-type, run-with, reboot/
 * shutdown, replicate, hide or template machinery -- none of that applies
 * to a package a manager installs by name.
 *
 * @category SoftwareManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareManagement extends FOGPage
{
    /**
     * The node this page operates off of.
     *
     * @var string
     */
    public $node = 'software';
    /**
     * Initializes the software page class.
     *
     * @param string $name the name to pass
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Software Management');
        parent::__construct($name);
        $this->headerData = [
            _('Name'),
            _('Backend'),
            _('Package'),
            _('Version'),
            _('State'),
            _('Enabled')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Which UI value a stored `version` corresponds to.
     *
     * The inverse of _resolveVersion(): '' is any version, the literal
     * 'latest' tracks the source, and anything else is a pinned version
     * string -- the form shows that string back in the pinned input.
     *
     * @param string $version the stored version value
     *
     * @return string 'latest', 'pinned', or '' (any version)
     */
    private static function _versionPolicyFor($version)
    {
        if ('latest' === $version) {
            return 'latest';
        }
        if ('' === (string)$version) {
            return '';
        }
        return 'pinned';
    }
    /**
     * Resolves the posted version policy and its companion text field into
     * the single `version` value the model stores.
     *
     * @param string $versionPolicy 'latest', 'pinned', or '' (any version)
     * @param string $version       the pinned-version text field, only
     *                              meaningful when $versionPolicy is 'pinned'
     *
     * @throws \Exception when 'pinned' carries no version
     *
     * @return string the value to store in `version`
     */
    private function _resolveVersion($versionPolicy, $version)
    {
        switch ($versionPolicy) {
            case 'latest':
                return 'latest';
            case 'pinned':
                $version = trim((string)$version);
                if ('' === $version) {
                    throw new \Exception(
                        _('A pinned entry needs a version.')
                    );
                }
                return $version;
            default:
                return '';
        }
    }
    /**
     * Validates the fields common to add and edit before anything is set
     * on the object.
     *
     * @param string $name    the software name
     * @param string $package the package id
     * @param mixed  $timeout the posted timeout
     * @param string $state   the posted state
     * @param string $backend the posted backend
     *
     * @throws \Exception on the first thing wrong
     *
     * @return void
     */
    private function _validateSoftwarePost($name, $package, $timeout, $state, $backend)
    {
        if ('' === $name) {
            throw new \Exception(_('Please enter a software name.'));
        }
        if ('' === $package) {
            throw new \Exception(_('Please enter a package id.'));
        }
        if (false === filter_var(
            $timeout,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        )) {
            throw new \Exception(
                _('Timeout must be a whole number of seconds, zero or more.')
            );
        }
        if (!in_array($state, Software::STATES, true)) {
            throw new \Exception(_('Please select a valid state.'));
        }
        if (!array_key_exists($backend, Software::BACKENDS)) {
            throw new \Exception(_('Please select a valid backend.'));
        }
    }
    /**
     * Builds the field list shared by the create form and the General edit
     * tab -- the only difference between the two is where $v's values come
     * from (POST defaults for create, POST-or-object for edit).
     *
     * @param array $v values keyed name/description/backend/package/
     *                 versionPolicy/version/state/source/args/timeout/
     *                 returnCodes/enabledChecked
     *
     * @return array
     */
    private function _fieldsFor(array $v)
    {
        $labelClass = 'col-sm-3 col-form-label';

        $stateOptions = [
            'present' => _('Present'),
            'absent' => _('Absent')
        ];
        $versionPolicyOptions = [
            '' => _('Any version'),
            'latest' => _('Latest (upgrade at each check)'),
            'pinned' => _('Pinned')
        ];

        return [
            self::makeLabel(
                $labelClass,
                'software',
                _('Name')
            ) => self::makeInput(
                'form-control softwarename-input',
                'software',
                _('Software Name'),
                'text',
                'software',
                $v['name'],
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Description')
            ) => self::makeTextarea(
                'form-control softwaredescription-input',
                'description',
                _('Description'),
                'description',
                $v['description']
            ),
            self::makeLabel(
                $labelClass,
                'backend',
                _('Backend')
            ) => self::selectForm(
                'backend',
                Software::BACKENDS,
                $v['backend'],
                true
            ),
            self::makeLabel(
                $labelClass,
                'package',
                _('Package')
            ) => self::makeInput(
                'form-control softwarepackage-input',
                'package',
                'googlechrome',
                'text',
                'package',
                $v['package'],
                true
            )
            . '<p class="form-text">'
            . _('The id the package manager knows, e.g. googlechrome.')
            . '</p>',
            self::makeLabel(
                $labelClass,
                'versionPolicy',
                _('Version policy')
            ) => self::selectForm(
                'versionPolicy',
                $versionPolicyOptions,
                $v['versionPolicy'],
                true
            )
            . '<div class="softwareversion-pinned'
            . ('pinned' === $v['versionPolicy'] ? '' : ' d-none')
            . '">'
            . self::makeInput(
                'form-control softwareversion-input',
                'version',
                '1.2.3',
                'text',
                'version',
                $v['version']
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'state',
                _('State')
            ) => self::selectForm(
                'state',
                $stateOptions,
                $v['state'],
                true
            )
            . '<p class="form-text">'
            . _('Absent removes the package if it is installed.')
            . '</p>',
            self::makeLabel(
                $labelClass,
                'source',
                _('Source')
            ) => self::makeInput(
                'form-control softwaresource-input',
                'source',
                '',
                'text',
                'source',
                $v['source']
            )
            . '<p class="form-text">'
            . _(
                'Optional. A feed URL, folder or share passed to the '
                . 'package manager as --source. Leave empty for the '
                . 'manager\'s own configured sources.'
            )
            . '</p>',
            self::makeLabel(
                $labelClass,
                'args',
                _('Extra arguments')
            ) => self::makeInput(
                'form-control softwareargs-input',
                'args',
                '',
                'text',
                'args',
                $v['args']
            ),
            self::makeLabel(
                $labelClass,
                'timeout',
                _('Timeout')
                . '<br/>('
                . _('in seconds')
                . ')'
            ) => self::makeInput(
                'form-control softwaretimeout-input',
                'timeout',
                '900',
                'number',
                'timeout',
                $v['timeout']
            ),
            self::makeLabel(
                $labelClass,
                'returnCodes',
                _('Return Codes')
            ) => self::makeTextarea(
                'form-control softwarereturncodes-input',
                'returnCodes',
                "0=success\n1707=success\n3010=reboot\n1641=reboot\n"
                . "350=reboot\n1618=retry",
                'returnCodes',
                $v['returnCodes'],
                false,
                false,
                'rows="5"'
            )
            . '<p class="form-text">'
            . _(
                'One per line, code=class. Classes: success, reboot '
                . '(installed, reboot to finish), retry (try again at '
                . 'the next check), failed. Empty uses the defaults '
                . 'shown; any code not listed is failed. The defaults '
                . 'are Windows codes: Linux and macOS keep only the low '
                . '8 bits of an exit status, so list the code the '
                . 'program can return.'
            )
            . '</p>',
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Enabled')
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
                $v['enabledChecked']
            )
            . '<p class="form-text">'
            . _(
                'A disabled entry stops being managed; it does not '
                . 'remove the package.'
            )
            . '</p>'
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        return $this->_fieldsFor(
            [
                'name' => filter_input(INPUT_POST, 'software'),
                'description' => filter_input(INPUT_POST, 'description'),
                'backend' => (
                    filter_input(INPUT_POST, 'backend') ?:
                    array_key_first(Software::BACKENDS)
                ),
                'package' => filter_input(INPUT_POST, 'package'),
                'versionPolicy' => (string)filter_input(
                    INPUT_POST,
                    'versionPolicy'
                ),
                'version' => filter_input(INPUT_POST, 'version'),
                'state' => (filter_input(INPUT_POST, 'state') ?: 'present'),
                'source' => filter_input(INPUT_POST, 'source'),
                'args' => filter_input(INPUT_POST, 'args'),
                'timeout' => (filter_input(INPUT_POST, 'timeout') ?: '900'),
                'returnCodes' => filter_input(INPUT_POST, 'returnCodes'),
                'enabledChecked' => 'checked'
            ]
        );
    }
    /**
     * The form to display when adding a new software definition.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'software',
            _('Create New Software'),
            'SOFTWARE_ADD_FIELDS',
            'Software'
        );
    }
    /**
     * The form to display when adding a new software definition, for the
     * association-tab create modal.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'software',
            'SOFTWARE_ADD_FIELDS',
            'Software'
        );
    }
    /**
     * Actually submit the creation of the software entry.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Software',
            'SOFTWARE_ADD',
            _('Software added!'),
            _('Software Create Success'),
            _('Software Create Fail'),
            function (&$serverFault) {
                $name = trim((string)filter_input(INPUT_POST, 'software'));
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $backend = trim((string)filter_input(INPUT_POST, 'backend'));
                $package = trim((string)filter_input(INPUT_POST, 'package'));
                $versionPolicy = trim(
                    (string)filter_input(INPUT_POST, 'versionPolicy')
                );
                $version = filter_input(INPUT_POST, 'version');
                $state = trim((string)filter_input(INPUT_POST, 'state'));
                $source = trim((string)filter_input(INPUT_POST, 'source'));
                $args = trim((string)filter_input(INPUT_POST, 'args'));
                $timeout = filter_input(INPUT_POST, 'timeout');
                $returnCodes = trim(
                    (string)filter_input(INPUT_POST, 'returnCodes')
                );
                $isEnabled = (int)isset($_POST['isEnabled']);

                $this->_validateSoftwarePost(
                    $name,
                    $package,
                    $timeout,
                    $state,
                    $backend
                );
                if ((new SoftwareManager())->exists($name)) {
                    throw new \Exception(
                        _('A software entry already exists with this name!')
                    );
                }
                $version = $this->_resolveVersion($versionPolicy, $version);

                $Software = (new Software())
                    ->set('name', $name)
                    ->set('description', $description)
                    ->set('backend', $backend)
                    ->set('package', $package)
                    ->set('version', $version)
                    ->set('state', $state)
                    ->set('source', $source)
                    ->set('args', $args)
                    ->set('timeout', (int)$timeout)
                    ->set('returnCodes', $returnCodes)
                    ->set('isEnabled', $isEnabled)
                    ->set('createdBy', self::$FOGUser->get('name'));
                if (!$Software->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add software failed!'));
                }
                return $Software;
            }
        );
    }
    /**
     * Display software general edit elements.
     *
     * @return void
     */
    public function softwareGeneral()
    {
        $versionPolicy = (
            filter_input(INPUT_POST, 'versionPolicy') ?:
            self::_versionPolicyFor($this->obj->get('version'))
        );
        $version = (
            filter_input(INPUT_POST, 'version') ?:
            $this->obj->get('version')
        );
        $isEnabled = (int)isset($_POST['isEnabled']) ?: $this->obj->get('isEnabled');

        $fields = $this->_fieldsFor(
            [
                'name' => (
                    filter_input(INPUT_POST, 'software') ?:
                    $this->obj->get('name')
                ),
                'description' => (
                    filter_input(INPUT_POST, 'description') ?:
                    $this->obj->get('description')
                ),
                'backend' => (
                    filter_input(INPUT_POST, 'backend') ?:
                    $this->obj->get('backend')
                ),
                'package' => (
                    filter_input(INPUT_POST, 'package') ?:
                    $this->obj->get('package')
                ),
                'versionPolicy' => $versionPolicy,
                // The pinned text only means something under that policy --
                // 'latest' and '' both store their policy name/empty string
                // verbatim in `version`, and showing that back in the text
                // field would read as a pinned value that was never pinned.
                'version' => ('pinned' === $versionPolicy ? $version : ''),
                'state' => (
                    filter_input(INPUT_POST, 'state') ?:
                    $this->obj->get('state')
                ),
                'source' => (
                    filter_input(INPUT_POST, 'source') ?:
                    $this->obj->get('source')
                ),
                'args' => (
                    filter_input(INPUT_POST, 'args') ?:
                    $this->obj->get('args')
                ),
                'timeout' => (
                    filter_input(INPUT_POST, 'timeout') ?:
                    $this->obj->get('timeout')
                ),
                'returnCodes' => (
                    filter_input(INPUT_POST, 'returnCodes') ?:
                    $this->obj->get('returnCodes')
                ),
                'enabledChecked' => ($isEnabled ? 'checked' : '')
            ]
        );

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
            'SOFTWARE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Software' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm(
            'software',
            $rendered,
            $buttons
        );
    }
    /**
     * Software General Post
     *
     * @return void
     */
    public function softwareGeneralPost()
    {
        $name = trim((string)filter_input(INPUT_POST, 'software'));
        $description = trim((string)filter_input(INPUT_POST, 'description'));
        $backend = trim((string)filter_input(INPUT_POST, 'backend'));
        $package = trim((string)filter_input(INPUT_POST, 'package'));
        $versionPolicy = trim(
            (string)filter_input(INPUT_POST, 'versionPolicy')
        );
        $version = filter_input(INPUT_POST, 'version');
        $state = trim((string)filter_input(INPUT_POST, 'state'));
        $source = trim((string)filter_input(INPUT_POST, 'source'));
        $args = trim((string)filter_input(INPUT_POST, 'args'));
        $timeout = filter_input(INPUT_POST, 'timeout');
        $returnCodes = trim((string)filter_input(INPUT_POST, 'returnCodes'));
        $isEnabled = (int)isset($_POST['isEnabled']);

        $this->_validateSoftwarePost(
            $name,
            $package,
            $timeout,
            $state,
            $backend
        );
        $exists = (new SoftwareManager())->exists($name);
        if ($name != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A software entry already exists with this name!')
            );
        }
        $version = $this->_resolveVersion($versionPolicy, $version);

        $this->obj
            ->set('name', $name)
            ->set('description', $description)
            ->set('backend', $backend)
            ->set('package', $package)
            ->set('version', $version)
            ->set('state', $state)
            ->set('source', $source)
            ->set('args', $args)
            ->set('timeout', (int)$timeout)
            ->set('returnCodes', $returnCodes)
            ->set('isEnabled', $isEnabled);
    }
    /**
     * Present the hosts list.
     *
     * @return void
     */
    public function softwareHosts()
    {
        $this->renderAssocTab(
            'software-host',
            _('Software Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update host.
     *
     * @return void
     */
    public function softwareHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Present the reported status across hosts for this software entry.
     *
     * @return void
     */
    public function softwareStatus()
    {
        $this->renderHistoryTab(
            [
                _('Host'),
                _('Installed'),
                _('Status'),
                _('Exit code'),
                _('Checked'),
                _('Details')
            ],
            [
                [],
                [],
                [],
                [],
                [],
                []
            ],
            _('Software Status'),
            'software-status-table'
        );
    }
    /**
     * Edit this software entry.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'software-general',
            'generator' => function () {
                $this->softwareGeneral();
            }
        ];

        // Hosts
        $tabData[] = [
            'name' => _('Hosts'),
            'id' => 'software-host',
            'generator' => function () {
                $this->softwareHosts();
            }
        ];

        // Status
        $tabData[] = [
            'name' => _('Status'),
            'id' => 'software-status',
            'generator' => function () {
                $this->softwareStatus();
            }
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
            'Software',
            'SOFTWARE_EDIT',
            _('Software updated!'),
            _('Software Update Success'),
            _('Software Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'software-general':
                        $this->softwareGeneralPost();
                        break;
                    case 'software-host':
                        $this->softwareHostPost();
                        break;
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Software update failed!'));
                }
            }
        );
    }
    /**
     * Software -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        $this->assocItemsList(
            'host',
            'softwareassociation',
            'softwareAssoc',
            '`hosts`.`hostID`',
            '`softwareAssoc`.`swaHostID`',
            '`softwareAssoc`.`swaSoftwareID`',
            [
                [
                    'db' => 'softwareAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * The reported softwareStatus rows for this entry, across hosts.
     *
     * @return void
     */
    public function getStatusList()
    {
        header('Content-type: application/json');
        Route::listem(
            'softwarestatus',
            ['softwareID' => $this->obj->get('id')]
        );
        echo Route::getData();
        exit;
    }
}
