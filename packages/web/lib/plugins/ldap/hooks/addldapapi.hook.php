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
        if (!in_array($this->node, self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
        )->register(
            'API_MASSDATA_MAPPING',
            [$this, 'adjustMassData']
        );
    }
    /**
     * Remove the users with ldap types from the list.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustMassData($arguments)
    {
        if ($arguments['classname'] != 'user') {
            return;
        }
        $where = "`users`.`uType` NOT IN ('"
            . implode("','", LDAPPluginHook::LDAP_TYPES)
            . "')";

        $arguments['ttlstr'] .= " WHERE $where";

        $arguments['data'] = FOGManagerController::complex(
            $arguments['pass_vars'],
            $arguments['table'],
            $arguments['tableID'],
            $arguments['columns'],
            $arguments['sqlstr'],
            $arguments['fltrstr'],
            $arguments['ttlstr'],
            $where
        );
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
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            ['ldap']
        );
    }
}
