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
    protected $databaseFields = array(
        'id' => 'cID',
        'imageID' => 'cImageID',
        'osID' => 'cOSID',
        'key' => 'cKey'
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'image',
        'os',
        'storagegroup',
        'storagenode',
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
        'OS' => array(
            'id',
            'osID',
            'os'
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
