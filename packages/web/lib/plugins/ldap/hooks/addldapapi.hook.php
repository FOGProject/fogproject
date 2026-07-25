<?php
/**
 * Injects ldap stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddLDAPAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects LDAP stuff into the api system.
 *
 * @category AddLDAPAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLDAPAPI extends Hook
{
    /**
     * Add LDAP API
     *
     * @var string
     */
    public $name = 'AddLDAPAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add LDAP stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to work with.
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
        // API_MASSDATA_MAPPING/adjustMassData is no longer registered. It
        // hid every LDAP user from User Management, which made sense while
        // an LDAP user was an unmanageable shadow row, but they now hold
        // real roles an admin needs to be able to see and change. Hiding
        // them also meant the accounts with the most access on an install
        // were the only ones absent from the user list.
        //
        // It carried a latent bug too: it appended " WHERE ..." to ttlstr
        // unconditionally, so stacking it with another plugin that filters
        // the same list (the site plugin) produced two WHERE clauses in one
        // statement.
        $this->registerInstalled([
            ['API_VALID_CLASSES', 'injectAPIElements'],
            ['CUSTOMIZE_DT_COLUMNS', 'customizeDT'],
        ]);
    }
    /**
     * Adds the owning server column to the LDAP group list.
     *
     * The list JSON is built from the table's own columns, so a group row
     * carries lgServerID and no server name. Route only knows how to turn
     * a handful of core id columns into a link, and lgServerID is not one
     * of them -- without this the datatable asks for a column the payload
     * never had and every visit to the list opens a DataTables warning
     * alert.
     *
     * Done through this hook rather than by adding a case to Route so a
     * plugin concern stays in the plugin. A 'serverID' case in core would
     * also be a global claim on a very generic column name; any future
     * entity with its own serverID would inherit a link to an LDAP server.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != 'ldapgroup') {
            return;
        }
        $arguments['columns'][] = [
            'db' => 'lgServerID',
            'dt' => 'ldapserver',
            'formatter' => function ($d, $row) {
                if (!$d) {
                    return Route::EMPTY_CELL;
                }
                $name = self::getClass('LDAP', $d)->get('name');
                // A group outliving its server should still list, not fatal.
                if (!$name) {
                    return Route::EMPTY_CELL;
                }
                return '<a href="../management/index.php?node=ldap&'
                    . 'sub=edit&id='
                    . $d
                    . '">'
                    . '(' . $d . ') - ' . Initiator::e($name)
                    . '</a>';
            }
        ];
    }
    /**
     * This function injects site elements for
     * api access.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectAPIElements($arguments)
    {
        array_push(
            $arguments['validClasses'],
            $this->node,
            'ldapgroup',
            'ldapgrouproleassociation',
            'ldapgroupusergroupassociation',
            'ldapusergrant'
        );
    }
}
