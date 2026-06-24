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
    protected function _addFields()
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
        $this->renderAddForm(
            'ntfy',
            _('Link ntfy Topic'),
            'NTFY_ADD_FIELDS',
            'Ntfy'
        );
    }
    /**
     * Presents the modal form for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'ntfy',
            'NTFY_ADD_FIELDS',
            'Ntfy'
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
            'Ntfy',
            'NTFY_ADD',
            _('Topic successfully added!'),
            _('Link ntfy Topic Success'),
            _('Link ntfy Topic Fail'),
            function (&$serverFault) {
                $serverURL = trim(
                    filter_input(INPUT_POST, 'serverURL')
                );
                $topicEndpoint = trim(
                    filter_input(INPUT_POST, 'topicEndpoint')
                );
                $credentials = (string)filter_input(INPUT_POST, 'credentials');
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
                return $Ntfy;
            }
        );
    }
}
