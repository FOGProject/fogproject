<?php
/**
 * The image storage group association class.
 *
 * PHP version 5
 *
 * @category ImageAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The image storage group association class.
 *
 * @category ImageAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageAssociation extends FOGController
{
    /**
     * Image association table name.
     *
     * @var string
     */
    protected $databaseTable = 'imageGroupAssoc';
    /**
     * Image association fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'igaID',
        'imageID' => 'igaImageID',
        'storagegroupID' => 'igaStorageGroupID',
        'primary' => 'igaPrimary',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'imageID',
        'storagegroupID',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'image',
        'storagegroup'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Image' => array(
            'id',
            'imageID',
            'image'
        ),
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup'
        )
    );
    /**
     * Returns the image object
     *
     * @return object
     */
    public function getImage()
    {
        return $this->get('image');
    }
    /**
     * Returns the storage group object
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Returns if we're primary or not
     *
     * @return bool
     */
    public function isPrimary()
    {
        return (bool)$this->get('primary') > 0;
    }
}
