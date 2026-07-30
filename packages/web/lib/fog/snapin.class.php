<?php
/**
 * The snapin object.
 *
 * PHP version 5
 *
 * @category Snapin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin object.
 *
 * @category Snapin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Snapin extends FOGController
{
    /**
     * The snapin table.
     *
     * @var string
     */
    protected $databaseTable = 'snapins';
    /**
     * The snapin table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc',
        'file' => 'sFilePath',
        'args' => 'sArgs',
        'createdTime' => 'sCreateDate',
        'createdBy' => 'sCreator',
        'reboot' => 'sReboot',
        'shutdown' => 'sShutdown',
        'runWith' => 'sRunWith',
        'runWithArgs' => 'sRunWithArgs',
        'protected' => 'snapinProtect',
        'isEnabled' => 'sEnabled',
        'toReplicate' => 'sReplicate',
        'hide' => 'sHideLog',
        'timeout' => 'sTimeout',
        'packtype' => 'sPackType',
        'hash' => 'sHash',
        'size' => 'sSize',
        'anon3' => 'sAnon3',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'file',
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storagegroups',
        'storagegroupsnotinme',
        'path',
    );
    /**
     * Removes the item from the database.
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        $find = array('snapinID' => $this->get('id'));
        $snapinJobIDs = self::getSubObjectIDs(
            'SnapinTask',
            $find,
            'jobID'
        );
        self::getClass('SnapinTaskManager')
            ->destroy($find);
        $snapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            array(
                'id' => $snapinJobIDs,
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                ),
            )
        );
        $sjIDs = array();
        foreach ((array) $snapinJobIDs as &$sjID) {
            $jobCount = self::getClass('SnapinTaskManager')
                ->count(
                    array(
                        'jobID' => $sjID,
                    )
                );
            if ($jobCount > 0) {
                continue;
            }
            $sjIDs[] = $sjID;
        }
        if (count($sjIDs) > 0) {
            self::getClass('SnapinJobManager')
                ->cancel($sjID);
        }
        self::getClass('SnapinGroupAssociationManager')
            ->destroy($find);
        self::getClass('SnapinAssociationManager')
            ->destroy($find);

        return parent::destroy($key);
    }
    /**
     * Stores data into the database.
     *
     * @return bool|object
     */
    public function save()
    {
        parent::save();

        $primary = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            array(
                'snapinID' => $this->get('id'),
                'primary' => 1
            ),
            'storagegroupID'
        );
        $this
            ->assocSetter('Snapin', 'host')
            ->assocSetter('SnapinGroup', 'storagegroup');
        if (count($primary) > 0) {
            $primary = array_shift($primary);
            $this->setPrimaryGroup($primary);
        }
        return $this->load();
    }
    /**
     * Deletes the snapin file.
     *
     * @return bool
     */
    public function deleteFile()
    {
        if ($this->get('protected')) {
            throw new Exception(self::$foglang['ProtectedSnapin']);
        }
        foreach ((array)self::getClass('StorageNodeManager')
            ->find(
                array(
                    'storagegroupID' => $this->get('storagegroups'),
                    'isEnabled' => 1
                )
            ) as &$StorageNode
        ) {
            $ftppath = $StorageNode->get('snapinpath');
            $ftppath = trim($ftppath, '/');
            $deleteFile = sprintf(
                '/%s/%s',
                $ftppath,
                $this->get('file')
            );
            $ip = $StorageNode->get('ip');
            $user = $StorageNode->get('user');
            $pass = $StorageNode->get('pass');
            self::$FOGFTP
                ->set('host', $ip)
                ->set('username', $user)
                ->set('password', $pass);
            if (!self::$FOGFTP->connect()) {
                continue;
            }
            self::$FOGFTP
                ->delete($deleteFile)
                ->close();
            unset($StorageNode);
        }
    }
    /**
     * Loads hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $hostids = self::getSubObjectIDs(
            'SnapinAssociation',
            array('snapinID' => $this->get('id')),
            'hostID'
        );
        $hostids = self::getSubObjectIDs(
            'Host',
            array('id' => $hostids)
        );
        $this->set('hosts', (array)$hostids);
    }
    /**
     * Add hosts to snapin object.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'hosts',
            (array) $addArray,
            'merge'
        );
    }
    /**
     * Remove hosts from snapin object.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'hosts',
            (array) $removeArray,
            'diff'
        );
    }
    /**
     * Loads items not with this object.
     *
     * @return void
     */
    protected function loadHostsnotinme()
    {
        $hosts = array_diff(
            self::getSubObjectIDs('Host'),
            $this->get('hosts')
        );
        $this->set('hostsnotinme', (array)$hosts);
    }
    /**
     * Loads storage groups with this object.
     *
     * @return void
     */
    protected function loadStoragegroups()
    {
        $groupids = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            array('snapinID' => $this->get('id')),
            'storagegroupID'
        );
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            array('id' => $groupids)
        );
        $groupids = array_filter($groupids);
        if (count($groupids) < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = self::minId($groupids);
        }
        $this->set('storagegroups', (array)$groupids);
    }
    /**
     * Adds groups to this object.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array) $addArray,
            'merge'
        );
    }
    /**
     * Removes groups from this object.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array) $removeArray,
            'diff'
        );
    }
    /**
     * Loads groups not with this snapin.
     *
     * @return void
     */
    protected function loadStoragegroupsnotinme()
    {
        $storagegroups = array_diff(
            self::getSubObjectIDs('StorageGroup'),
            $this->get('storagegroups')
        );
        $this->set('storagegroupsnotinme', (array)$storagegroups);
    }
    /**
     * Gets the storage group.
     *
     * @throws Exception
     * @return object
     */
    public function getStorageGroup()
    {
        $groupids = $this->get('storagegroups');
        $count = count($groupids);
        if ($count < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = self::minId($groupids);
            if ($groupids < 1) {
                throw new Exception(_('No viable storage groups found'));
            }
        }
        $primaryGroup = array();
        foreach ((array) $groupids as &$groupid) {
            if (!$this->getPrimaryGroup($groupid)) {
                continue;
            }
            $primaryGroup[] = $groupid;
            unset($groupid);
        }
        if (count($primaryGroup) < 1) {
            $primaryGroup = self::minId((array) $groupids);
        } else {
            $primaryGroup = array_shift($primaryGroup);
        }

        return new StorageGroup($primaryGroup);
    }
    /**
     * Gets the snapin's primary group.
     *
     * @param int $groupID the group id to check
     *
     * @return bool
     */
    public function getPrimaryGroup($groupID)
    {
        $primaryCount = self::getClass('SnapinGroupAssociationManager')
            ->count(
                array(
                    'snapinID' => $this->get('id'),
                    'primary' => 1,
                )
            );
        if ($primaryCount < 1) {
            $primaryCount = self::getClass('SnapinGroupAssociationManager')
                ->count(
                    array('snapinID' => $this->get('id'))
                );
        }
        if ($primaryCount < 1) {
            $groupid = self::getSubObjectIDs('StorageGroup');
            $groupid = self::minId($groupid);
            $this->setPrimaryGroup($groupid);
        }
        $assocID = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            array(
                'storagegroupID' => $groupID,
                'snapinID' => $this->get('id'),
            )
        );
        $assocID = self::minId((array) $assocID);

        return self::getClass('SnapinGroupAssociation', $assocID)->isPrimary();
    }
    /**
     * Sets the primary group for the snapin.
     *
     * @param int $groupID the id to set as primary
     *
     * @return array
     */
    public function setPrimaryGroup($groupID)
    {
        $exists = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            array(
                'snapinID' => $this->get('id'),
                'storagegroupID' => $groupID
            ),
            'storagegroupID'
        );
        if (count($exists) < 1) {
            self::getClass('SnapinGroupAssociation')
                ->set('snapinID', $this->get('id'))
                ->set('storagegroupID', $groupID)
                ->save();
        }
        /**
         * Unset all current groups to non-primary
         */
        self::getClass('SnapinGroupAssociationManager')
            ->update(
                array(
                    'snapinID' => $this->get('id'),
                    'storagegroupID' => $this->get('storagegroups')
                ),
                '',
                array('primary' => 0)
            );
        /**
         * Set the passed group as primary
         */
        self::getClass('SnapinGroupAssociationManager')
            ->update(
                array(
                    'snapinID' => $this->get('id'),
                    'storagegroupID' => $groupID,
                ),
                '',
                array('primary' => 1)
            );
    }
    /**
     * Loads the Path as the file for commonality
     * in some methods.
     *
     * @return void
     */
    protected function loadPath()
    {
        $this->set('path', $this->get('file'));

        return $this;
    }
    /**
     * Validate input, upload the snapin file to the Master Storage Node
     * of the chosen group, then save the Snapin row. Shared by the UI
     * (snapinmanagementpage::addPost) and the REST endpoint
     * (POST /fog/snapin/createwithfile).
     *
     * Throws:
     *  - InvalidArgumentException : bad input  -> HTTP 400
     *  - RuntimeException         : FTP failure -> HTTP 500 in API,
     *                                              HTTP 400 in UI (legacy)
     *  - SnapinSaveException      : DB save failed AFTER file landed on disk
     *
     * @param array $post  $_POST equivalents (uses API field names)
     * @param array $files $_FILES equivalents (expects 'snapinfile')
     *
     * @return Snapin the freshly-created, reloaded Snapin
     */
    public static function uploadAndCreate(array $post, array $files)
    {
        $name = isset($post['snapin']) ? $post['snapin'] : '';
        $desc = isset($post['description']) ? $post['description'] : '';
        $packtype = isset($post['packtype']) ? (int)$post['packtype'] : 0;
        $runWith = isset($post['rw']) ? $post['rw'] : '';
        $runWithArgs = isset($post['rwa']) ? $post['rwa'] : '';
        $storagegroup = isset($post['storagegroup']) ? (int)$post['storagegroup'] : 0;
        $existing = isset($post['snapinfileexist']) ? basename($post['snapinfileexist']) : '';
        $uploadname = isset($files['snapinfile']['name']) ? basename($files['snapinfile']['name']) : '';
        $snapinfile = $uploadname ?: $existing;
        $isEnabled = (int)isset($post['isEnabled']);
        $toReplicate = (int)isset($post['toReplicate']);
        $hide = (int)isset($post['isHidden']);
        $timeout = isset($post['timeout']) ? (int)$post['timeout'] : 0;
        $action = isset($post['action']) ? $post['action'] : '';
        $args = isset($post['args']) ? $post['args'] : '';
        if (!$name) {
            throw new \InvalidArgumentException(_('A snapin name is required!'));
        }
        if (self::getClass('SnapinManager')->exists($name)) {
            throw new \InvalidArgumentException(
                _('A snapin already exists with this name!')
            );
        }
        if (!$snapinfile) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s, %s, %s!',
                    _('A file'),
                    _('either already selected or uploaded'),
                    _('must be specified')
                )
            );
        }
        $snapinfile = self::sanitizeSnapinFileName($snapinfile);
        if (!$storagegroup) {
            throw new \InvalidArgumentException(
                _('A storage group is required!')
            );
        }
        $StorageGroup = new StorageGroup($storagegroup);
        if (!$StorageGroup->isValid()) {
            throw new \InvalidArgumentException(
                _('Storage Group not found')
            );
        }
        $StorageNode = $StorageGroup->getMasterStorageNode();
        if (!$StorageNode || !$StorageNode->isValid()) {
            throw new \RuntimeException(
                _('Storage Group has no reachable Master Node')
            );
        }
        $hash = '';
        $size = 0;
        if ($uploadname) {
            if (empty($files['snapinfile']['tmp_name'])
                || !is_uploaded_file($files['snapinfile']['tmp_name'])
            ) {
                $err = isset($files['snapinfile']['error'])
                    ? (int)$files['snapinfile']['error']
                    : UPLOAD_ERR_NO_FILE;
                throw new \InvalidArgumentException(
                    sprintf(_('Upload failed (error code %d)'), $err)
                );
            }
            $src = $files['snapinfile']['tmp_name'];
            $hash = hash_file('sha512', $src);
            $size = self::getFilesize($src);
            $dest = sprintf(
                '/%s/%s',
                trim($StorageNode->get('snapinpath'), '/'),
                $snapinfile
            );
            set_time_limit(0);
            self::$FOGFTP
                ->set('host', $StorageNode->get('ip'))
                ->set('username', $StorageNode->get('user'))
                ->set('password', $StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) {
                throw new \RuntimeException(
                    sprintf(
                        '%s: %s: %s.',
                        _('Storage Node'),
                        $StorageNode->get('ip'),
                        _('FTP Connection has failed')
                    )
                );
            }
            try {
                if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                    if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) {
                        throw new \RuntimeException(_('Failed to add snapin'));
                    }
                }
                self::$FOGFTP->delete($dest);
                if (!self::$FOGFTP->put($dest, $src)) {
                    throw new \RuntimeException(
                        _('Failed to add/update snapin file')
                    );
                }
                self::$FOGFTP->chmod(0777, $dest);
            } finally {
                self::$FOGFTP->close();
            }
        }
        $Snapin = self::getClass('Snapin')
            ->set('name', $name)
            ->set('packtype', $packtype)
            ->set('description', $desc)
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
            // File is already on Master at this point - caller may want
            // to surface that the row didn't save but the file landed.
            throw new SnapinSaveException(_('Add snapin failed!'));
        }
        $Snapin->setPrimaryGroup($storagegroup);
        return new Snapin($Snapin->get('id'));
    }
    /**
     * Sanitize a snapin file basename. Rejects names that match /ssl/i
     * (reserved by FOG for its own SSL bits) and replaces any character
     * that is not a word char, dot, or hyphen with an underscore.
     *
     * '.' and '..' are rejected outright: they survive basename() and
     * the normalization below untouched, so they would name the snapin
     * directory itself rather than a file in it. On working-1.6 that
     * was exploitable -- FOGSSH::delete() recursed and emptied the
     * directory. Here it is not, because FOGFTP::exists() rawlists the
     * parent and skips the '.' and '..' entries, so FOGFTP::delete()
     * no-ops and the ftp_put fails. This is parity hardening, not a
     * security fix on this branch (035 / 2.3.1).
     *
     * @param string $basename the raw basename to sanitize
     *
     * @throws InvalidArgumentException if the name is reserved or
     *                                  normalizes to '', '.' or '..'
     *
     * @return string the sanitized basename
     */
    public static function sanitizeSnapinFileName($basename)
    {
        $basename = basename($basename);
        if (preg_match('#ssl#i', $basename)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s, %s.',
                    _('Please choose a different name'),
                    _('this one is reserved for FOG')
                )
            );
        }
        $basename = preg_replace('/[^\-\w\.]+/', '_', $basename);
        if ('' === $basename
            || '.' === $basename
            || '..' === $basename
        ) {
            throw new \InvalidArgumentException(
                _('Invalid snapin filename')
            );
        }
        return $basename;
    }
    /**
     * Push one or more uploaded files to the given StorageNode via FTP.
     * Validates every file in $filesArray before opening any connection;
     * if any validation fails, nothing is sent. Once the connection is
     * open, files are pushed in order - a transport failure mid-batch
     * leaves earlier files on disk (no rollback). Used by
     * POST /fog/storagegroup/<id>/uploadsnapinfiles.
     *
     * Expects $filesArray in the multi-file $_FILES shape, e.g.:
     *   array(
     *     'name'     => array('a.exe', 'b.exe'),
     *     'tmp_name' => array('/tmp/phpAAA', '/tmp/phpBBB'),
     *     'error'    => array(0, 0),
     *     ...
     *   )
     *
     * @param StorageNode $StorageNode the master node to upload to
     * @param array       $filesArray  $_FILES['snapinfiles'] entry
     *
     * @throws InvalidArgumentException on bad input -> HTTP 400
     * @throws RuntimeException         on FTP failure -> HTTP 500
     *
     * @return void
     */
    public static function uploadFilesToNode(
        StorageNode $StorageNode,
        array $filesArray
    ) {
        if (empty($filesArray['name']) || !is_array($filesArray['name'])) {
            throw new \InvalidArgumentException(
                _('One or more files must be uploaded via the "snapinfiles[]" multipart field')
            );
        }
        $count = count($filesArray['name']);
        $validated = array();
        for ($i = 0; $i < $count; $i++) {
            $raw = isset($filesArray['name'][$i]) ? $filesArray['name'][$i] : '';
            if ($raw === '') {
                throw new \InvalidArgumentException(
                    sprintf(_('File at index %d has an empty filename'), $i)
                );
            }
            $err = isset($filesArray['error'][$i])
                ? (int)$filesArray['error'][$i]
                : UPLOAD_ERR_NO_FILE;
            if ($err !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('Upload failed for "%s" (error code %d)'),
                        basename($raw),
                        $err
                    )
                );
            }
            $tmp = isset($filesArray['tmp_name'][$i])
                ? $filesArray['tmp_name'][$i]
                : '';
            if (empty($tmp) || !is_uploaded_file($tmp)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('Malformed upload for "%s"'),
                        basename($raw)
                    )
                );
            }
            $sanitized = self::sanitizeSnapinFileName($raw);
            $validated[] = array('basename' => $sanitized, 'tmp' => $tmp);
        }
        set_time_limit(0);
        self::$FOGFTP
            ->set('host', $StorageNode->get('ip'))
            ->set('username', $StorageNode->get('user'))
            ->set('password', $StorageNode->get('pass'));
        if (!self::$FOGFTP->connect()) {
            throw new \RuntimeException(
                sprintf(
                    '%s: %s: %s.',
                    _('Storage Node'),
                    $StorageNode->get('ip'),
                    _('FTP Connection has failed')
                )
            );
        }
        try {
            if (!self::$FOGFTP->chdir($StorageNode->get('snapinpath'))) {
                if (!self::$FOGFTP->mkdir($StorageNode->get('snapinpath'))) {
                    throw new \RuntimeException(
                        _('Failed to create snapin path on Master Node')
                    );
                }
            }
            foreach ($validated as $f) {
                $dest = sprintf(
                    '/%s/%s',
                    trim($StorageNode->get('snapinpath'), '/'),
                    $f['basename']
                );
                self::$FOGFTP->delete($dest);
                if (!self::$FOGFTP->put($dest, $f['tmp'])) {
                    throw new \RuntimeException(
                        sprintf(
                            _('Failed to upload "%s" to Master Node'),
                            $f['basename']
                        )
                    );
                }
                self::$FOGFTP->chmod(0777, $dest);
            }
        } finally {
            self::$FOGFTP->close();
        }
    }
}
