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

use FOG\Assign\Resolver;
use FOG\Audit\Audit;
use FOG\Auth\Authorization;
use FOG\Base\FOGManagerController;
use FOG\Base\FOGPage;
use FOG\Boot\SecureBootState;
use FOG\Items\Architecture;
use FOG\Items\Group;
use FOG\Items\Host;
use FOG\Items\HostAutoLogout;
use FOG\Items\MACAddress;
use FOG\Items\PowerManagement;
use FOG\Items\ScheduledTask;
use FOG\Items\Setting;
use FOG\Items\TaskType;
use FOG\Managers\ArchitectureManager;
use FOG\Managers\HostAutoLogoutManager;
use FOG\Managers\HostManager;
use FOG\Managers\ImageManager;
use FOG\Managers\MACAddressAssociationManager;
use FOG\Managers\PowerManagementManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;
use FOG\Util\FOGCron;
use FOG\Util\MassEdit;
use FOG\Util\SharedHostValues;

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
                (new HostManager())->update(
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
            _('Primary MAC'),
            // Beside the two columns that say WHICH machine this is, because
            // that is what a group is now: a label on the host, not a place
            // its settings were copied from (ADR 0038). Rendered as chips by
            // fog.host.list.js and filterable from either search box -- see
            // the groups column in Route::_gridColumns().
            _('Groups')
        ];
        $this->attributes = [
            ['data-col' => 'mainlink'],
            ['data-col' => 'primac'],
            ['data-col' => 'groups']
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
            // when someone is picking enrollment targets and needs to sort or
            // filter by them. Both are searchable on the STORED word
            // ('disabled', 'setup') rather than the rendered label -- that is
            // what the free-text box and the Column search header box match,
            // and what the Filter panel offers as conditions.
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
            (new HostManager())->update(
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
        // Set before the try: both branches assign it, but a throw from
        // anywhere else reaches the catch with it undefined and puts null
        // in the response title.
        $errt = _('MAC Update Fail');
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
                (new MACAddressAssociationManager())->update(
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
     * The fog-agent installs waiting for an admin: Pending Agents.
     *
     * Sibling of Pending Hosts and Pending MACs, and the reason the
     * enrollment flow pends anything at all -- an admin looks at who is
     * asking before a machine gets a credential (fog-agent design decision:
     * admins should know who is doing what). The grid shows the identity
     * the machine reported and where the request came from; the CSR and
     * the certificate never reach the page.
     *
     * @return void
     */
    public function pendingAgents()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('All Pending Agents');

        $this->headerData = [
            _('Host'),
            _('Reason'),
            _('Platform'),
            _('Agent'),
            _('From'),
            _('Identity'),
            _('Requested')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
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
            'deny',
            _('Deny selected'),
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
            _('Approve Pending Agents'),
            _('Each selected agent is issued a certificate and collects it on its next check-in.'),
            $modalApprovalBtns,
            '',
            'success'
        );

        $modalDenyBtns = self::makeButton(
            'confirmDenyModal',
            _('Deny'),
            'btn btn-outline-secondary float-end'
        );
        $modalDenyBtns .= self::makeButton(
            'cancelDenyModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $denyModal = self::makeModal(
            'denyModal',
            _('Deny Pending Agents'),
            _('A denied agent keeps asking and keeps being refused until it is enrolled with a new key.'),
            $modalDenyBtns,
            '',
            'danger'
        );

        echo self::makeFormTag(
            '',
            'agent-pending-form',
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
        echo $denyModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Approves or denies the selected pending agents.
     *
     * One decision per row through FOG\Agent\Enrollment, the same code the
     * JSON route agentEnrollmentDecide runs, so a row approved here and a
     * row approved over the API are indistinguishable afterward. A row
     * that can no longer be decided -- already decided from elsewhere,
     * deleted, unbound -- is reported and the rest still go through.
     *
     * @return void
     */
    public function pendingAgentsAjax()
    {
        header('Content-type: application/json');

        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            ['pending' => $flags]
        );
        $pending = array_map('intval', (array)($items['pending'] ?? []));
        $by = (string)self::$FOGUser->get('name');
        $approve = isset($_POST['approvepending']);
        $errt = $approve ? _('Approve Agent Fail') : _('Deny Agent Fail');
        $failed = [];
        $done = 0;
        foreach ($pending as $id) {
            try {
                if ($approve) {
                    \FOG\Agent\Enrollment::approve($id, $by);
                } else {
                    \FOG\Agent\Enrollment::deny($id, $by);
                }
                $done++;
            } catch (\RuntimeException $e) {
                $failed[] = sprintf('%d: %s', $id, $e->getMessage());
            }
        }
        if (count($failed)) {
            $msg = json_encode(
                [
                    'error' => sprintf(
                        _('%d decided, %d not: %s'),
                        $done,
                        count($failed),
                        implode('; ', $failed)
                    ),
                    'title' => $errt
                ]
            );
            $code = $done > 0
                ? HTTPResponseCodes::HTTP_ACCEPTED
                : HTTPResponseCodes::HTTP_BAD_REQUEST;
        } else {
            $msg = json_encode(
                [
                    'msg' => $approve
                        ? _('Approved selected agents!')
                        : _('Denied selected agents!'),
                    'title' => $approve
                        ? _('Agent Approval Success')
                        : _('Agent Denial Success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Agent enrollment tokens: mint, list, revoke.
     *
     * A token lets a machine enroll without an admin clicking (design 0001
     * agent-based registration: the token goes onto the disk before first
     * boot). It is shown exactly once, in the modal that answers the mint;
     * the list only ever shows name, uses left and expiry.
     *
     * @return void
     */
    public function agentTokens()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('Agent Enrollment Tokens');

        $this->headerData = [
            _('Name'),
            _('State'),
            _('Uses left'),
            _('Expires'),
            _('Created by'),
            _('Created')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
        ];

        $buttons = self::makeButton(
            'mint',
            _('Create token'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'revoke',
            _('Revoke selected'),
            'btn btn-danger float-start'
        );

        $labelClass = 'col-sm-3 col-form-label';
        $default = self::niceDate()->modify('+7 days')->format('Y-m-d\\TH:i');
        $fields = [
            self::makeLabel($labelClass, 'tokenName', _('Name'))
            => self::makeInput('form-control', 'tokenName', _('What this token is for'), 'text', 'tokenName', '', true, false, -1, 191),
            self::makeLabel($labelClass, 'tokenUses', _('Uses'))
            => '<div class="input-group">'
            . self::makeInput('form-control', 'tokenUses', '', 'number', 'tokenUses', '1', true, false, -1, -1, 'min="1"')
            . '<div class="input-group-text">'
            . self::makeInput('form-check-input mt-0', 'tokenUnlimited', '', 'checkbox', 'tokenUnlimited', '1')
            . ' <label for="tokenUnlimited" class="ms-1">' . _('Unlimited') . '</label>'
            . '</div></div>',
            self::makeLabel($labelClass, 'tokenExpires', _('Expires'))
            => self::makeInput('form-control', 'tokenExpires', '', 'datetime-local', 'tokenExpires', $default, true)
        ];
        $mintBody = '';
        foreach ($fields as $label => $input) {
            $mintBody .= '<div class="row mb-3">' . $label . '<div class="col-sm-9">' . $input . '</div></div>';
        }
        $mintBody .= '<p class="text-muted mb-0">'
            . _('An expiry is required. The token approves enrollments until it is spent or expires; revoke it here at any time.')
            . '</p>';
        $modalMintBtns = self::makeButton(
            'confirmMintModal',
            _('Create'),
            'btn btn-outline-secondary float-end'
        );
        $modalMintBtns .= self::makeButton(
            'cancelMintModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $mintModal = self::makeModal(
            'mintModal',
            _('Create Enrollment Token'),
            $mintBody,
            $modalMintBtns,
            '',
            'primary'
        );

        // The one time the token is on screen. Nothing on the server can
        // show it again, and the modal says so.
        $showBody = '<p>' . _('Copy it now. It is not stored and cannot be shown again.') . '</p>'
            . '<div class="input-group">'
            . self::makeInput('form-control font-monospace', 'mintedToken', '', 'text', 'mintedToken', '', false, false, -1, -1, 'readonly')
            . self::makeButton('copyMintedToken', _('Copy'), 'btn btn-outline-secondary')
            . '</div>'
            . '<p class="mt-3 mb-0"><code>fog-agent enroll --server &lt;url&gt; --ca &lt;bundle&gt; --token &lt;token&gt;</code></p>';
        $showModal = self::makeModal(
            'showTokenModal',
            _('Enrollment Token'),
            $showBody,
            self::makeButton('closeShowTokenModal', _('Done'), 'btn btn-outline-secondary float-end', 'data-bs-dismiss="modal"'),
            '',
            'success'
        );

        $modalRevokeBtns = self::makeButton(
            'confirmRevokeModal',
            _('Revoke'),
            'btn btn-outline-secondary float-end'
        );
        $modalRevokeBtns .= self::makeButton(
            'cancelRevokeModal',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $revokeModal = self::makeModal(
            'revokeModal',
            _('Revoke Enrollment Tokens'),
            _('A revoked token can never approve an enrollment again.'),
            $modalRevokeBtns,
            '',
            'danger'
        );

        echo self::makeFormTag(
            '',
            'agent-token-form',
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
        echo $mintModal;
        echo $showModal;
        echo $revokeModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The bare halves of the two ajax handlers below. FOGPageManager
     * appends the Ajax suffix only after method_exists() passes for the
     * bare name, so without these the posts fell through to index() and
     * answered with the host list (found by the first browser run). A
     * plain GET of either sub lands back on the tokens page.
     *
     * @return void
     */
    public function createAgentToken()
    {
        self::redirect('?node=host&sub=agentTokens');
    }
    /**
     * See createAgentToken().
     *
     * @return void
     */
    public function deleteAgentTokens()
    {
        self::redirect('?node=host&sub=agentTokens');
    }
    /**
     * Mints a token from the modal. Named create* so the permission is
     * host.create: a token creates hosts.
     *
     * @return void
     */
    public function createAgentTokenAjax()
    {
        header('Content-type: application/json');
        $uses = filter_input(INPUT_POST, 'tokenUnlimited')
            ? \FOG\Agent\Token::UNLIMITED
            : (int)filter_input(INPUT_POST, 'tokenUses');
        // datetime-local sends 'Y-m-d\TH:i'; mint() wants a space.
        $expires = str_replace('T', ' ', (string)filter_input(INPUT_POST, 'tokenExpires'));
        try {
            $minted = \FOG\Agent\Token::mint(
                (string)filter_input(INPUT_POST, 'tokenName'),
                $uses,
                $expires,
                (string)self::$FOGUser->get('name')
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Token created. Copy it now.'),
                    'title' => _('Token Create Success'),
                    'token' => $minted['token'],
                    'name' => (string)$minted['row']->get('name')
                ]
            );
        } catch (\RuntimeException $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Token Create Fail')
                ]
            );
        }
        $this->jsonSend($code, $msg);
    }
    /**
     * Revokes the selected tokens. Named delete* so the permission is
     * host.delete.
     *
     * @return void
     */
    public function deleteAgentTokensAjax()
    {
        header('Content-type: application/json');
        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(INPUT_POST, ['tokens' => $flags]);
        $by = (string)self::$FOGUser->get('name');
        $failed = [];
        foreach (array_map('intval', (array)($items['tokens'] ?? [])) as $id) {
            try {
                \FOG\Agent\Token::revoke($id, $by);
            } catch (\RuntimeException $e) {
                $failed[] = sprintf('%d: %s', $id, $e->getMessage());
            }
        }
        if (count($failed)) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(['error' => implode('; ', $failed), 'title' => _('Token Revoke Fail')]);
        } else {
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(['msg' => _('Revoked selected tokens.'), 'title' => _('Token Revoke Success')]);
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
        $imageSelector = (new ImageManager())
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
                'Host' => new Host()
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
                'Host' => new Host()
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
                        'Host' => new Host()
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
        $imageSelector = (new ImageManager())
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

                $exists = (new HostManager())
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
                (new HostManager())->getHostByMacAddresses(
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
        $imageSelector = (new ImageManager())
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
        $archSelector = (new ArchitectureManager())
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
        // The fog-agent's poll heartbeat, written by Route::agentPoll() on
        // every poll.
        //
        // This REPLACED "Last Client Check-In" (hostLastCheckin), which the
        // legacy FOG Client wrote and which this form showed instead. On a
        // host running the agent that field read "Never" -- or, worse, a
        // real date from months ago -- for a machine that had checked in a
        // minute earlier, because the two clients write different columns
        // and the page only ever rendered the old one. The agent replaces
        // the legacy client, so the form shows the agent's clock.
        // hostLastCheckin itself is untouched: the legacy client still
        // writes it, and the host list still has a column for it.
        $agentCheckin = self::dateOrNever(
            $this->obj->get('agentCheckin'),
            'hosts',
            'hostAgentCheckin'
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
            // OBSERVED, and disabled for the same reason as the rest of this
            // group: it is a record of when a machine spoke, not a claim
            // anyone may make on its behalf.
            self::makeLabel(
                $labelClass,
                'agentcheckin',
                _('Last Agent Check-In')
            ) => self::makeInput(
                'form-control hostagentcheckin-input',
                'agentcheckin',
                '',
                'text',
                'agentcheckin',
                $agentCheckin,
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
            $mace = (new MACAddressAssociationManager())
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
            (new MACAddressAssociationManager())
                ->update(
                    ['hostID' => $this->obj->get('id')],
                    '',
                    ['primary' => 0]
                );
            if ($primary) {
                (new MACAddressAssociationManager())
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
            (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
                (new MACAddressAssociationManager())
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
     * Host software.
     *
     * Mirrors hostSnapins(): software is a package a host is held to
     * (design 0003), not a one-shot run, but the assignment/order machinery
     * is the same shape -- Host::addSoftware()/removeSoftware() stage the
     * assignment, save() persists it via assocSetter(), and
     * Host::appendSoftwareSequence()/setSoftwareOrder() manage the order the
     * agent applies packages in.
     *
     * @return void
     */
    public function hostSoftware()
    {
        // Trailing 'software' opts this tab into the "Create New Software"
        // button and modal (see renderAssocCreate), matching hostSnapins().
        $this->renderAssocTab(
            'host-software',
            _('Host Software Assignment'),
            _('Software Name'),
            'software',
            'btn btn-primary float-end',
            '',
            'software'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-software',
                $this->obj->get('id')
            )
            . '" ';

        $orderButton = self::makeButton(
            'host-software-order-save',
            _('Save order'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Software Order');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p class="form-text">';
        echo _(
            'The order software is applied in when the agent reconciles '
            . 'this host.'
        );
        echo '</p>';
        echo '<ol id="host-software-order-list" class="list-group"></ol>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $orderButton;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Returns the assigned software for this host in run order.
     *
     * @return void
     */
    public function getSoftwareOrderList()
    {
        $softwareIDs = (array)$this->obj->get('softwares');
        $names = [];
        if (count($softwareIDs) > 0) {
            $Softwares = Route::getList('software', ['id' => $softwareIDs]);
            foreach ($Softwares as $Software) {
                $names[$Software->id] = $Software->name;
            }
        }
        $data = [];
        foreach ($softwareIDs as $softwareID) {
            // Skip ids that don't resolve to a real software entry (a stale
            // association left by a removed entry, or a 0/blank id).
            // Mirrors setSoftwareOrder()'s "< 1" guard on the save path.
            if (!isset($names[$softwareID])) {
                continue;
            }
            $data[] = [
                'id' => $softwareID,
                'name' => $names[$softwareID]
            ];
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['data' => $data]));
    }
    /**
     * Host software post
     *
     * @return void
     */
    public function hostSoftwarePost()
    {
        $this->assocPost('addSoftware', 'removeSoftware', 'setSoftwareOrder', 'softwareorder');
    }
    /**
     * Display's the host service stuff
     *
     * @return void
     */
    public function hostModules()
    {
        // Association Area
        // ADR 0038: a module is a switch with three states, not a link that
        // exists or does not. The per-row control is a select rather than a
        // checkbox, so "off" can be said out loud -- unticking used to
        // delete the row, which now means "unstated" and lets a group grant
        // turn the module back on.
        $this->renderAssocTab(
            'host-module',
            _('Host Module Associations'),
            _('Module Name'),
            'module',
            'btn btn-primary float-end',
            _('Disabled items are not displayed. Legacy items are removed.')
            . ' '
            . _('Not set means a group may enable this module. Off is the '
            . 'host\'s own answer and no group can override it.'),
            '',
            '',
            true,
            _('State')
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-module',
                $this->obj->get('id')
            )
            . '" ';

        $labelClass = 'col-sm-3 col-form-label';
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
        self::checkAuthAndCSRF();
        // NOT assocPost('addModule', 'removeModule'). Those go through the
        // `modules` array and assocSetter, which can only insert a row or
        // delete one -- it has no way to write a row that exists and means
        // OFF, which is the state ADR 0038 adds. Every write from this tab
        // therefore goes through Host::setModuleState(), including the two
        // bulk buttons, so the tab has exactly one writer and `modules` is
        // never marked dirty from here.
        //
        // The wire shape is the association tab's own, unchanged, so the
        // bulk buttons keep working with no client change:
        //   confirmadd + additems[]  -> ON
        //   confirmdel + remitems[]  -> unstated (the row is removed)
        // plus this tab's own third state:
        //   confirmmodulestate + moduleid + state
        if (isset($_POST['confirmadd'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['additems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $this->obj->setModuleState((array)$items['additems'], 1);
        }
        if (isset($_POST['confirmdel'])) {
            $items = filter_input_array(
                INPUT_POST,
                ['remitems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $this->obj->setModuleState((array)$items['remitems'], null);
        }
        if (isset($_POST['confirmmodulestate'])) {
            $moduleID = (int)filter_input(INPUT_POST, 'moduleid');
            $state = filter_input(INPUT_POST, 'state');
            // Three spellings, and anything else is refused rather than
            // guessed at: a state this endpoint does not recognize must not
            // silently become one it does.
            $states = ['on' => 1, 'off' => 0, 'unset' => null];
            if (!array_key_exists((string)$state, $states)) {
                throw new \Exception(_('Unknown module state'));
            }
            $this->obj->setModuleState([$moduleID], $states[(string)$state]);
        }
        if (isset($_POST['confirmalosend'])) {
            $tme = (int)filter_input(INPUT_POST, 'tme');
            // HostAutoLogout::MIN_MINUTES, not the literal it was spelled as.
            // The same rule is stated in three other places -- the host page's
            // own validator, the group page's constant, and the data-alo-min
            // the group form hands the browser -- and this was the one copy
            // that would not move if the minimum ever changed. Below the
            // minimum means OFF, which is the existing behavior.
            if ($tme < HostAutoLogout::MIN_MINUTES) {
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
        // A plain button, not a split button. The second half of the split
        // was "Create New Immediate", and an immediate shut down, restart or
        // wake is a TASK: it acts on the machine now and leaves nothing
        // behind it. It is asked for from Queue Task on this same page, in
        // the Power pane, alongside every other one-off. This tab is for
        // SCHEDULES, which is what its card says it is.
        $scheduleButton = self::makeButton(
            'scheduleBtn',
            _('Create New Scheduled'),
            'btn btn-primary float-end'
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
        $this->render(
            12,
            'host-powermanagement-table',
            $buttons . $scheduleButton
        );
        echo '</div>';
        echo '<div class="card-footer">';
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
     * Host power management post: create and delete SCHEDULES.
     *
     * Nothing here fires an immediate action, and that is the point. This
     * sub is named `powermanagement`, which Authorization::_subToAction()
     * resolves to host.EDIT on a POST -- so anything reachable through it is
     * gated as "may change this host's settings". A schedule is exactly
     * that, and it matches the group side, where granting one is group.edit.
     *
     * An immediate shut down, restart or wake is not: it acts on the machine
     * now. Those live on taskPowerMulti, whose `task` prefix puts them
     * behind host.TASK, and are asked for from Queue Task. Two paths used to
     * create an on-demand row here -- `pmaddod`, behind "Create New
     * Immediate", and a `pmupdate` branch that no page had posted to in
     * years but that could still flip a saved schedule to on-demand. Both
     * are gone, and the insert pins onDemand to 0 rather than trusting that
     * they stay gone.
     *
     * @return void
     */
    public function hostPowermanagementPost()
    {
        self::checkAuthAndCSRF();
        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        if (isset($_POST['pmadd'])) {
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
            $min = FOGCron::_sanitizeCronField($min);
            $hour = FOGCron::_sanitizeCronField($hour);
            $dom = FOGCron::_sanitizeCronField($dom);
            $month = FOGCron::_sanitizeCronField($month);
            $dow = FOGCron::_sanitizeCronField($dow);
            (new PowerManagement())
                ->set('hostID', $this->obj->get('id'))
                ->set('min', $min)
                ->set('hour', $hour)
                ->set('dom', $dom)
                ->set('month', $month)
                ->set('dow', $dow)
                // Always a schedule. This sub is gated host.EDIT by the
                // naming convention in Authorization::_subToAction(), and an
                // on-demand row is an immediate shut down of the machine --
                // which is a task, and belongs behind host.TASK. The one
                // endpoint that creates them is taskPowerMulti, whose name
                // is what puts it there.
                ->set('onDemand', 0)
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
    public function hostAgentActivity()
    {
        $this->renderHistoryTab(
            [
                _('When'),
                _('Event'),
                _('Detail'),
                _('Outcome')
            ],
            [[], [], [], []],
            _('Host Agent Activity'),
            'host-agent-activity-table'
        );
    }
    /**
     * Renders the host's snapin history tab.
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
                _('Return Code'),
                _('Status')
            ],
            [
                [],
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
     * Display host software status.
     *
     * Read only: this reports what the agent last converged, it does not
     * assign anything (that is the Software tab above). One alert replaces
     * the table's usual silence when the host has software assigned but the
     * package manager itself never ran -- reporting that failure once per
     * host rather than once per row.
     *
     * @return void
     */
    public function hostSoftwareStatus()
    {
        $hostID = (int)$this->obj->get('id');
        $resolved = Resolver::resolveSoftware([$hostID])[$hostID] ?? [];
        if (count($resolved) > 0) {
            $statuses = (array)Route::getIds(
                'softwarestatus',
                ['hostID' => $hostID],
                'status'
            );
            // 'cannot_run' is the backend-missing status in
            // FOG\Agent\SoftwareSet::STATUSES -- every row saying it means
            // the agent never got as far as trying any package.
            if (count($statuses) > 0
                && count(array_diff($statuses, ['cannot_run'])) < 1
            ) {
                echo '<div class="alert alert-warning">';
                echo _(
                    'This host reported that its package manager is not '
                    . 'installed. Chocolatey must be installed on the host '
                    . 'before software can be managed.'
                );
                echo '</div>';
            }
        }
        $this->renderHistoryTab(
            [
                _('Software'),
                _('Package'),
                _('Desired'),
                _('Installed'),
                _('Status'),
                _('Exit Code'),
                _('Checked'),
                _('Details')
            ],
            [
                [], [], [], [], [], [], [], []
            ],
            _('Host Software Status'),
            'host-software-status-table'
        );
    }
    /**
     * Display the software the agent currently reports as installed.
     *
     * Read only, and unlike Software Status above, not tied to anything an
     * admin assigned (design 0006): every hostSoftware row currently open
     * (hsRemovedAt IS NULL) for this host, whether or not FOG ever asked for
     * it.
     *
     * @return void
     */
    public function hostInstalledSoftware()
    {
        $this->renderHistoryTab(
            [
                _('Name'),
                _('Version'),
                _('Publisher'),
                _('Source'),
                _('Arch'),
                _('Installed'),
                _('First Seen'),
                _('Last Seen')
            ],
            [
                [], [], [], [], [], [], [], []
            ],
            _('Installed Software'),
            'host-installed-software-table'
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
        // tabs: which image is assigned and when it was last imaged.
        //
        // No group line. It used to say "Primary Group", meaning
        // minId($this->obj->get('groups')) -- the lowest-id group the host
        // happened to be in. There is no primary group: a group GRANTS, and
        // what a host ends up with is resolved from every group it belongs
        // to at task time, ordered by groupOrder (ADR 0038, and see
        // FOG\Assign\Resolver). So the label named a rank that does not
        // exist and the value picked one membership arbitrarily. Listing
        // them all instead was considered and dropped: a host in eight
        // groups blows the card out, and the Group Associations tab already
        // shows them properly.
        // Whichever of the two clients last spoke, named so the card is not
        // ambiguous about which one it is reporting.
        //
        // The agent WINS when both are set, and not by date: a host that has
        // enrolled an agent is a host whose legacy check-in has stopped
        // moving, so the newer client is the live signal even on the day
        // the agent is installed. Falling back keeps the card useful for the
        // hosts that have not migrated yet, which during a migration is most
        // of them.
        // Two literal calls rather than one with a computed column: the
        // column each date came out of decides whether it can predate the
        // UTC boundary, so it is named where a reader -- and
        // tests/utc-storage-boundary.test.php -- can see it.
        $agentRaw = (string)$this->obj->get('agentCheckin');
        if ('' !== $agentRaw && self::validDate($agentRaw)) {
            $checkinWho = _('agent');
            $lastSpoke = self::dateOrNever(
                $this->obj->get('agentCheckin'),
                'hosts',
                'hostAgentCheckin'
            );
        } else {
            $checkinWho = _('client');
            $lastSpoke = self::dateOrNever(
                $this->obj->get('lastcheckin'),
                'hosts',
                'hostLastCheckin'
            );
        }
        if (_('Never') !== $lastSpoke) {
            $lastSpoke = sprintf('%s (%s)', $lastSpoke, $checkinWho);
        }

        $this->notes = [
            _('Host') => $this->obj->get('name'),
            _('Primary MAC') => (string)$this->obj->get('mac'),
            _('Assigned Image') => $this->obj->getImageName(),
            _('Last Check-In') => $lastSpoke,
            _('Last Deployed') => self::dateOrNever(
                $this->obj->get('deployed'),
                'hosts',
                'hostLastDeploy'
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
        // Deploy and Capture, one click, from whichever tab is open. Gated
        // on pending for the same reason the Tasks tab below is: a pending
        // host cannot be tasked, and deployPost() refuses it anyway, so
        // offering the button only produces a toast saying no.
        if (!$this->obj->get('pending')) {
            $this->noteActions = self::renderQuickTaskActions(
                'host',
                (int)$this->obj->get('id'),
                [TaskType::DEPLOY, TaskType::CAPTURE],
                sprintf(_('host "%s"'), $this->obj->get('name'))
            );
        }
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
                    [
                        'name' => _('Software'),
                        'id' => 'host-software',
                        'generator' => function () {
                            $this->hostSoftware();
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
                    [
                        'name' => _('Software Status'),
                        'id' => 'host-software-status',
                        'generator' => function () {
                            $this->hostSoftwareStatus();
                        }
                    ],
                    [
                        'name' => _('Installed Software'),
                        'id' => 'host-installed-software',
                        'generator' => function () {
                            $this->hostInstalledSoftware();
                        }
                    ],
                    [
                        // The agent's own trail for THIS host. The rows have
                        // always been in auditLog tagged with the host id;
                        // nothing could read them by host, so answering
                        // "what has this machine's agent been doing" meant
                        // scrolling the whole install's audit grid.
                        'name' => _('Agent Activity'),
                        'id' => 'host-agent-activity',
                        'generator' => function () {
                            $this->hostAgentActivity();
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
                    case 'host-software':
                        $this->hostSoftwarePost();
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
     * The posted host selection, normalized once.
     *
     * Shared by the form and the apply so the two cannot disagree about what
     * "the selection" is -- a form rendered over one set of ids and a write
     * bounded by another is a scope check answering the wrong question.
     *
     * @return array int host ids, deduplicated, zeroes dropped
     *
     * @throws \Exception when nothing usable was posted
     */
    private function massEditSelection()
    {
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

        return $hosts;
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
     * `tab` says which tab of the form the field is drawn on, and mirrors
     * where the same setting sits on a single host's own page -- so someone
     * who knows where to find Host Init on a host finds it in the same place
     * here. It is presentation only: the apply path never reads it, and a
     * field that omits it still renders (on General), because a new field
     * silently vanishing from the form is a far worse failure than one
     * appearing on the wrong tab. massEditTabGroups() names the tabs.
     *
     * @return array key => ['field', 'empty', 'label', 'kind', 'secret',
     *               'tab']
     */
    private function massEditCoreFields()
    {
        return [
            // NULL, not 0. `hosts`.`hostImage` carries a foreign key to
            // `images`.`imageID` (ADR 0031, schema-constraints.php:145) and
            // 0 is not exempt from a constraint just because it looks like
            // an absence -- there is no image with id 0, so clearing to 0 is
            // rejected outright and the whole mass edit fails. The column is
            // nullable and the reconciler already swept every legacy 0 to
            // NULL, so NULL is what "no image" actually IS on any 1.6
            // database. Verified against a live install: 77 hosts hold NULL
            // and none holds 0.
            'image' => [
                'field' => 'imageID',
                'empty' => null,
                'label' => _('Image'),
                'kind' => 'image',
                'tab' => 'general'
            ],
            'kernel' => [
                'field' => 'kernel',
                'empty' => '',
                'label' => _('Host Kernel'),
                'kind' => 'kernel',
                'tab' => 'general'
            ],
            'kernelArgs' => [
                'field' => 'kernelArgs',
                'empty' => '',
                'label' => _('Host Kernel Arguments'),
                'kind' => 'text',
                'tab' => 'general'
            ],
            'kernelDevice' => [
                'field' => 'kernelDevice',
                'empty' => '',
                'label' => _('Host Primary Disk'),
                'kind' => 'text',
                'tab' => 'general'
            ],
            'init' => [
                'field' => 'init',
                'empty' => '',
                'label' => _('Host Init'),
                'kind' => 'init',
                'tab' => 'general'
            ],
            'biosexit' => [
                'field' => 'biosexit',
                'empty' => '',
                'label' => _('Host BIOS Exit Type'),
                'kind' => 'biosexit',
                'tab' => 'general'
            ],
            'efiexit' => [
                'field' => 'efiexit',
                'empty' => '',
                'label' => _('Host EFI Exit Type'),
                'kind' => 'efiexit',
                'tab' => 'general'
            ],
            'productKey' => [
                'field' => 'productKey',
                'empty' => '',
                'label' => _('Product Key'),
                'kind' => 'text',
                'secret' => true,
                'tab' => 'general'
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
                'kind' => 'printerlevel',
                // FOG Client, not General. On a host's own page this is drawn
                // by hostPrinters() -- the Printer Associations tab -- and
                // there is no Printer Associations here, because an
                // association is not a value a mass edit sets. What is left
                // when you take the list away is a statement about how hard
                // the client works on printers at check-in, which is what the
                // rest of this tab is: auto-logout, this.
                'tab' => 'client'
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
                'kind' => 'bool',
                'tab' => 'ad'
            ],
            'enforce' => [
                'field' => 'enforce',
                'empty' => 0,
                'label' => _('Host Enforce Hostname Changes'),
                'kind' => 'bool',
                'tab' => 'general'
            ],
            'ADDomain' => [
                'field' => 'ADDomain',
                'empty' => '',
                'label' => _('Active Directory Domain Name'),
                'kind' => 'text',
                'tab' => 'ad'
            ],
            'ADOU' => [
                'field' => 'ADOU',
                'empty' => '',
                'label' => _('Active Directory Organizational Unit'),
                'kind' => 'text',
                'tab' => 'ad'
            ],
            'ADUser' => [
                'field' => 'ADUser',
                'empty' => '',
                'label' => _('Active Directory Username'),
                'kind' => 'text',
                'tab' => 'ad'
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
                'secret' => true,
                'tab' => 'ad'
            ],
        ];
    }
    /**
     * The host settings a mass edit may change that are NOT `hosts` columns.
     *
     * Auto-logout lives one row per host in its own table, and the group
     * page writes it by deleting every member's row and inserting a fresh
     * one (`Group::setAlo()`). That shape maps onto the three states
     * exactly: SET is delete-then-insert, CLEAR is the delete on its own --
     * no row IS the absence of an override -- and LEAVE touches nothing.
     *
     * Deliberately separate from massEditCoreFields(): nothing here can ever
     * be a column update, and keeping the two lists apart is what stops one
     * from being handed to columnUpdates() by accident.
     *
     * @return array key => ['label', 'kind', 'tab']
     */
    private function massEditRowFields()
    {
        return [
            'autologout' => [
                'label' => _('Auto Log Out Time (in minutes)'),
                'kind' => 'number',
                'tab' => 'client'
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
                (new HostAutoLogoutManager())
                    ->insertBatch(['hostID', 'time'], $rows);
            }
            $wrote = count($hostIDs);
        }

        return $wrote;
    }

    /**
     * The tabs the mass edit form is split across, in the order drawn.
     *
     * The form reached seventeen core fields plus whatever the plugins add,
     * which is a scroll rather than a form: the AD password sat below the
     * fold under a stack of kernel settings that have nothing to do with it.
     * Splitting it costs nothing at the wire -- every pane is in the DOM and
     * a hidden pane's inputs serialize exactly like a visible one's -- so
     * this is purely how the fields are laid out, and the POST is byte for
     * byte what it was.
     *
     * The names and the grouping mirror a single host's own page on purpose,
     * and the two fields that look misplaced are the ones following that rule
     * hardest: `enforce` is on General because hostGeneral() draws it there,
     * not because it has nothing to do with AD, and `printerLevel` is on FOG
     * Client because hostPrinters() draws it and there is no Printer
     * Associations tab here. Second-guessing the host page per field is how
     * the two drift apart.
     *
     * Plugin-contributed fields get their own tab, which is what tabFields()
     * does with plugin-contributed tabs everywhere else, and it is only drawn
     * when a plugin actually contributed something.
     *
     * @return array tab id => tab label
     */
    private function massEditTabGroups()
    {
        return [
            'general' => _('General'),
            'ad' => _('Active Directory'),
            'client' => _('FOG Client'),
            'plugins' => _('Plugins')
        ];
    }
    /**
     * Which tab a field spec is drawn on.
     *
     * Defaults to General rather than throwing or dropping the field. A spec
     * with no `tab`, or one naming a tab that does not exist, is a mistake in
     * the spec -- but the failure that mistake should produce is a control in
     * the wrong place, not a control that is silently absent from a form
     * whose whole job is to be the only way to set these values in bulk.
     *
     * @param array $spec one entry from massEditCoreFields()/RowFields()
     *
     * @return string a key of massEditTabGroups()
     */
    private function massEditTabFor(array $spec)
    {
        $tab = (string)($spec['tab'] ?? '');
        return isset($this->massEditTabGroups()[$tab]) ? $tab : 'general';
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
     * `ou` each shipped a whole second hook file whose only job was to set
     * one value across many hosts, always clobbering, unable to express
     * "leave alone" at all. Both now contribute through this seam instead
     * (fog-plugins #36), which is what allowed decision 10's last step --
     * removing the group page's push controls -- to happen at all.
     *
     * The selection is passed because a plugin's mixed-value hint has to be
     * computed over it -- "(varies)" is a statement about THESE hosts. Both
     * the form and the apply call this with the same ids, so the two can
     * never see different field sets: a key offered by the form and absent
     * from the apply would be a control that silently does nothing.
     *
     * @param array $hostIDs the selected host ids
     *
     * @return array key => ['label' => ..., 'input' => <html>,
     *               'hint' => <html>, 'kind' => ...]
     */
    private function massEditPluginFields(array $hostIDs = [])
    {
        $fields = [];
        self::$HookManager->processEvent(
            'HOST_MASSEDIT_FIELDS',
            [
                'fields' => &$fields,
                'hostIDs' => &$hostIDs
            ]
        );

        return $fields;
    }
    /**
     * The `hosts` columns behind the core field keys, for the shared-value
     * hints.
     *
     * The map is derived from the manager's own field map rather than
     * restated in massEditCoreFields(). Restating it would be a second place
     * that has to agree with `Host::$databaseFields`, and the failure when it
     * did not agree would be a hint quietly describing the wrong column --
     * which reads as a true statement about the selection and is not one.
     *
     * getColumns() also drops any field whose column is not actually on the
     * table, which is the state a server sits in between deploying new code
     * and running the schema updater. Such a field renders with no hint
     * rather than taking the whole form down with a SQL error.
     *
     * @param array $spec massEditCoreFields()
     *
     * @return array field key => `hosts` column name
     */
    private function massEditColumnMap(array $spec)
    {
        $map = (new HostManager())->getColumns();
        $columns = [];
        foreach ($spec as $key => $entry) {
            $field = $entry['field'] ?? '';
            if (isset($map[$field])) {
                $columns[$key] = $map[$field];
            }
        }

        return $columns;
    }

    /**
     * The action control. Core renders it, never the field's owner.
     *
     * ADR 0038 decision 11: a plugin that drew its own could ship a
     * two-state field, and a two-state field in a mass edit is the defect
     * that decision is entirely about -- it looks identical to a correct one
     * until somebody's images are gone.
     *
     * A boolean is offered LEAVE and SET only. Its value control is already
     * a yes/no, so a CLEAR that wrote 0 would be a second spelling of "set
     * to No" sitting next to the first, and two controls that mean the same
     * thing is how a person picks the wrong one.
     *
     * @param string $key  the field key
     * @param array  $spec that field's entry
     *
     * @return string
     */
    private function massEditActionControl($key, array $spec)
    {
        $id = self::massEditControlId('action', $key);
        $options = [MassEdit::LEAVE => _('No change')];
        $options[MassEdit::SET] = _('Set on all');
        if ('bool' !== ($spec['kind'] ?? 'text')) {
            $options[MassEdit::CLEAR] = _('Clear on all');
        }
        $html = '<select class="form-control massedit-action"'
            . ' name="action[' . \Initiator::e($key) . ']"'
            . ' id="' . $id . '"'
            . ' data-massedit-key="' . \Initiator::e($key) . '"'
            . ' autocomplete="off">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . $value . '">'
                . \Initiator::e($label)
                . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * The value control for one field, by kind.
     *
     * Every control is rendered EMPTY. There is no read path in this form --
     * not for the credentials, which is decision 11's requirement, and not
     * for anything else either, because a control pre-filled from a
     * selection has to answer "pre-filled with which host's value" and there
     * is no honest answer when they differ. What the hosts currently hold is
     * reported by the hint beside it instead, where "(varies)" is sayable.
     *
     * @param string $key  the field key
     * @param array  $spec that field's entry
     *
     * @return string
     */
    private function massEditValueControl($key, array $spec)
    {
        $name = 'value[' . $key . ']';
        $id = self::massEditControlId('value', $key);
        $kind = $spec['kind'] ?? 'text';
        switch ($kind) {
            case 'image':
                return (new ImageManager())
                    ->buildSelectBox('', $name, 'name', '', false, 'id', $id);
                /**
                 * The same picker the single-host form uses. Mass edit was left on
                 * a free-text box when the dropdowns landed, so the one place a
                 * typo reaches four hundred hosts at once was the one place
                 * offering no list to choose from.
                 *
                 * Blank is a legitimate mass-edit value here -- it means "clear
                 * the override and inherit the global default" -- which is why
                 * the spec's 'empty' is '' rather than null.
                 */
            case 'kernel':
                return self::kernelFileSelect(
                    $name,
                    '',
                    'kernel',
                    'form-control',
                    $id,
                    _('Use the default kernel')
                );
            case 'init':
                return self::kernelFileSelect(
                    $name,
                    '',
                    'init',
                    'form-control',
                    $id,
                    _('Use the default init')
                );
            case 'biosexit':
            case 'efiexit':
                return Setting::buildExitSelector($name, '', true, $id);
            case 'printerlevel':
                return self::massEditSelect(
                    $name,
                    $id,
                    [
                        '0' => _('No Printer Management'),
                        '1' => _('Add/Remove Managed Printers'),
                        '2' => _('All Printers'),
                    ]
                );
            case 'bool':
                return self::massEditSelect(
                    $name,
                    $id,
                    ['1' => _('Yes'), '0' => _('No')]
                );
            case 'password':
                // autocomplete off and no value: the browser must not offer
                // to fill a field that writes to four hundred hosts.
                return self::makeInput(
                    'form-control',
                    $name,
                    '',
                    'password',
                    $id,
                    '',
                    false,
                    false
                );
            case 'number':
                return self::makeInput(
                    'form-control',
                    $name,
                    '',
                    'number',
                    $id,
                    '',
                    false,
                    false,
                    -1,
                    -1,
                    'min="0"'
                );
        }

        return self::makeInput('form-control', $name, '', 'text', $id);
    }

    /**
     * A plain select whose options are a value => label map.
     *
     * @param string $name    the control name
     * @param string $id      the control id
     * @param array  $options value => label
     *
     * @return string
     */
    private static function massEditSelect($name, $id, array $options)
    {
        $html = '<select class="form-control" name="' . $name . '"'
            . ' id="' . $id . '" autocomplete="off">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . \Initiator::e((string)$value) . '">'
                . \Initiator::e($label)
                . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * A control id from a field key.
     *
     * The keys are core's and the plugins', so they are not trusted to be
     * HTML identifiers even though every one today is. Stripping rather than
     * escaping, because an id with a bracket in it is a selector nobody
     * writes correctly on the first try.
     *
     * @param string $kind 'action' or 'value'
     * @param string $key  the field key
     *
     * @return string
     */
    private static function massEditControlId($kind, $key)
    {
        return 'massedit-' . $kind . '-'
            . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
    }

    /**
     * What the selection currently holds, per field, ready to render.
     *
     * Mixed values are SHOWN, never resolved (ADR 0038 decision 11): forty
     * hosts holding six images render as "(varies)", not as one of the six.
     *
     * @param array $hostIDs the selected host ids
     * @param array $core    massEditCoreFields()
     *
     * @return array field key => hint HTML
     */
    private function massEditHints(array $hostIDs, array $core)
    {
        $hints = [];
        $shared = SharedHostValues::forHosts(
            $hostIDs,
            $this->massEditColumnMap($core)
        );
        foreach ($core as $key => $spec) {
            if (!isset($shared[$key])) {
                continue;
            }
            // A credential reports agreement and not the value. Both
            // secrets here match Redaction::CREDENTIAL_PATTERN, and a form
            // editing hundreds of hosts at once is the last place either
            // should be rendered back out.
            $hints[$key] = SharedHostValues::hint(
                $shared[$key],
                !empty($spec['secret'])
            );
        }

        $alo = SharedHostValues::forHostRows(
            $hostIDs,
            'hostAutoLogOut',
            'haloHostID',
            ['autologout' => 'haloTime']
        );
        $hints['autologout'] = SharedHostValues::hint($alo['autologout']);

        return $hints;
    }

    /**
     * Lays the mass edit controls out as tabs.
     *
     * Presentation only, and deliberately its own method: everything here is
     * pure string building over arrays it is handed, which is what lets a
     * test render the real form rather than assert on the shape of the code
     * that renders it.
     *
     * Every pane is in the DOM whether or not it is the visible one, and a
     * hidden pane's inputs serialize exactly like a visible one's -- so the
     * POST this form produces is byte for byte what the single flat list
     * produced. That is the whole reason tabbing it needed no change to
     * fog.host.list.js, which reaches its controls by class across the whole
     * form.
     *
     * @param array $core    massEditCoreFields()
     * @param array $rows    massEditRowFields()
     * @param array $hints   massEditHints(), keyed by field key
     * @param array $plugins massEditPluginFields(), keyed by field key
     *
     * @return string the rendered tab block
     */
    private function massEditTabbedFields(
        array $core,
        array $rows,
        array $hints,
        array $plugins
    ) {
        $labelClass = 'col-sm-3 col-form-label';
        $fields = [];
        foreach (array_merge($core, $rows) as $key => $spec) {
            $fields[$this->massEditTabFor($spec)][
                self::makeLabel(
                    $labelClass,
                    self::massEditControlId('value', $key),
                    $spec['label'] ?? $key
                )
            ] = '<div class="row g-2">'
                . '<div class="col-sm-4">'
                . $this->massEditActionControl($key, $spec)
                . '</div>'
                . '<div class="col-sm-8">'
                . $this->massEditValueControl($key, $spec)
                . ($hints[$key] ?? '')
                . '</div>'
                . '</div>';
        }
        foreach ($plugins as $key => $spec) {
            $fields['plugins'][
                self::makeLabel(
                    $labelClass,
                    self::massEditControlId('value', $key),
                    $spec['label'] ?? $key
                )
            ] = '<div class="row g-2">'
                . '<div class="col-sm-4">'
                . $this->massEditActionControl($key, $spec)
                . '</div>'
                . '<div class="col-sm-8">'
                . ($spec['input'] ?? '')
                . ($spec['hint'] ?? '')
                . '</div>'
                . '</div>';
        }

        // A tab with nothing on it is not drawn -- Plugins is the one that is
        // routinely empty, and an empty tab in a modal reads as something
        // that failed to load.
        $tabData = [];
        foreach ($this->massEditTabGroups() as $tabID => $tabName) {
            if (empty($fields[$tabID])) {
                continue;
            }
            $pane = self::formFields($fields[$tabID]);
            $tabData[] = [
                'name' => $tabName,
                'id' => 'massedit-tab-' . $tabID,
                'generator' => function () use ($pane) {
                    echo $pane;
                }
            ];
        }

        // false, not the -1 default: -1 resolves the current node and id into
        // an object and fires TABDATA_HOOK and PLUGINS_INJECT_TABDATA against
        // it. There is no single host being edited here, and a plugin tab
        // built for one host would be wrong for all of them -- the mass
        // edit's plugin seam is HOST_MASSEDIT_FIELDS.
        return self::tabFields($tabData, false);
    }
    /**
     * Refuses a GET to the mass edit form endpoint.
     *
     * @return void
     */
    public function massEditForm()
    {
        // Dispatch anchor. FOGPageManager::render() will not look for
        // massEditFormPost() until a method literally named for the sub
        // resolves, so without this the POST below is unreachable and the
        // request is answered by the host list instead -- 200, valid JSON,
        // no msg, an empty modal. See FOGPagePost::methodNotAllowed().
        //
        // There is no GET form here on purpose: the form's "(varies)" hints
        // are computed over the actual selection, and several hundred host
        // ids do not go in a query string.
        self::methodNotAllowed();
    }
    /**
     * Builds the mass edit form for a selection of hosts.
     *
     * A POST rather than a GET, and that is not incidental. The hints beside
     * every control describe THIS selection, so the endpoint needs the ids to
     * render at all -- and several hundred of them do not go in a query
     * string. deployMulti() gets away with a GET because it only needs a
     * count.
     *
     * @return void
     */
    public function massEditFormPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        try {
            $hosts = $this->massEditSelection();
            // The form reports what these hosts hold, so it is a read of
            // them and takes the same boundary the write does.
            Authorization::requirePageObjectScopeMass('host', $hosts);

            $core = $this->massEditCoreFields();
            $rows = $this->massEditRowFields();
            $hints = $this->massEditHints($hosts, $core);

            // The plugin half. A plugin supplies the label, the value
            // control and its own hint over the selection; core keeps the
            // action control. Same call the apply path makes, so the two
            // cannot see different field sets -- a plugin whose key appears
            // in the form and not in the apply would offer a control that
            // silently does nothing.
            $pluginFields = $this->massEditPluginFields($hosts);

            $rendered = $this->massEditTabbedFields(
                $core,
                $rows,
                $hints,
                $pluginFields
            );

            ob_start();
            echo self::makeFormTag(
                '',
                'host-massedit-form',
                '../management/index.php?node=host&sub=massedit',
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo '<p class="form-text">';
            printf(
                /* translators: %d is the number of selected hosts */
                _('Editing %d selected hosts. Every field is left alone '
                . 'unless you choose otherwise.'),
                count($hosts)
            );
            echo '</p>';
            echo $rendered;
            foreach ($hosts as $hostID) {
                echo '<input type="hidden" name="hosts[]" value="'
                    . (int)$hostID . '"/>';
            }
            echo '</form>';

            $msg = json_encode(
                [
                    'msg' => ob_get_clean(),
                    'title' => _('Mass edit form success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
        } catch (\Exception $e) {
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Mass edit form fail')
                ]
            );
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
        }
        $this->jsonSend($code, $msg);
    }

    /**
     * Refuses a GET to the mass edit apply endpoint.
     *
     * @return void
     */
    public function massEdit()
    {
        // Dispatch anchor for massEditPost(); see massEditForm() above.
        // This one is a WRITE, so being silently answered by the host list
        // meant the Update button reported nothing wrong and changed
        // nothing at all.
        self::methodNotAllowed();
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
            $hosts = $this->massEditSelection();
            // Airtight: one id outside the caller's site scope denies the
            // whole request rather than quietly editing the rest. The ids
            // come from the browser, so this is the only place they are
            // bounded -- same stance, and the same call, as
            // deployMultiPost() and saveGroup().
            Authorization::requirePageObjectScopeMass('host', $hosts);

            $coreFields = $this->massEditCoreFields();
            $rowFields = $this->massEditRowFields();
            $pluginFields = $this->massEditPluginFields($hosts);
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
            // The row-backed fields are resolved separately, not merged
            // into $keys: they are written one row per host rather than as
            // columns and must never reach columnUpdates().
            $scalarRows = array_keys($rowFields);

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
            $resolvedRows = MassEdit::resolve(
                $scalarRows,
                $posted['action'] ?? null,
                $posted['value'] ?? null
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
                // The return value is CHECKED. perform_update() answers
                // false and writes a fault rather than throwing, so ignoring
                // it reported "Updated 1 field(s) on 86 host(s)" for a write
                // the database had refused -- which is how a clear that
                // never landed reads as a clear that did.
                if (!(new HostManager())
                    ->update(['id' => $hosts], '', $updates)
                ) {
                    throw new \Exception(
                        _('The database refused the update; nothing was changed')
                    );
                }
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
     * Adds the selected hosts to groups, or removes them from groups.
     *
     * The list's membership editor, and since ADR 0038 Decision 16a the
     * PRIMARY way membership is edited -- a group is a label now, and the
     * group page's own Hosts tab is one host list per group, which is three
     * trips to apply one label to a fleet.
     *
     * BOTH DIRECTIONS, because a label you can apply and cannot retract is
     * not a label (requirement 2). `action=remove` is the only difference on
     * the wire; everything else -- the gates, the id normalization, the
     * scope bounds -- is one path, so add and remove cannot drift apart on
     * who may do them or to what.
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
        // Add unless the request says otherwise. Anything unrecognized is an
        // add: that is what every client predating the remove half sends,
        // and it is the direction that cannot take a label off a fleet if a
        // value is somehow wrong.
        $remove = 'remove' === strtolower(
            (string)filter_input(INPUT_POST, 'action')
        );
        // A TYPED NAME THAT ALREADY NAMES A GROUP IS THAT GROUP.
        //
        // `groupName` is UNIQUE (the manifest declares it twice over), so
        // ->set('name', $taken)->save() fails on the duplicate key -- and the
        // old code discarded save()'s return, so typing a group's own name
        // into the modal's (new) slot silently did nothing at all. The group
        // page's rename path has checked for this the whole time
        // (GroupManagement.php:705, via getManager()->exists()); this endpoint
        // is now the primary membership surface and cannot be the looser of
        // the two.
        //
        // Resolved HERE, ahead of the scope bound below, and that placement
        // is the security half: a resolved id is an id, so it has to be
        // subject to the same boundary an id posted directly is. Typing a
        // name must not be a way round a check.
        //
        // One query for every typed name. groupName's collation is
        // case-insensitive, so this matches on exactly what the UNIQUE index
        // would have collided on.
        if (count($groups_new)) {
            $existing = [];
            foreach ((array)Route::getNames('group', ['name' => $groups_new]) as $row) {
                $id = (int)($row->id ?? 0);
                $name = strtolower(trim((string)($row->name ?? '')));
                if ($id > 0 && '' !== $name) {
                    $existing[$name] = $id;
                }
            }
            if (count($existing)) {
                $stillNew = [];
                foreach ($groups_new as $name) {
                    $key = strtolower($name);
                    if (isset($existing[$key])) {
                        $groups[] = $existing[$key];
                        continue;
                    }
                    $stillNew[] = $name;
                }
                $groups = array_values(array_unique($groups));
                $groups_new = $stillNew;
            }
        }
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
        //
        // AFTER the resolution above, deliberately. A name that turned out to
        // name an existing group creates nothing, so it is an edit and asking
        // for group.create would be asking for the wider right to do the
        // narrower thing -- which is the mistake this split was written to
        // undo.
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
                throw new \Exception(_('No hosts are selected'));
            }
            if (!count($groups) && !count($groups_new)) {
                throw new \Exception(_('No groups are being created or selected'));
            }
            // Every name left in groups_new after the resolution above is one
            // that does NOT exist. Removing hosts from a group that does not
            // exist removes nothing, and creating an empty group in order to
            // remove nobody from it is not what anyone meant -- so say so
            // rather than reporting success over a no-op.
            if ($remove && count($groups_new)) {
                throw new \Exception(
                    sprintf(
                        _('No group named "%s" exists to remove hosts from'),
                        implode('", "', $groups_new)
                    )
                );
            }
            $touched = [];
            foreach ($groups as $group) {
                $Group = new Group($group);
                if (!$Group->isValid()) {
                    continue;
                }
                if ($remove) {
                    $Group->removeHost($hosts);
                } else {
                    $Group->addHost($hosts);
                }
                if (!$Group->save()) {
                    throw new \Exception(
                        sprintf(
                            _('Could not update the group "%s"'),
                            $Group->get('name')
                        )
                    );
                }
                $touched[] = $Group->get('name');
            }
            foreach ($groups_new as $group) {
                // save()'s return is propagated rather than discarded. It was
                // discarded here, which is how a duplicate name reported
                // success while inserting nothing -- see the resolution above
                // for the case that made it, and tests/save-propagates-
                // failure.test.php for why this tree treats it as a rule.
                $New = (new Group())
                    ->set('name', $group)
                    ->addHost($hosts);
                if (!$New->save()) {
                    throw new \Exception(
                        sprintf(_('Could not create the group "%s"'), $group)
                    );
                }
                $touched[] = $group;
            }
            // Membership is the label, and ADR 0038 makes this the surface it
            // is edited from -- so who applied which label to how many hosts
            // is exactly the question the audit log exists to answer. The
            // mass edit beside it has recorded its own writes since it
            // shipped; this one recorded nothing.
            Audit::record(
                [
                    'type' => $remove ? 'host.groupremove' : 'host.groupadd',
                    'subjectType' => 'host',
                    'subjectLabel' => implode(', ', $touched),
                    'permission' => 'group.edit',
                    'affectedCount' => count($hosts),
                    'renderable' => 1
                ]
            );
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => $remove
                        ? sprintf(
                            _('Removed %1$d host(s) from %2$d group(s).'),
                            count($hosts),
                            count($touched)
                        )
                        : sprintf(
                            _('Added %1$d host(s) to %2$d group(s).'),
                            count($hosts),
                            count($touched)
                        ),
                    'title' => $remove
                        ? _('Remove Hosts from Groups Success')
                        : _('Add Hosts to Groups Success')
                ]
            );
        } catch (\Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => $remove
                        ? _('Remove Hosts from Groups Fail')
                        : _('Add Hosts to Group Fail')
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
     * Presents the software list table.
     *
     * @return void
     */
    public function getSoftwareList()
    {
        // Not `return`: assocItemsList() is void (it echoes and exit()s), and
        // HostManagement's phpstan baseline already budgets a fixed count of
        // the "void result used" finding that returning it would trip.
        // GroupManagement::getSoftwareList() calls it the same, unreturned,
        // way.
        $this->assocItemsList(
            'software',
            'softwareassociation',
            'softwareAssoc',
            '`software`.`swID`',
            '`softwareAssoc`.`swaSoftwareID`',
            '`softwareAssoc`.`swaHostID`',
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
        $where = "`modules`.`short_name` IN ('"
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
        // The host's own state for this module, which the association
        // column cannot carry: 'associated'/'dissociated' has two values
        // and ADR 0038 gives a module three. NULL here is the third -- the
        // LEFT JOIN found no row, so the host has said nothing and a group
        // grant may still turn it on.
        //
        // nosearch because the free-text box would otherwise match every
        // row on a typed 0 or 1, which is never what the box is for.
        $columns[] = [
            'db' => 'msState',
            'dt' => 'state',
            'nosearch' => true
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
     * The pending agents grid's rows.
     *
     * Not Route::listem(): agentenrollment is deliberately not an API
     * class -- every row carries a CSR and, once approved, a certificate --
     * so the page takes the same whitelisted shape the admin JSON route
     * serves and the table pages it client-side. The list is bounded by
     * what an admin has not yet looked at, never by the fleet.
     *
     * @return void
     */
    public function getPendingAgentList()
    {
        Route::agentEnrollments();
        echo Route::getData();
        exit;
    }
    /**
     * The agent token grid's rows: the same whitelisted shape the admin
     * JSON route serves, paged client-side.
     *
     * @return void
     */
    public function getAgentTokenList()
    {
        Route::agentTokens();
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
            'HOST_ADVANCEDTASKS_DATA',
            function ($action, $label, $icon) {
                // `powertaskitem`, not `taskitem`: the tasking JS treats a
                // taskitem as "fetch this type's options form", and these
                // have no options. The action is what the server needs and
                // the only thing carried.
                return '<a href="#" class="powertaskitem" data-power="'
                    . \Initiator::e($action)
                    . '"><i class="fas fa-'
                    . \Initiator::e($icon)
                    . ' fa-2x"></i><br/>'
                    . \Initiator::e($label)
                    . '</a>';
            }
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
     * The Mass Edit control for the host list: one button, one modal.
     *
     * Picked up by FOGPage::process() through method_exists, the same seam
     * queueTaskActions() uses, so the generic list toolbar does not learn a
     * second node name.
     *
     * The modal ships EMPTY. Its body is fetched on click, by POST, because
     * the hints beside every control describe the current selection and
     * several hundred host ids do not go in a query string. Rendering it
     * with the page would mean rendering it before anything is ticked.
     *
     * @return array ['button' => string, 'modal' => string]
     */
    public function massEditActions()
    {
        // Left half of the toolbar, immediately right of "Delete selected".
        // Both act ON the rows already ticked, which is the distinction the
        // toolbar is split on -- the right-hand group brings something new
        // into existence instead. Secondary rather than danger because it
        // edits records rather than removing them, and deliberately not the
        // green Queue Task shares a group with: this changes records, it
        // does not start work on machines.
        $button = self::makeButton(
            'massEditSelected',
            _('Mass edit'),
            'btn btn-secondary float-start ms-2'
        );

        $modal = self::makeModal(
            'massEditModal',
            '<h4 class="card-title">'
            . _('Edit selected hosts')
            . '<span class="massedit-host-count"></span></h4>',
            '<div id="massedit-form-holder"></div>',
            self::makeButton(
                'massEditClose',
                _('Cancel'),
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                'massEditSend',
                _('Update'),
                'btn btn-primary float-end d-none'
            ),
            '',
            'secondary',
            'modal-lg'
        );

        return ['button' => $button, 'modal' => $modal];
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
            'HOST_ADVANCEDTASKS_DATA',
            function ($action, $label, $icon) {
                // data-access="both": an immediate power action is as valid
                // on one machine as on forty, unlike Capture and Multi-Cast.
                // Carried anyway so applyTaskAvailability() has one rule to
                // apply rather than a list of exceptions to remember.
                return '<a href="#" class="powertaskitem" data-power="'
                    . \Initiator::e($action)
                    . '" data-access="both"><i class="fas fa-'
                    . \Initiator::e($icon)
                    . ' fa-2x"></i><br/>'
                    . \Initiator::e($label)
                    . '</a>';
            }
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
     * Carries out an immediate power action on a selection of hosts.
     *
     * Shut down and restart are `powerManagement` rows with `pmOndemand = 1`:
     * the FOG client asks for them on its next check-in, acts, and the row is
     * deleted. Wake is not a row at all -- a sleeping machine cannot ask for
     * anything, so the server sends the magic packet here and now.
     *
     * A TASK, NOT A GRANT, and it is on the task surface for that reason. It
     * acts on the hosts that are selected at the moment you press it and
     * leaves nothing standing behind it. Group POWER SCHEDULES are the other
     * thing entirely and live on the group as grants (ADR 0038); an immediate
     * action has no group-owned counterpart because a grant of "shut down
     * immediately" would fire again for every host that joined later.
     *
     * THE NAME IS THE PERMISSION. Authorization::_subToAction() reads the
     * prefix off the sub and answers `task` for `deploy`, `multicast` and
     * `task` -- so `taskPowerMulti` is gated as host.task, and renaming it to
     * something tidier like `powerMulti` would silently reclassify it as
     * host.EDIT on the POST. Anyone who could rename a host could then shut
     * down the fleet. The same convention already covers `wakeemup` and
     * `clearpmtasks`, which that function lists by name because they do not
     * carry a prefix; this one carries the prefix instead.
     * tests/host-list-queue-task.test.php executes the resolution rather than
     * trusting the reading.
     *
     * The rest of the gate is deployMultiPost()'s, deliberately and not by
     * coincidence: the ids come from the browser, so one host outside the
     * caller's site scope must deny the whole request rather than quietly
     * acting on the rest. Pending hosts are excluded for the same reason
     * tasking excludes them -- a machine that has not been approved is not
     * one to act on.
     *
     * @return void
     */
    public function taskPowerMulti()
    {
        // Dispatch anchor. FOGPageManager::render() will not look for
        // taskPowerMultiPost() until a method literally named for the sub
        // resolves, so without this the POST below is unreachable and the
        // request is answered by the host list instead -- 200, valid JSON,
        // no msg, and a button that silently does nothing. See
        // FOGPagePost::methodNotAllowed().
        //
        // There is no GET form here on purpose: a power action takes no
        // options, which is the whole reason it is one click.
        self::methodNotAllowed();
    }
    /**
     * Carries out an immediate power action on a selection of hosts.
     *
     * See taskPowerMulti() above for why the bare method exists.
     *
     * @return void
     */
    public function taskPowerMultiPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        try {
            $action = trim((string)filter_input(INPUT_POST, 'action'));
            // Whitelisted, not passed through. The column is an ENUM, so an
            // unknown value would be stored as '' by a non-strict server
            // rather than refused -- a row the client reads as no action at
            // all and deletes, which looks exactly like the action having
            // been carried out.
            if (!in_array($action, ['shutdown', 'reboot', 'wol'], true)) {
                throw new \Exception(_('Unknown power action'));
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
            Authorization::requirePageObjectScopeMass('host', $hosts);

            $hosts = Route::getIds(
                'host',
                [
                    'id' => $hosts,
                    'pending' => ['', 0]
                ]
            );
            if (count($hosts ?: []) < 1) {
                throw new \Exception(_('No hosts available'));
            }

            if ('wol' === $action) {
                foreach (Route::getList('host', ['id' => $hosts]) as $Host) {
                    (new Host($Host->id))->wakeOnLAN();
                }
            } else {
                // insertBatch UPSERTS against `powerManagement`.`cron`, so
                // pressing this twice before the client checks in leaves one
                // row rather than two -- which is what you want from a button
                // whose effect is "shut this machine down".
                $items = [];
                foreach ($hosts as $hostID) {
                    $items[] = [$hostID, '', '', '', '', '', 1, $action];
                }
                (new PowerManagementManager())
                    ->insertBatch(
                        [
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

            self::jsonSend(
                HTTPResponseCodes::HTTP_OK,
                json_encode(
                    [
                        'msg' => sprintf(
                            _('Power action sent to %d host(s)'),
                            count($hosts)
                        )
                    ]
                )
            );
        } catch (\Exception $e) {
            // Always 400. Every throw above is a refusal of what was asked --
            // an unknown action, an empty or out-of-scope selection, nothing
            // left after the pending filter -- and there is no branch here
            // that can fail for the server's own reasons, so a 500 arm would
            // be a branch no request could reach.
            self::jsonSend(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(['error' => $e->getMessage()])
            );
        }
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
            $TaskType = new TaskType($typeId);
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

            $TaskType = new TaskType($type);
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

            $TaskType = new TaskType($type);
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
            $Selection = (new Group())
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
                $ScheduledTask = (new ScheduledTask())
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
    public function getAgentActivity()
    {
        header('Content-type: application/json');
        // The same read AgentActivityManagement::getHostActivity() performs,
        // so the host tab and the Logging page cannot drift into showing
        // different histories for one machine. A LIKE on the prefix rather
        // than a list of type names: a new fact kind is a registry entry and
        // a block in the poll, not a third place to remember to edit.
        Route::listem(
            'auditlog',
            [
                'type' => AgentActivityManagement::TYPE_PREFIX . '%',
                'subjectType' => 'host',
                'subjectID' => (int)$this->obj->get('id')
            ],
            false,
            'AND',
            'id'
        );
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
    /**
     * Serves the host's snapin history rows.
     *
     * @return void
     */
    public function getSnapinHist()
    {
        $this->renderSnapinHistoryData($this->obj->get('id'));
    }
    /**
     * Serves the host's software status grid.
     *
     * Joined to `software` for display (name/package/desired state), built
     * directly against FOGManagerController::complex() rather than through
     * Route::listem() -- listem()'s per-class column building (the
     * `taskTypeName`/`snapinLink`-style extras) lives entirely in
     * route.class.php, which this pass does not touch, so the join and the
     * DataTables envelope are assembled here instead, with the same generic
     * paging/search/order engine every other grid in FOG runs through.
     *
     * @return void
     */
    public function getSoftwareStatus()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $request
        );
        $hostID = (int)$this->obj->get('id');
        $columns = [
            ['db' => 'sstID', 'dt' => 'id'],
            ['db' => 'swName', 'dt' => 'software'],
            ['db' => 'swPackage', 'dt' => 'package'],
            // swVersion carries no column of its own in the grid; the
            // 'desired' formatter below reads it off the row, so it still
            // has to be selected.
            ['db' => 'swVersion', 'dt' => 'version'],
            [
                'db' => 'swState',
                'dt' => 'desired',
                'formatter' => function ($state, $row) {
                    if ('absent' === $state) {
                        return _('absent');
                    }
                    $version = trim((string)($row['swVersion'] ?? ''));
                    return _('present') . ' '
                        . ('' !== $version ? $version : _('latest'));
                }
            ],
            ['db' => 'sstInstalledVersion', 'dt' => 'installed'],
            ['db' => 'sstStatus', 'dt' => 'status'],
            ['db' => 'sstReturnCode', 'dt' => 'returnCode'],
            ['db' => 'sstChecked', 'dt' => 'checked'],
            ['db' => 'sstDetails', 'dt' => 'details']
        ];
        $sqlstr = "SELECT `%s`
            FROM `%s`
            LEFT OUTER JOIN `software`
            ON `softwareStatus`.`sstSoftwareID` = `software`.`swID`
            %s
            %s
            %s";
        $fltrstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `software`
            ON `softwareStatus`.`sstSoftwareID` = `software`.`swID`
            %s";
        $ttlstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `software`
            ON `softwareStatus`.`sstSoftwareID` = `software`.`swID`";
        $data = FOGManagerController::complex(
            $request,
            'softwareStatus',
            'sstID',
            $columns,
            $sqlstr,
            $fltrstr,
            $ttlstr,
            null,
            sprintf('`softwareStatus`.`sstHostID` = %d', $hostID),
            'checked'
        );
        $this->jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }
    /**
     * Serves the host's installed-software grid.
     *
     * No join: unlike getSoftwareStatus() above, hostSoftware carries
     * nothing that needs a lookup table, so this is built directly against
     * FOGManagerController::complex() the same way, just simpler.
     *
     * @return void
     */
    public function getInstalledSoftware()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $request
        );
        $hostID = (int)$this->obj->get('id');
        $columns = [
            ['db' => 'hsID', 'dt' => 'id'],
            ['db' => 'hsName', 'dt' => 'name'],
            ['db' => 'hsVersion', 'dt' => 'version'],
            ['db' => 'hsPublisher', 'dt' => 'publisher'],
            ['db' => 'hsSource', 'dt' => 'source'],
            ['db' => 'hsArch', 'dt' => 'arch'],
            ['db' => 'hsInstallDate', 'dt' => 'installed'],
            ['db' => 'hsFirstSeen', 'dt' => 'firstSeen'],
            ['db' => 'hsLastSeen', 'dt' => 'lastSeen']
        ];
        $sqlstr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";
        $fltrstr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";
        $ttlstr = "SELECT COUNT(`%s`)
            FROM `%s`";
        $data = FOGManagerController::complex(
            $request,
            'hostSoftware',
            'hsID',
            $columns,
            $sqlstr,
            $fltrstr,
            $ttlstr,
            null,
            // hostSoftware rows are CLOSED (hsRemovedAt set), not deleted,
            // once the agent stops reporting a package -- so this IS NULL
            // clause is what makes the tab "what is installed now" rather
            // than "everything ever seen".
            sprintf(
                '`hostSoftware`.`hsHostID` = %d '
                . 'AND `hostSoftware`.`hsRemovedAt` IS NULL',
                $hostID
            ),
            'name'
        );
        $this->jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
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
