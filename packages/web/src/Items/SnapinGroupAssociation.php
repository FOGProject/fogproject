<?php
/**
 * Snapin group association handling.
 *
 * PHP version 7.4+
 *
 * @category SnapinGroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Snapin group association handling.
 *
 * @category SnapinGroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinGroupAssociation extends FOGController
{
    /**
     * The snapin group association table.
     *
     * @var string
     */
    protected $databaseTable = 'snapinGroupAssoc';
    /**
     * The snapin group association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sgaID',
        'snapinID' => 'sgaSnapinID',
        'storagegroupID' => 'sgaStorageGroupID',
        'primary' => 'sgaPrimary'
    ];
    /**
     * The required fiedls
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'snapinID',
        'storagegroupID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'snapin',
        'storagegroup'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Snapin' => [
            'id',
            'snapinID',
            'snapin'
        ],
        'StorageGroup' => [
            'id',
            'storagegroupID',
            'storagegroup'
        ]
    ];
    /**
     * Get's the snapin object
     *
     * @return object
     */
    public function getSnapin()
    {
        return $this->get('snapin');
    }
    /**
     * Get's the associated storage group.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Returns whether this is the primary group or not.
     *
     * @return bool
     */
    public function isPrimary()
    {
        return (bool)$this->get('primary');
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SnapinGroupAssociation', 'SnapinGroupAssociation');
