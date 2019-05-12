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
    public $description = 'Add menu items to the management page';
    /**
     * The active flag (always true but for posterity
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
        self::$HookManager
            ->register(
                'GROUP_EDIT_EXTRA',
                array(
                    $this,
                    'groupFields'
                )
            )
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'groupSideMenu'
                )
            )
            ->register(
                'GROUP_EDIT_SUCCESS',
                array(
                    $this,
                    'groupAddLocation'
                )
            );
    }
    /**
     * The group side menu
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupSideMenu($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $link = $arguments['linkformat'];
        self::arrayInsertAfter(
            "$link#group-image",
            $arguments['submenu'],
            "$link#group-location",
            _('Location Association')
        );
    }
    /**
     * The group fields.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupFields($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        if ($node != 'group') {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Group']->get('hosts')
            ),
            'locationID'
        );
        $cnt = count($Locations);
        if ($cnt !== 1) {
            $locID = 0;
        } else {
            $locID = array_shift($Locations);
        }
        unset($Locations);
        unset($arguments['headerData']);
        $arguments['attributes'] = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $arguments['templates'] = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="location">'
            . _('Location')
            . '</label>' => self::getClass('LocationManager')->buildSelectBox(
                $locID
            ),
            '<label for="updateloc">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" class="btn btn-info btn-block" '
            . 'id="group-edit">'
            . _('Update')
            . '</button>'
        );
        $arguments['data'] = array();
        foreach ((array)$fields as $field => &$input) {
            $arguments['data'][] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        unset($fields);
        echo '<!-- Location -->';
        echo '<div id="group-location" class="tab-pane fade">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Location Association');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $arguments['formAction']
            . '&tab=group-location">';
        $arguments['render']->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * The group location selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocation($arguments)
    {
        global $node;
        global $tab;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        if ($tab != 'group-location') {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Group']->get('hosts')
            )
        );
        $location = (int)filter_input(INPUT_POST, 'location');
        if ($location) {
            $insert_fields = array(
                'locationID',
                'hostID'
            );
            $insert_values = array();
            foreach ((array)$arguments['Group']->get('hosts') as &$hostID) {
                $insert_values[] = array(
                    $location,
                    $hostID
                );
                unset($hostID);
            }
            if (count($insert_values) > 0) {
                self::getClass('LocationAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
    }
}
