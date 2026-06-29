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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $apiToken = filter_input(INPUT_POST, 'apiToken');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'pushbullet',
            _('Link Pushbullet Account'),
            'PUSHBULLET_ADD_FIELDS',
            'Pushbullet'
        );
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'pushbullet',
            'PUSHBULLET_ADD_FIELDS',
            'Pushbullet'
        );
    }
    /**
     * Actually insert the new object
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Pushbullet',
            'PUSHBULLET_ADD',
            _('Account successfully added!'),
            _('Link Pushbullet Account Success'),
            _('Link Pushbullet Account Fail'),
            function (&$serverFault) {
                $token = trim(
                    filter_input(INPUT_POST, 'apiToken')
                );
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
                return $Pushbullet;
            }
        );
    }
}
