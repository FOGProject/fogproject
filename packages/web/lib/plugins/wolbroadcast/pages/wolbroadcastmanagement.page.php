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
        $this->name = 'WOL Broadcast Management';
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
     * Create new wol broadcast entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Broadcast');

        $wolbroadcast = filter_input(INPUT_POST, 'wolbroadcast');
        $description = filter_input(INPUT_POST, 'description');
        $broadcast = filter_input(INPUT_POST, 'broadcast');

        $labelClass = 'col-sm-3 control-label';

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
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'WOLBROADCAST_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'WOLBroadcast' => self::getClass('WOLBroadcast')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'wolbroadcast-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="wolbroadcast-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Broadcast');
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
     * Create new wol broadcast entry.
     *
     * @return void
     */
    public function addModal()
    {
        $wolbroadcast = filter_input(INPUT_POST, 'wolbroadcast');
        $description = filter_input(INPUT_POST, 'description');
        $broadcast = filter_input(INPUT_POST, 'broadcast');

        $labelClass = 'col-sm-3 control-label';

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

        self::$HookManager->processEvent(
            'WOLBROADCAST_ADD_FIELDS',
            [
                'fields' => &$fields,
                'WOLBroadcast' => self::getClass('WOLBroadcast')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=wolbroadcast&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually create the broadcast.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('WOLBROADCAST_ADD_POST');
        $wolbroadcast = trim(
            filter_input(INPUT_POST, 'wolbroadcast')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $broadcast = trim(
            filter_input(INPUT_POST, 'broadcast')
        );

        $serverFault = false;
        try {
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'WOLBROADCAST_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Broadcast added!'),
                    'title' => _('Broadcast Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'WOLBROADCAST_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Broadcast Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=wolbroadcast&sub=edit&id='
        //    $WOLBroadcast->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'WOLBroadcast' => &$WOLBroadcast,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($WOLBroadcast);
        echo $msg;
        exit;
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

        $labelClass = 'col-sm-3 control-label';

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
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
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
            'form-horizontal',
            'wolbroadcast-general-form',
            self::makeTabUpdateURL(
                'wolbroadcast-general',
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
     * Updates the wolbroadcast general elements.
     *
     * @return void
     */
    public function wolbroadcastGeneralPost()
    {
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
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'wolbroadcast-general',
            'generator' => function () {
                $this->wolbroadcastGeneral();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * Actually update the wol broadcast.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'WOLBROADCAST_EDIT_POST',
            ['WOLBroadcast' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'wolbroadcast-general':
                    $this->wolbroadcastGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Broadcast update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'WOLBROADCAST_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Broadcast updated!'),
                    'title' => _('Broadcast Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'WOLBROADCAST_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Broadcast Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'WOLBroadcast' => &$this->obj,
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
}
