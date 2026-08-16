<?php
/**
 * File Delete Queue.
 *
 * PHP Version 5
 *
 * @category FileDeleteQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * File Delete Queue.
 *
 * @category FileDeleteQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileDeleteQueue extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'fileDeleteQueue';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'fdqID',
        'path' => 'fdqPathName',
        'storagegroupID' => 'fdqStorageGroupID',
        'createdTime' => 'fdqCreateDate',
        'createdBy' => 'fdqCreateBy',
        'completedTime' => 'fdqCompletedDate',
        'stateID' => 'fdqState',
        'pathtype' => 'fdqPathType'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'path',
        'pathtype',
        'storagegroupID'
    ];
    private function normalizeQueuePath($path)
    {
        $path = str_replace('\\', '/', trim((string)$path));
        if ($path === '' || strpos($path, "\0") !== false) {
            throw new \Exception(_('Invalid delete path'));
        }
        if (preg_match('#^(/|[A-Za-z]:/)#', $path) || preg_match('#(^|/)\\.\\.(/|$)#', $path)) {
            throw new \Exception(_('Path escapes storage root'));
        }
        return ltrim($path, '/');
    }

    public function save()
    {
        $type = strtolower(trim((string)$this->get('pathtype')));
        if (!in_array($type, ['image', 'snapin'], true)) {
            throw new \Exception(_('Invalid pathtype'));
        }
        $this->set('pathtype', ucfirst($type));
        $this->set('path', $this->normalizeQueuePath($this->get('path')));
        return parent::save();
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FileDeleteQueue', 'FileDeleteQueue');
