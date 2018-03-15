<?php
/**
 * The association between images and windows keys.
 *
 * PHP version 5
 *
 * @category WindowsKeyAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The association between images and windows keys.
 *
 * @category WindowsKeyAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyAssociation extends FOGController
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
    protected $databaseFields = [
        'id' => 'wkaID',
        'imageID' => 'wkaImageID',
        'windowskeyID' => 'wkaKeyID'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'imageID',
        'windowskeyID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'key',
        'image'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'WindowsKey' => [
            'id',
            'windowskeyID',
            'key'
        ],
        'Image' => [
            'id',
            'imageID',
            'image'
        ]
    ];
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
