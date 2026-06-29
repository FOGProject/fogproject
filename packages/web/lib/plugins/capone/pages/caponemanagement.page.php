<?php
/**
 * The capone page.
 *
 * PHP version 5
 *
 * @category CaponeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The capone page.
 *
 * @category CaponeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CaponeManagement extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'capone';
    /**
     * Initializes the WOL Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Capone Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Edit Capone'),
            _('Image Name'),
            _('Image OS'),
            _('Search Key')
        ];
        $this->attributes = [
            [],
            [],
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
        $image = filter_input(INPUT_POST, 'image');
        $key = filter_input(INPUT_POST, 'key');
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'image',
                _('Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'key',
                _('Key to match')
            ) => self::makeInput(
                'form-control caponekey-input',
                'key',
                _('Key to match'),
                'text',
                'key',
                $key,
                true
            )
        ];
    }
    /**
     * Create new capone entry.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'capone',
            _('Create New Capone'),
            'CAPONE_ADD_FIELDS',
            'Capone'
        );
    }
    /**
     * Create new capone entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'capone',
            'CAPONE_ADD_FIELDS',
            'Capone'
        );
    }
    /**
     * Actually create the broadcast.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Capone',
            'CAPONE_ADD',
            _('Capone added!'),
            _('Capone Create Success'),
            _('Capone Create Fail'),
            function (&$serverFault) {
                $imageID = trim(
                    filter_input(INPUT_POST, 'image')
                );
                $key = trim(
                    filter_input(INPUT_POST, 'key')
                );
                $image = new Image($imageID);
                $os = $image->getOS();
                $osID = $os->get('id');
                if (!$image->isValid()) {
                    throw new Exception(
                        _('Please select a valid image')
                    );
                }
                if (!$os->isValid()) {
                    throw new Exception(
                        _('The image associated does not have a valid OS!')
                    );
                }
                $Capone = self::getClass('Capone')
                    ->set('imageID', $imageID)
                    ->set('osID', $osID)
                    ->set('key', $key);
                if (!$Capone->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add capone failed!'));
                }
                return $Capone;
            }
        );
    }
    /**
     * Capone General tab.
     *
     * @return void
     */
    public function caponeGeneral()
    {
        $this->title = _('Editing Capone ID')
            . ': '
            . $this->obj->get('id');
            
        $image = (
            filter_input(INPUT_POST, 'image') ?:
            $this->obj->get('imageID')
        );
        $key = (
            filter_input(INPUT_POST, 'key') ?:
            $this->obj->get('key')
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'image',
                _('Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'key',
                _('Key to match')
            ) => self::makeInput(
                'form-control caponekey-input',
                'key',
                _('Key to match'),
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
            'CAPONE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Capone' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'capone-general-form',
            self::makeTabUpdateURL(
                'capone-general',
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
     * Updates the capone general elements.
     *
     * @return void
     */
    public function caponeGeneralPost()
    {
        self::checkAuthAndCSRF();
        $imageID = trim(
            filter_input(INPUT_POST, 'image')
        );
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );
        $image = new Image($imageID);
        $os = $image->getOS();
        $osID = $os->get('id');

        if (!$image->isValid()) {
            throw new Exception(
                _('Please select a valid image')
            );
        }
        if (!$os->isValid()) {
            throw new Exception(
                _('The image associated does not have a valid OS!')
            );
        }

        $this->obj
            ->set('imageID', $imageID)
            ->set('osID', $osID)
            ->set('key', $key);
    }
    /**
     * Present the wol broadcast to edit the page.
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

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'capone-general',
            'generator' => function () {
                $this->caponeGeneral();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * The capone global settings options.
     *
     * @return void
     */
    public function globalsettings()
    {
        $this->title = _('Editing Global Capone Settings');
        $find = [
            'name' => [
                'FOG_PLUGIN_CAPONE_DMI',
                'FOG_PLUGIN_CAPONE_SHUTDOWN'
            ]
        ];
        $settings = Route::getIds(
            'setting',
            $find,
            'value'
        );
        list(
            $dmiField,
            $actionType
        ) = $settings;

        $actionFields = [
            _('Reboot after deploy'),
            _('Shutdown after deploy')
        ];

        $dmifield = (
            filter_input(INPUT_POST, 'dmifield') ?:
            $dmiField
        );
        $action = (
            filter_input(INPUT_POST, 'action') ?:
            $actionType
        );

        $dmiSelector = self::getClass('DMIKeyManager')->buildSelectBox(
            $dmifield, // Match
            'dmifield', // Name of form element
            'name', // Sort by
            '', // Filter by
            false, // Whether to use template (old style likely no longer needed)
            'name' // Allow changing the value to a custom common id
        );

        $actionSelector = self::selectForm(
            'action',
            $actionFields,
            $action,
            true
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'dmifield',
                _('DMI Field')
            ) => $dmiSelector,
            self::makeLabel(
                $labelClass,
                'action',
                _('Action')
            ) => $actionSelector
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'CAPONE_GLOBAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'capone-global-form',
            self::makeTabUpdateURL(
                'capone-global',
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
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Update the global settings options.
     *
     * @return void
     */
    public function globalsettingsPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $dmifield = trim(
            filter_input(INPUT_POST, 'dmifield')
        );
        $action = trim(
            filter_input(INPUT_POST, 'action')
        );

        $serverFault = false;
        try {
            if (!$dmifield) {
                throw new Exception(_('A dmi field must be set!'));
            }
            if (!self::setSetting('FOG_PLUGIN_CAPONE_DMI', $dmifield)) {
                $serverFault = true;
                throw new Exception(_('Unable to set dmi field'));
            }
            if (!self::setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN', $action)) {
                $serverFault = true;
                throw new Exception(_('Unable to set action field'));
            }
            $hook = 'CAPONE_GLOBAL_EDIT_SUCCESS';
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Global settings updated!'),
                    'title' => _('Global Settings Update Success')
                ]
            );
        } catch (Exception $e) {
            $hook = 'CAPONE_GLOBAL_EDIT_FAIL';
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Global Settings Update Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Actually update the wol broadcast.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Capone',
            'CAPONE_EDIT',
            _('Capone updated!'),
            _('Capone Update Success'),
            _('Capone Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'capone-general':
                        $this->caponeGeneralPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Capone update failed!'));
                }
            }
        );
    }
}
