<?php
/**
 * Page presenter for pushbullet plugin
 *
 * PHP version 5
 *
 * @category PushbulletManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Page presenter for pushbullet plugin
 *
 * @category PushbulletManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletManagementPage extends FOGPage
{
    /**
     * The node name
     *
     * @var string
     */
    public $node = 'pushbullet';
    /**
     * The initializer for the page.
     *
     * @param string $name the name of the page
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Pushbullet Management';
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf(
                self::$foglang['ListAll'],
                _('Pushbullet Accounts')
            ),
            'add' => _('Link Pushbullet Account'),
        );
        global $id;
        if ($id) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Name'),
            _('Email'),
        );
        $this->templates = array(
            '<input type="checkbox" name="pushbullet[]" '
            . 'value="${id}" class="toggle-action"/>',
            '${name}',
            '${email}',
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $PushBullet the object to use
         *
         * @return void
         */
        self::$returnData = function (&$PushBullet) {
            $this->data[] = array(
                'name'    => $PushBullet->name,
                'email'   => $PushBullet->email,
                'id'      => $PushBullet->id,
            );
            unset($PushBullet);
        };
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->span,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('Link New Account');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $value = filter_input(
            INPUT_POST,
            'apiToken'
        );
        $fields = array(
            '<label for="apiToken">'
            . _('Access Token')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" '
            . 'name="apiToken" id="apiToken" value="'
            . $value
            . '"/>'
            . '</div>',
            '<label for="add">'
            . _('Add Pushbullet Account')
            . '</label>' => '<button type="submit" name="add" class="'
            . 'btn btn-info btn-block" id="add">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'PUSHBULLET_ADD',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually insert the new object
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $token = trim(
                filter_input(
                    INPUT_POST,
                    'apiToken'
                )
            );
            $PushExists = self::getClass('PushbulletManager')
                ->exists(
                    $token,
                    '',
                    'token'
                );
            if ($PushExists) {
                throw new Exception(
                    _('Account already linked')
                );
            }
            if (!$token) {
                throw new Exception(
                    _('Please enter an access token')
                );
            }
            $userInfo = self::getClass(
                'PushbulletHandler',
                $token
            )->getUserInformation();
            $Bullet = self::getClass('Pushbullet')
                ->set('token', $token)
                ->set('name', $userInfo->name)
                ->set('email', $userInfo->email);
            if (!$Bullet->save()) {
                throw new Exception(
                    _('Failed to create')
                );
            }
            self::getClass(
                'PushbulletHandler',
                $token
            )->pushNote(
                '',
                'FOG',
                'Account linked'
            );
            $msg = json_encode(
                array(
                    'msg' => _('Account successfully added!'),
                    'title' => _('Link Pushbullet Account Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Link Pushbullet Account Fail')
                )
            );
        }
        unset($Bullet);
        echo $msg;
        exit;
    }
}
