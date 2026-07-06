<?php
/**
 * Exposes the site plugin's objects to the REST API.
 *
 * PHP version 5
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Exposes the site plugin's objects to the REST API.
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddSiteAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Site stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['API_VALID_CLASSES', 'injectAPIElements'],
            ['CUSTOMIZE_DT_COLUMNS', 'customizeDT'],
        ]);
    }
    /**
     * This adjusts our DT columns for display.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != $this->node) {
            return;
        }
        $arguments['columns'][] = [
            'db' => 'shaMembers',
            'dt' => 'hostcount',
            'removeFromQuery' => true
        ];
        $arguments['columns'][] = [
            'db' => 'suaMembers',
            'dt' => 'usercount',
            'removeFromQuery' => true
        ];
        $arguments['columns'][] = [
            'db' => 'sgaMembers',
            'dt' => 'groupcount',
            'removeFromQuery' => true
        ];
        $arguments['columns'][] = [
            'db' => 'sugaMembers',
            'dt' => 'usergroupcount',
            'removeFromQuery' => true
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
            'sitehostassociation',
            'siteuserassociation',
            'sitegroupassociation',
            'siteusergroupassociation'
        );
    }
}
