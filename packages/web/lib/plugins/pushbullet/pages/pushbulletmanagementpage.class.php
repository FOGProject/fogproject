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
        $this->headerData = [
            _('Name'),
            _('Email'),
        ];
        $this->templates = [
            '',
            ''
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
        $apiToken = filter_input(
            INPUT_POST,
            'apiToken'
        );
        $labelClass = 'col-sm-2 control-label';
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
        echo self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
