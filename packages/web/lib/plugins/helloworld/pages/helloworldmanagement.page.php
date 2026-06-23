<?php
/**
 * Hello World example plugin (management page).
 *
 * A page extends FOGPage and renders the UI plus handles its POSTs. The
 * routing is ?node=helloworld&sub=<method>, e.g. sub=add -> add(),
 * sub=addPost -> addPost(), sub=edit -> edit(), sub=list -> the inherited
 * DataTables JSON list (driven by $headerData/$attributes + the JS list file).
 *
 * Conventions used here:
 *  - self::makeLabel / makeInput / makeTextarea / makeButton / makeFormTag /
 *    formFields build form markup.
 *  - addPost()/editPost() return JSON and ALWAYS run self::checkAuthAndCSRF().
 *  - user output goes through Initiator::e(); reads use filter_input().
 *
 * PHP version 5
 *
 * @category HelloWorldManagement
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hello World example plugin (management page).
 *
 * @category HelloWorldManagement
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HelloWorldManagement extends FOGPage
{
    /**
     * The node this page operates on (matches the plugin machine name).
     *
     * @var string
     */
    public $node = 'helloworld';
    /**
     * Initialize the page and define the list table columns.
     *
     * $headerData are the column headers; the matching column data keys live
     * in js/fog.helloworld.list.js (here: 'mainlink' and 'description').
     *
     * @param string $name The page name.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Hello World Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Description'),
        ];
        $this->attributes = [
            [],
            [],
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    private function _addFields()
    {
        $name = filter_input(INPUT_POST, 'name');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        return [
            self::makeLabel(
                $labelClass,
                'name',
                _('Name')
            ) => self::makeInput(
                'form-control helloworldname-input',
                'name',
                _('Name'),
                'text',
                'name',
                $name,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Description')
            ) => self::makeTextarea(
                'form-control helloworlddescription-input',
                'description',
                _('Description'),
                'description',
                $description
            ),
        ];
    }
    /**
     * The standalone "create" page (sub=add).
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'helloworld',
            _('Create New Hello World'),
            'HELLOWORLD_ADD_FIELDS',
            'HelloWorld'
        );
    }
    /**
     * The "create" form rendered inside the list page modal.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'helloworld',
            'HELLOWORLD_ADD_FIELDS',
            'HelloWorld'
        );
    }
    /**
     * Persist a new entry. Returns JSON.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'HelloWorld',
            'HELLOWORLD_ADD',
            _('Hello World added!'),
            _('Hello World Create Success'),
            _('Hello World Create Fail'),
            function (&$serverFault) {
                $name = trim(
                    filter_input(INPUT_POST, 'name')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                if (empty($name)) {
                    throw new Exception(_('Please enter a name'));
                }
                $exists = self::getClass('HelloWorldManager')
                    ->exists($name);
                if ($exists) {
                    throw new Exception(
                        _('An entry already exists with this name!')
                    );
                }
                $HelloWorld = self::getClass('HelloWorld')
                    ->set('name', $name)
                    ->set('description', $description);
                if (!$HelloWorld->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add Hello World failed!'));
                }
                return $HelloWorld;
            }
        );
    }
    /**
     * The "General" tab body shown on the edit page.
     *
     * @return void
     */
    public function helloworldGeneral()
    {
        $name = (
            filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'name',
                _('Name')
            ) => self::makeInput(
                'form-control helloworldname-input',
                'name',
                _('Name'),
                'text',
                'name',
                $name,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Description')
            ) => self::makeTextarea(
                'form-control helloworlddescription-input',
                'description',
                _('Description'),
                'description',
                $description
            ),
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
            'HELLOWORLD_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'HelloWorld' => $this->obj,
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'helloworld-general-form',
            self::makeTabUpdateURL(
                'helloworld-general',
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
     * Apply the General tab edits to $this->obj (saved by editPost()).
     *
     * @return void
     */
    public function helloworldGeneralPost()
    {
        self::checkAuthAndCSRF();
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        if (empty($name)) {
            throw new Exception(_('Please enter a name'));
        }
        $exists = self::getClass('HelloWorldManager')
            ->exists($name);
        if ($name != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('An entry already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $name)
            ->set('description', $description);
    }
    /**
     * The edit page (sub=edit) -- renders tabs.
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
            'id' => 'helloworld-general',
            'generator' => function () {
                $this->helloworldGeneral();
            },
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Persist edits. Returns JSON.
     *
     * @return void
     */
    public function editPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'HELLOWORLD_EDIT_POST',
            ['HelloWorld' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'helloworld-general':
                    $this->helloworldGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Hello World update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'HELLOWORLD_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Hello World updated!'),
                    'title' => _('Hello World Update Success'),
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'HELLOWORLD_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Hello World Update Fail'),
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'HelloWorld' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault,
            ],
            $hook
        );
    }
}
