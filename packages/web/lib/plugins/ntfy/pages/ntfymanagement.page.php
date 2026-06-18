<?php
/**
 * Page presenter for ntfy plugin
 *
 * PHP version 5
 *
 * @category NtfyManagement
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Page presenter for ntfy plugin
 *
 * @category NtfyManagement
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NtfyManagement extends FOGPage
{
    /**
     * The node name
     *
     * @var string
     */
    public $node = 'ntfy';
    /**
     * The initializer for the page.
     *
     * @param string $name the name of the page
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'ntfy Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Server URL'),
            _('Topic Endpoint'),
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Builds the add/link form fields.
     *
     * @return array
     */
    private function _addFields()
    {
        $serverURL = filter_input(
            INPUT_POST,
            'serverURL',
            FILTER_DEFAULT,
            ['options' => ['default' => 'https://ntfy.sh']]
        );
        $topicEndpoint = filter_input(INPUT_POST, 'topicEndpoint');

        $labelClass = 'col-sm-3 control-label';

        return [
            self::makeLabel(
                $labelClass,
                'serverURL',
                _('Server URL')
            ) => self::makeInput(
                'form-control ntfyserver-input',
                'serverURL',
                _('Server URL'),
                'text',
                'serverURL',
                $serverURL,
                true
            ),
            self::makeLabel(
                $labelClass,
                'topicEndpoint',
                _('Topic Endpoint')
            ) => self::makeInput(
                'form-control ntfytopic-input',
                'topicEndpoint',
                _('Topic name you choose, e.g. fog-alerts'),
                'text',
                'topicEndpoint',
                $topicEndpoint,
                true
            ),
            self::makeLabel(
                $labelClass,
                'credentials',
                _('Credentials')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control ntfycredentials-input',
                'credentials',
                _('Token or user:pass (optional)'),
                'password',
                'credentials',
                '',
                false
            )
            . '</div>',
        ];
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Link ntfy Topic');

        $fields = $this->_addFields();

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'NTFY_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Ntfy' => self::getClass('Ntfy')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ntfy-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="ntfy-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-borader">';
        echo '<h4 class="box-title">';
        echo _('Link ntfy Topic');
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
     * Presents the modal form for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $fields = $this->_addFields();

        self::$HookManager->processEvent(
            'NTFY_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Ntfy' => self::getClass('Ntfy')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=ntfy&sub=add',
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
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('NTFY_ADD_POST');

        $serverURL = trim(
            filter_input(INPUT_POST, 'serverURL')
        );
        $topicEndpoint = trim(
            filter_input(INPUT_POST, 'topicEndpoint')
        );
        $credentials = (string)filter_input(INPUT_POST, 'credentials');

        $serverFault = false;
        try {
            if (!$serverURL || !$topicEndpoint) {
                throw new Exception(
                    _('A server URL and topic endpoint are required')
                );
            }
            $existing = self::getClass('NtfyManager')
                ->exists($topicEndpoint, 0, 'topicEndpoint');
            if ($existing) {
                throw new Exception(_('Topic already linked'));
            }
            $Ntfy = self::getClass('Ntfy')
                ->set('serverURL', $serverURL)
                ->set('topicEndpoint', $topicEndpoint)
                ->set('credentials', $credentials);
            if (!$Ntfy->save()) {
                $serverFault = true;
                throw new Exception(_('Add ntfy topic failed!'));
            }
            self::getClass(
                'NtfyHandler',
                $serverURL,
                $topicEndpoint,
                $credentials
            )->pushNote(
                'FOG',
                _('Topic linked')
            );
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'NTFY_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Topic successfully added!'),
                    'title' => _('Link ntfy Topic Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'NTFY_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Link ntfy Topic Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Ntfy' => &$Ntfy,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Ntfy);
        echo $msg;
        exit;
    }
}
