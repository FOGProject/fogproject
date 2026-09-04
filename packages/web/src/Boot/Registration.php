<?php
/**
 * Performs host registration
 *
 * PHP version 7.4+
 *
 * @category Registration
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Base\SmbiosIdentity;
use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\Inventory;
use FOG\Items\TaskType;
use FOG\Items\User;
use FOG\Managers\HostManager;
use FOG\Router\Route;

/**
 * Performs host registration
 *
 * @category Registration
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Registration extends FOGBase
{
    /**
     * The MACs to register with.
     *
     * @var array
     */
    protected $MACs = [];
    /**
     * The host's primary mac.
     *
     * @var string
     */
    protected $PriMAC;
    /**
     * The simplified mac as a name
     *
     * @var string
     */
    protected $macsimple;
    /**
     * The host modules to associate to this host.
     *
     * @var array
     */
    protected $modulesToJoin;
    /**
     * The host description if needed.
     *
     * @var string
     */
    protected $description;
    /**
     * Initialize the registration class.
     *
     * @param bool $check to check if exists.
     *
     * @return void
     */
    public function __construct($check = false)
    {
        parent::__construct();
        if (!self::getSetting('FOG_REGISTRATION_ENABLED')) {
            return;
        }
        try {
            $this->MACs = self::getHostItem(
                false,
                true,
                true,
                true
            );
            $this->PriMAC = array_shift($this->MACs);
            if ($this->regExists($check)) {
                throw new \Exception();
            }
            $this->macsimple = strtolower(
                str_replace(
                    [':', '-'],
                    '',
                    $this->PriMAC
                )
            );
            $find = ['isDefault' => 1];
            $this->modulesToJoin = Route::getIds(
                'module',
                $find
            );
            $this->description = sprintf(
                '%s %s',
                _('Created by FOG Reg on'),
                self::formatTime('now', 'F j, Y, g:i a')
            );
            // The header goes in BEFORE the host is created, so the
            // create's own auditChange rows have something to attach to --
            // a registration is the one machine path where the whole
            // starting configuration of a new object is worth reading back.
            // identify() fills in the id and name once the INSERT returns.
            Audit::record([
                'type' => 'host.register',
                'authSource' => Audit::SOURCE_ANONYMOUS,
                'subjectType' => 'host',
                'renderable' => 1
            ]);
            if (isset($_POST['advanced'])) {
                $this->_fullReg();
            } else {
                $this->_quickRegAuto();
            }
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }
    /**
     * Checks if the host exists or not.
     *
     * @param bool $check whether to really check.
     *
     * @return bool
     */
    public function regExists($check = false)
    {
        // The firmware is asked first and acted on last (#198). Resolving
        // it before the MAC costs one indexed query and lets a disagreement
        // be logged whichever way the MAC goes; SmbiosIdentity::
        // registrationAction() says what the answer is worth.
        $mode = strtolower(
            trim((string)self::getSetting('FOG_HOST_IDENTIFY_SMBIOS'))
        );
        $smbios = $this->_smbiosFromRequest();
        $smbiosID = 0;
        if (in_array($mode, ['log', 'enforce'], true)
            && strlen(implode('', $smbios))
        ) {
            $smbiosID = (int)HostManager::resolveHostBySmbios($smbios);
        }
        try {
            (new HostManager())->getHostByMacAddresses($this->PriMAC);
            if (!self::$Host->isValid()) {
                (new HostManager())->getHostByMacAddresses($this->MACs);
            }
            if (self::$Host->isValid()) {
                $this->_smbiosOutcome(
                    $mode,
                    $smbios,
                    $smbiosID,
                    (int)self::$Host->get('id')
                );
                throw new \Exception(
                    sprintf(
                        _('This machine already registered as %s'),
                        self::$Host->get('name')
                    )
                );
            }
            if ($this->_smbiosOutcome($mode, $smbios, $smbiosID, 0) === 'attach') {
                throw new \Exception(
                    sprintf(
                        _('This machine already registered as %s'),
                        self::$Host->get('name')
                    )
                );
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return true;
        }
        if ($check === true) {
            throw new \Exception('#!ok');
        }
        return false;
    }
    /**
     * The firmware fields FOS sent with the registration (#198).
     *
     * FOS base64-encodes every registration field, so these are decoded
     * here rather than through stripAndDecode(), which HTML-escapes what
     * it decodes -- the same trap as a credential escaped before its hash
     * compare. An older FOS sends only the UUID, and a value that is not
     * base64 at all is kept as sent; SmbiosIdentity drops what is empty.
     *
     * @return array field => canonicalized value, every field present
     */
    private function _smbiosFromRequest()
    {
        $smbios = [];
        foreach (SmbiosIdentity::FIELDS as $field) {
            $raw = (string)(filter_input(INPUT_POST, $field) ?: '');
            $decoded = base64_decode(str_replace(' ', '+', $raw), true);
            $smbios[$field] = SmbiosIdentity::canonicalize(
                $decoded === false ? $raw : $decoded
            );
        }
        return $smbios;
    }
    /**
     * Log, and in enforce mode act on, the firmware's answer (#198).
     *
     * 'attach' is the only outcome with a side effect: the registering
     * machine's MACs are added to the host the firmware named, that host
     * becomes self::$Host, and an audit header records it under the same
     * host.register type a new host gets, because from the administrator's
     * side it is the same event -- a machine came in through registration
     * and left with a record. The MACs are known to belong to no host at
     * this point, or the MAC lookup would have found one.
     *
     * @param string $mode     the setting, lowercased
     * @param array  $smbios   the fields as sent
     * @param int    $smbiosID host the firmware resolved to, 0 for none
     * @param int    $macID    host the MACs resolved to, 0 for none
     *
     * @return string the SmbiosIdentity::registrationAction() taken
     */
    private function _smbiosOutcome($mode, array $smbios, $smbiosID, $macID)
    {
        $action = SmbiosIdentity::registrationAction($mode, $macID, $smbiosID);
        if ($action === 'none') {
            return $action;
        }
        $SmbiosHost = new Host($smbiosID);
        $fields = [];
        foreach (SmbiosIdentity::usable($smbios) as $k => $v) {
            $fields[] = "$k=$v";
        }
        $macs = array_merge([$this->PriMAC], (array)$this->MACs);
        error_log(
            sprintf(
                'FOG host identity (registration, %s): MAC %s resolved %s; '
                . 'SMBIOS resolved "%s" (id %d) on %s; %s',
                $mode,
                implode(',', $macs),
                $macID
                    ? sprintf('"%s" (id %d)', self::$Host->get('name'), $macID)
                    : 'no host',
                $SmbiosHost->get('name'),
                $smbiosID,
                implode(', ', $fields),
                $action === 'attach'
                    ? 'MACs attached to it, registration answered as existing'
                    : ($macID
                        ? 'MAC kept'
                        : 'registering as a new host (log mode)')
            )
        );
        if ($action === 'attach') {
            Audit::record([
                'type' => 'host.register',
                'authSource' => Audit::SOURCE_ANONYMOUS,
                'subjectType' => 'host',
                'subjectID' => $smbiosID,
                'subjectLabel' => (string)$SmbiosHost->get('name'),
                'renderable' => 1,
                'text' => sprintf(
                    'firmware identity: attached %s',
                    implode(',', $macs)
                )
            ]);
            $SmbiosHost->addMAC($macs);
            self::$Host = $SmbiosHost;
        }
        return $action;
    }
    /**
     * Perform the registration.
     *
     * @return void
     */
    private function _fullReg()
    {
        try {
            $stripped = self::stripAndDecode($_POST);
            $productKey = trim((string)filter_var($stripped['productKey'] ?? '', FILTER_UNSAFE_RAW));
            if ($productKey !== '' && !preg_match('/^[A-Za-z0-9\\-]{1,29}$/', $productKey)) {
                throw new \Exception(_('Invalid product key supplied'));
            }
            $host = filter_var($stripped['host'] ?? '');
            $hostnameSafe = (new Host())->isHostnameSafe($host);
            if (!$hostnameSafe) {
                throw new \Exception(
                    sprintf(
                        _('Unsafe hostname entered, please try again: %s'),
                        $host
                    )
                );
            }
            $hostnameExists = (new HostManager())->exists($host);
            if ($hostnameExists) {
                throw new \Exception(
                    _(
                        'Hostname already used, please try again'
                    )
                );
            }
            $imageid = filter_var($stripped['imageid'] ?? '');
            $imageid = (
                (new Image($imageid))->isValid() ?
                $imageid :
                0
            );
            $primaryuser = filter_var($stripped['primaryuser'] ?? '');
            $other1 = filter_var($stripped['other1'] ?? '');
            $other2 = filter_var($stripped['other2'] ?? '');
            $doimage = filter_var($stripped['doimage'] ?? '') == '1';
            if ($_POST['doad']) {
                $serviceNames = [
                    'FOG_AD_DEFAULT_DOMAINNAME',
                    'FOG_AD_DEFAULT_OU',
                    'FOG_AD_DEFAULT_PASSWORD',
                    'FOG_AD_DEFAULT_USER',
                    'FOG_ENFORCE_HOST_CHANGES'
                ];
                list(
                    $ADDomain,
                    $OUs,
                    $ADPass,
                    $ADUser,
                    $enforce
                ) = self::getSetting($serviceNames);
                $OUs = explode(
                    '|',
                    $OUs
                );
                foreach ((array)$OUs as &$OU) {
                    $OUOptions[] = $OU;
                    unset($OU);
                }
                $OUOptions = array_unique((array)$OUOptions);
                $OUOptions = array_values((array)$OUOptions);
                $opt = false;
                if (count($OUOptions) > 1) {
                    $OUs = $OUOptions;
                    foreach ($OUs as &$OU) {
                        $opt = preg_replace('#;#', '', $OU);
                        if ($opt) {
                            break;
                        }
                        unset($OU);
                    }
                }
                if (!$opt) {
                    $opt = preg_replace('#;#', '', $OUs[0]);
                }
                $useAD = 1;
                $ADOU = $opt;
            }
            $gID = filter_var($stripped['groupid'] ?? '');
            $groupsToJoin = explode(',', $gID);
            $sID = filter_var($stripped['snapinid'] ?? '');
            $snapinsToJoin = explode(',', $sID);
            self::$Host = (new Host())
                ->set('name', $host)
                ->set('description', $this->description)
                ->set('imageID', $imageid)
                ->set('enforce', $enforce)
                ->set('modules', $this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addSnapin($snapinsToJoin)
                ->addPriMAC($this->PriMAC)
                ->setAD(
                    $useAD,
                    $ADDomain,
                    $ADOU,
                    $ADUser,
                    $ADPass,
                    false,
                    true,
                    $productKey
                );
            if (!self::$Host->save()) {
                // The header is already written, so say it did not take.
                // "A registration was attempted and failed" is invisible
                // today, and it is the shape of both a misconfiguration and
                // a probe.
                Audit::markOutcome(Audit::FAILED, 'host save failed');
                throw new \Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            // Only after the save: addMAC() writes the hostMAC rows at once
            // with the id the object holds, and before save() a new host
            // holds none, so the secondaries went in with an empty hostID
            // (the insertBatch guard now rejects that outright). The
            // primary is unaffected -- addPriMAC() stages it and save()
            // writes it once the id exists.
            self::$Host->addMAC($this->MACs);
            Audit::identify(
                'host',
                (int)self::$Host->get('id'),
                (string)self::$Host->get('name')
            );
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            try {
                if (!$doimage) {
                    throw new \Exception(
                        _('Done, without imaging!')
                    );
                }
                self::_deployHost();
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
            (new Inventory())
                ->set('hostID', self::$Host->get('id'))
                ->set('primaryUser', $primaryuser)
                ->set('other1', $other1)
                ->set('other2', $other2)
                ->save();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
    /**
     * Commonize method to deploy tasks for either
     * quickreg or full reg.
     *
     * @param $quickReg bool Is this from quick registration
     *
     * @throws Exception
     * @return void
     */
    private static function _deployHost($quickReg = false)
    {
        /*
         * decodeCredential(), NOT stripAndDecode(). stripAndDecodeItem() ends
         * in \Initiator::e() -- HTML escaping -- so a password containing
         * & < > " or ' reached passwordValidate() as its entity form and could
         * never match, which is why an account could sign in from the web UI,
         * answer '#!ok' at service/checkcredentials.php, and still be told
         * "Invalid Login" here. This is the same decoder that endpoint uses,
         * so the two cannot answer differently again. Forums topic 18228.
         */
        $readCred = function ($name) {
            return filter_input(INPUT_POST, $name)
                ?? filter_input(INPUT_GET, $name)
                ?? '';
        };
        $username = self::decodeCredential($readCred('username'));
        $password = self::decodeCredential($readCred('password'));
        $username = (false === $username) ? '' : $username;
        $password = (false === $password) ? '' : $password;
        $userTest = (new User())->passwordValidate($username, $password);
        if (!$userTest && !$quickReg) {
            throw new \Exception(
                _('Done, without imaging: Invalid Login.')
            );
        }
        if (!self::$Host->get('token')) {
            self::$Host->getManager()->update(
                ['id' => self::$Host->get('id')],
                '',
                ['token' => self::createSecToken()]
            );
        }
        $username = ($username ?: 'fog');
        $Image = self::$Host->getImage();
        if (!$Image->isValid()) {
            throw new \Exception(
                _('Done, without imaging! No image assigned.')
            );
        }
        if (!$Image->get('isEnabled')) {
            throw new \Exception(
                _('Done, without imaging! Image is not enabled.')
            );
        }
        if (!$Image->getStorageGroup()->isValid()) {
            throw new \Exception(
                _('Done, without imaging! Image not in storage group.')
            );
        }
        // getItem(), not indiv(): a missing task type answers null rather
        // than ending the registration response with a 404 the client cannot
        // see. Refs #907, ADR 0011.
        $tasktype = Route::getItem('tasktype', TaskType::DEPLOY);
        if (!$tasktype) {
            throw new \Exception(
                sprintf(
                    _('Task type %d is missing from this server.'),
                    TaskType::DEPLOY
                )
            );
        }
        $task = self::$Host->createImagePackage(
            $tasktype,
            'AutoRegTask',
            false,
            false,
            true,
            false,
            $username
        );
        if (!$task) {
            throw new \Exception(
                _('Done, without imaging! Failed to create tasking.')
            );
        }
        throw new \Exception(_('Done, with imaging'));
    }
    /**
     * Quick registration handler.
     *
     * @return void
     */
    private function _quickRegAuto()
    {
        if (!self::getSetting('FOG_QUICKREG_AUTOPOP')) {
            $this->_quickReg();
        }
        try {
            $stripped = self::stripAndDecode($_POST);
            $serviceNames = [
                'FOG_QUICKREG_GROUP_ASSOC',
                'FOG_QUICKREG_IMG_ID',
                'FOG_QUICKREG_IMG_WHEN_REG',
                'FOG_QUICKREG_PROD_KEY_BIOS',
                'FOG_QUICKREG_SYS_NAME',
                'FOG_QUICKREG_SYS_NUMBER'
            ];
            list(
                $groupsToJoin,
                $imageid,
                $performimg,
                $prodkeyget,
                $autoRegSysName,
                $autoRegSysNumber
            ) = self::getSetting($serviceNames);
            $autoRegSysName = trim($autoRegSysName);
            if (strtoupper($autoRegSysName) == 'MAC') {
                $hostname = $this->macsimple;
            } else {
                $hostname = $autoRegSysName;
                $sysserial = filter_var($stripped['sysserial'] ?? '');
                $sysserial = strtoupper($sysserial);
                $hostname = str_replace('{SYSSERIAL}', $sysserial, $hostname);
            }
            $hostname = trim($hostname);
            if (!(new Host())->isHostnameSafe($hostname)) {
                $hostname = $this->macsimple;
            }
            $paddingLen = substr_count(
                $autoRegSysName,
                '*'
            );
            $paddingString = null;
            if ($paddingLen > 0) {
                $paddingString = str_repeat(
                    '*',
                    $paddingLen
                );
                $paddedInsert = str_pad(
                    $autoRegSysNumber,
                    $paddingLen,
                    0,
                    STR_PAD_LEFT
                );
                if (strtoupper($autoRegSysName) == 'MAC') {
                    $hostname = $this->macsimple;
                } else {
                    $hostname = str_replace(
                        $paddingString,
                        $paddedInsert,
                        $autoRegSysName
                    );
                    while ((new HostManager())->exists($hostname)) {
                        $paddingString = str_repeat(
                            '*',
                            $paddingLen
                        );
                        $paddedInsert = str_pad(
                            ++$autoRegSysNumber,
                            $paddingLen,
                            0,
                            STR_PAD_LEFT
                        );
                        // Was $autuRegSysName -- a typo, so this passed null
                        // and the loop produced an empty hostname on the first
                        // collision. isHostnameSafe() then rejected it and
                        // quick registration fell back to the MAC-derived name
                        // instead of incrementing to the next free number.
                        $hostname = str_replace(
                            $paddingString,
                            $paddedInsert,
                            $autoRegSysName
                        );
                    }
                    self::setSetting('FOG_QUICKREG_SYS_NUMBER', ++$autoRegSysNumber);
                }
            }
            if (!(new Host())->isHostnameSafe($hostname)) {
                $hostname = $this->macsimple;
            }
            self::$Host = (new Host())
                ->set('name', $hostname)
                ->set('description', $this->description)
                ->set('imageID', $imageid)
                ->set('modules', $this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addPriMAC($this->PriMAC);
            if ($prodkeyget > 0) {
                $productKey = trim((string)filter_var($stripped['productKey'] ?? '', FILTER_UNSAFE_RAW));
                if ($productKey !== '' && !preg_match('/^[A-Za-z0-9\\-]{1,29}$/', $productKey)) {
                    throw new \Exception(_('Invalid product key supplied'));
                }
                self::$Host->set('productKey', $productKey);
            }
            if (!self::$Host->save()) {
                // The header is already written, so say it did not take.
                // "A registration was attempted and failed" is invisible
                // today, and it is the shape of both a misconfiguration and
                // a probe.
                Audit::markOutcome(Audit::FAILED, 'host save failed');
                throw new \Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            Audit::identify(
                'host',
                (int)self::$Host->get('id'),
                (string)self::$Host->get('name')
            );
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            try {
                if (!$performimg) {
                    throw new \Exception(
                        _('Done, without imaging!')
                    );
                }
                self::_deployHost(true);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
    /**
     * The quick registration, non-auto
     *
     * @return void
     */
    private function _quickReg()
    {
        try {
            $stripped = self::stripAndDecode($_POST);
            $prodkeyget = self::getSetting('FOG_QUICKREG_PROD_KEY_BIOS');
            self::$Host = (new Host())
                ->set('name', $this->macsimple)
                ->set('description', $this->description)
                ->set('modules', $this->modulesToJoin)
                ->addPriMAC($this->PriMAC);
            if ($prodkeyget > 0) {
                $productKey = trim((string)filter_var($stripped['productKey'] ?? '', FILTER_UNSAFE_RAW));
                if ($productKey !== '' && !preg_match('/^[A-Za-z0-9\\-]{1,29}$/', $productKey)) {
                    throw new \Exception(_('Invalid product key supplied'));
                }
                self::$Host->set('productKey', $productKey);
            }
            if (!self::$Host->save()) {
                // The header is already written, so say it did not take.
                // "A registration was attempted and failed" is invisible
                // today, and it is the shape of both a misconfiguration and
                // a probe.
                Audit::markOutcome(Audit::FAILED, 'host save failed');
                throw new \Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            // Only after the save: addMAC() writes the hostMAC rows at once
            // with the id the object holds, and before save() a new host
            // holds none, so the secondaries went in with an empty hostID
            // (the insertBatch guard now rejects that outright). The
            // primary is unaffected -- addPriMAC() stages it and save()
            // writes it once the id exists.
            self::$Host->addMAC($this->MACs);
            Audit::identify(
                'host',
                (int)self::$Host->get('id'),
                (string)self::$Host->get('name')
            );
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            throw new \Exception(
                _('Done, without imaging!')
            );
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
