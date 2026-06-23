<?php
/**
 * OU management page.
 *
 * PHP version 5
 *
 * @category OUManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * OU management page.
 *
 * @category OUManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OUManagement extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'ou';
    /**
     * Initializes the OU management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'OU Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('OU Name'),
            _('OU DN')
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
    private function _addFields()
    {
        $ou = filter_input(INPUT_POST, 'ou');
        $description = filter_input(INPUT_POST, 'description');
        $oudn = filter_input(INPUT_POST, 'oudn');

        $labelClass = 'col-sm-3 control-label';

        return [
            self::makeLabel(
                $labelClass,
                'ou',
                _('OU Name')
            ) => self::makeInput(
                'form-control ouname-input',
                'ou',
                _('OU Name'),
                'text',
                'ou',
                $ou,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('OU Description')
            ) => self::makeTextarea(
                'form-control oudescription-input',
                'description',
                _('OU Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'oudn',
                _('OU DN')
            ) => self::makeInput(
                'form-control oudn-input',
                'oudn',
                'ou=computers,dc=example,dc=com',
                'text',
                'oudn',
                $oudn,
                true
            )
        ];
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'ou',
            _('Create New OU'),
            'OU_ADD_FIELDS',
            'OU'
        );
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'ou',
            'OU_ADD_FIELDS',
            'OU'
        );
    }
    /**
     * Actually create the ou.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('OU_ADD_POST');
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $oudn = trim(
            filter_input(INPUT_POST, 'oudn')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('OUManager')
                ->exists($ou);
            if ($exists) {
                throw new Exception(
                    _('An ou already exists with this name!')
                );
            }
            $OU = self::getClass('OU')
                ->set('name', $ou)
                ->set('description', $description)
                ->set('ou', $oudn);
            if (!$OU->save()) {
                $serverFault = false;
                throw new Exception(_('Add ou failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'OU_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('OU added!'),
                    'title' => _('OU Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'OU_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('OU Create Fail')
                ]
            );
        }
        // header(
        //     'Location: ../management/index.php?node=ou&sub=edit&id='
        //     . $OU->get('id')
        // );
        $this->jsonHookResponse(
            [
                'OU' => &$OU,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Displays the ou general tab.
     *
     * @return void
     */
    public function ouGeneral()
    {
        $ou = (
            filter_input(INPUT_POST, 'ou') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $oudn = (
            filter_input(INPUT_POST, 'oudn') ?:
            $this->obj->get('ou')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ou',
                _('OU Name')
            ) => self::makeInput(
                'form-control ouname-input',
                'ou',
                _('OU Name'),
                'text',
                'ou',
                $ou,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('OU Description')
            ) => self::makeTextarea(
                'form-control oudescription-input',
                'description',
                _('OU Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'oudn',
                _('OU DN')
            ) => self::makeInput(
                'form-control oudn-input',
                'oudn',
                'ou=computers,dc=example,dc=com',
                'text',
                'oudn',
                $oudn,
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
            'OU_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'OU' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ou-general-form',
            self::makeTabUpdateURL(
                'ou-general',
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
     * Actually update the general information.
     *
     * @return void
     */
    public function ouGeneralPost()
    {
        self::checkAuthAndCSRF();
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $oudn = trim(
            filter_input(INPUT_POST, 'oudn')
        );

        $exists = self::getClass('OUManager')
            ->exists($ou);
        if ($ou != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('An OU already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $ou)
            ->set('description', $description)
            ->set('ou', $oudn);
    }
    /**
     * Present the host membership tab.
     *
     * @return void
     */
    public function ouHosts()
    {
        $this->renderAssocTab(
            'ou-host',
            _('OU Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update host membership.
     *
     * @return void
     */
    public function ouHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Present the ou to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s %s: %s',
            _('Edit'),
            $this->obj->get('name'),
            _('ID'),
            $this->obj->get('id')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'ou-general',
            'generator' => function () {
                $this->ouGeneral();
            }
        ];

        // Hosts
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Association'),
                        'id' => 'ou-host',
                        'generator' => function () {
                            $this->ouHosts();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually update the ou.
     *
     * @return void
     */
    public function editPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'OU_EDIT_POST',
            ['OU' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'ou-general':
                    $this->ouGeneralPost();
                    break;
                case 'ou-host':
                    $this->ouHostPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('OU update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'OU_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('OU updated!'),
                    'title' => _('OU Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'OU_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('OU Update Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'OU' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * OU -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        return $this->assocItemsList(
            'host',
            'ouassociation',
            'ouAssoc',
            '`hosts`.`hostID`',
            '`ouAssoc`.`oaHostID`',
            '`ouAssoc`.`oaOUID`',
            [
                [
                    'db' => 'ouAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
