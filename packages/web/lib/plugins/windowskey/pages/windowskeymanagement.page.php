<?php
/**
 * Windows Keys management page.
 *
 * PHP version 5
 *
 * @category WindowsKeyManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows Keys management page.
 *
 * @category WindowsKeyManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyManagement extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'windowskey';
    /**
     * Initializes the Windows key management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Windows Key Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Windows Key Name'),
            _('Product Key')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Show form for creating a new windows key entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Windows Key');

        $windowskey = filter_input(INPUT_POST, 'windowskey');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'windowskey',
                _('Windows Key Name')
            ) => self::makeInput(
                'form-control windowskeyname-input',
                'windowskey',
                _('Windows 10 Professional'),
                'text',
                'windowskey',
                $windowskey,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Windows Key Description')
            ) => self::makeTextarea(
                'form-control windowskeydescription-name',
                'description',
                _('Windows Key Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Windows Product key')
            ) => self::makeInput(
                'form-control windowsproductkey-input',
                'key',
                '',
                'text',
                'key',
                $key,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'WINDOWSKEY_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'WindowsKey' => self::getClass('WindowsKey')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'windowskey-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="windowskey-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Windows Key');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Show form for creating a new windows key entry.
     *
     * @return void
     */
    public function addModal()
    {
        $windowskey = filter_input(INPUT_POST, 'windowskey');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'windowskey',
                _('Windows Key Name')
            ) => self::makeInput(
                'form-control windowskeyname-input',
                'windowskey',
                _('Windows 10 Professional'),
                'text',
                'windowskey',
                $windowskey,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Windows Key Description')
            ) => self::makeTextarea(
                'form-control windowskeydescription-name',
                'description',
                _('Windows Key Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Windows Product key')
            ) => self::makeInput(
                'form-control windowsproductkey-input',
                'key',
                '',
                'text',
                'key',
                $key,
                true
            )
        ];

        self::$HookManager->processEvent(
            'WINDOWSKEY_ADD_FIELDS',
            [
                'fields' => &$fields,
                'WindowsKey' => self::getClass('WindowsKey')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=windowskey&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually create the windows key.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('WINDOWSKEY_ADD_POST');
        $windowskey = trim(
            filter_input(INPUT_POST, 'windowskey')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('WindowsKeyManager')
                ->exists($windowskey);
            if ($exists) {
                throw new Exception(
                    _('A Windows Key already exists with this name!')
                );
            }
            $WindowsKey = self::getClass('WindowsKey')
                ->set('name', $windowskey)
                ->set('description', $description)
                ->set('key', $key);
            if (!$WindowsKey->save()) {
                $serverFault = true;
                throw new Exception(_('Add windows key failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'WINDOWSKEY_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Windows Key added!'),
                    'title' => _('Windows Key Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'WINDOWSKEY_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Windows Key Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=windowskey&sub=edit&id='
        //    . $WindowsKey->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'WindowsKey' => &$WindowsKey,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($WindowsKey);
        echo $msg;
        exit;
    }
    /**
     * Display Windows Key General information.
     *
     * @return void
     */
    public function windowsKeyGeneral()
    {
        $windowskey = (
            filter_input(
                INPUT_POST,
                'windowskey'
            ) ?: $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?: $this->obj->get('description')
        );
        $key = (
            filter_input(
                INPUT_POST,
                'key'
            ) ?: $this->obj->get('key')
        );
        // For compatibility
        $keytest = self::aesdecrypt($key);
        $test_base64 = base64_decode($keytest);
        $keyb64 = mb_detect_encoding($test_base64, 'utf-8', true);
        $keyenc = mb_detect_encoding($keytest, 'utf-8', true);
        if ($keyb64) {
            $key = $test_base64;
        } elseif ($keyenc) {
            $key = $keytest;
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'windowskey',
                _('Windows Key Name')
            ) => self::makeInput(
                'form-control windowskeyname-input',
                'windowskey',
                _('Windows 10 Professional'),
                'text',
                'windowskey',
                $windowskey,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Windows Key Description')
            ) => self::makeTextarea(
                'form-control windowskeydescription-name',
                'description',
                _('Windows Key Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Windows Product key')
            ) => self::makeInput(
                'form-control windowsproductkey-input',
                'key',
                '',
                'text',
                'key',
                $key,
                true
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'WINDOWSKEY_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'WindowsKey' => &$this->obj
            ]
        );

        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'windowskey-general-form',
            self::makeTabUpdateURL(
                'windowskey-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the windows key general area.
     *
     * @return void
     */
    public function windowsKeyGeneralPost()
    {
        $windowskey = trim(
            filter_input(INPUT_POST, 'windowskey')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );

        $exists = self::getClass('WindowsKeyManager')
            ->exists($windowskey);
        if ($windowskey != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A Windows Key already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $windowskey)
            ->set('description', $description)
            ->set('key', $key);
    }
    /**
     * Presents the membership information
     *
     * @return void
     */
    public function windowsKeyImages()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'windowskey-images',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'image-add',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'image-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Image Name'),
            _('Image Associated')
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<!-- Images -->';
        echo '<div class="box-group" id="images">';
        echo '<div class="box box-solid">';
        echo '<div class="updateimage" class="">';
        echo '<div class="box-body">';
        echo $this->render(12, 'windowskey-image-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('image');
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Commonized membership actions
     *
     * @return void
     */
    public function windowsKeyImagePost()
    {
        if (isset($_POST['updateimages'])) {
            $image = filter_input_array(
                INPUT_POST,
                [
                    'image' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $image = $image['image'];
            if (count($image ?: []) > 0) {
                $this->obj->addImage($image);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $image = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $image = $image['remitems'];
            if (count($image ?: []) > 0) {
                $this->obj->removeImage($image);
            }
        }
    }
    /**
     * Present the windows key to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        $tabData[] = [
            'name' => _('General'),
            'id' => 'windowskey-general',
            'generator' => function () {
                $this->windowsKeyGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Images'),
                        'id' => 'windowskey-images',
                        'generator' => function () {
                            $this->windowsKeyImages();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually update the windows key.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'WINDOWSKEY_EDIT_POST',
            ['WindowsKey' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'windowskey-general':
                    $this->windowsKeyGeneralPost();
                    break;
                case 'windowskey-images':
                    $this->windowsKeyImagePost();
            }

            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Windows Key update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'WINDOWSKEY_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Windows Key updated!'),
                    'title' => _('Windows Key Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'WINDOWSKEY_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Windows Key Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'WindowsKey' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Windows Key -> Image membership list
     *
     * @return void
     */
    public function getImagesList()
    {
        $join = [
            'LEFT OUTER JOIN `windowsKeysAssoc` ON '
            . '`images`.`imageID` = `windowsKeysAssoc`.`wkaImageID` '
            . "AND `windowsKeysAssoc`.`wkaKeyID` = '". $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'windowskeyAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'image',
            'windowskeyassociation',
            $join,
            '',
            $columns
        );
    }
}
