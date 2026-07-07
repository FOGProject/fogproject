<?php
/**
 * The wol broadcast page.
 *
 * PHP version 5
 *
 * @category WOLBroadcastManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The wol broadcast page.
 *
 * @category WOLBroadcastManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WOLBroadcastManagement extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'wolbroadcast';
    /**
     * Initializes the WOL Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('WOL Broadcast Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Broadcast Name'),
            _('Broadcast IP')
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
        $wolbroadcast = filter_input(INPUT_POST, 'wolbroadcast');
        $description = filter_input(INPUT_POST, 'description');
        $broadcast = filter_input(INPUT_POST, 'broadcast');

        $labelClass = 'col-sm-3 col-form-label';

        return [
            self::makeLabel(
                $labelClass,
                'wolbroadcast',
                _('Broadcast Name')
            ) => self::makeInput(
                'form-control wolbroadcastname-input',
                'wolbroadcast',
                _('Broadcast Name'),
                'text',
                'wolbroadcast',
                $wolbroadcast,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Broadcast Description')
            ) => self::makeTextarea(
                'form-control wolbroadcastdescription-input',
                'description',
                _('Broadcast Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'broadcast',
                _('Broadcast Address')
            ) => self::makeInput(
                'form-control wolbroadcastaddress-input',
                'broadcast',
                '192.168.1.255',
                'text',
                'broadcast',
                $broadcast,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            )
        ];
    }
    /**
     * Create new wol broadcast entry.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'wolbroadcast',
            _('Create New Broadcast'),
            'WOLBROADCAST_ADD_FIELDS',
            'WOLBroadcast'
        );
    }
    /**
     * Create new wol broadcast entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'wolbroadcast',
            'WOLBROADCAST_ADD_FIELDS',
            'WOLBroadcast'
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
            'WOLBroadcast',
            'WOLBROADCAST_ADD',
            _('Broadcast added!'),
            _('Broadcast Create Success'),
            _('Broadcast Create Fail'),
            function (&$serverFault) {
                $wolbroadcast = trim(
                    filter_input(INPUT_POST, 'wolbroadcast')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                $broadcast = trim(
                    filter_input(INPUT_POST, 'broadcast')
                );
                $exists = self::getClass('WOLBroadcastManager')
                    ->exists($wolbroadcast);
                if ($exists) {
                    throw new Exception(
                        _('A broadcast already exists with this name!')
                    );
                }
                $WOLBroadcast = self::getClass('WOLBroadcast')
                    ->set('name', $wolbroadcast)
                    ->set('description', $description)
                    ->set('broadcast', $broadcast);
                if (!$WOLBroadcast->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add broadcast failed!'));
                }
                return $WOLBroadcast;
            }
        );
    }
    /**
     * WOL General tab.
     *
     * @return void
     */
    public function wolbroadcastGeneral()
    {
        $wolbroadcast = (
            filter_input(INPUT_POST, 'wolbroadcast') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $broadcast = (
            filter_input(INPUT_POST, 'broadcast') ?:
            $this->obj->get('broadcast')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'wolbroadcast',
                _('Broadcast Name')
            ) => self::makeInput(
                'form-control wolbroadcastname-input',
                'wolbroadcast',
                _('Broadcast Name'),
                'text',
                'wolbroadcast',
                $wolbroadcast,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Broadcast Description')
            ) => self::makeTextarea(
                'form-control wolbroadcastdescription-input',
                'description',
                _('Broadcast Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'broadcast',
                _('Broadcast Address')
            ) => self::makeInput(
                'form-control wolbroadcastaddress-input',
                'broadcast',
                '192.168.1.255',
                'text',
                'broadcast',
                $broadcast,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
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
            'WOLBROADCAST_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'WOLBroadcast' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'wolbroadcast-general-form',
            self::makeTabUpdateURL(
                'wolbroadcast-general',
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
     * Updates the wolbroadcast general elements.
     *
     * @return void
     */
    public function wolbroadcastGeneralPost()
    {
        self::checkAuthAndCSRF();
        $wolbroadcast = trim(
            filter_input(INPUT_POST, 'wolbroadcast')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $broadcast = trim(
            filter_input(INPUT_POST, 'broadcast')
        );

        $exists = self::getClass('WOLBroadcastManager')
            ->exists($wolbroadcast);
        if ($wolbroadcast != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A broadcast already exists with this name!')
            );
        }

        $this->obj
            ->set('name', $wolbroadcast)
            ->set('description', $description)
            ->set('broadcast', $broadcast);
    }
    /**
     * Present the wol broadcast to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'wolbroadcast-general',
            'generator' => function () {
                $this->wolbroadcastGeneral();
            }
        ];
        $this->renderEditTabs($tabData);
    }
    /**
     * Actually update the wol broadcast.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'WOLBroadcast',
            'WOLBROADCAST_EDIT',
            _('Broadcast updated!'),
            _('Broadcast Update Success'),
            _('Broadcast Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'wolbroadcast-general':
                        $this->wolbroadcastGeneralPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Broadcast update failed!'));
                }
            }
        );
    }
}
