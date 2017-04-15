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
        if ($_REQUEST['id']) {
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
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'r'),
        );
        self::$returnData = function (&$PushBullet) {
            if (!$PushBullet->isValid()) {
                return;
            }
            $this->data[] = array(
                'name'    => $PushBullet->get('name'),
                'email'   => $PushBullet->get('email'),
                'id'      => $PushBullet->get('id'),
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
        $this->title = _('Link New Account');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Access Token') => '<input class="smaller" type="text" '
            . 'name="apiToken" />',
            '' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        self::$HookManager->processEvent(
            'PUSHBULLET_ADD',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
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
            $token = trim($_REQUEST['apiToken']);
            $PushExists = self::getClass('PushbulletManager')
                ->exists(
                    $token,
                    0,
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
                throw new Exception(_('Failed to create'));
            }
            self::getClass(
                'PushbulletHandler',
                $token
            )->pushNote(
                '',
                'FOG',
                'Account linked'
            );
            self::setMessage(_('Account Added!'));
            self::redirect('?node=pushbullet&sub=list');
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
