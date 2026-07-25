<?php
/**
 * Shows which directory groups feed a role or a user group.
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
 * Shows which directory groups feed a role or a user group.
 *
 * The authoring direction lives on the LDAP Group page, because that is
 * where the association owner is. This is the reading direction, and it
 * belongs here because "why does this person have this access?" is asked
 * while looking at the role or the group, not while looking at LDAP.
 *
 * Read-only on purpose. Offering an edit control on both ends of the same
 * association invites two pages to disagree about what was saved, and the
 * owning page already does the job.
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
    public $description = 'Show the directory groups feeding a role or user group';
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
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['PLUGINS_INJECT_TABDATA', 'injectTabData'],
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
        if (!in_array($node, ['role', 'usergroup'])) {
            return;
        }
        $obj = $arguments['obj'];
        $isRole = ('role' === $node);
        $arguments['pluginsTabData'][] = [
            'name' => _('LDAP Groups'),
            'id' => $node . '-ldapgroup',
            'generator' => function () use ($obj, $isRole) {
                $this->renderFeedingGroups($obj, $isRole);
            }
        ];
    }
    /**
     * The directory groups granting this role or user group.
     *
     * Raw bound SQL rather than Route::getIds(): the join is across three
     * tables, and this is the same access pattern the rest of the feature
     * uses to stay clear of _buildSql()'s wildcard rewriting.
     *
     * @param object $obj    the role or user group being edited
     * @param bool   $isRole whether the target is a role
     *
     * @return array
     */
    private function feedingGroups($obj, $isRole)
    {
        if ($isRole) {
            $sql = 'SELECT `lgID`, `lgName`, `lsName` '
                . 'FROM `ldapGroupRoleAssoc` '
                . 'INNER JOIN `LDAPGroups` ON `lgID` = `lgraGroupID` '
                . 'LEFT JOIN `LDAPServers` ON `lsID` = `lgServerID` '
                . 'WHERE `lgraRoleID` = :target ORDER BY `lgName`';
        } else {
            $sql = 'SELECT `lgID`, `lgName`, `lsName` '
                . 'FROM `ldapGroupUserGroupAssoc` '
                . 'INNER JOIN `LDAPGroups` ON `lgID` = `lgugGroupID` '
                . 'LEFT JOIN `LDAPServers` ON `lsID` = `lgServerID` '
                . 'WHERE `lgugUserGroupID` = :target ORDER BY `lgName`';
        }
        try {
            $rows = self::$DB
                ->query($sql, [], ['target' => (int)$obj->get('id')])
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            return [];
        }
        /**
         * PDODB reports a failed query as false rather than throwing
         * (throwOnQueryError is off), and (array)false is [false], not [].
         */
        return is_array($rows) ? $rows : [];
    }
    /**
     * Renders the tab body.
     *
     * @param object $obj    the role or user group being edited
     * @param bool   $isRole whether the target is a role
     *
     * @return void
     */
    public function renderFeedingGroups($obj, $isRole)
    {
        $groups = $this->feedingGroups($obj, $isRole);

        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<p>';
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
        echo '<table class="table table-striped">';
        echo '<thead><tr>';
        echo '<th>' . _('Directory Group') . '</th>';
        echo '<th>' . _('LDAP Server') . '</th>';
        echo '</tr></thead><tbody>';
        if (empty($groups)) {
            printf(
                '<tr><td colspan="2">%s</td></tr>',
                Initiator::e(
                    $isRole ?
                    _('No directory group grants this role.') :
                    _('No directory group feeds this user group.')
                )
            );
        }
        foreach ($groups as $group) {
            printf(
                '<tr><td><a href="?node=ldapgroup&sub=edit&id=%s">%s</a>'
                . '</td><td>%s</td></tr>',
                Initiator::e($group['lgID']),
                Initiator::e($group['lgName']),
                Initiator::e($group['lsName'] ?? '')
            );
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }
}
