<?php
/**
 * The Windows Keys class.
 *
 * PHP version 5
 *
 * @category WindowsKeys
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The Windows Keys class.
 *
 * @category WindowsKeys
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeys extends FOGController
{
    /**
     * The location table
     *
     * @var string
     */
    protected $databaseTable = 'windowskeys';
    /**
     * The location table fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'wkID',
        'name' => 'wkName',
        'description' => 'wkDesc',
        'createdBy' => 'wkCreatedBy',
        'createdTime' => 'wkCreatedTime',
        'key' => 'wkKey'
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'key'
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'images',
        'imagesnotinme'
    );
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        self::getClass('WindowsKeysAssociationManager')
            ->destroy(
                array(
                    'keyID' => $this->get('id')
                )
            );
        return parent::destroy($key);
    }
    /**
     * Stores the item in the DB either stored or updated.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('WindowsKeys', 'image');
    }
    /**
     * Add image to the windows key
     *
     * @param array $addArray the items to add.
     *
     * @return object
     */
    public function addImage($addArray)
    {
        return $this->addRemItem(
            'images',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove image from the windows key.
     *
     * @param array $removeArray the items to remove.
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'images',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads the windows keys images.
     *
     * @return void
     */
    protected function loadImages()
    {
        $imageIDs = self::getSubObjectIDs(
            'WindowsKeysAssociation',
            array('keyID' => $this->get('id')),
            'imageID'
        );
        $imageIDs = self::getSubObjectIDs(
            'Image',
            array('id' => $imageIDs)
        );
        $this->set(
            'images',
            $imageIDs
        );
    }
    /**
     * Load the images not with this key.
     *
     * @return void
     */
    protected function loadImagesnotinme()
    {
        $find = array(
            'id' => $this->get('images')
        );
        $imageIDs = self::getSubObjectIDs(
            'Image',
            $find,
            'id',
            true
        );
        $this->set('imagesnotinme', $imageIDs);
        unset($find);
    }
}
