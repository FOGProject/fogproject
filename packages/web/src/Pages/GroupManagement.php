<?php
/**
 * Group management page
 *
 * PHP version 7.4+
 *
 * The group represented to the GUI
 *
 * @category GroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Auth\Authorization;
use FOG\Base\FOGPage;
use FOG\Items\Group;
use FOG\Items\GroupPowerManagement;
use FOG\Items\ScheduledTask;
use FOG\Items\Setting;
use FOG\Items\TaskType;
use FOG\Managers\GroupManager;
use FOG\Managers\PowerManagementManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;
use FOG\Util\FOGCron;
use FOG\Util\SharedHostValues;

/**
 * Group management page
 *
 * The group represented to the GUI
 *
 * @category GroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManagement extends FOGPage
{
    /**
     * The node that uses this class
     *
     * @var string
     */
    public $node = 'group';
    /**
     * Initializes the group page
     *
     * @param string $name the name to construct with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Group Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Members'),
            // What the group grants, so a plain label group reads as one and
            // a group that pushes snapins at every host in it reads as
            // heavy. ADR 0038 Decision 16a requirement 5; the column is
            // built in the group arm of Route::_gridColumns().
            _('Grants')
        ];
        $this->attributes = [
            [],
            ['width' => 16],
            []
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $group = filter_input(INPUT_POST, 'group');
        $description = filter_input(INPUT_POST, 'description');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');
        $order = filter_input(INPUT_POST, 'order');

        $labelClass = 'col-sm-3 col-form-label';

        // The fields to display
        $fields = [
            self::makeLabel(
                $labelClass,
                'group',
                _('Group Name')
            ) => self::makeInput(
                'form-control groupname-input',
                'group',
                _('Group Name'),
                'text',
                'group',
                $group,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control groupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'order',
                _('Group Order')
            ) => self::makeInput(
                'form-control grouporder-input',
                'order',
                '0',
                'number',
                'order',
                $order,
                false,
                false,
                -1,
                -1,
                'min="0" step="1"'
            ) . '<p class="form-text">'
            . _(
                'Lowest first. When a host is in more than one group, this '
                . 'decides which group is applied first -- groups sharing a '
                . 'number fall back to name order. It only matters where two '
                . 'groups disagree, such as granting different default '
                . 'printers.'
            )
            . '</p>',
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Group Kernel')
            ) => self::kernelFileSelect(
                'kernel',
                $kernel,
                'kernel',
                'form-control groupkernel-input',
                '',
                _('Use the default kernel')
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Group Kernel Arguments')
            ) => self::makeInput(
                'form-control groupkernelargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Group Init')
            ) => self::kernelFileSelect(
                'init',
                $init,
                'init',
                'form-control groupinit-input',
                '',
                _('Use the default init')
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Group Primary Disk')
            ) => self::makeInput(
                'form-control groupdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            )
        ];

        return self::fastmerge($fields, self::siteAddField($labelClass));
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'group',
            _('Create New Group'),
            'GROUP_ADD_FIELDS',
            'Group'
        );
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'group',
            'GROUP_ADD_FIELDS',
            'Group'
        );
    }
    /**
     * When submitted to add post this is what's run
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Group',
            'GROUP_ADD',
            _('Group added!'),
            _('Group Create Success'),
            _('Group Create Fail'),
            function (&$serverFault) {
                $group = trim(
                    (string)filter_input(INPUT_POST, 'group')
                );
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $kernel = trim(
                    (string)filter_input(INPUT_POST, 'kernel')
                );
                $args = trim(
                    (string)filter_input(INPUT_POST, 'args')
                );
                $init = trim(
                    (string)filter_input(INPUT_POST, 'init')
                );
                $dev = trim(
                    (string)filter_input(INPUT_POST, 'dev')
                );
                $order = max(
                    0,
                    (int)filter_input(INPUT_POST, 'order')
                );
                $exists = (new GroupManager())
                    ->exists($group);
                if ($exists) {
                    throw new \Exception(
                        _('A group already exists with this name!')
                    );
                }
                $Group = (new Group())
                    ->set('name', $group)
                    ->set('description', $description)
                    ->set('order', $order)
                    ->set('kernel', $kernel)
                    ->set('kernelArgs', $args)
                    ->set('kernelDevice', $dev)
                    ->set('init', $init);
                if (!$Group->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add group failed!'));
                }
                $this->siteAddPost('group', $Group);
                return $Group;
            }
        );
    }
    /**
     * The printer this group grants as the default, or 0 for none.
     *
     * ADR 0038: one row on the group answers this. It replaces a per-member
     * COUNT(DISTINCT)/MIN sweep over printerAssoc that could only report
     * "they all agree on X" or "(varies)", because the group had no default
     * of its own to report.
     *
     * @return int the printer id, or 0
     */
    private function _groupDefaultPrinter()
    {
        $ids = Route::getIds(
            'groupprinterassociation',
            [
                'groupID' => $this->obj->get('id'),
                'isDefault' => 1
            ],
            'printerID'
        );
        return count((array)$ids) > 0 ? (int)reset($ids) : 0;
    }
    /**
     * Displays the group general tab.
     *
     * @return void
     */
    public function groupGeneral()
    {
        $group = (
            filter_input(INPUT_POST, 'group') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        // Not the ?: idiom the fields above use: an order of 0 is a real
        // value and the default one, so ?: would discard it and redisplay
        // the field as empty on every load.
        $order = filter_input(INPUT_POST, 'order');
        if ($order === null || $order === false || $order === '') {
            $order = (int)$this->obj->get('order');
        }

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'group',
                _('Group Name')
            ) => self::makeInput(
                'form-control groupname-input',
                'group',
                _('Group Name'),
                'text',
                'group',
                $group,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control groupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'order',
                _('Group Order')
            ) => self::makeInput(
                'form-control grouporder-input',
                'order',
                '0',
                'number',
                'order',
                $order,
                false,
                false,
                -1,
                -1,
                'min="0" step="1"'
            ) . '<p class="form-text">'
            . _(
                'Lowest first. When a host is in more than one group, this '
                . 'decides which group is applied first -- groups sharing a '
                . 'number fall back to name order. It only matters where two '
                . 'groups disagree, such as granting different default '
                . 'printers.'
            )
            . '</p>',
        ];

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
            'GROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => &$this->obj
            ]
        );
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
        $alert = '';
        $this->renderGeneralForm(
            'group',
            $alert . $rendered,
            $buttons . $modalreset
        );
    }
    /**
     * Group general post element
     *
     * @return void
     */
    public function groupGeneralPost()
    {
        self::checkAuthAndCSRF();
        $group = trim(
            (string)filter_input(INPUT_POST, 'group')
        );
        $desc = trim(
            (string)filter_input(INPUT_POST, 'description')
        );
        // Clamped rather than validated: the field is a number input with
        // min=0, so a negative or non-numeric value here is a hand-built
        // POST, and there is no order it could mean other than the default.
        $order = max(
            0,
            (int)filter_input(INPUT_POST, 'order')
        );
        if ($group != $this->obj->get('name')) {
            if ($this->obj->getManager()->exists($group)) {
                throw new \Exception(_('Please use another group name'));
            }
        }
        // Only the group's OWN fields. Everything this used to push onto the
        // member hosts -- kernel, args, primary disk, init, both exit types
        // and the product key -- is now set from the Hosts list's Edit
        // selected hosts (ADR 0038 decision 10), where "leave this host
        // alone" is a state the form can express and this one never could.
        $this->obj
            ->set('name', $group)
            ->set('description', $desc)
            ->set('order', $order);
    }
    /**
     * Group hosts display.
     *
     * @return void
     */
    public function groupHosts()
    {
        $this->renderAssocTab(
            'group-host',
            _('Group Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update the group hosts.
     *
     * @return void
     */
    public function groupHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Group printers display.
     *
     * @return void
     */
    public function groupPrinters()
    {
        // Printer Associations. Trailing 'printer' opts this tab into the
        // "Create New Printer" button and modal (see renderAssocCreate). The
        // created printer is granted through this tab's own update URL, i.e.
        // Group::addPrinter(), which writes one row on the GROUP.
        $this->renderAssocTab(
            'group-printer',
            _('Group Printer Assignment'),
            _('Printer Name'),
            'printer',
            'btn btn-primary float-end',
            _(
                'A printer granted here reaches every host in this group, '
                . 'including hosts added later. Removing a host from the '
                . 'group takes the printer away again. A printer the host '
                . 'was given directly is unaffected either way.'
            ),
            'printer'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-printer',
                $this->obj->get('id')
            )
            . '" ';

        // DEFAULT Printer
        $buttons = self::makeButton(
            'group-printer-default-send',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Group Default Printer');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('The default printer for hosts in this group. A host that '
            . 'has its own default keeps it.');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<span id="printerselector"></span>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group Printer Post.
     *
     * @return void
     */
    public function groupPrinterPost()
    {
        $this->assocPost('addPrinter', 'removePrinter');
        if (isset($_POST['confirmdefault'])) {
            $default = filter_input(INPUT_POST, 'default');
            $this->obj->addPrinter([$default]);
            $this->obj->updateDefault($default);
        }
    }
    /**
     * Group snapins.
     *
     * @return void
     */
    public function groupSnapins()
    {
        // Trailing 'snapin' opts this tab into the "Create New Snapin" button
        // and modal (see renderAssocCreate). Association runs through
        // Group::addSnapin(), which writes one row on the GROUP.
        $this->renderAssocTab(
            'group-snapin',
            _('Group Snapin Assignment'),
            _('Snapin Name'),
            'snapin',
            'btn btn-primary float-end',
            _(
                'A snapin granted here reaches every host in this group, '
                . 'including hosts added later. Granting a snapin does not '
                . 'run it; deploy it from the Tasks tab when you want it to '
                . 'run.'
            ),
            'snapin'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-snapin',
                $this->obj->get('id')
            )
            . '" ';

        $orderButton = self::makeButton(
            'group-snapin-order-save',
            _('Save order'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Snapin Run Order');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _(
            'The order this group\'s snapins run in. A host runs its own '
            . 'snapins first, then the ones granted here, in this order. '
            . 'Order only changes execution when "Abort snapin sequence on '
            . 'failure" is enabled for the task.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<ol id="group-snapin-order-list" class="list-group"></ol>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $orderButton;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group snapin post
     *
     * @return void
     */
    public function groupSnapinPost()
    {
        $this->assocPost('addSnapin', 'removeSnapin', 'setSnapinOrder');
    }
    /**
     * Group software.
     *
     * Mirrors groupSnapins(): association runs through
     * Group::addSoftware()/removeSoftware(), which writes one row on the
     * GROUP.
     *
     * @return void
     */
    public function groupSoftware()
    {
        $this->renderAssocTab(
            'group-software',
            _('Group Software Assignment'),
            _('Software Name'),
            'software',
            'btn btn-primary float-end',
            _(
                'Software granted here applies to every host in this group, '
                . 'including hosts added later.'
            ),
            'software'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-software',
                $this->obj->get('id')
            )
            . '" ';

        $orderButton = self::makeButton(
            'group-software-order-save',
            _('Save order'),
            'btn btn-primary float-end',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Software Order');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _(
            'The order this group\'s software is applied in. A host applies '
            . 'its own software first, then the software granted here, in '
            . 'this order.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<ol id="group-software-order-list" class="list-group"></ol>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $orderButton;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group software post
     *
     * @return void
     */
    public function groupSoftwarePost()
    {
        $this->assocPost('addSoftware', 'removeSoftware', 'setSoftwareOrder', 'softwareorder');
    }
    /**
     * Display's the group service stuff
     *
     * @return void
     */
    public function groupModules()
    {
        // Association Area
        $this->renderAssocTab(
            'group-module',
            _('Group Module Associations'),
            _('Module Name'),
            'module',
            'btn btn-primary float-end',
            _('Disabled items are not displayed. Legacy items are removed.')
            . '<br/>'
            . _(
                'A module granted here is on for every host in this group, '
                . 'including hosts added later -- unless the host itself '
                . 'says otherwise. A host set to Off on its own Modules tab '
                . 'stays off, and no grant can override that.'
            )
        );
    }
    /**
     * Group Service post.
     *
     * @return void
     */
    public function groupModulePost()
    {
        // Grants only. The display-manager, auto-logout and enforce-hostname
        // pushes that used to live here are set from the Hosts list's Edit
        // selected hosts (ADR 0038 decision 10) as `resolution`, `autologout`
        // and `enforce`.
        $this->assocPost('addModule', 'removeModule');
    }
    /**
     * Display the group Power Management grants.
     *
     * ADR 0038: this tab GRANTS a schedule, it does not copy one.
     *
     * It used to write one `powerManagement` row per host that happened to be
     * a member at the moment you pressed the button, and record nothing about
     * where those rows came from. A host added afterward got no schedule, a
     * host removed kept one forever, and "Delete All" reached only the current
     * membership. The tab's own text said "to all hosts in this group", which
     * was true for one instant and wrong from the next membership change on.
     *
     * The grid lists the GROUP'S OWN grants -- not a summary of what its
     * members hold. What a given machine actually runs is its own schedules
     * unioned with the grants of every group it is in, resolved at read time
     * by Assign\Resolver::resolvePowerManagement(). The host's own Power
     * Management tab remains the answer to "what is THIS machine scheduled to
     * do".
     *
     * IMMEDIATE ACTIONS ARE STILL A FAN-OUT, and that is correct rather than
     * an omission. An immediate shutdown, reboot or wake acts on the
     * membership at the moment you start it, which is what a task should do; a
     * GRANT of "shut down immediately" would fire again for every host that
     * joined the group later. There is no `gpmOndemand` column for that
     * reason.
     *
     * @return void
     */
    public function groupPowermanagement()
    {
        $this->headerData = [
            _('Cron Schedule'),
            _('Action')
        ];
        $this->attributes = [
            [],
            []
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-powermanagement',
                $this->obj->get('id')
            )
            . '" ';
        $buttons = self::makeButton(
            'pm-delete',
            _('Delete selected'),
            'btn btn-danger float-start',
            $props
        );
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
        // The legacy sweep. Kept, and deliberately NOT relabeled as a way to
        // clear the grants above: it does the opposite thing. Grants live on
        // the group and are removed with "Delete selected"; this reaches into
        // the member hosts and deletes the rows THEY hold, which on an
        // upgraded server is where every schedule this tab ever created ended
        // up. Without it there is no way to undo a pre-1.6 fan-out short of
        // opening each host in turn.
        $modaldeleteBtns = self::makeButton(
            'deletepowermanagementConfirm',
            _('Confirm'),
            'btn btn-outline-secondary float-end',
            $props
        );
        $modaldeleteBtns .= self::makeButton(
            'deletepowermanagementCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        $sweepButton = self::makeButton(
            'powermanagement-delete',
            _('Clear schedules from member hosts'),
            'btn btn-outline-danger float-start'
        );
        echo '<!-- Power Management -->';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Power Management Grants');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _(
            'Every host in this group runs these schedules, including hosts '
            . 'added later. Nothing is written onto a host; a host keeps its '
            . 'own schedules as well.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(
            12,
            'group-powermanagement-table',
            $buttons . $splitButtons
        );
        echo '</div>';
        echo '<div class="card-footer">';
        echo $sweepButton;
        echo self::makeModal(
            'ondemandModal',
            _('Create Immediate Power task'),
            '<p class="form-text">'
            . _(
                'This acts on the hosts that are in the group right now. It '
                . 'is a task, not a grant: a host added afterward is not '
                . 'affected.'
            )
            . '</p>'
            . $this->newPMDisplay(true),
            $ondemandModalBtns,
            '',
            'info'
        );
        echo self::makeModal(
            'scheduleModal',
            _('Create Scheduled Power grant'),
            $this->newPMDisplay(false),
            $scheduleModalBtns,
            '',
            'primary'
        );
        echo self::makeModal(
            'deletepowermanagementmodal',
            _('Clear schedules from member hosts'),
            _(
                'This deletes the power management schedules held by the '
                . 'hosts that are in this group right now. It does NOT touch '
                . 'this group\'s grants, and it also removes schedules those '
                . 'hosts were given individually.'
            ),
            $modaldeleteBtns,
            '',
            'warning'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Gets this group's power management grants for the grid.
     *
     * Route::listem() rather than a REST route: `grouppowermanagement` is
     * deliberately NOT in Route::$validClasses, exactly as
     * `groupsnapinassociation` and `groupmoduleassociation` are not. listem()
     * validates nothing itself -- that list gates the HTTP API surface -- so
     * a page can drive its own grid off a table without the grant becoming a
     * new public endpoint and a new permission to grant.
     *
     * @return void
     */
    public function getGrouppowermanagementList()
    {
        Route::listem(
            'grouppowermanagement',
            ['groupID' => $this->obj->get('id')]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Modify the power management stuff.
     *
     * @return void
     */
    public function groupPowermanagementPost()
    {
        self::checkAuthAndCSRF();
        $groupID = (int)$this->obj->get('id');
        if (isset($_POST['pmadd']) || isset($_POST['pmaddod'])) {
            $onDemand = (int)isset($_POST['pmaddod']);
            $min = trim((string)filter_input(INPUT_POST, 'scheduleCronMin'));
            $hour = trim((string)filter_input(INPUT_POST, 'scheduleCronHour'));
            $dom = trim((string)filter_input(INPUT_POST, 'scheduleCronDOM'));
            $month = trim((string)filter_input(INPUT_POST, 'scheduleCronMonth'));
            $dow = trim((string)filter_input(INPUT_POST, 'scheduleCronDOW'));
            $action = filter_input(INPUT_POST, 'action');
            if (!$action) {
                throw new \Exception(_('You must select an action to perform'));
            }
            if ($onDemand) {
                // STILL A FAN-OUT, and correctly so -- an immediate action is
                // a task against the membership at this instant. Wake is the
                // one the server performs itself, because a sleeping machine
                // cannot ask for anything.
                $hostIDs = (array)$this->obj->get('hosts');
                if ('wol' === $action) {
                    $this->obj->wakeOnLAN();
                    return;
                }
                $items = [];
                foreach ($hostIDs as $hostID) {
                    $items[] = [
                        $hostID,
                        $min,
                        $hour,
                        $dom,
                        $month,
                        $dow,
                        1,
                        $action
                    ];
                }
                if (count($items) > 0) {
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
                return;
            }
            // ONE ROW, ABOUT THE GROUP. Not one per member.
            (new GroupPowerManagement())
                ->set('groupID', $groupID)
                ->set('min', FOGCron::_sanitizeCronField($min))
                ->set('hour', FOGCron::_sanitizeCronField($hour))
                ->set('dom', FOGCron::_sanitizeCronField($dom))
                ->set('month', FOGCron::_sanitizeCronField($month))
                ->set('dow', FOGCron::_sanitizeCronField($dow))
                ->set('action', $action)
                ->save();
        }
        if (isset($_POST['pmremove'])) {
            $flags = ['flags' => FILTER_REQUIRE_ARRAY];
            $grantIDs = filter_input_array(
                INPUT_POST,
                ['remgrants' => $flags]
            );
            $grantIDs = (array)($grantIDs['remgrants'] ?? []);
            // Scoped to THIS group as well as to the ids. The ids arrive from
            // the browser, and a grant id is not a secret -- without the
            // groupID clause a crafted post would revoke another group's
            // schedule.
            Route::deletemass(
                'grouppowermanagement',
                [
                    'id' => $grantIDs,
                    'groupID' => $groupID
                ]
            );
        }
        if (isset($_POST['pmdelete'])) {
            // The legacy sweep -- member hosts' own rows, not this group's
            // grants. See groupPowermanagement().
            Route::deletemass(
                'powermanagement',
                ['hostID' => (array)$this->obj->get('hosts')]
            );
        }
    }
    /**
     * Displays Group Host Inventories
     *
     * @return void
     */
    public function groupInventory()
    {
        // Get this group's Host Inventory items.
        $inventories = Route::getList(
            'inventory',
            ['hostID' => $this->obj->get('hosts')],
            'AND',
            'hostID'
        );

        // Get the host names
        $hostnames = Route::getIds(
            'host',
            ['id' => $this->obj->get('hosts')],
            'name',
            'AND',
            'id'
        );

        // Just to make the fields nice and formatted.
        $labelClass = 'col-sm-3 col-form-label';

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Group Host Inventories');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        if (!count($hostnames)) {
            echo _('No hosts associated to this group yet');
            echo '</div>';
            echo '</div>';
            return;
        }
        // Loop and print the inventory data broken out by host names.
        foreach ($inventories as $i => &$inventory) {
            if (!isset($hostnames[$i])) {
                continue;
            }
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo '<a data-bs-toggle="collapse" data-bs-parent="#accordion" href="#'
                . $hostnames[$i]
                . '">';
            echo $hostnames[$i] . ' ' . _('Inventory Data');
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $hostnames[$i]
                . '" class="collapse">';
            $puser = $inventory->primaryUser;
            $other1 = $inventory->other1;
            $other2 = $inventory->other2;
            $sysman = $inventory->sysman;
            $sysprod = $inventory->sysproduct;
            $sysver = $inventory->sysversion;
            $sysser = $inventory->sysserial;
            $systype = $inventory->systype;
            $sysuuid = $inventory->sysuuid;
            $biosven = $inventory->biosvendor;
            $biosver = $inventory->biosversion;
            $biosdate = $inventory->biosversion;
            $mbman = $inventory->mbman;
            $mbprod = $inventory->mbproductname;
            $mbver = $inventory->mbversion;
            $mbser = $inventory->mbserial;
            $mbast = $inventory->mbasset;
            $cpuman = $inventory->cpuman;
            $cpuver = $inventory->cpuversion;
            $cpucur = $inventory->cpucurrent;
            $cpumax = $inventory->cpumax;
            $mem = $inventory->mem;
            $hdmod = $inventory->hdmodel;
            $hdfirm = $inventory->hdfirmware;
            $hdser = $inventory->hdserial;
            $caseman = $inventory->caseman;
            $casever = $inventory->casever;
            $caseser = $inventory->caseserial;
            $caseast = $inventory->caseasset;
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'pu-' . $i,
                    _('Primary User')
                ) => self::makeInput(
                    'form-control',
                    'pu-' . $i,
                    '',
                    'text',
                    'pu-' . $i,
                    $puser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'other1-' . $i,
                    _('Other Tag #1')
                ) => self::makeInput(
                    'form-control',
                    'other1-' . $i,
                    '',
                    'text',
                    'other1-' . $i,
                    $other1,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'other2-' . $i,
                    _('Other Tag #2')
                ) => self::makeInput(
                    'form-control',
                    'other2-' . $i,
                    '',
                    'text',
                    'other2-' . $i,
                    $other2,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-manufacturer-' . $i,
                    _('System Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-manufacturer-' . $i,
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
                    'inventory-system-product-' . $i,
                    _('System Product')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-product-' . $i,
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
                    'inventory-system-version-' . $i,
                    _('System Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-version-' . $i,
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
                    'inventory-system-serial-' . $i,
                    _('System Serial')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-serial-' . $i,
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
                    'inventory-system-uuid-' . $i,
                    _('System UUID')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-uuid-' . $i,
                    '',
                    'text',
                    '',
                    $sysuuid,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'inventory-system-type-' . $i,
                    _('System Type')
                ) => self::makeInput(
                    'form-control',
                    'inventory-system-type-' . $i,
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
                    'inventory-bios-vendor-' . $i,
                    _('BIOS Vendor')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-vendor-' . $i,
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
                    'inventory-bios-version-' . $i,
                    _('BIOS Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-version-' . $i,
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
                    'inventory-bios-date-' . $i,
                    _('BIOS Date')
                ) => self::makeInput(
                    'form-control',
                    'inventory-bios-date-' . $i,
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
                    'inventory-motherboard-manufacturer-' . $i,
                    _('Motherboard Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-manufacturer-' . $i,
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
                    'inventory-motherboard-productname-' . $i,
                    _('Motherboard Product Name')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-productname-' . $i,
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
                    'inventory-motherboard-version-' . $i,
                    _('Motherboard Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-version-' . $i,
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
                    'inventory-motherboard-serial-number-' . $i,
                    _('Motherboard Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-serial-number-' . $i,
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
                    'inventory-motherboard-asset-tag-' . $i,
                    _('Motherboard Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    'inventory-motherboard-asset-tag-' . $i,
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
                    'inventory-cpu-manufacturer-' . $i,
                    _('CPU Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-manufacturer-' . $i,
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
                    'inventory-cpu-version-' . $i,
                    _('CPU Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-version-' . $i,
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
                    'inventory-cpu-normal-speed-' . $i,
                    _('CPU Normal Speed')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-normal-speed-' . $i,
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
                    'inventory-cpu-max-speed-' . $i,
                    _('CPU Max Speed')
                ) => self::makeInput(
                    'form-control',
                    'inventory-cpu-max-speed-' . $i,
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
                    'inventory-memory-' . $i,
                    _('Memory')
                ) => self::makeInput(
                    'form-control',
                    'inventory-memory-' . $i,
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
                    'inventory-hard-drive-model-' . $i,
                    _('Hard Drive Model')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-model-' . $i,
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
                    'inventory-hard-drive-firmware-' . $i,
                    _('Hard Drive Firmware')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-firmware-' . $i,
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
                    'inventory-hard-drive-serial-number-' . $i,
                    _('Hard Drive Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-hard-drive-serial-number-' . $i,
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
                    'inventory-chassis-manufacturer-' . $i,
                    _('Chassis Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-manufacturer-' . $i,
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
                    'inventory-chassis-version-' . $i,
                    _('Chassis Version')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-version-' . $i,
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
                    'inventory-chassis-serial-number-' . $i,
                    _('Chassis Serial Number')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-serial-number-' . $i,
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
                    'inventory-chassis-asset-tag-' . $i,
                    _('Chassis Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    'inventory-chassis-asset-tag-' . $i,
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
            $rendered = self::formFields($fields);
            unset($fields);
            echo '<div class="card-body">';
            echo self::makeFormTag(
                '',
                'group-inventory-form-' . $hostnames[$i],
                '#',
                'get',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($inventory);
        }
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display Login History for Hosts in this Group
     *
     * @return void
     */
    public function groupLoginHistory()
    {
        $this->renderHistoryTab(
            [
                _('Host Name'),
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
            _('Group Login History'),
            'group-login-history-table'
        );
    }
    /**
     * Display Image History for Hosts in this Group
     *
     * @return void
     */
    public function groupImageHistory()
    {
        $this->renderHistoryTab(
            [
                _('Host Name'),
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
                [],
                []
            ],
            _('Group Task History'),
            'group-image-history-table'
        );
    }
    /**
     * Display Snapin History for Hosts in this Group
     *
     * @return void
     */
    public function groupSnapinHistory()
    {
        $this->renderHistoryTab(
            [
                _('Host Name'),
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
                [],
                []
            ],
            _('Group Snapin History'),
            'group-snapin-history-table'
        );
    }
    /**
     * The group edit display method
     *
     * @return void
     */
    public function edit()
    {
        // Read once: the note below and the quick-task confirmation both
        // want it, and getHostCount() is a query.
        $hostCount = (int)$this->obj->getHostCount();
        $this->notes = [
            _('Group') => $this->obj->get('name'),
            _('Members') => (string)$hostCount
        ];
        // Info-card notes that mirror a General-tab control, so the card
        // tracks the form instead of going stale until the next page
        // load. Keys must match $notes exactly; notes left out here (the
        // association counts, and anything no control on this page can
        // change) keep their server-rendered value.
        $this->noteSources = [
            _('Group') => '#group'
        ];
        // Deploy and Multi-Cast, one click, from whichever tab is open.
        // Multi-Cast rather than Capture: capturing is a thing you do to
        // one machine, and a group is by definition more than one. Not a
        // style call -- deployPost() below throws "Groups cannot create
        // capture tasks" outright, so a Capture button here could only ever
        // produce a toast saying no. The list grid draws the same line on
        // ttIsAccess ('host' vs 'group').
        //
        // The member count goes in the confirmation, not just the name. A
        // group's size is the fact that decides whether you meant to press
        // this, and it is the one thing the button itself cannot show.
        if ($hostCount > 0) {
            $this->noteActions = self::renderQuickTaskActions(
                'group',
                (int)$this->obj->get('id'),
                [TaskType::DEPLOY, TaskType::MULTICAST],
                sprintf(
                    _('all %1$d hosts in group "%2$s"'),
                    $hostCount,
                    $this->obj->get('name')
                )
            );
        }
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'group-general',
            'generator' => function () {
                $this->groupGeneral();
            }
        ];

        // Tasks
        $tabData[] = [
            'name' => _('Tasks'),
            'id' => 'group-tasks',
            'generator' => function () {
                $this->groupTasks();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Associations'),
                        'id' => 'group-host',
                        'generator' => function () {
                            $this->groupHosts();
                        }
                    ],
                    [
                        'name' => _('Printer Associations'),
                        'id' => 'group-printer',
                        'generator' => function () {
                            $this->groupPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapin Associations'),
                        'id' => 'group-snapin',
                        'generator' => function () {
                            $this->groupSnapins();
                        }
                    ],
                    [
                        'name' => _('Software'),
                        'id' => 'group-software',
                        'generator' => function () {
                            $this->groupSoftware();
                        }
                    ]
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
                        'id' => 'group-module',
                        'generator' => function () {
                            $this->groupModules();
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'group-powermanagement',
                        'generator' => function () {
                            $this->groupPowermanagement();
                        }
                    ]
                ]
            ]
        ];

        // Inventory
        $tabData[] = [
            'name' => _('Inventory'),
            'id' => 'group-inventory',
            'generator' => function () {
                $this->groupInventory();
            }
        ];

        // History Items
        //
        // Login History is gated on usertracking.view rather than group.view
        // -- the group form of the same split the host page carries. See
        // ADR 0023 and the note there.
        $historyTabs = [];
        if (Authorization::can('usertracking.view')) {
            $historyTabs[] = [
                'name' => _('Login History'),
                'id' => 'group-login-history',
                'generator' => function () {
                    $this->groupLoginHistory();
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
                        'id' => 'group-imaging-history',
                        'generator' => function () {
                            $this->groupImageHistory();
                        }
                    ],
                    [
                        'name' => _('Snapin History'),
                        'id' => 'group-snapin-history',
                        'generator' => function () {
                            $this->groupSnapinHistory();
                        }
                    ]
                ])
            ]
        ];
        // Site
        $tabData[] = [
            'name' => _('Site'),
            'id' => 'group-site',
            'generator' => function () {
                $this->groupSite();
            }
        ];

        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Submit the edit function.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Group',
            'GROUP_EDIT',
            _('Group updated!'),
            _('Group Update Success'),
            _('Group Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'group-site':
                        $this->groupSitePost();
                        break;
                    case 'group-general':
                        $this->groupGeneralPost();
                        break;
                    case 'group-powermanagement':
                        $this->groupPowermanagementPost();
                        break;
                    case 'group-host':
                        $this->groupHostPost();
                        break;
                    case 'group-printer':
                        $this->groupPrinterPost();
                        break;
                    case 'group-snapin':
                        $this->groupSnapinPost();
                        break;
                    case 'group-software':
                        $this->groupSoftwarePost();
                        break;
                    case 'group-module':
                        $this->groupModulePost();
                        break;
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Group update failed!'));
                }
            }
        );
    }
    /**
     * The group tasks items.
     *
     * @return void
     */
    public function groupTasks()
    {
        // Predefine needed variables for closure function.
        global $id;
        $data = [];
        // The closure we want to use.
        $taskTypeIterator = function ($TaskType, $advanced) use (
            &$data,
            $id
        ) {
            if ($advanced != $TaskType->isAdvanced) {
                return;
            }
            $data['<a href="?node=group&sub=deploy&id='
                . $id
                . '&type='
                . $TaskType->id
                . '" class="taskitem"><i class="fas fa-'
                . $TaskType->icon
                . ' fa-2x"></i><br/>'
                . $TaskType->name
                . '</a>'
            ] = $TaskType->description;
        };
        // The keys we need to search for.
        $key = [
            'access' => [
                'group',
                'both'
            ]
        ];
        // The items we're getting.
        $items = Route::getList(
            'tasktype',
            $key,
            'AND',
            'id'
        );
        // Loop 1, the basic non-advanced tasks.
        foreach ($items as &$TaskType) {
            $taskTypeIterator($TaskType, 0);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            'GROUP_BASICTASKS_DATA',
            ['data' => &$data]
        );
        $basic = self::stripedTable($data);

        $data = [];
        $advanced = 1;
        // Loop 2, the advanced tasks.
        foreach ($items as &$TaskType) {
            $taskTypeIterator($TaskType, 1);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            'GROUP_ADVANCEDTASKS_DATA',
            ['data' => &$data]
        );
        $advanced = self::stripedTable($data);
        unset($data);
        unset($items);
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
        echo '<div id="taskAccordian">';

        // Basic Tasks
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo '<a href="#tasksBasic" class="" data-bs-toggle="collapse" '
            . 'data-bs-parent="#taskAccordian">';
        echo _('Basic Tasks');
        echo '</a>';
        echo '</h4>';
        echo '</div>';
        echo '<div id="tasksBasic" class="collapse show">';
        echo '<div class="card-body">';
        echo '<table class="table table-striped">';
        echo '<tbody>';
        echo $basic;
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Advanced Tasks
        echo '<div class="card card-warning card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo '<a href="#tasksAdvanced" class="" data-bs-toggle="collapse" '
            . 'data-bs-parent="#taskAccordian">';
        echo _('Advanced Tasks');
        echo '</a>';
        echo '</h4>';
        echo '</div>';
        echo '<div id="tasksAdvanced" class="collapse">';
        echo '<div class="card-body">';
        echo '<table class="table table-striped">';
        echo '<tbody>';
        echo $advanced;
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '<div class="card-footer">';
        echo $taskModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Presents the hosts list table.
     *
     * @return void
     */
    public function getHostsList()
    {
        return $this->assocItemsList(
            'host',
            'groupassociation',
            'groupMembers',
            '`hosts`.`hostID`',
            '`groupMembers`.`gmHostID`',
            '`groupMembers`.`gmGroupID`',
            [
                [
                    'db' => 'groupAssoc',
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
        // ADR 0038: the group owns its printers now, so this is the same
        // plain association tab the host page uses. It replaces a custom
        // query that reduced every printer to how its MEMBER hosts covered
        // it (all/some/none plus an n-of-total badge) -- machinery whose
        // only job was to reconstruct, after the fact, what the group would
        // have looked like if it had ever owned anything.
        $this->assocItemsList(
            'printer',
            'groupprinterassociation',
            'groupPrinterAssoc',
            '`printers`.`pID`',
            '`groupPrinterAssoc`.`gpaPrinterID`',
            '`groupPrinterAssoc`.`gpaGroupID`',
            [
                [
                    'db' => 'groupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Presents the selector for groups
     *
     * @return void
     */
    public function getPrintersSelect()
    {
        header('Content-tyep: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $printerID = trim((string)filter_input(INPUT_GET, 'printerID'));

        $printersAvail = Route::getIds('printer', false);
        if (!count($printersAvail ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No printers available to assign'),
                    'disablebtn' => true
                ]
            ));
        }
        // getNames(): names() answers with its rows under a `data`
        // envelope, and this wants the rows. It raises on failure rather
        // than ending the page, as asValue() did.
        $printerNames = Route::getNames('printer');
        foreach ($printerNames as &$printer) {
            $printers[$printer->id] = $printer->name;
            unset($printer);
        }
        $printerSelector = self::selectForm(
            'printer',
            $printers,
            $printerID,
            true,
            '',
            true
        );
        // Which printer this group currently grants as the default. A host
        // that has chosen its own default still keeps it -- the group's
        // answer is only used when the host has none.
        $def = $this->_groupDefaultPrinter();
        if ($def < 1) {
            $defText = _('(none)');
        } else {
            $defText = isset($printers[$def])
                ? \Initiator::e($printers[$def])
                : ('#' . $def);
        }
        $hint = '<p class="form-text help-block-spaced">'
            . _('Group default:') . ' ' . $defText
            . '</p>';
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $hint . $printerSelector,
                'disablebtn' => false
            ]
        ));
    }
    /**
     * Presents the snapins list table.
     *
     * @return void
     */
    public function getSnapinsList()
    {
        // See getPrintersList() for why this is now a plain association tab.
        $this->assocItemsList(
            'snapin',
            'groupsnapinassociation',
            'groupSnapinAssoc',
            '`snapins`.`sID`',
            '`groupSnapinAssoc`.`gsaSnapinID`',
            '`groupSnapinAssoc`.`gsaGroupID`',
            [
                [
                    'db' => 'groupAssoc',
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
        $this->assocItemsList(
            'software',
            'groupsoftwareassociation',
            'groupSoftwareAssoc',
            '`software`.`swID`',
            '`groupSoftwareAssoc`.`gswaSoftwareID`',
            '`groupSoftwareAssoc`.`gswaGroupID`',
            [
                [
                    'db' => 'groupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Returns the snapins this group grants, in run order.
     *
     * ADR 0038: the group owns the rows, so the order is read straight off
     * gsaSequence. Before the split there was nothing on the group to order,
     * so this had to intersect every member host's snapinAssoc rows to find
     * the ones they all shared and guess a starting order from the lowest
     * sequence any host happened to carry.
     *
     * @return void
     */
    public function getSnapinOrderList()
    {
        $data = [];
        $grants = Route::getList(
            'groupsnapinassociation',
            ['groupID' => $this->obj->get('id')],
            'AND',
            'sequence'
        );
        $snapinIDs = [];
        foreach ($grants as $grant) {
            $snapinIDs[] = (int)$grant->snapinID;
        }
        if (count($snapinIDs) > 0) {
            $names = [];
            $Snapins = Route::getList('snapin', ['id' => $snapinIDs]);
            foreach ($Snapins as $Snapin) {
                $names[(int)$Snapin->id] = $Snapin->name;
            }
            foreach ($snapinIDs as $sid) {
                // Same contract as the host tab: only list ids that resolve
                // to a real snapin (skip stale/0 associations).
                if (!isset($names[$sid])) {
                    continue;
                }
                $data[] = [
                    'id' => $sid,
                    'name' => $names[$sid]
                ];
            }
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['data' => $data]));
    }
    /**
     * Returns the software this group grants, in run order.
     *
     * Mirrors getSnapinOrderList() above over groupSoftwareAssoc/gswaSequence.
     *
     * @return void
     */
    public function getSoftwareOrderList()
    {
        $data = [];
        $grants = Route::getList(
            'groupsoftwareassociation',
            ['groupID' => $this->obj->get('id')],
            'AND',
            'sequence'
        );
        $softwareIDs = [];
        foreach ($grants as $grant) {
            $softwareIDs[] = (int)$grant->softwareID;
        }
        if (count($softwareIDs) > 0) {
            $names = [];
            $Softwares = Route::getList('software', ['id' => $softwareIDs]);
            foreach ($Softwares as $Software) {
                $names[(int)$Software->id] = $Software->name;
            }
            foreach ($softwareIDs as $sid) {
                // Same contract as the host tab: only list ids that resolve
                // to a real software entry (skip stale/0 associations).
                if (!isset($names[$sid])) {
                    continue;
                }
                $data[] = [
                    'id' => $sid,
                    'name' => $names[$sid]
                ];
            }
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['data' => $data]));
    }
    /**
     * Returns the module list as well as the associated
     * for the group being edited.
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
        $keys = array_diff($keys, $notWhere);
        $where = "`modules`.`short_name` IN ('"
            . implode("','", $keys)
            . "')";
        $join = [
            'LEFT OUTER JOIN `groupModuleAssoc` '
            . 'ON `modules`.`id` = `groupModuleAssoc`.`gmaModuleID` '
            . "AND `groupModuleAssoc`.`gmaGroupID` = '"
            . $this->obj->get('id')
            . "'"
        ];
        // Two states here, not the host tab's three. A grant is
        // presence-only: a group can turn a module on and cannot turn one
        // off, so there is no state column to surface (ADR 0038).
        $columns[] = [
            'db' => 'groupAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        $this->obj->getItemsList(
            'module',
            'groupmoduleassociation',
            $join,
            $where,
            $columns
        );
    }
    /**
     * Tasking for this group.
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

            $TaskType = new TaskType($type);

            $this->title = $TaskType->get('name')
                . ' '
                . $this->obj->get('name');

            $imagingTypes = $TaskType->isImagingTask();
            $iscapturetask = $TaskType->isCapture();
            $isdebug = $TaskType->isDebug();
            $hosts = $this->obj->get('hosts');

            if (!$TaskType->isValid()) {
                throw new \Exception(_('Task type is invalid'));
            }
            if (count($hosts ?: []) < 1) {
                throw new \Exception(_('There are no hosts to task'));
            }
            if ($iscapturetask) {
                throw new \Exception(_('Groups cannot create capture tasks'));
            }

            $labelClass = 'col-sm-3 col-form-label';

            // Shared with HostManagement::deploy() and deployMulti(); the
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
                'GROUP_CREATE_TASKING',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Group' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);
            ob_start();
            echo self::makeFormTag(
                '',
                'group-deploy-form',
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
        self::$HookManager->processEvent('GROUP_DEPLOY_POST');

        $serverFault = false;
        try {
            global $type;
            if (!is_numeric($type) && $type > 0) {
                $type = 1;
            }
            // Host checks:
            $hosts = $this->obj->get('hosts');
            $find = [
                'id' => $hosts,
                'pending' => ['', 0]
            ];
            $hosts = Route::getIds(
                'host',
                $find
            );
            if (count($hosts ?: []) < 1) {
                throw new \Exception(_('No hosts available to be tasked'));
            }
            $nhosts = [];
            $hostImages = [];
            $Hosts = Route::getList(
                'host',
                ['id' => $hosts]
            );
            foreach ($Hosts as &$host) {
                if (!$host->imageID) {
                    continue;
                }
                $nhosts[] = $host->id;
                $hostImages[] = $host->imageID;
                unset($host);
            }
            if (count($nhosts ?: []) < 1) {
                throw new \Exception(_('No hosts are assigned an image'));
            }

            // Multicast task requires all hosts in the group to have the same
            // imageID set.
            if (TaskType::MULTICAST == $type) {
                $hostImages = array_filter(
                    array_unique(
                        $hostImages
                    )
                );
                if (count($hostImages ?: []) != 1) {
                    throw new \Exception(
                        _('All hosts must have the same image assigned')
                    );
                }
            }

            // Task Type setup
            $TaskType = new TaskType($type);
            if (!$TaskType->isValid()) {
                throw new \Exception(_('Task Type is invalid'));
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

            // Generic setup
            $imagingTasks = $TaskType->isImagingTask();
            $taskName = sprintf(
                '%s Task',
                $TaskType->get('name')
            );

            // Shutdown setup
            $enableShutdown = isset($_POST['shutdown']);

            // Debug setup
            $enableDebug = false;
            $debug = isset($_POST['debug']);
            $isdebug = isset($_POST['isDebugTask']);
            if ($debug || $isdebug) {
                $enableDebug = true;
            }

            // WOL Setup
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

            // Task Type Imaging Checks
            if ($TaskType->isImagingTask()) {
                if ($TaskType->isCapture()) {
                    throw new \Exception(_('Groups cannot create capture tasks'));
                }
            }

            // Actually create tasking
            if ($scheduleType == 'instant') {
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
                $this->obj->createImagePackage(
                    $tasktype,
                    $taskName,
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
            } else {
                $ScheduledTask = (new ScheduledTask())
                    ->set('taskTypeID', $type)
                    ->set('name', $taskName)
                    ->set('hostID', $this->obj->get('id'))
                    ->set('shutdown', $enableShutdown)
                    ->set('other1', (int)$snapinAbortOnFailure)
                    ->set('other2', $enableSnapins)
                    ->set('type', $scheduleType == 'single' ? 'S' : 'C')
                    ->set('isGroupTask', 1)
                    ->set('other3', self::$FOGUser->get('name'))
                    ->set('isActive', 1)
                    ->set('other4', $wol);
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
            $hook = 'GROUP_DEPLOY_SUCCESS';
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
            $hook = 'GROUP_DEPLOY_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create Task Fail')
                ]
            );
        }

        $this->jsonHookResponse(
            [
                'Group' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Get the login history for hosts in this group
     *
     * @return void
     */
    public function getLoginHist()
    {
        $this->renderHistoryData($this->obj->get('hosts'), 'usertracking');
    }
    /**
     * Get the image history for hosts in this group.
     *
     * @return void
     */
    public function getImageHist()
    {
        // taskLog since imagingLog was retired -- see the host page's
        // equivalent and ADR 0022 decision 3.
        $this->renderHistoryData($this->obj->get('hosts'), 'tasklog');
    }
    /**
     * Gets the snapin history for hosts in this group.
     *
     * @return void
     */
    public function getSnapinHist()
    {
        $this->renderSnapinHistoryData($this->obj->get('hosts'));
    }

    /**
     * Presents the site tab.
     *
     * @return void
     */
    public function groupSite()
    {
        $this->renderSiteTab('group', $this->obj);
    }
    /**
     * Updates the site.
     *
     * @return void
     */
    public function groupSitePost()
    {
        $this->siteTabPost('group', $this->obj);
    }
}
