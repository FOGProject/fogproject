<?php
/**
 * The Windows Keys class.
 *
 * PHP version 5
 *
 * @category WindowsKey
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The Windows Keys class.
 *
 * @category WindowsKey
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKey extends FOGController
{
    /**
     * The windows keys table
     *
     * @var string
     */
    protected $databaseTable = 'windowsKeys';
    /**
     * The windows keys table fields and common names
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
        self::getClass('WindowsKeyAssociationManager')
            ->destroy(
                array(
                    'windowskeyID' => $this->get('id')
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
            ->assocSetter('WindowsKey', 'image');
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
    public function removeImage($removeArray)
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
            'WindowsKeyAssociation',
            array('windowskeyID' => $this->get('id')),
            'imageID'
        );
        $imageIDs = self::getSubObjectIDs(
            'Image',
            array('id' => $imageIDs)
        );
        $this->set(
            'images',
            (array)$imageIDs
        );
    }
    /**
     * Load the images not with this key.
     *
     * @return void
     */
    protected function loadImagesnotinme()
    {
        $images = array_diff(
            self::getSubObjectIDs('Image'),
            $this->get('images')
        );
        $this->set('imagesnotinme', (array)$images);
    }
}
