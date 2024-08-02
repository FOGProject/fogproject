<?php
/**
 * Performs host registration
 *
 * PHP version 5
 *
 * @category Registration
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
                throw new Exception();
            }
            $this->macsimple = strtolower(
                str_replace(
                    [':', '-'],
                    '',
                    $this->PriMAC
                )
            );
            $find = ['isDefault' => 1];
            Route::ids(
                'module',
                $find
            );
            $this->modulesToJoin = json_decode(
                Route::getData(),
                true
            );
            $this->description = sprintf(
                '%s %s',
                _('Created by FOG Reg on'),
                self::formatTime('now', 'F j, Y, g:i a')
            );
            if (isset($_POST['advanced'])) {
                $this->_fullReg();
            } else {
                $this->_quickRegAuto();
            }
        } catch (Exception $e) {
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
        try {
            self::getClass('HostManager')->getHostByMacAddresses($this->PriMAC);
            if (self::$Host->isValid()) {
                throw new Exception(
                    _(
                        'This machine already registered as '
                        . self::$Host->get('name')
                    )
                );
            }
            self::getClass('HostManager')->getHostByMacAddresses($this->MACs);
            if (self::$Host->isValid()) {
                throw new Exception(
                    _(
                        'This machine already registered as '
                        . self::$Host->get('name')
                    )
                );
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return true;
        }
        if ($check === true) {
            throw new Exception('#!ok');
        }
        return false;
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
            $productKey = filter_var($stripped['productKey'] ?? '');
            $host = filter_var($stripped['host'] ?? '');
            $hostnameSafe = self::getClass('Host')->isHostnameSafe($host);
            if (!$hostnameSafe) {
                throw new Exception(
                    _(
                        'Unsafe hostname entered, please try again: '
                        . $host
                    )
                );
            }
            $hostnameExists = self::getClass('HostManager')->exists($host);
            if ($hostnameExists) {
                throw new Exception(
                    _(
                        'Hostname already used, please try again'
                    )
                );
            }
            $imageid = filter_var($stripped['imageid'] ?? '');
            $imageid = (
                self::getClass('Image', $imageid)->isValid() ?
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
            self::$Host = self::getClass('Host')
                ->set('name', $host)
                ->set('description', $this->description)
                ->set('imageID', $imageid)
                ->set('enforce', $enforce)
                ->set('modules', $this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addSnapin($snapinsToJoin)
                ->addPriMAC($this->PriMAC)
                ->addMAC($this->MACs)
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
                throw new Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            try {
                if (!$doimage) {
                    throw new Exception(
                        _('Done, without imaging!')
                    );
                }
                self::_deployHost();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            self::getClass('Inventory')
                ->set('hostID', self::$Host->get('id'))
                ->set('primaryUser', $primaryuser)
                ->set('other1', $other1)
                ->set('other2', $other2)
                ->save();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    /**
     * Commonize method to deploy tasks for either
     * quickreg or full reg.
     *
     * @throws Exception
     * @return void
     */
    private static function _deployHost()
    {
        $stripped = self::stripAndDecode($_POST);
        $username = filter_var($stripped['username'] ?? '');
        $password = filter_var($stripped['password'] ?? '');
        $userTest = self::getClass('User')->passwordValidate($username, $password);
        if (!$userTest) {
            throw new Exception(
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
            throw new Exception(
                _('Done, without imaging! No image assigned.')
            );
        }
        if (!$Image->get('isEnabled')) {
            throw new Exception(
                _('Done, without imaging! Image is not enabled.')
            );
        }
        if (!$Image->getStorageGroup()->isValid()) {
            throw new Exception(
                _('Done, without imaging! Image not in storage group.')
            );
        }
        Route::indiv('tasktype', TaskType::DEPLOY);
        $tasktype = json_decode(Route::getData());
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
            throw new Exception(
                _('Done, without imaging! Failed to create tasking.')
            );
        }
        throw new Exception(_('Done, with imaging'));
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
            if (!self::getClass('Host')->isHostnameSafe($hostname)) {
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
                    while (self::getClass('HostManager')->exists($hostname)) {
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
                        $hostname = str_replace(
                            $paddingString,
                            $paddedInsert,
                            $autuRegSysName
                        );
                    }
                    self::setSetting('FOG_QUICKREG_SYS_NUMBER', ++$autoRegSysNumber);
                }
            }
            if (!self::getClass('Host')->isHostnameSafe($hostname)) {
                $hostname = $this->macsimple;
            }
            self::$Host = self::getClass('Host')
                ->set('name', $hostname)
                ->set('description', $this->description)
                ->set('imageID', $imageid)
                ->set('modules', $this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addPriMAC($this->PriMAC);
            if ($prodkeyget > 0) {
                $productKey = filter_var($stripped['productKey'] ?? '');
                self::$Host->set('productKey', $productKey);
            }
            if (!self::$Host->save()) {
                throw new Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            try {
                if (!$performimg) {
                    throw new Exception(
                        _('Done, without imaging!')
                    );
                }
                self::_deployHost();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } catch (Exception $e) {
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
            self::$Host = self::getClass('Host')
                ->set('name', $this->macsimple)
                ->set('description', $this->description)
                ->set('modules', $this->modulesToJoin)
                ->addPriMAC($this->PriMAC)
                ->addMAC($this->MACs);
            if ($prodkeyget > 0) {
                $productKey = filter_var($stripped['productKey'] ?? '');
                self::$Host->set('productKey', $productKey);
            }
            if (!self::$Host->save()) {
                throw new Exception(
                    _('Failed to create Host!')
                );
            }
            self::$Host->load();
            self::$HookManager->processEvent(
                'HOST_REGISTER',
                ['Host' => &self::$Host]
            );
            throw new Exception(
                _('Done, without imaging!')
            );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
