<?php
/**
 * Adds the location choice to groups.
 *
 * PHP version 5
 *
 * @category AddLocationGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the location choice to groups.
 *
 * @category AddLocationGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLocationGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Location to Groups';
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
    public $node = 'location';
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
            [$this, 'groupAddLocationEdit']
        )->register(
            'GROUP_ADD_FIELDS',
            [$this, 'groupAddLocationField']
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
            'name' => _('Location Association'),
            'id' => 'group-location',
            'generator' => function () use ($obj) {
                $this->groupLocation($obj);
            }
        ];
    }
    /**
     * The group location display
     *
     * @param object $obj The group object we're working with.
     *
     * @return void
     */
    public function groupLocation($obj)
    {
        $locationID = (int)filter_input(INPUT_POST, 'location');
        // Group Locations
        $locationSelector = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'location',
                _('Group Location')
            ) => $locationSelector
        ];

        $buttons = FOGPage::makeButton(
            'location-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'GROUP_LOCATION_FIELDS',
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
            'group-location-form',
            FOGPage::makeTabUpdateURL(
                'group-location',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Location');
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
     * The location updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function groupLocationPost($obj)
    {
        $locationID = trim(
            (int)filter_input(INPUT_POST, 'location')
        );
        $insert_fields = ['hostID', 'locationID'];
        $insert_values = [];
        Route::ids(
            'groupassociation',
            ['groupID' => $obj->get('id')],
            'hostID'
        );
        $hosts = json_decode(Route::getData(), true);
        if (count($hosts ?: [])) {
            Route::deletemass(
                'locationassociation',
                ['hostID' => $hosts]
            );
            if (self::getClass('Location', $locationID)->isValid()) {
                foreach ((array)$hosts as $ind => &$hostID) {
                    $insert_values[] = [$hostID, $locationID];
                    unset($hostID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('LocationAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The group location selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocationEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['Group'];
        try {
            switch ($tab) {
                case 'group-location':
                    $this->groupLocationPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'GROUP_EDIT_LOCATION_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Group Location Updated!'),
                    'title' => _('Group Location Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'GROUP_EDIT_LOCATION_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update Location Fail')
                ]
            );
        }
    }
    /**
     * The group location field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocationField($arguments)
    {
        global $node;
        if ($node != 'group') {
            return;
        }
        $locationID = (int)filter_input(INPUT_POST, 'location');
        $locationSelector = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'location',
                _('Group Location')
            )
        ] = $locationSelector;
    }
}
