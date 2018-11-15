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
                "$this->linkformat#wol-general" => self::$foglang['General'],
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
            _('Broadcast Name'),
            _('Broadcast IP')
        );
        $this->templates = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="wolbroadcast[]" value='
            . '"${id}" class="toggle-action" checked/>'
            . '</label>',
            '<a href="?node=wolbroadcast&sub=edit&id=${id}" title="'
            . _('Edit')
            . '">${name}</a>',
            '${wol_ip}'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => '16'
            ),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $WOLBroadcast the object to use
         *
         * @return void
         */
        self::$returnData = function (&$WOLBroadcast) {
            $this->data[] = array(
                'id'    => $WOLBroadcast->id,
                'name'  => $WOLBroadcast->name,
                'wol_ip' => $WOLBroadcast->broadcast,
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
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $broadcast = trim(
            filter_input(INPUT_POST, 'broadcast')
        );
        $this->title = _('New Broadcast Address');
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="name">'
            . _('Broadcast Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control wolinput-name" type='
            . '"text" name="name" id="name" required value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="broadcast">'
            . _('Broadcast IP')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control wolinput-ip" type='
            . '"text" name="broadcast" id="broadcast" required value="'
            . $broadcast
            . '"/>',
            '<label for="add">'
            . _('Create WOL Broadcast?')
            . '</label>' => '<button class="btn btn-info btn-block" name="'
            . 'add" id="add" type="submit">'
            . _('Create')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
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
        unset($fields);
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
     * Actually create the items.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('BROADCAST_ADD_POST');
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $broadcast = trim(
            filter_input(INPUT_POST, 'broadcast')
        );
        try {
            if (!$name) {
                throw new Exception(
                    _('A name is required!')
                );
            }
            if (self::getClass('WolbroadcastManager')->exists($name)) {
                throw new Exception(
                    _('A broadcast already exists with this name!')
                );
            }
            if (!$broadcast) {
                throw new Exception(
                    _('A broadcast address is required')
                );
            }
            if (!filter_var($broadcast, FILTER_VALIDATE_IP)) {
                throw new Exception(
                    _('Please enter a valid ip')
                );
            }
            $WOLBroadcast = self::getClass('Wolbroadcast')
                ->set('name', $name)
                ->set('broadcast', $broadcast);
            if (!$WOLBroadcast->save()) {
                throw new Exception(_('Add broadcast failed!'));
            }
            $hook = 'BROADCAST_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Broadcast added!'),
                    'title' => _('Broadcast Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'BROADCAST_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Broadcast Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('WOLBroadcast' => &$WOLBroadcast)
            );
        unset($WOLBroadcast);
        echo $msg;
        exit;
    }
    /**
     * WOL General tab.
     *
     * @return void
     */
    public function wolGeneral()
    {
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $broadcast = filter_input(INPUT_POST, 'broadcast') ?:
            $this->obj->get('broadcast');
        $this->title = _('WOL Broadcast General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="name">'
            . _('Broadcast Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control wolinput-name" type='
            . '"text" name="name" id="name" required value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="broadcast">'
            . _('Broadcast IP')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control wolinput-ip" type='
            . '"text" name="broadcast" id="broadcast" required value="'
            . $broadcast
            . '"/>',
            '<label for="updategen">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" name="'
            . 'updategen" id="updategen" type="submit">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'BROADCAST_EDIT',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes,
                    'templates' => &$this->templates
                )
            );
        unset($fields);
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="wol-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=wol-general">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Edit the current item.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->wolGeneral();
        echo '</div>';
    }
    /**
     * WOL General Post()
     *
     * @return void
     */
    public function wolGeneralPost()
    {
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $broadcast = trim(
            filter_input(INPUT_POST, 'broadcast')
        );
        if ($this->obj->get('name') != $name
            && self::getClass('WOLBroadcastManager')->exists(
                $name,
                $this->obj->get('id')
            )
        ) {
            throw new Exception(
                _('A broadcast already exists with this name')
            );
        }
        $this->obj
            ->set('name', $name)
            ->set('broadcast', $broadcast);
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
        global $tab;
        try {
            switch ($tab) {
            case 'wol-general':
                $this->wolGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Broadcast update failed!'));
            }
            $hook = 'BROADCAST_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Broadcast updated!'),
                    'title' => _('Broadcast Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'BROADCAST_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Broadcast Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('WOLBroadcast' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
