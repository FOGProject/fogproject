<?php
/**
 * The host object (main item FOG deals with
 *
 * PHP version 7.4+
 *
 * @category Host
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Agent\WakeRelay;
use FOG\Assign\Resolver;
use FOG\Base\FOGController;
use FOG\Boot\SecureBootState;
use FOG\Boot\UbootTftpSync;
use FOG\Managers\MACAddressAssociationManager;
use FOG\Managers\PrinterAssociationManager;
use FOG\Managers\SnapinAssociationManager;
use FOG\Managers\SnapinJobManager;
use FOG\Managers\SnapinTaskManager;
use FOG\Managers\SoftwareAssociationManager;
use FOG\Managers\TaskManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

/**
 * The host object (main item FOG deals with
 *
 * @category Host
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */
class Host extends FOGController
{
    /**
     * The host table
     *
     * @var string
     */
    protected $databaseTable = 'hosts';
    /**
     * The Host table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hostID',
        'name' => 'hostName',
        'description' => 'hostDesc',
        'ip' => 'hostIP',
        'imageID' => 'hostImage',
        'building' => 'hostBuilding',
        'createdTime' => 'hostCreateDate',
        'deployed' => 'hostLastDeploy',
        'createdBy' => 'hostCreateBy',
        'useAD' => 'hostUseAD',
        'ADDomain' => 'hostADDomain',
        'ADOU' => 'hostADOU',
        'ADUser' => 'hostADUser',
        'ADPass' => 'hostADPass',
        'ADPassLegacy' => 'hostADPassLegacy',
        'productKey' => 'hostProductKey',
        'printerLevel' => 'hostPrinterLevel',
        'kernelArgs' => 'hostKernelArgs',
        'kernel' => 'hostKernel',
        'kernelDevice' => 'hostDevice',
        'init' => 'hostInit',
        'pending' => 'hostPending',
        'pub_key' => 'hostPubKey',
        'sec_tok' => 'hostSecToken',
        // The token superseded by the most recent rotation. authorize()
        // commits a rotated sec_tok before the client can possibly have
        // received it, so a response lost in flight used to strand the client
        // on a token the server no longer recognized -- an unrecoverable
        // #!ist. Keeping one generation lets that client re-present its old
        // token once and be handed the current one again.
        'prev_sec_tok' => 'hostSecTokenPrev',
        'sec_time' => 'hostSecTime',
        'pingstatus' => 'hostPingCode',
        // WHICH probe produced pingstatus -- 'icmp' or 'tcp', empty when
        // unknown. Separate because pingstatus is an errno and an ICMP echo
        // reply has none; both an echo reply and a completed connect record
        // 0, and only this says which. See schema step 356.
        'pingmethod' => 'hostPingMethod',
        // The two halves of "when was this host last seen". lastping is
        // written by FOGPingHosts whenever the host answered; lastcheckin by
        // FOGClient on every client request. Kept apart on purpose -- see
        // schema step 353. "Last seen" is MAX(the two) and is derived where
        // it is displayed, never stored.
        'lastping' => 'hostLastPing',
        'lastcheckin' => 'hostLastCheckin',
        // The architecture last observed for this host, as a row in
        // `architectures` (schema step 372; it was a free-text column in 369).
        // Stored in iPXE's vocabulary -- not uname's -- so it matches the
        // value the boot decision is made from. NULL until the host PXE boots
        // once, or until someone sets it on the edit form for a host that
        // never will. Advisory either way: IpxeBootMenu still chooses a kernel
        // from the live request, never from here.
        'archID' => 'hostArchID',
        // The Secure Boot ledger, in two halves that are deliberately not
        // one thing (schema steps 376 and 377).
        //
        // sbstate is OBSERVED: what iPXE told us on the last PXE boot, in
        // FOG\Boot\SecureBootState's vocabulary, stamped with the server's
        // own clock. Never editable, in the UI or over the API -- it is a
        // report, not a claim, and Route::$serverOwnedFields refuses a write
        // to either field.
        //
        // sbenrolled and its two companions are ASSERTED: a record that an
        // enrollment happened, which IS editable because a technician with a
        // USB stick is one of the three ways it happens and the only one FOG
        // cannot observe. sbenrollcert is the SHA-256 of what was enrolled,
        // so "does this host trust the certificate I serve today" is
        // answerable rather than inferred from a date.
        //
        // Advisory, both halves. Every value here originates in an
        // unauthenticated boot request or a text box. Nothing reads them as
        // a security control -- see ADR 0029.
        'sbstate' => 'hostSbState',
        'sbstatetime' => 'hostSbStateTime',
        'sbenrolled' => 'hostSbEnrolled',
        'sbenrollcert' => 'hostSbEnrollCert',
        'sbenrollvia' => 'hostSbEnrollVia',
        'biosexit' => 'hostExitBios',
        'efiexit' => 'hostExitEfi',
        'enforce' => 'hostEnforce',
        'token' => 'hostInfoKey',
        'tokenlock' => 'hostInfoLock',
        // fog-agent binding (schema 416). agentFingerprint is the sha256 of
        // the agent key's SubjectPublicKeyInfo and is what a client
        // certificate is matched against; the rest is what the agent last
        // reported about itself.
        'agentFingerprint' => 'hostAgentFingerprint',
        'agentNotAfter' => 'hostAgentNotAfter',
        'agentVersion' => 'hostAgentVersion',
        'agentCheckin' => 'hostAgentCheckin'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'mac',
        'primac',
        'imagename',
        'groups',
        'hostscreen',
        'hostalo',
        'optimalStorageNode',
        'printers',
        'snapins',
        // Named 'softwares', not 'software': assocSetter() pluralizes the
        // alter item it is given as "{$alterItem}s", so save()'s
        // assocSetter('Software', 'software') diffs against this exact key.
        // See appendSoftwareSequence() and loadSoftwares() below.
        'softwares',
        'modules',
        'inventory',
        'task',
        'snapinjob',
        'users',
        'fingerprint',
        'powermanagementtasks',
        'arch'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'MACAddressAssociation' => [
            'hostID',
            'id',
            'primac',
            ['primary' => 1]
        ],
        'Image' => [
            'id',
            'imageID',
            'imagename'
        ],
        'HostScreenSetting' => [
            'hostID',
            'id',
            'hostscreen'
        ],
        'HostAutoLogout' => [
            'hostID',
            'id',
            'hostalo'
        ],
        'Inventory' => [
            'hostID',
            'id',
            'inventory'
        ]
        // No 'Architecture' entry, deliberately -- see loadArch() below.
    ];

    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'
        %s
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'";
    /**
     * Display val storage
     *
     * @var array
     */
    private static $_hostscreen = [];
    /**
     * ALO time val
     *
     * @var int
     */
    private static $_hostalo = [];
    /**
     * Set value to key
     *
     * @param string $key   the key to set to
     * @param mixed  $value the value to set
     *
     * @throws Exception
     * @return object
     */
    public function set($key, $value)
    {
        $key = $this->key($key);
        switch ($key) {
            case 'mac':
                if (!($value instanceof MACAddress)) {
                    $value = new MACAddress($value);
                    $value = $value->__toString();
                }
                break;
            case 'snapinjob':
                if (!($value instanceof SnapinJob)) {
                    $value = new SnapinJob($value);
                }
                break;
            case 'task':
                if (!($value instanceof Task)) {
                    $value = new Task($value);
                }
        }
        return parent::set($key, $value);
    }
    /**
     * Removes the item from the database
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     * @return object
     */
    public function destroy($key = 'id')
    {
        // DESTROY_HOST is fired by Route::deletemass(), not here. Firing it
        // here only reached the UI path -- the REST delete goes straight to
        // deletemass and never builds a Host, so no listener ever heard about
        // a host deleted over the API. See the destroy-event block there.
        //
        // Funnel the child/association cleanup through the single cascade
        // authority (Route::deletemass) so the host single-delete path runs the
        // exact same removeItems map and fires DELETEMASS_API for plugins
        // (location/ou/windowskey/site), instead of duplicating the map here.
        // deletemass also deletes the host row; the trailing parent::destroy()
        // is then a harmless no-op that preserves the audit-log/history entry.
        Route::deletemass('host', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
    /**
     * Stores data into the database
     *
     * @return bool|object
     */
    public function save()
    {
        if (!$this->isHostnameSafe()) {
            throw new \Exception(
                _('Invalid hostname; must be 1-15 of these characters: ')
                . 'a-z 0-9 ! @ # $ % ^ ( ) - \' { } . ~ _'
            );
        }
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        if (array_key_exists('mac', $this->data)) {
            (new MACAddressAssociation())
                ->set('mac', $this->get('mac'))
                ->set('primary', '1')
                ->set('hostID', $this->get('id'))
                ->save();
        }
        if (array_key_exists('powermanagementtasks', $this->data)) {
            $find = ['hostID' => $this->get('id')];
            $DBPowerManagementIDs = Route::getIds(
                'powermanagement',
                $find
            );
            $RemovePowerManagementIDs = array_diff(
                (array)$DBPowerManagementIDs,
                (array)$this->get('powermanagementtasks')
            );
            if (count($RemovePowerManagementIDs)) {
                Route::deletemass(
                    'powermanagement',
                    [
                        'hostID' => $this->get('id'),
                        'id'=> $RemovePowerManagementIDs
                    ]
                );
                $DBPowerManagementIDs = Route::getIds(
                    'powermanagement',
                    $find
                );
                unset($RemovePowerManagementIDs);
            }
            $objNeeded = false;
            unset($DBPowerManagementIDs, $RemovePowerManagementIDs);
        }
        // The stale/blank snapin id filter that used to sit here (8e31b5cf0)
        // now lives in assocSetter(), where it guards every relation instead
        // of this one. Filtering at a single caller was the original mistake:
        // it left the other assocSetter() call sites able to persist an id-0
        // row, and the isLoaded() gate it needed in order to run at all is
        // what poisoned assocSetter into wiping the host's snapins (PR #906).
        // An explicit OFF row must survive a save that did not ask to clear
        // it. ADR 0038: only a HOST may say a module is off, and that is the
        // one statement a group grant cannot overrule -- so losing it
        // silently switches a module back on across a fleet.
        //
        // assocSetter deletes ($cur - $items), where $cur is every row in
        // moduleStatusByHost and $items is get('modules'). loadModules()
        // filters to state 1, so an OFF row is in $cur and never in $items:
        // the two sides of the diff are not reading the same set, and every
        // save that touches modules at all would drop it. Union the OFF ids
        // into the diff set so they appear on both sides and neither insert
        // nor delete fires for them.
        //
        // The consequence, stated because it is a real limit rather than an
        // oversight: a caller that lists `modules` cannot turn an OFF module
        // back ON, because the row already exists and assocSetter only ever
        // inserts or deletes. Clearing an OFF is the host Modules tab's job
        // (Host::setModuleState()), which writes msState directly. That
        // fails closed, which is the direction this rule wants.
        if ($this->isDirty('modules')) {
            $off = self::statedModuleIDs($this->get('id'), 0);
            if (count($off)) {
                $this->set(
                    'modules',
                    array_values(
                        array_unique(
                            array_merge(
                                (array)$this->get('modules'),
                                $off
                            )
                        )
                    )
                );
            }
        }
        $this
            ->assocSetter('Group', 'group')
            ->assocSetter('Module', 'module')
            ->assocSetter('Printer', 'printer')
            ->assocSetter('Snapin', 'snapin')
            ->assocSetter('Software', 'software');
        // assocSetter inserts new snapin associations with sequence 0; give
        // any unsequenced rows a run-order value after the existing ones so
        // newly added snapins land at the end rather than jumping to front.
        // isDirty(), not isPopulated(): matches assocSetter()'s own gate --
        // there's nothing new to sequence if the caller never touched
        // snapins, even if something else in the request read them.
        if ($this->isDirty('snapins')) {
            self::appendSnapinSequence($this->get('id'));
        }
        // Same reasoning as the snapin sequence above, for software's
        // swaSequence. isDirty('softwares') because assocSetter() diffs
        // against 'softwares' (the pluralized alter item), not 'software'.
        if ($this->isDirty('softwares')) {
            self::appendSoftwareSequence($this->get('id'));
        }
        // Safety net: never leave the host with MAC rows but no primary MAC.
        // The primac join requires hmPrimary='1', so a host with no primary
        // MAC cannot be loaded and becomes un-editable via the API/GUI. If an
        // update (e.g. replacing the MAC set) removed the former primary,
        // promote the first remaining approved (non-pending) MAC so the host
        // stays reachable.
        $hostID = $this->get('id');
        if ($hostID) {
            $primaryMacs = Route::getIds(
                'macaddressassociation',
                ['hostID' => $hostID, 'primary' => '1'],
                'mac'
            );
            if (count((array)$primaryMacs) < 1) {
                $approvedMacs = Route::getIds(
                    'macaddressassociation',
                    ['hostID' => $hostID, 'pending' => '0'],
                    'mac'
                );
                if (count((array)$approvedMacs) > 0) {
                    (new MACAddressAssociationManager())
                        ->update(
                            [
                                'hostID' => $hostID,
                                'mac' => array_shift($approvedMacs)
                            ],
                            '',
                            ['primary' => '1']
                        );
                }
            }
        }
        return $this->load();
    }
    /**
     * Defines if the host is valid
     *
     * @return bool
     */
    public function isValid()
    {
        return parent::isValid() && $this->isHostnameSafe();
    }
    /**
     * Tells us if the hostname is safe to use
     *
     * @param string $hostname the hostname to test
     *
     * @return bool
     */
    public function isHostnameSafe($hostname = '')
    {
        if (empty($hostname)) {
            $hostname = $this->get('name');
        }
        $pattern = "/^[\w!@#$%^()\-'{}\.~]{1,15}$/";
        return (bool)preg_match($pattern, $hostname);
    }
    /**
     * Returns if the printer is the default
     *
     * @param int $printerid the printer id to test
     *
     * @return bool
     */
    public function getDefault($printerid)
    {
        return Route::getCount(
            'printerassociation',
            [
                'hostID' => $this->get('id'),
                'printerID' => $printerid,
                'isDefault' => 1
            ]
        ) > 0;
    }
    /**
     * Updates the default printer
     *
     * @param int   $printerid the printer id to update
     *
     * @return object
     */
    public function updateDefault($printerid)
    {
        $printers = array_diff(
            $this->get('printers'),
            [$printerid]
        );
        (new PrinterAssociationManager())
            ->update(
                [
                    'printerID' => $printers,
                    'hostID' => $this->get('id'),
                    'isDefault' => 1
                ],
                '',
                ['isDefault' => 0]
            );
        if ($printerid) {
            (new PrinterAssociationManager())
                ->update(
                    [
                        'printerID' => $printerid,
                        // Schema 426 made paIsDefault a tinyint(1). The
                        // empty string this used to also match was the
                        // 1.5-origin varchar's "never set", and the upgrade
                        // normalizes it to 0.
                        'hostID' => $this->get('id'),
                        'isDefault' => 0
                    ],
                    '',
                    ['isDefault' => 1]
                );
        }
        return $this;
    }
    /**
     * Sets display vals for the host
     *
     * @return void
     */
    private function _setDispVals()
    {
        if (count(self::$_hostscreen)) {
            return;
        }
        $keys = [
            'FOG_CLIENT_DISPLAYMANAGER_R',
            'FOG_CLIENT_DISPLAYMANAGER_X',
            'FOG_CLIENT_DISPLAYMANAGER_y'
        ];
        list(
            $refresh,
            $width,
            $height
        ) = self::getSetting($keys);
        $refresh = (
            $this->get('hostscreen')->get('refresh') ?:
            $refresh
        );
        $width = (
            $this->get('hostscreen')->get('width') ?:
            $width
        );
        $height = (
            $this->get('hostscreen')->get('height') ?:
            $height
        );
        self::$_hostscreen = [
            'refresh' => $refresh,
            'width' => $width,
            'height' => $height
        ];
    }
    /**
     * Gets the display values
     *
     * @param string $key the key to get
     *
     * @return mixed
     */
    public function getDispVals($key = '')
    {
        $this->_setDispVals();
        return self::$_hostscreen[$key];
    }
    /**
     * Sets the display values
     *
     * @param mixed $x the width
     * @param mixed $y the height
     * @param mixed $r the refresh
     *
     * @return object
     */
    public function setDisp($x, $y, $r)
    {
        if (!$this->get('hostscreen')->isValid()) {
            $this->get('hostscreen')
                ->set('hostID', $this->get('id'));
        }
        $this->get('hostscreen')
            ->set('width', $x)
            ->set('height', $y)
            ->set('refresh', $r)
            ->save();
        return $this;
    }
    /**
     * Sets this hosts alo time (or default to global if needed
     *
     * @return void
     */
    private function _setAlo()
    {
        if (!empty(self::$_hostalo)) {
            return;
        }
        self::$_hostalo = (
            $this->get('hostalo')->get('time') ?:
            self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN')
        );
    }
    /**
     * Gets the auto logout time
     *
     * @return int
     */
    public function getAlo()
    {
        $this->_setAlo();
        return self::$_hostalo;
    }
    /**
     * Sets the auto logout time
     *
     * @param int $time the time to set
     *
     * @return object
     */
    public function setAlo($time)
    {
        return $this->get('hostalo')
            ->set('hostID', $this->get('id'))
            ->set('time', $time)
            ->save();
    }
    /**
     * Loads the mac additional field
     *
     * @return void
     */
    protected function loadMac()
    {
        $mac = new MACAddress($this->get('primac'));
        $this->set('mac', $mac);
    }
    /**
     * Loads the arch additional field
     *
     * A lazy load rather than a databaseFieldClassRelationships entry, which
     * is what it was and what did not work. buildQuery() keys its JOIN map by
     * lowercase class name, one slot per class. Host relates to Image, Image
     * relates to Architecture, and Image is declared first -- so Image's
     * nested join claimed the single 'architecture' slot and Host's own join
     * off hosts.hostArchID was skipped by the array_key_exists() guard:
     *
     *   LEFT OUTER JOIN `architectures`
     *     ON `architectures`.`archID` = `images`.`imageArchID`
     *
     * Reordering the map only moves the problem, because SELECT
     * `architectures`.* returns ONE row per result row either way -- the join
     * shape cannot carry a host's architecture and its image's at the same
     * time without an alias, which the builder has no way to express.
     *
     * The visible cost was not the undefined-method fatal that led here. It
     * was that $this->get('arch') silently answered with the IMAGE's
     * architecture, so the image/host compatibility guard in getImageMemberFromHostID()
     * compared the image against itself and could never fire: a host recorded
     * as arm64 running an i386 image read as i386 on both sides.
     *
     * @return void
     */
    protected function loadArch()
    {
        $arch = new Architecture($this->get('archID'));
        $this->set('arch', $arch);
    }
    /**
     * Loads any groups this host is in
     *
     * @return void
     */
    protected function loadGroups()
    {
        $find = ['hostID' => $this->get('id')];
        $groups = Route::getIds(
            'groupassociation',
            $find,
            'groupID'
        );
        $this->set('groups', (array)$groups);
    }
    /**
     * Loads any printers those host has
     *
     * @return void
     */
    protected function loadPrinters()
    {
        $find = ['hostID' => $this->get('id')];
        $printers = Route::getIds(
            'printerassociation',
            $find,
            'printerID'
        );
        $this->set('printers', (array)$printers);
    }
    /**
     * Loads any snapins this host has
     *
     * @return void
     */
    protected function loadSnapins()
    {
        $find = ['hostID' => $this->get('id')];
        $snapins = Route::getIds(
            'snapinassociation',
            $find,
            'snapinID',
            'AND',
            'sequence'
        );
        $this->set('snapins', (array)$snapins);
    }
    /**
     * Loads any software directly assigned to this host, in run order.
     *
     * Mirrors loadSnapins(); the field is 'softwares' rather than
     * 'software' -- see the additionalFields comment above for why.
     *
     * @return void
     */
    protected function loadSoftwares()
    {
        $find = ['hostID' => $this->get('id')];
        $software = Route::getIds(
            'softwareassociation',
            $find,
            'softwareID',
            'AND',
            'sequence'
        );
        $this->set('softwares', (array)$software);
    }
    /**
     * Loads the modules this host itself has turned ON.
     *
     * HOST-DIRECT, NOT RESOLVED, and that is the whole point. This is the
     * value the edit surfaces diff against -- Route's host update arm
     * computes its add/remove lists from it, and the host page's module tab
     * ticks it. If it returned the group-granted modules too, saving either
     * of those would write a host row for every grant, turning a grant back
     * into a copy: exactly what ADR 0038 exists to stop. What the client
     * gets is resolvedModules().
     *
     * The `state` filter is new and changes nothing today. Every row in
     * `moduleStatusByHost` on every existing install carries state 1,
     * because the only writer -- FOGController::addRemItem() -- hardcodes
     * it. A row meaning OFF is the new thing, and this is what keeps it out
     * of the ON list once something writes one.
     *
     * @return void
     */
    protected function loadModules()
    {
        $find = ['hostID' => $this->get('id'), 'state' => 1];
        $modules = Route::getIds(
            'moduleassociation',
            $find,
            'moduleID'
        );
        $this->set('modules', (array)$modules);
    }
    /**
     * The modules actually enabled on this host, grants included.
     *
     * ADR 0038: a group GRANTS a module. What a host ends up with is this
     * host's own ON rows, plus every module granted by a group it is in,
     * minus anything this host has explicitly turned OFF. The reasoning for
     * the three tiers lives on Resolver::resolveModules(); this is the
     * accessor the client protocol reads.
     *
     * NOT a loadX() pseudo-field. Those are cached on the object and the
     * edit surfaces diff against them, and a value that changes when someone
     * edits a GROUP has no business being cached on a host or being written
     * back. Kept as an explicit call so every caller is visibly asking for
     * the resolved answer rather than picking it up by accident.
     *
     * @return array module ids, ascending
     * @throws \RuntimeException on any query failure
     */
    public function resolvedModules()
    {
        $id = (int)$this->get('id');
        if ($id < 1) {
            return [];
        }
        $resolved = Resolver::resolveModules([$id]);

        return (array)($resolved[$id] ?? []);
    }
    /**
     * Loads any powermanagement tasks this host has
     *
     * @return void
     */
    protected function loadPowermanagementtasks()
    {
        $find = ['hostID' => $this->get('id')];
        $pms = Route::getIds(
            'powermanagement',
            $find
        );
        $this->set('powermanagementtasks', (array)$pms);
    }
    /**
     * Loads any users have logged in
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['hostID' => $this->get('id')];
        $users = Route::getIds(
            'usertracking',
            $find
        );
        $this->set('users', (array)$users);
    }
    /**
     * Loads the current snapin job
     *
     * @return void
     */
    protected function loadSnapinjob()
    {
        $find = ['hostID' => $this->get('id')];
        $find['stateID'] = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $snapinjobs = Route::getIds(
            'snapinjob',
            $find
        );
        $sjID = array_shift($snapinjobs);
        $this->set('snapinjob', new SnapinJob($sjID));
    }
    /**
     * Loads the current task
     *
     * @return void
     */
    protected function loadTask()
    {
        $find['hostID'] = $this->get('id');
        $find['stateID'] = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $types = [
            'up',
            'down'
        ];
        $type = filter_input(INPUT_POST, 'type');
        if (!$type) {
            $type = filter_input(INPUT_GET, 'type');
        }
        $type = trim($type);
        if (in_array($type, $types)) {
            if ($type === 'up') {
                $find['typeID'] = TaskType::CAPTURETASKS;
            } else {
                $find['typeID'] = TaskType::DEPLOYTASKS;
            }
        }
        $taskID = Route::getIds(
            'task',
            $find
        );
        $taskID = array_shift($taskID);
        $this->set('task', $taskID);
        unset($find);
    }
    /**
     * Loads the optimal storage node
     *
     * @return void
     */
    protected function loadOptimalStorageNode()
    {
        $node = $this
            ->getImage()
            ->getStorageGroup()
            ->getOptimalStorageNode();
        $this->set('optimalStorageNode', $node);
    }
    /**
     * Gets the active task count
     *
     * @return int
     */
    public function getActiveTaskCount()
    {
        $find = [
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            ),
            'hostID' => $this->get('id')
        ];
        return Route::getCount(
            'task',
            $find
        );
    }
    /**
     * Returns the optimal storage node
     *
     * @return object
     */
    public function getOptimalStorageNode()
    {
        return $this->get('optimalStorageNode');
    }
    /**
     * Creates the tasking so I don't have to keep typing it in for each element.
     *
     * @param string   $taskName        the name to assign to the tasking
     * @param int      $taskTypeID      the task type id to set the tasking
     * @param string   $username        the username to associate with
     * @param int|null $groupID         the Storage Group ID to associate
     *                                  with; null when no group serves it
     * @param int|null $memID           the Storage Node ID to associate
     *                                  with; null when no node serves it
     * @param bool     $imagingTask     if the task is an imaging type
     * @param bool     $shutdown        if the task is to be shutdown once done
     * @param string   $passreset       if the task is a password reset task
     * @param bool     $debug           if the task is a debug task
     * @param bool     $wol             if the task is to wol
     * @param bool     $bypassbitlocker bypass bitlocker checks
     *
     * @return object
     */
    private function _createTasking(
        $taskName,
        $taskTypeID,
        $username,
        $groupID,
        $memID,
        $imagingTask = true,
        $shutdown = false,
        $passreset = false,
        $debug = false,
        $wol = false,
        $bypassbitlocker = false
    ) {
        $Task = (new Task())
            ->set('name', $taskName)
            ->set('createdBy', $username)
            ->set('hostID', $this->get('id'))
            ->set('isForced', 0)
            ->set('stateID', self::getQueuedState())
            ->set('typeID', $taskTypeID)
            ->set('storagegroupID', $groupID)
            ->set('storagenodeID', $memID)
            ->set('wol', (string)intval($wol))
            ->set('host', $this)
            ->set('image', $this->getImage())
            ->set('tasktype', new TaskType($taskTypeID))
            ->set('TaskState', new TaskState(self::getQueuedState()))
            ->set('StorageGroup', $this->getImage()->getStorageGroup())
            ->set('StorageNode', new StorageNode())
            ->set('NFSLastMemberID', $memID)
            ->set('bypassbitlocker', ($bypassbitlocker ? '1' : '0'));
        if ($imagingTask) {
            $Task->set('imageID', $this->getImage()->get('id'));
        }
        if ($shutdown) {
            $Task->set('shutdown', $shutdown);
        }
        if ($debug) {
            $Task->set('isDebug', $debug);
        }
        if ($passreset) {
            $Task->set('passreset', $passreset);
        }
        return $Task;
    }
    /**
     * Cancels and tasks/jobs for snapins on this host
     *
     * @return void
     */
    private function _cancelJobsSnapinsForHost()
    {
        $find = [
            'hostID' => $this->get('id'),
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            )
        ];
        $SnapinJobs = Route::getIds(
            'snapinjob',
            $find
        );
        (new SnapinTaskManager())
            ->update(
                [
                    'jobID' => $SnapinJobs,
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                ],
                '',
                [
                    'return' => -9999,
                    'details' => _('Canceled due to new tasking.'),
                    'stateID' => self::getCancelledState()
                ]
            );
        (new SnapinJobManager())
            ->update(
                ['id' => $SnapinJobs],
                '',
                ['stateID' => self::getCancelledState()]
            );
        $AllTasks = Route::getIds(
            'task',
            $find
        );
        $MyTask = $this->get('task')->get('id');
        (new TaskManager())
            ->update(
                [
                    'id' => array_diff(
                        (array)$AllTasks,
                        (array)$MyTask
                    )
                ],
                '',
                [
                    'stateID' => self::getCancelledState(),
                    'stateChangedTime' => self::niceDate()->format(
                        'Y-m-d H:i:s'
                    )
                ]
            );
    }
    /**
     * Creates the snapin tasking as needed
     *
     * @param int    $snapin The snapin to create tasking on (-1 = all)
     * @param bool   $error  Whether to die on error or not
     * @param object $Task   The task object
     *
     * @return void
     */
    private function _createSnapinTasking(
        $snapin = -1,
        $abortOnFailure = false,
        $error = false,
        $Task = false
    ) {
        try {
            // ADR 0038 decision 4. The "all snapins" list is RESOLVED once,
            // here, and written onto the task: the host's own associations
            // plus whatever its groups grant, in the order Resolver
            // promises. `snapinTasks` is already the snapshot, so a group
            // edited while this task is in flight does not change it --
            // re-tasking is the only way to pick a change up.
            //
            // Resolved ONCE and reused below rather than read twice. The old
            // code called $this->get('snapins') here for the emptiness guard
            // and again further down for the ids, which was harmless when
            // both came off the same loaded property and is not once the
            // answer involves a query.
            $resolved = [];
            if (-1 == $snapin) {
                $hostID = (int)$this->get('id');
                $resolved = Resolver::resolveSnapins([$hostID])[$hostID] ?? [];
                if (count($resolved) <= 0) {
                    throw new \Exception(_('No snapins associated'));
                }
            }
            $SnapinJob = $this->get('snapinjob');
            if (!$SnapinJob->isValid()) {
                $SnapinJob
                    ->set('hostID', $this->get('id'))
                    ->set('stateID', self::getQueuedState())
                    ->set('abortOnFail', (int)(bool)$abortOnFailure)
                    ->set(
                        'createdTime',
                        self::niceDate()
                        ->format('Y-m-d H:i:s')
                    );
                if (!$SnapinJob->save()) {
                    throw new \Exception(_('Failed to create Snapin Job'));
                }
            } elseif ((int)$SnapinJob->get('abortOnFail')
                !== (int)(bool)$abortOnFailure
            ) {
                $SnapinJob
                    ->set('abortOnFail', (int)(bool)$abortOnFailure)
                    ->save();
            }
            $insert_fields = ['jobID', 'stateID', 'snapinID', 'sequence'];
            $insert_values = [];
            if ($snapin == -1) {
                $snapin = $resolved;
            }
            // Drop any 0/blank snapin id before it becomes a snapintask row
            // that renders as a phantom "null" snapin. Mirrors the same guard
            // save() applies to the snapinAssoc rows; legacy assoc rows that
            // predate that guard still carry a snapinID of 0.
            $snapin = self::positiveIntIds((array)$snapin);
            if (count($snapin) < 1) {
                throw new \Exception(_('No snapins associated'));
            }
            // The job id gets the same treatment as the snapin id above. A
            // task is only reachable through its job, so one inserted against
            // a jobID of 0 can never be shown, run or canceled -- it is a row
            // nothing can ever act on. save() failing is already caught above;
            // this catches a save that reported success without leaving an id
            // behind. See the matching guard in Group::createImagePackage().
            $snapinJobID = (int)$SnapinJob->get('id');
            if ($snapinJobID < 1) {
                throw new \Exception(_('Failed to create Snapin Job'));
            }
            $nextSequence = 1;
            // listem order is ASC by the requested field, so the last row has max sequence.
            $existingTasks = Route::getList(
                'snapintask',
                ['jobID' => $snapinJobID],
                'AND',
                'sequence'
            );
            if (count($existingTasks) > 0) {
                $lastTask = end($existingTasks);
                $nextSequence = max(1, (int)$lastTask->sequence + 1);
            }
            foreach ((array)$snapin as &$snapinID) {
                $insert_values[] = [
                    $snapinJobID,
                    $this->getQueuedState(),
                    $snapinID,
                    $nextSequence++
                ];
                unset($snapinID);
            }
            if (count($insert_values) > 0) {
                (new SnapinTaskManager())
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        } catch (\Exception $e) {
            if ($error) {
                $Task->cancel();
                throw new \Exception($e->getMessage());
            }
        }
        return $this;
    }
    /**
     * Creates tasking for the host based on the type
     *
     * @param int    $TaskType        the task type
     * @param string $taskName        the name of the task
     * @param bool   $shutdown        whether to shutdown or reboot
     * @param bool   $debug           is this a debug task
     * @param mixed  $deploySnapins   snapins to deploy
     * @param bool   $isGroupTask     is the tasking a group task
     * @param string $username        the username creating the task
     * @param string $passreset       username that needs password reset
     * @param bool   $sessionjoin     is this task joining an mc task
     * @param bool   $wol             should we wake the host up
     * @param bool   $bypassbitlocker bypass bitlocker?
     * @param bool   $snapinAbortOnFailure abort remaining snapins on failure?
     *
     * @return string
     */
    public function createImagePackage(
        $TaskType,
        $taskName = '',
        $shutdown = false,
        $debug = false,
        $deploySnapins = false,
        $isGroupTask = false,
        $username = '',
        $passreset = '',
        $sessionjoin = false,
        $wol = false,
        $bypassbitlocker = false,
        $snapinAbortOnFailure = false,
        $sessExpected = 0,
        $sessMaxwait = 0
    ) {
        if (!$sessionjoin) {
            $taskName .= ' - '
                . $this->get('name')
                . ' '
                . self::niceDate()->format('Y-m-d H:i:s');
        }
        $serverFault = false;
        try {
            if (!$this->isValid()) {
                throw new \Exception(self::$foglang['HostNotValid']);
            }
            $Task = $this->get('task');
            // A non-imaging active task (e.g. a queued snapin task) must not
            // block a new imaging task. Cancel it and reset $Task so the host
            // is treated as having no active task, letting the imaging task be
            // created below.
            if ($Task->isValid()
                && $TaskType->isImagingTask
                && !$Task->getTaskType()->isImagingTask()
            ) {
                $Task->cancel();
                $Task = new Task();
            }
            // Block only if the host is already in an imaging task.
            if ($Task->isValid() && $TaskType->isImagingTask) {
                throw new \Exception(self::$foglang['InTask']);
            }

            // Snapin Tasking
            if ($TaskType->isSnapinTasking) {
                switch ($TaskType->id) {
                    case TaskType::SINGLE_SNAPIN:
                        $find = [
                            'jobID' => $this->get('snapinjob')->get('id'),
                            'stateID' => self::fastmerge(
                                $this->getQueuedStates(),
                                (array)$this->getProgressState()
                            )
                        ];
                        $curSnapins = Route::getIds(
                            'snapintask',
                            $find,
                            'snapinID'
                        );
                        if (!in_array($deploySnapins, $curSnapins)) {
                            $Task
                                ->set('hostID', $this->get('hostID'))
                                ->set('name', _('Multiple Snapin -- orig Single'))
                                ->set('typeID', TaskType::ALL_SNAPINS);
                            // A task reached here is normally a NEW one --
                            // the branch above cancels any live non-imaging
                            // task and replaces it with an empty object, and
                            // a live imaging task has already thrown. Three
                            // fields and a save() therefore INSERTS, and
                            // taskStateID was not among them: save()'s
                            // optional-*id branch filled it with 0, which is
                            // not a taskStates row, so the task never showed
                            // in Active Tasks and never ran. Schema step 389
                            // turns that into a visible 1452 rather than a
                            // silent dud; this is the actual fix.
                            //
                            // Guarded rather than unconditional so that an
                            // existing task being converted keeps whatever
                            // state it is already in.
                            if (!$Task->get('stateID')) {
                                $Task->set('stateID', self::getQueuedState());
                            }
                            if (!$Task->save()) {
                                $serverFault = true;
                                throw new \Exception(_('Unable to update task'));
                            }
                        }
                        break;
                    case TaskType::ALL_SNAPINS:
                        $this->_cancelJobsSnapinsForHost();
                        break;
                }
            }
            // Refuse a Secure Boot enrollment the target cannot run.
            //
            // This is the payoff for schema step 376, and it is the same
            // shape of argument as the architecture check further down: the
            // task does not fail loudly on an enforcing machine, it fails by
            // the machine never booting FOS at all. The admin sees a host
            // that PXE booted, refused the kernel, and rebooted -- which
            // looks like a broken image or a broken network, not like a task
            // that was never eligible.
            //
            // ADR 0008 states this constraint in the task's own description,
            // which is the last thing an admin reads before scheduling. That
            // was the only enforcement there could be while nothing recorded
            // what a machine was; now something does, so it is checked.
            //
            // Advisory data, deliberately used for an advisory purpose. The
            // value comes from an unauthenticated boot request, so this is
            // NOT a security control and must never become one -- a host that
            // spoofed "disabled" earns itself a task that cannot work, which
            // is exactly what happens today with no record at all. See ADR
            // 0029. What it is NOT allowed to do is refuse on a guess:
            // isEnrollmentTarget() lets UNKNOWN through, so an upgraded server
            // whose fleet has not PXE booted yet behaves exactly as it does
            // now, and only a positively-reported bad state is refused.
            if (TaskType::ENROLL_SECUREBOOT == $TaskType->id) {
                $sbRefusal = SecureBootState::refusalReason(
                    $this->get('sbstate')
                );
                if ('' !== $sbRefusal) {
                    throw new \Exception($sbRefusal);
                }
            }
            $Image = $this->getImage();
            $imagingTypes = $TaskType->isImagingTask;
            $isCapture = $TaskType->isCapture;
            if ($imagingTypes) {
                if (!$Image->isValid()) {
                    throw new \Exception(self::$foglang['ImageNotValid']);
                }
                if (!$Image->get('isEnabled')) {
                    throw new \Exception(_('Image is not enabled'));
                }
                // Refuse a deploy the target cannot possibly boot.
                //
                // Capture is exempt: the image is being written from this
                // host, not run on it, so there is nothing to be incompatible
                // with -- and it is the capture that gives the image its
                // architecture in the first place.
                //
                // Worth catching here rather than letting it run because the
                // deploy SUCCEEDS. partclone writes the bytes, the task goes
                // Complete and every report is green; the disk just holds a
                // bootloader and binaries the machine cannot execute. The
                // failure surfaces at the next power-on looking like dead
                // hardware rather than like the wrong image, which is the
                // expensive way to find out.
                //
                // Architecture::canRun() allows anything it cannot disprove,
                // so this only fires when both architectures are recorded AND
                // incompatible. See schema steps 369/370/372.
                //
                // Both sides are read as NAMES through the relation rather
                // than compared as ids: two ids being different is not the
                // question -- i386 and x86_64 are different rows and are
                // compatible in one direction -- and an id says nothing a
                // human can read back in the refusal message.
                $imageArchName = $Image->get('arch')->get('name');
                $hostArchName = $this->get('arch')->get('name');
                if (!$isCapture
                    && !Architecture::canRun($imageArchName, $hostArchName)
                ) {
                    throw new \Exception(
                        sprintf(
                            '%s: %s %s, %s %s',
                            _('Image is not compatible with this host'),
                            _('image is'),
                            $imageArchName,
                            _('host is'),
                            $hostArchName
                        )
                    );
                }
                // Let plugins pick the group/node before falling back to the
                // image's primary group. Every other place that resolves a
                // node for a host -- TaskingElement and IpxeBootMenu -- already
                // fires this, but tasking never did, so the Location plugin
                // had no say in where a task was pointed. For multicast that
                // was decisive: the session is stamped below with the node's
                // group, so it always landed in the image's group no matter
                // where the host was, only that group's master ever ran a
                // udp-sender, and clients at every other site waited on a
                // stream that could not reach them (#815). With the hook here
                // each location's hosts get a session in their own group,
                // served by the node next to them.
                $Host = $this;
                $StorageGroup = $StorageNode = null;
                self::$HookManager->processEvent(
                    'HOST_NEW_SETTINGS',
                    [
                        'Host' => &$Host,
                        'StorageNode' => &$StorageNode,
                        'StorageGroup' => &$StorageGroup,
                        'TaskType' => &$TaskType
                    ]
                );
                // A node carries its own group, and it is the node that ends
                // up serving the task, so that pairing wins over any group
                // the hook set separately -- otherwise the session could be
                // stamped with one group while a node from another streams
                // it.
                if ($StorageNode instanceof StorageNode
                    && $StorageNode->isValid()
                ) {
                    $StorageGroup = $StorageNode->getStorageGroup();
                }
                $hookGroup = (
                    $StorageGroup instanceof StorageGroup
                    && $StorageGroup->isValid()
                );
                if (!$hookGroup) {
                    $StorageGroup = $Image->getStorageGroup();
                }
                if (!$StorageGroup->isValid()) {
                    throw new \Exception(self::$foglang['ImageGroupNotValid']);
                }
                // Only a hook-chosen group needs checking; the image's own
                // group holds the image by definition. Without this the task
                // is created happily and the miss surfaces much later as a
                // client sitting at gparted until it times out, so say it
                // here instead. Captures are exempt: they write the image to
                // the node rather than read it, and a first capture of a new
                // image has no association to find yet.
                if ($hookGroup && !$isCapture) {
                    $inGroup = Route::getIds(
                        'imageassociation',
                        [
                            'imageID' => $Image->get('id'),
                            'storagegroupID' => $StorageGroup->get('id')
                        ]
                    );
                    if (count($inGroup ?: []) < 1) {
                        throw new \Exception(
                            sprintf(
                                '%s: %s -> %s',
                                _('Image is not replicated to this storage group'),
                                $Image->get('name'),
                                $StorageGroup->get('name')
                            )
                        );
                    }
                }
                $getNode = 'getOptimalStorageNode';
                if ($isCapture) {
                    $getNode = 'getMasterStorageNode';
                }
                if (!($StorageNode instanceof StorageNode)
                    || !$StorageNode->isValid()
                ) {
                    $StorageNode = $StorageGroup->{$getNode}();
                }
                if (!$StorageNode->isValid()) {
                    $msg = sprintf(
                        '%s %s',
                        _('Could not find any'),
                        _('nodes containing this image')
                    );
                    throw new \Exception($msg);
                }
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = Route::getIds(
                    'host',
                    ['imageID' => $imageTaskImgID]
                );
                $realImageID = Route::getIds(
                    'host',
                    ['id' => $this->get('id')],
                    'imageID'
                );
                if (!in_array($this->get('id'), $hostsWithImgID)) {
                    $realImageID = array_shift($realImageID);
                    $this->set(
                        'imageID',
                        $realImageID
                    );
                    if (!$this->save()) {
                        $serverFault = true;
                        throw new \Exception(_('Could not update host'));
                    }
                }
                $this->set('imageID', $imageTaskImgID);
            }
            $username = ($username ? $username : self::$FOGUser->get('name'));
            if (!$Task->isValid()) {
                $Task = $this->_createTasking(
                    $taskName,
                    $TaskType->id,
                    $username,
                    // A non-imaging task -- inventory, wake-up, a snapin-only
                    // run -- is served by no storage group and no node, and
                    // "none" is NULL now that taskNFSGroupID and
                    // taskNFSMemberID are nullable and constrained (ADR
                    // 0031). The 0 that used to stand for it is not a
                    // nfsGroups row, so the INSERT is refused outright with
                    // 1452 and the task cannot be created at all.
                    $imagingTypes ? $StorageGroup->get('id') : null,
                    $imagingTypes ? $StorageNode->get('id') : null,
                    $imagingTypes,
                    $shutdown,
                    $passreset,
                    $debug,
                    $wol,
                    $bypassbitlocker
                );
                $Task->set('imageID', $this->get('imageID'));
                if (!$Task->save()) {
                    $serverFault = true;
                    throw new \Exception(self::$foglang['FailedTask']);
                }
                $this->set('task', $Task);
                // Best-effort: a wget-less U-Boot board can only find its
                // task over TFTP (see UbootTftpSync), and this is what
                // closes the gap between queuing and the board being
                // rebooted right after. Never allowed to fail the task
                // itself -- UbootTftpSync::materialize() swallows its own
                // errors, and Service/TaskScheduler.php's reconcile() picks
                // up anything this misses.
                UbootTftpSync::materialize($this);
            }
            if ($TaskType->isSnapinTask) {
                if ($deploySnapins === true) {
                    $deploySnapins = -1;
                }
                $mac = $this->get('mac');
                if ($deploySnapins) {
                    $this->_createSnapinTasking(
                        $deploySnapins,
                        $snapinAbortOnFailure,
                        $TaskType->isSnapinTasking,
                        $Task
                    );
                }
            }
            if ($TaskType->isMulticast) {
                $assoc = false;
                $MulticastSession = null;
                $showStates = self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                );
                // Both lookups are scoped to the group this host will be
                // served from. A session only ever has one sender, running on
                // its group's master, so a host that joins a session outside
                // its own group is joining a stream that will never reach it.
                // Unscoped, hosts at every site piled into whichever session
                // for the image existed first. Scoped, each site's hosts
                // converge on their own session -- same name, own group, own
                // port, own local sender.
                $mcGroupID = $StorageNode->get('storagegroupID');
                if ($sessionjoin) {
                    $MCSessions = Route::getList(
                        'multicastsession',
                        [
                            'name' => $taskName,
                            'stateID' => $showStates,
                            'storagegroupID' => $mcGroupID
                        ]
                    );
                    $assoc = true;
                } else {
                    $MCSessions = Route::getList(
                        'multicastsession',
                        [
                            'image' => $Image->get('id'),
                            'stateID' => $showStates,
                            'storagegroupID' => $mcGroupID
                        ]
                    );
                }
                $MultiSessJoin = array_values(
                    array_filter(
                        $MCSessions
                    )
                );
                if (count($MultiSessJoin ?: [])) {
                    $MulticastSession = array_shift($MultiSessJoin);
                    $MulticastSession = new MulticastSession($MulticastSession->id);
                    // Joining a session that is already transmitting hands
                    // this host a partial image while still counting it as
                    // part of the session.
                    if (!$MulticastSession->isJoinable()) {
                        if ($sessionjoin) {
                            throw new \Exception(
                                _('That session has already started')
                                . '. '
                                . _('It can no longer be joined')
                            );
                        }
                        // Not joining by name, so a fresh session is the
                        // right answer rather than a partial image.
                        $MulticastSession = null;
                    }
                }
                unset($MultiSessJoin);
                if ($MulticastSession instanceof MulticastSession
                    && $MulticastSession->isValid()
                ) {
                    $assoc = true;
                } else {
                    MulticastSession::assertCapacity();
                    $MulticastSession = (new MulticastSession())
                        ->set('name', $taskName)
                        ->set('port', MulticastSession::allocatePort())
                        ->set('logpath', $this->getImage()->get('path'))
                        ->set('image', $this->getImage()->get('id'))
                        ->set('interface', $StorageNode->get('interface'))
                        // A session that has not started names no state. NULL
                        // rather than 0 since schema step 386 -- taskStates
                        // has no row with tsID 0.
                        ->set('stateID', null)
                        ->set('starttime', self::niceDate()->format('Y-m-d H:i:s'))
                        ->set('percent', 0)
                        ->set('isDD', $this->getImage()->get('imageTypeID'))
                        ->set('storagegroupID', $StorageNode->get('storagegroupID'))
                        ->set('clients', -1)
                        // sessclients is what makes a session joinable by
                        // name and what udp-sender holds for; leaving it at
                        // 0 is why sessions created from a booting machine
                        // could never be joined by anyone else.
                        ->set('sessclients', max(0, (int)$sessExpected))
                        ->set(
                            'maxwait',
                            $sessMaxwait > 0
                            ? (int)$sessMaxwait
                            : self::getSetting('FOG_UDPCAST_MAXWAIT') * 60
                        )
                        ->set('shutdown', (int)$shutdown);
                    if (!$MulticastSession->save()) {
                        $serverFault = true;
                        throw new \Exception(_('Failed to create multicast task'));
                    }
                    $assoc = true;
                }
                if ($assoc) {
                    $stat = (new MulticastSessionAssociation())
                        ->set('msID', $MulticastSession->get('id'))
                        ->set('taskID', $Task->get('id'))
                        ->save();
                    if (!$stat) {
                        $serverFault = true;
                        throw new \Exception(_('Unable to create association'));
                    }
                }
            }
            if ($TaskType->id == 14) {
                $Task
                    ->set('stateID', self::getProgressState())
                    ->set('checkInTime', self::storageNow())
                    ->save();
            }
            if ($wol || $TaskType->id == 14) {
                $this->wakeOnLAN();
            }
            if ($TaskType->id == 14) {
                $Task
                    ->set('stateID', self::getCompleteState())
                    ->save();
            }
        } catch (\Exception $e) {
            $errcode = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $message = $e->getMessage();
            $title = _('Create Task Fail');
            if ($serverFault) {
                $errcode = HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            }
            self::error(sprintf(
                '%s: %s, %s: %s, %s: %s',
                _('Title'),
                $title,
                _('HTML Error Code'),
                $errcode,
                _('Message'),
                $message
            ));
            if (preg_match('#/service/ipxe/boot.php#', self::$scriptname)) {
                throw new \Exception($message);
            }
            http_response_code($errcode);
            echo json_encode(
                [
                    'error' => $message,
                    'title' => $title
                ]
            );
            exit;
        }
        return true;
    }
    /**
     * Wakes this host up
     *
     * @return object
     */
    public function wakeOnLAN()
    {
        self::wakeUp($this->getMyMacs());
        // Design 0011, and ADDITIONAL to the line above rather than a
        // replacement for it. wakeUp() reaches every link FOG owns a
        // machine on; this reaches the links it does not, by asking an
        // agent already awake there. Off by default and a no-op when the
        // host has no awake neighbor, which is why it needs no branch
        // here.
        WakeRelay::request($this, 'host wake');
        return $this;
    }
    /**
     * Lower-cases a mac entry, tolerating non-string input.
     *
     * Stringifies scalars and __toString-able objects (e.g. a MACAddress
     * object) and returns an empty string for anything else, so it is safe
     * to use as an array_map callback on PHP 8 where strtolower() would
     * fatal on a non-string argument.
     *
     * @param mixed $mac the mac entry to normalize
     *
     * @return string
     */
    public static function macToLower($mac)
    {
        if (is_scalar($mac)
            || (is_object($mac) && method_exists($mac, '__toString'))
        ) {
            return strtolower((string)$mac);
        }
        return '';
    }
    /**
     * Adds additional macs
     *
     * @param array $addArray the macs to add
     *
     * @return object
     */
    public function addMAC($addArray)
    {
        if (!is_array($addArray)) {
            $addArray = [$addArray];
        }
        $addArray = array_map([self::class, 'macToLower'], $addArray);
        $addArray = self::parseMacList($addArray);
        $insert_fields = ['hostID', 'mac'];
        $insert_values = [];
        foreach ((array)$addArray as &$mac) {
            $insert_values[] = [$this->get('id'), $mac];
            unset($mac);
        }
        if (count($insert_values) > 0) {
            (new MACAddressAssociationManager())
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }

        return $this;
    }
    /**
     * Removes additional macs
     *
     * @param array $removeArray the macs to remove
     *
     * @return object
     */
    public function removeMAC($removeArray)
    {
        Route::deletemass(
            'macaddressassociation',
            [
                'hostID' => $this->get('id'),
                'mac' => (array)$removeArray
            ]
        );
        return $this;
    }
    /**
     * Adds primary mac
     *
     * @param string $mac the mac to make as primary
     *
     * @return object
     */
    public function addPriMAC($mac)
    {
        $mac = self::parseMacList($mac);
        $count = count($mac ?: []);
        if ($count < 1) {
            throw new \Exception(_('No viable macs to use'));
        }
        if (is_array($mac) && $count > 0) {
            $mac = array_shift($mac);
        }
        $host = $mac->getHost();
        if ($host instanceof Host && $host->isValid()) {
            throw new \Exception(
                sprintf(
                    "%s: %s => %s",
                    _('MAC address is already in use by another host'),
                    $mac,
                    $host->get('name')
                )
            );
        }
        return $this->set('mac', $mac);
    }
    /**
     * Adds pending mac
     *
     * @param string|array[] $mac the mac to add
     *
     * @return obect
     */
    public function addPendMAC($mac)
    {
        if (!is_array($mac)) {
            $mac = [$mac];
        }
        $mac = array_map([self::class, 'macToLower'], $mac);
        $mac = self::parseMacList($mac);
        $insert_fields = ['hostID', 'mac', 'pending'];
        $insert_values = [];
        foreach ((array)$mac as &$m) {
            $insert_values[] = [$this->get('id'), $m, '1'];
            unset($m);
        }
        if (count($insert_values) > 0) {
            (new MACAddressAssociationManager())
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }

        return $this;
    }
    /**
     * Adds printers to the host
     *
     * @param array $addArray the printers to add
     *
     * @return object
     */
    public function addPrinter($addArray)
    {
        return $this->addRemItem(
            'printers',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes printers from the host
     *
     * @param array $removeArray the printers to remove
     *
     * @return object
     */
    public function removePrinter($removeArray)
    {
        return $this->addRemItem(
            'printers',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds snapins to the host
     *
     * @param array $addArray the snapins to add
     *
     * @throws Exception
     * @return object
     */
    public function addSnapin($addArray)
    {
        $limit = self::getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            $snapinCount = Route::getCount(
                'snapin',
                ['id' => $this->get('snapins')]
            );
            if ($snapinCount >= $limit || count($addArray) > $limit) {
                $limitstr = sprintf(
                    '%s%s %s',
                    _('snapin'),
                    $limit == 1 ? '' : 's',
                    _('per host')
                );
                throw new \Exception(
                    sprintf(
                        '%s %d %s',
                        _('You are only allowed to assign'),
                        $limit,
                        $limitstr
                    )
                );
            }
        }
        // Staged in-memory here; the snapinAssoc rows (and their run-order
        // sequence) are persisted in save() via assocSetter()/appendSnapinSequence().
        return $this->addRemItem(
            'snapins',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes snapins from the host
     *
     * @param array $removeArray the snapins to remove
     *
     * @return object
     */
    public function removeSnapin($removeArray)
    {
        return $this->addRemItem(
            'snapins',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds software to the host.
     *
     * Staged in-memory here; the softwareAssoc rows (and their run-order
     * sequence) are persisted in save() via
     * assocSetter()/appendSoftwareSequence().
     *
     * @param array $addArray the software ids to add
     *
     * @return object
     */
    public function addSoftware($addArray)
    {
        return $this->addRemItem(
            'softwares',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes software from the host.
     *
     * @param array $removeArray the software ids to remove
     *
     * @return object
     */
    public function removeSoftware($removeArray)
    {
        return $this->addRemItem(
            'softwares',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Assigns a run-order sequence to any snapin associations that do
     * not have one yet, placing newly added snapins after existing ones.
     *
     * Static and host-id-parameterized rather than an instance method so a
     * caller that already knows the host id does not have to construct (and
     * fully load) a Host just to sequence its rows. Group::addSnapin() is
     * that caller: it writes snapinAssoc rows for every member host with a
     * direct insertBatch, which cannot carry the sequence itself -- the
     * batch upserts on the (saHostID, saSnapinID) unique key, so including
     * sequence would overwrite the deliberate ordering of any snapin the
     * host already had.
     *
     * @param int $hostID the host whose unsequenced rows to number
     *
     * @return void
     */
    public static function appendSnapinSequence($hostID)
    {
        $hostID = (int)$hostID;
        if ($hostID < 1) {
            return;
        }
        $associations = Route::getList(
            'snapinassociation',
            ['hostID' => $hostID],
            'AND',
            'sequence'
        );
        $maxSequence = 0;
        $unsequenced = [];
        foreach ($associations as $association) {
            $sequence = (int)$association->sequence;
            if ($sequence > 0) {
                $maxSequence = max($maxSequence, $sequence);
            } else {
                $unsequenced[] = $association->snapinID;
            }
        }
        foreach ($unsequenced as $snapinID) {
            (new SnapinAssociationManager())
                ->update(
                    [
                        'hostID' => $hostID,
                        'snapinID' => $snapinID
                    ],
                    '',
                    ['sequence' => ++$maxSequence]
                );
        }
    }
    /**
     * Sets the run order of the host's snapins from an ordered list of
     * snapin ids (first id runs first).
     *
     * @param array $snapinIDs the ordered snapin ids
     *
     * @return object
     */
    public function setSnapinOrder($snapinIDs)
    {
        $sequence = 0;
        foreach ((array)$snapinIDs as $snapinID) {
            $snapinID = (int)$snapinID;
            if ($snapinID < 1) {
                continue;
            }
            ++$sequence;
            (new SnapinAssociationManager())
                ->update(
                    [
                        'hostID' => $this->get('id'),
                        'snapinID' => $snapinID
                    ],
                    '',
                    ['sequence' => $sequence]
                );
        }
        return $this;
    }
    /**
     * Assigns a run-order sequence to any software associations that do
     * not have one yet, placing newly added software after existing ones.
     *
     * Mirrors appendSnapinSequence() above over softwareAssoc/swaSequence.
     *
     * @param int $hostID the host whose unsequenced rows to number
     *
     * @return void
     */
    public static function appendSoftwareSequence($hostID)
    {
        $hostID = (int)$hostID;
        if ($hostID < 1) {
            return;
        }
        $associations = Route::getList(
            'softwareassociation',
            ['hostID' => $hostID],
            'AND',
            'sequence'
        );
        $maxSequence = 0;
        $unsequenced = [];
        foreach ($associations as $association) {
            $sequence = (int)$association->sequence;
            if ($sequence > 0) {
                $maxSequence = max($maxSequence, $sequence);
            } else {
                $unsequenced[] = $association->softwareID;
            }
        }
        foreach ($unsequenced as $softwareID) {
            (new SoftwareAssociationManager())
                ->update(
                    [
                        'hostID' => $hostID,
                        'softwareID' => $softwareID
                    ],
                    '',
                    ['sequence' => ++$maxSequence]
                );
        }
    }
    /**
     * Sets the run order of the host's software from an ordered list of
     * software ids (first id runs first).
     *
     * Mirrors setSnapinOrder() above.
     *
     * @param array $softwareIDs the ordered software ids
     *
     * @return object
     */
    public function setSoftwareOrder($softwareIDs)
    {
        $sequence = 0;
        foreach ((array)$softwareIDs as $softwareID) {
            $softwareID = (int)$softwareID;
            if ($softwareID < 1) {
                continue;
            }
            ++$sequence;
            (new SoftwareAssociationManager())
                ->update(
                    [
                        'hostID' => $this->get('id'),
                        'softwareID' => $softwareID
                    ],
                    '',
                    ['sequence' => $sequence]
                );
        }
        return $this;
    }
    /**
     * The module ids this host has STATED a state for.
     *
     * ADR 0038 gives a module three states on a host: a row with msState 1
     * (on), a row with msState 0 (off, and it beats every group grant), and
     * no row at all (unstated -- a group may grant it, and nothing else
     * turns it on). loadModules() answers the middle question only.
     *
     * @param int      $hostID The host.
     * @param int|null $state  1, 0, or null for either.
     *
     * @return array int module ids
     */
    public static function statedModuleIDs($hostID, $state = null)
    {
        $hostID = (int)$hostID;
        if ($hostID < 1) {
            return [];
        }
        $find = ['hostID' => $hostID];
        if (null !== $state) {
            $find['state'] = (int)$state;
        }

        return array_map(
            'intval',
            (array)Route::getIds('moduleassociation', $find, 'moduleID')
        );
    }
    /**
     * Writes a module's state on this host, or clears it.
     *
     * The tri-state half of ADR 0038's module rule. Deliberately NOT routed
     * through addModule()/removeModule(): those go through the `modules`
     * array and assocSetter, which has no vocabulary for a row that exists
     * and means OFF -- it can only insert a row or delete one. Writing
     * msState directly is the only way to express the third state, and
     * leaving `modules` untouched is what keeps save() from re-running that
     * diff over a value this method already settled.
     *
     * @param array    $moduleIDs The modules to write.
     * @param int|null $state     1 for on, 0 for off, null to clear the row
     *                            and leave the module unstated.
     *
     * @return object
     */
    public function setModuleState($moduleIDs, $state)
    {
        $hostID = (int)$this->get('id');
        $ids = [];
        foreach ((array)$moduleIDs as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($hostID < 1 || !count($ids)) {
            return $this;
        }
        if (null === $state) {
            Route::deletemass(
                'moduleassociation',
                ['hostID' => $hostID, 'moduleID' => array_values($ids)]
            );

            return $this;
        }
        $state = (int)$state ? 1 : 0;
        // One call covers both creating the row and flipping an existing
        // one, because save() emits
        //
        //   INSERT INTO `moduleStatusByHost` (...) VALUES (...)
        //   ON DUPLICATE KEY UPDATE ... `msState`=VALUES(`msState`)
        //
        // and UNIQUE (msHostID, msModuleID) is what makes that fire. So
        // turning ON to OFF is the same statement as stating it for the
        // first time, and no read is needed to tell the two apart.
        foreach ($ids as $id) {
            (new ModuleAssociation())
                ->set('hostID', $hostID)
                ->set('moduleID', $id)
                ->set('state', $state)
                ->save();
        }

        return $this;
    }
    /**
     * Adds modules to the host
     *
     * @param array $addArray the modules to add
     *
     * @return object
     */
    public function addModule($addArray)
    {
        return $this->addRemItem(
            'modules',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes modules from the host
     *
     * @param array $removeArray the modules to remove
     *
     * @return object
     */
    public function removeModule($removeArray)
    {
        return $this->addRemItem(
            'modules',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds powermanagement tasks to the host
     *
     * @param array $addArray the powermanagement tasks to add
     *
     * @return object
     */
    public function addPowerManagement($addArray)
    {
        return $this->addRemItem(
            'powermanagementtasks',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes powermanagement tasks from the host
     *
     * @param array $removeArray the powermanagement tasks to remove
     *
     * @return object
     */
    public function removePowerManagement($removeArray)
    {
        return $this->addRemItem(
            'powermanagementtasks',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Returns the macs
     *
     * @param bool $justme should only return this or all macs
     *
     * @return array
     */
    public function getMyMacs($justme = true)
    {
        $find = [];
        if ($justme) {
            $find = ['hostID' => $this->get('id')];
        }
        return Route::getIds('macaddressassociation', $find, 'mac');
    }
    /**
     * Sets the ignore status of a mac for either image or client ignore
     *
     * @param array $imageIgnore  to ignore for imaging
     * @param array $clientIgnore to ignore for client
     *
     * @return object
     */
    public function ignore($imageIgnore, $clientIgnore)
    {
        $MyMACs = $this->getMyMacs();
        $myMACs = $igMACs = $cgMACs = [];
        $macaddress = function ($mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if (!$mac->isValid()) {
                return;
            }
            return $mac->__toString();
        };
        $myMACs = array_map($macaddress, (array)$MyMACs);
        $igMACs = array_map($macaddress, (array)$imageIgnore);
        $cgMACs = array_map($macaddress, (array)$clientIgnore);
        $myMACs = array_filter($myMACs);
        $igMACs = array_filter($igMACs);
        $cgMACs = array_filter($cgMACs);
        $myMACs = array_unique($myMACs);
        $igMACs = array_unique($igMACs);
        $cgMACs = array_unique($cgMACs);
        (new MACAddressAssociationManager())
            ->update(
                [
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$igMACs
                    ),
                    'hostID' => $this->get('id')
                ],
                '',
                ['imageIgnore' => 0]
            );
        (new MACAddressAssociationManager())
            ->update(
                [
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$cgMACs
                    ),
                    'hostID'=>$this->get('id')
                ],
                '',
                ['clientIgnore' => 0]
            );
        if (count($igMACs) > 0) {
            (new MACAddressAssociationManager())
                ->update(
                    [
                        'mac' => $igMACs,
                        'hostID' => $this->get('id')
                    ],
                    '',
                    ['imageIgnore' => 1]
                );
        }
        if (count($cgMACs) > 0) {
            (new MACAddressAssociationManager())
                ->update(
                    [
                        'mac' => $cgMACs,
                        'hostID'=>$this->get('id')
                    ],
                    '',
                    ['clientIgnore' => 1]
                );
        }
    }
    /**
     * Adds host to the selected group
     * alias to addHost method
     *
     * @param array $addArray the groups to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addHost($addArray);
    }
    /**
     * Removes host from the selected group
     * alias to removeHost method
     *
     * @param array $removeArray the groups to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->removeHost($removeArray);
    }
    /**
     * Adds host to the selected group
     *
     * @param array $addArray the groups to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'groups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes host from the selected group
     *
     * @param array $removeArray the groups to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'groups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Tells if the mac is client ignored
     *
     * @param string $mac the mac to test
     *
     * @return string
     */
    public function clientMacCheck($mac = false)
    {
        if ($mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if ($mac->isClientIgnored()) {
                return ' checked';
            }
            return '';
        }
        return $this->get('mac')->isClientIgnored() ? ' checked' : '';
    }
    /**
     * Tells if the mac is image ignored
     *
     * @param string $mac the mac to test
     *
     * @return string
     */
    public function imageMacCheck($mac = false)
    {
        if ($mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if ($mac->isImageIgnored()) {
                return ' checked';
            }
            return '';
        }
        return $this->get('mac')->isImageIgnored() ? ' checked' : '';
    }
    /**
     * Sets the host settings for AD (mainly)
     *
     * @param mixed  $useAD      whether to perform joins
     * @param string $domain     the domain to associate
     * @param string $ou         the ou to bind to
     * @param string $user       the user to perform join with
     * @param string $pass       the pass to perform join with
     * @param bool   $override   should the host fields override whats passed
     * @param bool   $nosave     should we save automatically
     * @param string $productKey the product key for the host to activate
     *
     * @return object
     */
    public function setAD(
        $useAD = '',
        $domain = '',
        $ou = '',
        $user = '',
        $pass = '',
        $override = false,
        $nosave = false,
        $productKey = ''
    ) {
        $adpasspat = "/^\*{32}$/";
        $pass = (preg_match($adpasspat, $pass) ? $this->get('ADPass') : $pass);
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) {
                    $useAD = $this->get('useAD');
                }
                if (empty($domain)) {
                    $domain = trim($this->get('ADDomain'));
                }
                if (empty($ou)) {
                    $ou = trim($this->get('ADOU'));
                }
                if (empty($user)) {
                    $user = trim($this->get('ADUser'));
                }
                if (empty($pass)) {
                    $pass = trim($this->get('ADPass'));
                }
                if (empty($productKey)) {
                    $productKey = trim($this->get('productKey'));
                }
            }
        }
        if ($pass) {
            $pass = trim($pass);
        }
        return $this
            ->set('useAD', $useAD)
            ->set('ADDomain', trim($domain))
            ->set('ADOU', trim($ou))
            ->set('ADUser', trim($user))
            ->set('ADPass', $pass)
            ->set('productKey', trim($productKey));
    }
    /**
     * Returns the hosts image object
     *
     * @return Image
     */
    public function getImage()
    {
        return $this->get('imagename');
    }
    /**
     * Returns the hosts image name
     *
     * @return string
     */
    public function getImageName()
    {
        return $this
            ->get('imagename')
            ->get('name');
    }
    /**
     * Returns the hosts image os name
     *
     * @return string
     */
    public function getOS()
    {
        return $this->getImage()->getOS()->get('name');
    }
    /**
     * Returns the hosts architecture object
     *
     * Named accessor for the same reason getImage() and getOS() are ones:
     * Route::getter() calls it alongside getImageType()/getOS()/
     * getStorageGroup() on its neighboring lines. It was written there
     * before it existed here, which is the fatal that led to this. The value
     * comes from loadArch(), not from a join.
     *
     * @return Architecture
     */
    public function getArch()
    {
        return $this->get('arch');
    }
    /**
     * Returns the snapinjob
     *
     * @return SnapinJob
     */
    public function getActiveSnapinJob()
    {
        return $this->get('snapinjob');
    }
}
