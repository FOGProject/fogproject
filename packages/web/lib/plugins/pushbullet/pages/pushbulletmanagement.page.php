<?php
/**
 * Page presenter for pushbullet plugin
 *
 * PHP version 5
 *
 * @category PushbulletManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Page presenter for pushbullet plugin
 *
 * @category PushbulletManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletManagement extends FOGPage
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
        $this->headerData = [
            _('Name'),
            _('Email'),
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Link Pushbullet Account');
        $apiToken = filter_input(INPUT_POST, 'apiToken');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'apiToken',
                _('Access token')
            ) => self::makeInput(
                'form-control pushbullettoken-input',
                'apiToken',
                _('Pushbullet Token'),
                'text',
                'apiToken',
                $apiToken,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'PUSHBULLET_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Pushbullet' => self::getClass('Pushbullet')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'pushbullet-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="pushbullet-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-borader">';
        echo '<h4 class="box-title">';
        echo _('Link Pushbullet Account');
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
     * Presents for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $apiToken = filter_input(INPUT_POST, 'apiToken');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'apiToken',
                _('Access token')
            ) => self::makeInput(
                'form-control pushbullettoken-input',
                'apiToken',
                _('Pushbullet Token'),
                'text',
                'apiToken',
                $apiToken,
                true
            )
        ];

        self::$HookManager->processEvent(
            'PUSHBULLET_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Pushbullet' => self::getClass('Pushbullet')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=pushbullet&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually insert the new object
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('PUSHBULLET_ADD_POST');
        $token = trim(
            filter_input(INPUT_POST, 'apiToken')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('PushbulletManager')
                ->exists($token, '', 'token');
            if ($exists) {
                throw new Exception(_('Account already linked'));
            }
            $userInfo = self::getClass(
                'PushbulletHandler',
                $token
            )->getUserInformation();
            $Pushbullet = self::getClass('Pushbullet')
                ->set('token', $token)
                ->set('name', $userInfo->name)
                ->set('email', $userInfo->email);
            if (!$Pushbullet->save()) {
                $serverFault = true;
                throw new Exception(_('Add pushbullet account failed!'));
            }
            $userInfo->pushNote(
                '',
                'FOG',
                'Account linked'
            );
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'PUSHBULLET_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Account successfully added!'),
                    'title' => _('Link Pushbullet Account Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PUSHBULLET_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Link Pushbullet Account Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Pushbullet' => &$Pushbullet,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Pushbullet);
        echo $msg;
        exit;
    }
}
