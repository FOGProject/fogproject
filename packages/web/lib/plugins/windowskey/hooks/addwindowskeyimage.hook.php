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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'PLUGINS_INJECT_TABDATA',
            [$this, 'imageTabData']
        )->register(
            'IMAGE_EDIT_SUCCESS',
            [$this, 'imageAddKeyEdit']
        )->register(
            'IMAGE_ADD_FIELDS',
            [$this, 'imageAddKeyField']
        );
    }
    /**
     * The image tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageTabData($arguments)
    {
        global $node;
        if ($node != 'image') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['pluginsTabData'][] = [
            'name' => _('Key Association'),
            'id' => 'image-windowskey',
            'generator' => function () use ($obj) {
                $this->imageWindowskey($obj);
            }
        ];
    }
    /**
     * The image key display.
     *
     * @param object $obj The image object we're working with.
     *
     * @return void
     */
    public function imageWindowskey($obj)
    {
        $keyID = (int)filter_input(INPUT_POST, 'windowskey');
        // Image keys
        $windowskeySelector = self::getClass('WindowsKeyManager')
            ->buildSelectBox($keyID, 'windowskey');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'windowskey',
                _('Windows Key')
            ) => $windowskeySelector
        ];

        $buttons = FOGPage::makeButton(
            'windowskey-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'IMAGE_WINDOWSKEY_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Image' => &$obj
            ]
        );
        $rendered = FOGPage::formFields($fields);
        unset($fields);

        echo FOGPage::makeFormTag(
            'form-horizontal',
            'image-windowskey-form',
            FOGPage::makeTabUpdateURL(
                'image-windowskey',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Windows Key');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The windows key updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function imageWindowskeyPost($obj)
    {
        $keyID = (int)filter_input(INPUT_POST, 'windowskey');
        $insert_fields = ['imageID', 'windowskeyID'];
        $insert_values = [];
        $images = [$obj->get('id')];
        if (count($images ?: [])) {
            Route::deletemass(
                'windowskeyassociation',
                ['imageID' => $images]
            );
            if ($keyID > 0) {
                foreach ((array)$images as $ind => &$imageID) {
                    $insert_values[] = [$imageID, $keyID];
                    unset($imageID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('WindowsKeyAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The image key selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageAddKeyEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'image') {
            return;
        }
        $obj = $arguments['Image'];
        try {
            switch ($tag) {
                case 'image-windowskey':
                    $this->imageWindowskeyPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'IMAGE_EDIT_WINDOWSKEY_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Image Windows Key Updated!'),
                    'title' => _('Image Windows Key Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'IMAGE_EDIT_WINDOWSKEY_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Image Windows Key Update Fail')
                ]
            );
        }
    }
    /**
     * The image key field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function imageAddKeyField($arguments)
    {
        global $node;
        if ($node != 'image') {
            return;
        }
        $keyID = (int)filter_input(INPUT_POST, 'windowskey');
        $keySelector = self::getClass('WindowsKeyManager')
            ->buildSelectBox($keyID, 'windowskey');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'windowskey',
                _('Image Windows Key')
            )
        ] = $keySelector;
    }
}
