<?php
/**
 * Adds the windows keys choice to image.
 *
 * PHP version 5
 *
 * @category AddWindowsKeyImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the windows keys choice to image.
 *
 * @category AddWindowsKeyImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWindowsKeyImage extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddWindowsKeyImage';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Windows Keys to images';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * THe node this hook enacts with.
     *
     * @var string
     */
    public $node = 'windowskey';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'IMAGE_FIELDS',
                array(
                    $this,
                    'imageFields'
                )
            )
            ->register(
                'IMAGE_ADD_SUCCESS',
                array(
                    $this,
                    'imageAddKey'
                )
            )
            ->register(
                'IMAGE_EDIT_SUCCESS',
                array(
                    $this,
                    'imageAddKey'
                )
            )
            ->register(
                'DESTROY_IMAGE',
                array(
                    $this,
                    'imageRemove'
                )
            )
            ->register(
                'SELECT_BUILD',
                array(
                    $this,
                    'imageKeySelector'
                )
            );
    }
    /**
     * Adjusts the image fields.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageFields($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'image') {
            return;
        }
        $WindowsKeys = self::getSubObjectIDs(
            'WindowsKeyAssociation',
            array(
                'imageID' => $arguments['Image']->get('id')
            ),
            'windowskeyID'
        );
        $cnt = self::getClass('WindowsKeyManager')->count(
            array(
                'id' => $WindowsKeys
            )
        );
        if ($cnt !== 1) {
            $wkID = 0;
        } else {
            $WindowsKeys = self::getSubObjectIDs(
                'WindowsKey',
                array('id' => $WindowsKeys)
            );
            $wkID = array_shift($WindowsKeys);
        }
        self::arrayInsertAfter(
            _('Operating System'),
            $arguments['fields'],
            _('Windows Key for Image'),
            self::getClass('WindowsKeyManager')->buildSelectBox(
                $wkID
            )
        );
    }
    /**
     * Adds the image selector to the host.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageAddKey($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        global $tab;
        $subs = array(
            'add',
            'edit',
            'addPost',
            'editPost'
        );
        if ($node != 'image') {
            return;
        }
        if (!in_array($sub, $subs)) {
            return;
        }
        if (str_replace('_', '-', $tab) != 'image-gen') {
            return;
        }
        self::getClass('WindowsKeyAssociationManager')->destroy(
            array(
                'imageID' => $arguments['Image']->get('id')
            )
        );
        $cnt = self::getClass('WindowsKeyManager')
            ->count(
                array('id' => $_REQUEST['windowskey'])
            );
        if ($cnt !== 1) {
            return;
        }
        self::getClass('WindowsKeyAssociation')
            ->set('imageID', $arguments['Image']->get('id'))
            ->load('imageID')
            ->set('windowskeyID', $_REQUEST['windowskey'])
            ->save();
    }
    /**
     * Removes windows key when image is destroyed.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageRemove($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::getClass('WindowsKeyAssociationManager')->destroy(
            array(
                'imageID' => $arguments['Image']->get('id')
            )
        );
    }
    /**
     * Changes the selector default item.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageKeySelector($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($arguments['obj'] instanceof WindowsKeyManager) {
            if (true === $arguments['waszero']) {
                $arguments['matchID'] = 0;
            }
        }
    }
}
