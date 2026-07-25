<?php
/**
 * Associates directory groups from the role and user group pages.
 *
 * PHP version 5
 *
 * @category AddLDAPGroupTabs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associates directory groups from the role and user group pages.
 *
 * The LDAP Group page owns the association, but "which directory groups
 * feed this?" is asked while looking at the role or the user group, and
 * the answer is only half useful if it cannot be changed there. Every
 * other association tab in FOG edits from both ends -- both ends write
 * the same table, so there is no second source of truth to drift.
 *
 * The mechanics differ from a core association tab in two places, both
 * because a plugin cannot add methods to a core page class:
 *
 *  - the datatable is served from this plugin's own node, passed through
 *    the registerAssociationTab 'url' option;
 *  - the add/remove POST is picked up from {NODE}_EDIT_SUCCESS, the same
 *    hook the site plugin uses for its User Group site tab. The core
 *    page's own tab switch ignores an unknown tab, so the POST reaches
 *    here having changed nothing.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category AddLDAPGroupTabs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLDAPGroupTabs extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLDAPGroupTabs';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Associate directory groups from a role or user group';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact upon.
     *
     * @var string
     */
    public $node = 'ldap';
    /**
     * The nodes this tab is offered on, mapped to the pieces that differ.
     *
     * 'list' is the sub on the plugin's own page that feeds the table and
     * 'owner' the class the association hangs off.
     *
     * @var array
     */
    const TARGETS = [
        'role' => [
            'owner' => 'LDAPGroup',
            'list' => 'getRoleFeedList',
            'add' => 'addRole',
            'remove' => 'removeRole'
        ],
        'usergroup' => [
            'owner' => 'LDAPGroup',
            'list' => 'getUserGroupFeedList',
            'add' => 'addUserGroup',
            'remove' => 'removeUserGroup'
        ]
    ];
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['PLUGINS_INJECT_TABDATA', 'injectTabData'],
            ['ROLE_EDIT_SUCCESS', 'editSuccess'],
            ['USERGROUP_EDIT_SUCCESS', 'editSuccess'],
        ]);
    }
    /**
     * Adds the tab to the role and user group edit pages.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function injectTabData($arguments)
    {
        global $node;
        if (!array_key_exists($node, self::TARGETS)) {
            return;
        }
        // The tab is a second door onto an ldapgroup association, so it
        // answers to the ldapgroup permissions and not merely to the
        // role/usergroup edit right that got the admin onto this page.
        // Without this, role.edit alone would be enough to rewrite what a
        // directory group grants -- a right the LDAP Group page itself
        // does not hand out.
        if (!Authorization::can('ldapgroup.view')) {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['pluginsTabData'][] = [
            'name' => _('LDAP Groups'),
            'id' => $node . '-ldapgroup',
            'generator' => function () use ($obj, $node) {
                $this->renderTab($obj, $node);
            }
        ];
    }
    /**
     * Renders the association tab.
     *
     * The ids and markup mirror FOGPage::renderAssocTab() because the
     * shared association JS keys off them, but the table is emitted here
     * rather than delegated. Neither route into FOGPage works:
     * renderAssocTab() reads the owner from the page's own $this->obj,
     * and borrowing a page instance to call render() is worse than it
     * looks -- FOGPage::__construct() loads $this->obj from the URL id
     * and REDIRECTS when it does not resolve, so constructing the plugin
     * page from the role page would bounce the browser to the LDAP group
     * list whenever no LDAP group happened to share the role's id.
     *
     * @param object $obj  the role or user group being edited
     * @param string $node the node being edited
     *
     * @return void
     */
    public function renderTab($obj, $node)
    {
        $slug = $node . '-ldapgroup';
        $isRole = ('role' === $node);

        $props = ' method="post" action="'
            . FOGPage::makeTabUpdateURL($slug, $obj->get('id'))
            . '" ';
        $buttons = FOGPage::makeButton(
            "$slug-send",
            _('Add selected'),
            'btn btn-primary float-end',
            $props
        );
        $buttons .= FOGPage::makeButton(
            "$slug-remove",
            _('Remove selected'),
            'btn btn-danger float-start',
            $props
        );

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('LDAP Group Associations');
        echo '</h4>';
        echo '<p class="form-text">';
        echo(
            $isRole ?
            _(
                'Anyone signing in through one of these directory groups '
                . 'receives this role. Roles granted this way are '
                . 'recomputed on every sign in.'
            ) :
            _(
                'Anyone signing in through one of these directory groups '
                . 'is placed in this user group. Membership granted this '
                . 'way is recomputed on every sign in.'
            )
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<table id="' . $slug . '-table" '
            . 'class="display table table-bordered table-striped">';
        echo '<thead><tr class="header">';
        echo '<th data-column="0" scope="col">'
            . _('Directory Group')
            . '</th>';
        echo '<th data-column="1" scope="col">'
            . _('LDAP Server')
            . '</th>';
        echo '<th width="16" data-column="2" scope="col">'
            . _('Associated')
            . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        // After the table, not before: FOGPage::process() returns the
        // table markup followed by the actionbox, so every other
        // association tab carries its buttons below the grid.
        echo '<div class="btn-actionbox">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo FOGPage::makeModal(
            'ldapgroupDelModal',
            _('Remove LDAP Group Associations'),
            _(
                'Please confirm you would like to dissociate the selected '
                . 'directory groups'
            ),
            FOGPage::makeButton(
                'closeldapgroupDeleteModal',
                _('Cancel'),
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . FOGPage::makeButton(
                'confirmldapgroupDeleteModal',
                _('Remove'),
                'btn btn-outline-secondary float-end'
            ),
            '',
            'warning'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Applies an add/remove posted from the injected tab.
     *
     * The association is written through the LDAPGroup entity rather than
     * by batch-inserting rows, so this shares the deduplication and the
     * cascade the LDAP Group page already goes through. The owner is the
     * role or user group, so the loop is over the selected groups.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function editSuccess($arguments)
    {
        global $node;
        global $tab;
        if (!array_key_exists($node, self::TARGETS)) {
            return;
        }
        if ($tab !== $node . '-ldapgroup') {
            return;
        }
        self::checkAuthAndCSRF();
        // Same reasoning as the render gate: this writes an ldapgroup
        // association, so it takes ldapgroup.edit rather than riding in on
        // the role/usergroup edit permission that reached this POST.
        if (!Authorization::can('ldapgroup.edit')) {
            $arguments['code'] = HTTPResponseCodes::HTTP_FORBIDDEN;
            $arguments['msg'] = json_encode(
                [
                    'error' => _(
                        'You do not have permission to change LDAP group '
                        . 'associations.'
                    ),
                    'title' => _('LDAP Group Update Fail')
                ]
            );
            return;
        }
        $target = self::TARGETS[$node];
        $obj = (
            isset($arguments['Role']) ?
            $arguments['Role'] :
            $arguments['UserGroup']
        );
        $ownerID = (int)$obj->get('id');
        if ($ownerID < 1) {
            return;
        }

        $method = '';
        $items = [];
        if (isset($_POST['confirmadd'])) {
            $method = $target['add'];
            $items = filter_input_array(
                INPUT_POST,
                ['additems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $items = $items['additems'];
        } elseif (isset($_POST['confirmdel'])) {
            $method = $target['remove'];
            $items = filter_input_array(
                INPUT_POST,
                ['remitems' => ['flags' => FILTER_REQUIRE_ARRAY]]
            );
            $items = $items['remitems'];
        }
        if ($method === '') {
            return;
        }

        foreach (self::positiveIntIds($items) as $groupID) {
            $group = self::getClass($target['owner'], $groupID);
            if (!$group->isValid()) {
                continue;
            }
            $group->{$method}([$ownerID]);
            $group->save();
        }
        // A change here changes who has what, and the answer is cached.
        Authorization::resetCache();

        $arguments['msg'] = json_encode(
            [
                'msg' => _('LDAP Group associations updated!'),
                'title' => _('LDAP Group Update Success')
            ]
        );
    }
}
