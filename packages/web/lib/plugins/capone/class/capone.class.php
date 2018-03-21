<?php
/**
 * Handles the database for Capone plugin
 *
 * PHP version 5
 *
 * @category Capone
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles the database for Capone plugin
 *
 * @category Capone
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Capone extends FOGController
{
    /**
     * The capone table
     *
     * @var string
     */
    protected $databaseTable = 'capone';
    /**
     * The Capone table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'cID',
        'imageID' => 'cImageID',
        'osID' => 'cOSID',
        'key' => 'cKey'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'image',
        'os',
        'storagegroup',
        'storagenode'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Image' => [
            'id',
            'imageID',
            'image'
        ],
        'OS' => [
            'id',
            'osID',
            'os'
        ]
    ];
    /**
     * Gets the capone data for api.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `capone`.`cImageID` = `images`.`imageID`
        LEFT OUTER JOIN `os`
        ON `images`.`imageOSID` = `os`.`osID`
        %s
        %s
        %s";
    /**
     * Gets the filter str.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        %s";
    /**
     * Gets the total str.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`";
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
     * Returns the os object
     *
     * @return object
     */
    public function getOS()
    {
        return $this->get('os');
    }
    /**
     * Returns the storage group
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Returns the storage node
     *
     * @return object
     */
    public function getStorageNode()
    {
        return $this->get('storagenode');
    }
    /**
     * Loads the storage group for this capone
     *
     * @return void
     */
    protected function loadStoragegroup()
    {
        $this->set(
            'storagegroup',
            $this->get('image')->getStorageGroup()
        );
    }
    /**
     * Loads the storage node for this capone
     *
     * @return void
     */
    protected function loadStoragenode()
    {
        $group = $this->get('storagegroup');
        $node = $group
            ->getOptimalStorageNode();
        $this->set('storagenode', $node);
    }
}
