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
        $this->name = _('Windows Key Management');
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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $windowskey = filter_input(INPUT_POST, 'windowskey');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
    }
    /**
     * Show form for creating a new windows key entry.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'windowskey',
            _('Create New Windows Key'),
            'WINDOWSKEY_ADD_FIELDS',
            'WindowsKey'
        );
    }
    /**
     * Show form for creating a new windows key entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'windowskey',
            'WINDOWSKEY_ADD_FIELDS',
            'WindowsKey'
        );
    }
    /**
     * Actually create the windows key.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'WindowsKey',
            'WINDOWSKEY_ADD',
            _('Windows Key added!'),
            _('Windows Key Create Success'),
            _('Windows Key Create Fail'),
            function (&$serverFault) {
                $windowskey = trim(
                    filter_input(INPUT_POST, 'windowskey')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                $key = self::productKeyResolve(
                    trim(filter_input(INPUT_POST, 'key')),
                    ''
                );
                $exists = self::getClass('WindowsKeyManager')
                    ->exists($windowskey);
                if ($exists) {
                    throw new Exception(
                        _('A Windows Key already exists with this name!')
                    );
                }
                // The product key is checked here rather than left to a
                // unique index, because save() would silently overwrite the
                // record already holding it instead of failing. The empty
                // guard is belt-and-braces: 'key' is in databaseFieldsRequired
                // so a blank one should never reach here, and a blank is not a
                // meaningful duplicate of another blank anyway.
                if ($key !== ''
                    && self::getClass('WindowsKeyManager')
                        ->exists($key, 0, 'key')
                ) {
                    throw new Exception(
                        _('A Windows Key already exists with this product key!')
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
                return $WindowsKey;
            }
        );
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
        $key = self::productKeyMask($key);

        $labelClass = 'col-sm-3 col-form-label';

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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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
            '',
            'windowskey-general-form',
            self::makeTabUpdateURL(
                'windowskey-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
        $windowskey = trim(
            filter_input(INPUT_POST, 'windowskey')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $key = self::productKeyResolve(
            trim(filter_input(INPUT_POST, 'key')),
            $this->obj->get('key')
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
        // Same guard as addPost, and needed just as much here: retyping
        // another record's product key would have overwritten that record.
        // exists() excludes this row by id, so re-saving an unchanged key is
        // not mistaken for a duplicate.
        if ($key !== ''
            && self::getClass('WindowsKeyManager')
                ->exists($key, $this->obj->get('id'), 'key')
        ) {
            throw new Exception(
                _('A Windows Key already exists with this product key!')
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
            'windowskey-image-send',
            _('Add selected'),
            'btn btn-primary float-end',
            $props
        );
        $buttons .= self::makeButton(
            'windowskey-image-remove',
            _('Remove selected'),
            'btn btn-danger float-start',
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
        echo '<div id="images">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="updateimage" class="">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Images');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'windowskey-image-table', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
        if (isset($_POST['confirmadd'])) {
            $image = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $image = $image['additems'];
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually update the windows key.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'WindowsKey',
            'WINDOWSKEY_EDIT',
            _('Windows Key updated!'),
            _('Windows Key Update Success'),
            _('Windows Key Update Fail'),
            function (&$serverFault) {
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
            }
        );
    }
    /**
     * Windows Key -> Image membership list
     *
     * @return void
     */
    public function getImagesList()
    {
        return $this->assocItemsList(
            'image',
            'windowskeyassociation',
            'windowsKeysAssoc',
            '`images`.`imageID`',
            '`windowsKeysAssoc`.`wkaImageID`',
            '`windowsKeysAssoc`.`wkaKeyID`',
            [
                [
                    'db' => 'windowskeyAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
