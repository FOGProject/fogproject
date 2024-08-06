<?php
/**
 * Adds the ou choice to groups.
 *
 * PHP version 5
 *
 * @category AddOUGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the OU choice to groups.
 *
 * @category AddOUGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddOUGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddOUGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add OU to Groups';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'ou';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'PLUGINS_INJECT_TABDATA',
            [$this, 'groupTabData']
        )->register(
            'GROUP_EDIT_SUCCESS',
            [$this, 'groupAddOUEdit']
        )->register(
            'GROUP_ADD_FIELDS',
            [$this, 'groupAddOUField']
        );
    }
    /**
     * The group tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupTabData($arguments)
    {
        global $node;
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['obj'];

        $arguments['pluginsTabData'][] = [
            'name' => _('OU Association'),
            'id' => 'group-ou',
            'generator' => function () use ($obj) {
                $this->groupOU($obj);
            }
        ];
    }
    /**
     * The group ou display
     *
     * @param object $obj The group object we're working with.
     *
     * @return void
     */
    public function groupOU($obj)
    {
        $ouID = (int)filter_input(INPUT_POST, 'ou');
        // Group OUs
        $ouSelector = self::getClass('OUManager')
            ->buildSelectBox($ouID, 'ou');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'ou',
                _('Group OU')
            ) => $ouSelector
        ];

        $buttons = FOGPage::makeButton(
            'ou-send',
            _('Update'),
            'btn btn-primary pull-left'
        );

        self::$HookManager->processEvent(
            'GROUP_OU_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => &$obj
            ]
        );
        $rendered = FOGPage::formFields($fields);
        unset($fields);

        echo FOGPage::makeFormTag(
            'form-horizontal',
            'group-ou-form',
            FOGPage::makeTabUpdateURL(
                'group-ou',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('OU');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The OU updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function groupOUPost($obj)
    {
        $ouID = trim(
            (int)filter_input(INPUT_POST, 'ou')
        );
        $insert_fields = ['hostID', 'ouID'];
        $insert_values = [];
        $hosts = $obj->get('hosts');
        if (count($hosts ?: [])) {
            Route::deletemass(
                'ouassociation',
                ['hostID' => $hosts]
            );
            foreach ((array)$hosts as $ind => &$hostID) {
                $insert_values[] = [$hostID, $ouID];
                unset($hostID);
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('OUAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The group ou selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddOUEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['Group'];
        try {
            switch ($tab) {
                case 'group-ou':
                    $this->groupOUPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'GROUP_EDIT_OU_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Group OU Updated!'),
                    'title' => _('Group OU Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'GROUP_EDIT_OU_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update OU Fail')
                ]
            );
        }
    }
    /**
     * The group OU field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddOUField($arguments)
    {
        global $node;
        if ($node != 'group') {
            return;
        }
        $ouID = (int)filter_input(INPUT_POST, 'ou');
        $ouSelector = self::getClass('OUManager')
            ->buildSelectBox($ouID, 'ou');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'ou',
                _('Group OU')
            )
        ] = $ouSelector;
    }
}
