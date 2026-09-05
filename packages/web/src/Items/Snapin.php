<?php
/**
 * The snapin object.
 *
 * PHP version 7.4+
 *
 * @category Snapin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Exception\SnapinSaveException;
use FOG\Exception\UploadException;
use FOG\Managers\SnapinJobManager;
use FOG\Managers\SnapinManager;
use FOG\Router\Route;

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
    protected $databaseFields = [
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
        'returnCodes' => 'sReturnCodes',
        'packtype' => 'sPackType',
        'hash' => 'sHash',
        'size' => 'sSize',
        'anon3' => 'sAnon3'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'file'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts',
        'storagegroups',
        'path'
    ];
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
        $find = ['snapinID' => $this->get('id')];
        $snapinJobIDs = Route::getIds(
            'snapintask',
            $find,
            'jobID'
        );
        Route::deletemass(
            'snapintask',
            $find
        );
        $snapinJobIDs = Route::getIds(
            'snapinjob',
            [
                'id' => $snapinJobIDs,
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            ]
        );
        $sjIDs = [];
        foreach ((array)$snapinJobIDs as &$sjID) {
            $jobCount = Route::getCount(
                'snapintask',
                ['jobID' => $sjID]
            );
            if ($jobCount > 0) {
                continue;
            }
            $sjIDs[] = $sjID;
        }
        if (count($sjIDs ?: [])) {
            (new SnapinJobManager())->cancel($sjIDs);
        }
        Route::deletemass(
            'snapingroupassociation',
            $find
        );
        Route::deletemass(
            'snapinassociation',
            $find
        );

        return parent::destroy($key);
    }
    /**
     * Stores data into the database.
     *
     * @return bool|object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }

        $primary = Route::getIds(
            'snapingroupassociation',
            [
                'snapinID' => $this->get('id'),
                'primary' => 1
            ],
            'storagegroupID'
        );
        $this
            ->assocSetter('Snapin', 'host')
            ->assocSetter('SnapinGroup', 'storagegroup');
        if (count($primary) > 0) {
            $primary = array_shift($primary);
            self::setPrimaryGroup($primary, $this->get('id'));
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
            throw new \Exception(self::$foglang['ProtectedSnapin']);
        }
        foreach ($this->get('storagegroups') as $storagegroupID) {
            (new FileDeleteQueue())
                ->set('path', $this->get('file'))
                ->set('pathtype', 'Snapin')
                ->set('createdTime', self::storageNow())
                ->set('stateID', self::getQueuedState())
                ->set('createdBy', self::$FOGUser->get('name'))
                ->set('storagegroupID', $storagegroupID)
                ->save();
        }
        return true;
    }
    /**
     * Loads hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->_loadHostIds(
            'snapinassociation',
            ['snapinID' => $this->get('id')],
            'hostID'
        );
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
     * Loads storage groups with this object.
     *
     * @return void
     */
    protected function loadStoragegroups()
    {
        $find = ['snapinID' => $this->get('id')];
        $groups = Route::getIds(
            'snapingroupassociation',
            $find,
            'storagegroupID'
        );
        if (count($groups ?: []) < 1) {
            $groups = Route::getIds('storagegroup', false);
            $groups = [self::minId($groups)];
        }
        $this->set('storagegroups', $groups);
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
            $groupids = Route::getIds('storagegroup', false);
            $groupids = [self::minId($groupids)];
            if (count($groupids) < 1) {
                throw new \Exception(_('No viable storage groups found'));
            }
        }
        $primaryGroup = [];
        foreach ($groupids as &$groupid) {
            if (!self::getPrimaryGroup($groupid, $this->get('id'))) {
                continue;
            }
            $primaryGroup[] = $groupid;
            unset($groupid);
        }
        if (count($primaryGroup) < 1) {
            $primaryGroup = self::minId($groupids);
        } else {
            $primaryGroup = array_shift($primaryGroup);
        }

        return new StorageGroup($primaryGroup);
    }
    /**
     * Gets the snapin's primary group.
     *
     * @param int $groupID  the group id to check
     * @param int $snapinID the snapin id to check
     *
     * @return bool
     */
    public static function getPrimaryGroup($groupID, $snapinID)
    {
        $find = [
            'snapinID' => $snapinID,
            'primary' => 1
        ];
        $primaryCount = Route::getCount(
            'snapingroupassociation',
            $find
        );
        if ($primaryCount < 1) {
            unset($find['primary']);
            $primaryCount = Route::getCount(
                'snapingroupassociation',
                $find
            );
        }
        if ($primaryCount < 1) {
            $groupid = Route::getIds('storagegroup', false);
            $groupid = self::minId($groupid);
            if ($groupid > 0) {
                self::setPrimaryGroup($groupid, $snapinID);
            }
        }
        $find = [
            'storagegroupID' => $groupID,
            'snapinID' => $snapinID
        ];
        $assocID = Route::getIds(
            'snapingroupassociation',
            $find
        );
        $assocID = self::minId($assocID);

        return (new SnapinGroupAssociation($assocID))->isPrimary();
    }
    /**
     * Sets the primary group for the snapin.
     *
     * @param int $groupID  the id to set as primary
     * @param int $snapinID the id to use with primary group
     *
     * @return array
     */
    public static function setPrimaryGroup($groupID, $snapinID)
    {
        self::_setPrimaryGroup(
            $groupID,
            $snapinID,
            'snapinID',
            'snapingroupassociation',
            'SnapinGroupAssociation'
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
     * Validates incoming form data, uploads the file to the Master
     * Storage Node of the chosen Storage Group (when a new file was
     * uploaded), and persists the Snapin row.
     *
     * Shared between SnapinManagement::addPost (UI) and
     * Route::createSnapinWithFile (API). The two callers differ in
     * how they map exceptions to HTTP codes; the helper itself is
     * transport-agnostic. See docs/adr/0001-api-ui-http-status-divergence.md.
     *
     * Exception contract:
     *  - \InvalidArgumentException — bad/missing/duplicate name,
     *    missing file, reserved filename ('ssl'), or upload error
     *  - \RuntimeException — SSH connect, SFTP mkdir, delete, or put
     *    failure
     *  - SnapinSaveException — $Snapin->save() returned false after
     *    the file was already on the node (leaves an orphaned file
     *    behind; matches existing UI behavior)
     *
     * Note: when 'snapinfileexist' names a file already on the node,
     * this helper reuses it without checking whether another Snapin
     * references it (overwrite-and-pray). Matches existing UI
     * behavior; not endorsed, but preserved for parity.
     *
     * @param array $post  Sanitized POST data using the UI's field
     *                     names: snapin, description, packtype, rw,
     *                     rwa, storagegroup, snapinfileexist,
     *                     isEnabled, toReplicate, isHidden, timeout,
     *                     action, args
     * @param array $files $_FILES (looks for 'snapinfile' key)
     *
     * @return Snapin The newly saved Snapin
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws SnapinSaveException
     */
    public static function uploadAndCreate(array $post, array $files)
    {
        $snapin = trim((string)($post['snapin'] ?? ''));
        $description = trim((string)($post['description'] ?? ''));
        $packtype = trim((string)($post['packtype'] ?? ''));
        $runWith = trim((string)($post['rw'] ?? ''));
        $runWithArgs = trim((string)($post['rwa'] ?? ''));
        $storagegroup = (int)trim((string)($post['storagegroup'] ?? ''));
        if (!$storagegroup) {
            $storagegroup = self::minId(Route::getIds('storagegroup', false));
        }
        $snapinfile = basename(trim((string)($post['snapinfileexist'] ?? '')));
        $uploadfile = '';
        if (!empty($files['snapinfile']['name'])) {
            $uploadfile = basename(trim((string)$files['snapinfile']['name']));
        }
        if ($uploadfile) {
            $snapinfile = $uploadfile;
        }
        $isEnabled = (int)isset($post['isEnabled']);
        $toReplicate = (int)isset($post['toReplicate']);
        $hide = (int)isset($post['isHidden']);
        $action = trim((string)($post['action'] ?? ''));
        $args = trim((string)($post['args'] ?? ''));
        $timeout = trim((string)($post['timeout'] ?? ''));
        $returnCodes = trim((string)($post['returnCodes'] ?? ''));

        if ((new SnapinManager())->exists($snapin)) {
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
        // Single chokepoint for both the uploaded name and the
        // 'snapinfileexist' selection -- they merge into $snapinfile
        // above, so the helper's reserved-name and dot rejection cover
        // both. Was an open-coded copy of the 'ssl' check plus the
        // normalization regex, which let '.' through (035 / 2.3.1).
        $snapinfile = self::sanitizeSnapinFileName($snapinfile);
        $StorageGroup = new StorageGroup($storagegroup);
        $StorageNode = $StorageGroup->getMasterStorageNode();
        if ($uploadfile && (int)($files['snapinfile']['error'] ?? 0) > 0) {
            throw new \InvalidArgumentException(
                (new UploadException((int)$files['snapinfile']['error']))->getMessage()
            );
        }
        $src = sprintf(
            '%s/%s',
            dirname($files['snapinfile']['tmp_name'] ?? ''),
            basename($files['snapinfile']['tmp_name'] ?? '')
        );
        $dest = sprintf(
            '/%s/%s',
            trim($StorageNode->get('snapinpath'), '/'),
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
                throw new \RuntimeException(
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
                    throw new \RuntimeException(
                        _('Failed to add snapin')
                        . ' ' . $rdir . ' '
                        . _('does not exist and cannot be created')
                    );
                }
            }
            if (self::$FOGSSH->exists($dest)) {
                // unlinkFile, not delete: delete() recurses into a
                // directory when the unlink fails (035 / 2.3.1). This
                // guard is also now live -- delete() returned $this on
                // every path, so the !delete() test could never fire.
                if (!self::$FOGSSH->unlinkFile($dest)) {
                    throw new \RuntimeException(
                        _('Failed to delete existing snapin file')
                    );
                }
            }
            self::$FOGSSH->put($src, $dest);
            self::$FOGSSH->disconnect();
        }
        $Snapin = (new Snapin())
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
            ->set('returnCodes', $returnCodes)
            ->addGroup($storagegroup);
        if (!$Snapin->save()) {
            throw new SnapinSaveException(_('Add snapin failed!'));
        }
        self::setPrimaryGroup($storagegroup, $Snapin->get('id'));
        return $Snapin;
    }
    /**
     * Rejects reserved snapin filenames and returns a filesystem-safe
     * basename. The 'ssl' substring is reserved for FOG's own SSL
     * material under the snapin path; allowing it would let an upload
     * shadow or clobber a cert/key.
     *
     * '.' and '..' are rejected outright: they survive basename() and
     * the [^-\w.] normalization below untouched, so a snapin filename
     * of '.' made $dest the snapin directory itself. FOGSSH::delete()
     * then fell back to its recursive branch and unlinked every snapin
     * payload on the group's master node. Rejecting exactly '', '.'
     * and '..' is sufficient -- after normalization no other value can
     * contain a path separator, so no other value can name anything
     * but a file inside the snapin directory.
     * Reported by Aisle Research (035 / 2.3.1).
     *
     * @param string $basename The candidate basename (already
     *                         basename()'d by the caller)
     *
     * @return string Sanitized basename
     *
     * @throws \InvalidArgumentException If $basename matches the
     *                                   reserved 'ssl' pattern, or
     *                                   normalizes to '', '.' or '..'
     */
    public static function sanitizeSnapinFileName(string $basename): string
    {
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
     * Transfers one or more uploaded files to a Storage Node's snapin
     * path over SFTP. Pure transport — no Snapin DB rows are created
     * or modified. Companion to Route::uploadSnapinFiles, which
     * normally targets the Master Node of a Storage Group so the
     * FOGSnapinReplicator can propagate from there.
     *
     * All files are validated up front; the SFTP session is opened
     * once, used for every transfer, then closed in a finally so
     * the connection doesn't leak on a partial failure.
     *
     * Collision policy: overwrite. Matches existing UI/createwithfile
     * behavior. Caller is responsible for whatever protection it
     * wants on top.
     *
     * @param StorageNode $StorageNode The destination node
     * @param array       $filesArray  $_FILES['snapinfiles']
     *                                 (multi-file form with 'name',
     *                                 'tmp_name', 'error' as arrays)
     *
     * @return void
     *
     * @throws \InvalidArgumentException Bad/missing filename, reserved
     *                                   filename, upload error
     * @throws \RuntimeException         SSH connect / mkdir / delete /
     *                                   put failure
     */
    public static function uploadFilesToNode(
        StorageNode $StorageNode,
        array $filesArray
    ): void {
        $names = $filesArray['name'] ?? [];
        $tmpNames = $filesArray['tmp_name'] ?? [];
        $errors = $filesArray['error'] ?? [];
        if (!is_array($names) || empty($names)) {
            throw new \InvalidArgumentException(
                _('No files provided in the "snapinfiles[]" multipart field')
            );
        }
        $transfers = [];
        foreach ($names as $idx => $name) {
            $name = basename(trim((string)$name));
            $err = (int)($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException(
                    (new UploadException($err))->getMessage()
                );
            }
            if ('' === $name) {
                throw new \InvalidArgumentException(
                    _('Empty filename in upload')
                );
            }
            $sanitized = self::sanitizeSnapinFileName($name);
            $tmp = (string)($tmpNames[$idx] ?? '');
            if ('' === $tmp || !is_uploaded_file($tmp)) {
                throw new \InvalidArgumentException(
                    sprintf('%s: %s', _('Invalid upload'), $name)
                );
            }
            $transfers[] = ['src' => $tmp, 'basename' => $sanitized];
        }
        set_time_limit(0);
        self::$FOGSSH->username = $StorageNode->get('user');
        self::$FOGSSH->password = $StorageNode->get('pass');
        self::$FOGSSH->host = $StorageNode->get('ip');
        if (!self::$FOGSSH->connect()) {
            throw new \RuntimeException(
                sprintf(
                    '%s: %s: %s.',
                    _('Storage Node'),
                    $StorageNode->get('ip'),
                    _('SSH Connection has failed')
                )
            );
        }
        try {
            self::$FOGSSH->sftp();
            $rdir = $StorageNode->get('snapinpath');
            if (!self::$FOGSSH->exists($rdir)) {
                if (false === self::$FOGSSH->sftp_mkdir($rdir)) {
                    throw new \RuntimeException(
                        _('Failed to create snapin directory')
                        . ': ' . $rdir
                    );
                }
            }
            foreach ($transfers as $t) {
                $dest = sprintf(
                    '/%s/%s',
                    trim($rdir, '/'),
                    $t['basename']
                );
                if (self::$FOGSSH->exists($dest)) {
                    // See uploadAndCreate above -- non-recursive
                    // removal only (035 / 2.3.1).
                    if (!self::$FOGSSH->unlinkFile($dest)) {
                        throw new \RuntimeException(
                            sprintf(
                                '%s: %s',
                                _('Failed to delete existing snapin file'),
                                $dest
                            )
                        );
                    }
                }
                self::$FOGSSH->put($t['src'], $dest);
            }
        } finally {
            self::$FOGSSH->disconnect();
        }
    }
}
