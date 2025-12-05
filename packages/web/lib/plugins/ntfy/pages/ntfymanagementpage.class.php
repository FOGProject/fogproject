<?php
/**
 * Page presenter for Ntfy plugin
 *
 * PHP version 5
 *
 * @category NtfyManagementPage
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Page presenter for Ntfy plugin
 *
 * @category NtfyManagementPage
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NtfyManagementPage extends FOGPage
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
        $this->name = _('ntfy Management');
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf(
                self::$foglang['ListAll'],
                _('ntfy Accounts')
            ),
            'add' => _('Link ntfy Account'),
        );
        global $id;
        if ($id) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Server URL'),
            _('Topic Endpoint'),
        );
        $this->templates = array(
            '<input type="checkbox" name="ntfy[]" '
            . 'value="${id}" class="toggle-action"/>',
            '${serverURL}',
            '${topicEndpoint}',
        );
        $this->attributes = array(
            array(
                'class' => 'parser-false filter-false',
                'width' => 16
            ),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $ntfy the object to use
         *
         * @return void
         */
        self::$returnData = function (&$ntfy) {
            $this->data[] = array(
                'serverURL'    => $ntfy->serverURL,
                'topicEndpoint'   => $ntfy->topicEndpoint,
                'id'      => $ntfy->id,
            );
            unset($ntfy);
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

        $this->title = _('Add Pub/Sub Topic');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $server_value = filter_input(
            INPUT_POST,
            'serverURL',
            FILTER_DEFAULT,
            array(
                'options' => array(
                    'default' => 'https://ntfy.sh'
                )
            )
        );
        $topic_endpoint = filter_input(
            INPUT_POST,
            'topicEndpoint'
        );
        $fields = array(
            '<label for="serverURL">'
            . _('Server URL')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" '
            . 'name="serverURL" id="serverURL" value="'
            . $server_value
            . '" required/>'
            . '</div>',
            '<label for="topicEndpoint">'
            . _('Topic Endpoint')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" '
            . 'name="topicEndpoint" id="topicEndpoint" value="'
            . $topic_endpoint
            . '" required/>'
            . '</div>',
            '<label for="add">'
            . _('Add Ntfy Topic')
            . '</label>' => '<button type="submit" name="add" class="'
            . 'btn btn-info btn-block" id="add">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'NTFY_ADD',
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
     * Actually performs the post to ntfy
     * Altnerative version of addPost, testing a modified copy from slackmanagement
     * 
     * @return void
     */
    public function addPost()
    {
        try {
            $server_url = trim(
                filter_input(
                    INPUT_POST,
                    'serverURL'
                )
            );
            $topic_endpoint = trim(
                filter_input(
                    INPUT_POST,
                    'topicEndpoint'
                )
            );
            // $usertype = preg_match(
            //     '/^[@]/',
            //     $user
            // );
            // $channeltype = preg_match(
            //     '/^[#]/',
            //     $user
            // );
            // if (!$usertype && !$channeltype) {
            //     throw new Exception(
            //         sprintf(
            //             '%s @ %s # %s!',
            //             _('Must use an'),
            //             _('or'),
            //             _('to signify if this is a user or channel to send to')
            //         )
            //     );
            // }
            // if (!$token) {
            //     throw new Exception(
            //         _('Please enter an access token')
            //     );
            // }
            $ntfy_topic = self::getClass('Ntfy')
                ->set('serverURL', $server_url)
                ->set('topicEndpoint', $topic_endpoint);

            $conditions = array(
                'serverURL' => $server_url,
                'topicEndpoint' => $topic_endpoint
            );
            $existing = self::getClass('NtfyManager')
                ->find($conditions);

            if($existing) {
                throw new Exception(
                    _('Account already linked')
                );
            }
            if (!$ntfy_topic->save()) {
                throw new Exception(
                    _('Failed to create')
                );
            }

            self::getClass(
                'NtfyHandler',
                $server_url,
                $topic_endpoint
            )->pushNote(
                '',
                'FOG',
                'Account linked'
            );
            $msg = json_encode(
                array(
                    'msg' => _('Account successfully added!'),
                    'title' => _('Link Ntfy Account Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Link Ntfy Account Fail')
                )
            );
        }
        unset($Slack);
        echo $msg;
        exit;
    }
}