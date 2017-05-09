<?php
/**
 * The wol broadcast page.
 *
 * PHP version 5
 *
 * @category WOLBroadcastManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The wol broadcast page.
 *
 * @category WOLBroadcastManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WOLBroadcastManagementPage extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'wolbroadcast';
    /**
     * Initializes the WOL Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'WOL Broadcast Management';
        self::$foglang['ExportWolbroadcast'] = _('Export WOLBroadcasts');
        self::$foglang['ImportWolbroadcast'] = _('Import WOLBroadcasts');
        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                $this->linkformat => self::$foglang['General'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Broadcast Name') => $this->obj->get('name'),
                _('IP Address') => $this->obj->get('broadcast'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" checked/>',
            'Broadcast Name',
            'Broadcast IP',
        );
        $this->templates = array(
            '<input type="checkbox" name="wolbroadcast[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=wolbroadcast&sub=edit&id=${id}" title="'
            . _('Edit')
            . '">${name}</a>',
            '${wol_ip}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => '16'
            ),
            array('class' => 'l'),
            array('class' => 'r'),
        );
        self::$returnData = function (&$WOLBroadcast) {
            if (!$WOLBroadcast->isValid()) {
                return;
            }
            $this->data[] = array(
                'id'    => $WOLBroadcast->get('id'),
                'name'  => $WOLBroadcast->get('name'),
                'wol_ip' => $WOLBroadcast->get('broadcast'),
            );
            unset($WOLBroadcast);
        };
    }
    /**
     * Present page to create new WOL entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Broadcast Address');
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
            _('Broadcast Name') => '<input class="smaller" type='
            . '"text" name="name"/>',
            _('Broadcast IP') => '<input class="smaller" type='
            . '"text" name="broadcast"/>',
            '' => sprintf(
                '<input class="smaller" type="submit" value="%s" name="add"/>',
                _('Add')
            ),
        );
        foreach ((array)$fields as $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager
            ->processEvent(
                'BROADCAST_ADD',
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
     * Actually create the items.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = $_REQUEST['name'];
            $ip = $_REQUEST['broadcast'];
            if (self::getClass('WolbroadcastManager')->exists($name)) {
                throw new Exception(
                    _('Broacast name already Exists, please try again.')
                );
            }
            if (!$name) {
                throw new Exception(
                    _('Please enter a name for this address.')
                );
            }
            if (empty($ip)) {
                throw new Exception(
                    _('Please enter the broadcast address.')
                );
            }
            if (strlen($ip) > 15
                || !filter_var($ip, FILTER_VALIDATE_IP)
            ) {
                throw new Exception(
                    _('Please enter a valid ip')
                );
            }
            $WOLBroadcast = self::getClass('Wolbroadcast')
                ->set('name', $name)
                ->set('broadcast', $ip);
            if (!$WOLBroadcast->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Broadcast Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=wolbroadcast&sub=edit&id=%s',
                    $WOLBroadcast->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Edit the current item.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );
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
            _('Broadcast Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                $this->obj->get('name')
            ),
            _('Broadcast Address') => sprintf(
                '<input class="smaller" type="text" name="broadcast" value="%s"/>',
                $this->obj->get('broadcast')
            ),
            '&nbsp;' => sprintf(
                '<input class="smaller" type="submit" value="%s" name="update"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager
            ->processEvent(
                'BROADCAST_EDIT',
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
     * Submit the edits.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'BROADCAST_EDIT_POST',
                array('Broadcast'=> &$this->obj)
            );
        try {
            $name = $_REQUEST['name'];
            $ip = $_REQUEST['broadcast'];
            if (!$name) {
                throw new Exception(
                    _('You need to have a name for the broadcast address.')
                );
            }
            if (!$ip
                || !filter_var($ip, FILTER_VALIDATE_IP)
            ) {
                throw new Exception(
                    _('Please enter a valid IP address')
                );
            }
            if ($_REQUEST['name'] != $this->obj->get('name')
                && $this->obj->getManager()->exists($_REQUEST['name'])
            ) {
                throw new Exception(
                    _('A broadcast with that name already exists.')
                );
            }
            $this->obj
                ->set('broadcast', $ip)
                ->set('name', $name);
            if (!$this->obj->save()) {
                throw new Exception(_('Failed to update'));
            }
            self::setMessage(_('Broadcast Updated'));
            self::redirect($this->formAction);
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
