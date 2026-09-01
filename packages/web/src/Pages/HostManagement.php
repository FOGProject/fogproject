<?php
/**
 * Host management page
 *
 * PHP version 7.4+
 *
 * The host represented to the GUI
 *
 * @category HostManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Audit\Audit;
use FOG\Auth\Authorization;
use FOG\Base\FOGPage;
use FOG\Boot\SecureBootState;
use FOG\Items\Architecture;
use FOG\Items\Group;
use FOG\Items\Host;
use FOG\Items\HostAutoLogout;
use FOG\Items\MACAddress;
use FOG\Items\Setting;
use FOG\Items\TaskType;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;
use FOG\Util\FOGCron;
use FOG\Util\MassEdit;

/**
 * Host management page
 *
 * The host represented to the GUI
 *
 * @category HostManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostManagement extends FOGPage
{
    /**
     * The node that uses this class.
     *
     * @var string
     */
    public $node = 'host';
    /**
     * Initializes the host page
     *
     * @param string $name the name to construct with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Host Management');
        parent::__construct($this->name);
        if (!($this->obj instanceof Host && $this->obj->isValid())) {
            $this->exitNorm = filter_input(INPUT_POST, 'bootTypeExit');
            $this->exitEfi = filter_input(INPUT_POST, 'efiBootTypeExit');
        } else {
            // If the host doesn't have a token
            // or the tasking has completed and the token
            // was already checked out but not used and cleared
            // clear it.
            if (
                !$this->obj->get('token')
                || (
                    $this->obj->get('tokenlock')
                    && !$this->obj->get('task')->isValid()
                )
            ) {
                self::getClass('HostManager')->update(
                    ['id' => $this->obj->get('id')],
                    '',
                    [
                        'token' => self::createSecToken(),
                        'tokenlock' => false
                    ]
                );
            }
            $this->exitNorm = (
                filter_input(INPUT_POST, 'bootTypeExit') ?:
                ($this->obj->get('biosexit') ?: '')
            );
            $this->exitEfi = (
                filter_input(INPUT_POST, 'efiBootTypeExit') ?:
                ($this->obj->get('efiexit') ?: '')
            );
        }
        $this->exitNorm = Setting::buildExitSelector(
            'bootTypeExit',
            $this->exitNorm,
            true,
            'bootTypeExit'
        );
        $this->exitEfi = Setting::buildExitSelector(
            'efiBootTypeExit',
            $this->exitEfi,
            true,
            'efiBootTypeExit'
        );
        // Every header carries the DataTables `data` key of the column
        // behind it. fog.host.list.js builds its column list from these
        // rather than hardcoding one, because Ping Status below is
        // conditional on FOG_HOST_LOOKUP: with that setting off the table
        // had one fewer <th> than the JS had columns, and DataTables raises
        // "Incorrect column count" and never draws the grid. Deriving the
        // list from the header row means a conditional column takes its
        // entry with it and the two cannot fall out of step.
        $this->headerData = [
            _('Host'),
            _('Primary MAC')
        ];
        $this->attributes = [
            ['data-col' => 'mainlink'],
            ['data-col' => 'primac']
        ];
        if (self::$fogpingactive) {
            $this->headerData[] = _('Ping Status');
            $this->attributes[] = ['data-col' => 'pingstatus'];
        }
        array_push(
            $this->headerData,
            // The two halves of "when was this host last seen" (schema step
            // 353). Neither is gated on FOG_HOST_LOOKUP above: that setting
            // governs the live ping status cell, while these are the record
            // of when the host was last actually reached -- and a check-in
            // is the FOG client talking to us, which has nothing to do with
            // host lookup at all.
            _('Last Ping'),
            _('Last Check-In'),
            _('Imaged'),
            _('Assigned Image'),
            // Next to the assigned image on purpose: those two cells together
            // are the thing worth checking. The full comparison, including
            // the image's own architecture, is the Architectures page under
            // Image Management. See schema step 369.
            _('Architecture'),
            // The observed half of the Secure Boot ledger (schema step 376),
            // and the record of an enrollment having been performed (377).
            // Beside Architecture because they answer the same shape of
            // question -- what is this machine, and can the thing I am about
            // to schedule actually run on it.
            //
            // Both default to hidden in the column picker's sense of the
            // word only in that most fleets will not look at them daily;
            // they are emitted always, because the one time they matter is
            // when someone is picking enrollment targets and needs to sort by
            // them. Sorting is the filter here -- this grid has no
            // per-column search UI, so the global box matches the STORED
            // word ('disabled', 'setup') rather than the rendered label.
            _('Secure Boot'),
            _('SB Enrolled'),
            _('Description')
        );
        array_push(
            $this->attributes,
            ['data-col' => 'lastping'],
            ['data-col' => 'lastcheckin'],
            ['data-col' => 'deployed'],
            ['data-col' => 'imageLink'],
            ['data-col' => 'arch'],
            ['data-col' => 'sbstate'],
            ['data-col' => 'sbenrolled'],
            ['data-col' => 'description']
        );
    }
    /**
     * Lists the pending hosts
     *
     * @return false
     */
    public function pending()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('All Pending Hosts');

        // The pending grid is its own three-column table (see
        // fog.host.pending.js), so state it rather than deriving it from the
        // main list by index. It used to unset positions 2, 3 and 4 -- which
        // silently assumed FOG_HOST_LOOKUP was on, because with it off those
        // positions are Imaged/Assigned Image/Description and the wrong three
        // headers were dropped. Adding any column to index() moved them
        // again. DataTables raises "Incorrect column count" for a header with
        // no column behind it, so this has to agree with the JS exactly.
        $this->headerData = [
            _('Host'),
            _('Primary MAC'),
            _('Description')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        $buttons = self::makeButton(
            'approve',
            _('Approve selected'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger float-start'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-outline-secondary float-end'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns,
            '',
            'info'
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-outline-secondary float-end'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . self::makeInput(
                'form-control',
                'deletePassword',
                _('Password'),
                'password',
                'deletePassword',
                '',
                true
            )
            . '</div>',
            $modalDeleteBtns,
            '',
            'danger'
        );

        echo self::makeFormTag(
            '',
            'host-pending-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
        echo $approvalModal;
        echo $deleteModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually performs the update/delete actions
     *
     * @return void
     */
    public function pendingAjax()
    {
        header('Content-type: application/json');

        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            [
                'remitems' => $flags,
                'pending' => $flags
            ]
        );
        $remitems = $items['remitems'];
        $pending = $items['pending'];
        if (isset($_POST['confirmdel'])) {
            self::checkauth();
            Route::deletemass(
                'host',
                [
                    'id' => $remitems,
                    'pending' => 1
                ]
            );
        }
        if (isset($_POST['approvepending'])) {
            self::getClass('HostManager')->update(
                [
                    'id' => $pending,
                    'pending' => 1
                ],
                '',
                ['pending' => 0]
            );
            $this->jsonSend(HTTPResponseCodes::HTTP_ACCEPTED, json_encode(
                [
                    'msg' => _('Approved selected hosts!'),
                    'title' => _('Host Approval Success')
                ]
            ));
        }
    }
    /**
     * Lists the pending macs
     *
     * @return false
     */
    public function pendingMacs()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('All Pending MACs');

        $this->headerData = [
            _('Host Name'),
            _('MAC Address')
        ];
        $this->attributes = [
            [],
            []
        ];

        self::$HookManager->processEvent(
            'HOST_PENDING_MAC_DATA',
            [
                'attributes' => &$this->attributes,
                'headerData' => &$this->headerData
            ]
        );
        self::$HookManager->processEvent(
            'HOST_PENDING_MAC_HEADER_DATA',
            ['headerData' => &$this->headerData]
        );

        $buttons = self::makeButton(
            'approve',
            _('Approve selected'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger float-start'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-outline-secondary float-end'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns,
            '',
            'success'
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-outline-secondary float-end'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . self::makeInput(
                'form-control',
                'deletePassword',
                _('Password'),
                'password',
                'deletePassword',
                '',
                true
            )
            . '</div>',
            $modalDeleteBtns,
            '',
            'danger'
        );

        echo self::makeFormTag(
            '',
            'mac-pending-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
        //echo $buttons;
        echo $approvalModal;
        echo $deleteModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually performs the update/delete actions
     *
     * @return void
     */
    public function pendingMacsAjax()
    {
        header('Content-type: application/json');

        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            [
                'remitems' => $flags,
                'pending' => $flags
            ]
        );
        $remitems = $items['remitems'];
        $pending = $items['pending'];
        $serverFault = false;
        try {
            if (isset($_POST['confirmdel'])) {
                $errt = _('Delete MAC Fail');
                self::checkauth();
                self::$HookManager->processEvent(
                    'MULTI_REMOVE',
                    ['removing' => &$remitems]
                );
                Route::deletemass(
                    'macaddressassociation',
                    [
                        'id' => $remitems,
                        'pending' => 1
                    ]
                );
                $msg = json_encode(
                    [
                        'msg' => _('Successfully deleted'),
                        'title' => _('Delete Success')
                    ]
                );
                $code = HTTPResponseCodes::HTTP_SUCCESS;
            }
            if (isset($_POST['approvepending'])) {
                $errt = _('Approve MAC Fail');
                self::getClass('MACAddressAssociationManager')->update(
                    [
                        'id' => $pending,
                        'pending' => 1
                    ],
                    '',
                    ['pending' => 0]
                );
                $msg = json_encode(
                    [
                        'msg' => _('Approved selected macs!'),
                        'title' => _('MAC Approval Success')
                    ]
                );
                $code = HTTPResponseCodes::HTTP_ACCEPTED;
            }
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $errt
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Builds the enforce checkbox together with its explanatory help text.
     *
     * The group page states what this setting actually does in the header of
     * its own "Enforce Hostname | AD Join Reboots" card (groupModules()). The
     * host used to carry the same card on its Service Settings tab, but when
     * the control moved onto the General/Add field lists (9f0e1a3, to line Add
     * and Edit up) the card went with it -- and the description was the only
     * place either page explained the setting. A flat field row has no header
     * to hang that on, so the text rides under the checkbox instead. Keep the
     * wording byte-identical to groupModules() so the two pages say the same
     * thing and share one gettext msgid.
     *
     * @param mixed $enforce Truthy if the box should render checked.
     *
     * @return string
     */
    private function _enforceControl($enforce)
    {
        return self::makeInput(
            '',
            'enforce',
            '',
            'checkbox',
            'enforce',
            '',
            false,
            false,
            -1,
            -1,
            ($enforce ? 'checked' : '')
        )
        . '<p class="form-text help-block-tight">'
        . _(
            'This tells the client to force reboots for host name '
            . 'changing and AD Joining.'
        )
        . '</p>'
        . '<p class="form-text help-block-tight">'
        . _(
            'If disabled, the client will not make changes until all users '
            . 'are logged off'
        )
        . '</p>';
    }
    /**
     * Creates a new host.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Host');
        // Check all the post fields if they've already been set.
        $host = filter_input(INPUT_POST, 'host');
        $mac = filter_input(INPUT_POST, 'mac');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');
        $image = filter_input(INPUT_POST, 'image');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');
        $domain = filter_input(INPUT_POST, 'domain');
        $domainname = filter_input(INPUT_POST, 'domainname');
        $ou = filter_input(INPUT_POST, 'ou');
        $domainuser = filter_input(INPUT_POST, 'domainuser');
        $domainpassword = filter_input(INPUT_POST, 'domainpassword');
        $enforce = isset($_POST['enforce']) ?: self::getSetting(
            'FOG_ENFORCE_HOST_CHANGES'
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image, '', 'id');

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'host',
                _('Host Name')
            ) => self::makeInput(
                'form-control hostname-input',
                'host',
                _('Host Name'),
                'text',
                'host',
                $host,
                true,
                false,
                -1,
                15
            ),
            self::makeLabel(
                $labelClass,
                'mac',
                _('MAC Address')
            ) => self::makeInput(
                'form-control hostmac-input',
                'mac',
                '00:00:00:00:00:00',
                'text',
                'mac',
                $mac,
                true,
                false,
                -1,
                17,
                'exactlength="12"'
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Host Description')
            ) => self::makeTextarea(
                'form-control hostdescription-input',
                'description',
                _('Host Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Host Product Key')
            ) => self::makeInput(
                'form-control hostkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $key,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Host Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Host Kernel')
            ) => self::kernelFileSelect(
                'kernel',
                $kernel,
                'kernel',
                'form-control hostkernel-input',
                '',
                _('Use the default kernel')
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Host Kernel Arguments')
            ) => self::makeInput(
                'form-control hostargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Host Init')
            ) => self::kernelFileSelect(
                'init',
                $init,
                'init',
                'form-control hostinit-input',
                '',
                _('Use the default init')
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Host Primary Disk')
            ) => self::makeInput(
                'form-control hostdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Enforce Hostname | AD Join Reboots')
            ) => $this->_enforceControl($enforce),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Host BIOS Exit Type')
            ) => $this->exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Host EFI Exit Type')
            ) => $this->exitEfi
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'HOST_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => self::getClass('Host')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $fieldads = $this->adFieldsToDisplay(
            $domain,
            $domainname,
            $ou,
            $domainuser,
            $domainpassword,
            false,
            true
        );

        self::$HookManager->processEvent(
            'HOST_ADD_AD_FIELDS',
            [
                'fields' => &$fieldads,
                'Host' => self::getClass('Host')
            ]
        );
        $renderedad = self::formFields($fieldads);
        unset($fieldads);

        $this->renderCreateForm(
            'host',
            [
                [_('Create New Host'), $rendered],
                [_('Active Directory'), $renderedad]
            ],
            $buttons
        );
    }
    /**
     * Creates a new host.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'host',
            'HOST_ADD_FIELDS',
            'Host',
            null,
            'application/x-www-form-urlencoded',
            function () {
                $domain = filter_input(INPUT_POST, 'domain');
                $domainname = filter_input(INPUT_POST, 'domainname');
                $ou = filter_input(INPUT_POST, 'ou');
                $domainuser = filter_input(INPUT_POST, 'domainuser');
                $domainpassword = filter_input(INPUT_POST, 'domainpassword');

                $fieldads = $this->adFieldsToDisplay(
                    $domain,
                    $domainname,
                    $ou,
                    $domainuser,
                    $domainpassword,
                    false,
                    true
                );
                self::$HookManager->processEvent(
                    'HOST_ADD_AD_FIELDS',
                    [
                        'fields' => &$fieldads,
                        'Host' => self::getClass('Host')
                    ]
                );
                $renderedad = self::formFields($fieldads);
                unset($fieldads);

                return '<hr/>'
                    . '<h4 class="mt-2 mb-3">'
                    . _('Active Directory')
                    . '</h4>'
                    . $renderedad;
            }
        );
    }
    /**
     * Builds the create-host form fields (main section, sans AD).
     *
     * @return array
     */
    protected function _addFields()
    {
        // Check all the post fields if they've already been set.
        $host = filter_input(INPUT_POST, 'host');
        $mac = filter_input(INPUT_POST, 'mac');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');
        $image = filter_input(INPUT_POST, 'image');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');
        $enforce = isset($_POST['enforce']) ?: self::getSetting(
            'FOG_ENFORCE_HOST_CHANGES'
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image, '', 'id');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'host',
                _('Host Name')
            ) => self::makeInput(
                'form-control hostname-input',
                'host',
                _('Host Name'),
                'text',
                'host',
                $host,
                true,
                false,
                -1,
                15
            ),
            self::makeLabel(
                $labelClass,
                'mac',
                _('MAC Address')
            ) => self::makeInput(
                'form-control hostmac-input',
                'mac',
                '00:00:00:00:00:00',
                'text',
                'mac',
                $mac,
                true,
                false,
                -1,
                17,
                'exactlength="12"'
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Host Description')
            ) => self::makeTextarea(
                'form-control hostdescription-input',
                'description',
                _('Host Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Host Product Key')
            ) => self::makeInput(
                'form-control hostkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $key,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Host Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Host Kernel')
            ) => self::kernelFileSelect(
                'kernel',
                $kernel,
                'kernel',
                'form-control hostkernel-input',
                '',
                _('Use the default kernel')
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Host Kernel Arguments')
            ) => self::makeInput(
                'form-control hostargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Host Init')
            ) => self::kernelFileSelect(
                'init',
                $init,
                'init',
                'form-control hostinit-input',
                '',
                _('Use the default init')
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Host Primary Disk')
            ) => self::makeInput(
                'form-control hostdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Enforce Hostname | AD Join Reboots')
            ) => $this->_enforceControl($enforce),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Host BIOS Exit Type')
            ) => $this->exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Host EFI Exit Type')
            ) => $this->exitEfi
        ];
    }
    /**
     * Handles the forum submission process.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Host',
            'HOST_ADD',
            _('Host added!'),
            _('Host Create Success'),
            _('Host Create Fail'),
            function (&$serverFault) {
                $host = trim(
                    (string)filter_input(INPUT_POST, 'host')
                );
                $mac = trim(
                    (string)filter_input(INPUT_POST, 'mac')
                );
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $password = trim(
                    (string)filter_input(INPUT_POST, 'domainpassword')
                );
                $useAD = (int)isset($_POST['domain']);
                $domain = trim(
                    (string)filter_input(INPUT_POST, 'domainname')
                );
                $ou = trim(
                    (string)filter_input(INPUT_POST, 'ou')
                );
                $user = trim(
                    (string)filter_input(INPUT_POST, 'domainuser')
                );
                $pass = $password;
                $key = trim(
                    (string)filter_input(INPUT_POST, 'key')
                );
                $productKey = self::productKeyResolve($key, '');
                $enforce = filter_has_var(INPUT_POST, 'enforce') ? 1 : 0;
                $image = (int)filter_input(INPUT_POST, 'image');
                $kernel = trim(
                    (string)filter_input(INPUT_POST, 'kernel')
                );
                $kernelArgs = trim(
                    (string)filter_input(INPUT_POST, 'args')
                );
                $kernelDevice = trim(
                    (string)filter_input(INPUT_POST, 'dev')
                );
                $init = trim(
                    (string)filter_input(INPUT_POST, 'init')
                );
                $bootTypeExit = trim(
                    (string)filter_input(INPUT_POST, 'bootTypeExit')
                );
                $efiBootTypeExit = trim(
                    (string)filter_input(INPUT_POST, 'efiBootTypeExit')
                );

                $exists = self::getClass('HostManager')
                    ->exists($host);
                if ($exists) {
                    throw new \Exception(
                        _('A host already exists with this name!')
                    );
                }
                $MAC = new MACAddress($mac);
                if (!$MAC->isValid()) {
                    throw new \Exception(_('MAC Format is invalid'));
                }
                self::getClass('HostManager')->getHostByMacAddresses(
                    $MAC->__toString()
                );
                if (self::$Host->isValid()) {
                    throw new \Exception(
                        sprintf(
                            '%s: %s',
                            _('A host with this mac already exists with name'),
                            self::$Host->get('name')
                        )
                    );
                }
                $ModuleIDs = Route::getIds(
                    'module',
                    ['isDefault' => 1]
                );
                self::$Host
                    ->set('name', $host)
                    ->set('description', $description)
                    ->set('imageID', $image)
                    ->set('kernel', $kernel)
                    ->set('kernelArgs', $kernelArgs)
                    ->set('kernelDevice', $kernelDevice)
                    ->set('init', $init)
                    ->set('biosexit', $bootTypeExit)
                    ->set('efiexit', $efiBootTypeExit)
                    ->set('productKey', $productKey)
                    ->set('enforce', $enforce)
                    ->set('modules', $ModuleIDs)
                    ->addPriMAC($MAC)
                    ->setAD(
                        $useAD,
                        $domain,
                        $ou,
                        $user,
                        $pass,
                        true,
                        true,
                        $productKey
                    );
                if (!self::$Host->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add host failed!'));
                }
                return self::$Host;
            }
        );
    }
    /**
     * Displays the host general tab.
     *
     * @return void
     */
    public function hostGeneral()
    {
        $image = (
            filter_input(INPUT_POST, 'image') ?:
            ($this->obj->get('imageID') ?: '')
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);
        // The architectures an admin may pick on a HOST, which is what
        // `architectures.archIsAccess` is for -- the same flag taskTypes uses
        // to say a task type belongs to hosts, to groups, or to both. An
        // architecture flagged image-only never appears here.
        //
        // buildSelectBox() treats an empty filter as "no filter" and would
        // then offer every row, so an empty pick list has to be spelled as an
        // id that matches nothing rather than as nothing.
        $archID = (
            filter_input(INPUT_POST, 'archID') ?:
            ($this->obj->get('archID') ?: '')
        );
        $archIds = array_keys(Architecture::pickable('host'));
        if (count($archIds) < 1) {
            $archIds = [0];
        }
        $archSelector = self::getClass('ArchitectureManager')
            ->buildSelectBox($archID, 'archID', 'name', $archIds);
        // Either use the passed in or get the objects info.
        $host = (
            filter_input(INPUT_POST, 'host') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        $productKey = (
            filter_input(INPUT_POST, 'key') ?:
            ($this->obj->get('productKey') ?: '')
        );
        $productKeytest = self::aesdecrypt($productKey);
        $test_base64 = base64_decode($productKeytest);
        $base64 = mb_detect_encoding($test_base64, 'utf-8', true);
        $enctest = mb_detect_encoding($productKeytest, 'utf-8', true);
        if ($base64) {
            $productKey = $test_base64;
        } elseif ($enctest) {
            $productKey = $productKeytest;
        }
        $key = self::productKeyMask($productKey);
        $kernel = (
            filter_input(INPUT_POST, 'kernel') ?:
            ($this->obj->get('kernel') ?: '')
        );
        $args = (
            filter_input(INPUT_POST, 'args') ?:
            ($this->obj->get('kernelArgs') ?: '')
        );
        $init = (
            filter_input(INPUT_POST, 'init') ?:
            ($this->obj->get('init') ?: '')
        );
        $dev = (
            filter_input(INPUT_POST, 'dev') ?:
            ($this->obj->get('kernelDevice') ?: '')
        );
        $enforce = (int)filter_input(INPUT_POST, 'enforce')
            ?: $this->obj->get('enforce');
        // Server-owned, never posted back -- see the disabled inputs below.
        // Deliberately NOT read from INPUT_POST like every other value here:
        // these two are written by the ping service and by the client
        // check-in, so the object is the only source that can be right.
        $lastPing = self::dateOrNever(
            $this->obj->get('lastping'),
            'hosts',
            'hostLastPing'
        );
        // Say WHICH probe reached it, when the row knows. A timestamp with
        // no method answers "was it up" and leaves "is the service on
        // PINGHOSTPORT running" unanswered, which is the next question
        // anyone asks. Rows written before schema 356 have no method and
        // show the bare timestamp, which is all that is known about them.
        $pingMethod = strtolower((string)$this->obj->get('pingmethod'));
        if (_('Never') !== $lastPing && $pingMethod) {
            $lastPing = sprintf(
                '%s (%s)',
                $lastPing,
                // Protocol name, not translated.
                strtoupper($pingMethod)
            );
        }
        $lastCheckin = self::dateOrNever(
            $this->obj->get('lastcheckin'),
            'hosts',
            'hostLastCheckin'
        );
        // The Secure Boot ledger's two halves, prepared very differently on
        // purpose (schema steps 376 and 377).
        //
        // The reported state is server-owned in the same sense lastping is:
        // it is what the machine said on its last PXE boot, so the object is
        // the only source that can be right and INPUT_POST is never consulted.
        // Rendered with its "as of" stamp beside it, because an unqualified
        // "Secure Boot ON" invites the reader to treat a report from eight
        // months ago as current.
        $sbState = SecureBootState::label($this->obj->get('sbstate'));
        $sbStateTime = self::dateOrNever(
            $this->obj->get('sbstatetime'),
            'hosts',
            'hostSbStateTime'
        );
        if (_('Never') !== $sbStateTime) {
            $sbState = sprintf(
                /* translators: 1: a Secure Boot state, 2: a date and time */
                _('%1$s (as of %2$s)'),
                $sbState,
                $sbStateTime
            );
        }
        // The enrollment record IS posted back -- a technician who enrolled
        // from a USB stick is the only source for it and has to be able to
        // type it. filter_input first, object second, exactly like every
        // other editable value on this form.
        $sbEnrolled = (
            filter_input(INPUT_POST, 'sbenrolled') ?:
            ($this->obj->get('sbenrolled') ?: '')
        );
        // Rendered as the stored datetime rather than through dateOrNever(),
        // because this one round-trips: whatever is shown is what posts back
        // and is written to a DATETIME column, and "Never" is not a date. An
        // empty box is how "not enrolled" is both displayed and cleared.
        //
        // Deliberately NOT re-parsed, and deliberately carrying no zero-date
        // guard. hostSbEnrolled is NULL-able from birth (schema step 377), so
        // the zero date is not a value it can hold -- that guard belongs to
        // columns that predate the NULL convention, and writing one here would
        // put a 0000-00-00 literal back into the page layer that
        // tests/date-columns-nullable.test.php exists to keep out. NULL
        // becomes '' through the coalesce above; anything else came out of a
        // DATETIME and is already in this box's format.
        //
        // A value straight off a rejected POST is echoed back unchanged and
        // deliberately: the administrator needs to see what they typed in
        // order to fix it. makeInput() escapes it.
        $sbEnrollVia = (
            filter_input(INPUT_POST, 'sbenrollvia') ?:
            ($this->obj->get('sbenrollvia') ?: '')
        );
        $sbEnrollCert = (
            filter_input(INPUT_POST, 'sbenrollcert') ?:
            ($this->obj->get('sbenrollcert') ?: '')
        );
        // The comparison this column exists for, made rather than left to
        // the reader. Storing a fingerprint and rendering it next to nothing
        // asks an administrator to check 95 hex characters against a
        // different page by eye, which is not an answer -- it is the raw
        // material for one, and ADR 0029 decision 5 says the question is
        // "does this machine trust what I serve today".
        //
        // Computed from the STORED value, never from INPUT_POST: a rejected
        // post re-renders whatever was typed, and running the comparison on
        // that would report the freshness of a value the database does not
        // hold. Empty when there is nothing to compare -- see
        // enrollmentFreshness() for why that is not the same as stale.
        $sbEnrollFresh = SecureBootState::freshnessLabel(
            SecureBootState::enrollmentFreshness(
                $this->obj->get('sbenrollcert')
            )
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'host',
                _('Host Name')
            ) => self::makeInput(
                'form-control hostname-input',
                'host',
                _('Host Name'),
                'text',
                'host',
                $host,
                true,
                false,
                -1,
                15
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Host Description')
            ) => self::makeTextarea(
                'form-control hostdescription-input',
                'description',
                _('Host Description'),
                'description',
                $description
            ),
            // Architecture. Normally OBSERVED, not chosen:
            // IpxeBootMenu::_recordHostArch() writes it from what the machine
            // itself reported on its last PXE boot, and will overwrite
            // anything set here on the next one. That precedence is right --
            // the machine is a better witness than a person -- but it leaves
            // a gap this picker fills: a host registered through the client,
            // or imaged from USB, may never PXE boot into a FOG menu at all,
            // and until it does the deploy refusal has nothing to work with.
            //
            // Edit page only. A host being created has neither booted nor got
            // anything worth guessing, so the add form and its modal leave it
            // out rather than inviting a guess at creation time.
            self::makeLabel(
                $labelClass,
                'archID',
                _('Architecture')
            ) => $archSelector,
            self::makeLabel(
                $labelClass,
                'key',
                _('Host Product Key')
            ) => self::makeInput(
                'form-control hostkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $key,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Host Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Host Kernel')
            ) => self::kernelFileSelect(
                'kernel',
                $kernel,
                'kernel',
                'form-control hostkernel-input',
                '',
                _('Use the default kernel')
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Host Kernel Arguments')
            ) => self::makeInput(
                'form-control hostargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Host Init')
            ) => self::kernelFileSelect(
                'init',
                $init,
                'init',
                'form-control hostinit-input',
                '',
                _('Use the default init')
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Host Primary Disk')
            ) => self::makeInput(
                'form-control hostdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Enforce Hostname | AD Join Reboots')
            ) => $this->_enforceControl($enforce),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Host BIOS Exit Type')
            ) => $this->exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Host EFI Exit Type')
            ) => $this->exitEfi,
            // The two halves of "when was this host last seen" (schema step
            // 353). Both are disabled rather than merely readonly: a disabled
            // input is not submitted at all, so nothing server-owned can ride
            // back in on this form's POST even if a future hook were to start
            // mass-assigning it. A readonly input still posts its value.
            self::makeLabel(
                $labelClass,
                'lastping',
                _('Last Successful Ping')
            ) => self::makeInput(
                'form-control hostlastping-input',
                'lastping',
                '',
                'text',
                'lastping',
                $lastPing,
                false,
                false,
                -1,
                -1,
                '',
                true,
                true
            ),
            self::makeLabel(
                $labelClass,
                'lastcheckin',
                _('Last Client Check-In')
            ) => self::makeInput(
                'form-control hostlastcheckin-input',
                'lastcheckin',
                '',
                'text',
                'lastcheckin',
                $lastCheckin,
                false,
                false,
                -1,
                -1,
                '',
                true,
                true
            ),
            // OBSERVED -- disabled, for the same reason and in the same way
            // as the two above. This is the field ADR 0029's hard constraint
            // is about: it is a report of what a machine said, not a claim
            // anyone is entitled to make, so there is no editable rendering
            // of it anywhere. Route::$serverOwnedFields refuses it over the
            // API too, because a rule enforced by one form is a rule that
            // holds until someone adds a second writer.
            self::makeLabel(
                $labelClass,
                'sbstate',
                _('Secure Boot (reported)')
            ) => self::makeInput(
                'form-control hostsbstate-input',
                'sbstate',
                '',
                'text',
                'sbstate',
                $sbState,
                false,
                false,
                -1,
                -1,
                '',
                true,
                true
            ),
            // ASSERTED -- editable, and the three below are one record.
            //
            // Enrollment happens three ways and only two of them can write
            // this themselves: fog.enrollsb reports the db and MOK paths, and
            // a technician at the machine with a USB stick reports nothing at
            // all. Leaving these hand-editable is what makes the third path
            // recordable; it is also why they carry less authority than the
            // reported state above, not more.
            self::makeLabel(
                $labelClass,
                'sbenrolled',
                _('Secure Boot Enrolled')
            ) => self::makeInput(
                'form-control hostsbenrolled-input',
                'sbenrolled',
                'YYYY-MM-DD HH:MM:SS',
                'text',
                'sbenrolled',
                $sbEnrolled
            ),
            self::makeLabel(
                $labelClass,
                'sbenrollvia',
                _('Enrolled Via')
            ) => self::makeInput(
                'form-control hostsbenrollvia-input',
                'sbenrollvia',
                'db, trusted, mok, mok-pending, manual',
                'text',
                'sbenrollvia',
                $sbEnrollVia
            ),
            // The certificate that was enrolled, not merely the date. This
            // is the field that answers the question an admin actually has:
            // an enrollment date alone says nothing once the certificate has
            // rotated, and FOG has PKI zones, a multi-server CA and certs
            // that expire. Compare it against the SHA-256 on the Secure Boot
            // configuration page -- the two are computed identically, so the
            // check is string equality.
            self::makeLabel(
                $labelClass,
                'sbenrollcert',
                _('Enrolled Certificate (SHA-256)')
            ) => self::makeInput(
                'form-control hostsbenrollcert-input',
                'sbenrollcert',
                _('unrecorded'),
                'text',
                'sbenrollcert',
                $sbEnrollCert
            )
        ];
        // Appended rather than written into the literal above, because it is
        // only rendered when it has something to say. A disabled, empty
        // "Certificate Status" box on every host in a fleet that has never
        // enrolled anything is noise, and a field that is blank almost
        // everywhere trains people to skip the one place it is not.
        //
        // Disabled AND read-only, the same pair the reported state uses: a
        // disabled input is not submitted at all, so this cannot become a
        // second writer for a value that is derived rather than stored.
        if ('' !== $sbEnrollFresh) {
            $fields[
                self::makeLabel(
                    $labelClass,
                    'sbenrollfresh',
                    _('Certificate Status')
                )
            ] = self::makeInput(
                'form-control hostsbenrollfresh-input',
                'sbenrollfresh',
                '',
                'text',
                'sbenrollfresh',
                $sbEnrollFresh,
                false,
                false,
                -1,
                -1,
                '',
                true,
                true
            );
        }

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= '<div class="btn-group float-start">';
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger'
        );
        $buttons .= self::makeButton(
            'reset-encryption-data',
            _('Reset Encryption Data'),
            'btn btn-warning'
        );
        $buttons .= '</div>';

        self::$HookManager->processEvent(
            'HOST_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
            ]
        );
        // The enforce control moved here from the Service Settings tab; keep
        // firing its plugin hook so any external listeners still contribute.
        // Field additions merge into this form; button additions are appended.
        $enforceButtons = '';
        self::$HookManager->processEvent(
            'HOST_ENFORCE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$enforceButtons,
                'Host' => &$this->obj
            ]
        );
        $buttons .= $enforceButtons;
        $rendered = self::formFields($fields);
        unset($fields);

        $modalresetBtn = self::makeButton(
            'resetencryptionConfirm',
            _('Confirm'),
            'btn btn-outline-secondary float-end',
            ' method="post" action="../management/index.php?sub=clearAES" '
        );
        $modalresetBtn .= self::makeButton(
            'resetencryptionCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start'
        );
        $modalreset = self::makeModal(
            'resetencryptionmodal',
            _('Reset Encryption Data'),
            _(
                'Resetting encryption data should only be done '
                . 'if you re-installed the FOG Client or are using Debugger'
            ),
            $modalresetBtn,
            '',
            'warning'
        );
        $buttons .= $modalreset;

        $this->renderGeneralForm('host', $rendered, $buttons);
    }
    /**
     * Host general post update.
     *
     * @return void
     */
    public function hostGeneralPost()
    {
        self::checkAuthAndCSRF();
        $host = trim(
            (string)filter_input(INPUT_POST, 'host')
        );
        $description = trim(
            (string)filter_input(INPUT_POST, 'description')
        );
        $imageID = trim(
            (string)filter_input(INPUT_POST, 'image')
        );
        $key = trim(
            (string)filter_input(INPUT_POST, 'key')
        );
        $productKey = self::productKeyResolve(
            $key,
            $this->obj->get('productKey')
        );
        $kernel = trim(
            (string)filter_input(INPUT_POST, 'kernel')
        );
        $args = trim(
            (string)filter_input(INPUT_POST, 'args')
        );
        $dev = trim(
            (string)filter_input(INPUT_POST, 'dev')
        );
        $init = trim(
            (string)filter_input(INPUT_POST, 'init')
        );
        $bte = trim(
            (string)filter_input(INPUT_POST, 'bootTypeExit')
        );
        $ebte = trim(
            (string)filter_input(INPUT_POST, 'efiBootTypeExit')
        );
        $enforce = filter_has_var(INPUT_POST, 'enforce') ? 1 : 0;
        // The blank "- Please select -" option means "not recorded", which is
        // a real value here and not the absence of one: Architecture::canRun()
        // reads it as "nothing to contradict" and allows the deploy. Stored
        // as NULL rather than 0 so it reads the same as a host that has never
        // been touched.
        $archID = trim(
            (string)filter_input(INPUT_POST, 'archID')
        );
        $archID = '' === $archID ? null : (int)$archID;
        // The enrollment record (schema step 377). Editable, unlike the
        // reported state above it on the form, because a technician who
        // enrolled from a USB stick is the only source for it.
        //
        // Validated rather than trusted, even though the writer is an
        // authenticated admin: these three land in a DATETIME and two
        // VARCHARs, and an unparseable date written to a DATETIME is the
        // GH-1243/GH-1245 family -- it stores as the zero date and the
        // display layer then reads "never enrolled" as "enrolled in year
        // zero". An empty box is how an enrollment is cleared, and must stay
        // distinguishable from a bad one: '' stores NULL, garbage is
        // refused out loud.
        $sbEnrolled = trim(
            (string)filter_input(INPUT_POST, 'sbenrolled')
        );
        if ('' === $sbEnrolled) {
            $sbEnrolled = null;
        } elseif (self::validDate($sbEnrolled)) {
            // Typed into the form, so read in the viewer's zone.
            $sbEnrolled = self::viewerDate($sbEnrolled)
                ->format('Y-m-d H:i:s');
        } else {
            throw new \Exception(
                _('Secure Boot enrollment date is not a valid date')
            );
        }
        // Whitelisted against the same five words fog.enrollsb and the host
        // form document, lower-cased on the way in. A free-text provenance
        // column is a column that ends up holding 'usb', 'USB stick' and
        // 'Dave did it', none of which anything can read back.
        //
        // 'trusted' is its own value rather than a synonym for 'db' because
        // it records something FOG did NOT do: the machine already trusted
        // this certificate when the task ran, and nothing here observed how
        // it got there -- db, a MOK confirmed months ago, or an image that
        // shipped with it. Folding it into 'db' would assert a mechanism
        // nobody watched happen, which is the same mistake as recording a
        // staged MOK as an enrollment, just quieter.
        $sbEnrollVia = strtolower(
            trim((string)filter_input(INPUT_POST, 'sbenrollvia'))
        );
        if ('' === $sbEnrollVia) {
            $sbEnrollVia = null;
        } elseif (
            !in_array(
                $sbEnrollVia,
                ['db', 'trusted', 'mok', 'mok-pending', 'manual'],
                true
            )
        ) {
            throw new \Exception(
                _(
                    'Enrolled Via must be one of: db, trusted, mok, '
                    . 'mok-pending, manual'
                )
            );
        }
        // Shape-checked, not merely trimmed, and through the same
        // normalizer service/secureboot.report.php uses -- this format was
        // written out longhand in four files, which is three places for it
        // to drift. Accepts the colon-formatted form the Secure Boot page
        // displays and the bare hex a copy-paste tends to produce, and
        // stores the former.
        //
        // Rejecting rather than storing whatever arrived matters because
        // this column's only use is an equality test: a comparison against
        // something that is not a SHA-256 can only ever be false, silently,
        // and looking exactly like "this host trusts an older certificate".
        $sbEnrollCert = trim(
            (string)filter_input(INPUT_POST, 'sbenrollcert')
        );
        if ('' === $sbEnrollCert) {
            $sbEnrollCert = null;
        } else {
            $sbEnrollCert = SecureBootState::normalizeFingerprint(
                $sbEnrollCert
            );
            if ('' === $sbEnrollCert) {
                throw new \Exception(
                    _(
                        'Enrolled certificate must be a SHA-256 fingerprint '
                        . '(64 hex characters)'
                    )
                );
            }
        }
        if (strtolower($host) != strtolower($this->obj->get('name'))) {
            if (!$this->obj->isHostnameSafe($host)) {
                throw new \Exception(_('Please enter a valid hostname'));
            }
            if ($this->obj->getManager()->exists($host)) {
                throw new \Exception(_('Please use another hostname'));
            }
        }
        $Task = $this->obj->get('task');
        if ($Task->isValid()
            && $imageID != $this->obj->get('imageID')
        ) {
            throw new \Exception(_('Cannot change image when in tasking'));
        }
        $this->obj
            ->set('name', $host)
            ->set('description', $description)
            ->set('imageID', $imageID)
            ->set('kernel', $kernel)
            ->set('kernelArgs', $args)
            ->set('kernelDevice', $dev)
            ->set('init', $init)
            ->set('biosexit', $bte)
            ->set('efiexit', $ebte)
            ->set('enforce', $enforce)
            ->set('archID', $archID)
            ->set('sbenrolled', $sbEnrolled)
            ->set('sbenrollvia', $sbEnrollVia)
            ->set('sbenrollcert', $sbEnrollCert)
            ->set('productKey', $productKey);
    }
    /**
     * Host MAC Address listing.
     *
     * @return void
     */
    public function hostMacaddress()
    {
        $newMac = (
            filter_input(INPUT_POST, 'newMac')
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-macaddress',
                $this->obj->get('id')
            )
            . '" ';

        $fields = [
            self::makeLabel(
                'col-sm-3 col-form-label',
                'newMac',
                _('MAC Address')
            ) => self::makeInput(
                'form-control hostmac-input',
                'newMac',
                '00:00:00:00:00:00',
                'text',
                'newMac',
                $newMac,
                true,
                false,
                12,
                17
            )
        ];

        $buttons = self::makeButton(
            'newmac-cancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $buttons .= self::makeButton(
            'newmac-send',
            _('Add'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'HOST_MACADDRESS_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        // =========================================================
        // New MAC Address add.
        $macAddModal = self::makeModal(
            'macaddressModal',
            _('Add New MAC Address'),
            self::makeFormTag(
                '',
                'macaddress-add-form',
                self::makeTabUpdateURL(
                    'host-macaddress',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            )
            . $rendered
            . self::makeInput(
                '',
                'macadd',
                '',
                'hidden',
                '',
                '1'
            )
            . '</form>',
            $buttons,
            '',
            'info'
        );

        // MAC Address Table
        $buttons = '<div class="btn-group float-end">';
        // Secondary, not primary: this sits immediately LEFT of the primary
        // split button in the same group, and two blues touching read as one
        // wide button. The split button acts on the selected rows and stays the
        // primary; adding a new address supports it. Same relationship as
        // "Create New X" sitting left of "Add selected" on association tabs.
        $buttons .= self::makeButton(
            'macaddress-add',
            _('Add New MAC Address'),
            'btn btn-secondary'
        );
        $buttons .= self::makeSplitButton(
            'macaddress-table-update-image',
            _('Mark selected image ignore'),
            [
                [
                    'id' => 'macaddress-table-update-unimage',
                    'text' => _('Unmark selected image ignore'),
                    'props' => $props
                ],
                [
                    'id' => 'macaddress-table-update-client',
                    'text' => _('Mark selected client ignore'),
                    'props' => $props
                ],
                [
                    'id' => 'macaddress-table-update-unclient',
                    'text' => _('Unmark selected client ignore'),
                    'props' => $props
                ],
                [
                    'id' => 'macaddress-table-update-pending',
                    'text' => _('Mark selected pending'),
                    'props' => $props
                ],
                [
                    'id' => 'macaddress-table-update-unpending',
                    'text' => _('Unmark selected pending'),
                    'props' => $props
                ]
            ],
            'right',
            'primary',
            $props
        );
        $buttons .= '</div>';
        $buttons .= self::makeButton(
            'macaddress-table-delete',
            _('Delete selected'),
            'btn btn-danger float-start',
            $props
        );
        $this->headerData = [
            _('MAC Address'),
            _('Description'),
            _('Primary'),
            _('Ignore Imaging'),
            _('Ignore Client'),
            _('Pending')
        ];
        $this->attributes = [
            [],
            [],
            ['width' => 16],
            ['width' => 16],
            ['width' => 16],
            ['width' => 16]
        ];
        echo '<div class="card">';
        echo '<div id="updatemacaddresses" class="">';
        echo '<div class="card-body">';
        $this->render(12, 'host-macaddresses-table', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
        echo $macAddModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host MAC Address update.
     *
     * @return void
     */
    public function hostMacaddressPost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['macadd'])) {
            $mac = trim(
                (string)filter_input(
                    INPUT_POST,
                    'newMac'
                )
            );
            $mact = new MACAddress($mac);
            if (!$mact->isValid()) {
                throw new \Exception(_('MAC Address is invalid!'));
            }
            $mace = self::getClass('MACAddressAssociationManager')
                ->exists($mac, '', 'mac');
            if ($mace) {
                throw new \Exception(
                    _('MAC Address already exists')
                );
            }
            $this->obj->addMAC($mac);
        }
        if (isset($_POST['updateprimary'])) {
            $primary = (int)filter_input(
                INPUT_POST,
                'primary'
            );
            self::getClass('MACAddressAssociationManager')
                ->update(
                    ['hostID' => $this->obj->get('id')],
                    '',
                    ['primary' => 0]
                );
            if ($primary) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $primary,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['primary' => 1]
                    );
            }
        }
        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        if (isset($_POST['updatechecks'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'imageIgnore' => $flags,
                    'clientIgnore' => $flags,
                    'pending' => $flags
                ]
            );
            $imageIgnore = $items['imageIgnore'];
            $clientIgnore = $items['clientIgnore'];
            $pending = $items['pending'];
            self::getClass('MACAddressAssociationManager')
                ->update(
                    ['hostID' => $this->obj->get('id')],
                    '',
                    [
                        'imageIgnore' => 0,
                        'clientIgnore' => 0,
                        'pending' => 0
                    ]
                );
            if (count($imageIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $imageIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['imageIgnore' => 1]
                    );
            }
            if (count($clientIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $clientIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['clientIgnore' => 1]
                    );
            }
            if (count($pending ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $pending,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['pending' => 1]
                    );
            }
        }
        if (isset($_POST['removeMacs'])) {
            $toRemove = filter_input_array(
                INPUT_POST,
                [
                    'toRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $toRemove = $toRemove['toRemove'];

            $find = [
                'id' => $toRemove,
                'hostID' => $this->obj->get('id'),
                'primary' => [1]
            ];

            $hasPrimary = Route::getIds(
                'macaddressassociation',
                $find
            );

            if (count($hasPrimary ?: []) > 0) {
                throw new \Exception(
                    _('Cannot delete the primary mac address, please reselect')
                );
            }

            $find = [
                'id' => $toRemove,
                'hostID' => $this->obj->get('id'),
                'primary' => [0, '']
            ];

            $toRemove = Route::getIds(
                'macaddressassociation',
                $find,
                'mac'
            );

            if (count($toRemove ?: []) < 1) {
                throw new \Exception(
                    _('No mac addresses to be removed')
                );
            }

            Route::deletemass(
                'macaddressassociation',
                ['mac' => $toRemove]
            );
        }
        if (isset($_POST['markimageignore'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['imageIgnore' => $flags]
            );
            $imageIgnore = $items['imageIgnore'];
            if (count($imageIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $imageIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['imageIgnore' => 1]
                    );
            }
        }
        if (isset($_POST['markimageunignore'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['imageIgnore' => $flags]
            );
            $imageIgnore = $items['imageIgnore'];
            if (count($imageIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $imageIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['imageIgnore' => 0]
                    );
            }
        }
        if (isset($_POST['markclientignore'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['clientIgnore' => $flags]
            );
            $clientIgnore = $items['clientIgnore'];
            if (count($clientIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $clientIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['clientIgnore' => 1]
                    );
            }
        }
        if (isset($_POST['markclientunignore'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['clientIgnore' => $flags]
            );
            $clientIgnore = $items['clientIgnore'];
            if (count($clientIgnore ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $clientIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['clientIgnore' => 0]
                    );
            }
        }
        if (isset($_POST['markpending'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['pending' => $flags]
            );
            $pending = $items['pending'];
            if (count($pending ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $pending,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['pending' => 1]
                    );
            }
        }
        if (isset($_POST['markunpending'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['pending' => $flags]
            );
            $pending = $items['pending'];
            if (count($pending ?: []) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $pending,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['pending' => 0]
                    );
            }
        }
    }
    /**
     * Host active directory post element.
     *
     * @return void
     */
    public function hostADPost()
    {
        self::checkAuthAndCSRF();
        $useAD = isset($_POST['domain']);
        $domain = trim(
            (string)filter_input(INPUT_POST, 'domainname')
        );
        $ou = trim(
            (string)filter_input(INPUT_POST, 'ou')
        );
        $user = trim(
            (string)filter_input(INPUT_POST, 'domainuser')
        );
        $pass = trim(
            (string)filter_input(INPUT_POST, 'domainpassword')
        );
        $this->obj->setAD(
            $useAD,
            $domain,
            $ou,
            $user,
            $pass,
            true,
            true
        );
    }
    /**
     * Host groups dispay.
     *
     * @return void
     */
    public function hostGroups()
    {
        // Trailing 'group' opts this tab into the "Create New Group" button and
        // modal, so a group that does not exist yet does not mean leaving the
        // host page to make it.
        $this->renderAssocTab(
            'host-group',
            _('Host Group Associations'),
            _('Group Name'),
            'group',
            'btn btn-primary float-end',
            '',
            'group'
        );
    }
    /**
     * Host groups modifications.
     *
     * @return void
     */
    public function hostGroupPost()
    {
        $this->assocPost('addGroup', 'removeGroup');
    }
    /**
     * Host printers display.
     *
     * @return void
     */
    public function hostPrinters()
    {
        // Printer Associations. Trailing 'printer' opts this tab into the
        // "Create New Printer" button and modal (see renderAssocCreate), so a
        // printer that does not exist yet does not mean leaving the host page.
        $this->renderAssocTab(
            'host-printer',
            _('Host Printer Associations'),
            _('Printer Name'),
            'printer',
            'btn btn-primary float-end',
            '',
            'printer'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-printer',
                $this->obj->get('id')
            )
            . '" ';

        // DEFAULT Printer
        $buttons = self::makeButton(
            'host-printer-default-send',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Host Default Printer');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<span id="printerselector"></span>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';

        // =========================================================
        // Printer Configuration
        $printerLevel = (
            filter_input(INPUT_POST, 'level') ?:
            $this->obj->get('printerLevel')
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Host Printer Configuration');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<div class="form-check">';
        echo self::makeLabel(
            '',
            'noLevel',
            self::makeInput(
                'printer-nolevel',
                'level',
                '',
                'radio',
                'noLevel',
                '0',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 0 ? 'checked' : '')
            )
            . ' '
            . _('No Printer Management'),
            'data-bs-toggle="tooltip" data-bs-placement="right" title="'
            . _(
                'This setting turns off all FOG Printer Management. '
                . 'Although there are multiple levels already, this '
                . 'is just another level if needed.'
            )
            . '"'
        );
        echo '</div>';
        echo '<div class="form-check">';
        echo self::makeLabel(
            '',
            'addlevel',
            self::makeInput(
                'printer-addlevel',
                'level',
                '',
                'radio',
                'addlevel',
                '1',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 1 ? 'checked' : '')
            )
            . ' '
            . _('Add/Remove Managed Printers'),
            'data-bs-toggle="tooltip" data-bs-placement="right" title="'
            . _(
                'This setting only adds and removes '
                . 'printers that are managed by FOG. '
                . 'If the printer exists in printer '
                . 'management but is not assigned to a '
                . 'host, it will remove the printer if '
                . 'it exists on the unassigned host. '
                . 'It will add printers to the host '
                . 'that are assigned.'
            )
            . '"'
        );
        echo '</div>';
        echo '<div class="form-check">';
        echo self::makeLabel(
            '',
            'alllevel',
            self::makeInput(
                'printer-alllevel',
                'level',
                '',
                'radio',
                'alllevel',
                '2',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 2 ? 'checked' : '')
            )
            . ' '
            . _('All Printers'),
            'data-bs-toggle="tooltip" data-bs-placement="right" title="'
            . _(
                'This setting will only allow FOG Assigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            )
            . '"'
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo self::makeButton(
            'printer-config-send',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host printer post.
     *
     * @return void
     */
    public function hostPrinterPost()
    {
        $this->assocPost('addPrinter', 'removePrinter');
        if (isset($_POST['confirmdefault'])) {
            $this->obj->updateDefault(
                filter_input(
                    INPUT_POST,
                    'default'
                )
            );
        }
        if (isset($_POST['confirmlevelup'])) {
            $level = filter_input(INPUT_POST, 'level');
            $this->obj->set('printerLevel', $level);
        }
    }
    /**
     * Host snapins.
     *
     * @return void
     */
    public function hostSnapins()
    {
        // Trailing 'snapin' opts this tab into the "Create New Snapin" button
        // and modal (see renderAssocCreate), so a snapin that does not exist yet
        // does not mean leaving the host page to upload it.
        $this->renderAssocTab(
            'host-snapin',
            _('Host Snapin Associations'),
            _('Snapin Name'),
            'snapin',
            'btn btn-primary float-end',
            '',
            'snapin'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-snapin',
                $this->obj->get('id')
            )
            . '" ';

        $orderButton = self::makeButton(
            'host-snapin-order-save',
            _('Save order'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Snapin Run Order');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p class="form-text">';
        echo _(
            'Snapins run in this order. The order only changes execution when '
            . '"Abort snapin sequence on failure" is enabled for the task; '
            . 'otherwise it sets the order snapins are sent.'
        );
        echo '</p>';
        echo '<ol id="host-snapin-order-list" class="list-group"></ol>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $orderButton;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Returns the associated snapins for this host in run order.
     *
     * @return void
     */
    public function getSnapinOrderList()
    {
        $snapinIDs = (array)$this->obj->get('snapins');
        $names = [];
        if (count($snapinIDs) > 0) {
            $Snapins = Route::getList('snapin', ['id' => $snapinIDs]);
            foreach ($Snapins as $Snapin) {
                $names[$Snapin->id] = $Snapin->name;
            }
        }
        $data = [];
        foreach ($snapinIDs as $snapinID) {
            // Skip ids that don't resolve to a real snapin (a stale
            // association left by a removed snapin, or a 0/blank id). They
            // are not orderable and previously rendered as a phantom "#0".
            // Mirrors setSnapinOrder()'s "< 1" guard on the save path.
            if (!isset($names[$snapinID])) {
                continue;
            }
            $data[] = [
                'id' => $snapinID,
                'name' => $names[$snapinID]
            ];
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['data' => $data]));
    }
    /**
     * Host snapin post
     *
     * @return void
     */
    public function hostSnapinPost()
    {
        $this->assocPost('addSnapin', 'removeSnapin', 'setSnapinOrder');
    }
    /**
     * Display's the host service stuff
     *
     * @return void
     */
    public function hostModules()
    {
        // Association Area
        $this->renderAssocTab(
            'host-module',
            _('Host Module Associations'),
            _('Module Name'),
            'module',
            'btn btn-primary float-end',
            _('Disabled items are not displayed. Legacy items are removed.')
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-module',
                $this->obj->get('id')
            )
            . '" ';

        $labelClass = 'col-sm-3 col-form-label';
        // Display Manager area
        $dispEnabled = self::getSetting('FOG_CLIENT_DISPLAYMANAGER_ENABLED');
        if ($dispEnabled) {
            $buttons = self::makeButton(
                'host-displayman-send',
                _('Update'),
                'btn btn-primary float-end',
                $props
            );
            // If the x, y, and/or r inputs are set.
            $ix = filter_input(INPUT_POST, 'x');
            $iy = filter_input(INPUT_POST, 'y');
            $ir = filter_input(INPUT_POST, 'r');
            if (!$ix) {
                // If x not set check hosts setting
                $ix = $this->obj->getDispVals('width');
            }
            if (!$iy) {
                // If y not set check hosts setting
                $iy = $this->obj->getDispVals('height');
            }
            if (!$ir) {
                // If r not set check hosts setting
                $ir = $this->obj->getDispVals('refresh');
            }
            $x = $ix;
            $y = $iy;
            $r = $ir;
            $names = [
                'x' => [
                    'width',
                    _('Screen Width')
                    . '<br/>('
                    . _('in pixels')
                    . ')'
                ],
                'y' => [
                    'height',
                    _('Screen Height')
                    . '<br/>('
                    . _('in pixels')
                    . ')'
                ],
                'r' => [
                    'refresh',
                    _('Screen Refresh Rate')
                    . '<br/>('
                    . _('in Hz')
                    . ')'
                ]
            ];
            foreach ($names as $name => &$get) {
                switch ($name) {
                    case 'r':
                        $val = $r;
                        break;
                    case 'x':
                        $val = $x;
                        break;
                    case 'y':
                        $val = $y;
                }
                $fields[
                    self::makeLabel(
                        $labelClass,
                        $name,
                        $get[1]
                    )
                ] = self::makeInput(
                    'form-control',
                    $name,
                    '',
                    'number',
                    $name,
                    $val
                );
                unset($get);
            }

            self::$HookManager->processEvent(
                'HOST_DISPLAYMAN_FIELDS',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Host' => &$this->obj
                ]
            );

            $rendered = self::formFields($fields);
            unset($fields);
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo _('Host Display Manager Settings');
            echo '</h4>';
            echo '</div>';
            echo '<div class="card-body">';
            echo self::makeFormTag(
                '',
                'host-displayman-form',
                self::makeTabUpdateURL(
                    'host-module',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '<div class="card-footer">';
            echo $buttons;
            echo '</div>';
            echo '</div>';
        }

        // Auto Log Out
        $aloEnabled = self::getSetting('FOG_CLIENT_AUTOLOGOFF_ENABLED');
        if ($aloEnabled) {
            $buttons = self::makeButton(
                'host-alo-send',
                _('Update'),
                'btn btn-primary float-end',
                $props
            );
            $tme = filter_input(INPUT_POST, 'tme');
            if (!$tme) {
                $tme = $this->obj->getAlo();
            }
            if (!$tme) {
                $tme = 0;
            }
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'tme',
                    _('Auto Logout Time')
                    . '<br/>('
                    . _('in minutes')
                    . ')'
                ) => self::makeInput(
                    'form-control',
                    'tme',
                    '',
                    'number',
                    'tme',
                    $tme
                )
            ];

            self::$HookManager->processEvent(
                'HOST_ALO_FIELDS',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Host' => &$this->obj
                ]
            );

            $rendered = self::formFields($fields);
            unset($fields);

            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo _('Auto Logout Settings');
            echo '</h4>';
            echo '<p class="form-text">';
            echo _('Minimum time limit for Auto Logout to become active is 5 minutes.');
            echo '</p>';
            echo '</div>';
            echo '<div class="card-body">';
            echo self::makeFormTag(
                '',
                'host-alo-form',
                self::makeTabUpdateURL(
                    'host-module',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '<div class="card-footer">';
            echo $buttons;
            echo '</div>';
            echo '</div>';
        }
    }
    /**
     * Update the actual thing.
     *
     * @return void
     */
    public function hostModulePost()
    {
        $this->assocPost('addModule', 'removeModule');
        if (isset($_POST['confirmdisplaysend'])) {
            $x = (int)filter_input(INPUT_POST, 'x');
            $y = (int)filter_input(INPUT_POST, 'y');
            $r = (int)filter_input(INPUT_POST, 'r');
            $this->obj->setDisp($x, $y, $r);
        }
        if (isset($_POST['confirmalosend'])) {
            $tme = (int)filter_input(INPUT_POST, 'tme');
            if (!(is_numeric($tme) && $tme > 4)) {
                $tme = 0;
            }
            $this->obj->setAlo($tme);
        }
    }
    /**
     * Generates the powermanagement display items.
     *
     * @return void
     */
    public function hostPowermanagement()
    {
        // The powermanagement table.
        $this->headerData = [
            _('Cron Schedule'),
            _('Action')
        ];
        $this->attributes = [
            [],
            []
        ];
        $buttons = '';
        $splitButtons = self::makeSplitButton(
            'scheduleBtn',
            _('Create New Scheduled'),
            [
                [
                    'id' => 'ondemandBtn',
                    'text' => _('Create New Immediate')
                ]
            ],
            'right',
            'primary'
        );
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-powermanagement',
                $this->obj->get('id')
            )
            . '" ';
        $buttons .= self::makeButton(
            'pm-delete',
            _('Delete selected'),
            'btn btn-danger float-start',
            $props
        );
        $ondemandModalBtns = self::makeButton(
            'ondemandCancelBtn',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $ondemandModalBtns .= self::makeButton(
            'ondemandCreateBtn',
            _('Create'),
            'btn btn-outline-secondary float-end',
            $props
        );
        $scheduleModalBtns = self::makeButton(
            'scheduleCancelBtn',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $scheduleModalBtns .= self::makeButton(
            'scheduleCreateBtn',
            _('Create'),
            'btn btn-outline-secondary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Scheduled Power Management Tasks');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'host-powermanagement-table', $buttons.$splitButtons);
        echo '</div>';
        echo '<div class="card-footer">';
        echo self::makeModal(
            'ondemandModal',
            _('Create Immediate Power task'),
            $this->newPMDisplay(true),
            $ondemandModalBtns,
            '',
            'info'
        );
        echo self::makeModal(
            'scheduleModal',
            _('Create Scheduled Power task'),
            $this->newPMDisplay(false),
            $scheduleModalBtns,
            '',
            'primary'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host power management post.
     *
     * @return void
     */
    public function hostPowermanagementPost()
    {
        self::checkAuthAndCSRF();
        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        if (isset($_POST['pmupdate'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'scheduleCronMin' => $flags,
                    'scheduleCronHour' => $flags,
                    'scheduleCronDOM' => $flags,
                    'scheduleCronMonth' => $flags,
                    'scheduleCronDOW' => $flags,
                    'pmid' => $flags,
                    'onDemand' => $flags,
                    'action' => $flags
                ]
            );
            extract($items);
            if (!$action) {
                throw new \Exception(
                    _('You must select an action to perform')
                );
            }
            $items = [];
            foreach ((array)$pmid as $index => &$pm) {
                $onDemandItem = array_search(
                    $pm,
                    (array)$onDemand
                );
                $items[] = [
                    $pm,
                    $this->obj->get('id'),
                    FOGCron::_sanitizeCronField($scheduleCronMin[$index]),
                    FOGCron::_sanitizeCronField($scheduleCronHour[$index]),
                    FOGCron::_sanitizeCronField($scheduleCronDOM[$index]),
                    FOGCron::_sanitizeCronField($scheduleCronMonth[$index]),
                    FOGCron::_sanitizeCronField($scheduleCronDOW[$index]),
                    $onDemandItem !== false ? 1 : 0,
                    $action[$index]
                ];
                unset($pm);
            }
            self::getClass('PowerManagementManager')
                ->insertBatch(
                    [
                        'id',
                        'hostID',
                        'min',
                        'hour',
                        'dom',
                        'month',
                        'dow',
                        'onDemand',
                        'action'
                    ],
                    $items
                );
        }
        if (isset($_POST['pmadd']) || isset($_POST['pmaddod'])) {
            $onDemand = (int)isset($_POST['pmaddod']);
            $min = trim(
                (string)filter_input(
                    INPUT_POST,
                    'scheduleCronMin'
                )
            );
            $hour = trim(
                (string)filter_input(
                    INPUT_POST,
                    'scheduleCronHour'
                )
            );
            $dom = trim(
                (string)filter_input(
                    INPUT_POST,
                    'scheduleCronDOM'
                )
            );
            $month = trim(
                (string)filter_input(
                    INPUT_POST,
                    'scheduleCronMonth'
                )
            );
            $dow = trim(
                (string)filter_input(
                    INPUT_POST,
                    'scheduleCronDOW'
                )
            );
            $action = trim(
                (string)filter_input(
                    INPUT_POST,
                    'action'
                )
            );
            if ($onDemand && $action === 'wol') {
                $this->obj->wakeOnLAN();
                return;
            }
            if (!$onDemand) {
                $min = FOGCron::_sanitizeCronField($min);
                $hour = FOGCron::_sanitizeCronField($hour);
                $dom = FOGCron::_sanitizeCronField($dom);
                $month = FOGCron::_sanitizeCronField($month);
                $dow = FOGCron::_sanitizeCronField($dow);
            }
            self::getClass('PowerManagement')
                ->set('hostID', $this->obj->get('id'))
                ->set('min', $min)
                ->set('hour', $hour)
                ->set('dom', $dom)
                ->set('month', $month)
                ->set('dow', $dow)
                ->set('onDemand', $onDemand)
                ->set('action', $action)
                ->save();
        }
        if (isset($_POST['pmdelete'])) {
            $pmid = filter_input_array(
                INPUT_POST,
                ['rempowermanagements' => $flags]
            );
            $pmid = $pmid['rempowermanagements'];
            Route::deletemass('powermanagement', ['id' => $pmid]);
        }
    }
    /**
     * Displays Host Inventory
     *
     * @param bool $static This is if we're displaying the static items
     *
     * @return void
     */
    public function hostInventory($static = false)
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-inventory',
                $this->obj->get('id')
            )
            . '" ';
        $cpus = ['cpuman', 'cpuversion'];
        foreach ($cpus as &$x) {
            $this->obj->get('inventory')
                ->set(
                    $x,
                    implode(
                        ' ',
                        array_unique(
                            explode(
                                ' ',
                                $this->obj->get('inventory')->get($x)
                            )
                        )
                    )
                )
                ->set('hostID', $this->obj->get('id'));
            unset($x);
        }
        $Inv = $this->obj->get('inventory');
        $puser = $Inv->get('primaryUser');
        $other1 = $Inv->get('other1');
        $other2 = $Inv->get('other2');
        $sysman = $Inv->get('sysman');
        $sysprod = $Inv->get('sysproduct');
        $sysver = $Inv->get('sysversion');
        $sysser = $Inv->get('sysserial');
        $systype = $Inv->get('systype');
        $sysuuid = $Inv->get('sysuuid');
        $biosven = $Inv->get('biosvendor');
        $biosver = $Inv->get('biosversion');
        $biosdate = $Inv->get('biosdate');
        $mbman = $Inv->get('mbman');
        $mbprod = $Inv->get('mbproductname');
        $mbver = $Inv->get('mbversion');
        $mbser = $Inv->get('mbserial');
        $mbast = $Inv->get('mbasset');
        $cpuman = $Inv->get('cpuman');
        $cpuver = $Inv->get('cpuversion');
        $cpucur = $Inv->get('cpucurrent');
        $cpumax = $Inv->get('cpumax');
        $mem = $Inv->getMem();
        $hdmod = $Inv->get('hdmodel');
        $hdfirm = $Inv->get('hdfirmware');
        $hdser = $Inv->get('hdserial');
        $caseman = $Inv->get('caseman');
        // 'casever', not 'caseversion'. Inventory declares the key as
        // casever; get() returns false for a key the model does not have,
        // so the Chassis Version input has rendered empty for every host.
        $casever = $Inv->get('casever');
        $caseser = $Inv->get('caseserial');
        $caseast = $Inv->get('caseasset');
        $gpuvendors = $Inv->get('gpuvendors');
        $gpuproducts = $Inv->get('gpuproducts');
        $gpuvendorsArray = explode(',', $gpuvendors);
        $gpuproductsArray = explode(',', $gpuproducts);

        $labelClass = 'col-sm-3 col-form-label';

        $buttons = '';

        if (!$static) {
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'pu',
                    _('Primary User')
                ) => self::makeInput(
                    'form-control',
                    'pu',
                    _('Primary User'),
                    'text',
                    'pu',
                    $puser
                ),
                self::makeLabel(
                    $labelClass,
                    'other1',
                    _('Other Tag #1')
                ) => self::makeInput(
                    'form-control',
                    'other1',
                    '',
                    'text',
                    'other1',
                    $other1
                ),
                self::makeLabel(
                    $labelClass,
                    'other2',
                    _('Other Tag #2')
                ) => self::makeInput(
                    'form-control',
                    'other2',
                    '',
                    'text',
                    'other2',
                    $other2
                )
            ];
            $buttons = self::makeButton(
                'host-inventory-send',
                _('Update'),
                'btn btn-primary float-end'
            );
        } else {
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'inventory-manufacturer',
                    _('System Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-manufacturer',
                    '',
                    'text',
                    '',
                    $sysman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-product',
                    _('System Product')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-product',
                    '',
                    'text',
                    '',
                    $sysprod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-version',
                    _('System Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-version',
                    '',
                    'text',
                    '',
                    $sysver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-serial',
                    _('System Serial')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-serial',
                    '',
                    'text',
                    '',
                    $sysser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-uuid',
                    _('System UUID')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-uuid',
                    '',
                    'text',
                    '',
                    \Initiator::e($sysuuid),
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-type',
                    _('System Type')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-type',
                    '',
                    'text',
                    '',
                    $systype,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-bios-vendor',
                    _('BIOS Vendor')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-vendor',
                    '',
                    'text',
                    '',
                    $biosven,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-bios-version',
                    _('BIOS Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-version',
                    '',
                    'text',
                    '',
                    $biosver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-bios-date',
                    _('BIOS Date')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-date',
                    '',
                    'text',
                    '',
                    $biosdate,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-motherboard-manufacturer',
                    _('Motherboard Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-manufacturer',
                    '',
                    'text',
                    '',
                    $mbman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-motherboard-productname',
                    _('Motherboard Product Name')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-productname',
                    '',
                    'text',
                    '',
                    $mbprod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-motherboard-version',
                    _('Motherboard Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-version',
                    '',
                    'text',
                    '',
                    $mbver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-motherboard-serial-number',
                    _('Motherboard Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-serial-number',
                    '',
                    'text',
                    '',
                    $mbser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-motherboard-asset-tag',
                    _('Motherboard Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-asset-tag',
                    '',
                    'text',
                    '',
                    $mbast,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-cpu-manufacturer',
                    _('CPU Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-manufacturer',
                    '',
                    'text',
                    '',
                    $cpuman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-cpu-version',
                    _('CPU Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-version',
                    '',
                    'text',
                    '',
                    $cpuver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-cpu-normal-speed',
                    _('CPU Normal Speed')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-normal-speed',
                    '',
                    'text',
                    '',
                    $cpucur,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-cpu-max-speed',
                    _('CPU Max Speed')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-max-speed',
                    '',
                    'text',
                    '',
                    $cpumax,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-memory',
                    _('Memory')
                ) => self::makeInput(
                    'form-control',
                    'inventory-memory',
                    '',
                    'text',
                    '',
                    $mem,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-hard-drive-model',
                    _('Hard Drive Model')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-model',
                    '',
                    'text',
                    '',
                    $hdmod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-hard-drive-firmware',
                    _('Hard Drive Firmware')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-firmware',
                    '',
                    'text',
                    '',
                    $hdfirm,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-hard-drive-serial-number',
                    _('Hard Drive Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-serial-number',
                    '',
                    'text',
                    '',
                    $hdser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-chassis-manufacturer',
                    _('Chassis Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-manufacturer',
                    '',
                    'text',
                    '',
                    $caseman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-chassis-version',
                    _('Chassis Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-version',
                    '',
                    'text',
                    '',
                    $casever,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-chassis-serial-number',
                    _('Chassis Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-serial-number',
                    '',
                    'text',
                    '',
                    $caseser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-chassis-asset-tag',
                    _('Chassis Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-asset-tag',
                    '',
                    'text',
                    '',
                    $caseast,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                )
            ];
            for ($i = 0; $i < count($gpuvendorsArray); $i++) {
                $fields[
                    self::makeLabel(
                        $labelClass,
                        'inventory-gpu-vendor-' . $i,
                        sprintf(_('GPU-%d Vendor'), $i)
                    )
                ] = self::makeInput(
                    'form-control',
                    'inventory-gpu-vendor-' . $i,
                    '',
                    'text',
                    '',
                    $gpuvendorsArray[$i],
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                );
                $fields[
                    self::makeLabel(
                        $labelClass,
                        'inventory-gpu-product-' . $i,
                        sprintf(_('GPU-%d Product'), $i)
                    )
                ] = self::makeInput(
                    'form-control',
                    'inventory-gpu-product-' . $i,
                    '',
                    'text',
                    '',
                    $gpuproductsArray[$i],
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                );
            }
        }

        self::$HookManager->processEvent(
            'HOST_INVENTORY_FIELDS_' . (!$static ? 'EDITABLE' : 'STATIC'),
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        if (!$static) {
            echo self::makeFormTag(
                '',
                'host-inventory-form',
                self::makeTabUpdateURL(
                    'host-inventory',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
        }
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Host Inventory');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        if (!$static) {
            echo '</form>';
        }
    }
    /**
     * Actually submit inventory data.
     *
     * @return void
     */
    public function hostInventoryPost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['confirminventoryadd'])) {
            $pu = filter_input(INPUT_POST, 'pu');
            $other1 = filter_input(INPUT_POST, 'other1');
            $other2 = filter_input(INPUT_POST, 'other2');
            $this->obj
                ->get('inventory')
                ->set('primaryUser', $pu)
                ->set('other1', $other1)
                ->set('other2', $other2)
                ->set('hostID', $this->obj->get('id'))
                ->save();
        }
    }
    /**
     * Display Login History for Host.
     *
     * @return void
     */
    public function hostLoginHistory()
    {
        $this->renderHistoryTab(
            [
                _('Time'),
                _('Action'),
                _('Username'),
                _('Description')
            ],
            [
                [],
                [],
                [],
                []
            ],
            _('Host Login History'),
            'host-login-history-table'
        );
    }
    /**
     * Display host imaging history.
     *
     * @return void
     */
    public function hostImageHistory()
    {
        $this->renderHistoryTab(
            [
                _('Engineer'),
                _('Time'),
                _('State'),
                _('Task Type'),
                _('Image')
            ],
            [
                [],
                [],
                [],
                [],
                []
            ],
            _('Host Task History'),
            'host-image-history-table'
        );
    }
    /**
     * Display host snapin history
     *
     * @return void
     */
    public function hostSnapinHistory()
    {
        $this->renderHistoryTab(
            [
                _('Snapin Name'),
                _('Start Time'),
                _('Complete'),
                _('Duration'),
                _('Return Code')
            ],
            [
                [],
                [],
                [],
                [],
                []
            ],
            _('Host Snapin History'),
            'host-snapin-history-table'
        );
    }
    /**
     * Edits an existing item.
     *
     * @return void
     */
    public function edit()
    {
        // Identity plus the facts you cannot see from the other twenty
        // tabs: which image is assigned, when it was last imaged, and which
        // group it belongs to.
        $primaryGroup = new Group(self::minId($this->obj->get('groups')));
        $this->notes = [
            _('Host') => $this->obj->get('name'),
            _('Primary MAC') => (string)$this->obj->get('mac'),
            _('Assigned Image') => $this->obj->getImageName(),
            _('Last Deployed') => self::dateOrNever(
                $this->obj->get('deployed'),
                'hosts',
                'hostLastDeploy'
            ),
            _('Primary Group') => (
                $primaryGroup->isValid() ?
                $primaryGroup->get('name') :
                _('None')
            )
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('Host') => '#host',
            _('Assigned Image') => '#image'
        ];
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'host-general',
            'generator' => function () {
                $this->hostGeneral();
            }
        ];

        // MAC Addresses
        $tabData[] = [
            'name' => _('MAC Addresses'),
            'id' => 'host-macaddress',
            'generator' => function () {
                $this->hostMacaddress();
            }
        ];

        // Tasks
        if (!$this->obj->get('pending')) {
            $tabData[] = [
                'name' =>  _('Tasks'),
                'id' => 'host-tasks',
                'generator' => function () {
                    $this->hostTasks();
                }
            ];
        }

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Group Associations'),
                        'id' => 'host-group',
                        'generator' => function () {
                            $this->hostGroups();
                        }
                    ],
                    [
                        'name' => _('Printer Associations'),
                        'id' => 'host-printer',
                        'generator' => function () {
                            $this->hostPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapin Associations'),
                        'id' => 'host-snapin',
                        'generator' => function () {
                            $this->hostSnapins();
                        }
                    ],
                ]
            ]
        ];

        // FOG Client settings.
        $tabData[] = [
            'tabs' => [
                'name' => _('Service Settings'),
                'tabData' => [
                    [
                        'name' => _('Client Settings'),
                        'id' => 'host-module',
                        'generator' => function () {
                            $this->hostModules();
                        }
                    ],
                    [
                        'name' =>  _('Active Directory'),
                        'id' => 'host-active-directory',
                        'generator' => function () {
                            $this->adFieldsToDisplay(
                                $this->obj->get('useAD'),
                                $this->obj->get('ADDomain'),
                                $this->obj->get('ADOU'),
                                $this->obj->get('ADUser')
                            );
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'host-powermanagement',
                        'generator' => function () {
                            $this->hostPowermanagement();
                        }
                    ]
                ]
            ]
        ];

        // Inventory
        $tabData[] = [
            'name' => _('Inventory Edits'),
            'id' => 'host-inventory-edit',
            'generator' => function () {
                $this->hostInventory();
            }
        ];
        $tabData[] = [
            'name' => _('Inventory Static'),
            'id' => 'host-inventory-static',
            'generator' => function () {
                $this->hostInventory(true);
            }
        ];

        // History Items
        //
        // Login History is a movement log for named people and is gated on
        // usertracking.view, not on host.view like the rest of this page
        // (ADR 0023). The tab is dropped rather than shown-and-denied: its
        // grid would fetch and get a denial, which reads as a broken page.
        // getLoginHist has the matching SUB_OVERRIDE, so the endpoint is
        // closed whether or not the tab is drawn.
        $historyTabs = [];
        if (Authorization::can('usertracking.view')) {
            $historyTabs[] = [
                'name' => _('Login History'),
                'id' => 'host-login-history',
                'generator' => function () {
                    $this->hostLoginHistory();
                }
            ];
        }
        $tabData[] = [
            'tabs' => [
                'name' => _('History Items'),
                'tabData' => array_merge($historyTabs, [
                    [
                        // Reads taskLog since imagingLog was retired, so it
                        // is every task's states, not only imaging runs
                        // (ADR 0022 decision 3). The card inside says the
                        // same thing.
                        'name' => _('Task History'),
                        'id' => 'host-image-history',
                        'generator' => function () {
                            $this->hostImageHistory();
                        }
                    ],
                    [
                        'name' => _('Snapin History'),
                        'id' => 'host-snapin-history',
                        'generator' => function () {
                            $this->hostSnapinHistory();
                        }
                    ],
                ])
            ]
        ];
        // Site
        $tabData[] = [
            'name' => _('Site'),
            'id' => 'host-site',
            'generator' => function () {
                $this->hostSite();
            }
        ];

        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Updates the host when form is submitted
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Host',
            'HOST_EDIT',
            _('Host updated!'),
            _('Host Update Success'),
            _('Host Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'host-site':
                        $this->hostSitePost();
                        break;
                    case 'host-general':
                        $this->hostGeneralPost();
                        break;
                    case 'host-macaddress':
                        $this->hostMacaddressPost();
                        break;
                    case 'host-active-directory':
                        $this->hostADPost();
                        break;
                    case 'host-powermanagement':
                        $this->hostPowermanagementPost();
                        break;
                    case 'host-group':
                        $this->hostGroupPost();
                        break;
                    case 'host-printer':
                        $this->hostPrinterPost();
                        break;
                    case 'host-snapin':
                        $this->hostSnapinPost();
                        break;
                    case 'host-module':
                        $this->hostModulePost();
                        break;
                    case 'host-inventory':
                        $this->hostInventoryPost();
                        break;
                }
                if (!$this->obj->isValid()) {
                    throw new \Exception(_('Host is not valid!'));
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Host update failed!'));
                }
            }
        );
    }
    /**
     * The core host fields a mass edit may change.
     *
     * The set is every `hosts` column ADR 0038's disposition table sends to
     * mass edit -- the General tab's fields, the AD tab's, the Enforce tab's
     * and the printer level. Those are the ones the group page pushes today
     * through its in-band `NULL` sentinel and its tri-state selects, so they
     * are the ones the three-state replacement has to cover before anything
     * can be taken away from the group page (ADR 0038 decision 10).
     *
     * `hostBuilding` is deliberately NOT here. The `persistentgroups` trigger
     * copies it and both field maps declare it, but nothing in the tree ever
     * writes it and the only place it is read is an always-hidden export
     * column. Carrying a dead column into a new form would make it look
     * alive.
     *
     * `empty` is per field because "empty" is not one value: a varchar
     * clears to '', an image reference clears to 0. Writing '' into an int
     * column stores 0 on a permissive server and errors on a strict one, so
     * the answer belongs here rather than in a cast at the write.
     *
     * `kind` tells the form which control to draw. It carries no authority --
     * the apply path reads `field` and `empty` only -- but it lives here so
     * that the form and the whitelist cannot disagree about which fields
     * exist. `secret` marks a field the form must render EMPTY with no read
     * path: ADR 0038 decision 11 rejects the 32-asterisk placeholder the
     * group page uses, because a fake value rendered into a form has to be
     * pattern-matched back out at every call site that ever touches it.
     *
     * A field ABSENT from this map cannot be written by a mass edit at all,
     * whatever a request asks for -- FOG\Util\MassEdit::columnUpdates()
     * skips what the spec does not name. That is the map's second job and
     * the reason it is a whitelist rather than a blacklist.
     *
     * @return array key => ['field', 'empty', 'label', 'kind', 'secret']
     */
    private function massEditCoreFields()
    {
        return [
            'image' => [
                'field' => 'imageID',
                'empty' => 0,
                'label' => _('Image'),
                'kind' => 'image'
            ],
            'kernel' => [
                'field' => 'kernel',
                'empty' => '',
                'label' => _('Host Kernel'),
                'kind' => 'text'
            ],
            'kernelArgs' => [
                'field' => 'kernelArgs',
                'empty' => '',
                'label' => _('Host Kernel Arguments'),
                'kind' => 'text'
            ],
            'kernelDevice' => [
                'field' => 'kernelDevice',
                'empty' => '',
                'label' => _('Host Primary Disk'),
                'kind' => 'text'
            ],
            'init' => [
                'field' => 'init',
                'empty' => '',
                'label' => _('Host Init'),
                'kind' => 'text'
            ],
            'biosexit' => [
                'field' => 'biosexit',
                'empty' => '',
                'label' => _('Host BIOS Exit Type'),
                'kind' => 'biosexit'
            ],
            'efiexit' => [
                'field' => 'efiexit',
                'empty' => '',
                'label' => _('Host EFI Exit Type'),
                'kind' => 'efiexit'
            ],
            'productKey' => [
                'field' => 'productKey',
                'empty' => '',
                'label' => _('Product Key'),
                'kind' => 'text',
                'secret' => true
            ],
            // Printer level is a `hosts` column and so belongs to the
            // imperative half (ADR 0038 decision 1) even though the printers
            // it governs are becoming group-owned. Clearing it means level 0,
            // "no printer management", which is a real setting rather than an
            // absence -- so CLEAR and "set to 0" land on the same value on
            // purpose.
            'printerLevel' => [
                'field' => 'printerLevel',
                'empty' => 0,
                'label' => _('Host Printer Management Level'),
                'kind' => 'printerlevel'
            ],
            // The two booleans. A boolean has no meaningful "clear", so the
            // form draws them as No change / Enable on all / Disable on all
            // -- the shape the group page already gets right
            // (GroupManagement.php:1405, :1519) -- which lands here as SET
            // with '1' or '0'. `empty` is 0 so that a request that does ask
            // to clear one gets the safe answer rather than an error.
            'useAD' => [
                'field' => 'useAD',
                'empty' => 0,
                'label' => _('Join Domain after image task'),
                'kind' => 'bool'
            ],
            'enforce' => [
                'field' => 'enforce',
                'empty' => 0,
                'label' => _('Host Enforce Hostname Changes'),
                'kind' => 'bool'
            ],
            'ADDomain' => [
                'field' => 'ADDomain',
                'empty' => '',
                'label' => _('Active Directory Domain Name'),
                'kind' => 'text'
            ],
            'ADOU' => [
                'field' => 'ADOU',
                'empty' => '',
                'label' => _('Active Directory Organizational Unit'),
                'kind' => 'text'
            ],
            'ADUser' => [
                'field' => 'ADUser',
                'empty' => '',
                'label' => _('Active Directory Username'),
                'kind' => 'text'
            ],
            // Set-only. There is no read path and there is deliberately no
            // 32-asterisk placeholder: the form renders an empty password
            // input whose action defaults to LEAVE, and typing into it is
            // what selects SET. ADPass matches Redaction::CREDENTIAL_PATTERN,
            // so it is already redacted from the audit trail -- displaying it
            // back into a form editing four hundred hosts at once would be
            // the one place it leaked.
            'ADPass' => [
                'field' => 'ADPass',
                'empty' => '',
                'label' => _('Active Directory Password'),
                'kind' => 'password',
                'secret' => true
            ],
        ];
    }
    /**
     * The host settings a mass edit may change that are NOT `hosts` columns.
     *
     * Auto-logout and screen resolution live one row per host in their own
     * tables, and the group page writes each by deleting every member's row
     * and inserting a fresh one (`Group::setAlo()`, `Group::setDisp()`). That
     * shape maps onto the three states exactly: SET is delete-then-insert,
     * CLEAR is the delete on its own -- no row IS the absence of an override
     * -- and LEAVE touches nothing.
     *
     * `composite` marks the one field whose value is more than one number.
     * A resolution is width, height and refresh written as a single row, so
     * "set the width and leave the height" has no meaning at the storage
     * layer; it is one instruction carrying three parts, resolved through
     * MassEdit::resolveComposite(). See that method for why it is not a
     * `1024x768@60` string.
     *
     * Deliberately separate from massEditCoreFields(): nothing here can ever
     * be a column update, and keeping the two lists apart is what stops one
     * from being handed to columnUpdates() by accident.
     *
     * @return array key => ['label', 'kind', 'composite']
     */
    private function massEditRowFields()
    {
        return [
            'autologout' => [
                'label' => _('Auto Log Out Time (in minutes)'),
                'kind' => 'number'
            ],
            'resolution' => [
                'label' => _('Host Screen Resolution'),
                'kind' => 'resolution',
                'composite' => true
            ],
        ];
    }

    /**
     * Applies the row-backed instructions, and says how many hosts it wrote.
     *
     * Each arm is delete-then-insert over the whole selection rather than a
     * loop, so it is two statements per field regardless of how many hosts
     * were picked -- the same reason the column half is one UPDATE.
     *
     * @param array $resolved the resolved row-backed instructions
     * @param array $hostIDs  the selected host ids, already scope-checked
     *
     * @return int the number of hosts written, 0 if nothing was asked for
     */
    private function massEditApplyRows(array $resolved, array $hostIDs)
    {
        $wrote = 0;
        $alo = $resolved['autologout'] ?? null;
        if (null !== $alo && MassEdit::LEAVE !== $alo['action']) {
            Route::deletemass('hostautologout', ['hostID' => $hostIDs]);
            if (MassEdit::SET === $alo['action']) {
                // Below the minimum means "off", which is how the group
                // page has always read it. A blank SET is therefore 0 rather
                // than an error, and CLEAR -- the delete with no insert -- is
                // the same outcome expressed as an absent row.
                $minutes = (int)$alo['value'];
                if ($minutes < HostAutoLogout::MIN_MINUTES) {
                    $minutes = 0;
                }
                $rows = [];
                foreach ($hostIDs as $hostID) {
                    $rows[] = [$hostID, $minutes];
                }
                self::getClass('HostAutoLogoutManager')
                    ->insertBatch(['hostID', 'time'], $rows);
            }
            $wrote = count($hostIDs);
        }

        $res = $resolved['resolution'] ?? null;
        if (null !== $res && MassEdit::LEAVE !== $res['action']) {
            Route::deletemass('hostscreensetting', ['hostID' => $hostIDs]);
            if (MassEdit::SET === $res['action']) {
                $x = (int)($res['value']['x'] ?? 0);
                $y = (int)($res['value']['y'] ?? 0);
                $r = (int)($res['value']['r'] ?? 0);
                $rows = [];
                foreach ($hostIDs as $hostID) {
                    $rows[] = [$hostID, $x, $y, $r];
                }
                self::getClass('HostScreenSettingManager')
                    ->insertBatch(
                        ['hostID', 'width', 'height', 'refresh'],
                        $rows
                    );
            }
            $wrote = count($hostIDs);
        }

        return $wrote;
    }

    /**
     * The plugin-contributed field keys a mass edit may act on.
     *
     * HOST_MASSEDIT_FIELDS is the CONTRIBUTION seam: a plugin adds a key, a
     * label and a value control, and core renders the three-state action
     * control around it. The action control is core's on purpose -- a plugin
     * that rendered its own could ship a two-state field, and a two-state
     * field in a mass edit is the defect ADR 0038 decision 11 is entirely
     * about.
     *
     * This is also what makes the apply side safe: a key is actionable only
     * because a plugin declared it here, so a request cannot name one that
     * nothing offered.
     *
     * The wider point (decision 13): the ABI already has a bulk READ seam
     * (API_MASSDATA_MAPPING) and a bulk DELETE seam (DELETEMASS_API) and no
     * bulk EDIT seam. Between them sat the operation neither covers --
     * changing a value across a set -- and its absence is why `location` and
     * `ou` each ship a whole second hook file whose only job is to set one
     * value across many hosts, always clobbering, unable to express "leave
     * alone" at all.
     *
     * @return array key => ['label' => ..., 'input' => <html>]
     */
    private function massEditPluginFields()
    {
        $fields = [];
        self::$HookManager->processEvent(
            'HOST_MASSEDIT_FIELDS',
            ['fields' => &$fields]
        );

        return $fields;
    }
    /**
     * Applies a three-state edit to a selection of hosts.
     *
     * ADR 0038 decisions 11 and 12. The three-state resolution itself lives
     * in FOG\Util\MassEdit and fails closed; this is the endpoint around it.
     *
     * ONE STATEMENT, ONE AUDIT ROW. The host columns go out as a single
     * UPDATE ... WHERE hostID IN (...), so four hundred hosts is one
     * statement rather than four hundred: it succeeds or fails whole, and
     * "a 400-row loop that times out" is not a risk this path has. Per ADR
     * 0021 decision 11 that is ONE authorized action and therefore one audit
     * header with affectedCount, never a header per host. And per ADR 0021
     * decision 5 it carries NO auditChange rows, because a bulk update has
     * no before/after to record -- that gap is named in the ADR rather than
     * being a bug here.
     *
     * @return void
     */
    public function massEditPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        try {
            $hosts = filter_input(
                INPUT_POST,
                'hosts',
                FILTER_DEFAULT,
                FILTER_REQUIRE_ARRAY
            );
            $hosts = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array)$hosts)
                    )
                )
            );
            if (count($hosts) < 1) {
                throw new \Exception(_('No hosts are selected'));
            }
            // Airtight: one id outside the caller's site scope denies the
            // whole request rather than quietly editing the rest. The ids
            // come from the browser, so this is the only place they are
            // bounded -- same stance, and the same call, as
            // deployMultiPost() and saveGroup().
            Authorization::requirePageObjectScopeMass('host', $hosts);

            $coreFields = $this->massEditCoreFields();
            $rowFields = $this->massEditRowFields();
            $pluginFields = $this->massEditPluginFields();
            // Core wins a key collision. A plugin cannot capture `image` and
            // quietly redirect what the Image control writes, which it could
            // if the arrays were merged the other way round.
            $keys = array_values(
                array_unique(
                    array_merge(
                        array_keys($coreFields),
                        array_keys($pluginFields)
                    )
                )
            );
            // The row-backed fields are resolved separately by shape, not
            // merged into $keys: the composite one has an array value, and
            // resolve()'s safety property is that an array is never a value.
            $scalarRows = [];
            $compositeRows = [];
            foreach ($rowFields as $key => $rowSpec) {
                if (!empty($rowSpec['composite'])) {
                    $compositeRows[] = $key;
                } else {
                    $scalarRows[] = $key;
                }
            }

            $flags = ['flags' => FILTER_REQUIRE_ARRAY];
            $posted = filter_input_array(
                INPUT_POST,
                ['action' => $flags, 'value' => $flags]
            );
            $resolved = MassEdit::resolve(
                $keys,
                $posted['action'] ?? null,
                $posted['value'] ?? null
            );
            $resolvedRows = array_merge(
                MassEdit::resolve(
                    $scalarRows,
                    $posted['action'] ?? null,
                    $posted['value'] ?? null
                ),
                MassEdit::resolveComposite(
                    $compositeRows,
                    $posted['action'] ?? null,
                    $posted['value'] ?? null
                )
            );
            $touched = array_merge(
                MassEdit::touched($resolved),
                MassEdit::touched($resolvedRows)
            );
            if (count($touched) < 1) {
                // Refused rather than treated as a no-op success. A form
                // that reports "12 hosts updated" having changed nothing is
                // how somebody concludes the edit landed and moves on.
                throw new \Exception(_('No fields were set to change'));
            }

            $updates = MassEdit::columnUpdates($resolved, $coreFields);
            $affected = 0;
            if (count($updates) > 0) {
                // One statement over the whole selection. Guarded on being
                // non-empty because an UPDATE with no assignments is either
                // a syntax error or, worse, a statement whose WHERE is the
                // only part left.
                self::getClass('HostManager')
                    ->update(['id' => $hosts], '', $updates);
                $affected = count($hosts);
            }
            // Row-backed fields count toward the same affectedCount: one
            // authorized action is one audit header (ADR 0021 decision 11),
            // and a submission that only changed the resolution still
            // changed every selected host.
            $affected = max($affected, $this->massEditApplyRows($resolvedRows, $hosts));

            // The plugin half receives the RESOLVED actions for its own keys
            // only, already reduced to leave/set/clear. A plugin never parses
            // a sentinel, because there is no sentinel to parse.
            $forPlugins = array_intersect_key(
                $resolved,
                $pluginFields
            );
            if (count($forPlugins) > 0) {
                self::$HookManager->processEvent(
                    'HOST_MASSEDIT_APPLY',
                    [
                        'hostIDs' => &$hosts,
                        'actions' => &$forPlugins
                    ]
                );
            }

            Audit::record(
                [
                    'type' => 'host.massedit',
                    'subjectType' => 'host',
                    'subjectLabel' => implode(', ', $touched),
                    'permission' => 'host.edit',
                    'affectedCount' => $affected,
                    'renderable' => 1
                ]
            );

            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => sprintf(
                        _('Updated %1$d field(s) on %2$d host(s).'),
                        count($touched),
                        count($hosts)
                    ),
                    'title' => _('Mass Edit Success')
                ]
            );
        } catch (\Exception $e) {
            // Always a 400. Every throw above is a bad request -- no hosts,
            // nothing to change, or a scope refusal -- so there is no
            // server-fault branch to carry. deployMultiPost() has one
            // because it can fail on a task type the server is missing;
            // this cannot.
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Mass Edit Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Saves host to a selected or new group depending on action.
     *
     * @return void
     */
    public function saveGroup()
    {
        // This handler mutates and was reachable without either -- a session
        // cookie alone was enough, from any origin. Every other mutating
        // handler on this page calls it; saveGroup was missed because the
        // router's CSRF gate keys off a *Post/*Ajax suffix and this method
        // has neither (ADR 0038 decision 16a, UNKNOWN-6).
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            [
                'groups' => $flags,
                'hosts' => $flags,
                'groups_new' => $flags
            ]
        );
        // Normalized to ints before anything is bounded by them: the ids come
        // from the browser, and requirePageObjectScopeMass() below is the only
        // place they are checked. Same shape as deployMultiPost().
        $ids = function ($v) {
            return array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array)$v)
                    )
                )
            );
        };
        $groups = $ids($items['groups']);
        $hosts = $ids($items['hosts']);
        // Names, not ids -- deliberately not passed through $ids().
        $groups_new = array_values(
            array_filter(
                array_map('trim', (array)($items['groups_new'] ?: [])),
                function ($name) {
                    return '' !== $name;
                }
            )
        );
        // Airtight, both directions: one host outside the caller's site scope
        // denies the whole request rather than quietly adding the rest, and
        // the same for the target groups. A site-scoped operator could
        // previously add ANY host to ANY group from here, because neither
        // side was bounded.
        Authorization::requirePageObjectScopeMass('host', $hosts);
        Authorization::requirePageObjectScopeMass('group', $groups);
        // The body-dependent half of the permission split. The route requires
        // group.edit (Authorization::SUB_OVERRIDES); creating a group needs
        // group.create as well, and only when the request actually asks for
        // one. Checked here rather than at the route because the route cannot
        // see the body.
        if (count($groups_new) && !Authorization::can('group.create')) {
            $this->jsonSend(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(
                    [
                        'error' => _(
                            'You do not have permission to create groups.'
                        ),
                        'title' => _('Add Hosts to Group Fail')
                    ]
                )
            );
        }
        try {
            if (!count($hosts)) {
                throw new \Exception(_('No hosts selected to be added'));
            }
            if (!count($groups) && !count($groups_new)) {
                throw new \Exception(_('No groups are being created or selected'));
            }
            if (count($groups)) {
                foreach ($groups as &$group) {
                    $Group = new Group($group);
                    if (!$Group->isValid()) {
                        continue;
                    }
                    $Group->addHost($hosts)->save();
                    unset($group);
                }
            }
            if (count($groups_new)) {
                foreach ($groups_new as &$group) {
                    self::getClass('Group')
                        ->set('name', $group)
                        ->addHost($hosts)
                        ->save();
                    unset($group);
                }
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Successfully added hosts to the provided groups!'),
                    'title' => _('Add Hosts to Groups Success')
                ]
            );
        } catch (\Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Add Hosts to Group Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Presents the groups list table.
     *
     * @return void
     */
    public function getGroupsList()
    {
        return $this->assocItemsList(
            'group',
            'groupassociation',
            'groupMembers',
            '`groups`.`groupID`',
            '`groupMembers`.`gmGroupID`',
            '`groupMembers`.`gmHostID`',
            [
                [
                    'db' => 'hostAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Presents the printers list table.
     *
     * @return void
     */
    public function getPrintersList()
    {
        return $this->assocItemsList(
            'printer',
            'printerassociation',
            'printerAssoc',
            '`printers`.`pID`',
            '`printerAssoc`.`paPrinterID`',
            '`printerAssoc`.`paHostID`',
            [
                [
                    'db' => 'hostAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Presents the snapins list table.
     *
     * @return void
     */
    public function getSnapinsList()
    {
        return $this->assocItemsList(
            'snapin',
            'snapinassociation',
            'snapinAssoc',
            '`snapins`.`sID`',
            '`snapinAssoc`.`saSnapinID`',
            '`snapinAssoc`.`saHostID`',
            [
                [
                    'db' => 'hostAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Returns the module list as well as the associated
     * for the host being edited.
     *
     * @return void
     */
    public function getModulesList()
    {
        $moduleName = self::getGlobalModuleStatus();
        $keys = [];
        foreach ((array)$moduleName as $short_name => $bool) {
            if ($bool) {
                $keys[] = $short_name;
            }
        }
        $notWhere = [
            'clientupdater',
            'dircleanup',
            'usercleanup'
        ];

        $where = "`modules`.`short_name` "
            . "NOT IN ('"
            . implode("','", $notWhere)
            . "') AND `modules`.`short_name` IN ('"
            . implode("','", $keys)
            . "')";

        $join = [
            'LEFT OUTER JOIN `moduleStatusByHost` '
            . "ON `modules`.`id` = `moduleStatusByHost`.`msModuleID` "
            . "AND `moduleStatusByHost`.`msHostID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'hostAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'module',
            'moduleassociation',
            $join,
            $where,
            $columns
        );
    }
    /**
     * Resolves a typed MAC address to its hardware vendor (OUI) name.
     *
     * Read-only AJAX helper for the live vendor icon shown next to MAC inputs
     * on the host create/edit forms. Returns {"vendor": "..."} (empty when the
     * prefix is unknown or the oui table has not been loaded).
     *
     * @return void
     */
    public function getmacman()
    {
        header('Content-type: application/json');
        $prefix = filter_input(INPUT_GET, 'prefix');
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['vendor' => MACAddress::getVendor($prefix)]));
    }
    /**
     * Get's the hosts mac address list.
     *
     * @return void
     */
    public function getMacaddressesList()
    {
        Route::listem(
            'macaddressassociation',
            ['hostID' => $this->obj->get('id')]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Get pending host list.
     *
     * @return void
     */
    public function getPendingList()
    {
        Route::listem(
            'host',
            ['pending' => 1]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Get pending mac list.
     *
     * @return void
     */
    public function getPendingMacList()
    {
        Route::listem(
            'macaddressassociation',
            ['pending' => 1]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Gets the current list of power management tasks.
     *
     * @return void
     */
    public function getPowermanagementList()
    {
        Route::listem(
            'powermanagement',
            ['hostID' => $this->obj->get('id')]
        );
        echo Route::getData();
        exit;
    }
    /**
     * The host tasks items.
     *
     * @return void
     */
    public function hostTasks()
    {
        global $id;

        $accordion = $this->taskTypeAccordion(
            ['host', 'both'],
            function ($TaskType) use ($id) {
                return '<a href="?node=host&sub=deploy&id='
                    . $id
                    . '&type='
                    . $TaskType->id
                    . '" class="taskitem"><i class="fas fa-'
                    . $TaskType->icon
                    . ' fa-2x"></i><br/>'
                    . $TaskType->name
                    . '</a>';
            },
            'taskAccordian',
            'HOST_BASICTASKS_DATA',
            'HOST_ADVANCEDTASKS_DATA'
        );

        $modalApprovalBtns = self::makeButton(
            'tasking-send',
            _('Create'),
            'btn btn-outline-secondary float-end'
        );
        $modalApprovalBtns .= self::makeButton(
            'tasking-close',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $taskModal = self::makeModal(
            'task-modal',
            '<h4 class="card-title">'
            . _('Create new tasking')
            . '<span class="task-name"></span></h4>',
            '<div id="task-form-holder"></div>',
            $modalApprovalBtns,
            '',
            'success'
        );

        echo '<div class="card" id="host-tasks">';
        echo '<div class="card-body">';
        echo $accordion;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $taskModal;
        echo '</div>';
        echo '</div>';
    }
    /**
     * The Queue Task control for the host list: one button, one modal.
     *
     * Called by FOGPage::process() when the page defines it, the same way
     * addModal() is picked up, so the generic list toolbar stays generic.
     *
     * The modal holds the same Basic/Advanced accordion the host edit page
     * shows, built from the same taskTypes rows. What differs is the anchor:
     * no `id` in the href, because which hosts are tasked is whatever is
     * ticked at the moment of the click, and only the browser knows that.
     * Each row carries its `access` value instead, which is what lets the
     * script show Multi-Cast only when more than one row is selected (it is
     * `ttIsAccess = 'group'`) and Capture only when exactly one is (it is
     * `ttIsAccess = 'host'`).
     *
     * @return array ['button' => string, 'modal' => string]
     */
    public function queueTaskActions()
    {
        $accordion = $this->taskTypeAccordion(
            ['host', 'group', 'both'],
            function ($TaskType) {
                return '<a href="#" class="taskitem queuetaskitem" '
                    . 'data-type="' . (int)$TaskType->id . '" '
                    . 'data-access="'
                    . \Initiator::e($TaskType->access)
                    . '"><i class="fas fa-'
                    . \Initiator::e($TaskType->icon)
                    . ' fa-2x"></i><br/>'
                    . \Initiator::e($TaskType->name)
                    . '</a>';
            },
            'queueTaskAccordion',
            'HOST_BASICTASKS_DATA',
            'HOST_ADVANCEDTASKS_DATA'
        );

        // Green, not gray. Tasking is a genuinely different operation from
        // the two edit actions beside it -- it starts work on machines
        // rather than changing a record -- and the convention for that is
        // btn-success, the same reason image replication's Resume Reload is
        // green next to a blue Create. It also stops the toolbar reading as
        // two identical gray buttons: the cluster is now
        // [Queue Task (success)][Add to group (secondary)][Add (primary)],
        // three distinguishable actions with the commit still rightmost.
        $button = self::makeButton(
            'queueTask',
            _('Queue Task'),
            'btn btn-success'
        );

        $modal = self::makeModal(
            'queueTaskModal',
            '<h4 class="card-title">'
            . _('Queue task on selected hosts')
            . '<span class="queue-task-name"></span></h4>',
            '<div id="queue-task-picker">' . $accordion . '</div>'
            . '<div id="queue-task-form-holder"></div>',
            self::makeButton(
                'queueTaskClose',
                _('Cancel'),
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                'queueTaskSend',
                _('Create'),
                'btn btn-primary float-end d-none'
            ),
            '',
            'success',
            'modal-lg'
        );

        return [
            'button' => $button,
            'modal' => $modal,
            'quick' => $this->_quickTaskItems()
        ];
    }
    /**
     * The one-click task types offered beside the grid's own controls.
     *
     * Data only -- the buttons themselves are built by the browser, because
     * they live in the DataTables button bar under the search box and that
     * bar does not exist until the grid initializes. What the server owns is
     * the part the browser cannot know: which of the three types this
     * install actually has, what each is called in the reader's language,
     * which icon it carries, and whether the reader may task at all.
     *
     * Three types and no more. Deploy and Capture are the pair a single
     * host wants; Deploy and Multi-Cast are the pair a set of hosts wants.
     * Anything else stays behind the Queue Task button, which is where a
     * task that needs options -- a snapin choice, an account to reset, a
     * debug session -- belongs. These three take no options, which is the
     * whole reason they can be one click.
     *
     * `access` rides along rather than being hardcoded in the script: it is
     * the same ttIsAccess the Queue Task modal filters on and the same value
     * assertSelectionTaskable() refuses on, so all three agree by
     * construction. 'host' shows only for one selected row (Capture),
     * 'group' only for two or more (Multi-Cast), 'both' for any (Deploy).
     *
     * @return string
     */
    private function _quickTaskItems()
    {
        // ?node=host&sub=deployMulti resolves to host.task
        // (Authorization::_subToAction maps the 'deploy' prefix), so a user
        // without it would be shown three buttons that can only be refused.
        if (!Authorization::can('host.task')) {
            return '';
        }

        $items = '';
        $types = [TaskType::DEPLOY, TaskType::CAPTURE, TaskType::MULTICAST];
        foreach ($types as $typeId) {
            $TaskType = self::getClass('TaskType', $typeId);
            // A server whose taskTypes row was deleted simply loses that
            // button, the same way the accordion loses the entry.
            if (!$TaskType->isValid()) {
                continue;
            }
            // get(), not ->prop. These are FOGController instances built
            // by getClass(); the class has no __get, so a property read
            // answers null and the whole span comes out blank -- which is
            // exactly what the first cut of this did. The accordion above
            // reads properties because ITS objects come from
            // Route::getList(), which hands back plain decoded objects.
            $items .= '<span class="quicktaskitem" '
                . 'data-type="' . (int)$TaskType->get('id') . '" '
                . 'data-access="' . \Initiator::e($TaskType->get('access'))
                . '" '
                . 'data-icon="' . \Initiator::e($TaskType->get('icon')) . '" '
                . 'data-name="' . \Initiator::e($TaskType->get('name'))
                . '"></span>';
        }
        if ('' === $items) {
            return '';
        }

        return '<div id="quick-task-data" class="d-none">' . $items . '</div>';
    }
    /**
     * The task options form for an ad-hoc selection of hosts.
     *
     * Deliberately takes a COUNT and not the ids. The form does not vary by
     * which hosts were picked -- only by task type -- and putting a few
     * hundred ids in a query string to build a form that ignores them buys
     * nothing and eventually exceeds the request line. The ids are posted to
     * deployMultiPost(), which is where they are checked and used.
     *
     * The count is not decoration: it decides whether the chosen type is
     * even applicable. `ttIsAccess = 'group'` (Multi-Cast) needs more than
     * one host to mean anything, and `ttIsAccess = 'host'` (Capture) needs
     * exactly one -- capturing an image from several machines at once is not
     * a thing. The script hides those entries, and this refuses them, so a
     * hand-made request gets the same answer as the UI.
     *
     * @return void
     */
    public function deployMulti()
    {
        header('Content-type: application/json');
        global $type;

        try {
            if (!is_numeric($type) || $type < 1) {
                $type = 1;
            }
            $count = (int)filter_input(INPUT_GET, 'count');
            if ($count < 1) {
                throw new \Exception(_('No hosts are selected'));
            }

            $TaskType = self::getClass('TaskType', $type);
            if (!$TaskType->isValid()) {
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        $type
                    )
                );
            }
            $this->assertSelectionTaskable($TaskType, $count);

            $this->title = $TaskType->get('name');

            $labelClass = 'col-sm-3 col-form-label';
            $fields = $this->taskingOptionFields($type, $labelClass);

            $buttons = '';
            self::$HookManager->processEvent(
                'HOST_CREATE_TASKING',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Host' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);
            ob_start();
            echo self::makeFormTag(
                '',
                'host-deploy-multi-form',
                '../management/index.php?node=host&sub=deployMulti&type='
                . (int)$type,
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo $buttons;
            echo '</form>';
            $msg = json_encode(
                [
                    'msg' => ob_get_clean(),
                    'title' => _('Create task form success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
        } catch (\Exception $e) {
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create task form fail')
                ]
            );
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Refuses a task type the selection size cannot support.
     *
     * Shared by the form builder and the create, so a request that skips the
     * UI is answered the same way the UI would have.
     *
     * @param object $TaskType the task type being created
     * @param int    $count    how many hosts are selected
     *
     * @throws \Exception
     *
     * @return void
     */
    private function assertSelectionTaskable($TaskType, $count)
    {
        $access = $TaskType->get('access');
        if ('group' === $access && $count < 2) {
            throw new \Exception(
                sprintf(
                    _('%s needs more than one host'),
                    $TaskType->get('name')
                )
            );
        }
        if ('host' === $access && $count > 1) {
            throw new \Exception(
                sprintf(
                    _('%s can only be run on one host at a time'),
                    $TaskType->get('name')
                )
            );
        }
    }
    /**
     * Creates the tasking for an ad-hoc selection of hosts.
     *
     * Instant only. A scheduled task is one `scheduledTasks` row carrying a
     * single hostID plus an isGroupTask flag, so it can name a host or a
     * group and has nowhere to put a set of hosts that is neither. Giving it
     * one is a schema change; until then the list creates tasks now and the
     * edit page still schedules them per host or per group.
     *
     * The work itself goes through Group::createImagePackage() on a Group
     * that is built here and never saved. That is not a shortcut around the
     * host path -- it is the only path that gets Multi-Cast right. One
     * multicast session has to cover the whole selection: one port, one
     * sender, one row per host in multicastSessionsAssoc. Looping
     * Host::createImagePackage() would create a separate session per host
     * and none of them would ever stream.
     *
     * @return void
     */
    public function deployMultiPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('HOST_DEPLOY_POST');

        $serverFault = false;
        try {
            global $type;
            if (!is_numeric($type) || $type < 1) {
                $type = 1;
            }

            $hosts = filter_input(
                INPUT_POST,
                'hosts',
                FILTER_DEFAULT,
                FILTER_REQUIRE_ARRAY
            );
            $hosts = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array)$hosts)
                    )
                )
            );
            if (count($hosts) < 1) {
                throw new \Exception(_('No hosts are selected'));
            }
            // Airtight: one id outside the caller's site scope denies the
            // whole request rather than quietly tasking the rest. The ids
            // come from the browser, so this is the only place they are
            // bounded.
            Authorization::requirePageObjectScopeMass('host', $hosts);

            $TaskType = self::getClass('TaskType', $type);
            if (!$TaskType->isValid()) {
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        $type
                    )
                );
            }
            $this->assertSelectionTaskable($TaskType, count($hosts));

            // Pending hosts cannot be tasked -- the same refusal the single
            // host path makes, applied to the selection.
            $hosts = Route::getIds(
                'host',
                [
                    'id' => $hosts,
                    'pending' => ['', 0]
                ]
            );
            if (count($hosts ?: []) < 1) {
                throw new \Exception(_('No hosts available to be tasked'));
            }

            if ($TaskType->isImagingTask()) {
                $images = [];
                $withImage = [];
                foreach (Route::getList('host', ['id' => $hosts]) as $Host) {
                    if (!$Host->imageID) {
                        continue;
                    }
                    $withImage[] = (int)$Host->id;
                    $images[] = (int)$Host->imageID;
                }
                if (count($withImage) < 1) {
                    throw new \Exception(_('No hosts are assigned an image'));
                }
                // One session, one image. Checked here rather than left to
                // the replicator, which would otherwise start a stream the
                // odd host out can never use.
                if (TaskType::MULTICAST == $type
                    && count(array_unique($images)) !== 1
                ) {
                    throw new \Exception(
                        _('All hosts must have the same image assigned')
                    );
                }
                $hosts = $withImage;
            }

            // Password reset setup
            $passreset = trim(
                (string)filter_input(INPUT_POST, 'account')
            );
            if (TaskType::PASSWORD_RESET == $type
                && !$passreset
            ) {
                throw new \Exception(_('Password reset requires a user account'));
            }

            // Snapin setup
            $enableSnapins = (int)filter_input(INPUT_POST, 'snapin');
            if (0 === $enableSnapins) {
                $enableSnapins = -1;
            }
            if (TaskType::DEPLOY_NO_SNAPINS === $type || $enableSnapins < -1) {
                $enableSnapins = 0;
            }
            $snapinAbortOnFailure = isset($_POST['snapinAbortOnFailure']);

            $enableShutdown = isset($_POST['shutdown']);

            $enableDebug = isset($_POST['debug']) || isset($_POST['isDebugTask']);

            $wol = (TaskType::WAKE_UP == $type) || isset($_POST['wol']);

            // The carrier for the selection. Never saved -- nothing reads it
            // back and a groups row would outlive the tasking it exists for.
            // Group::loadHosts() short circuits on an unsaved group, so the
            // ids set here are the ids used.
            $Selection = self::getClass('Group')
                ->set(
                    'name',
                    sprintf(
                        /* translators: %d is a number of hosts */
                        ngettext(
                            '%d selected host',
                            '%d selected hosts',
                            count($hosts)
                        ),
                        count($hosts)
                    )
                )
                ->set('hosts', $hosts);

            // getItem(), not indiv(): a deleted task type used to end the
            // response with a 404 rather than report it. Refs ADR 0011.
            $tasktype = Route::getItem('tasktype', $type);
            if (!$tasktype) {
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        $type
                    )
                );
            }
            $Selection->createImagePackage(
                $tasktype,
                sprintf('%s Task', $TaskType->get('name')),
                $enableShutdown,
                $enableDebug,
                $enableSnapins,
                true,
                self::$FOGUser->get('name'),
                $passreset,
                false,
                $wol,
                false,
                $snapinAbortOnFailure
            );

            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'HOST_DEPLOY_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => sprintf(
                        // ngettext, not _(): a single-host tasking is by
                        // far the commonest thing this method does, and it
                        // used to answer "Tasking created for 1 hosts".
                        // The count is passed twice on purpose -- once to
                        // choose the form, once for sprintf to interpolate.
                        /* translators: %d is a number of hosts */
                        ngettext(
                            'Tasking created for %d host',
                            'Tasking created for %d hosts',
                            count($hosts)
                        ),
                        count($hosts)
                    ),
                    'title' => _('Create Task Success')
                ]
            );
        } catch (\Exception $e) {
            // Always 400, unlike deployPost(). Everything this method can
            // refuse is a property of the REQUEST -- an empty or
            // out-of-scope selection, a task type the selection size cannot
            // support, hosts with no image -- and all of it is checked
            // before anything is written. What createImagePackage() itself
            // throws is configuration the caller has to fix as well (image
            // not enabled, not replicated to the group), so there is no arm
            // here that means "the server failed". $serverFault stays in the
            // hook payload because a listener may still set it.
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'HOST_DEPLOY_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create Task Fail')
                ]
            );
        }

        $this->jsonHookResponse(
            [
                'Host' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Tasking for this host.
     *
     * @return void
     */
    public function deploy()
    {
        header('Content-type: application/json');
        global $type;
        global $id;

        try {
            if (!is_numeric($type) || $type < 1) {
                $type = 1;
            }
            // getItem(), not indiv(): a deleted task type used to end the
            // response with a 404 rather than report it. Refs ADR 0011.
            $TaskType = Route::getItem('tasktype', $type);
            if (!$TaskType) {
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        $type
                    )
                );
            }

            $this->title = $TaskType->name
                . ' '
                . $this->obj->get('name');

            $imagingTypes = $TaskType->isImagingTask;

            $iscapturetask = $TaskType->isCapture;

            $isdebug = $TaskType->isDebug;

            $image = $this->obj->getImage();

            if ($this->obj->get('pending') > 0) {
                throw new \Exception(_('Cannot task pending hosts'));
            }
            if ($imagingTypes
                && !$image->isValid()
            ) {
                throw new \Exception(_('Assigned image is invalid'));
            }
            if ($imagingTypes
                && $image->get('isEnabled') < 1
            ) {
                throw new \Exception(_('Assigned image is not enabled'));
            }
            if ($imagingTypes
                && $iscapturetask
                && $image->get('protected')
            ) {
                throw new \Exception(_('Assigned image is protected'));
            }
            $labelClass = 'col-sm-3 col-form-label';
            // Shared with GroupManagement::deploy() and deployMulti(); the
            // three forms differ only in which hosts they apply to.
            $fields = $this->taskingOptionFields($type, $labelClass);
            $fields = self::fastmerge(
                $fields,
                $this->scheduleTypeFields($labelClass, $isdebug, $type)
            );

            // $buttons is echoed below, after the rendered fields. It was
            // passed here by reference while being neither initialized nor
            // rendered, so the argument this hook advertises did nothing and
            // said nothing. Empty by default -- the modal's own Create button
            // lives in its static footer, not in this fragment.
            $buttons = '';
            self::$HookManager->processEvent(
                'HOST_CREATE_TASKING',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Host' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);
            ob_start();
            echo self::makeFormTag(
                '',
                'host-deploy-form',
                $this->formAction,
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo $buttons;
            echo '</form>';
            $msg = json_encode(
                [
                    'msg' => ob_get_clean(),
                    'title' => _('Create task form success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
        } catch (\Exception $e) {
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create task form fail')
                ]
            );
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Actually creates the tasking.
     *
     * @return void
     */
    public function deployPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('HOST_DEPLOY_POST');

        $serverFault = false;
        try {
            global $type;
            if (!is_numeric($type) && $type > 0) {
                $type = 1;
            }

            // getItem(), not indiv(): a deleted task type used to end the
            // response with a 404 rather than report it. Refs ADR 0011.
            $TaskType = Route::getItem('tasktype', $type);
            if (!$TaskType) {
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        $type
                    )
                );
            }
            // Pending check.
            if ($this->obj->get('pending')) {
                throw new \Exception(_('Pending hosts cannot be tasked'));
            }
            // Password reset setup
            $passreset = trim(
                (string)filter_input(INPUT_POST, 'account')
            );
            if (TaskType::PASSWORD_RESET == $TaskType->id
                && !$passreset
            ) {
                throw new \Exception(_('Password reset requires a user account'));
            }

            // Snapin setup
            $enableSnapins = (int)filter_input(INPUT_POST, 'snapin');
            if (0 === $enableSnapins) {
                $enableSnapins = -1;
            }
            if (TaskType::DEPLOY_NO_SNAPINS === $TaskType->id
                || $enableSnapins < -1
            ) {
                $enableSnapins = 0;
            }
            $snapinAbortOnFailure = isset($_POST['snapinAbortOnFailure']);

            // Generic setup
            $imagingTasks = $TaskType->isImagingTask;
            $taskName = sprintf(
                '%s Task',
                $TaskType->name
            );

            // Shutdown setup
            $shutdown = isset($_POST['shutdown']);
            $enableShutdown = false;
            if ($shutdown) {
                $enableShutdown = true;
            }

            // Debug setup
            $enableDebug = false;
            $debug = isset($_POST['debug']);
            $isdebug = isset($_POST['isDebugTask']);
            if ($debug || $isdebug) {
                $enableDebug = true;
            }

            // Bypass Bitlocker
            $bypassbitlocker = isset($_POST['bitlocker']);

            // WOL setup
            $wol = false;
            $wolon = isset($_POST['wol']);
            if (TaskType::WAKE_UP == $type || $wolon) {
                $wol = true;
            }

            // Schedule Type setup + Delayed/Cron checks.
            [
                'scheduleType' => $scheduleType,
                'scheduleDeployTime' => $scheduleDeployTime,
                'min' => $min,
                'hour' => $hour,
                'dom' => $dom,
                'month' => $month,
                'dow' => $dow
            ] = $this->validateScheduleType();

            // Task Type Imaging Checks.
            if ($TaskType->isImagingTask) {
                $Image = $this->obj->getImage();
                if (!$Image->isValid()) {
                    throw new \Exception(_('Image is invalid'));
                }
                if (!$Image->get('isEnabled')) {
                    throw new \Exception(_('Image is not enabled'));
                }
                if ($TaskType->isCapture
                    && $Image->get('protected')
                ) {
                    throw new \Exception(_('Image is protected'));
                }
            }

            // Actually create tasking
            if ($scheduleType == 'instant') {
                $this->obj->createImagePackage(
                    $TaskType,
                    $taskName,
                    $enableShutdown,
                    $enableDebug,
                    $enableSnapins,
                    false,
                    self::$FOGUser->get('name'),
                    $passreset,
                    false,
                    $wol,
                    $bypassbitlocker,
                    $snapinAbortOnFailure
                );
            } else {
                $ScheduledTask = self::getClass('ScheduledTask')
                    ->set('taskTypeID', $TaskType->id)
                    ->set('name', $taskName)
                    ->set('hostID', $this->obj->get('id'))
                    ->set('shutdown', $enableShutdown)
                    ->set('other2', $enableSnapins)
                    ->set('other1', (int)$snapinAbortOnFailure)
                    ->set('type', 'single' == $scheduleType ? 'S' : 'C')
                    ->set('isGroupTask', 0)
                    ->set('other3', self::$FOGUser->get('name'))
                    ->set('isActive', 1)
                    ->set('other4', $wol)
                    ->set('other5', $bypassbitlocker);
                if ($scheduleType == 'single') {
                    $ScheduledTask->set(
                        'scheduleTime',
                        $scheduleDeployTime->getTimestamp()
                    );
                } elseif ($scheduleType == 'cron') {
                    $ScheduledTask
                        ->set('minute', $min)
                        ->set('hour', $hour)
                        ->set('dayOfMonth', $dom)
                        ->set('month', $month)
                        ->set('dayOfWeek', $dow);
                }
                if (!$ScheduledTask->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Failed to create scheduled task'));
                }
            }

            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'HOST_DEPLOY_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Create tasking succeeded'),
                    'title' => _('Create Task Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'HOST_DEPLOY_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create Task Fail')
                ]
            );
        }

        $this->jsonHookResponse(
            [
                'Host' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Get the login history for this host.
     *
     * @return void
     */
    public function getLoginHist()
    {
        $this->renderHistoryData($this->obj->get('id'), 'usertracking');
    }
    /**
     * Get the image history for this host.
     *
     * @return void
     */
    public function getImageHist()
    {
        // taskLog since imagingLog was retired (ADR 0022 decision 3). The
        // tab shows more than it used to: every state a task passed through,
        // failures included, where imagingLog only ever held runs that had
        // been started and the last one to start at that.
        $this->renderHistoryData($this->obj->get('id'), 'tasklog');
    }
    /**
     * Get the snapin history for this host.
     *
     * @return void
     */
    public function getSnapinHist()
    {
        $this->renderSnapinHistoryData($this->obj->get('id'));
    }
    /**
     * Get the hosts display man values
     *
     * @return void
     */
    public function getHostDisplayManVals()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'x' => $this->obj->getDispVals('width'),
                'y' => $this->obj->getDispVals('height'),
                'r' => $this->obj->getDispVals('refresh')
            ]
        ));
    }
    /**
     * Get the hosts display man values
     *
     * @return void
     */
    public function getHostAloVals()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'tme' => $this->obj->getAlo()
            ]
        ));
    }
    /**
     * Gets the printer selector for setting default printers.
     *
     * @return string
     */
    public function getHostDefaultPrinters()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $printersAssigned = Route::getIds(
            'printerassociation',
            ['hostID' => $this->obj->get('id')],
            'printerID'
        );
        if (!count($printersAssigned ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No printers assigned to this host'),
                    'disablebtn' => true
                ]
            ));
        }
        // getNames(): names() answers with its rows under a `data`
        // envelope, and this wants the rows.
        $printerNames = Route::getNames(
            'printer',
            ['id' => $printersAssigned]
        );
        foreach ($printerNames as $printer) {
            $printers[$printer->id] = $printer->name;
        }
        unset($printerNames);
        $defaultprinter = Route::getIds(
            'printerassociation',
            [
                'hostID' => $this->obj->get('id'),
                'isDefault' => '1'
            ],
            'printerID'
        );
        $defaultprinter = array_shift($defaultprinter);
        $printerSelector = self::selectForm(
            'printer',
            $printers ?? [],
            $defaultprinter,
            true,
            '',
            true
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $printerSelector,
                'disablebtn' => false
            ]
        ));
    }

    /**
     * Presents the site tab.
     *
     * @return void
     */
    public function hostSite()
    {
        $this->renderSiteTab('host', $this->obj);
    }
    /**
     * Updates the site.
     *
     * @return void
     */
    public function hostSitePost()
    {
        $this->siteTabPost('host', $this->obj);
    }
}
