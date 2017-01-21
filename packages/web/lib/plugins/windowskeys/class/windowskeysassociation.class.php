<?php
/**
 * The association between images and windows keys.
 *
 * PHP version 5
 *
 * @category WindowsKeysAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The association between images and windows keys.
 *
 * @category WindowsKeysAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeysAssociation extends FOGController
{
    /**
     * The association table.
     *
     * @var string
     */
    protected $databaseTable = 'windowsKeysAssoc';
    /**
     * The association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'wkaID',
        'imageID' => 'wkaImageID',
        'keyID' => 'wkaKeyID',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'imageID',
        'keyID',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'key',
        'image'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'WindowsKeys' => array(
            'id',
            'keyID',
            'key'
        ),
        'Location' => array(
            'id',
            'imageID',
            'image'
        )
    );
    /**
     * Return the associated image.
     *
     * @return object
     */
    public function getImage()
    {
        return $this->get('image');
    }
    /**
     * Return the associated key.
     *
     * @return host
     */
    public function getKey()
    {
        return $this->get('key');
    }
}
